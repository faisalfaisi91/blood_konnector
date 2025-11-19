<?php
session_start();
include('assets/lib/openconn.php'); // Fixed path

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['error' => 'User ID required']);
    exit();
}

$user_id = $_GET['user_id'];
$online = false;

$query = "SELECT last_activity FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $last_activity = $result->fetch_assoc()['last_activity'];
    $online = (time() - strtotime($last_activity)) < 300; // 5 minute threshold
}

echo json_encode(['online' => $online]);
?>