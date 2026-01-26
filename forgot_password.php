<?php
session_start();
require_once 'config.php';
require_once 'assets/lib/email-helper.php';
include('assets/lib/openconn.php');

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$alert_message = "";
$alert_type = "danger";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
    if (!$stmt) {
        $alert_message = "Database error: Unable to prepare query.";
    } else {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];
            $first_name = $user['first_name'];

            // Generate unique reset token
            $reset_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

            // Store token in database
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE user_id = ?");
            if (!$stmt) {
                $alert_message = "Database error: Unable to prepare update query.";
            } else {
                $stmt->bind_param("sss", $reset_token, $expires_at, $user_id);
                if ($stmt->execute()) {
                    // Log the attempt
                    error_log("Attempting to send password reset email to: $email (User: $first_name, ID: $user_id)");
                    
                    // Send reset email
                    try {
                        $email_sent = sendPasswordResetEmail($email, $first_name, $reset_token);
                        if ($email_sent) {
                            $alert_message = "A password reset link has been sent to your email.";
                            $alert_type = "success";
                            error_log("Password reset email function returned TRUE for: $email");
                        } else {
                            // Log the error for debugging
                            error_log("Password reset email sending returned FALSE for: $email");
                            $alert_message = "Failed to send reset email. Please try again or contact support.";
                        }
                    } catch (Exception $e) {
                        error_log("Exception during password reset email send: " . $e->getMessage());
                        $alert_message = "Failed to send reset email: " . $e->getMessage() . ". Please contact support.";
                    } catch (\Exception $e) {
                        error_log("Exception during password reset email send: " . $e->getMessage());
                        $alert_message = "Failed to send reset email. Please contact support.";
                    }
                } else {
                    $alert_message = "Error generating reset link. Please try again.";
                    error_log("Database update failed for password reset: " . mysqli_error($conn));
                }
            }
        } else {
            $alert_message = "If that email exists, a reset link has been sent.";
        }
        $stmt->close();
    }
}

// sendPasswordResetEmail() function moved to assets/lib/email-helper.php
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
    <?php include('assets/includes/link-js.php'); ?>
</head>
<body>
    <?php include('assets/includes/preloader.php'); ?>
    <?php include('assets/includes/scroll-to-top.php'); ?>
    <?php include('assets/includes/header.php'); ?>

    <?php if ($alert_message): ?>
        <script>
            Swal.fire({
                icon: '<?php echo $alert_type; ?>',
                title: '<?php echo $alert_type === "success" ? "✅ Email Sent!" : "❌ Error!"; ?>',
                html: '<?php echo $alert_message; ?><?php if($alert_type === "success"): ?><br><br><strong style="color: #EA062B;">📧 Please check your email inbox and spam folder!</strong><?php endif; ?>',
                confirmButtonText: 'OK, Got it!',
                confirmButtonColor: '#EA062B',
                allowOutsideClick: false,
                showConfirmButton: true
            });
        </script>
    <?php endif; ?>

    <section class="ptb-100">
        <div class="auth-container">
            
            <?php if ($alert_message && $alert_type === "success"): ?>
                <!-- Success Message Box - Stays visible on page -->
                <div class="alert alert-success" style="background: #d4edda; border: 2px solid #28a745; border-radius: 12px; padding: 25px; margin-bottom: 30px; text-align: center;">
                    <div style="font-size: 60px; margin-bottom: 15px;">✅</div>
                    <h3 style="color: #155724; margin-bottom: 15px; font-weight: 700; font-size: 24px;">Email Sent Successfully!</h3>
                    <p style="color: #155724; margin-bottom: 20px; font-size: 16px; line-height: 1.6;">
                        <?php echo $alert_message; ?>
                    </p>
                    <div style="background: #c3e6cb; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left;">
                        <strong style="color: #155724; font-size: 16px;">
                            <i class="fas fa-info-circle"></i> Next Steps:
                        </strong>
                        <ol style="margin: 15px 0 0 0; padding-left: 20px; color: #155724; font-size: 15px; line-height: 2;">
                            <li>Check your email inbox</li>
                            <li>Look in your spam/junk folder if you don't see it</li>
                            <li>Click the password reset link (valid for 1 hour)</li>
                            <li>Create your new password</li>
                        </ol>
                    </div>
                    <a href="sign-in.php" class="btn btn-lg mt-3" style="background: #28a745; color: white; border: none; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Back to Sign In
                    </a>
                </div>
            <?php elseif ($alert_message && $alert_type === "danger"): ?>
                <!-- Error Message Box -->
                <div class="alert alert-danger" style="background: #f8d7da; border: 2px solid #dc3545; border-radius: 12px; padding: 25px; margin-bottom: 30px; text-align: center;">
                    <div style="font-size: 60px; margin-bottom: 15px;">❌</div>
                    <h3 style="color: #721c24; margin-bottom: 15px; font-weight: 700;">Error</h3>
                    <p style="color: #721c24; font-size: 16px;"><?php echo $alert_message; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h2>Forgot Password?</h2>
                <p class="text-muted">Enter your registered email to receive a password reset link</p>
            </div>

            <form method="POST" id="forgotForm">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4" id="submitBtn">
                    <span class="btn-text">
                        Send Reset Link <i class="fas fa-arrow-right ms-2"></i>
                    </span>
                    <span class="btn-loading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Sending Email...
                    </span>
                </button>
            </form>

            <div class="auth-footer">
                <p class="mb-0">Remember your password? <a href="sign-in.php">Sign in here</a></p>
            </div>
        </div>
    </section>

    <?php include('assets/includes/footer.php'); ?>
    <?php include('assets/includes/link-js.php'); ?>
    
    <script>
        // Loading state for form submission
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoading = btn.querySelector('.btn-loading');
            
            // Show loading state
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
            btn.disabled = true;
            btn.style.opacity = '0.7';
        });
    </script>
</body>
</html>