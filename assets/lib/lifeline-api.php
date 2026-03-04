<?php
/**
 * LifeLine Panel API
 * Handles all API requests for the LifeLine Panel system
 */

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/openconn.php';
require_once __DIR__ . '/ProfileManager.php';

header('Content-Type: application/json');

$profileManager = new ProfileManager($conn);

// Check if user is logged in
if (!$profileManager->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

// Handle JSON input
$jsonInput = json_decode(file_get_contents('php://input'), true);
if ($jsonInput) {
    $_POST = array_merge($_POST, $jsonInput);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Helper function to send JSON response
 */
function respond($success, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(array_merge(['success' => $success], $data));
    exit();
}

/**
 * Ensure user has recipient role
 */
function ensureRecipient($profileManager) {
    if (!$profileManager->hasRole('recipient')) {
        respond(false, ['error' => 'Recipient role required'], 403);
    }
}

/**
 * Find matching donors by blood type and city
 */
function findMatchingDonors($conn, $bloodType, $city) {
    $donors = [];
    
    // Find donors with matching blood type and city (case-insensitive partial match)
    // Exclude donors who donated in the last 4 months (not eligible for matching/invites)
    $query = "
        SELECT d.user_id, d.blood_type, d.location,
               u.first_name, u.last_name, u.email
        FROM donors d
        INNER JOIN users u ON d.user_id = u.user_id
        WHERE d.blood_type = ?
          AND (d.last_donation_date IS NULL OR d.last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 4 MONTH))
          AND (? = '' OR LOWER(d.location) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', d.location, '%')))
        ORDER BY 
            CASE WHEN ? != '' AND (LOWER(d.location) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', d.location, '%'))) THEN 1 ELSE 2 END,
            (CASE WHEN d.last_donation_date IS NULL THEN 0 ELSE 1 END),
            d.last_donation_date ASC
        LIMIT 20
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssss", $bloodType, $city, $city, $city, $city, $city, $city);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $donors[] = $row;
    }
    $stmt->close();
    
    return $donors;
}

/**
 * Notify matching donors about a new request
 */
function notifyMatchingDonors($conn, $requestId, $bloodType, $city) {
    require_once __DIR__ . '/email-helper.php';
    
    $donors = findMatchingDonors($conn, $bloodType, $city);
    $notifiedCount = 0;
    
    // Get request details for email
    $reqStmt = $conn->prepare("SELECT lr.*, lp.full_name, lp.city, lp.contact_number_primary FROM lifeline_requests lr INNER JOIN lifeline_profiles lp ON lp.recipient_id = lr.recipient_id WHERE lr.id = ? LIMIT 1");
    $reqStmt->bind_param("i", $requestId);
    $reqStmt->execute();
    $reqResult = $reqStmt->get_result();
    $requestData = $reqResult->fetch_assoc();
    $reqStmt->close();
    
    foreach ($donors as $donor) {
        // Check if notification already exists
        $checkStmt = $conn->prepare("SELECT id FROM lifeline_notifications WHERE request_id = ? AND donor_id = ? LIMIT 1");
        $checkStmt->bind_param("is", $requestId, $donor['user_id']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            // Create notification in lifeline_notifications table
            $notifStmt = $conn->prepare("INSERT INTO lifeline_notifications (request_id, donor_id, status) VALUES (?, ?, 'sent')");
            $notifStmt->bind_param("is", $requestId, $donor['user_id']);
            $notifStmt->execute();
            $notifStmt->close();
            
            // Create notification in emergency_notifications table for notification bell
            $payload = json_encode([
                'request_id' => $requestId,
                'blood_type' => $bloodType,
                'city' => $city,
                'type' => 'lifeline'
            ]);
            $bellNotifStmt = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'lifeline_new_request', ?, 'queued')");
            $bellNotifStmt->bind_param("ss", $donor['user_id'], $payload);
            $bellNotifStmt->execute();
            $bellNotifStmt->close();
            
            // Send email to donor
            if (!empty($donor['email'])) {
                try {
                    $mail = getConfiguredMailer();
                    $mail->addAddress($donor['email'], ($donor['first_name'] ?? '') . ' ' . ($donor['last_name'] ?? ''));
                    $mail->Subject = 'New LifeLine Blood Donation Request - ' . $bloodType;
                    $mail->isHTML(true);
                    
                    $baseUrl = rtrim(env('BASE_URL', 'http://localhost/blood_konnector'), '/');
                    $viewUrl = $baseUrl . '/lifeline-donor-requests';
                    
                    $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width:600px; margin:20px auto; border:1px solid #eee; border-radius:8px; overflow:hidden; box-shadow:0 0 8px rgba(0,0,0,0.05);">
                        <div style="background-color:#EA062B; color:white; padding:20px; text-align:center;">
                            <h2 style="margin:0; color:white;">New LifeLine Blood Request</h2>
                        </div>
                        <div style="padding:20px; color:#333;">
                            <p style="margin:10px 0;">Hi <strong>' . htmlspecialchars($donor['first_name'] ?? 'Donor') . '</strong>,</p>
                            <p style="margin:10px 0;">A new LifeLine blood donation request has been created that matches your profile:</p>
                            <div style="background:#f8f9fa; padding:15px; border-radius:6px; margin:20px 0;">
                                <p style="margin:5px 0;"><strong>Blood Type:</strong> ' . htmlspecialchars($bloodType) . '</p>
                                <p style="margin:5px 0;"><strong>Location:</strong> ' . htmlspecialchars($city) . '</p>
                                <p style="margin:5px 0;"><strong>Recipient:</strong> ' . htmlspecialchars($requestData['full_name'] ?? 'N/A') . '</p>
                            </div>
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="' . $viewUrl . '" target="_blank" style="background-color:#EA062B; color:#ffffff; padding:14px 28px; border-radius:6px; text-decoration:none; font-weight:bold; display:inline-block; font-size:16px;">
                                            View Request
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin-top:30px; font-size:13px; color:#666;">Regards,<br><strong>The Blood Konnector Team</strong></p>
                        </div>
                    </div>';
                    
                    $mail->AltBody = "Hi " . ($donor['first_name'] ?? 'Donor') . ",\n\nA new LifeLine blood donation request has been created.\n\nBlood Type: " . $bloodType . "\nLocation: " . $city . "\n\nView the request: " . $viewUrl . "\n\nRegards,\nThe Blood Konnector Team";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("LifeLine email failed for donor {$donor['user_id']}: " . $e->getMessage());
                }
            }
            
            $notifiedCount++;
        }
        $checkStmt->close();
    }
    
    return $notifiedCount;
}

// Handle file upload
function handleFileUpload($fileInput, $prefix = 'lifeline') {
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $file = $_FILES[$fileInput];
    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'image/jpg'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Check file extension as well
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    
    if (!in_array($extension, $allowedExtensions)) {
        return null;
    }
    
    if ($file['size'] > $maxSize) {
        return null;
    }
    
    // Get the project root directory (two levels up from assets/lib)
    $projectRoot = dirname(dirname(__DIR__));
    $uploadDir = $projectRoot . '/uploads/lifeline_documents/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = $prefix . '_' . uniqid('', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Return relative path from project root
        return 'uploads/lifeline_documents/' . $filename;
    }
    
    return null;
}

// Route actions
switch ($action) {
    case 'save_profile':
        ensureRecipient($profileManager);
        
        // Personal Information
        $fullName = trim($_POST['full_name'] ?? '');
        $cnicNationalId = trim($_POST['cnic_national_id'] ?? '');
        $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $bloodType = trim($_POST['blood_type'] ?? '');
        $contactPrimary = trim($_POST['contact_number_primary'] ?? '');
        $contactAlternate = trim($_POST['contact_number_alternate'] ?? '');
        $emailAddress = trim($_POST['email_address'] ?? '');
        $residentialAddress = trim($_POST['residential_address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $provinceState = trim($_POST['province_state'] ?? '');
        
        // Medical Information
        $hospitalClinicName = trim($_POST['hospital_clinic_name'] ?? '');
        $doctorConsultantName = trim($_POST['doctor_consultant_name'] ?? '');
        $hospitalContactNumber = trim($_POST['hospital_contact_number'] ?? '');
        $healthCondition = trim($_POST['health_condition'] ?? '');
        $frequencyOfRequirement = trim($_POST['frequency_of_requirement'] ?? 'on-demand');
        $averageUnitsPerSession = (int)($_POST['average_units_per_session'] ?? 1);
        $preferredDonorType = trim($_POST['preferred_donor_type'] ?? 'any');
        $specialInstructions = trim($_POST['special_instructions'] ?? '');
        
        // Emergency & Verification
        $emergencyContactName = trim($_POST['emergency_contact_name'] ?? '');
        $emergencyContactRelation = trim($_POST['emergency_contact_relation'] ?? '');
        $emergencyContactNumber = trim($_POST['emergency_contact_number'] ?? '');
        
        // Consent & Declaration - all checkboxes must be checked
        $consentDeclaration = (isset($_POST['consent_declaration']) && isset($_POST['consent_storage']) && 
                               isset($_POST['consent_contact']) && isset($_POST['consent_liability'])) ? 1 : 0;
        $declarationDate = trim($_POST['declaration_date'] ?? date('Y-m-d'));
        
        // Validation
        if (empty($fullName) || empty($cnicNationalId) || empty($dateOfBirth) || empty($gender) || 
            empty($bloodType) || empty($contactPrimary) || empty($residentialAddress) || 
            empty($city) || empty($provinceState) || empty($emergencyContactName) || 
            empty($emergencyContactRelation) || empty($emergencyContactNumber)) {
            respond(false, ['error' => 'All required fields must be filled']);
        }
        
        if (!$consentDeclaration) {
            respond(false, ['error' => 'You must agree to all consent declarations']);
        }
        
        // Check if profile already exists
        $checkStmt = $conn->prepare("SELECT verification_letter_path, cnic_copy_path, medical_proof_path, signature_name FROM lifeline_profiles WHERE recipient_id = ? LIMIT 1");
        $checkStmt->bind_param("s", $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $existingProfile = $checkResult->num_rows > 0 ? $checkResult->fetch_assoc() : null;
        $checkStmt->close();
        
        // Handle file uploads
        $verificationLetterPath = handleFileUpload('verification_letter', 'verification');
        $cnicCopyPath = handleFileUpload('cnic_copy', 'cnic');
        $medicalProofPath = handleFileUpload('medical_proof', 'medical');
        
        // Signature is now a text field (name)
        $signatureName = trim($_POST['signature_name'] ?? '');
        
        // For new profiles, required files must be uploaded
        if (!$existingProfile) {
            if (empty($verificationLetterPath)) {
                respond(false, ['error' => 'Hospital/Doctor Verification Letter is required']);
            }
            if (empty($cnicCopyPath)) {
                respond(false, ['error' => 'CNIC Copy is required']);
            }
            if (empty($signatureName)) {
                respond(false, ['error' => 'Signature name is required']);
            }
        } else {
            // For updates, use existing paths if new files not uploaded
            // First check hidden input fields (sent from form), then fallback to database
            if (empty($verificationLetterPath)) {
                $existingVerification = trim($_POST['existing_verification_letter'] ?? '');
                $verificationLetterPath = !empty($existingVerification) ? $existingVerification : $existingProfile['verification_letter_path'];
            }
            if (empty($cnicCopyPath)) {
                $existingCnic = trim($_POST['existing_cnic_copy'] ?? '');
                $cnicCopyPath = !empty($existingCnic) ? $existingCnic : $existingProfile['cnic_copy_path'];
            }
            if (empty($medicalProofPath)) {
                $existingMedical = trim($_POST['existing_medical_proof'] ?? '');
                $medicalProofPath = !empty($existingMedical) ? $existingMedical : ($existingProfile['medical_proof_path'] ?? null);
            }
            // For signature name, use new value if provided, otherwise keep existing
            if (empty($signatureName)) {
                $signatureName = $existingProfile['signature_name'] ?? '';
            }
            
            // Validate that required files still exist (either new upload or existing)
            if (empty($verificationLetterPath)) {
                respond(false, ['error' => 'Hospital/Doctor Verification Letter is required']);
            }
            if (empty($cnicCopyPath)) {
                respond(false, ['error' => 'CNIC Copy is required']);
            }
        }
        
        if ($existingProfile) {
            $updateStmt = $conn->prepare("UPDATE lifeline_profiles SET 
                full_name = ?, cnic_national_id = ?, date_of_birth = ?, gender = ?, blood_type = ?,
                contact_number_primary = ?, contact_number_alternate = ?, email_address = ?,
                residential_address = ?, city = ?, province_state = ?,
                hospital_clinic_name = ?, doctor_consultant_name = ?, hospital_contact_number = ?,
                health_condition = ?, frequency_of_requirement = ?, average_units_per_session = ?,
                preferred_donor_type = ?, special_instructions = ?,
                emergency_contact_name = ?, emergency_contact_relation = ?, emergency_contact_number = ?,
                verification_letter_path = ?, cnic_copy_path = ?, medical_proof_path = ?,
                consent_declaration = ?, signature_name = ?, declaration_date = ?
                WHERE recipient_id = ?");
            $updateStmt->bind_param(
                "ssssssssssssssssissssssssisss",
                $fullName,
                $cnicNationalId,
                $dateOfBirth,
                $gender,
                $bloodType,
                $contactPrimary,
                $contactAlternate,
                $emailAddress,
                $residentialAddress,
                $city,
                $provinceState,
                $hospitalClinicName,
                $doctorConsultantName,
                $hospitalContactNumber,
                $healthCondition,
                $frequencyOfRequirement,
                $averageUnitsPerSession,
                $preferredDonorType,
                $specialInstructions,
                $emergencyContactName,
                $emergencyContactRelation,
                $emergencyContactNumber,
                $verificationLetterPath,
                $cnicCopyPath,
                $medicalProofPath,
                $consentDeclaration,
                $signatureName,
                $declarationDate,
                $userId
            );
            
            if (!$updateStmt->execute()) {
                $updateStmt->close();
                respond(false, ['error' => 'Failed to update profile: ' . $conn->error]);
            }
            $updateStmt->close();
        } else {
            // Insert new profile
            // Count: 29 parameters total
            $insertStmt = $conn->prepare("INSERT INTO lifeline_profiles (
                recipient_id, full_name, cnic_national_id, date_of_birth, gender, blood_type,
                contact_number_primary, contact_number_alternate, email_address,
                residential_address, city, province_state,
                hospital_clinic_name, doctor_consultant_name, hospital_contact_number,
                health_condition, frequency_of_requirement, average_units_per_session,
                preferred_donor_type, special_instructions,
                emergency_contact_name, emergency_contact_relation, emergency_contact_number,
                verification_letter_path, cnic_copy_path, medical_proof_path,
                consent_declaration, signature_name, declaration_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("ssssssssssssssssissssssssssss", 
                $userId, $fullName, $cnicNationalId, $dateOfBirth, $gender, $bloodType,
                $contactPrimary, $contactAlternate, $emailAddress,
                $residentialAddress, $city, $provinceState,
                $hospitalClinicName, $doctorConsultantName, $hospitalContactNumber,
                $healthCondition, $frequencyOfRequirement, $averageUnitsPerSession,
                $preferredDonorType, $specialInstructions,
                $emergencyContactName, $emergencyContactRelation, $emergencyContactNumber,
                $verificationLetterPath, $cnicCopyPath, $medicalProofPath,
                $consentDeclaration, $signatureName, $declarationDate);
            
            if (!$insertStmt->execute()) {
                $insertStmt->close();
                respond(false, ['error' => 'Failed to save profile: ' . $conn->error]);
            }
            $insertStmt->close();
        }
        
        respond(true, ['message' => 'Profile saved successfully']);
        break;
        
    case 'generate_request':
        ensureRecipient($profileManager);
        
        // Get recipient's lifeline profile
        $profileStmt = $conn->prepare("SELECT * FROM lifeline_profiles WHERE recipient_id = ? LIMIT 1");
        $profileStmt->bind_param("s", $userId);
        $profileStmt->execute();
        $profileResult = $profileStmt->get_result();
        
        if ($profileResult->num_rows === 0) {
            $profileStmt->close();
            respond(false, ['error' => 'Please complete your LifeLine profile first']);
        }
        
        $profile = $profileResult->fetch_assoc();
        $profileStmt->close();
        
        $bloodType = $profile['blood_type'];
        $city = $profile['city'];
        $urgency = $_POST['urgency'] ?? 'normal';
        $note = trim($_POST['note'] ?? '');
        
        // Create request
        $insertStmt = $conn->prepare("INSERT INTO lifeline_requests (recipient_id, blood_type, city, urgency, note, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $insertStmt->bind_param("sssss", $userId, $bloodType, $city, $urgency, $note);
        
        if (!$insertStmt->execute()) {
            $insertStmt->close();
            respond(false, ['error' => 'Failed to create request']);
        }
        
        $requestId = $conn->insert_id;
        $insertStmt->close();
        
        // Notify matching donors
        $notifiedCount = notifyMatchingDonors($conn, $requestId, $bloodType, $city);
        
        respond(true, [
            'request_id' => $requestId,
            'notified_donors' => $notifiedCount,
            'message' => 'Request created successfully'
        ]);
        break;
        
    case 'complete_request':
        ensureRecipient($profileManager);
        
        $requestId = (int)($_POST['request_id'] ?? 0);
        
        if ($requestId <= 0) {
            respond(false, ['error' => 'Invalid request ID']);
        }
        
        // Verify request belongs to recipient
        $verifyStmt = $conn->prepare("SELECT id FROM lifeline_requests WHERE id = ? AND recipient_id = ? LIMIT 1");
        $verifyStmt->bind_param("is", $requestId, $userId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows === 0) {
            $verifyStmt->close();
            respond(false, ['error' => 'Request not found or access denied']);
        }
        $verifyStmt->close();
        
        // Update request status
        $updateStmt = $conn->prepare("UPDATE lifeline_requests SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $requestId);
        
        if ($updateStmt->execute()) {
            $updateStmt->close();
            respond(true, ['message' => 'Request marked as completed']);
        } else {
            $updateStmt->close();
            respond(false, ['error' => 'Failed to update request']);
        }
        break;
        
    case 'cancel_request':
        ensureRecipient($profileManager);
        
        $requestId = (int)($_POST['request_id'] ?? 0);
        
        if ($requestId <= 0) {
            respond(false, ['error' => 'Invalid request ID']);
        }
        
        // Verify request belongs to recipient
        $verifyStmt = $conn->prepare("SELECT id FROM lifeline_requests WHERE id = ? AND recipient_id = ? LIMIT 1");
        $verifyStmt->bind_param("is", $requestId, $userId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows === 0) {
            $verifyStmt->close();
            respond(false, ['error' => 'Request not found or access denied']);
        }
        $verifyStmt->close();
        
        // Update request status
        $updateStmt = $conn->prepare("UPDATE lifeline_requests SET status = 'cancelled' WHERE id = ?");
        $updateStmt->bind_param("i", $requestId);
        
        if ($updateStmt->execute()) {
            $updateStmt->close();
            respond(true, ['message' => 'Request cancelled']);
        } else {
            $updateStmt->close();
            respond(false, ['error' => 'Failed to cancel request']);
        }
        break;
        
    case 'donor_accept':
        // Donor accepting a request
        if (!$profileManager->hasRole('donor')) {
            respond(false, ['error' => 'Donor role required'], 403);
        }
        
        $requestId = (int)($_POST['request_id'] ?? 0);
        
        if ($requestId <= 0) {
            respond(false, ['error' => 'Invalid request ID']);
        }
        
        // Check if request exists and is pending
        $checkStmt = $conn->prepare("SELECT id, status, accepted_donor_id FROM lifeline_requests WHERE id = ? LIMIT 1");
        $checkStmt->bind_param("i", $requestId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            $checkStmt->close();
            respond(false, ['error' => 'Request not found']);
        }
        
        $request = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        if ($request['status'] !== 'pending') {
            respond(false, ['error' => 'Request is no longer available']);
        }
        
        if (!empty($request['accepted_donor_id']) && $request['accepted_donor_id'] !== $userId) {
            respond(false, ['error' => 'Request has already been accepted by another donor']);
        }
        
        // Update request to accepted
        $updateStmt = $conn->prepare("UPDATE lifeline_requests SET status = 'accepted', accepted_donor_id = ?, accepted_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("si", $userId, $requestId);
        
        if (!$updateStmt->execute()) {
            $updateStmt->close();
            respond(false, ['error' => 'Failed to accept request']);
        }
        $updateStmt->close();
        
        // Update notification status
        $notifStmt = $conn->prepare("UPDATE lifeline_notifications SET status = 'accepted', responded_at = NOW() WHERE request_id = ? AND donor_id = ?");
        $notifStmt->bind_param("is", $requestId, $userId);
        $notifStmt->execute();
        $notifStmt->close();
        
        // Create donor response record
        $responseStmt = $conn->prepare("INSERT INTO lifeline_donor_responses (request_id, donor_id, response, message) VALUES (?, ?, 'accept', ?) ON DUPLICATE KEY UPDATE response = 'accept', message = ?");
        $message = trim($_POST['message'] ?? '');
        $responseStmt->bind_param("isss", $requestId, $userId, $message, $message);
        $responseStmt->execute();
        $responseStmt->close();
        
        // Get recipient and donor details for notification and email
        $detailsStmt = $conn->prepare("
            SELECT lr.recipient_id, lp.full_name AS recipient_name, lp.email_address AS recipient_email,
                   u.first_name AS donor_first, u.last_name AS donor_last, u.email AS donor_email
            FROM lifeline_requests lr
            INNER JOIN lifeline_profiles lp ON lp.recipient_id = lr.recipient_id
            INNER JOIN users u ON u.user_id = ?
            WHERE lr.id = ? LIMIT 1
        ");
        $detailsStmt->bind_param("si", $userId, $requestId);
        $detailsStmt->execute();
        $detailsResult = $detailsStmt->get_result();
        $details = $detailsResult->fetch_assoc();
        $detailsStmt->close();
        
        // Create notification for recipient in emergency_notifications table
        if ($details && !empty($details['recipient_id'])) {
            require_once __DIR__ . '/email-helper.php';
            
            $payload = json_encode([
                'request_id' => $requestId,
                'donor_id' => $userId,
                'type' => 'lifeline'
            ]);
            $recipientNotifStmt = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'lifeline_donor_approved', ?, 'queued')");
            $recipientNotifStmt->bind_param("ss", $details['recipient_id'], $payload);
            $recipientNotifStmt->execute();
            $recipientNotifStmt->close();
            
            // Send email to recipient
            if (!empty($details['recipient_email'])) {
                try {
                    $mail = getConfiguredMailer();
                    $mail->addAddress($details['recipient_email'], $details['recipient_name']);
                    $mail->Subject = 'LifeLine Request Accepted - Donor Available';
                    $mail->isHTML(true);
                    
                    $baseUrl = rtrim(env('BASE_URL', 'http://localhost/blood_konnector'), '/');
                    $viewUrl = $baseUrl . '/lifeline-panel';
                    $donorName = trim(($details['donor_first'] ?? '') . ' ' . ($details['donor_last'] ?? ''));
                    
                    $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width:600px; margin:20px auto; border:1px solid #eee; border-radius:8px; overflow:hidden; box-shadow:0 0 8px rgba(0,0,0,0.05);">
                        <div style="background-color:#EA062B; color:white; padding:20px; text-align:center;">
                            <h2 style="margin:0; color:white;">Request Accepted!</h2>
                        </div>
                        <div style="padding:20px; color:#333;">
                            <p style="margin:10px 0;">Hi <strong>' . htmlspecialchars($details['recipient_name']) . '</strong>,</p>
                            <p style="margin:10px 0;">Great news! A donor has accepted your LifeLine blood donation request.</p>
                            <div style="background:#f8f9fa; padding:15px; border-radius:6px; margin:20px 0;">
                                <p style="margin:5px 0;"><strong>Donor:</strong> ' . htmlspecialchars($donorName) . '</p>
                                <p style="margin:5px 0;">You can now contact the donor through the chat system to coordinate the donation.</p>
                            </div>
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="' . $viewUrl . '" target="_blank" style="background-color:#EA062B; color:#ffffff; padding:14px 28px; border-radius:6px; text-decoration:none; font-weight:bold; display:inline-block; font-size:16px;">
                                            View Request
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin-top:30px; font-size:13px; color:#666;">Regards,<br><strong>The Blood Konnector Team</strong></p>
                        </div>
                    </div>';
                    
                    $mail->AltBody = "Hi " . $details['recipient_name'] . ",\n\nGreat news! A donor has accepted your LifeLine blood donation request.\n\nDonor: " . $donorName . "\n\nView the request: " . $viewUrl . "\n\nRegards,\nThe Blood Konnector Team";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("LifeLine acceptance email failed for recipient {$details['recipient_id']}: " . $e->getMessage());
                }
            }
        }
        
        respond(true, ['message' => 'Request accepted successfully']);
        break;
        
    case 'donor_decline':
        // Donor declining a request
        if (!$profileManager->hasRole('donor')) {
            respond(false, ['error' => 'Donor role required'], 403);
        }
        
        $requestId = (int)($_POST['request_id'] ?? 0);
        
        if ($requestId <= 0) {
            respond(false, ['error' => 'Invalid request ID']);
        }
        
        // Update notification status
        $notifStmt = $conn->prepare("UPDATE lifeline_notifications SET status = 'declined', responded_at = NOW() WHERE request_id = ? AND donor_id = ?");
        $notifStmt->bind_param("is", $requestId, $userId);
        $notifStmt->execute();
        $notifStmt->close();
        
        // Create donor response record
        $responseStmt = $conn->prepare("INSERT INTO lifeline_donor_responses (request_id, donor_id, response, message) VALUES (?, ?, 'decline', ?) ON DUPLICATE KEY UPDATE response = 'decline', message = ?");
        $message = trim($_POST['message'] ?? '');
        $responseStmt->bind_param("isss", $requestId, $userId, $message, $message);
        $responseStmt->execute();
        $responseStmt->close();
        
        respond(true, ['message' => 'Request declined']);
        break;
        
    default:
        respond(false, ['error' => 'Invalid action'], 400);
        break;
}
