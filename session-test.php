<?php
session_start();

echo "Session Status: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session data:<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Session Handler Info</h3>";
echo "Session Save Handler: " . ini_get('session.save_handler') . "<br>";
echo "Session Save Path: " . ini_get('session.save_path') . "<br>";
echo "Session Cookie Path: " . ini_get('session.cookie_path') . "<br>";
echo "Session Cookie Domain: " . ini_get('session.cookie_domain') . "<br>";
echo "Session Name: " . session_name() . "<br>";

echo "<h3>Cookies</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<h3>Test</h3>";
$_SESSION['test'] = 'This is a test value';
echo "Set test session variable. <a href='session-test.php'>Reload</a> to see if it persists.";

echo "<hr>";
echo "<a href='sign-in'>Back to Sign-In</a>";
?>
