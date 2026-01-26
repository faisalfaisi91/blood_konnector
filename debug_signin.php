<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
include("assets/lib/openconn.php");

echo "<!-- DEBUG INFO -->\n";
echo "<!-- POST Data: " . json_encode($_POST) . " -->\n";
echo "<!-- REQUEST METHOD: " . $_SERVER['REQUEST_METHOD'] . " -->\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!-- FORM SUBMITTED -->\n";
    
    // Check if button is set
    if (isset($_POST['btnSignIn'])) {
        echo "<!-- BUTTON FOUND -->\n";
        
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        echo "<!-- EMAIL: " . htmlspecialchars($email) . " -->\n";
        echo "<!-- PASSWORD LENGTH: " . strlen($password) . " -->\n";
        
        if (empty($email) || empty($password)) {
            echo "<!-- ERROR: EMAIL OR PASSWORD EMPTY -->\n";
        } else {
            // Try database query
            $query = "SELECT * FROM users WHERE LOWER(email) = LOWER(?)";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                echo "<!-- DATABASE ERROR: " . $conn->error . " -->\n";
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                echo "<!-- USER FOUND: " . ($result->num_rows > 0 ? 'YES' : 'NO') . " -->\n";
                
                if ($result->num_rows > 0) {
                    $user = mysqli_fetch_assoc($result);
                    echo "<!-- USER ID: " . htmlspecialchars($user['user_id']) . " -->\n";
                    echo "<!-- EMAIL VERIFIED: " . $user['email_verified'] . " -->\n";
                    
                    // Check password
                    $password_match = false;
                    if (strlen($user['password']) == 32) {
                        $password_match = (md5($password) === $user['password']);
                        echo "<!-- PASSWORD TYPE: MD5 -->\n";
                    } else {
                        $password_match = password_verify($password, $user['password']);
                        echo "<!-- PASSWORD TYPE: BCRYPT -->\n";
                    }
                    
                    echo "<!-- PASSWORD MATCH: " . ($password_match ? 'YES' : 'NO') . " -->\n";
                }
                
                $stmt->close();
            }
        }
    } else {
        echo "<!-- BUTTON NOT FOUND -->\n";
    }
} else {
    echo "<!-- NOT A POST REQUEST -->\n";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign In Debug</title>
</head>
<body>
<h1>Sign In Debug Test</h1>

<form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit" name="btnSignIn">Sign In</button>
</form>

<p>Check the HTML source for debug info</p>
</body>
</html>
