<?php
/**
 * Donor Dashboard API
 * Handles donor availability toggle, emergency availability toggle
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/openconn.php';
require_once __DIR__ . '/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$userId = $_SESSION['user_id'] ?? null;

if (!$userId || !$profileManager->hasRole('donor')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action !== 'toggle_availability' && $action !== 'toggle_emergency') {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Check if is_available column exists
$checkCol = $conn->query("SHOW COLUMNS FROM donors LIKE 'is_available'");
$hasIsAvailable = $checkCol && $checkCol->num_rows > 0;

if ($action === 'toggle_availability') {
    $value = isset($_POST['value']) ? (int)$_POST['value'] : null;
    if ($value === null) {
        $value = isset($_POST['checked']) ? (($_POST['checked'] === 'true' || $_POST['checked'] === true) ? 1 : 0) : null;
    }
    if ($value === null) {
        echo json_encode(['success' => false, 'error' => 'Missing value']);
        exit;
    }
    $value = $value ? 1 : 0;
    if ($hasIsAvailable) {
        $stmt = $conn->prepare("UPDATE donors SET is_available = ? WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("is", $value, $userId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'is_available' => $value]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Update failed']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        // Fallback: use availability_status if is_available doesn't exist
        $status = $value ? 'available' : 'inactive';
        $stmt = $conn->prepare("UPDATE donors SET availability_status = ? WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $status, $userId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'is_available' => $value]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Update failed']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Column not available']);
        }
    }
    exit;
}

if ($action === 'toggle_emergency') {
    $value = isset($_POST['value']) ? ($_POST['value'] ? 'yes' : 'no') : null;
    if ($value === null) {
        $checked = isset($_POST['checked']) ? ($_POST['checked'] === 'true' || $_POST['checked'] === true) : null;
        $value = $checked ? 'yes' : 'no';
    }
    $stmt = $conn->prepare("UPDATE donors SET emergency_availability = ? WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $value, $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'emergency_availability' => $value]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}
