<?php
// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once 'config.php';
require_once 'assets/lib/email-helper.php';
session_start();
include("assets/lib/openconn.php");

$alert_message = ""; // For user feedback

// Password hashing configuration
// Set to 'md5' for legacy compatibility or 'bcrypt' for secure modern hashing
// IMPORTANT: 'bcrypt' (using password_hash) is HIGHLY RECOMMENDED for security
$password_hash_method = 'bcrypt'; // Options: 'md5' or 'bcrypt'

if (isset($_POST['btnSignUp'])) {
    // Sanitize inputs
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $password   = mysqli_real_escape_string($conn, $_POST['password']);
    $message    = mysqli_real_escape_string($conn, $_POST['message']);
    
    // Hash password based on configuration
    if ($password_hash_method === 'md5') {
        // MD5 hashing (LEGACY - NOT SECURE)
        $hashed_password = md5($password);
    } else {
        // Bcrypt hashing (SECURE - RECOMMENDED)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    }

    // Generate user details
    $user_id = 'bk' . substr(md5(uniqid(rand(), true)), 0, 6);
    $verification_code = md5(uniqid(rand(), true));
    $status = NULL;

    // Check existing email
    $check_email = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    if (mysqli_num_rows($check_email) > 0) {
        $alert_message = "This email is already registered. Please try another email.";
    } else {
        // Insert user data
        $insert_query = "INSERT INTO users (user_id, first_name, last_name, email, password, message, status, verification_code)
                         VALUES ('$user_id', '$first_name', '$last_name', '$email', '$hashed_password', '$message', NULL, '$verification_code')";
        
        if (mysqli_query($conn, $insert_query)) {
            // Send verification email
            $email_sent = sendVerificationEmail($email, $first_name, $verification_code);
            
            if ($email_sent) {
                $_SESSION['user_first_name'] = $first_name;
                $_SESSION['user_last_name']  = $last_name;
                $_SESSION['user_email']      = $email;
                $_SESSION['user_id']         = $user_id;
                
                $alert_message = "Sign-up successful! Please check your email to verify your account.";
            } else {
                $alert_message = "Account created but verification email failed to send. Please contact support.";
                // mysqli_query($conn, "DELETE FROM users WHERE email='$email'");
            }
        } else {
            $alert_message = "Error: Could not complete registration. Please try again.";
        }
    }
}

// sendVerificationEmail() function moved to assets/lib/email-helper.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>

    <!-- SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
        }

        .signup-card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            padding: 2.5rem;
            margin: 2rem auto;
            max-width: 700px;
            border: 1px solid #e5e7eb;
        }

        .form-control {
            height: 48px;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.75rem 1.25rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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
            display: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 0.875rem 2rem;
            font-size: 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #EA062B;
            transform: translateY(-2px);
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }

        .breadcrumb_section h2 {
            color: white;
            font-weight: 600;
        }

        .breadcrumb_section .active {
            color: #e0e7ff;
        }

        @media (max-width: 768px) {
            .signup-card {
                padding: 1.5rem;
                margin: 1rem;
            }
        }
    </style>
    <?php include('assets/includes/link-js.php'); ?>
</head>
<body>

    <?php include('assets/includes/preloader.php'); ?>
    <?php include('assets/includes/scroll-to-top.php'); ?>
    <?php include('assets/includes/header.php'); ?>

    <!-- Breadcrumb Section -->
    <div class="breadcrumb_section overflow-hidden ptb-150">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-6 col-lg-6 col-md-8 col-sm-10 col-12 text-center">
                    <h2>Create Your Account</h2>
                    <ul>
                        <li><a href="index">Home</a></li>
                        <li class="active">Sign Up</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Updated Registration Section -->
    <section class="ptb-100">
        <div class="container">
            <div class="signup-card">
                <h3 class="text-center mb-4" style="font-weight: 600; color: var(--text-color);">Create Your Account</h3>
                <form id="signupForm" method="post" enctype="multipart/form-data">
                    <div class="row g-4">
                        <!-- Name Fields -->
                        <div class="col-md-6">
                            <div class="position-relative">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" class="form-control ps-5" name="first_name" id="first_name" placeholder="First Name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="position-relative">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" class="form-control ps-5" name="last_name" id="last_name" placeholder="Last Name" required>
                            </div>
                        </div>

                        <!-- Email Field -->
                        <div class="col-12">
                            <div class="position-relative">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" class="form-control ps-5" id="email" name="email" placeholder="Email Address" required>
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="col-12">
                            <div class="password-wrapper">
                                <div class="position-relative">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" name="password" class="form-control ps-5" id="password" placeholder="Create Password" required>
                                    <i class="fas fa-eye eye-icon" onclick="togglePassword('password')"></i>
                                </div>
                                <small id="password-hint" class="password-hint">Password must be at least 6 characters long, containing uppercase, lowercase, and digits.</small>
                            </div>
                        </div>

                        <!-- Additional Info -->
                        <div class="col-12">
                            <textarea class="form-control" id="message" name="message" rows="4" placeholder="Tell us about yourself (Optional)"></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="col-12">
                            <button  name="btnSignUp" class="btn btn-primary w-100 py-3" type="submit">
                                <i class="fas fa-user-plus me-2"></i>
                                Create Account
                            </button>
                        </div>

                        <!-- Login Link -->
                        <div class="col-12 text-center mt-4">
                            <p class="mb-0">Already have an account? 
                                <a href="sign-in" class="text-decoration-none" style="color: var(--primary-color);">
                                    Sign In Here
                                </a>
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include('assets/includes/footer.php'); ?>
    <?php include('assets/includes/link-js.php'); ?>
</body>
</html>

<script>
    // SweetAlert Handling
    <?php if ($alert_message): ?>
        Swal.fire({
            icon: '<?php echo (strpos($alert_message, "successful") !== false ? "success" : "error"); ?>',
            title: '<?php echo (strpos($alert_message, "successful") !== false ? "Success!" : "Error!"); ?>',
            text: '<?php echo $alert_message; ?>',
            <?php if (strpos($alert_message, 'successful') !== false): ?>
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: false,
                willClose: () => {
                    window.location.href = 'sign-in';
                }
            <?php else: ?>
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6'
            <?php endif; ?>
        });
    <?php endif; ?>

    function togglePassword(id) {
        const passwordField = document.getElementById(id);
        const eyeIcon = passwordField.nextElementSibling;
        passwordField.type = passwordField.type === "password" ? "text" : "password";
        eyeIcon.innerHTML = passwordField.type === "password" ? "&#x1F441;" : "&#x1F440;";
    }

    document.getElementById('signupForm').addEventListener('submit', function(event) {
        let isValid = true;
        const errorMessages = [];

        // Email validation
        const email = document.getElementById('email').value.trim();
        const emailPattern = /^[a-zA-Z0-9._%+-]+@(gmail\.com|outlook\.com)$/;
        if (!emailPattern.test(email)) {
            errorMessages.push("• Please enter a valid email with @gmail.com or @outlook.com");
            isValid = false;
        }

        // Password validation
        const password = document.getElementById('password').value;
        const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/;
        if (!passwordPattern.test(password)) {
            errorMessages.push("• Password must be at least 6 characters<br>• Contain at least one uppercase letter<br>• One lowercase letter<br>• One digit");
            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                html: errorMessages.join('<br>'),
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            });
        }
    });

    // Password hint toggle
    document.getElementById('password').addEventListener('focus', () => {
        document.getElementById('password-hint').classList.add('show-password-hint');
    });

    document.getElementById('password').addEventListener('blur', () => {
        document.getElementById('password-hint').classList.remove('show-password-hint');
    });
</script>