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
 * Get a configured PHPMailer instance using PHP's default mail() function
 * 
 * @return PHPMailer Configured PHPMailer instance
 * @throws Exception if configuration fails
 */
function getConfiguredMailer() {
    // Ensure config is loaded for env() function
    if (!function_exists('env')) {
        require_once __DIR__ . '/../../config.php';
    }
    
    // Check if we should use SMTP or mail() function
    // On Windows/XAMPP, mail() often doesn't work, so we'll use SMTP if configured
    $useSMTP = env('USE_SMTP', 'true'); // Default to SMTP for better reliability
    
    $mail = new PHPMailer(true);
    
    try {
        if ($useSMTP === 'true' || $useSMTP === true) {
            // Use SMTP (more reliable, especially on Windows/XAMPP)
            $mail->isSMTP();
            $mail->Host       = env('SMTP_HOST', 'mail.bloodkonnector.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('SMTP_USERNAME', 'info@bloodkonnector.com');
            $mail->Password   = env('SMTP_PASSWORD', 'Nokia#001');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = env('SMTP_PORT', 465);
            
            // Set HELO/EHLO name for better email deliverability
            // This identifies your server when connecting to SMTP
            // Use staging domain for staging server, or main domain for production
            $heloName = env('SMTP_HELO', 'staging.bloodkonnector.com');
            $mail->Helo = $heloName;
            $mail->Hostname = $heloName; // Also set Hostname for Message-ID header
            
            // Enable SMTP debugging (set to 0 for production, 2 for detailed debugging)
            // Temporarily enable debugging to diagnose email delivery issues
            $smtpDebugLevel = env('SMTP_DEBUG', 2); // 0 = off, 1 = client messages, 2 = client and server messages
            $mail->SMTPDebug = $smtpDebugLevel;
            $mail->Debugoutput = function($str, $level) {
                $debugLogFile = __DIR__ . '/../../smtp_debug.log';
                $logMessage = date('Y-m-d H:i:s') . " [Level $level] $str\n";
                // Try to write to file
                @file_put_contents($debugLogFile, $logMessage, FILE_APPEND);
                // Also log to PHP error log for immediate visibility
                error_log("SMTP Debug [Level $level]: $str");
            };
            
            // SSL Options - relaxed for better compatibility
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ];
        } else {
            // Use PHP's default mail() function
            $mail->isMail();
        }
        
        $mail->CharSet = 'UTF-8';
        
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
        
        // Send email - CHECK THE RETURN VALUE!
        $sendResult = $mail->send();
        
        // Even if send() returns true, check ErrorInfo to see if there were warnings
        $hasErrors = !empty($mail->ErrorInfo);
        
        if (!$sendResult || $hasErrors) {
            // send() returned false OR there are errors/warnings
            $errorMsg = "Verification email failed for $email";
            if (!$sendResult) {
                $errorMsg .= " | send() returned false";
            }
            if ($hasErrors) {
                $errorMsg .= " | PHPMailer Error: " . $mail->ErrorInfo;
            }
            $lastError = error_get_last();
            if ($lastError) {
                $errorMsg .= " | PHP Last Error: " . $lastError['message'];
            }
            error_log($errorMsg);
            // Log to a specific email error file for easier debugging
            $emailLogFile = __DIR__ . '/../../email_errors.log';
            file_put_contents($emailLogFile, date('Y-m-d H:i:s') . " - " . $errorMsg . "\n", FILE_APPEND);
            return false;
        }
        
        // Success - email was sent (but verify SMTP actually accepted it)
        // Note: send() returning true doesn't guarantee delivery, just that SMTP accepted it
        error_log("Verification email accepted by SMTP server for: $email");
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
        // Ensure config is loaded for env() function
        if (!function_exists('env')) {
            require_once __DIR__ . '/../../config.php';
        }
        
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
        
        // Send email - CHECK THE RETURN VALUE!
        $sendResult = $mail->send();
        
        // Even if send() returns true, check ErrorInfo to see if there were warnings
        $hasErrors = !empty($mail->ErrorInfo);
        
        // Always log the result for debugging
        $logFile = __DIR__ . '/../../email_errors.log';
        $logEntry = date('Y-m-d H:i:s') . " - Password reset email attempt for: $email | send() result: " . ($sendResult ? 'true' : 'false') . " | ErrorInfo: " . ($mail->ErrorInfo ?: 'none') . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        error_log("Email send attempt: $email - Result: " . ($sendResult ? 'SUCCESS' : 'FAILED') . " - ErrorInfo: " . ($mail->ErrorInfo ?: 'none'));
        
        if (!$sendResult || $hasErrors) {
            // send() returned false OR there are errors/warnings
            $errorMsg = "Password reset email failed for $email";
            if (!$sendResult) {
                $errorMsg .= " | send() returned false";
            }
            if ($hasErrors) {
                $errorMsg .= " | PHPMailer Error: " . $mail->ErrorInfo;
            }
            $lastError = error_get_last();
            if ($lastError) {
                $errorMsg .= " | PHP Last Error: " . $lastError['message'];
            }
            error_log($errorMsg);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: " . $errorMsg . "\n", FILE_APPEND);
            return false;
        }
        
        // Success - email was sent (but verify SMTP actually accepted it)
        // Note: send() returning true doesn't guarantee delivery, just that SMTP accepted it
        // IMPORTANT: If email is not received, check:
        // 1. SMTP server logs (if accessible)
        // 2. Recipient's spam folder
        // 3. Email provider blocking
        // 4. SMTP server may have accepted but filtered the email
        error_log("Password reset email accepted by SMTP server for: $email (Note: This doesn't guarantee delivery - check spam folder)");
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - SUCCESS: Email accepted by SMTP for: $email\n", FILE_APPEND);
        return true;
        
    } catch (Exception $e) {
        $errorMsg = "Password reset email failed for $email: " . $e->getMessage();
        if (isset($mail)) {
            $errorMsg .= " | PHPMailer Error: " . $mail->ErrorInfo;
        }
        error_log($errorMsg);
        return false;
    } catch (\Exception $e) {
        $errorMsg = "Password reset email failed for $email: " . $e->getMessage();
        if (isset($mail)) {
            $errorMsg .= " | PHPMailer Error: " . $mail->ErrorInfo;
        }
        error_log($errorMsg);
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

/**
 * Send email to donor when a new blood donation request is received (emergency or lifeline).
 *
 * @param string $donorEmail
 * @param string $donorName
 * @param string $bloodType
 * @param string $city
 * @param string $requestType 'emergency' or 'lifeline'
 * @param string|null $viewUrl Optional view URL (defaults to emergency-donor or lifeline-donor-requests)
 * @return bool
 */
function sendNewBloodRequestEmailToDonor($donorEmail, $donorName, $bloodType, $city, $requestType = 'emergency', $viewUrl = null) {
    if (empty($donorEmail) || !filter_var($donorEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    try {
        if (!function_exists('env')) {
            require_once __DIR__ . '/../../config.php';
        }
        $mail = getConfiguredMailer();
        $mail->addAddress($donorEmail, $donorName);
        $baseUrl = getBaseUrl();
        if ($viewUrl === null) {
            $viewUrl = $requestType === 'lifeline' ? $baseUrl . '/lifeline-donor-requests' : $baseUrl . '/emergency-donor';
        }
        $title = $requestType === 'lifeline' ? 'New LifeLine Blood Donation Request' : 'New Blood Donation Request';
        $mail->Subject = $title . ' - ' . $bloodType;
        $mail->isHTML(true);
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width:600px; margin:20px auto; border:1px solid #eee; border-radius:8px; overflow:hidden; box-shadow:0 0 8px rgba(0,0,0,0.05);">
            <div style="background-color:#EA062B; color:white; padding:20px; text-align:center;">
                <h2 style="margin:0; color:white;">' . htmlspecialchars($title) . '</h2>
            </div>
            <div style="padding:20px; color:#333;">
                <p style="margin:10px 0;">Hi <strong>' . htmlspecialchars($donorName ?: 'Donor') . '</strong>,</p>
                <p style="margin:10px 0;">A new blood donation request has been created that matches your profile.</p>
                <div style="background:#f8f9fa; padding:15px; border-radius:6px; margin:20px 0;">
                    <p style="margin:5px 0;"><strong>Blood Type:</strong> ' . htmlspecialchars($bloodType) . '</p>
                    <p style="margin:5px 0;"><strong>Location:</strong> ' . htmlspecialchars($city) . '</p>
                </div>
                <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                    <tr>
                        <td align="center">
                            <a href="' . htmlspecialchars($viewUrl) . '" target="_blank" style="background-color:#EA062B; color:#ffffff; padding:14px 28px; border-radius:6px; text-decoration:none; font-weight:bold; display:inline-block; font-size:16px;">View Request</a>
                        </td>
                    </tr>
                </table>
                <p style="margin-top:30px; font-size:13px; color:#666;">Regards,<br><strong>The Blood Konnector Team</strong></p>
            </div>
        </div>';
        $mail->AltBody = "Hi " . ($donorName ?: 'Donor') . ",\n\nA new blood donation request has been created.\n\nBlood Type: " . $bloodType . "\nLocation: " . $city . "\n\nView the request: " . $viewUrl . "\n\nRegards,\nThe Blood Konnector Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("New request email to donor failed for $donorEmail: " . $e->getMessage());
        return false;
    } catch (\Exception $e) {
        error_log("New request email to donor failed for $donorEmail: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email to recipient when a donor has accepted their blood request.
 *
 * @param string $recipientEmail
 * @param string $recipientName
 * @param string $donorName
 * @param string $requestType 'emergency' or 'lifeline'
 * @param string|null $viewUrl Optional view URL
 * @return bool
 */
function sendDonorAcceptedEmailToRecipient($recipientEmail, $recipientName, $donorName, $requestType = 'emergency', $viewUrl = null) {
    if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    try {
        if (!function_exists('env')) {
            require_once __DIR__ . '/../../config.php';
        }
        $mail = getConfiguredMailer();
        $mail->addAddress($recipientEmail, $recipientName);
        $baseUrl = getBaseUrl();
        if ($viewUrl === null) {
            $viewUrl = $requestType === 'lifeline' ? $baseUrl . '/lifeline-panel' : $baseUrl . '/emergency-recipient';
        }
        $mail->Subject = 'Good news: A donor has accepted your blood request';
        $mail->isHTML(true);
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width:600px; margin:20px auto; border:1px solid #eee; border-radius:8px; overflow:hidden; box-shadow:0 0 8px rgba(0,0,0,0.05);">
            <div style="background-color:#27ae60; color:white; padding:20px; text-align:center;">
                <h2 style="margin:0; color:white;">Request Accepted</h2>
            </div>
            <div style="padding:20px; color:#333;">
                <p style="margin:10px 0;">Hi <strong>' . htmlspecialchars($recipientName ?: 'there') . '</strong>,</p>
                <p style="margin:10px 0;">A donor has accepted your blood donation request.</p>
                <div style="background:#f8f9fa; padding:15px; border-radius:6px; margin:20px 0;">
                    <p style="margin:5px 0;"><strong>Donor:</strong> ' . htmlspecialchars($donorName ?: 'Donor') . '</p>
                    <p style="margin:5px 0;">You can coordinate the donation through the platform.</p>
                </div>
                <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                    <tr>
                        <td align="center">
                            <a href="' . htmlspecialchars($viewUrl) . '" target="_blank" style="background-color:#EA062B; color:#ffffff; padding:14px 28px; border-radius:6px; text-decoration:none; font-weight:bold; display:inline-block; font-size:16px;">View Request</a>
                        </td>
                    </tr>
                </table>
                <p style="margin-top:30px; font-size:13px; color:#666;">Regards,<br><strong>The Blood Konnector Team</strong></p>
            </div>
        </div>';
        $mail->AltBody = "Hi " . ($recipientName ?: 'there') . ",\n\nA donor has accepted your blood donation request.\n\nDonor: " . ($donorName ?: 'Donor') . "\n\nView: " . $viewUrl . "\n\nRegards,\nThe Blood Konnector Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Donor accepted email to recipient failed for $recipientEmail: " . $e->getMessage());
        return false;
    } catch (\Exception $e) {
        error_log("Donor accepted email to recipient failed for $recipientEmail: " . $e->getMessage());
        return false;
    }
}

