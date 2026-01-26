<?php
/**
 * PHP Configuration Diagnostic Tool
 * 
 * Shows critical PHP settings that affect session management
 * Helps diagnose session persistence issues
 */

$session_config_keys = [
    'session.save_handler',
    'session.save_path',
    'session.name',
    'session.auto_start',
    'session.gc_maxlifetime',
    'session.gc_probability',
    'session.gc_divisor',
    'session.use_cookies',
    'session.use_only_cookies',
    'session.cookie_lifetime',
    'session.cookie_path',
    'session.cookie_domain',
    'session.cookie_secure',
    'session.cookie_httponly',
    'session.cookie_samesite',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Configuration Diagnostic</title>
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
            color: #ea062b;
            border-bottom: 2px solid #ea062b;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #f0f0f0;
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #ddd;
            padding: 10px;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .key {
            font-family: monospace;
            font-weight: bold;
            width: 40%;
        }
        .value {
            font-family: monospace;
            background: #f0f0f0;
            padding: 5px;
            border-radius: 4px;
        }
        .warning {
            color: #dc3545;
            font-weight: bold;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .section {
            margin: 30px 0;
        }
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info-box h3 {
            margin-top: 0;
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 PHP Session Configuration Diagnostic</h1>
        
        <div class="info-box">
            <h3>Purpose</h3>
            <p>This page shows the PHP session configuration that affects user login and session persistence. 
            Review these settings if users are not staying logged in after sign-in.</p>
        </div>

        <div class="section">
            <h2>Core Session Settings</h2>
            <table>
                <thead>
                    <tr>
                        <th>Configuration Key</th>
                        <th>Current Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    global $session_config_keys;
                    foreach ($session_config_keys as $key) {
                        $value = ini_get($key);
                        $status = '✓';
                        
                        // Add warnings for problematic settings
                        if ($key === 'session.save_handler' && $value !== 'files') {
                            $status = '<span class="warning">⚠️ Non-standard handler</span>';
                        }
                        if ($key === 'session.save_path' && empty($value)) {
                            $status = '<span class="warning">⚠️ Save path not set</span>';
                        }
                        if ($key === 'session.use_only_cookies' && $value !== '1') {
                            $status = '<span class="warning">⚠️ May allow non-cookie sessions</span>';
                        }
                        if ($key === 'session.cookie_httponly' && $value !== '1') {
                            $status = '<span class="warning">⚠️ Cookie vulnerable to XSS</span>';
                        }
                        
                        echo '<tr>';
                        echo '<td class="key">' . htmlspecialchars($key) . '</td>';
                        echo '<td class="value">' . htmlspecialchars($value === '' ? '(empty)' : $value) . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Runtime Information</h2>
            <table>
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="key">PHP Version</td>
                        <td class="value"><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <td class="key">Session Status</td>
                        <td class="value">
                            <?php
                            $status = session_status();
                            echo $status . ' (';
                            switch ($status) {
                                case PHP_SESSION_DISABLED:
                                    echo 'Disabled</span>';
                                    break;
                                case PHP_SESSION_NONE:
                                    echo 'No session started yet</span>';
                                    break;
                                case PHP_SESSION_ACTIVE:
                                    echo '<span class="success">Active</span>';
                                    break;
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="key">Session ID</td>
                        <td class="value"><?php echo session_id(); ?></td>
                    </tr>
                    <tr>
                        <td class="key">Session Name</td>
                        <td class="value"><?php echo session_name(); ?></td>
                    </tr>
                    <tr>
                        <td class="key">Current Directory</td>
                        <td class="value"><?php echo getcwd(); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>File System Information</h2>
            <table>
                <thead>
                    <tr>
                        <th>Path</th>
                        <th>Status</th>
                        <th>Writable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $save_path = ini_get('session.save_path');
                    $paths = [
                        'Session Save Path' => $save_path,
                        'This File Directory' => __DIR__,
                        'Script Directory' => dirname(__FILE__),
                        'Default Temp' => sys_get_temp_dir(),
                    ];
                    
                    foreach ($paths as $label => $path) {
                        $exists = is_dir($path);
                        $writable = is_writable($path);
                        
                        echo '<tr>';
                        echo '<td class="value">' . htmlspecialchars($path) . '</td>';
                        echo '<td>' . ($exists ? '<span class="success">✓ Exists</span>' : '<span class="warning">✗ Missing</span>') . '</td>';
                        echo '<td>' . ($writable ? '<span class="success">✓ Writable</span>' : '<span class="warning">✗ Not Writable</span>') . '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="info-box">
            <h3>🔧 Troubleshooting Guide</h3>
            <ul>
                <li><strong>Session not persisting:</strong> Check that session.save_path is writable</li>
                <li><strong>Session files not created:</strong> Verify session handler is 'files' and save_path exists</li>
                <li><strong>Sessions timing out too fast:</strong> Check session.gc_maxlifetime value</li>
                <li><strong>Cookies not being set:</strong> Ensure session.use_cookies is '1'</li>
                <li><strong>Cross-site session issues:</strong> Review cookie_domain and cookie_path settings</li>
            </ul>
        </div>

        <hr>
        <p style="text-align: center; color: #666;">
            <a href="debug-session.php" style="color: #ea062b; text-decoration: none; font-weight: bold;">← Back to Session Debugger</a>
        </p>
    </div>
</body>
</html>
