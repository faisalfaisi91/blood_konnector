<?php
	// Load environment configuration
	require_once __DIR__ . '/../config.php';

	// Get database credentials from environment variables
	$servername = env('DB_HOST', 'localhost');
	$username = env('DB_USERNAME', 'root');
	$password = env('DB_PASSWORD', '');
	$database = env('DB_DATABASE', 'bloodkon_bk');
	
	// Create connection
	$conn = new mysqli($servername, $username, $password, $database);

	// Check connection
	if ($conn->connect_error) {
	    die("Connection failed: " . $conn->connect_error);
	}

    // Add these helper functions
    function is_recipient($user_id) {
        global $conn;
        $query = "SELECT is_recipient FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['is_recipient'] == 1;
    }
    
    function is_donor($user_id) {
        return !is_recipient($user_id);
    }
?>
