<?php
session_start();
include('assets/lib/openconn.php');

// Password hashing configuration
// Set to 'md5' for legacy compatibility or 'bcrypt' for secure modern hashing
// IMPORTANT: 'bcrypt' (using password_hash) is HIGHLY RECOMMENDED for security
$password_hash_method = 'bcrypt'; // Options: 'md5' or 'bcrypt'

$alert_message = "";
$alert_type = "danger";
$token = isset($_GET['token']) ? mysqli_real_escape_string($conn, $_GET['token']) : '';

if (empty($token)) {
    $alert_message = "Invalid or missing reset token.";
} else {
    // Check if token is valid and not expired
    $stmt = $conn->prepare("SELECT user_id, reset_token_expires FROM users WHERE reset_token = ?");
    if (!$stmt) {
        $alert_message = "Database error: Unable to prepare query.";
    } else {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $expires_at = strtotime($user['reset_token_expires']);
            $current_time = time();
            
            if ($expires_at < $current_time) {
                $alert_message = "This password reset link has expired.";
            } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $password = mysqli_real_escape_string($conn, $_POST['password']);
                
                // Validate password
                $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/';
                if (!preg_match($password_pattern, $password)) {
                    $alert_message = "Password must be at least 6 characters long, containing uppercase, lowercase, and digits.";
                } else {
                    // Hash new password based on configuration
                    if ($password_hash_method === 'md5') {
                        // MD5 hashing (LEGACY - NOT SECURE)
                        $hashed_password = md5($password);
                    } else {
                        // Bcrypt hashing (SECURE - RECOMMENDED)
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    }
                    
                    // Update password and clear reset token
                    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ?");
                    if (!$stmt) {
                        $alert_message = "Database error: Unable to prepare update query.";
                    } else {
                        $stmt->bind_param("ss", $hashed_password, $token);
                        if ($stmt->execute()) {
                            $alert_message = "Password reset successful! You can now sign in.";
                            $alert_type = "success";
                        } else {
                            $alert_message = "Error updating password. Please try again.";
                        }
                    }
                }
            }
        } else {
            $alert_message = "Invalid reset token.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #EA062B;
            --primary-hover: #C10523;
            --text: #2d3748;
            --light: #f8f9fa;
        }

        .auth-container {
            max-width: 480px;
            margin: 2rem auto;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            background: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text);
            margin: 1rem 0 0.5rem;
        }

        .auth-icon {
            width: 80px;
            height: 80px;
            background: rgba(234, 6, 43, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .auth-icon i {
            font-size: 2rem;
            color: var(--primary);
        }

        .form-control {
            height: 48px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 6, 43, 0.1);
        }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            border-radius: 8px;
            background: var(--primary);
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #718096;
        }

        .auth-footer a {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
        }

        .password-wrapper {
            position: relative;
        }

        .eye-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
        }

        .password-hint {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }

        @media (max-width: 576px) {
            .auth-container {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .auth-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include('assets/includes/preloader.php'); ?>
    <?php include('assets/includes/scroll-to-top.php'); ?>
    <?php include('assets/includes/header.php'); ?>

    <?php if ($alert_message): ?>
        <script>
            Swal.fire({
                icon: '<?php echo $alert_type; ?>',
                title: '<?php echo $alert_type === "success" ? "Success!" : "Error!"; ?>',
                text: '<?php echo $alert_message; ?>',
                timer: <?php echo $alert_type === "success" ? 5000 : 0; ?>,
                showConfirmButton: <?php echo $alert_type === "success" ? "false" : "true"; ?>,
                willClose: () => {
                    <?php if ($alert_type === "success"): ?>
                        window.location.href = 'sign-in.php';
                    <?php endif; ?>
                }
            });
        </script>
    <?php endif; ?>

    <section class="ptb-100">
        <div class="auth-container">
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h2>Reset Password</h2>
                <p class="text-muted">Enter your new password below</p>
            </div>

            <?php if (empty($alert_message) || $alert_type !== "success"): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="password-wrapper">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Enter new password" required>
                                <span class="eye-icon" onclick="togglePassword('password')">&#x1F441;</span>
                            </div>
                            <small class="password-hint">Password must be at least 6 characters long, containing uppercase, lowercase, and digits.</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-4">
                        Reset Password <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p class="mb-0">Return to <a href="sign-in.php">Sign in</a></p>
            </div>
        </div>
    </section>

    <?php include('assets/includes/footer.php'); ?>
    <?php include('assets/includes/link-js.php'); ?>

    <script>
        function togglePassword(id) {
            const passwordField = document.getElementById(id);
            const eyeIcon = passwordField.nextElementSibling;
            passwordField.type = passwordField.type === "password" ? "text" : "password";
            eyeIcon.innerHTML = passwordField.type === "password" ? "&#x1F441;" : "&#x1F440;";
        }
    </script>
</body>
</html>