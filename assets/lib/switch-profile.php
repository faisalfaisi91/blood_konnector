<?php
/**
 * Profile Switching Handler
 * Handles AJAX requests for switching between donor and recipient profiles
 */

session_start();
require_once '../../config.php';
require_once 'openconn.php';
require_once 'ProfileManager.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to switch profiles.'
    ]);
    exit;
}

// Check if profile parameter is provided
if (!isset($_POST['profile']) || empty($_POST['profile'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid profile type.'
    ]);
    exit;
}

$requestedProfile = trim($_POST['profile']);
$userId = $_SESSION['user_id'];

// Validate profile type
if ($requestedProfile !== 'donor' && $requestedProfile !== 'recipient') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid profile type. Must be "donor" or "recipient".'
    ]);
    exit;
}

try {
    $profileManager = new ProfileManager($conn);
    
    // Get user's roles
    $roles = $profileManager->getUserRoles($userId);
    
    // Check if user has the requested role
    if ($requestedProfile === 'donor' && !$roles['is_donor']) {
        echo json_encode([
            'success' => false,
            'message' => 'You do not have a donor profile. Please register as a donor first.'
        ]);
        exit;
    }
    
    if ($requestedProfile === 'recipient' && !$roles['is_recipient']) {
        echo json_encode([
            'success' => false,
            'message' => 'You do not have a recipient profile. Please register as a recipient first.'
        ]);
        exit;
    }
    
    // Switch profile in session
    $_SESSION['active_profile'] = $requestedProfile;
    
    // Update last activity
    $profileManager->updateLastActivity();
    
    // Log the profile switch (optional)
    error_log("User ID {$userId} switched to {$requestedProfile} profile at " . date('Y-m-d H:i:s'));
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Profile switched successfully.',
        'profile' => $requestedProfile,
        'profileLabel' => ucfirst($requestedProfile)
    ]);
    
} catch (Exception $e) {
    error_log("Profile switch error for user {$userId}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while switching profiles. Please try again.'
    ]);
}

