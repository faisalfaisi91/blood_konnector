<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once 'openconn.php';

// Initialize response
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $tutorial_video = isset($_POST['tutorial_video']) ? trim($_POST['tutorial_video']) : '#';
    
    // Validate URL if not empty or '#'
    if ($tutorial_video !== '#' && $tutorial_video !== '') {
        // Basic URL validation
        if (!filter_var($tutorial_video, FILTER_VALIDATE_URL)) {
            $response['message'] = 'Invalid URL format. Please enter a valid YouTube URL.';
            echo json_encode($response);
            exit();
        }
        
        // Check if it's a YouTube URL (optional but recommended)
        if (strpos($tutorial_video, 'youtube.com') === false && strpos($tutorial_video, 'youtu.be') === false) {
            // Allow non-YouTube URLs but warn
            // You can uncomment the following to enforce YouTube only:
            // $response['message'] = 'Please enter a valid YouTube URL.';
            // echo json_encode($response);
            // exit();
        }
    }
    
    // Prepare the setting value (use '#' if empty)
    $setting_value = ($tutorial_video === '' || $tutorial_video === '#') ? '#' : $tutorial_video;
    
    // Check if setting exists
    $check_query = "SELECT setting_id FROM settings WHERE setting_key = 'donor_tutorial_video' LIMIT 1";
    $check_result = $conn->query($check_query);
    
    if ($check_result && $check_result->num_rows > 0) {
        // Update existing setting
        $update_query = "UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = 'donor_tutorial_video'";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("s", $setting_value);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Settings saved successfully!';
        } else {
            $response['message'] = 'Error updating settings: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        // Insert new setting
        $insert_query = "INSERT INTO settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $setting_key = 'donor_tutorial_video';
        $setting_description = 'YouTube video link for donor registration tutorial';
        $stmt->bind_param("sss", $setting_key, $setting_value, $setting_description);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Settings saved successfully!';
        } else {
            $response['message'] = 'Error saving settings: ' . $stmt->error;
        }
        $stmt->close();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

// Close connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>

