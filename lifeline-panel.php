<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('recipient', 'sign-in.php');
$userId = $_SESSION['user_id'];

// Check if lifeline profile exists
$profileExists = false;
$lifelineProfile = null;
$editMode = isset($_GET['edit']) && $_GET['edit'] == '1';
$profileStmt = $conn->prepare("SELECT * FROM lifeline_profiles WHERE recipient_id = ? LIMIT 1");
$profileStmt->bind_param("s", $userId);
$profileStmt->execute();
$profileResult = $profileStmt->get_result();
if ($profileResult && $profileResult->num_rows > 0) {
    $lifelineProfile = $profileResult->fetch_assoc();
    $profileExists = true;
}
$profileStmt->close();

// Fetch lifeline requests for this recipient
$requests = [];
if ($profileExists) {
    $stmt = $conn->prepare("
        SELECT lr.*, 
               u.first_name AS donor_first, u.last_name AS donor_last,
               u.profile_pic AS donor_pic
        FROM lifeline_requests lr
        LEFT JOIN users u ON u.user_id = lr.accepted_donor_id
        WHERE lr.recipient_id = ?
        ORDER BY lr.created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeLine Panel - Blood Konnector</title>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        .lifeline-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .profile-form-card, .dashboard-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .form-section {
            margin-bottom: 2rem;
        }
        .form-section h4 {
            color: #dc3545;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
        }
        .request-card {
            border-left: 4px solid #dee2e6;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .request-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .request-card.pending { border-left-color: #ffc107; }
        .request-card.accepted { border-left-color: #0d6efd; }
        .request-card.completed { border-left-color: #198754; }
        .request-card.cancelled { border-left-color: #6c757d; }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-pending { background:#fff4e6; color:#d97706; }
        .status-accepted { background:#e0f2fe; color:#0369a1; }
        .status-completed { background:#ecfdf3; color:#15803d; }
        .status-cancelled { background:#f3f4f6; color:#374151; }
        
        .urgency-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .urgency-normal { background: #e9ecef; color: #495057; }
        .urgency-high { background: #fff3cd; color: #856404; }
        .urgency-critical { background: #f8d7da; color: #721c24; }
        
        .donor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .donor-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        .btn-generate {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .file-upload-area:hover {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .file-upload-area.dragover {
            border-color: #dc3545;
            background: #fff5f5;
        }
        #filePreview {
            margin-top: 1rem;
        }
        .file-preview-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }
        .file-preview-existing {
            padding: 0.75rem;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            margin-top: 0.5rem;
        }
        .existing-file-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
            border: 1px solid #ddd;
            object-fit: cover;
        }
        /* Ensure header dropdowns work on this page */
        .header_bottom {
            position: relative !important;
            z-index: 1000 !important;
        }
        .header_bottom .main_menu {
            position: relative !important;
            z-index: 1001 !important;
        }
        .header_bottom .main_menu li.dropdown {
            position: relative !important;
            z-index: 1002 !important;
        }
        .header_bottom .main_menu li.dropdown .dropdown-toggle {
            pointer-events: auto !important;
            cursor: pointer !important;
            position: relative !important;
            z-index: 1003 !important;
            display: inline-flex !important;
        }
        .header_bottom .main_menu li.dropdown .dropdown-toggle {
            position: relative !important;
        }
        .header_bottom .main_menu li.dropdown .dropdown-menu {
            z-index: 1050 !important;
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            transform: none !important;
            margin-top: -35px !important;
            pointer-events: auto !important;
        }
        .header_bottom .main_menu li.dropdown .dropdown-menu.show {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
        }
        /* Ensure nothing is blocking clicks on header */
        body > *:not(header):not(script) {
            position: relative;
        }
        .lifeline-container {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body>
<?php include('assets/includes/header.php'); ?>

<section class="lifeline-container">
    <?php if (!$profileExists || $editMode): ?>
        <!-- Initial Profile Form -->
        <div class="profile-form-card">
            <div class="text-center mb-4">
                <h2 class="text-danger"><i class="fas fa-heartbeat me-2"></i>Blood Konnector LifeLine Panel - Recipient Join</h2>
                <p class="text-muted"><?= $editMode ? 'Update your LifeLine Panel information. All information will be securely stored.' : 'Fill out this comprehensive form once to set up your LifeLine Panel. All information will be securely stored.'; ?></p>
            </div>
            
            <form id="lifelineProfileForm" enctype="multipart/form-data">
                <!-- Section 1: Personal Information -->
                <div class="form-section">
                    <h4><i class="fas fa-user me-2"></i>1. Personal Information</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cnic_national_id" class="form-label">CNIC / National ID <span class="text-danger">*</span></label>
                            <input type="text" name="cnic_national_id" id="cnic_national_id" class="form-control" placeholder="12345-1234567-1" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" id="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="blood_type" class="form-label">Blood Group <span class="text-danger">*</span></label>
                            <select name="blood_type" id="blood_type" class="form-select" required>
                                <option value="">Select blood type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_number_primary" class="form-label">Contact Number (Primary) <span class="text-danger">*</span></label>
                            <input type="tel" name="contact_number_primary" id="contact_number_primary" class="form-control" placeholder="+92 300 1234567" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact_number_alternate" class="form-label">Contact Number (Alternate)</label>
                            <input type="tel" name="contact_number_alternate" id="contact_number_alternate" class="form-control" placeholder="+92 300 1234567">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email_address" class="form-label">Email Address</label>
                            <input type="email" name="email_address" id="email_address" class="form-control" placeholder="example@email.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" name="city" id="city" class="form-control" placeholder="e.g., Lahore, Karachi" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="province_state" class="form-label">Province / State <span class="text-danger">*</span></label>
                            <input type="text" name="province_state" id="province_state" class="form-control" placeholder="e.g., Punjab, Sindh" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="residential_address" class="form-label">Residential Address <span class="text-danger">*</span></label>
                            <textarea name="residential_address" id="residential_address" class="form-control" rows="2" required></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Section 2: Medical Information -->
                <div class="form-section">
                    <h4><i class="fas fa-hospital me-2"></i>2. Medical Information</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hospital_clinic_name" class="form-label">Hospital / Clinic Name</label>
                            <input type="text" name="hospital_clinic_name" id="hospital_clinic_name" class="form-control" placeholder="Where blood is usually required">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="doctor_consultant_name" class="form-label">Doctor / Consultant Name</label>
                            <input type="text" name="doctor_consultant_name" id="doctor_consultant_name" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hospital_contact_number" class="form-label">Hospital Contact Number</label>
                            <input type="tel" name="hospital_contact_number" id="hospital_contact_number" class="form-control" placeholder="+92 42 1234567">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="health_condition" class="form-label">Recipient's Health Condition</label>
                            <input type="text" name="health_condition" id="health_condition" class="form-control" placeholder="e.g., Thalassemia, Cancer, Accident, Surgery">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="frequency_of_requirement" class="form-label">Frequency of Blood Requirement</label>
                            <select name="frequency_of_requirement" id="frequency_of_requirement" class="form-select">
                                <option value="on-demand">On Demand</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="occasionally">Occasionally</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="average_units_per_session" class="form-label">Average Units of Blood Required per Session</label>
                            <input type="number" name="average_units_per_session" id="average_units_per_session" class="form-control" min="1" value="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="preferred_donor_type" class="form-label">Preferred Blood Donor Type</label>
                            <select name="preferred_donor_type" id="preferred_donor_type" class="form-select">
                                <option value="any">Any</option>
                                <option value="regular">Regular</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="special_instructions" class="form-label">Special Instructions (if any)</label>
                            <textarea name="special_instructions" id="special_instructions" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Section 3: Emergency & Verification Details -->
                <div class="form-section">
                    <h4><i class="fas fa-phone-alt me-2"></i>3. Emergency & Verification Details</h4>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_name" class="form-label">Emergency Contact Name <span class="text-danger">*</span></label>
                            <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_relation" class="form-label">Emergency Contact Relation <span class="text-danger">*</span></label>
                            <input type="text" name="emergency_contact_relation" id="emergency_contact_relation" class="form-control" placeholder="e.g., Father, Mother, Spouse" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_number" class="form-label">Emergency Contact Number <span class="text-danger">*</span></label>
                            <input type="tel" name="emergency_contact_number" id="emergency_contact_number" class="form-control" placeholder="+92 300 1234567" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="verification_letter" class="form-label">Hospital / Doctor Verification Letter <span class="text-danger">*</span></label>
                            <input type="file" name="verification_letter" id="verification_letter" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" <?= $editMode ? '' : 'required'; ?>>
                            <small class="text-muted">PDF, DOC, DOCX, JPG, PNG (Max 5MB)<?= $editMode ? ' - Leave empty to keep existing file' : ''; ?></small>
                            <?php if ($editMode && !empty($lifelineProfile['verification_letter_path'])): ?>
                                <div class="file-preview-existing mt-2">
                                    <small class="text-success"><i class="fas fa-check-circle"></i> Current file:</small>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <?php if (preg_match('/\.(jpg|jpeg|png)$/i', $lifelineProfile['verification_letter_path'])): ?>
                                            <img src="<?= htmlspecialchars($lifelineProfile['verification_letter_path']); ?>" alt="Verification Letter" class="existing-file-preview" style="max-width: 100px; max-height: 100px; border-radius: 4px; border: 1px solid #ddd;">
                                        <?php else: ?>
                                            <i class="fas fa-file-pdf text-danger fa-2x"></i>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars($lifelineProfile['verification_letter_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                    <input type="hidden" name="existing_verification_letter" value="<?= htmlspecialchars($lifelineProfile['verification_letter_path']); ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cnic_copy" class="form-label">CNIC Copy <span class="text-danger">*</span></label>
                            <input type="file" name="cnic_copy" id="cnic_copy" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" <?= $editMode ? '' : 'required'; ?>>
                            <small class="text-muted">PDF, DOC, DOCX, JPG, PNG (Max 5MB)<?= $editMode ? ' - Leave empty to keep existing file' : ''; ?></small>
                            <?php if ($editMode && !empty($lifelineProfile['cnic_copy_path'])): ?>
                                <div class="file-preview-existing mt-2">
                                    <small class="text-success"><i class="fas fa-check-circle"></i> Current file:</small>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <?php if (preg_match('/\.(jpg|jpeg|png)$/i', $lifelineProfile['cnic_copy_path'])): ?>
                                            <img src="<?= htmlspecialchars($lifelineProfile['cnic_copy_path']); ?>" alt="CNIC Copy" class="existing-file-preview" style="max-width: 100px; max-height: 100px; border-radius: 4px; border: 1px solid #ddd;">
                                        <?php else: ?>
                                            <i class="fas fa-file-pdf text-danger fa-2x"></i>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars($lifelineProfile['cnic_copy_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                    <input type="hidden" name="existing_cnic_copy" value="<?= htmlspecialchars($lifelineProfile['cnic_copy_path']); ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="medical_proof" class="form-label">Medical Proof Document (Optional)</label>
                            <input type="file" name="medical_proof" id="medical_proof" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="text-muted">PDF, DOC, DOCX, JPG, PNG (Max 5MB)</small>
                            <?php if ($editMode && !empty($lifelineProfile['medical_proof_path'])): ?>
                                <div class="file-preview-existing mt-2">
                                    <small class="text-success"><i class="fas fa-check-circle"></i> Current file:</small>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <?php if (preg_match('/\.(jpg|jpeg|png)$/i', $lifelineProfile['medical_proof_path'])): ?>
                                            <img src="<?= htmlspecialchars($lifelineProfile['medical_proof_path']); ?>" alt="Medical Proof" class="existing-file-preview" style="max-width: 100px; max-height: 100px; border-radius: 4px; border: 1px solid #ddd;">
                                        <?php else: ?>
                                            <i class="fas fa-file-pdf text-danger fa-2x"></i>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars($lifelineProfile['medical_proof_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                    <input type="hidden" name="existing_medical_proof" value="<?= htmlspecialchars($lifelineProfile['medical_proof_path']); ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Section 4: Consent & Declaration -->
                <div class="form-section">
                    <h4><i class="fas fa-file-signature me-2"></i>4. Consent & Declaration</h4>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="consent_declaration" id="consent_declaration" value="1" required>
                            <label class="form-check-label" for="consent_declaration">
                                I hereby declare that all information provided above is accurate and truthful to the best of my knowledge.
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="consent_storage" id="consent_storage" value="1" required>
                            <label class="form-check-label" for="consent_storage">
                                I consent to Blood Konnector storing and using my medical and contact data for the purpose of connecting me with verified blood donors.
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="consent_contact" id="consent_contact" value="1" required>
                            <label class="form-check-label" for="consent_contact">
                                I agree to be contacted by Blood Konnector representatives and donors for coordination regarding my blood requirements.
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="consent_liability" id="consent_liability" value="1" required>
                            <label class="form-check-label" for="consent_liability">
                                I understand that Blood Konnector acts as a connector and is not responsible for medical procedures, blood testing, or transfusion outcomes.
                            </label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="signature_name" class="form-label">Signature of Recipient / Guardian <span class="text-danger">*</span></label>
                            <input type="text" name="signature_name" id="signature_name" class="form-control" placeholder="Enter your complete name" required>
                            <small class="text-muted">Type your full name as signature</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="declaration_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="declaration_date" id="declaration_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-danger btn-lg px-5">
                        <i class="fas fa-save me-2"></i>Submit & Save Profile
                    </button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Dashboard View -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="text-danger mb-1"><i class="fas fa-heartbeat me-2"></i>LifeLine Panel</h2>
                    <p class="text-muted mb-0">Manage your blood donation requests</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="lifeline-panel?edit=1" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </a>
                    <button class="btn btn-generate text-white" id="generateRequestBtn">
                        <i class="fas fa-plus me-2"></i>Generate New Request
                    </button>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h3 class="text-primary mb-0"><?= count(array_filter($requests, fn($r) => $r['status'] === 'pending')); ?></h3>
                        <small class="text-muted">Pending Requests</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h3 class="text-info mb-0"><?= count(array_filter($requests, fn($r) => $r['status'] === 'accepted')); ?></h3>
                        <small class="text-muted">Accepted Requests</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h3 class="text-success mb-0"><?= count(array_filter($requests, fn($r) => $r['status'] === 'completed')); ?></h3>
                        <small class="text-muted">Completed Requests</small>
                    </div>
                </div>
            </div>
            
            <h4 class="mb-3"><i class="fas fa-history me-2"></i>Request History</h4>
            
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5 class="mt-3">No requests yet</h5>
                    <p class="text-muted">Click "Generate New Request" to create your first blood donation request.</p>
                </div>
            <?php else: ?>
                <div class="requests-list">
                    <?php foreach ($requests as $req): ?>
                        <div class="request-card <?= $req['status']; ?>" data-request-id="<?= (int)$req['id']; ?>">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div>
                                    <span class="text-muted small">Request #<?= (int)$req['id']; ?></span>
                                    <span class="status-badge status-<?= $req['status']; ?> ms-2"><?= htmlspecialchars($req['status']); ?></span>
                                    <span class="urgency-badge urgency-<?= $req['urgency'] ?? 'normal'; ?> ms-2"><?= htmlspecialchars($req['urgency'] ?? 'normal'); ?></span>
                                </div>
                                <small class="text-muted"><?= date('M d, Y h:i A', strtotime($req['created_at'])); ?></small>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Blood Type:</strong> <?= htmlspecialchars($req['blood_type']); ?></p>
                                    <p class="mb-1"><strong>City:</strong> <?= htmlspecialchars($req['city']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <?php if (!empty($req['note'])): ?>
                                        <p class="mb-1"><strong>Note:</strong> <?= htmlspecialchars($req['note']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($req['status'] === 'accepted' && !empty($req['accepted_donor_id'])): ?>
                                <div class="donor-info">
                                    <img src="<?= !empty($req['donor_pic']) ? htmlspecialchars($req['donor_pic']) : 'assets/images/default-avatar.png'; ?>" 
                                         alt="Donor" class="donor-avatar">
                                    <div class="flex-grow-1">
                                        <strong>Accepted by:</strong> <?= htmlspecialchars(trim(($req['donor_first'] ?? '') . ' ' . ($req['donor_last'] ?? ''))); ?>
                                        <br>
                                        <small class="text-muted">Accepted on <?= date('M d, Y h:i A', strtotime($req['accepted_at'])); ?></small>
                                    </div>
                                    <a href="chat.php?id=<?= urlencode($req['accepted_donor_id']); ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-comments me-1"></i>Chat
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <?php if ($req['status'] === 'accepted'): ?>
                                    <button class="btn btn-sm btn-success complete-request-btn" data-request-id="<?= (int)$req['id']; ?>">
                                        <i class="fas fa-check me-1"></i>Mark as Completed
                                    </button>
                                <?php endif; ?>
                                <?php if ($req['status'] === 'pending' || $req['status'] === 'accepted'): ?>
                                    <button class="btn btn-sm btn-secondary cancel-request-btn" data-request-id="<?= (int)$req['id']; ?>">
                                        <i class="fas fa-times me-1"></i>Cancel Request
                                    </button>
                                <?php endif; ?>
                                <?php if ($req['status'] === 'accepted' && !empty($req['accepted_donor_id'])): ?>
                                    <a href="chat.php?id=<?= urlencode($req['accepted_donor_id']); ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-comments me-1"></i>Chat with Donor
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php include('assets/includes/footer.php'); ?>

<!-- Javascript Files -->
<?php include('assets/includes/link-js.php'); ?>

<script>
// Populate form with existing data if editing
<?php if ($editMode && $lifelineProfile): ?>
document.addEventListener('DOMContentLoaded', function() {
    const profile = <?= json_encode($lifelineProfile); ?>;
    if (document.getElementById('full_name')) document.getElementById('full_name').value = profile.full_name || '';
    if (document.getElementById('cnic_national_id')) document.getElementById('cnic_national_id').value = profile.cnic_national_id || '';
    if (document.getElementById('date_of_birth')) document.getElementById('date_of_birth').value = profile.date_of_birth || '';
    if (document.getElementById('gender')) document.getElementById('gender').value = profile.gender || '';
    if (document.getElementById('blood_type')) document.getElementById('blood_type').value = profile.blood_type || '';
    if (document.getElementById('contact_number_primary')) document.getElementById('contact_number_primary').value = profile.contact_number_primary || '';
    if (document.getElementById('contact_number_alternate')) document.getElementById('contact_number_alternate').value = profile.contact_number_alternate || '';
    if (document.getElementById('email_address')) document.getElementById('email_address').value = profile.email_address || '';
    if (document.getElementById('residential_address')) document.getElementById('residential_address').value = profile.residential_address || '';
    if (document.getElementById('city')) document.getElementById('city').value = profile.city || '';
    if (document.getElementById('province_state')) document.getElementById('province_state').value = profile.province_state || '';
    if (document.getElementById('hospital_clinic_name')) document.getElementById('hospital_clinic_name').value = profile.hospital_clinic_name || '';
    if (document.getElementById('doctor_consultant_name')) document.getElementById('doctor_consultant_name').value = profile.doctor_consultant_name || '';
    if (document.getElementById('hospital_contact_number')) document.getElementById('hospital_contact_number').value = profile.hospital_contact_number || '';
    if (document.getElementById('health_condition')) document.getElementById('health_condition').value = profile.health_condition || '';
    if (document.getElementById('frequency_of_requirement')) document.getElementById('frequency_of_requirement').value = profile.frequency_of_requirement || 'on-demand';
    if (document.getElementById('average_units_per_session')) document.getElementById('average_units_per_session').value = profile.average_units_per_session || 1;
    if (document.getElementById('preferred_donor_type')) document.getElementById('preferred_donor_type').value = profile.preferred_donor_type || 'any';
    if (document.getElementById('special_instructions')) document.getElementById('special_instructions').value = profile.special_instructions || '';
    if (document.getElementById('emergency_contact_name')) document.getElementById('emergency_contact_name').value = profile.emergency_contact_name || '';
    if (document.getElementById('emergency_contact_relation')) document.getElementById('emergency_contact_relation').value = profile.emergency_contact_relation || '';
    if (document.getElementById('emergency_contact_number')) document.getElementById('emergency_contact_number').value = profile.emergency_contact_number || '';
    if (document.getElementById('signature_name')) document.getElementById('signature_name').value = profile.signature_name || '';
    if (document.getElementById('declaration_date')) document.getElementById('declaration_date').value = profile.declaration_date || '';
    if (document.getElementById('consent_declaration')) document.getElementById('consent_declaration').checked = profile.consent_declaration == 1;
    if (document.getElementById('consent_storage')) document.getElementById('consent_storage').checked = profile.consent_declaration == 1;
    if (document.getElementById('consent_contact')) document.getElementById('consent_contact').checked = profile.consent_declaration == 1;
    if (document.getElementById('consent_liability')) document.getElementById('consent_liability').checked = profile.consent_declaration == 1;
});
<?php endif; ?>

// Profile form submission
const profileForm = document.getElementById('lifelineProfileForm');
if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // In edit mode, check if required files exist (either new upload or existing)
        const isEditMode = <?= $editMode ? 'true' : 'false'; ?>;
        if (isEditMode) {
            const verificationLetter = document.getElementById('verification_letter');
            const existingVerification = document.querySelector('input[name="existing_verification_letter"]');
            if (!verificationLetter.files.length && !existingVerification) {
                alert('Please upload a Hospital/Doctor Verification Letter or keep the existing one.');
                return;
            }
            
            const cnicCopy = document.getElementById('cnic_copy');
            const existingCnic = document.querySelector('input[name="existing_cnic_copy"]');
            if (!cnicCopy.files.length && !existingCnic) {
                alert('Please upload a CNIC Copy or keep the existing one.');
                return;
            }
        }
        
        const formData = new FormData(profileForm);
        formData.append('action', 'save_profile'); // Add action parameter
        const submitBtn = profileForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        
        try {
            const response = await fetch('assets/lib/lifeline-api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                alert('Profile saved successfully!');
                location.reload();
            } else {
                alert(result.error || 'Failed to save profile');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Generate request button
const generateRequestBtn = document.getElementById('generateRequestBtn');
if (generateRequestBtn) {
    generateRequestBtn.addEventListener('click', async () => {
        if (!confirm('Generate a new blood donation request? This will notify all matching donors in your city.')) {
            return;
        }
        
        generateRequestBtn.disabled = true;
        generateRequestBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
        
        try {
            const response = await fetch('assets/lib/lifeline-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'generate_request' })
            });
            const result = await response.json();
            
            if (result.success) {
                alert('Request generated successfully! Matching donors have been notified.');
                location.reload();
            } else {
                alert(result.error || 'Failed to generate request');
                generateRequestBtn.disabled = false;
                generateRequestBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Generate New Request';
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
            generateRequestBtn.disabled = false;
            generateRequestBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Generate New Request';
        }
    });
}

// Complete request button
document.querySelectorAll('.complete-request-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const requestId = this.dataset.requestId;
        if (!confirm('Mark this request as completed? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch('assets/lib/lifeline-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'complete_request', request_id: requestId })
            });
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert(result.error || 'Failed to complete request');
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
        }
    });
});

// Cancel request button
document.querySelectorAll('.cancel-request-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const requestId = this.dataset.requestId;
        if (!confirm('Cancel this request?')) {
            return;
        }
        
        try {
            const response = await fetch('assets/lib/lifeline-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cancel_request', request_id: requestId })
            });
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert(result.error || 'Failed to cancel request');
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
        }
    });
});
</script>
</body>
</html>
