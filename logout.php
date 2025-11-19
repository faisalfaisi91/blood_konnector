<?php
session_start();
include("assets/lib/openconn.php");

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    // Set last activity to 10 minutes ago
    $pastTime = date('Y-m-d H:i:s', time() - 600);
    $updateQuery = "UPDATE users SET last_activity = '$pastTime' WHERE user_id = '$userId'";
    mysqli_query($conn, $updateQuery);
}

session_unset();
session_destroy();
header("Location: sign-in");
exit();

?>
