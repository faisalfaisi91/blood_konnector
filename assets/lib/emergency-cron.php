<?php
/**
 * Emergency reminder/timeout processor
 * Run this via cron: php assets/lib/emergency-cron.php
 */
require_once __DIR__ . '/openconn.php';

$now = date('Y-m-d H:i:s');

// Expire stale pending/rescheduled/confirmed with responder timeout
$expireStmt = $conn->prepare("
    UPDATE emergency_requests
    SET status = 'expired', updated_at = NOW()
    WHERE status IN ('pending','rescheduled','confirmed')
      AND responder_timeout_at IS NOT NULL
      AND responder_timeout_at < ?
");
$expireStmt->bind_param("s", $now);
$expireStmt->execute();
$expired = $expireStmt->affected_rows;
$expireStmt->close();

// Send due reminders
$due = $conn->prepare("
    SELECT lr.id, lr.recipient_id, lc.donor_id, lr.status, lr.location, lc.scheduled_at, r.id AS reminder_id, r.type
    FROM emergency_reminders r
    JOIN emergency_requests lr ON lr.id = r.request_id
    LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id
    WHERE r.sent_at IS NULL AND r.scheduled_for <= ?
");
$due->bind_param("s", $now);
$due->execute();
$result = $due->get_result();

while ($row = $result->fetch_assoc()) {
    $reminderId = $row['reminder_id'];
    $users = array_filter([$row['recipient_id'], $row['donor_id']]);
    foreach ($users as $uid) {
        $stmt = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', ?, ?, 'queued')");
        $payload = json_encode([
            'request_id' => $row['id'],
            'scheduled_at' => $row['scheduled_at'],
            'location' => $row['location'],
            'type' => $row['type']
        ]);
        $template = 'emergency_' . $row['type'];
        $stmt->bind_param("sss", $uid, $template, $payload);
        $stmt->execute();
        $stmt->close();

        // Duplicate for email/SMS channels for later processing (optional)
        $channels = ['email','sms'];
        foreach ($channels as $ch) {
            $s2 = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, ?, ?, ?, 'queued')");
            $s2->bind_param("ssss", $uid, $ch, $template, $payload);
            $s2->execute();
            $s2->close();
        }
    }
    $mark = $conn->prepare("UPDATE emergency_reminders SET sent_at = NOW() WHERE id = ?");
    $mark->bind_param("i", $reminderId);
    $mark->execute();
    $mark->close();
}
$due->close();

echo json_encode([
    'expired' => $expired ?? 0,
    'processed_reminders' => $result ? $result->num_rows : 0,
    'timestamp' => $now
]);
