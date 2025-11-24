<?php
/**
 * Email Helper Functions
 * Centralized email configuration and sending functions
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get a configured PHPMailer instance with SMTP settings from environment
 * 
 * @return PHPMailer Configured PHPMailer instance
 * @throws Exception if configuration fails
 */
function getConfiguredMailer() {
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
        
        // SSL Options
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false
            ]
        ];
        
        // Set default sender
        $mail->setFrom(
            env('SMTP_FROM_EMAIL', 'noreply@bloodkonnector.com'), 
            env('SMTP_FROM_NAME', 'Blood Connector')
        );
        
        return $mail;
        
    } catch (Exception $e) {
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
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width:600px; margin:20px auto; border:1px solid #eee; border-radius:8px; overflow:hidden; box-shadow:0 0 8px rgba(0,0,0,0.05);'>
            <div style='background-color:#EA062B; color:white; padding:20px; text-align:center;'>
                <h2>Welcome to Blood Connector</h2>
            </div>
            <div style='padding:20px; color:#333;'>
                <p>Hi <strong>$first_name</strong>,</p>
                <p>Thank you for signing up at <strong>Blood Connector</strong>. To complete your registration, please verify your email address by clicking the button below:</p>
                <p style='text-align:center; margin:30px 0;'>
                    <a href='$verification_link' style='background-color:#EA062B; color:#fff; padding:12px 25px; border-radius:6px; text-decoration:none; font-weight:bold; display:inline-block;'>
                        Verify My Email
                    </a>
                </p>
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p style='word-wrap:break-word;'><a href='$verification_link' style='color:#EA062B;'>$verification_link</a></p>
                <hr style='border:none; border-top:1px solid #eee; margin:30px 0;'>
                <p style='font-size:13px; color:#666;'>If you didn't create this account, please ignore this email.</p>
                <p style='font-size:13px; color:#666;'>Regards,<br><strong>The Blood Connector Team</strong></p>
            </div>
        </div>";
        
        // Plain text version
        $mail->AltBody = "Hi $first_name,\n\nThank you for signing up at Blood Connector. 
Please verify your email using the following link:\n$verification_link\n\nIf you didn't create this account, please ignore this message.\n\n- The Blood Connector Team";
        
        // Send email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Verification email failed for $email: " . $e->getMessage());
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
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #EA062B;'>Password Reset Request</h2>
            <p>Dear $first_name,</p>
            <p>We received a request to reset your password. Click the button below to reset it:</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='$reset_link' style='background-color: #EA062B; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;'>
                    Reset Password
                </a>
            </p>
            <p>If the button doesn't work, copy and paste this link into your browser:</p>
            <p><a href='$reset_link'>$reset_link</a></p>
            <p>This link will expire in 1 hour. If you didn't request a password reset, please ignore this email.</p>
            <p style='margin-top: 30px; color: #666; font-size: 12px;'>
                Best regards,<br>
                <strong>The Blood Connector Team</strong>
            </p>
        </div>";
        
        // Plain text version
        $mail->AltBody = "Dear $first_name,\n\nWe received a request to reset your password. 
Click or copy this link to reset it:\n$reset_link\n\nThis link will expire in 1 hour. 
If you didn't request a password reset, please ignore this email.\n\nBest regards,\nThe Blood Connector Team";
        
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

