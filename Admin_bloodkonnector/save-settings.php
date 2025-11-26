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
    // Function to save tutorial video setting
    function saveTutorialVideo($conn, $setting_key, $tutorial_video, $description) {
        // Validate URL if not empty or '#'
        if ($tutorial_video !== '#' && $tutorial_video !== '') {
            // Basic URL validation
            if (!filter_var($tutorial_video, FILTER_VALIDATE_URL)) {
                return ['success' => false, 'message' => 'Invalid URL format. Please enter a valid YouTube URL.'];
            }
        }
        
        // Prepare the setting value (use '#' if empty)
        $setting_value = ($tutorial_video === '' || $tutorial_video === '#') ? '#' : $tutorial_video;
        
        // Check if setting exists
        $check_query = "SELECT setting_id FROM settings WHERE setting_key = ? LIMIT 1";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $setting_key);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_stmt->close();
        
        if ($check_result && $check_result->num_rows > 0) {
            // Update existing setting
            $update_query = "UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ss", $setting_value, $setting_key);
            
            if ($stmt->execute()) {
                $stmt->close();
                return ['success' => true, 'message' => 'Settings saved successfully!'];
            } else {
                $error = $stmt->error;
                $stmt->close();
                return ['success' => false, 'message' => 'Error updating settings: ' . $error];
            }
        } else {
            // Insert new setting
            $insert_query = "INSERT INTO settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("sss", $setting_key, $setting_value, $description);
            
            if ($stmt->execute()) {
                $stmt->close();
                return ['success' => true, 'message' => 'Settings saved successfully!'];
            } else {
                $error = $stmt->error;
                $stmt->close();
                return ['success' => false, 'message' => 'Error saving settings: ' . $error];
            }
        }
    }
    
    // Handle donor tutorial video
    if (isset($_POST['donor_tutorial_video'])) {
        $donor_tutorial_video = trim($_POST['donor_tutorial_video']);
        $result = saveTutorialVideo($conn, 'donor_tutorial_video', $donor_tutorial_video, 'YouTube video link for donor registration tutorial');
        if (!$result['success']) {
            $response = $result;
            echo json_encode($response);
            exit();
        }
    }
    
    // Handle recipient tutorial video
    if (isset($_POST['recipient_tutorial_video'])) {
        $recipient_tutorial_video = trim($_POST['recipient_tutorial_video']);
        $result = saveTutorialVideo($conn, 'recipient_tutorial_video', $recipient_tutorial_video, 'YouTube video link for recipient registration tutorial');
        if (!$result['success']) {
            $response = $result;
            echo json_encode($response);
            exit();
        }
    }
    
    // If we get here, all settings were saved successfully
    $response['success'] = true;
    $response['message'] = 'Settings saved successfully!';
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

