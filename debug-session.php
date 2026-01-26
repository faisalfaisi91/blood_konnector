<?php
session_start();

// Get logs directory
$logs_dir = __DIR__ . '/logs';
$log_file = $logs_dir . '/signin_debug.log';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Debug</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #ea062b;
            padding-bottom: 10px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #ea062b;
        }
        .section h2 {
            margin-top: 0;
            color: #ea062b;
            font-size: 1.1rem;
        }
        pre {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .log-entry {
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .info {
            color: #007bff;
        }
        button {
            background: #ea062b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        button:hover {
            background: #c70520;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Session Debugging Page</h1>

        <div class="section">
            <h2>Current Session Data</h2>
            <?php if (!empty($_SESSION)): ?>
                <div class="success">✓ Session is active</div>
                <pre><?php print_r($_SESSION); ?></pre>
            <?php else: ?>
                <div class="error">✗ Session is empty</div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Server Information</h2>
            <pre><?php
echo "PHP Version: " . phpversion() . "\n";
echo "Current File: " . __FILE__ . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Path: " . ini_get('session.save_path') . "\n";
echo "Session Handler: " . ini_get('session.save_handler') . "\n";
echo "Session Status: " . session_status() . " (0=disabled, 1=none, 2=active)\n";
echo "Cookie Path: " . ini_get('session.cookie_path') . "\n";
echo "Cookie Domain: " . ini_get('session.cookie_domain') . "\n";
            ?></pre>
        </div>

        <div class="section">
            <h2>Cookies</h2>
            <pre><?php
if (isset($_COOKIE)) {
    print_r($_COOKIE);
} else {
    echo "No cookies found";
}
            ?></pre>
        </div>

        <div class="section">
            <h2>Sign-In Debug Log</h2>
            <?php if (file_exists($log_file)): ?>
                <div class="info">Log file exists at: <?php echo $log_file; ?></div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php
                    $logs = file_get_contents($log_file);
                    $log_lines = explode("\n", trim($logs));
                    foreach (array_reverse($log_lines) as $line) {
                        if (!empty($line)) {
                            echo '<div class="log-entry">' . htmlspecialchars($line) . '</div>';
                        }
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="error">✗ Debug log file not found</div>
                <p>Expected location: <?php echo $log_file; ?></p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Actions</h2>
            <form method="post" style="margin-bottom: 10px;">
                <button type="submit" name="action" value="clear_log">Clear Debug Log</button>
                <button type="submit" name="action" value="destroy_session">Destroy Session</button>
            </form>
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if ($_POST['action'] === 'clear_log' && file_exists($log_file)) {
                    unlink($log_file);
                    echo '<div class="success">✓ Debug log cleared</div>';
                } elseif ($_POST['action'] === 'destroy_session') {
                    session_destroy();
                    echo '<div class="success">✓ Session destroyed. Redirecting...</div>';
                    echo '<script>setTimeout(() => window.location.href = "sign-in", 2000);</script>';
                }
            }
            ?>
        </div>

        <div class="section">
            <h2>Navigation</h2>
            <p>
                <a href="sign-in" style="color: #ea062b; text-decoration: none; font-weight: bold;">← Back to Sign-In</a>
                <br><br>
                <a href="php-config-diagnostic.php" style="color: #007bff; text-decoration: none; font-weight: bold;">View PHP Configuration →</a>
                <br><small>Shows session handler, save path, and other critical settings</small>
            </p>
        </div>
    </div>
</body>
</html>
