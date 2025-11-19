<?php
session_start();
include("assets/lib/openconn.php");

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$alert_message = "";
$alert_type = "danger";

if (isset($_GET['code'])) {
    $verification_code = mysqli_real_escape_string($conn, $_GET['code']);
    
    // Debugging: Log the verification attempt
    error_log("Verification attempt - Code: $verification_code");
    
    // Check for unverified account with this code
    $check_code_query = "SELECT * FROM users 
                        WHERE verification_code='$verification_code' 
                        AND (status IS NULL OR status = '' OR status = 'pending' OR status = 'Null' OR email_verified = 0)";
    
    $result = mysqli_query($conn, $check_code_query);
    
    // Debugging: Log query results
    error_log("Verification query: $check_code_query");
    error_log("Found rows: " . mysqli_num_rows($result));
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Update account to verified status
        $update_query = "UPDATE users 
                         SET status='active', 
                             verification_code=NULL, 
                             email_verified=1,
                             last_activity=NOW()
                         WHERE verification_code='$verification_code'";
        
        if (mysqli_query($conn, $update_query)) {
            $alert_message = "Your email has been verified successfully!";
            $alert_type = "success";
            
            // Set session variables for automatic login
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_first_name'] = $user['first_name'];
            $_SESSION['user_last_name'] = $user['last_name'];
            $_SESSION['verified'] = true;
            
            // Debugging: Log successful verification
            error_log("Successfully verified user: " . $user['email']);
        } else {
            $alert_message = "Database error during verification: " . mysqli_error($conn);
            error_log("Verification update failed: " . mysqli_error($conn));
        }
    } else {
        // Check if already verified
        $check_verified = "SELECT * FROM users 
                          WHERE verification_code='$verification_code' 
                          AND (status='active' OR email_verified=1)";
        
        $verified_result = mysqli_query($conn, $check_verified);
        
        if (mysqli_num_rows($verified_result) > 0) {
            $alert_message = "Account already verified. Please sign in.";
            $alert_type = "info";
            error_log("Verification attempt for already verified account");
        } else {
            $alert_message = "Invalid verification code. No matching unverified account found.";
            error_log("Invalid verification code: $verification_code");
            
            // Additional check for debugging
            $check_code_existence = "SELECT * FROM users WHERE verification_code='$verification_code'";
            $existence_result = mysqli_query($conn, $check_code_existence);
            error_log("Code existence check: " . mysqli_num_rows($existence_result) . " rows found");
        }
    }
} else {
    $alert_message = "Missing verification code in URL.";
    error_log("Verification attempt without code parameter");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        .verification-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            background: #fff;
        }
        .alert {
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        #countdown {
            font-weight: bold;
            color: #EA062B;
        }
    </style>
</head>
<body>
    <?php include('assets/includes/header.php'); ?>

    <div class="container">
        <div class="verification-container">
            <div class="alert alert-<?php echo $alert_type; ?>">
                <h4 class="alert-heading">Email Verification</h4>
                <p><?php echo $alert_message; ?></p>
                <?php if ($alert_type == 'success'): ?>
                    <hr>
                    <p class="mb-0">You will be automatically logged in. Redirecting in <span id="countdown">5</span> seconds...</p>
                <?php endif; ?>
            </div>
            
            <?php if ($alert_type != 'success'): ?>
            <div class="text-center mt-3">
                <a href="sign-in" class="btn btn-primary">Go to Sign In</a>
                <a href="index" class="btn btn-secondary">Return Home</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include('assets/includes/footer.php'); ?>
    
    <?php if ($alert_type == 'success'): ?>
    <script>
        // Countdown timer for redirect
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = 'index'; // Redirect to dashboard after verification
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>