<?php
/**
 * Lifeline Panel API (Phase 2 skeleton)
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
function lifelineAutoAssignDonor($conn, $recipientId, $bloodTypeOverride = null) {
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
    $loc = $rec['location'] ?? '';

    // Prefer donors with same blood type and same location, oldest last_donation_date first
    $query = "
        SELECT user_id
        FROM donors
        WHERE blood_type = ?
          AND (? = '' OR location = ?)
        ORDER BY (CASE WHEN last_donation_date IS NULL THEN 0 ELSE 1 END), last_donation_date ASC
        LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $blood, $loc, $loc);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row['user_id'];
    }
    $stmt->close();

    // Fallback: any donor with same blood type
    $fallback = $conn->prepare("SELECT user_id FROM donors WHERE blood_type = ? ORDER BY (CASE WHEN last_donation_date IS NULL THEN 0 ELSE 1 END), last_donation_date ASC LIMIT 1");
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
 * Ensure donor_id refers to an existing user (donor). Returns donor_id or null.
 */
function lifelineValidateDonor($conn, $donorId) {
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
    $delete = $conn->prepare("DELETE FROM lifeline_reminders WHERE request_id = ?");
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
        $ins = $conn->prepare("INSERT INTO lifeline_reminders (request_id, type, scheduled_for) VALUES (?, ?, ?)");
        $ins->bind_param("iss", $requestId, $type, $scheduledFor);
        $ins->execute();
        $ins->close();
    }

    // Final timeout reminder at responder timeout (for donor follow-up)
    $timeout = $conn->prepare("SELECT responder_timeout_at FROM lifeline_requests WHERE id = ?");
    $timeout->bind_param("i", $requestId);
    $timeout->execute();
    $result = $timeout->get_result()->fetch_assoc();
    $timeout->close();
    if (!empty($result['responder_timeout_at'])) {
        $final = $conn->prepare("INSERT INTO lifeline_reminders (request_id, type, scheduled_for) VALUES (?, 'final_timeout', ?)");
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
        $urgency = $_POST['urgency'] ?? 'normal';
        $note = trim($_POST['note'] ?? '');
        $donorId = trim($_POST['donor_id'] ?? '');
        $bloodTypeOverride = trim($_POST['blood_type'] ?? '');

        if (!$preferredDate || !$preferredTime || !$location) {
            respond(false, ['error' => 'Date, time, and location are required'], 422);
        }

        // If a blood type is provided on the form, prefer it for matching when recipient profile is missing/empty
        $matchingBloodType = null;
        if ($bloodTypeOverride) {
            $matchingBloodType = $bloodTypeOverride;
        }

        // Auto-assign donor if none provided
        if (!$donorId) {
            $auto = lifelineAutoAssignDonor($conn, $userId, $matchingBloodType);
            if ($auto) {
                $donorId = $auto;
            }
        }

        // Validate donor_id against users/donors; null it if invalid
        $donorId = lifelineValidateDonor($conn, $donorId);

        $responderTimeout = date('Y-m-d H:i:s', strtotime('+12 hours'));

        $stmt = $conn->prepare("INSERT INTO lifeline_requests (recipient_id, preferred_date, preferred_time, location, urgency, note, status, responder_timeout_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param("sssssss", $userId, $preferredDate, $preferredTime, $location, $urgency, $note, $responderTimeout);
        if (!$stmt->execute()) {
            respond(false, ['error' => 'Failed to create request'], 500);
        }
        $requestId = $conn->insert_id;
        $stmt->close();

        $scheduledAt = date('Y-m-d H:i:s', strtotime("$preferredDate $preferredTime"));

        $stmt = $conn->prepare("INSERT INTO lifeline_confirmations (request_id, recipient_confirmed, recipient_confirmed_at, donor_id, scheduled_at, countdown_start_at) VALUES (?, 1, NOW(), ?, ?, NOW())");
        $donorParam = $donorId ?: null;
        $stmt->bind_param("iss", $requestId, $donorParam, $scheduledAt);
        $stmt->execute();
        $stmt->close();

        resetReminders($conn, $requestId, $scheduledAt);

        respond(true, ['request_id' => $requestId]);
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

        $req = $conn->prepare("SELECT lr.id, lr.recipient_id, lr.status, lc.donor_id FROM lifeline_requests lr JOIN lifeline_confirmations lc ON lc.request_id = lr.id WHERE lr.id = ?");
        $req->bind_param("i", $requestId);
        $req->execute();
        $info = $req->get_result()->fetch_assoc();
        $req->close();

        if (!$info) {
            respond(false, ['error' => 'Request not found'], 404);
        }

        if (!empty($info['donor_id']) && $info['donor_id'] !== $userId) {
            respond(false, ['error' => 'You are not assigned to this request'], 403);
        }

        // Approve/reschedule require a new schedule
        if (in_array($response, ['approve', 'reschedule'], true)) {
            if (!$scheduledAt || !$location) {
                respond(false, ['error' => 'scheduled_at and location are required'], 422);
            }
        }

        $conn->begin_transaction();
        try {
            $reschedulePayload = null;
            $newStatus = $response === 'approve' ? 'confirmed' : ($response === 'decline' ? 'failed' : 'rescheduled');

            $updateReq = $conn->prepare("UPDATE lifeline_requests SET status = ?, location = CASE WHEN ? <> '' THEN ? ELSE location END, responder_timeout_at = DATE_ADD(NOW(), INTERVAL 12 HOUR) WHERE id = ?");
            $updateReq->bind_param("sssi", $newStatus, $location, $location, $requestId);
            $updateReq->execute();
            $updateReq->close();

            if ($response === 'reschedule') {
                $reschedulePayload = json_encode(['suggested_at' => $scheduledAt, 'location' => $location]);
            }

            $updateConf = $conn->prepare("UPDATE lifeline_confirmations SET donor_id = ?, donor_response = ?, donor_response_at = NOW(), reschedule_payload = ?, scheduled_at = ?, countdown_start_at = NOW() WHERE request_id = ?");
            $updateConf->bind_param("ssssi", $userId, $response, $reschedulePayload, $scheduledAt, $requestId);
            $updateConf->execute();
            $updateConf->close();

            if ($response === 'approve') {
                resetReminders($conn, $requestId, $scheduledAt);
            } else {
                // Clear reminders on decline/reschedule
                $clr = $conn->prepare("DELETE FROM lifeline_reminders WHERE request_id = ?");
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
        $infoStmt = $conn->prepare("
            SELECT lr.recipient_id, lc.reschedule_payload
            FROM lifeline_requests lr
            JOIN lifeline_confirmations lc ON lc.request_id = lr.id
            WHERE lr.id = ?
        ");
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
                $updateReq = $conn->prepare("UPDATE lifeline_requests SET status='confirmed', location=?, responder_timeout_at=DATE_ADD(NOW(), INTERVAL 12 HOUR) WHERE id=?");
                $updateReq->bind_param("si", $location, $requestId);
                $updateReq->execute();
                $updateReq->close();

                $updateConf = $conn->prepare("UPDATE lifeline_confirmations SET reschedule_payload=NULL, donor_response='approve', donor_response_at=NOW(), scheduled_at=?, countdown_start_at=NOW() WHERE request_id=?");
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
            $updateConf = $conn->prepare("UPDATE lifeline_confirmations SET reschedule_payload=NULL WHERE request_id=?");
            $updateConf->bind_param("i", $requestId);
            $updateConf->execute();
            $updateConf->close();

            $updateReq = $conn->prepare("UPDATE lifeline_requests SET status='failed', responder_timeout_at=NULL WHERE id=?");
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

        $req = $conn->prepare("SELECT id, recipient_id FROM lifeline_requests WHERE id = ?");
        $req->bind_param("i", $requestId);
        $req->execute();
        $info = $req->get_result()->fetch_assoc();
        $req->close();

        if (!$info || $info['recipient_id'] !== $userId) {
            respond(false, ['error' => 'Request not found or unauthorized'], 404);
        }

        $update = $conn->prepare("UPDATE lifeline_requests SET status = ?, updated_at = NOW() WHERE id = ?");
        $update->bind_param("si", $result, $requestId);
        $update->execute();
        $update->close();

        respond(true, ['status' => $result]);
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
        $req = $conn->prepare("SELECT lr.recipient_id, lc.donor_id FROM lifeline_requests lr JOIN lifeline_confirmations lc ON lc.request_id = lr.id WHERE lr.id = ?");
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

        $stmt = $conn->prepare("INSERT INTO lifeline_feedback (request_id, from_user_id, to_user_id, role, rating, remarks) VALUES (?, ?, ?, ?, ?, ?)");
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

