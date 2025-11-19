<?php
	// Load environment configuration
	require_once __DIR__ . '/../../config.php';

	// Get database credentials from environment variables (no defaults - .env required)
	$servername = env('DB_HOST') ?: die('Error: DB_HOST not configured in .env file');
	$username = env('DB_USERNAME') ?: die('Error: DB_USERNAME not configured in .env file');
	$password = env('DB_PASSWORD') ?? die('Error: DB_PASSWORD not configured in .env file');
	$database = env('DB_DATABASE') ?: die('Error: DB_DATABASE not configured in .env file');
	
	// Create connection
	$conn = new mysqli($servername, $username, $password, $database);

	// Check connection
	if ($conn->connect_error) {
	    die("Connection failed: " . $conn->connect_error);
	}

    // Add these helper functions
    function is_recipient($user_id) {
        global $conn;
        
        // First check the users table
        $query = "SELECT is_recipient FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            if (isset($row['is_recipient']) && $row['is_recipient'] == 1) {
                return true;
            }
        }
        
        // Fallback: Check recipients table for legacy users
        $query2 = "SELECT recipient_id FROM recipients WHERE user_id = ?";
        $stmt2 = $conn->prepare($query2);
        $stmt2->bind_param("s", $user_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        if ($result2 && $result2->num_rows > 0) {
            // Update users table for future checks
            $update_query = "UPDATE users SET is_recipient = 1 WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("s", $user_id);
            $update_stmt->execute();
            return true;
        }
        
        return false;
    }
    
    function is_donor($user_id) {
        global $conn;
        
        // First check the users table
        $query = "SELECT is_donor FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            if (isset($row['is_donor']) && $row['is_donor'] == 1) {
                return true;
            }
        }
        
        // Fallback: Check donors table for legacy users
        $query2 = "SELECT donor_id FROM donors WHERE user_id = ?";
        $stmt2 = $conn->prepare($query2);
        $stmt2->bind_param("s", $user_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        if ($result2 && $result2->num_rows > 0) {
            // Update users table for future checks
            $update_query = "UPDATE users SET is_donor = 1 WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("s", $user_id);
            $update_stmt->execute();
            return true;
        }
        
        return false;
    }