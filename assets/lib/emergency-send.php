<?php
/**
 * Emergency notification dispatcher
 * Use with cron: php assets/lib/emergency-send.php
 */
require_once __DIR__ . '/openconn.php';
require_once __DIR__ . '/email-helper.php';

function updateStatus($conn, $id, $status) {
    $stmt = $conn->prepare("UPDATE emergency_notifications SET status = ?, sent_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();
}

function buildEmailContent($template, $payload) {
    $when = $payload['scheduled_at'] ?? '';
    $loc = $payload['location'] ?? '';
    $id = $payload['request_id'] ?? '';
    switch ($template) {
        case 'emergency_24h':
            $subject = "Reminder: Emergency donation in 24h (Request #$id)";
            $body = "Your donation is scheduled in about 24 hours.\nTime: $when\nLocation: $loc\nRequest #$id";
            break;
        case 'emergency_6h':
            $subject = "Reminder: Emergency donation in 6h (Request #$id)";
            $body = "Donation in ~6 hours.\nTime: $when\nLocation: $loc\nRequest #$id";
            break;
        case 'emergency_1h':
            $subject = "Reminder: Emergency donation in 1h (Request #$id)";
            $body = "Donation in ~1 hour.\nTime: $when\nLocation: $loc\nRequest #$id";
            break;
        case 'emergency_final_timeout':
            $subject = "Action needed: Emergency request pending (Request #$id)";
            $body = "No response within the window. Please respond or reschedule.\nTime: $when\nLocation: $loc\nRequest #$id";
            break;
        default:
            $subject = "Emergency notification (Request #$id)";
            $body = "Update for request #$id.\nTime: $when\nLocation: $loc";
    }
    return [$subject, nl2br(htmlspecialchars($body))];
}

$batchLimit = 50;
$queued = $conn->prepare("SELECT ln.*, u.email, u.first_name, u.phone_number FROM emergency_notifications ln JOIN users u ON u.user_id = ln.user_id WHERE ln.status='queued' ORDER BY ln.id ASC LIMIT ?");
$queued->bind_param("i", $batchLimit);
$queued->execute();
$result = $queued->get_result();

$sent = 0;
$failed = 0;

while ($row = $result->fetch_assoc()) {
    $payload = json_decode($row['payload'] ?? '{}', true) ?: [];
    $template = $row['template_key'];
    $channel = $row['channel'];
    $id = (int)$row['id'];

    try {
        if ($channel === 'email') {
            if (empty($row['email'])) {
                throw new Exception('No email on file');
            }
            [$subject, $body] = buildEmailContent($template, $payload);
            $mailer = getConfiguredMailer();
            $mailer->addAddress($row['email'], $row['first_name'] ?? '');
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = strip_tags(str_replace('<br>', "\n", $body));
            $mailer->send();
            updateStatus($conn, $id, 'sent');
            $sent++;
        } elseif ($channel === 'sms') {
            // Placeholder: integrate with SMS provider
            if (empty($row['phone_number'])) {
                throw new Exception('No phone number on file');
            }
            // TODO: call SMS gateway here
            updateStatus($conn, $id, 'sent');
            $sent++;
        } else { // in_app or push left to app layer
            updateStatus($conn, $id, 'sent');
            $sent++;
        }
    } catch (Exception $e) {
        error_log("emergency-send failed id {$id}: " . $e->getMessage());
        updateStatus($conn, $id, 'failed');
        $failed++;
    }
}

$queued->close();

echo json_encode([
    'processed' => $sent + $failed,
    'sent' => $sent,
    'failed' => $failed,
    'timestamp' => date('c')
]);
