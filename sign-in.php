<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
include("assets/lib/openconn.php");

$alert_script = ""; // For storing SweetAlert script

if (isset($_POST['btnSignIn'])) {
    // Trim and sanitize input (remove spaces)
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Use prepared statement for security and case-insensitive search
    $query = "SELECT * FROM users WHERE LOWER(email) = LOWER(?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Support both MD5 (legacy) and bcrypt password hashing
        $password_match = false;
        if (strlen($user['password']) == 32) {
            // MD5 hash (32 characters)
            $password_match = (md5($password) === $user['password']);
        } else {
            // Bcrypt hash (starts with $2y$)
            $password_match = password_verify($password, $user['password']);
        }
        
        if ($password_match) {
            if ($user['email_verified'] == 1) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_first_name'] = $user['first_name'];
                $_SESSION['user_last_name'] = $user['last_name'];
                $_SESSION['user_email'] = $user['email'];

                $currentTime = date('Y-m-d H:i:s');
                $updateQuery = "UPDATE users SET last_activity = '$currentTime' WHERE user_id = '{$user['user_id']}'";
                mysqli_query($conn, $updateQuery);

                $alert_script = "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Login successful! Redirecting to the homepage...',
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'profile';
                    });
                </script>";
            } else {
                $alert_script = "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Email Not Verified',
                        text: 'Your email is not verified. Please check your inbox.',
                        confirmButtonText: 'OK'
                    });
                </script>";
            }
        } else {
            $alert_script = "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Incorrect Password',
                    text: 'Incorrect password. Please try again.',
                    confirmButtonText: 'OK'
                });
            </script>";
        }
    } else {
        $alert_script = "<script>
            Swal.fire({
                icon: 'error',
                title: 'No Account Found',
                text: 'No account found with this email. Please sign up first.',
                timer: 5000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'sign-up';
            });
        </script>";
    }
    
    // Close statement if it exists
    if (isset($stmt)) {
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .auth-container {
            max-width: 500px;
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
            font-size: 2rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .auth-header p {
            color: #718096;
            font-size: 0.9rem;
        }

        .form-control {
            height: 48px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .password-wrapper {
            position: relative;
            margin-top: 1rem;
        }

        .eye-icon {
            position: absolute;
            right: 16px;
            top: 55%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #a0aec0;
            padding: 4px;
            transition: color 0.3s ease;
        }

        .eye-icon:hover {
            color: #667eea;
        }

        .auth-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0;
            font-size: 0.9rem;
        }

        .auth-actions a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .auth-actions a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            border-radius: 8px;
            background: #ea062b;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #ea062b;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #718096;
        }

        .auth-footer a {
            color: #667eea;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        @media (max-width: 576px) {
            .auth-container {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .auth-header h2 {
                font-size: 1.75rem;
            }
        }
    </style>
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
                <h2>Sign In</h2>
                <ul>
                    <li><a href="index">Home</a></li>
                    <li class="active">Sign In</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Sign In Form Section -->
<section class="km__message__box ptb-120">
    <div class="container">
        <div class="km__contact__form">
            <div class="row g-5 justify-content-center">
                <div class="col-xl-8">
                    <div class="km__box__form">
                        <h4 class="mb-4">Welcome Back!</h4>
                        <p class="mb-3">Please enter your email and password to sign in to your account.</p>

                        <form method="post" class="km__main__form">
                            <div class="row mt-3">
                                <div class="col-sm">
                                    <input type="email" name="email" placeholder="Email" required class="form-control">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-sm">
                                    <div class="password-wrapper">
                                        <input id="password" type="password" name="password" placeholder="Password" required class="form-control">
                                        <span class="eye-icon" onclick="togglePassword('password')">&#x1F441;</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-sm">
                                    <a href="forgot_password" class="text-dark">Forgot Password?</a>
                                </div>
                            </div>
                            <button type="submit" name="btnSignIn" class="btn btn-lg btn-primary btn-block contact__btn mt-4">
                                Sign In <i class="fa-solid fa-angles-right"></i>
                            </button>
                            <div class="mt-3 text-center">
                                <p>Don't have an account? <a href="sign-up" class="text-dark">Create an Account</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include('assets/includes/footer.php'); ?>

<?php include('assets/includes/link-js.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function togglePassword(id) {
        const passwordField = document.getElementById(id);
        const eyeIcon = passwordField.nextElementSibling;
        if (passwordField.type === "password") {
            passwordField.type = "text";
            eyeIcon.innerHTML = "&#x1F440;";
        } else {
            passwordField.type = "password";
            eyeIcon.innerHTML = "&#x1F441;";
        }
    }
</script>

<!-- SweetAlert Logic -->
<?php if (!empty($alert_script)) echo $alert_script; ?>

</body>
</html>
