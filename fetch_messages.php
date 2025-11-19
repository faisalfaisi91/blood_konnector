<?php
session_start();
include("assets/lib/openconn.php");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sender_id = $_SESSION['user_id']; // Recipient's ID
    $receiver_id = $_GET['receiver_id']; // Donor's ID

    // Fetch messages between sender and receiver
    $stmt = $conn->prepare("SELECT * FROM chat_messages 
                            WHERE (sender_id = ? AND receiver_id = ?) 
                            OR (sender_id = ? AND receiver_id = ?) 
                            ORDER BY timestamp ASC");
    $stmt->bind_param("ssss", $sender_id, $receiver_id, $receiver_id, $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'sender_id' => $row['sender_id'],
            'message' => htmlspecialchars_decode($row['message']),
            'timestamp' => $row['timestamp']
        ];
    }

    echo json_encode(['status' => 'success', 'messages' => $messages]);
    $stmt->close();
    $conn->close();
}
?>