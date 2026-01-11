<?php
/**
 * Lifeline reminder/timeout processor
 * Run this via cron: php assets/lib/lifeline-cron.php
 */
require_once __DIR__ . '/openconn.php';

$now = date('Y-m-d H:i:s');

// Expire stale pending/rescheduled/confirmed with responder timeout
$expireStmt = $conn->prepare("
    UPDATE lifeline_requests
    SET status = 'expired', updated_at = NOW()
    WHERE status IN ('pending','rescheduled','confirmed')
      AND responder_timeout_at IS NOT NULL
      AND responder_timeout_at < ?
");
$expireStmt->bind_param("s", $now);
$expireStmt->execute();
$expired = $expireStmt->affected_rows;
<?php
/**
 * DEPRECATED: Legacy lifeline reminder/timeout processor
 * This wrapper forwards to assets/lib/emergency-cron.php to preserve
 * existing cron job references. Run with: php assets/lib/emergency-cron.php
 */

require_once __DIR__ . '/emergency-cron.php';

// Minimal response for compatibility
echo json_encode([
    'note' => 'This lifeline-cron.php is deprecated and forwards to emergency-cron.php',
    'timestamp' => date('c')
]);
while ($row = $result->fetch_assoc()) {

    $reminderId = $row['reminder_id'];
