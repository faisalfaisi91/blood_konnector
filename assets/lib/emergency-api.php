<?php
/**
 * Emergency Panel API (Phase 2 skeleton)
 * Handles request creation, donor confirmations, post-donation checks, and feedback.
 * Auth: uses existing session + ProfileManager roles.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/openconn.php';
require_once __DIR__ . '/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$userId = $_SESSION['user_id'] ?? null;

function respond($success, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success], $data));
    exit;
}

function ensureRole($profileManager, $role) {
    if (!$profileManager->hasRole($role)) {
        respond(false, ['error' => 'Forbidden'], 403);
    }
}

/**
 * Auto-assign a donor based on recipient profile (blood_type + location).
 * Returns donor_id string or null if none found.
 */
function emergencyAutoAssignDonor($conn, $recipientId, $bloodTypeOverride = null, $city = null) {
    // Fetch recipient profile traits
    $r = $conn->prepare("SELECT blood_type, location FROM recipients WHERE user_id = ? LIMIT 1");
    $r->bind_param("s", $recipientId);
    $r->execute();
    $rec = $r->get_result()->fetch_assoc();
    $r->close();
    $blood = $bloodTypeOverride ?: ($rec['blood_type'] ?? '');
    if (empty($blood)) {
        return null;
    }
    
    // Use provided city or extract from recipient location
    $matchingCity = $city;
    if (empty($matchingCity) && !empty($rec['location'])) {
        $matchingCity = $rec['location'];
    }
    
    // Prefer donors with same blood type and same city (case-insensitive partial match)
    // Exclude donors with is_available = 0 (profile deactivated)
    $query = "
        SELECT user_id
        FROM donors
        WHERE blood_type = ?
          AND COALESCE(is_available, 1) = 1
          AND (? = '' OR LOWER(location) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', location, '%')))
        ORDER BY (CASE WHEN last_donation_date IS NULL THEN 0 ELSE 1 END), last_donation_date ASC
        LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssss", $blood, $matchingCity, $matchingCity, $matchingCity);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row['user_id'];
    }
    $stmt->close();
    
    // Fallback: any donor with same blood type (exclude deactivated)
    $fallback = $conn->prepare("SELECT user_id FROM donors WHERE blood_type = ? AND COALESCE(is_available, 1) = 1 ORDER BY (CASE WHEN last_donation_date IS NULL THEN 0 ELSE 1 END), last_donation_date ASC LIMIT 1");
    $fallback->bind_param("s", $blood);
    $fallback->execute();
    $res2 = $fallback->get_result();
    if ($res2 && $res2->num_rows > 0) {
        $row = $res2->fetch_assoc();
        $fallback->close();
        return $row['user_id'];
    }
    $fallback->close();
    return null;
}

/**
 * Find multiple matching donors and notify them about an emergency request.
 * Returns array of donor IDs that were notified.
 * @param int|null $donorLimit Maximum number of donors to notify (null for unlimited)
 */
function emergencyNotifyMatchingDonors($conn, $requestId, $recipientId, $bloodTypeOverride = null, $city = '', $scheduledAt = '', $excludeDonorId = null, $donorLimit = 10) {
    // Use the blood_type and city from the request (form), not from recipient profile
    $blood = $bloodTypeOverride ?: '';
    
    // If blood_type not provided, try to get from request table
    if (empty($blood)) {
        $reqStmt = $conn->prepare("SELECT blood_type FROM emergency_requests WHERE id = ? LIMIT 1");
        $reqStmt->bind_param("i", $requestId);
        $reqStmt->execute();
        $reqResult = $reqStmt->get_result();
        if ($reqResult && $reqResult->num_rows > 0) {
            $reqRow = $reqResult->fetch_assoc();
            $blood = $reqRow['blood_type'] ?? '';
        }
        $reqStmt->close();
    }
    
    // If still empty, fallback to recipient profile
    if (empty($blood)) {
        $r = $conn->prepare("SELECT blood_type FROM recipients WHERE user_id = ? LIMIT 1");
        $r->bind_param("s", $recipientId);
        $r->execute();
        $rec = $r->get_result()->fetch_assoc();
        $r->close();
        $blood = $rec['blood_type'] ?? '';
    }
    
    if (empty($blood)) {
        return [];
    }
    
    // Use provided city from request form
    $matchingCity = $city ?: '';
    
    // If city not provided, try to get from request table
    if (empty($matchingCity)) {
        $cityStmt = $conn->prepare("SELECT city FROM emergency_requests WHERE id = ? LIMIT 1");
        $cityStmt->bind_param("i", $requestId);
        $cityStmt->execute();
        $cityResult = $cityStmt->get_result();
        if ($cityResult && $cityResult->num_rows > 0) {
            $cityRow = $cityResult->fetch_assoc();
            $matchingCity = $cityRow['city'] ?? '';
        }
        $cityStmt->close();
    }
    
    // Find matching donors with configurable limit
    // Priority: same city first, then same blood type
    // Match city case-insensitively and handle partial matches
    // Join with users table to ensure user_id exists and is valid
    
    // Build LIMIT clause (cannot use placeholder in prepared statement for LIMIT)
    $limitClause = '';
    if ($donorLimit !== null && $donorLimit !== 'unlimited') {
        $limitValue = (int)$donorLimit;
        // Validate limit value is reasonable (max 10000 to prevent abuse)
        if ($limitValue > 0 && $limitValue <= 10000) {
            $limitClause = " LIMIT " . $limitValue;
        }
    }
    // If null or 'unlimited', no LIMIT clause (gets all matching donors)
    
    $query = "
        SELECT d.user_id
        FROM donors d
        INNER JOIN users u ON d.user_id = u.user_id
        WHERE d.blood_type = ?
          AND d.user_id != ?
          AND COALESCE(d.is_available, 1) = 1
          AND d.user_id NOT IN (SELECT donor_id FROM emergency_confirmations WHERE request_id = ? AND donor_id IS NOT NULL)
        ORDER BY 
            CASE WHEN ? != '' AND (LOWER(d.location) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', d.location, '%'))) THEN 1 ELSE 2 END,
            (CASE WHEN d.last_donation_date IS NULL THEN 0 ELSE 1 END),
            d.last_donation_date ASC
        " . $limitClause . "
    ";
    $stmt = $conn->prepare($query);
    $exclude = $excludeDonorId ?: '';
    // 6 parameters: blood, exclude, requestId, matchingCity (3 times for the CASE/LIKE conditions)
    $stmt->bind_param("ssssss", $blood, $exclude, $requestId, $matchingCity, $matchingCity, $matchingCity);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $notifiedDonors = [];
    $payload = json_encode([
        'request_id' => $requestId,
        'scheduled_at' => $scheduledAt,
        'city' => $matchingCity,
        'type' => 'new_request'
    ]);
    
    while ($row = $res->fetch_assoc()) {
        $donorId = $row['user_id'];
        
        // Double-check that user_id exists in users table before creating notification
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
        $checkStmt->bind_param("s", $donorId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if ($checkResult && $checkResult->num_rows > 0) {
            // Create notification for each matching donor
            $notifStmt = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'emergency_new_request', ?, 'queued')");
            $notifStmt->bind_param("ss", $donorId, $payload);
            if ($notifStmt->execute()) {
                $notifiedDonors[] = $donorId;
            } else {
                // Log error but continue with other donors
                error_log("Failed to create notification for donor {$donorId}: " . $conn->error);
            }
            $notifStmt->close();
        }
    }
    $stmt->close();
    
    return $notifiedDonors;
}

/**
 * Ensure donor_id refers to an existing user (donor). Returns donor_id or null.
 */
function emergencyValidateDonor($conn, $donorId) {
    if (!$donorId) return null;
    $stmt = $conn->prepare("SELECT u.user_id FROM users u LEFT JOIN donors d ON d.user_id = u.user_id WHERE u.user_id = ? AND (u.is_donor = 1 OR d.user_id IS NOT NULL) LIMIT 1");
    $stmt->bind_param("s", $donorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = ($res && $res->num_rows > 0);
    $stmt->close();
    return $ok ? $donorId : null;
}

if (!$profileManager->isLoggedIn()) {
    respond(false, ['error' => 'Unauthorized'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if (!$action) {
    respond(false, ['error' => 'Action is required'], 400);
}

/**
 * Remove existing reminders and recreate countdown set
 */
function resetReminders($conn, $requestId, $scheduledAt) {
    $delete = $conn->prepare("DELETE FROM emergency_reminders WHERE request_id = ?");
    $delete->bind_param("i", $requestId);
    $delete->execute();
    $delete->close();

    $offsets = [
        ['24h', '-24 hour'],
        ['6h', '-6 hour'],
        ['1h', '-1 hour'],
    ];

    foreach ($offsets as [$type, $modifier]) {
        $scheduledFor = date('Y-m-d H:i:s', strtotime($modifier, strtotime($scheduledAt)));
        $ins = $conn->prepare("INSERT INTO emergency_reminders (request_id, type, scheduled_for) VALUES (?, ?, ?)");
        $ins->bind_param("iss", $requestId, $type, $scheduledFor);
        $ins->execute();
        $ins->close();
    }

    // Final timeout reminder at responder timeout (for donor follow-up)
    $timeout = $conn->prepare("SELECT responder_timeout_at FROM emergency_requests WHERE id = ?");
    $timeout->bind_param("i", $requestId);
    $timeout->execute();
    $result = $timeout->get_result()->fetch_assoc();
    $timeout->close();
    if (!empty($result['responder_timeout_at'])) {
        $final = $conn->prepare("INSERT INTO emergency_reminders (request_id, type, scheduled_for) VALUES (?, 'final_timeout', ?)");
        $final->bind_param("is", $requestId, $result['responder_timeout_at']);
        $final->execute();
        $final->close();
    }
}

switch ($action) {
    case 'create_request':
        ensureRole($profileManager, 'recipient');

        $preferredDate = trim($_POST['preferred_date'] ?? '');
        $preferredTime = trim($_POST['preferred_time'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $urgency = $_POST['urgency'] ?? 'normal';
        $note = trim($_POST['note'] ?? '');
        $donorId = trim($_POST['donor_id'] ?? '');
        $bloodTypeOverride = trim($_POST['blood_type'] ?? '');

        if (!$preferredDate || !$preferredTime || !$location || !$city) {
            respond(false, ['error' => 'Date, time, location, and city are required'], 422);
        }

        // If a blood type is provided on the form, prefer it for matching when recipient profile is missing/empty
        $matchingBloodType = null;
        if ($bloodTypeOverride) {
            $matchingBloodType = $bloodTypeOverride;
        }

        // Auto-assign donor if none provided
        if (!$donorId) {
            $auto = emergencyAutoAssignDonor($conn, $userId, $matchingBloodType, $city);
            if ($auto) {
                $donorId = $auto;
            }
        }

        // Validate donor_id against users/donors; null it if invalid
        $donorId = emergencyValidateDonor($conn, $donorId);

        $responderTimeout = date('Y-m-d H:i:s', strtotime('+12 hours'));

        // Check if city and blood_type columns exist
        $checkStmt = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'emergency_requests' AND COLUMN_NAME IN ('city', 'blood_type')");
        $existingColumns = [];
        if ($checkStmt) {
            while ($row = $checkStmt->fetch_assoc()) {
                $existingColumns[] = $row['COLUMN_NAME'];
            }
        }
        
        // Add city column if it doesn't exist
        if (!in_array('city', $existingColumns)) {
            try {
                $conn->query("ALTER TABLE emergency_requests ADD COLUMN city VARCHAR(100) NULL AFTER location");
                $existingColumns[] = 'city';
            } catch (Exception $e) {
                // Column might already exist
            }
        }
        
        // Add blood_type column if it doesn't exist
        if (!in_array('blood_type', $existingColumns)) {
            try {
                $conn->query("ALTER TABLE emergency_requests ADD COLUMN blood_type VARCHAR(10) NULL AFTER city");
                $existingColumns[] = 'blood_type';
            } catch (Exception $e) {
                // Column might already exist
            }
        }
        
        // Insert request with available columns
        if (in_array('city', $existingColumns) && in_array('blood_type', $existingColumns)) {
            $stmt = $conn->prepare("INSERT INTO emergency_requests (recipient_id, preferred_date, preferred_time, location, city, blood_type, urgency, note, status, responder_timeout_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->bind_param("sssssssss", $userId, $preferredDate, $preferredTime, $location, $city, $bloodTypeOverride, $urgency, $note, $responderTimeout);
        } elseif (in_array('city', $existingColumns)) {
            $stmt = $conn->prepare("INSERT INTO emergency_requests (recipient_id, preferred_date, preferred_time, location, city, urgency, note, status, responder_timeout_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->bind_param("ssssssss", $userId, $preferredDate, $preferredTime, $location, $city, $urgency, $note, $responderTimeout);
        } else {
            $stmt = $conn->prepare("INSERT INTO emergency_requests (recipient_id, preferred_date, preferred_time, location, urgency, note, status, responder_timeout_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->bind_param("sssssss", $userId, $preferredDate, $preferredTime, $location, $urgency, $note, $responderTimeout);
        }
        if (!$stmt->execute()) {
            respond(false, ['error' => 'Failed to create request'], 500);
        }
        $requestId = $conn->insert_id;
        $stmt->close();

        $scheduledAt = date('Y-m-d H:i:s', strtotime("$preferredDate $preferredTime"));

        $stmt = $conn->prepare("INSERT INTO emergency_confirmations (request_id, recipient_confirmed, recipient_confirmed_at, donor_id, scheduled_at, countdown_start_at) VALUES (?, 1, NOW(), ?, ?, NOW())");
        $donorParam = $donorId ?: null;
        $stmt->bind_param("iss", $requestId, $donorParam, $scheduledAt);
        $stmt->execute();
        $stmt->close();

        resetReminders($conn, $requestId, $scheduledAt);

        // Notify multiple matching donors (if no specific donor was provided)
        // Use the blood_type from the request (form), not from recipient profile
        $notifiedCount = 0;
        if (!$donorId || $donorId === '') {
            // Get donor limit from form (default to 10 if not provided)
            $donorLimit = trim($_POST['donor_limit'] ?? '10');
            if ($donorLimit === 'unlimited') {
                $donorLimit = null; // null means unlimited
            } else {
                $donorLimit = (int)$donorLimit; // Convert to integer
            }
            
            $notifiedDonors = emergencyNotifyMatchingDonors($conn, $requestId, $userId, $bloodTypeOverride, $city, $scheduledAt, $donorId, $donorLimit);
            $notifiedCount = count($notifiedDonors);
        }

        respond(true, [
            'request_id' => $requestId,
            'donor_assigned' => $donorId ? true : false,
            'matching_donors_notified' => $notifiedCount
        ]);
        break;

    case 'donor_response':
        ensureRole($profileManager, 'donor');

        $requestId = (int)($_POST['request_id'] ?? 0);
        $response = $_POST['response'] ?? '';
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if (!$requestId || !in_array($response, ['approve', 'decline', 'reschedule'], true)) {
            respond(false, ['error' => 'Invalid donor response payload'], 422);
        }

        $req = $conn->prepare("SELECT lr.id, lr.recipient_id, lr.status, lc.donor_id, lc.donor_response FROM emergency_requests lr JOIN emergency_confirmations lc ON lc.request_id = lr.id WHERE lr.id = ?");
        $req->bind_param("i", $requestId);
        $req->execute();
        $info = $req->get_result()->fetch_assoc();
        $req->close();

        if (!$info) {
            respond(false, ['error' => 'Request not found'], 404);
        }

        // Check if request is already confirmed/assigned to another donor
        if ($response === 'approve') {
            // If status is already confirmed, check if it's assigned to someone else
            if ($info['status'] === 'confirmed') {
                if (!empty($info['donor_id']) && $info['donor_id'] !== $userId) {
                    respond(false, ['error' => 'This request has already been assigned to another donor. Please check other available requests.'], 403);
                }
            }
            
            // If donor_response is already 'approve' and assigned to someone else
            if ($info['donor_response'] === 'approve' && !empty($info['donor_id']) && $info['donor_id'] !== $userId) {
                respond(false, ['error' => 'This request has already been accepted by another donor. Please check other available requests.'], 403);
            }
            
            // If there's a donor_id assigned and it's not the current user
            if (!empty($info['donor_id']) && $info['donor_id'] !== $userId) {
                respond(false, ['error' => 'This request has already been assigned to another donor. Please check other available requests.'], 403);
            }
        }

        // For other responses (decline, reschedule), only allow if they are the assigned donor or no one is assigned
        if (in_array($response, ['decline', 'reschedule'], true)) {
            if (!empty($info['donor_id']) && $info['donor_id'] !== $userId) {
                respond(false, ['error' => 'You are not assigned to this request'], 403);
            }
        }

        // Approve/reschedule require a new schedule
        if (in_array($response, ['approve', 'reschedule'], true)) {
            if (!$scheduledAt || !$location) {
                respond(false, ['error' => 'scheduled_at and location are required'], 422);
            }
        }

        $conn->begin_transaction();
        try {
            // Double-check within transaction to prevent race conditions
            // Re-check if request is still available for approval
            if ($response === 'approve') {
                $checkStmt = $conn->prepare("SELECT lr.status, lc.donor_id, lc.donor_response FROM emergency_requests lr JOIN emergency_confirmations lc ON lc.request_id = lr.id WHERE lr.id = ? FOR UPDATE");
                $checkStmt->bind_param("i", $requestId);
                $checkStmt->execute();
                $checkInfo = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();
                
                if ($checkInfo) {
                    // If already confirmed and assigned to someone else
                    if ($checkInfo['status'] === 'confirmed' && !empty($checkInfo['donor_id']) && $checkInfo['donor_id'] !== $userId) {
                        $conn->rollback();
                        respond(false, ['error' => 'This request has already been assigned to another donor. Please check other available requests.'], 403);
                    }
                    
                    // If donor_response is already 'approve' and assigned to someone else
                    if ($checkInfo['donor_response'] === 'approve' && !empty($checkInfo['donor_id']) && $checkInfo['donor_id'] !== $userId) {
                        $conn->rollback();
                        respond(false, ['error' => 'This request has already been accepted by another donor. Please check other available requests.'], 403);
                    }
                    
                    // If there's a donor_id assigned and it's not the current user
                    if (!empty($checkInfo['donor_id']) && $checkInfo['donor_id'] !== $userId) {
                        $conn->rollback();
                        respond(false, ['error' => 'This request has already been assigned to another donor. Please check other available requests.'], 403);
                    }
                }
            }
            
            $reschedulePayload = null;
            $newStatus = $response === 'approve' ? 'confirmed' : ($response === 'decline' ? 'failed' : 'rescheduled');

            $updateReq = $conn->prepare("UPDATE emergency_requests SET status = ?, location = CASE WHEN ? <> '' THEN ? ELSE location END, responder_timeout_at = DATE_ADD(NOW(), INTERVAL 12 HOUR) WHERE id = ?");
            $updateReq->bind_param("sssi", $newStatus, $location, $location, $requestId);
            $updateReq->execute();
            $updateReq->close();

            if ($response === 'reschedule') {
                $reschedulePayload = json_encode(['suggested_at' => $scheduledAt, 'location' => $location]);
            }

            $updateConf = $conn->prepare("UPDATE emergency_confirmations SET donor_id = ?, donor_response = ?, donor_response_at = NOW(), reschedule_payload = ?, scheduled_at = ?, countdown_start_at = NOW() WHERE request_id = ?");
            $updateConf->bind_param("ssssi", $userId, $response, $reschedulePayload, $scheduledAt, $requestId);
            $updateConf->execute();
            $updateConf->close();

            if ($response === 'approve') {
                resetReminders($conn, $requestId, $scheduledAt);
                
                // Create notification for recipient that donor has approved
                $recipientId = $info['recipient_id'];
                $notifPayload = json_encode([
                    'request_id' => $requestId,
                    'scheduled_at' => $scheduledAt,
                    'location' => $location,
                    'donor_id' => $userId,
                    'type' => 'donor_approved'
                ]);
                $notifStmt = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'emergency_donor_approved', ?, 'queued')");
                $notifStmt->bind_param("ss", $recipientId, $notifPayload);
                $notifStmt->execute();
                $notifStmt->close();
            } else {
                // Clear reminders on decline/reschedule
                $clr = $conn->prepare("DELETE FROM emergency_reminders WHERE request_id = ?");
                $clr->bind_param("i", $requestId);
                $clr->execute();
                $clr->close();
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            respond(false, ['error' => 'Failed to update response']);
        }

        respond(true, ['status' => $newStatus]);
        break;

    case 'accept_reschedule':
        ensureRole($profileManager, 'recipient');
        $requestId = (int)($_POST['request_id'] ?? 0);
        $accept = ($_POST['accept'] ?? '') === '1';
        if (!$requestId) {
            respond(false, ['error' => 'Request id required'], 422);
        }

        // fetch reschedule payload
        $infoStmt = $conn->prepare("\n            SELECT lr.recipient_id, lc.reschedule_payload\n            FROM emergency_requests lr\n            JOIN emergency_confirmations lc ON lc.request_id = lr.id\n            WHERE lr.id = ?\n        ");
        $infoStmt->bind_param("i", $requestId);
        $infoStmt->execute();
        $info = $infoStmt->get_result()->fetch_assoc();
        $infoStmt->close();

        if (!$info || $info['recipient_id'] !== $userId) {
            respond(false, ['error' => 'Unauthorized'], 403);
        }

        if (!$info['reschedule_payload']) {
            respond(false, ['error' => 'No reschedule data'], 400);
        }

        $payload = json_decode($info['reschedule_payload'], true);
        if ($accept) {
            $newScheduled = $payload['suggested_at'] ?? null;
            $location = $payload['location'] ?? null;
            if (!$newScheduled || !$location) {
                respond(false, ['error' => 'Invalid reschedule payload'], 400);
            }

            $conn->begin_transaction();
            try {
                $updateReq = $conn->prepare("UPDATE emergency_requests SET status='confirmed', location=?, responder_timeout_at=DATE_ADD(NOW(), INTERVAL 12 HOUR) WHERE id=?");
                $updateReq->bind_param("si", $location, $requestId);
                $updateReq->execute();
                $updateReq->close();

                $updateConf = $conn->prepare("UPDATE emergency_confirmations SET reschedule_payload=NULL, donor_response='approve', donor_response_at=NOW(), scheduled_at=?, countdown_start_at=NOW() WHERE request_id=?");
                $updateConf->bind_param("si", $newScheduled, $requestId);
                $updateConf->execute();
                $updateConf->close();

                resetReminders($conn, $requestId, $newScheduled);
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                respond(false, ['error' => 'Failed to accept reschedule'], 500);
            }
            respond(true, ['status' => 'confirmed']);
        } else {
            // decline reschedule keeps request pending for recipient to recreate
            $updateConf = $conn->prepare("UPDATE emergency_confirmations SET reschedule_payload=NULL WHERE request_id=?");
            $updateConf->bind_param("i", $requestId);
            $updateConf->execute();
            $updateConf->close();

            $updateReq = $conn->prepare("UPDATE emergency_requests SET status='failed', responder_timeout_at=NULL WHERE id=?");
            $updateReq->bind_param("i", $requestId);
            $updateReq->execute();
            $updateReq->close();

            respond(true, ['status' => 'failed']);
        }
        break;

    case 'post_check':
        ensureRole($profileManager, 'recipient');
        $requestId = (int)($_POST['request_id'] ?? 0);
        $result = $_POST['result'] ?? '';
        if (!$requestId || !in_array($result, ['completed', 'failed', 'rescheduled'], true)) {
            respond(false, ['error' => 'Invalid post-check data'], 422);
        }

        $req = $conn->prepare("SELECT id, recipient_id FROM emergency_requests WHERE id = ?");
        $req->bind_param("i", $requestId);
        $req->execute();
        $info = $req->get_result()->fetch_assoc();
        $req->close();

        if (!$info || $info['recipient_id'] !== $userId) {
            respond(false, ['error' => 'Request not found or unauthorized'], 404);
        }

        $update = $conn->prepare("UPDATE emergency_requests SET status = ?, updated_at = NOW() WHERE id = ?");
        $update->bind_param("si", $result, $requestId);
        $update->execute();
        $update->close();

        respond(true, ['status' => $result]);
        break;

    case 'mark_notification_read':
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if (!$notificationId) {
            respond(false, ['error' => 'Notification ID is required'], 422);
        }
        
        // Verify notification belongs to current user
        $checkStmt = $conn->prepare("SELECT user_id FROM emergency_notifications WHERE id = ? AND user_id = ? LIMIT 1");
        $checkStmt->bind_param("is", $notificationId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if (!$checkResult || $checkResult->num_rows === 0) {
            respond(false, ['error' => 'Notification not found or unauthorized'], 404);
        }
        
        // Mark as read (update status to 'sent')
        $updateStmt = $conn->prepare("UPDATE emergency_notifications SET status = 'sent', sent_at = NOW() WHERE id = ? AND user_id = ?");
        $updateStmt->bind_param("is", $notificationId, $userId);
        if ($updateStmt->execute()) {
            respond(true, ['message' => 'Notification marked as read']);
        } else {
            respond(false, ['error' => 'Failed to update notification'], 500);
        }
        $updateStmt->close();
        break;

    case 'mark_all_notifications_read':
        // Mark all in_app notifications for current user as read
        $updateStmt = $conn->prepare("UPDATE emergency_notifications SET status = 'sent', sent_at = NOW() WHERE user_id = ? AND channel = 'in_app' AND status = 'queued'");
        $updateStmt->bind_param("s", $userId);
        if ($updateStmt->execute()) {
            $affected = $updateStmt->affected_rows;
            respond(true, ['message' => 'All notifications marked as read', 'count' => $affected]);
        } else {
            respond(false, ['error' => 'Failed to update notifications'], 500);
        }
        $updateStmt->close();
        break;

    case 'get_unread_count':
        $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM emergency_notifications WHERE user_id = ? AND channel = 'in_app' AND status = 'queued' AND template_key IN ('emergency_new_request', 'emergency_donor_approved')");
        $countStmt->bind_param("s", $userId);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countRow = $countResult->fetch_assoc();
        $countStmt->close();
        respond(true, ['unread_count' => (int)($countRow['cnt'] ?? 0)]);
        break;

    case 'get_notifications':
        // Fetch emergency and lifeline notifications for current user
        $notifications = [];
        $notifStmt = $conn->prepare("\n            SELECT ln.*\n            FROM emergency_notifications ln\n            WHERE ln.user_id = ?\n              AND ln.channel = 'in_app'\n              AND ln.template_key IN ('emergency_new_request', 'emergency_donor_approved', 'lifeline_new_request', 'lifeline_donor_approved')\n            ORDER BY ln.created_at DESC\n            LIMIT 10\n        ");
        $notifStmt->bind_param("s", $userId);
        $notifStmt->execute();
        $notifResult = $notifStmt->get_result();
        while ($row = $notifResult->fetch_assoc()) {
            // Parse payload to get request details
            $payload = json_decode($row['payload'] ?? '{}', true) ?: [];
            $requestId = $payload['request_id'] ?? null;
            $donorId = $payload['donor_id'] ?? null;
            $isLifeline = ($payload['type'] ?? '') === 'lifeline' || in_array($row['template_key'], ['lifeline_new_request', 'lifeline_donor_approved']);
            
            // Fetch request details if available
            if ($requestId) {
                if ($isLifeline) {
                    // Fetch LifeLine request details
                    $reqStmt = $conn->prepare("SELECT lr.id, lr.blood_type, lr.city, lr.urgency, lp.full_name AS recipient_name\n                                           FROM lifeline_requests lr\n                                           LEFT JOIN lifeline_profiles lp ON lp.recipient_id = lr.recipient_id\n                                           WHERE lr.id = ? LIMIT 1");
                    $reqStmt->bind_param("i", $requestId);
                    $reqStmt->execute();
                    $reqResult = $reqStmt->get_result();
                    if ($reqResult && $reqResult->num_rows > 0) {
                        $reqData = $reqResult->fetch_assoc();
                        $row = array_merge($row, $reqData);
                    }
                    $reqStmt->close();
                } else {
                    // Fetch emergency request details
                    $reqStmt = $conn->prepare("SELECT lr.id, lr.preferred_date, lr.preferred_time, lr.location, lr.blood_type, lr.city\n                                           FROM emergency_requests lr\n                                           WHERE lr.id = ? LIMIT 1");
                    $reqStmt->bind_param("i", $requestId);
                    $reqStmt->execute();
                    $reqResult = $reqStmt->get_result();
                    if ($reqResult && $reqResult->num_rows > 0) {
                        $reqData = $reqResult->fetch_assoc();
                        $row = array_merge($row, $reqData);
                    }
                    $reqStmt->close();
                }
            }
            
            // Fetch donor details if available (for recipient notifications)
            if ($donorId) {
                $donorStmt = $conn->prepare("SELECT u.first_name AS donor_first, u.last_name AS donor_last\n                                             FROM users u\n                                             WHERE u.user_id = ? LIMIT 1");
                $donorStmt->bind_param("s", $donorId);
                $donorStmt->execute();
                $donorResult = $donorStmt->get_result();
                if ($donorResult && $donorResult->num_rows > 0) {
                    $donorData = $donorResult->fetch_assoc();
                    $row = array_merge($row, $donorData);
                }
                $donorStmt->close();
            }
            
            // Store parsed payload as object
            $row['payload_obj'] = $payload;
            $notifications[] = $row;
        }
        $notifStmt->close();
        
        // Count unread notifications
        $unreadCount = 0;
        $unreadStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM emergency_notifications WHERE user_id = ? AND channel = 'in_app' AND status = 'queued' AND template_key IN ('emergency_new_request', 'emergency_donor_approved', 'lifeline_new_request', 'lifeline_donor_approved')");
        $unreadStmt->bind_param("s", $userId);
        $unreadStmt->execute();
        $unreadResult = $unreadStmt->get_result();
        if ($unreadResult && $unreadResult->num_rows > 0) {
            $unreadRow = $unreadResult->fetch_assoc();
            $unreadCount = (int)($unreadRow['cnt'] ?? 0);
        }
        $unreadStmt->close();
        
        respond(true, ['notifications' => $notifications, 'unread_count' => $unreadCount]);
        break;

    case 'feedback':
        $role = $_POST['role'] ?? '';
        $requestId = (int)($_POST['request_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        if (!in_array($role, ['donor', 'recipient'], true) || $rating < 1 || $rating > 5 || !$requestId) {
            respond(false, ['error' => 'Invalid feedback payload'], 422);
        }

        // Validate participation
        $req = $conn->prepare("SELECT lr.recipient_id, lc.donor_id FROM emergency_requests lr JOIN emergency_confirmations lc ON lc.request_id = lr.id WHERE lr.id = ?");
        $req->bind_param("i", $requestId);
        $req->execute();
        $info = $req->get_result()->fetch_assoc();
        $req->close();

        if (!$info) {
            respond(false, ['error' => 'Request not found'], 404);
        }

        $isRecipient = $info['recipient_id'] === $userId;
        $isDonor = $info['donor_id'] === $userId;
        if (($role === 'recipient' && !$isRecipient) || ($role === 'donor' && !$isDonor)) {
            respond(false, ['error' => 'Unauthorized feedback'], 403);
        }

        $toUser = $role === 'recipient' ? $info['donor_id'] : $info['recipient_id'];

        $stmt = $conn->prepare("INSERT INTO emergency_feedback (request_id, from_user_id, to_user_id, role, rating, remarks) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis", $requestId, $userId, $toUser, $role, $rating, $remarks);
        if (!$stmt->execute()) {
            respond(false, ['error' => 'Failed to submit feedback'], 500);
        }
        $stmt->close();

        respond(true, ['feedback_id' => $conn->insert_id]);
        break;

    default:
        respond(false, ['error' => 'Unknown action'], 400);
}
