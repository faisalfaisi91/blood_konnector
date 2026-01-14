<?php
/**
 * Email Helper Functions
 * Centralized email configuration and sending functions
 */

// Ensure PHPMailer is loaded
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // Try vendor autoload first
    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        // Fallback to phpmailer directory
        require_once __DIR__ . '/../../phpmailer/src/Exception.php';
        require_once __DIR__ . '/../../phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../../phpmailer/src/SMTP.php';
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get a configured PHPMailer instance with SMTP settings from environment
 * 
 * @return PHPMailer Configured PHPMailer instance
 * @throws Exception if configuration fails
 */
function getConfiguredMailer() {
    // Ensure config is loaded for env() function
    if (!function_exists('env')) {
        require_once __DIR__ . '/../../config.php';
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration from environment variables
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST', 's26.hosterpk.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USERNAME', 'info@bloodkonnector.com');
        $mail->Password   = env('SMTP_PASSWORD', 'Nokia#001Nokia#001');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = env('SMTP_PORT', 465);
        $mail->CharSet    = 'UTF-8';
        
        // SSL Options - relaxed for better compatibility
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Enable debug output (can be disabled in production)
        // $mail->SMTPDebug = 2; // Uncomment for debugging
        // $mail->Debugoutput = function($str, $level) {
        //     error_log("SMTP Debug: $str");
        // };
        
        // Set default sender
        $mail->setFrom(
            env('SMTP_FROM_EMAIL', 'noreply@bloodkonnector.com'), 
            env('SMTP_FROM_NAME', 'Blood Connector')
        );
        
        return $mail;
        
    } catch (Exception $e) {
        error_log("Failed to configure PHPMailer: " . $e->getMessage());
        throw $e;
    } catch (\Exception $e) {
        error_log("Failed to configure PHPMailer: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Send verification email to new user
 * 
 * @param string $email User's email address
 * @param string $first_name User's first name
 * @param string $verification_code Verification code
 * @return bool True on success, false on failure
 */
function sendVerificationEmail($email, $first_name, $verification_code) {
    try {
        // Ensure config is loaded for env() function
        if (!function_exists('env')) {
            require_once __DIR__ . '/../../config.php';
        }
        
        $mail = getConfiguredMailer();
        
        // Recipients
        $mail->addAddress($email, $first_name);
        
        // Email Subject
        $mail->Subject = 'Verify Your Email - Blood Connector Registration';
        
        // Verification link
        $base_url = rtrim(env('BASE_URL', 'http://localhost/blood_konnector'), '/');
        $verification_link = $base_url . '/verify-email?code=' . $verification_code;
        
        // HTML Email Body
        $mail->isHTML(true);
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width:600px; margin:20px auto; border:1px solid #eee; border-radius:8px; overflow:hidden; box-shadow:0 0 8px rgba(0,0,0,0.05);">
            <div style="background-color:#EA062B; color:white; padding:20px; text-align:center;">
                <h2 style="margin:0; color:white;">Welcome to Blood Connector</h2>
            </div>
            <div style="padding:20px; color:#333;">
                <p style="margin:10px 0;">Hi <strong>' . htmlspecialchars($first_name) . '</strong>,</p>
                <p style="margin:10px 0;">Thank you for signing up at <strong>Blood Connector</strong>. To complete your registration, please verify your email address by clicking the button below:</p>
                <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                    <tr>
                        <td align="center">
                            <a href="' . $verification_link . '" target="_blank" style="background-color:#EA062B; color:#ffffff; padding:14px 28px; border-radius:6px; text-decoration:none; font-weight:bold; display:inline-block; font-size:16px;">
                                Verify My Email
                            </a>
                        </td>
                    </tr>
                </table>
                <p style="margin:10px 0;">If the button doesn\'t work, copy and paste this link into your browser:</p>
                <p style="word-wrap:break-word; margin:10px 0;"><a href="' . $verification_link . '" target="_blank" style="color:#EA062B;">' . $verification_link . '</a></p>
                <hr style="border:none; border-top:1px solid #eee; margin:30px 0;">
                <p style="font-size:13px; color:#666; margin:10px 0;">If you didn\'t create this account, please ignore this email.</p>
                <p style="font-size:13px; color:#666; margin:10px 0;">Regards,<br><strong>The Blood Connector Team</strong></p>
            </div>
        </div>';
        
        // Plain text version
        $mail->AltBody = "Hi " . $first_name . ",\n\nThank you for signing up at Blood Connector.\n\nPlease verify your email using the following link:\n" . $verification_link . "\n\nIf you didn't create this account, please ignore this message.\n\nRegards,\nThe Blood Connector Team";
        
        // Send email
        $mail->send();
        error_log("Verification email sent successfully to: $email");
        return true;
        
    } catch (Exception $e) {
        $errorMsg = "Verification email failed for $email: " . $e->getMessage();
        if (isset($mail)) {
            $errorMsg .= " | PHPMailer Error: " . $mail->ErrorInfo;
        }
        error_log($errorMsg);
        return false;
    } catch (\Exception $e) {
        $errorMsg = "Verification email failed for $email: " . $e->getMessage();
        if (isset($mail)) {
            $errorMsg .= " | PHPMailer Error: " . $mail->ErrorInfo;
        }
        error_log($errorMsg);
        return false;
    }
}

/**
 * Send password reset email
 * 
 * @param string $email User's email address
 * @param string $first_name User's first name
 * @param string $reset_token Password reset token
 * @return bool True on success, false on failure
 */
function sendPasswordResetEmail($email, $first_name, $reset_token) {
    try {
        $mail = getConfiguredMailer();
        
        // Recipients
        $mail->addAddress($email, $first_name);
        
        // Email Subject
        $mail->Subject = 'Password Reset Request for Blood Connector';
        
        // Reset link
        $base_url = rtrim(env('BASE_URL', 'http://localhost/blood_konnector'), '/');
        $reset_link = $base_url . '/reset_password.php?token=' . $reset_token;
        
        // HTML Email Body
        $mail->isHTML(true);
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #EA062B; margin-bottom: 20px;">Password Reset Request</h2>
            <p style="margin:10px 0;">Dear <strong>' . htmlspecialchars($first_name) . '</strong>,</p>
            <p style="margin:10px 0;">We received a request to reset your password. Click the button below to reset it:</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                <tr>
                    <td align="center">
                        <a href="' . $reset_link . '" target="_blank" style="background-color: #EA062B; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 4px; font-weight: bold; display:inline-block; font-size:16px;">
                            Reset Password
                        </a>
                    </td>
                </tr>
            </table>
            <p style="margin:10px 0;">If the button doesn\'t work, copy and paste this link into your browser:</p>
            <p style="margin:10px 0;"><a href="' . $reset_link . '" target="_blank" style="color:#EA062B; word-wrap:break-word;">' . $reset_link . '</a></p>
            <p style="margin:10px 0;">This link will expire in 1 hour. If you didn\'t request a password reset, please ignore this email.</p>
            <p style="margin-top: 30px; color: #666; font-size: 12px;">
                Best regards,<br>
                <strong>The Blood Connector Team</strong>
            </p>
        </div>';
        
        // Plain text version
        $mail->AltBody = "Dear " . $first_name . ",\n\nWe received a request to reset your password.\n\nClick or copy this link to reset it:\n" . $reset_link . "\n\nThis link will expire in 1 hour. If you didn't request a password reset, please ignore this email.\n\nBest regards,\nThe Blood Connector Team";
        
        // Send email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Password reset email failed for $email: " . $e->getMessage());
        return false;
    }
}

/**
 * Get base URL from environment
 * 
 * @return string Base URL with trailing slash removed
 */
function getBaseUrl() {
    return rtrim(env('BASE_URL', 'http://localhost/blood_konnector'), '/');
}

