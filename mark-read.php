<?php
session_start();
include('openconn.php');

if (isset($_GET['user']) {
    $user = mysqli_real_escape_string($conn, $_GET['user']);
    $query = "UPDATE messages SET is_read = 1 
              WHERE recipient_id = ? AND sender_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $_SESSION['user_id'], $user);
    $stmt->execute();
}