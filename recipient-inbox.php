<?php
session_start();
include('assets/lib/openconn.php');
require_once('assets/lib/ProfileManager.php');

// =============== 1. INITIALIZE PROFILE MANAGER ===============
$profileManager = new ProfileManager($conn);

// =============== 2. REQUIRE LOGIN & RECIPIENT ROLE ===============
$profileManager->requireRole('recipient', 'profile');

// =============== 3. UPDATE LAST ACTIVITY ===============
$profileManager->updateLastActivity();

$recipient_id = $_SESSION['user_id'];

// Get all conversations
$query = "SELECT 
            m.sender_id AS donor_id,
            d.first_name,
            d.last_name,
            d.profile_pic,
            MAX(m.timestamp) AS last_message_time,
            COUNT(CASE WHEN m.is_read = 0 THEN 1 END) AS unread_count,
            last_msg.message AS last_message
          FROM messages m
          INNER JOIN (
              SELECT sender_id, MAX(timestamp) AS max_time
              FROM messages
              WHERE recipient_id = ?
              GROUP BY sender_id
          ) latest ON m.sender_id = latest.sender_id AND m.timestamp = latest.max_time
          INNER JOIN donors d ON m.sender_id = d.user_id
          LEFT JOIN messages last_msg ON last_msg.timestamp = latest.max_time
          WHERE m.recipient_id = ?
          GROUP BY m.sender_id
          ORDER BY last_message_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $recipient_id, $recipient_id);
$stmt->execute();
$conversations = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        .inbox-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .conversation-card {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }

        .conversation-card:hover {
            background: #f9f9f9;
            cursor: pointer;
        }

        .unread-badge {
            background: #EA062B;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }

        .last-message {
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
        }

        .time-ago {
            font-size: 0.8em;
            color: #999;
        }
    </style>
</head>
<body>
    <?php include('assets/includes/header.php'); ?>

    <div class="breadcrumb_section overflow-hidden ptb-150">
        <div class="container">
            <h2 class="text-center">Your Messages</h2>
        </div>
    </div>

    <div class="container my-5">
        <div class="inbox-container">
            <?php if ($conversations->num_rows > 0): ?>
                <?php while($conv = $conversations->fetch_assoc()): ?>
                    <a href="chat.php?id=<?= $conv['donor_id'] ?>" class="text-decoration-none text-dark">
                        <div class="conversation-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <img src="<?= htmlspecialchars($conv['profile_pic']) ?>" 
                                         class="rounded-circle"
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                    <div class="ms-3">
                                        <h5 class="mb-0">
                                            <?= htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']) ?>
                                            <?php if ($conv['unread_count'] > 0): ?>
                                                <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="last-message mb-0">
                                            <?= htmlspecialchars($conv['last_message']) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small class="time-ago">
                                        <?php
                                        $timeAgo = time() - strtotime($conv['last_message_time']);
                                        if ($timeAgo < 60) {
                                            echo 'Just now';
                                        } elseif ($timeAgo < 3600) {
                                            echo floor($timeAgo/60) . 'm ago';
                                        } elseif ($timeAgo < 86400) {
                                            echo floor($timeAgo/3600) . 'h ago';
                                        } else {
                                            echo date('M j', strtotime($conv['last_message_time']));
                                        }
                                        ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center p-5">
                    <h4>No messages yet</h4>
                    <p>When donors send you messages, they'll appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include('assets/includes/footer.php'); ?>
    <?php include('assets/includes/link-js.php'); ?>
</body>
</html>