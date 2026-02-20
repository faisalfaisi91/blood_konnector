<?php
    session_start();
    include('assets/lib/openconn.php');
    require_once('assets/lib/ProfileManager.php');
    
    // Initialize ProfileManager
    $profileManager = new ProfileManager($conn);
    
    // Check if user is logged in
    if (!$profileManager->isLoggedIn()) {
        $_SESSION['error'] = "Please login to view this page!";
        header("Location: sign-in.php");
        exit();
    }
    $userId = $_SESSION['user_id'];
    
    // Update last activity
    $profileManager->updateLastActivity();
    
    // Validate other_user_id
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $_SESSION['error'] = "Invalid user ID.";
        // Redirect to appropriate inbox based on active profile
        $active_profile = $profileManager->getCurrentProfile();
        $redirect_to = ($active_profile === 'recipient') ? 'recipient-inbox.php' : 'donor-inbox.php';
        header("Location: $redirect_to");
        exit();
    }
    $other_user_id = $_GET['id'];
    
    // Get current user's active profile (from session, not just table membership)
    $current_user_role = $profileManager->getCurrentProfile();
    
    // If no active profile is set, try to determine from available roles
    if (!$current_user_role) {
        $roles = $profileManager->getUserRoles();
        // Prioritize recipient if both exist (for chat functionality)
        if ($roles['is_recipient']) {
            $current_user_role = 'recipient';
            $profileManager->setViewingProfile('recipient');
        } elseif ($roles['is_donor']) {
            $current_user_role = 'donor';
            $profileManager->setViewingProfile('donor');
        } else {
            $current_user_role = 'unknown';
        }
    }
    
    // Get current user data
    $current_user_data = $profileManager->getUserInfo();
    if (!$current_user_data) {
        error_log("Current user not found: user_id = $userId");
        header("Location: donor-detail.php?id=" . urlencode($other_user_id) . "&alert=recipient_required");
        exit();
    }
    
    // Get profile picture
    $current_user_pic = !empty(trim($current_user_data['profile_pic']))
        ? $current_user_data['profile_pic']
        : 'assets/images/default-avatar.png';
    
    // Get other user data - Check profile type
    // For the other user, we need to determine their role based on table membership
    $other_user_query = "
        SELECT u.first_name, u.last_name, u.profile_pic,
               d.user_id AS is_donor,
               r.user_id AS is_recipient,
               COALESCE(u.profile_pic, d.profile_pic, r.profile_pic) AS profile_pic
        FROM users u
        LEFT JOIN donors d ON u.user_id = d.user_id
        LEFT JOIN recipients r ON u.user_id = r.user_id
        WHERE u.user_id = ?";
    $stmt = $conn->prepare($other_user_query);
    if (!$stmt) {
        error_log("Other user query preparation failed: " . $conn->error);
        header("Location: donor-detail.php?id=" . urlencode($other_user_id) . "&alert=recipient_required");
        exit();
    }
    $stmt->bind_param("s", $other_user_id);
    $stmt->execute();
    $other_user_result = $stmt->get_result();
    
    if ($other_user_result->num_rows === 0) {
        error_log("Other user not found: user_id = $other_user_id");
        $_SESSION['error'] = "User not found.";
        // Redirect to appropriate inbox based on active profile
        $redirect_to = ($current_user_role === 'recipient') ? 'recipient-inbox.php' : 'donor-inbox.php';
        header("Location: $redirect_to");
        exit();
    }
    $other_user_data = $other_user_result->fetch_assoc();
    
    // Determine other user's role
    // If they have both profiles, choose the opposite of current user's role
    if ($other_user_data['is_donor'] && $other_user_data['is_recipient']) {
        // User has both profiles - choose opposite of current user
        $other_user_role = ($current_user_role === 'recipient') ? 'donor' : 'recipient';
    } elseif ($other_user_data['is_donor']) {
        $other_user_role = 'donor';
    } elseif ($other_user_data['is_recipient']) {
        $other_user_role = 'recipient';
    } else {
        $other_user_role = 'unknown';
    }
    
    // Check for unknown roles
    if ($current_user_role === 'unknown' || $other_user_role === 'unknown') {
        error_log("Unknown role detected: current_role = $current_user_role, other_role = $other_user_role");
        header("Location: donor-detail.php?id=" . urlencode($other_user_id) . "&alert=recipient_required");
        exit();
    }
    
    // Validate chat pair
    $valid_chat = (
        ($current_user_role === 'recipient' && $other_user_role === 'donor') ||
        ($current_user_role === 'donor' && $other_user_role === 'recipient')
    );
    
    if (!$valid_chat) {
        error_log("Invalid chat pair: current_user_role = $current_user_role, other_user_role = $other_user_role");
        header("Location: donor-detail.php?id=" . urlencode($other_user_id) . "&alert=recipient_required");
        exit();
    }
    
    // Update message read status
    $update_read = "UPDATE messages SET is_read = 1 WHERE recipient_id = ? AND sender_id = ?";
    $stmt = $conn->prepare($update_read);
    if ($stmt) {
        $stmt->bind_param("ss", $userId, $other_user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Get messages between both users
    $chat_query = "SELECT * FROM messages 
                   WHERE (sender_id = ? AND recipient_id = ?) 
                      OR (sender_id = ? AND recipient_id = ?) 
                   ORDER BY timestamp ASC";
    $stmt = $conn->prepare($chat_query);
    if ($stmt) {
        $stmt->bind_param("ssss", $userId, $other_user_id, $other_user_id, $userId);
        $stmt->execute();
        $messages = $stmt->get_result();
    } else {
        error_log("Chat query preparation failed: " . $conn->error);
        header("Location: donor-detail.php?id=" . urlencode($other_user_id) . "&alert=recipient_required");
        exit();
    }
    
    // Get online status using ProfileManager
    $online_status = $profileManager->isUserOnline($other_user_id);
    
    $profile_pic = !empty(trim($other_user_data['profile_pic']))
        ? $other_user_data['profile_pic']
        : 'assets/images/default-avatar.png';
    
    // Get recipient profile data for automated emergency requests
    $recipient_profile_data = null;
    if ($current_user_role === 'recipient') {
        $recipient_query = "SELECT blood_type, location, hospital_name FROM recipients WHERE user_id = ? LIMIT 1";
        $recipient_stmt = $conn->prepare($recipient_query);
        if ($recipient_stmt) {
            $recipient_stmt->bind_param("s", $userId);
            $recipient_stmt->execute();
            $recipient_result = $recipient_stmt->get_result();
            if ($recipient_result && $recipient_result->num_rows > 0) {
                $recipient_profile_data = $recipient_result->fetch_assoc();
            }
            $recipient_stmt->close();
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        :root {
            --primary-red: #b5002a;
            --soft-red: #ff7c9f;
            --dark-red: #c41b47;
            --chat-bg: #f8f9fa;
            --message-sent: #b5002a;
            --message-received: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-light: #e9ecef;
            --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .chat-container {
            max-width: 1200px;
            margin: 2rem auto;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            height: 85vh;
            min-height: 500px;
            display: flex;
            flex-direction: column;
            background: white;
            position: relative;
        }

        .chat-header {
            background: white;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-light);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            flex-shrink: 0;
        }

        .chat-header img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-light);
        }

        .user-info {
            flex: 1;
        }

        .user-info h4 {
            margin: 0 0 0.25rem 0;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .online-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: <?= $online_status ? '#4CAF50' : '#9e9e9e' ?>;
            box-shadow: <?= $online_status ? '0 0 6px rgba(76, 175, 80, 0.4)' : 'none' ?>;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: #f5f7fb;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: relative;
        }

        .message {
            display: flex;
            margin-bottom: 0.75rem;
            max-width: 75%;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .sent {
            align-self: flex-end;
            justify-content: flex-end;
        }

        .received {
            align-self: flex-start;
            justify-content: flex-start;
        }

        .message-content {
            display: flex;
            align-items: flex-end;
            gap: 0.75rem;
            max-width: 100%;
        }

        .message-bubble {
            background: var(--message-received);
            padding: 0.75rem 1.25rem;
            border-radius: 1.25rem;
            position: relative;
            max-width: 100%;
            word-wrap: break-word;
            box-shadow: var(--shadow-light);
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .sent .message-bubble {
            background: var(--message-sent);
            color: white;
            border-bottom-right-radius: 0.5rem;
        }

        .received .message-bubble {
            background: white;
            border: 1px solid var(--border-light);
            border-bottom-left-radius: 0.5rem;
        }

        .message-bubble p {
            margin: 0;
            line-height: 1.4;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .timestamp {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 0.25rem;
            text-align: right;
        }

        .received .timestamp {
            color: var(--text-secondary);
        }

        .message-input {
            padding: 1.25rem;
            background: #fff;
            border-top: 1px solid var(--border-light);
            flex-shrink: 0;
        }

        .input-group {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .form-control {
            border-radius: 1.5rem;
            border: 1px solid var(--border-light);
            padding: 0.875rem 1.5rem;
            flex: 1;
            font-size: 0.95rem;
            resize: none;
            min-height: 50px;
            max-height: 120px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(181, 0, 42, 0.1);
        }

        .btn-send {
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

        .btn-send:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(181, 0, 42, 0.3);
        }

        .btn-send:active {
            transform: translateY(0);
        }

        /* Suggestions Section - Compact & User Friendly */
        .suggestion-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 12px 16px;
            margin: 15px 0;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }
        
        .suggestion-title {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }
        
        .suggestion-title i {
            font-size: 1.1rem;
        }
        
        .suggestion-toggle {
            margin-left: auto;
            font-size: 0.8rem;
            opacity: 0.9;
            transition: transform 0.3s ease;
        }
        
        .suggestion-toggle.collapsed {
            transform: rotate(180deg);
        }
        
        .suggestion-badges {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            overflow-y: hidden;
            padding: 8px 0;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            max-height: 200px;
            flex-wrap: wrap;
            transition: max-height 0.3s ease, opacity 0.3s ease;
        }
        
        .suggestion-badges.collapsed {
            max-height: 0;
            opacity: 0;
            padding: 0;
            overflow: hidden;
        }
        
        .suggestion-badges::-webkit-scrollbar {
            height: 6px;
        }
        
        .suggestion-badges::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }
        
        .suggestion-badges::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        .suggestion-badges::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .suggestion-badge {
            background: white;
            color: #667eea;
            padding: 8px 14px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            white-space: nowrap;
            flex-shrink: 0;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .suggestion-badge i {
            font-size: 0.75rem;
        }

        .suggestion-badge:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .suggestion-badge:active {
            transform: translateY(0) scale(0.98);
        }
        
        /* Old container - keep for compatibility */
        .suggestions-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 1rem;
            backdrop-filter: blur(5px);
        }

        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: white;
            border-radius: 1.25rem;
            border-bottom-left-radius: 0.5rem;
            width: fit-content;
            margin-bottom: 0.75rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-light);
        }

        .typing-dots {
            display: flex;
            gap: 0.25rem;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--text-secondary);
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: 0s; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            text-align: center;
            padding: 2rem;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-light);
        }

        .empty-state p {
            margin: 0;
            font-size: 1.1rem;
        }

        /* Scrollbar styling */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #c5c5c5;
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #a5a5a5;
        }

        @media (max-width: 768px) {
            .chat-container {
                margin: 0;
                height: 100vh;
                border-radius: 0;
            }

            .chat-header {
                padding: 1rem;
            }

            .chat-messages {
                padding: 1rem;
            }

            .message {
                max-width: 85%;
            }

            .message-bubble {
                max-width: 100%;
            }

            .message-input {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .chat-header img {
                width: 42px;
                height: 42px;
            }

            .user-avatar {
                width: 32px;
                height: 32px;
            }

            .message {
                max-width: 90%;
            }

            .message-bubble {
                padding: 0.625rem 1rem;
            }

            .form-control {
                padding: 0.75rem 1.25rem;
            }

            .btn-send {
                width: 44px;
                height: 44px;
            }
        }
    </style>
    <?php include('assets/includes/link-js.php'); ?>
</head>
<body>
    <?php include('assets/includes/header.php'); ?>
    
    <div class="chat-container">
        <div class="chat-header">
            <a href="<?= $current_user_role === 'recipient' ? 'recipient-inbox' : 'donor-inbox' ?>" class="back-button me-2" title="Back to Chat List" style="text-decoration:none;color:inherit;font-size:1.2rem;">
                <i class="fas fa-arrow-left"></i> Chat List
            </a>
            <img src="<?= htmlspecialchars($profile_pic) ?>" alt="User Avatar">
            <div class="user-info">
                <h4><?= htmlspecialchars($other_user_data['first_name'] . ' ' . $other_user_data['last_name']) ?></h4>
                <div class="status-indicator">
                    <span class="online-dot"></span>
                    <span><?= $online_status ? 'Online' : 'Offline' ?></span>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-sm btn-primary me-1" id="schedulingDonationBtn" title="Schedule donation after approval">
                    <i class="fas fa-calendar-check me-1"></i> Scheduling Donation
                </button>
                <?php if ($current_user_role === 'recipient'): ?>
                    <button class="btn btn-sm btn-danger" id="emergencyQuick">
                        <i class="fas fa-hand-holding-heart me-1"></i> Emergency Request
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scheduling Donation Modal -->
        <div class="modal fade" id="schedulingDonationModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-calendar-check"></i> Scheduling Donation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="schedulingDonationForm">
                            <input type="hidden" name="recipient_id" value="<?= $current_user_role === 'recipient' ? htmlspecialchars($userId) : htmlspecialchars($other_user_id) ?>">
                            <input type="hidden" name="donor_id" value="<?= $current_user_role === 'donor' ? htmlspecialchars($userId) : htmlspecialchars($other_user_id) ?>">
                            <input type="hidden" name="other_user_id" value="<?= htmlspecialchars($other_user_id) ?>">
                            <div class="mb-3">
                                <label>Donation Date *</label>
                                <input type="date" name="donation_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label>Donation Time *</label>
                                <input type="time" name="donation_time" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Location / Hospital *</label>
                                <input type="text" name="location" class="form-control" placeholder="Hospital or donation center address" required>
                            </div>
                            <div class="mb-3">
                                <label>Additional Details</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Any special instructions"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="submitSchedulingBtn">
                            <i class="fas fa-paper-plane"></i> Submit & Notify Donor
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <?php if($messages->num_rows === 0): ?>
                <div class="empty-state">
                    <i class="far fa-comments"></i>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            <?php else: ?>
                <div class="suggestions-container"></div>
                <?php while($message = $messages->fetch_assoc()): ?>
                    <div class="message <?= $message['sender_id'] === $userId ? 'sent' : 'received' ?>">
                        <div class="message-content">
                            <?php if ($message['sender_id'] !== $userId): ?>
                                <img src="<?= htmlspecialchars($profile_pic) ?>" class="user-avatar" alt="User Avatar">
                            <?php endif; ?>
                            <div class="message-bubble">
                                <p class="mb-0"><?= htmlspecialchars($message['message']) ?></p>
                                <div class="timestamp">
                                    <?= format_display_date($message['timestamp']) ?>
                                </div>
                            </div>
                            <?php if ($message['sender_id'] === $userId): ?>
                                <img src="<?= htmlspecialchars($current_user_pic) ?>" class="user-avatar" alt="Your Avatar">
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <div class="message-input">
            <form id="chatForm" method="post">
                <div class="input-group">
                    <input type="hidden" name="recipient_id" value="<?= htmlspecialchars($other_user_id) ?>">
                    <textarea name="message" id="messageInput" class="form-control" placeholder="Type your message..." rows="1" required></textarea>
                    <button type="submit" class="btn-send">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="progress-wrap">
        <svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98"/>
        </svg>
    </div>

    <script>
    // Global function for setting suggestion text
    function setSuggestion(text) {
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.value = text;
            messageInput.focus();
            
            // Auto-resize textarea
            messageInput.style.height = 'auto';
            messageInput.style.height = (messageInput.scrollHeight) + 'px';
        }
    }
    
    // Toggle suggestions visibility
    function toggleSuggestions() {
        const badges = document.getElementById('suggestionBadges');
        const toggle = document.getElementById('suggestionToggle');
        
        if (badges && toggle) {
            badges.classList.toggle('collapsed');
            toggle.classList.toggle('collapsed');
            
            // Save state to localStorage
            const isCollapsed = badges.classList.contains('collapsed');
            localStorage.setItem('suggestionCollapsed', isCollapsed);
        }
    }
    
    // Restore suggestion state from localStorage
    function restoreSuggestionState() {
        const isCollapsed = localStorage.getItem('suggestionCollapsed') === 'true';
        const badges = document.getElementById('suggestionBadges');
        const toggle = document.getElementById('suggestionToggle');
        
        if (isCollapsed && badges && toggle) {
            badges.classList.add('collapsed');
            toggle.classList.add('collapsed');
        }
    }
    
    // Call on page load
    document.addEventListener('DOMContentLoaded', restoreSuggestionState);
    
    document.addEventListener('DOMContentLoaded', function() {
        const chatForm = document.getElementById('chatForm');
        const chatMessages = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        
        // Auto-resize textarea based on content
        function autoResizeTextarea() {
            messageInput.style.height = 'auto';
            messageInput.style.height = (messageInput.scrollHeight) + 'px';
        }
        
        messageInput.addEventListener('input', autoResizeTextarea);
        
        // Auto-scroll to bottom
        function scrollToBottom() {
            chatMessages.scrollTo({
                top: chatMessages.scrollHeight,
                behavior: 'smooth'
            });
        }
        
        // Load messages with fetch API - OPTIMIZED (No Flickering)
        let isLoadingMessages = false; // Prevent multiple simultaneous calls
        let loadMessagesErrorCount = 0; // Track consecutive errors
        const MAX_ERRORS = 3; // Stop polling after 3 consecutive errors
        let lastMessageCount = 0; // Track number of messages to detect new ones
        let currentMessagesHTML = ''; // Store current HTML to compare
        
        async function loadMessages(forceUpdate = false) {
            // Prevent multiple simultaneous calls
            if (isLoadingMessages) {
                console.log('Already loading messages, skipping...');
                return;
            }
            
            // Stop polling if too many errors
            if (loadMessagesErrorCount >= MAX_ERRORS) {
                console.error('Too many errors, stopped auto-refresh');
                return;
            }
            
            isLoadingMessages = true;
            
            try {
                const response = await fetch(`assets/lib/get-messages.php?user=<?= htmlspecialchars($other_user_id) ?>`);
                if (response.ok) {
                    const newData = await response.text();
                    
                    if (newData.trim()) {
                        // Only update if content has actually changed
                        if (forceUpdate || newData !== currentMessagesHTML) {
                            // Store the current scroll position
                            const wasScrolledToBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;
                            
                            // Check if we're adding a new message (user is at bottom)
                            const newMessageCount = (newData.match(/class="message/g) || []).length;
                            const hasNewMessages = newMessageCount > lastMessageCount;
                            
                            // Update the content
                            chatMessages.innerHTML = newData;
                            currentMessagesHTML = newData;
                            lastMessageCount = newMessageCount;
                            
                            // Only auto-scroll if user was at bottom or there are new messages
                            if (wasScrolledToBottom || hasNewMessages) {
                                scrollToBottom();
                            }
                            
                            // Notification: play sound and show popup when new message arrives (tab in background)
                            if (hasNewMessages && document.hidden) {
                                try {
                                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                                    const osc = ctx.createOscillator();
                                    const gain = ctx.createGain();
                                    osc.connect(gain);
                                    gain.connect(ctx.destination);
                                    osc.frequency.value = 880;
                                    gain.gain.value = 0.2;
                                    osc.start();
                                    setTimeout(function() { osc.stop(); }, 120);
                                } catch (e) {}
                                if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                                    new Notification('New message', { body: 'You have a new message', icon: 'assets/images/default-avatar.png' });
                                }
                            }
                            
                            // Re-attach suggestion click events
                            attachSuggestionEvents();
                        } else {
                            console.log('No changes in messages, skipped update');
                        }
                        
                        // Reset error count on success
                        loadMessagesErrorCount = 0;
                    }
                } else {
                    loadMessagesErrorCount++;
                }
            } catch (error) {
                loadMessagesErrorCount++;
            } finally {
                isLoadingMessages = false;
            }
        }
        
        // Attach click events to suggestion badges
        function attachSuggestionEvents() {
            const suggestionBadges = document.querySelectorAll('.suggestion-badge');
            suggestionBadges.forEach(badge => {
                badge.addEventListener('click', function() {
                    const text = this.getAttribute('data-text') || this.textContent.trim();
                    setSuggestion(text);
                });
            });
            
            // Restore suggestion collapsed state after loading new messages
            restoreSuggestionState();
        }
        
        // Update online status
        async function updateOnlineStatus() {
            try {
                const response = await fetch(`check-online-status.php?user_id=<?= htmlspecialchars($other_user_id) ?>`);
                if (response.ok) {
                    const data = await response.json();
                    const onlineDot = document.querySelector('.online-dot');
                    const statusText = document.querySelector('.status-indicator span:last-child');
                    
                    if (onlineDot && statusText) {
                        onlineDot.style.background = data.online ? '#4CAF50' : '#9e9e9e';
                        onlineDot.style.boxShadow = data.online ? '0 0 6px rgba(76, 175, 80, 0.4)' : 'none';
                        statusText.textContent = data.online ? 'Online' : 'Offline';
                    }
                }
            } catch (error) {
                console.error('Error updating online status:', error);
            }
        }
        
        // Form submission with async/await
        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const message = messageInput.value.trim();
            
            if (!message) {
                alert('Please enter a message');
                return;
            }
            
            try {
                const response = await fetch('assets/lib/send-message.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    throw new Error('Invalid response from server');
                }
                
                if (result.success) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    loadMessages(true); // Force update after sending
                } else {
                    alert('Failed to send message: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Error sending message: ' + error.message);
            }
        });
        
        // Initial setup
        scrollToBottom();
        attachSuggestionEvents();
        autoResizeTextarea();
        
        // Set up periodic updates - OPTIMIZED with Page Visibility API
        let messagePollingInterval = null;
        let statusPollingInterval = null;
        
        function startPolling() {
            // Only start if not already running
            if (messagePollingInterval) return;
            
            // Poll messages every 5 seconds (increased from 3 to reduce load)
            messagePollingInterval = setInterval(loadMessages, 5000);
            
            // Poll online status every 30 seconds
            statusPollingInterval = setInterval(updateOnlineStatus, 30000);
        }
        
        function stopPolling() {
            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
                messagePollingInterval = null;
            }
            if (statusPollingInterval) {
                clearInterval(statusPollingInterval);
                statusPollingInterval = null;
            }
        }
        
        // Use Page Visibility API to pause polling when tab is not active
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopPolling();
            } else {
                startPolling();
                loadMessages(true); // Force update when returning to tab
            }
        });
        
        // Start polling
        startPolling();

        // Scheduling Donation modal
        const schedulingBtn = document.getElementById('schedulingDonationBtn');
        const schedulingModal = document.getElementById('schedulingDonationModal');
        const submitSchedulingBtn = document.getElementById('submitSchedulingBtn');
        const schedulingForm = document.getElementById('schedulingDonationForm');
        if (schedulingBtn && schedulingModal) {
            schedulingBtn.addEventListener('click', function() {
                const modal = new bootstrap.Modal(schedulingModal);
                modal.show();
            });
        }
        if (submitSchedulingBtn && schedulingForm) {
            submitSchedulingBtn.addEventListener('click', async function() {
                const fd = new FormData(schedulingForm);
                fd.append('action', 'schedule_donation');
                const date = schedulingForm.querySelector('[name="donation_date"]').value;
                const time = schedulingForm.querySelector('[name="donation_time"]').value;
                const loc = schedulingForm.querySelector('[name="location"]').value;
                if (!date || !time || !loc) {
                    alert('Please fill in date, time, and location.');
                    return;
                }
                submitSchedulingBtn.disabled = true;
                submitSchedulingBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                try {
                    const res = await fetch('assets/lib/scheduling-api.php', { method: 'POST', body: fd });
                    const json = await res.json();
                    if (json.success) {
                        bootstrap.Modal.getInstance(schedulingModal).hide();
                        alert(json.message || 'Scheduling submitted. Donor will be notified to confirm.');
                        schedulingForm.reset();
                    } else {
                        alert(json.error || 'Failed to submit');
                    }
                } catch (e) {
                    alert('Error: ' + e.message);
                }
                submitSchedulingBtn.disabled = false;
                submitSchedulingBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit & Notify Donor';
            });
        }

        // Quick emergency request hook (recipient side) - Automated
        const emergencyBtn = document.getElementById('emergencyQuick');
        if (emergencyBtn) {
            emergencyBtn.addEventListener('click', async () => {
                // Disable button to prevent multiple clicks
                emergencyBtn.disabled = true;
                emergencyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Creating Request...';
                
                try {
                                // Get recipient profile data from PHP for automated emergency requests
                    const recipientData = <?= json_encode($recipient_profile_data); ?>;
                    
                    if (!recipientData) {
                        alert('Recipient profile data not found. Please update your profile first.');
                        emergencyBtn.disabled = false;
                        emergencyBtn.innerHTML = '<i class="fas fa-hand-holding-heart me-1"></i> Emergency Request';
                        return;
                    }
                    
                    // Set default date (tomorrow)
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    const defaultDate = tomorrow.toISOString().split('T')[0];
                    
                    // Set default time (10:00 AM)
                    const defaultTime = '10:00';
                    
                    // Use recipient's location/hospital, fallback to location
                    const location = recipientData.hospital_name || recipientData.location || 'Hospital';
                    
                    // Extract city from location or use location as city
                    // Try to get city from location (assuming format might be "City, Address" or just "City")
                    let city = recipientData.location || '';
                    // If location contains comma, take the first part as city
                    if (city.includes(',')) {
                        city = city.split(',')[0].trim();
                    }
                    // If no location, use a default or prompt
                    if (!city) {
                        city = prompt('Please enter your city name:');
                        if (!city) {
                            alert('City is required to match with nearby donors.');
                            emergencyBtn.disabled = false;
                            emergencyBtn.innerHTML = '<i class="fas fa-hand-holding-heart me-1"></i> Emergency Request';
                            return;
                        }
                    }
                    
                    // Get blood type from recipient profile
                    const bloodType = recipientData.blood_type || '';
                    
                    // Create request automatically
                    const data = new FormData();
                    data.append('action', 'create_request');
                    data.append('preferred_date', defaultDate);
                    data.append('preferred_time', defaultTime);
                    data.append('city', city);
                    data.append('location', location);
                    data.append('urgency', 'high'); // Set as high urgency for automated requests
                    data.append('note', 'Automated emergency request created from chat');
                    if (bloodType) {
                        data.append('blood_type', bloodType);
                    }
                    // Don't set donor_id - let the API auto-match donors
                    
                    const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: data });
                    const json = await res.json();
                    
                    if (json.success) {
                        let message = 'Emergency request created successfully!';
                        if (json.matching_donors_notified > 0) {
                            message += ` ${json.matching_donors_notified} matching donor(s) have been notified.`;
                        } else if (json.donor_assigned) {
                            message += ' A donor has been assigned to your request.';
                        } else {
                            message += ' The system will find and notify matching donors.';
                        }
                        alert(message);
                        // Optionally reload the page to show the new request
                        setTimeout(() => {
                            window.location.href = 'emergency-recipient.php';
                        }, 1500);
                    } else {
                        alert(json.error || 'Failed to create emergency request.');
                        emergencyBtn.disabled = false;
                        emergencyBtn.innerHTML = '<i class="fas fa-hand-holding-heart me-1"></i> Emergency Request';
                    }
                } catch (e) {
                    console.error('Error creating emergency request:', e);
                    alert('Network error creating emergency request. Please try again.');
                    emergencyBtn.disabled = false;
                    emergencyBtn.innerHTML = '<i class="fas fa-hand-holding-heart me-1"></i> Emergency Request';
                }
            });
        }
        
        // Focus input when clicking anywhere in the message area
        chatMessages.addEventListener('click', () => {
            messageInput.focus();
        });
        
        // Allow Enter key to send message (but allow Shift+Enter for new line)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });
    });
    </script>
    
    <?php include('assets/includes/footer.php'); ?>
    <?php include('assets/includes/link-js.php'); ?>
</body>
</html>