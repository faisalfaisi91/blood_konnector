<?php
/**
 * Scheduling Donation API
 * - schedule_donation: Recipient submits (donor gets confirmation request)
 * - confirm_donor: Donor confirms the date (creates reminders: 1 day, day of, 5h after)
 * - completion_response: Donor/recipient responds yes/no/reschedule with remarks
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/openconn.php';
require_once __DIR__ . '/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$allowed = ['schedule_donation', 'confirm_donor', 'completion_response', 'get_pending_confirmations', 'get_completion_asks'];
if (!in_array($action, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Helper: create scheduling reminders when donor confirms
function createSchedulingReminders($conn, $bloodDonationId, $donationDate, $donationTime) {
    $t = trim($donationTime ?: '10:00');
    if (strlen($t) <= 5) $t .= ':00';
    $dt = $donationDate . ' ' . $t;
    $ts = strtotime($dt);

    $types = [
        ['1day', date('Y-m-d H:i:s', strtotime('-1 day', $ts))],
        ['day_of', date('Y-m-d H:i:s', strtotime('-2 hours', $ts))],
        ['completion_ask', date('Y-m-d H:i:s', strtotime('+5 hours', $ts))]
    ];
    $ins = $conn->prepare("INSERT INTO scheduling_reminders (blood_donation_id, type, scheduled_for) VALUES (?, ?, ?)");
    foreach ($types as $t) {
        $ins->bind_param("iss", $bloodDonationId, $t[0], $t[1]);
        $ins->execute();
    }
    $ins->close();
}

// --- schedule_donation ---
if ($action === 'schedule_donation') {
    $recipientId = trim($_POST['recipient_id'] ?? '');
    $donorId = trim($_POST['donor_id'] ?? '');
    $donationDate = trim($_POST['donation_date'] ?? '');
    $donationTime = trim($_POST['donation_time'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$donationDate || !$donationTime || !$location) {
        echo json_encode(['success' => false, 'error' => 'Date, time, and location are required']);
        exit;
    }

    if ($profileManager->hasRole('recipient') && $recipientId === $userId) {
        // Recipient submitting
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid participant']);
        exit;
    }

    $checkTable = $conn->query("SHOW TABLES LIKE 'blood_donations'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        $responderTimeout = date('Y-m-d H:i:s', strtotime('+12 hours'));
        $stmt = $conn->prepare("INSERT INTO emergency_requests (recipient_id, preferred_date, preferred_time, location, urgency, note, status, responder_timeout_at) VALUES (?, ?, ?, ?, 'normal', ?, 'pending', ?)");
        $stmt->bind_param("ssssss", $recipientId, $donationDate, $donationTime, $location, $notes, $responderTimeout);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Failed to create request']);
            exit;
        }
        $requestId = $conn->insert_id;
        $stmt->close();
        $confStmt = $conn->prepare("INSERT INTO emergency_confirmations (request_id, donor_id, donor_response, scheduled_at, countdown_start_at) VALUES (?, ?, 'approve', ?, NOW())");
        $scheduledAt = $donationDate . ' ' . $donationTime . ':00';
        $confStmt->bind_param("iis", $requestId, $donorId, $scheduledAt);
        $confStmt->execute();
        $confStmt->close();
        echo json_encode(['success' => true, 'message' => 'Donation scheduled. Donor will be notified to confirm.', 'request_id' => $requestId]);
        exit;
    }

    $urgency = 'normal';
    $stmt = $conn->prepare("INSERT INTO blood_donations (donor_id, recipient_id, donation_date, donation_time, location, status, urgency, recipient_remarks) VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?)");
    $stmt->bind_param("sssssss", $donorId, $recipientId, $donationDate, $donationTime, $location, $urgency, $notes);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Failed to schedule donation']);
        exit;
    }
    $bloodDonationId = $conn->insert_id;
    $stmt->close();

    $checkNotif = $conn->query("SHOW TABLES LIKE 'emergency_notifications'");
    if ($checkNotif && $checkNotif->num_rows > 0) {
        $payload = json_encode([
            'type' => 'scheduling_confirmation',
            'blood_donation_id' => $bloodDonationId,
            'donation_date' => $donationDate,
            'donation_time' => $donationTime,
            'location' => $location,
            'notes' => $notes
        ]);
        $notifStmt = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'scheduling_confirmation', ?, 'queued')");
        $notifStmt->bind_param("ss", $donorId, $payload);
        $notifStmt->execute();
        $notifStmt->close();
    }
    echo json_encode(['success' => true, 'message' => 'Donation scheduled. Donor will be asked to confirm.', 'blood_donation_id' => $bloodDonationId]);
    exit;
}

// --- confirm_donor ---
if ($action === 'confirm_donor') {
    if (!$profileManager->hasRole('donor')) {
        echo json_encode(['success' => false, 'error' => 'Donor role required']);
        exit;
    }
    $bloodDonationId = (int)($_POST['blood_donation_id'] ?? $_GET['blood_donation_id'] ?? 0);
    $confirmed = isset($_POST['confirmed']) ? (bool)$_POST['confirmed'] : true;

    if (!$bloodDonationId) {
        echo json_encode(['success' => false, 'error' => 'blood_donation_id required']);
        exit;
    }

    $chk = $conn->prepare("SELECT id, donor_id, donation_date, donation_time FROM blood_donations WHERE id = ? AND donor_id = ? AND status = 'scheduled'");
    $chk->bind_param("is", $bloodDonationId, $userId);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Donation not found or already processed']);
        exit;
    }

    if ($confirmed) {
        $upd = $conn->prepare("UPDATE blood_donations SET donor_confirmed = 1, donor_confirmed_at = NOW(), confirmed_at = NOW() WHERE id = ?");
        $upd->bind_param("i", $bloodDonationId);
        $upd->execute();
        $upd->close();

        $t2 = $conn->query("SHOW TABLES LIKE 'scheduling_reminders'");
        if ($t2 && $t2->num_rows > 0) {
            createSchedulingReminders($conn, $bloodDonationId, $row['donation_date'], $row['donation_time']);
        }

        // Notify recipient
        $recStmt = $conn->prepare("SELECT recipient_id FROM blood_donations WHERE id = ?");
        $recStmt->bind_param("i", $bloodDonationId);
        $recStmt->execute();
        $rec = $recStmt->get_result()->fetch_assoc();
        $recStmt->close();
        if ($rec && $conn->query("SHOW TABLES LIKE 'emergency_notifications'")->num_rows > 0) {
            $pl = json_encode(['type' => 'donor_confirmed_scheduling', 'blood_donation_id' => $bloodDonationId]);
            $n = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'donor_confirmed_scheduling', ?, 'queued')");
            $n->bind_param("ss", $rec['recipient_id'], $pl);
            $n->execute();
            $n->close();
        }
        echo json_encode(['success' => true, 'message' => 'Donation confirmed. Countdown and reminders are set.']);
    } else {
        $upd = $conn->prepare("UPDATE blood_donations SET status = 'failed', donor_remarks = ? WHERE id = ?");
        $remark = trim($_POST['remarks'] ?? 'Declined by donor');
        $upd->bind_param("si", $remark, $bloodDonationId);
        $upd->execute();
        $upd->close();
        echo json_encode(['success' => true, 'message' => 'Donation declined.']);
    }
    exit;
}

// --- completion_response ---
if ($action === 'completion_response') {
    $bloodDonationId = (int)($_POST['blood_donation_id'] ?? 0);
    $response = trim($_POST['response'] ?? ''); // yes, no, reschedule
    $remarks = trim($_POST['remarks'] ?? '');
    $newDate = trim($_POST['new_date'] ?? '');
    $newTime = trim($_POST['new_time'] ?? '');

    if (!$bloodDonationId || !in_array($response, ['yes', 'no', 'reschedule'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid blood_donation_id or response (yes/no/reschedule)']);
        exit;
    }

    $chk = $conn->prepare("SELECT id, donor_id, recipient_id, status FROM blood_donations WHERE id = ? AND (donor_id = ? OR recipient_id = ?)");
    $chk->bind_param("iss", $bloodDonationId, $userId, $userId);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row || $row['status'] !== 'scheduled') {
        echo json_encode(['success' => false, 'error' => 'Donation not found or already completed']);
        exit;
    }

    $isDonor = ($row['donor_id'] === $userId);

    if ($response === 'yes') {
        $status = 'completed';
        $col = $isDonor ? 'donor_remarks' : 'recipient_remarks';
        $upd = $conn->prepare("UPDATE blood_donations SET status = ?, {$col} = ? WHERE id = ?");
        $upd->bind_param("ssi", $status, $remarks, $bloodDonationId);
        $upd->execute();
        $upd->close();
        echo json_encode(['success' => true, 'message' => 'Thank you! Donation marked as completed.']);
    } elseif ($response === 'no') {
        $status = 'failed';
        $col = $isDonor ? 'donor_remarks' : 'recipient_remarks';
        $upd = $conn->prepare("UPDATE blood_donations SET status = ?, {$col} = ? WHERE id = ?");
        $upd->bind_param("ssi", $status, $remarks, $bloodDonationId);
        $upd->execute();
        $upd->close();
        echo json_encode(['success' => true, 'message' => 'Donation marked as failed.']);
    } else { // reschedule
        if (!$newDate || !$newTime) {
            echo json_encode(['success' => false, 'error' => 'New date and time required for reschedule']);
            exit;
        }
        $dR = $isDonor ? $remarks : '';
        $rR = $isDonor ? '' : $remarks;
        $upd = $conn->prepare("UPDATE blood_donations SET status = 'scheduled', donation_date = ?, donation_time = ?, donor_confirmed = 0, donor_confirmed_at = NULL, completion_asked_at = NULL, donor_remarks = ?, recipient_remarks = ? WHERE id = ?");
        $upd->bind_param("ssssi", $newDate, $newTime, $dR, $rR, $bloodDonationId);
        $upd->execute();
        $upd->close();

        $conn->query("DELETE FROM scheduling_reminders WHERE blood_donation_id = " . (int)$bloodDonationId);

        $rec = ($row['donor_id'] === $userId) ? $row['recipient_id'] : $row['donor_id'];
        if ($conn->query("SHOW TABLES LIKE 'emergency_notifications'")->num_rows > 0) {
            $pl = json_encode(['type' => 'scheduling_confirmation', 'blood_donation_id' => $bloodDonationId, 'donation_date' => $newDate, 'donation_time' => $newTime]);
            $n = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'scheduling_confirmation', ?, 'queued')");
            $n->bind_param("ss", $rec, $pl);
            $n->execute();
            $n->close();
        }
        echo json_encode(['success' => true, 'message' => 'Rescheduled. Donor will be asked to confirm the new date.']);
    }
    exit;
}

// --- get_pending_confirmations ---
if ($action === 'get_pending_confirmations') {
    if (!$profileManager->hasRole('donor')) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }
    $t = $conn->query("SHOW TABLES LIKE 'blood_donations'");
    if (!$t || $t->num_rows === 0) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }
    $chkCol = $conn->query("SHOW COLUMNS FROM blood_donations LIKE 'donor_confirmed'");
    if (!$chkCol || $chkCol->num_rows === 0) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }
    $q = $conn->prepare("SELECT bd.*, u.first_name AS recipient_first, u.last_name AS recipient_last FROM blood_donations bd JOIN users u ON u.user_id = bd.recipient_id WHERE bd.donor_id = ? AND bd.status = 'scheduled' AND COALESCE(bd.donor_confirmed, 0) = 0");
    $q->bind_param("s", $userId);
    $q->execute();
    $res = $q->get_result();
    $items = [];
    while ($r = $res->fetch_assoc()) $items[] = $r;
    $q->close();
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

// --- get_completion_asks ---
if ($action === 'get_completion_asks') {
    $t = $conn->query("SHOW TABLES LIKE 'blood_donations'");
    if (!$t || $t->num_rows === 0) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }
    $chkCol = $conn->query("SHOW COLUMNS FROM blood_donations LIKE 'completion_asked_at'");
    if (!$chkCol || $chkCol->num_rows === 0) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }
    $q = $conn->prepare("SELECT bd.*, u1.first_name AS recipient_first, u1.last_name AS recipient_last, u2.first_name AS donor_first, u2.last_name AS donor_last FROM blood_donations bd JOIN users u1 ON u1.user_id = bd.recipient_id JOIN users u2 ON u2.user_id = bd.donor_id WHERE (bd.donor_id = ? OR bd.recipient_id = ?) AND bd.status = 'scheduled' AND bd.completion_asked_at IS NOT NULL");
    $q->bind_param("ss", $userId, $userId);
    $q->execute();
    $res = $q->get_result();
    $items = [];
    while ($r = $res->fetch_assoc()) $items[] = $r;
    $q->close();
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}
