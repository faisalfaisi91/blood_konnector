<?php
    session_start();
    include('openconn.php');
    
    if (!isset($_SESSION['user_id']) || !isset($_GET['user'])) {
        exit('Unauthorized');
    }
    
    $current_user_id = $_SESSION['user_id'];
    $other_user_id = mysqli_real_escape_string($conn, $_GET['user']);
    $system_user_id = 'blood_konnection_system'; // System user ID for Blood Konnection
    
    // Get current user profile picture
    $current_user_pic_query = "SELECT profile_pic FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($current_user_pic_query);
    $stmt->bind_param("s", $current_user_id);
    $stmt->execute();
    $current_user_pic_result = $stmt->get_result();
    $current_user_pic_data = $current_user_pic_result->fetch_assoc();
    $current_user_pic = !empty(trim($current_user_pic_data['profile_pic'])) 
        ? $current_user_pic_data['profile_pic'] 
        : 'assets/images/default-avatar.png';
    
    // Get other user data
    $other_user_query = "SELECT first_name, last_name, profile_pic, last_activity FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($other_user_query);
    $stmt->bind_param("s", $other_user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    $online_status = false;
    $recipient_name = "User";
    $other_user_pic = 'assets/images/default-avatar.png';
    
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $recipient_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
        $online_status = (time() - strtotime($user_data['last_activity'])) < 300;
        $other_user_pic = !empty(trim($user_data['profile_pic'])) 
            ? $user_data['profile_pic'] 
            : 'assets/images/default-avatar.png';
    }
    
    // Get system user profile picture
    $system_user_query = "SELECT profile_pic FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($system_user_query);
    $stmt->bind_param("s", $system_user_id);
    $stmt->execute();
    $system_user_result = $stmt->get_result();
    $system_user_pic = 'assets/images/blood_konnection_logo.png'; // Default system avatar
    if ($system_user_result->num_rows > 0) {
        $system_user_data = $system_user_result->fetch_assoc();
        $system_user_pic = !empty(trim($system_user_data['profile_pic'])) 
            ? $system_user_data['profile_pic'] 
            : 'assets/images/blood_konnection_logo.png';
    }
    
    // Fetch messages
    $chat_query = "SELECT * FROM messages 
                   WHERE (sender_id = ? AND recipient_id = ?) 
                      OR (sender_id = ? AND recipient_id = ?) 
                      OR (sender_id = ? AND recipient_id = ?) 
                   ORDER BY timestamp ASC";
    $stmt = $conn->prepare($chat_query);
    $stmt->bind_param("ssssss", $current_user_id, $other_user_id, $other_user_id, $current_user_id, $system_user_id, $current_user_id);
    $stmt->execute();
    $messages = $stmt->get_result();
    
    // Output chat messages
    while ($message = $messages->fetch_assoc()) {
        $is_sent = $message['sender_id'] === $current_user_id;
        $is_system = $message['sender_id'] === $system_user_id;
        
        // Determine message class
        $message_class = $is_system ? 'system' : ($is_sent ? 'sent' : 'received');
        
        echo '<div class="message ' . $message_class . '">';
        echo '<div class="message-content">';
        
        if (!$is_sent && !$is_system) {
            echo '<img src="' . htmlspecialchars($other_user_pic) . '" class="user-avatar" alt="User Avatar">';
        } elseif ($is_system) {
            echo '<img src="' . htmlspecialchars($system_user_pic) . '" class="user-avatar" alt="Blood Konnection Avatar">';
        }
        
        echo '<div class="message-bubble">';
        echo '<p class="mb-0 ' . ($is_system ? 'text-muted' : 'text-black') . '">' . htmlspecialchars($message['message']) . '</p>';
        echo '<div class="timestamp">';
        echo date('h:i A', strtotime($message['timestamp']));
        echo '</div>';
        echo '</div>';
        
        if ($is_sent) {
            echo '<img src="' . htmlspecialchars($current_user_pic) . '" class="user-avatar" alt="Your Avatar">';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    // If recipient is offline, show system message and suggestions
    if (!$online_status) {
        echo '<div class="message system">';
        echo '<div class="message-content">';
        echo '<img src="' . htmlspecialchars($system_user_pic) . '" class="user-avatar" alt="Blood Konnection Avatar">';
        echo '<div class="message-bubble">';
        echo '<p class="mb-0 text-muted">' . htmlspecialchars($recipient_name) . ' is currently offline. We will notify them to respond as soon as possible.</p>';
        echo '<div class="timestamp">' . date('h:i A') . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    
        // Check if current user is recipient
        $role_query = "SELECT is_recipient FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($role_query);
        $stmt->bind_param("s", $current_user_id);
        $stmt->execute();
        $role_result = $stmt->get_result();
        $is_recipient = $role_result->fetch_assoc()['is_recipient'] == 1;
    
        // Show suggestions for recipients
        if ($is_recipient) {
            $suggestions = [
                "I need blood urgently, how can I find a donor?",
                "What are the blood types that can donate to me?",
                "How quickly can I get blood?",
                "How does Blood Konnector work?",
                "What should I do if I need blood in an emergency?",
                "How do I request blood on Blood Konnector?",
                "Can I track the status of my donation request?",
                "What should I do if I cannot find a donor on the platform?",
                "Is my information safe on this platform?",
                "How can I get help if I face issues on the platform?",
                "How does the platform match donors and recipients?",
                "What are the requirements for donating blood?"
            ];
    
            echo '<div class="suggestion-box">';
            echo '<div class="suggestion-title" onclick="toggleSuggestions()">';
            echo '<i class="fas fa-lightbulb"></i> ';
            echo '<span>Quick Questions</span>';
            echo '<i class="fas fa-chevron-up suggestion-toggle" id="suggestionToggle"></i>';
            echo '</div>';
            echo '<div class="suggestion-badges" id="suggestionBadges">';
            foreach ($suggestions as $question) {
                echo '<div class="suggestion-badge" onclick="setSuggestion(\'' . htmlspecialchars(addslashes($question)) . '\')">';
                echo '<i class="fas fa-comment-dots"></i> ';
                echo htmlspecialchars($question);
                echo '</div>';
            }
            echo '</div></div>';
        }
    }
?>