<?php
session_start();
include('assets/lib/openconn.php');

// Verify donor login
if (!isset($_SESSION['user_id']) || !is_donor($_SESSION['user_id'])) {
    header("Location: sign-in");
    exit();
}

$donor_id = $_SESSION['user_id'];

// Fetch donor profile picture with fallback
$profile_query = "SELECT profile_pic FROM users WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_query);
$profile_stmt->bind_param("s", $donor_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$other_user_data = $profile_result->fetch_assoc();
$profile_pic = !empty(trim($other_user_data['profile_pic']))
    ? $other_user_data['profile_pic']
    : 'assets/images/default-avatar.png';

// Handle message reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $recipient_id = mysqli_real_escape_string($conn, $_POST['recipient_id']);
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $query = "INSERT INTO messages (sender_id, recipient_id, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("sss", $donor_id, $recipient_id, $message);
            
            if ($stmt->execute()) {
                // Success - redirect to prevent form resubmission
                $_SESSION['message_sent'] = true;
                header("Location: donor-inbox.php");
                exit();
            } else {
                // Log error
                error_log("Message insert failed: " . $stmt->error);
                $_SESSION['error'] = "Failed to send message. Please try again.";
            }
            $stmt->close();
        } else {
            error_log("Message prepare failed: " . $conn->error);
            $_SESSION['error'] = "System error. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Message cannot be empty.";
    }
    
    // Redirect even on error to prevent resubmission
    header("Location: donor-inbox.php");
    exit();
}

// Get all recipients the donor has conversations with
$query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.profile_pic
          FROM messages m
          INNER JOIN users u ON (u.user_id = m.sender_id OR u.user_id = m.recipient_id)
          WHERE (m.recipient_id = ? OR m.sender_id = ?)
            AND u.user_id != ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $donor_id, $donor_id, $donor_id);
$stmt->execute();
$recipients = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        :root {
            --primary-red: #b5002a;
            --soft-red: #ff7c9f;
            --dark-red: #c41b47;
            --bg-light: #f8f9fa;
            --bg-white: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-light: #e9ecef;
            --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.12);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        body {
            background: var(--bg-light);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
        }

        .inbox-container {
            max-width: 1200px;
            margin: 2rem auto;
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            overflow: hidden;
        }

        .inbox-header {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .inbox-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }

        .inbox-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
            color: #fff;
        }

        .conversations-list {
            display: grid;
            gap: 1.5rem;
            padding: 2rem;
        }

        .conversation-card {
            background: var(--bg-white);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .conversation-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            border-color: var(--soft-red);
        }

        .conversation-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border-light);
        }

        .user-info {
            flex: 1;
        }

        .user-info h3 {
            margin: 0 0 0.25rem 0;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .user-info .last-active {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .chat-preview {
            background: var(--bg-light);
            border-radius: var(--radius-sm);
            padding: 1rem;
            margin-bottom: 1rem;
            max-height: 200px;
            overflow-y: auto;
        }

        .message {
            display: flex;
            margin-bottom: 1rem;
            animation: fadeIn 0.3s ease;
        }
        
        .message.new-message {
            animation: newMessagePulse 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideInRight {
            from { 
                opacity: 0; 
                transform: translateX(400px); 
            }
            to { 
                opacity: 1; 
                transform: translateX(0); 
            }
        }
        
        @keyframes newMessagePulse {
            0% { 
                opacity: 0; 
                transform: translateY(10px) scale(0.95); 
            }
            50% { 
                transform: translateY(-2px) scale(1.02); 
            }
            100% { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message.received {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1.25rem;
            border-radius: 1.25rem;
            position: relative;
            word-wrap: break-word;
        }

        .sent .message-bubble {
            background: var(--primary-red);
            color: white;
            border-bottom-right-radius: 0.5rem;
        }

        .received .message-bubble {
            background: var(--bg-white);
            border: 1px solid var(--border-light);
            border-bottom-left-radius: 0.5rem;
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
            text-align: right;
        }

        .received .message-time {
            text-align: left;
            color: rgba(0, 0, 0, 0.5);
        }

        .reply-section {
            border-top: 1px solid var(--border-light);
            padding-top: 1rem;
        }

        .reply-form {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            border: 1px solid var(--border-light);
            border-radius: 2rem;
            padding: 0.875rem 1.5rem;
            font-size: 0.95rem;
            resize: none;
            min-height: 50px;
            max-height: 120px;
            transition: all 0.2s ease;
            background: var(--bg-light);
        }

        .message-input:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(181, 0, 42, 0.1);
            background: var(--bg-white);
        }

        .send-button {
            background: var(--primary-red);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .send-button:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(181, 0, 42, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--border-light);
        }

        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            font-weight: 600;
        }

        .empty-state p {
            margin: 0;
            font-size: 1.1rem;
        }

        .conversation-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-light);
            border-radius: 2rem;
            padding: 0.5rem 1rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .btn-outline:hover {
            border-color: var(--primary-red);
            color: var(--primary-red);
        }

        /* Scrollbar styling */
        .chat-preview::-webkit-scrollbar {
            width: 6px;
        }

        .chat-preview::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-preview::-webkit-scrollbar-thumb {
            background: #c5c5c5;
            border-radius: 3px;
        }

        .chat-preview::-webkit-scrollbar-thumb:hover {
            background: #a5a5a5;
        }

        @media (max-width: 768px) {
            .inbox-container {
                margin: 0;
                border-radius: 0;
            }

            .inbox-header {
                padding: 1.5rem 1rem;
            }

            .inbox-header h1 {
                font-size: 1.75rem;
            }

            .conversations-list {
                padding: 1rem;
                gap: 1rem;
            }

            .conversation-card {
                padding: 1rem;
            }

            .conversation-header {
                gap: 0.75rem;
            }

            .user-avatar {
                width: 50px;
                height: 50px;
            }

            .message-bubble {
                max-width: 85%;
            }

            .reply-form {
                flex-direction: column;
                gap: 0.5rem;
            }

            .send-button {
                align-self: flex-end;
                width: 44px;
                height: 44px;
            }
        }

        @media (max-width: 480px) {
            .inbox-header {
                padding: 1.25rem 1rem;
            }

            .inbox-header h1 {
                font-size: 1.5rem;
            }

            .user-avatar {
                width: 45px;
                height: 45px;
            }

            .user-info h3 {
                font-size: 1.1rem;
            }

            .message-bubble {
                max-width: 90%;
                padding: 0.625rem 1rem;
            }

            .message-input {
                padding: 0.75rem 1.25rem;
            }
        }
    </style>
</head>
<body>
<?php include('assets/includes/header.php'); ?>

<div class="inbox-container">
    <div class="inbox-header">
        <h1>Your Messages</h1>
        <p>Connect with recipients and manage your conversations</p>
    </div>
    
    <?php if (isset($_SESSION['message_sent'])): ?>
        <div class="alert alert-success" style="margin: 1.5rem; padding: 1rem; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; color: #155724;">
            <i class="fas fa-check-circle"></i> Message sent successfully!
        </div>
        <?php unset($_SESSION['message_sent']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" style="margin: 1.5rem; padding: 1rem; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="conversations-list">
        <?php if ($recipients->num_rows > 0): ?>
            <?php while($recipient = $recipients->fetch_assoc()): ?>
                <div class="conversation-card" id="conversation-<?= $recipient['user_id'] ?>">
                    <div class="conversation-header">
                        <?php
                        $recipient_profile_pic = !empty(trim($recipient['profile_pic'])) 
                            ? $recipient['profile_pic'] 
                            : 'assets/images/default-avatar.png';
                        ?>
                        <img src="<?= htmlspecialchars($recipient_profile_pic) ?>" 
                             class="user-avatar" 
                             alt="<?= htmlspecialchars($recipient['first_name']) ?>">
                        <div class="user-info">
                            <h3><?= htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']) ?></h3>
                            <div class="last-active">Last active recently</div>
                        </div>
                    </div>

                    <div class="chat-preview">
                        <?php
                        $msg_query = "SELECT m.*, u.profile_pic FROM messages m
                                      JOIN users u ON m.sender_id = u.user_id
                                      WHERE (m.sender_id = ? AND m.recipient_id = ?)
                                         OR (m.sender_id = ? AND m.recipient_id = ?)
                                      ORDER BY m.timestamp DESC
                                      LIMIT 10";
                        $msg_stmt = $conn->prepare($msg_query);
                        $msg_stmt->bind_param("ssss", $donor_id, $recipient['user_id'], $recipient['user_id'], $donor_id);
                        $msg_stmt->execute();
                        $messages = $msg_stmt->get_result();
                        $recent_messages = [];

                        while($msg = $messages->fetch_assoc()) {
                            $recent_messages[] = $msg;
                        }
                        $recent_messages = array_reverse($recent_messages);

                        if (count($recent_messages) > 0): 
                            foreach($recent_messages as $msg):
                                $is_donor = $msg['sender_id'] == $donor_id;
                                $chat_profile = !empty(trim($msg['profile_pic'])) 
                                    ? $msg['profile_pic'] 
                                    : 'assets/images/default-avatar.png';
                        ?>
                                <div class="message <?= $is_donor ? 'sent' : 'received' ?>" data-message-id="<?= $msg['message_id'] ?>">
                                    <div class="message-bubble">
                                        <?= htmlspecialchars($msg['message']) ?>
                                        <div class="message-time">
                                            <?= date('h:i A', strtotime($msg['timestamp'])) ?>
                                        </div>
                                    </div>
                                </div>
                        <?php 
                            endforeach;
                        else: 
                        ?>
                            <div style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                                <i class="far fa-comments" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="reply-section">
                        <form method="POST" class="reply-form">
                            <textarea name="message" 
                                      class="message-input" 
                                      placeholder="Type your reply..." 
                                      rows="1" 
                                      required></textarea>
                            <input type="hidden" name="recipient_id" value="<?= $recipient['user_id'] ?>">
                            <button type="submit" class="send-button">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                        <!--<div class="conversation-actions">-->
                        <!--    <button class="btn-outline" onclick="location.href='chat.php?id=<?= $recipient['user_id'] ?>'">-->
                        <!--        <i class="fas fa-expand-alt"></i> Open Full Chat-->
                        <!--    </button>-->
                        <!--    <button class="btn-outline">-->
                        <!--        <i class="far fa-bookmark"></i> Save Conversation-->
                        <!--    </button>-->
                        <!--</div>-->
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-comments"></i>
                <h3>No conversations yet</h3>
                <p>When recipients message you, your conversations will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-fade success/error messages
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 3000);
    });
    
    // Auto-refresh messages configuration
    let messagePollingIntervals = new Map();
    const POLLING_INTERVAL = 2000; // 2 seconds (faster!)
    const MAX_RETRY_ERRORS = 5;
    let errorCounts = new Map();
    let lastMessageIds = new Map(); // Track last message ID for each conversation
    let notificationPermission = 'default';
    
    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            notificationPermission = permission;
        });
    } else if ('Notification' in window) {
        notificationPermission = Notification.permission;
    }
    
    // Initialize last message IDs from existing messages
    function initializeLastMessageIds() {
        document.querySelectorAll('.conversation-card').forEach(card => {
            const recipientId = card.id.replace('conversation-', '');
            const chatPreview = card.querySelector('.chat-preview');
            const messages = chatPreview.querySelectorAll('.message');
            
            if (messages.length > 0) {
                // Get the last message's ID from data attribute (we'll add this)
                const lastMessage = messages[messages.length - 1];
                const messageId = lastMessage.getAttribute('data-message-id');
                if (messageId) {
                    lastMessageIds.set(recipientId, parseInt(messageId));
                } else {
                    lastMessageIds.set(recipientId, 0);
                }
            } else {
                lastMessageIds.set(recipientId, 0);
            }
        });
    }
    
    // Create message HTML element
    function createMessageElement(messageData) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${messageData.is_own ? 'sent' : 'received'} new-message`;
        messageDiv.setAttribute('data-message-id', messageData.message_id);
        
        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble';
        
        const messageText = document.createTextNode(messageData.message);
        bubbleDiv.appendChild(messageText);
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.textContent = formatTime(messageData.timestamp);
        
        bubbleDiv.appendChild(timeDiv);
        messageDiv.appendChild(bubbleDiv);
        
        return messageDiv;
    }
    
    // Format timestamp
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        let hours = date.getHours();
        const minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        const minutesStr = minutes < 10 ? '0' + minutes : minutes;
        return hours + ':' + minutesStr + ' ' + ampm;
    }
    
    // Show browser notification
    function showBrowserNotification(senderName, message) {
        if (notificationPermission === 'granted' && document.hidden) {
            try {
                const notification = new Notification(`New message from ${senderName}`, {
                    body: message.substring(0, 100) + (message.length > 100 ? '...' : ''),
                    icon: 'assets/images/logo.png',
                    badge: 'assets/images/logo.png',
                    tag: 'blood-konnector-message',
                    requireInteraction: false
                });
                
                notification.onclick = function() {
                    window.focus();
                    notification.close();
                };
                
                // Auto-close after 5 seconds
                setTimeout(() => notification.close(), 5000);
            } catch (e) {
                // Notification failed silently
            }
        }
    }
    
    // Function to update messages for a specific conversation (NEW: Only appends new messages)
    async function updateConversationMessages(recipientId) {
        const chatPreview = document.querySelector(`#conversation-${recipientId} .chat-preview`);
        if (!chatPreview) return;
        
        // Check error count
        const errorCount = errorCounts.get(recipientId) || 0;
        if (errorCount >= MAX_RETRY_ERRORS) {
            stopPollingForConversation(recipientId);
            return;
        }
        
        const lastId = lastMessageIds.get(recipientId) || 0;
        
        try {
            const response = await fetch(`assets/lib/get-inbox-messages.php?recipient_id=${encodeURIComponent(recipientId)}&last_id=${lastId}`);
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.success && data.messages && data.messages.length > 0) {
                    const wasAtBottom = chatPreview.scrollHeight - chatPreview.scrollTop <= chatPreview.clientHeight + 50;
                    
                    // Remove "no messages" placeholder if exists
                    const placeholder = chatPreview.querySelector('[style*="text-align: center"]');
                    if (placeholder) {
                        placeholder.remove();
                    }
                    
                    // Append each new message
                    data.messages.forEach(msg => {
                        const messageElement = createMessageElement(msg);
                        chatPreview.appendChild(messageElement);
                        
                        // Update last message ID
                        lastMessageIds.set(recipientId, msg.message_id);
                        
                        // Show browser notification for received messages
                        if (!msg.is_own) {
                            showBrowserNotification(msg.sender_name, msg.message);
                        }
                    });
                    
                    // Auto-scroll if user was at bottom
                    if (wasAtBottom) {
                        chatPreview.scrollTop = chatPreview.scrollHeight;
                    }
                }
                
                // Reset error count on success
                errorCounts.set(recipientId, 0);
            } else {
                errorCounts.set(recipientId, errorCount + 1);
            }
        } catch (error) {
            errorCounts.set(recipientId, errorCount + 1);
        }
    }
    
    // Start polling for a specific conversation
    function startPollingForConversation(recipientId) {
        if (messagePollingIntervals.has(recipientId)) return; // Already polling
        
        // Set up interval (skip initial update since page already has messages)
        const intervalId = setInterval(() => {
            updateConversationMessages(recipientId);
        }, POLLING_INTERVAL);
        
        messagePollingIntervals.set(recipientId, intervalId);
    }
    
    // Stop polling for a specific conversation
    function stopPollingForConversation(recipientId) {
        const intervalId = messagePollingIntervals.get(recipientId);
        if (intervalId) {
            clearInterval(intervalId);
            messagePollingIntervals.delete(recipientId);
        }
    }
    
    // Start polling for all conversations
    function startAllPolling() {
        const conversations = document.querySelectorAll('.conversation-card');
        conversations.forEach(card => {
            const recipientId = card.id.replace('conversation-', '');
            if (recipientId) {
                startPollingForConversation(recipientId);
            }
        });
    }
    
    // Stop all polling
    function stopAllPolling() {
        messagePollingIntervals.forEach((intervalId, recipientId) => {
            clearInterval(intervalId);
        });
        messagePollingIntervals.clear();
    }
    
    // Use Page Visibility API to pause polling when tab is not active
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAllPolling();
        } else {
            startAllPolling();
        }
    });
    
    // Initialize last message IDs from existing messages
    initializeLastMessageIds();
    
    // Start polling when page loads
    startAllPolling();
    
    // Auto-resize textareas
    const textareas = document.querySelectorAll('.message-input');
    
    textareas.forEach(textarea => {
        // Set initial height
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
        
        // Add input event listener
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });

    // Add smooth scrolling to chat previews
    const chatPreviews = document.querySelectorAll('.chat-preview');
    chatPreviews.forEach(preview => {
        preview.scrollTop = preview.scrollHeight;
    });

    // Form submission handling with AJAX for instant updates
    const forms = document.querySelectorAll('.reply-form');
    forms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault(); // Prevent default form submission
            
            const textarea = this.querySelector('textarea');
            const message = textarea.value.trim();
            const recipientIdInput = this.querySelector('input[name="recipient_id"]');
            const recipientId = recipientIdInput ? recipientIdInput.value : null;
            
            if (!message) {
                alert('Please enter a message');
                return;
            }
            
            // Add loading state
            const button = this.querySelector('button[type="submit"]');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            try {
                // Submit via AJAX
                const formData = new FormData(this);
                const response = await fetch('donor-inbox.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    // Clear textarea
                    textarea.value = '';
                    textarea.style.height = 'auto';
                    
                    // Immediately fetch and append the new message
                    if (recipientId) {
                        // Small delay to ensure message is in database
                        await new Promise(resolve => setTimeout(resolve, 100));
                        await updateConversationMessages(recipientId);
                        
                        // Scroll to bottom
                        const chatPreview = document.querySelector(`#conversation-${recipientId} .chat-preview`);
                        if (chatPreview) {
                            chatPreview.scrollTop = chatPreview.scrollHeight;
                        }
                    }
                    
                    // Show success feedback (without reloading page)
                    showNotification('Message sent successfully!', 'success');
                } else {
                    showNotification('Failed to send message. Please try again.', 'error');
                }
            } catch (error) {
                showNotification('Error sending message. Please try again.', 'error');
            } finally {
                // Restore button
                button.innerHTML = originalHTML;
                button.disabled = false;
            }
        });
    });
    
    // Notification function
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
            border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
            color: ${type === 'success' ? '#155724' : '#721c24'};
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease;
        `;
        notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        document.body.appendChild(notification);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(400px)';
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    }

    // Allow Enter key to send message (but allow Shift+Enter for new line)
    textareas.forEach(textarea => {
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const form = this.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    });
});
</script>

<?php //include('assets/includes/footer.php'); ?>
</body>
</html>