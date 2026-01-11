<?php
/**
 * DEPRECATED: Legacy lifeline notification dispatcher
 * This file is kept for backward compatibility. It now forwards to the
 * new Emergency dispatcher: assets/lib/emergency-send.php
 * Use with cron: php assets/lib/emergency-send.php
 */

require_once __DIR__ . '/emergency-send.php';

// If this file is executed directly (CLI), ensure the emergency send logic runs.
if (php_sapi_name() === 'cli') {
    // emergency-send.php exposes the same behavior when included
    // so nothing more to do here; the included file will execute its logic.
}

// Provide minimal JSON output for callers expecting the same shape
echo json_encode([
    'processed' => null,
    'sent' => null,
    'failed' => null,
    'timestamp' => date('c'),
    'note' => 'This lifeline-send.php is deprecated, forwarded to emergency-send.php'
]);

