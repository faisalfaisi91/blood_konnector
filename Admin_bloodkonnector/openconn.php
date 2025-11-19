<?php
	$servername = "localhost";
    $username = "root";
	$password = ""; 
	// $username = "bloodkon_bloodkon_bk";
	// $password = "{Ok#76DVx,q+2.a$"; 
	$database = "bloodkon_bk";
	
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
