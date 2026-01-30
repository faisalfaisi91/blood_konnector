
<?php
/**
 * Lifeline reminder/timeout processor
 * Run this via cron: php assets/lib/lifeline-cron.php
 * Handles 12-hour timeouts for confirmations and feedback, queues notifications, and marks requests as expired.
 */
require_once __DIR__ . '/openconn.php';
require_once __DIR__ . '/email-helper.php';

$now = date('Y-m-d H:i:s');

// 1. Expire stale requests (pending/awaiting_confirmation) with responder_timeout_at < now
$expireStmt = $conn->prepare("
    UPDATE lifeline_requests
    SET status = 'expired', updated_at = NOW()
    WHERE status IN ('pending','awaiting_confirmation')
      AND responder_timeout_at IS NOT NULL
      AND responder_timeout_at < ?
");
$expireStmt->bind_param("s", $now);
$expireStmt->execute();
$expired = $expireStmt->affected_rows;
$expireStmt->close();

// 2. Queue notifications for confirmations about to expire (within 1 hour of timeout)
$notifStmt = $conn->prepare("
    SELECT id, recipient_id, accepted_donor_id, responder_timeout_at, status
    FROM lifeline_requests
    WHERE status = 'awaiting_confirmation'
      AND responder_timeout_at IS NOT NULL
      AND responder_timeout_at > ?
      AND responder_timeout_at <= DATE_ADD(?, INTERVAL 1 HOUR)
");
$notifStmt->bind_param("ss", $now, $now);
$notifStmt->execute();
$result = $notifStmt->get_result();
$queued = 0;
while ($row = $result->fetch_assoc()) {
    // Queue in-app and email notification for recipient
    $recipientId = $row['recipient_id'];
    $requestId = $row['id'];
    $timeoutAt = $row['responder_timeout_at'];
    $payload = json_encode([
        'request_id' => $requestId,
        'timeout_at' => $timeoutAt,
        'type' => 'lifeline_confirmation_timeout'
    ]);
    // In-app notification
    $stmt = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'lifeline_confirmation_timeout', ?, 'queued')");
    $stmt->bind_param("ss", $recipientId, $payload);
    $stmt->execute();
    $stmt->close();
    // Email notification (if email available)
    $userStmt = $conn->prepare("SELECT email, first_name FROM users WHERE user_id = ? LIMIT 1");
    $userStmt->bind_param("s", $recipientId);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    if ($user = $userRes->fetch_assoc()) {
        $mail = getConfiguredMailer();
        $mail->addAddress($user['email'], $user['first_name']);
        $mail->Subject = 'Action Required: Confirm Donor for Your LifeLine Request';
        $mail->isHTML(true);
        $mail->Body = '<p>Dear ' . htmlspecialchars($user['first_name']) . ',</p>' .
            '<p>Your LifeLine blood donation request requires confirmation. Please confirm your donor before the request expires.</p>' .
            '<p><strong>Request ID:</strong> ' . $requestId . '<br><strong>Expires At:</strong> ' . $timeoutAt . '</p>' .
            '<p><a href="' . getBaseUrl() . '/lifeline-recipient-dashboard.php">Go to Dashboard</a></p>';
        $mail->AltBody = 'Your LifeLine blood donation request requires confirmation. Please confirm your donor before the request expires.';
        $mail->send();
    }
    $userStmt->close();
    $queued++;
}
$notifStmt->close();

// 3. Queue feedback reminders for donations awaiting feedback (pending > 12h)
$feedbackStmt = $conn->prepare("
    SELECT id, recipient_id, accepted_donor_id, preferred_date, preferred_time
    FROM lifeline_requests
    WHERE status = 'awaiting_feedback'
      AND TIMESTAMPDIFF(HOUR, CONCAT(preferred_date, ' ', preferred_time), ?) >= 12
");
$feedbackStmt->bind_param("s", $now);
$feedbackStmt->execute();
$result2 = $feedbackStmt->get_result();
$feedbackQueued = 0;
while ($row = $result2->fetch_assoc()) {
    $recipientId = $row['recipient_id'];
    $requestId = $row['id'];
    $payload = json_encode([
        'request_id' => $requestId,
        'type' => 'lifeline_feedback_reminder'
    ]);
    // In-app notification
    $stmt = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'lifeline_feedback_reminder', ?, 'queued')");
    $stmt->bind_param("ss", $recipientId, $payload);
    $stmt->execute();
    $stmt->close();
    // Email notification (if email available)
    $userStmt = $conn->prepare("SELECT email, first_name FROM users WHERE user_id = ? LIMIT 1");
    $userStmt->bind_param("s", $recipientId);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    if ($user = $userRes->fetch_assoc()) {
        $mail = getConfiguredMailer();
        $mail->addAddress($user['email'], $user['first_name']);
        $mail->Subject = 'Reminder: Please Provide Feedback for Your LifeLine Donation';
        $mail->isHTML(true);
        $mail->Body = '<p>Dear ' . htmlspecialchars($user['first_name']) . ',</p>' .
            '<p>Please provide feedback for your recent LifeLine blood donation. Your response helps us improve the service.</p>' .
            '<p><strong>Request ID:</strong> ' . $requestId . '</p>' .
            '<p><a href="' . getBaseUrl() . '/lifeline-recipient-dashboard.php">Go to Dashboard</a></p>';
        $mail->AltBody = 'Please provide feedback for your recent LifeLine blood donation.';
        $mail->send();
    }
    $userStmt->close();
    $feedbackQueued++;
}
$feedbackStmt->close();

echo json_encode([
    'expired' => $expired,
    'confirmation_notifications_queued' => $queued,
    'feedback_reminders_queued' => $feedbackQueued,
    'timestamp' => $now
]);
