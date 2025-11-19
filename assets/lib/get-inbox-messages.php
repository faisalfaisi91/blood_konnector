<?php
/**
 * API Endpoint: Fetch NEW messages for a specific conversation
 * Returns JSON with only messages AFTER the last known message ID
 */

session_start();
include('openconn.php');

// Set JSON header
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$recipient_id = $_GET['recipient_id'] ?? '';
$last_message_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Validate recipient_id
if (empty($recipient_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid recipient ID']);
    exit();
}

// If last_id is 0, it means this is initial load - return empty
// The page already has messages loaded from PHP
if ($last_message_id === 0) {
    echo json_encode([
        'success' => true,
        'messages' => [],
        'count' => 0
    ]);
    $conn->close();
    exit();
}

// Fetch only NEW messages (after last_message_id)
$msg_query = "SELECT m.message_id, m.sender_id, m.recipient_id, m.message, m.timestamp, u.profile_pic, u.first_name, u.last_name
              FROM messages m
              JOIN users u ON m.sender_id = u.user_id
              WHERE ((m.sender_id = ? AND m.recipient_id = ?)
                 OR (m.sender_id = ? AND m.recipient_id = ?))
                 AND m.message_id > ?
              ORDER BY m.timestamp ASC";
              
$msg_stmt = $conn->prepare($msg_query);
$msg_stmt->bind_param("ssssi", $current_user_id, $recipient_id, $recipient_id, $current_user_id, $last_message_id);
$msg_stmt->execute();
$result = $msg_stmt->get_result();

$new_messages = [];
while($msg = $result->fetch_assoc()) {
    $new_messages[] = [
        'message_id' => $msg['message_id'],
        'sender_id' => $msg['sender_id'],
        'recipient_id' => $msg['recipient_id'],
        'message' => $msg['message'],
        'timestamp' => $msg['timestamp'],
        'profile_pic' => !empty(trim($msg['profile_pic'])) ? $msg['profile_pic'] : 'assets/images/default-avatar.png',
        'sender_name' => $msg['first_name'] . ' ' . $msg['last_name'],
        'is_own' => $msg['sender_id'] == $current_user_id
    ];
}

// Return JSON response
echo json_encode([
    'success' => true,
    'messages' => $new_messages,
    'count' => count($new_messages)
]);

$msg_stmt->close();
$conn->close();
?>

