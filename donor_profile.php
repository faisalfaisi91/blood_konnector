<?php
session_start();
include('assets/lib/openconn.php');

// Check if user is logged in as donor
if (!isset($_SESSION['user_id']) || !is_donor($_SESSION['user_id'])) {
    header("Location: sign-in");
    exit();
}

$donor_id = $_SESSION['user_id'];

// Get unread message count
$unread_count = 0;
$message_query = "SELECT COUNT(*) AS unread 
                 FROM messages 
                 WHERE recipient_id = ? 
                 AND is_read = 0";
$stmt = $conn->prepare($message_query);
$stmt->bind_param("s", $donor_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['unread'];

// Get donor details
$donor_query = "SELECT * FROM donors WHERE user_id = ?";
$stmt = $conn->prepare($donor_query);
$stmt->bind_param("s", $donor_id);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();

// Update last activity
$conn->query("UPDATE users SET last_activity = NOW() WHERE user_id = '$donor_id'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
    :root {
        --primary: #EA062B;
        --primary-light: #ffe6ea;
        --text: #2d3748;
        --text-light: #718096;
    }

    .profile-card {
        background: #ffffff;
        border-radius: 1.5rem;
        padding: 3rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        max-width: 800px;
        margin: 2rem auto;
    }

    .profile-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2.5rem;
    }

    .user-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: var(--primary);
        font-weight: 600;
    }

    .detail-card {
        background: #f8fafc;
        border-radius: 1rem;
        padding: 2rem;
        margin: 2rem 0;
    }

    .detail-item {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 1.5rem;
        padding: 1rem 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .detail-item:last-child {
        border-bottom: none;
    }

    .action-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        margin-top: 2.5rem;
    }

    .btn-primary {
        background: var(--primary);
        padding: 0.8rem 1.5rem;
        border-radius: 0.75rem;
        color: white;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.2s;
    }

    .btn-primary:hover {
        background: #c80523;
        transform: translateY(-1px);
    }

    .notification-badge {
        background: var(--primary);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.85rem;
        margin-left: 0.5rem;
    }

    .message-alert {
        background: var(--primary-light);
        border-left: 4px solid var(--primary);
        padding: 1.25rem;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    @media (max-width: 768px) {
        .profile-card {
            padding: 1.5rem;
            margin: 1rem;
        }
        
        .detail-item {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
    }
    </style>
</head>
<body>
    <?php include('assets/includes/header.php'); ?>

    <main class="container">
        <div class="profile-card">
            <!-- Header Section -->
            <div class="profile-header">
                <div class="user-avatar">
                    <?= strtoupper(substr($donor['first_name'], 0, 1)) ?>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?>
                    </h1>
                    <p class="text-gray-600">Blood Donor</p>
                </div>
            </div>

            <!-- Notification Alert -->
            <?php if ($unread_count > 0): ?>
                <div class="message-alert">
                    <i class="fas fa-bell text-red-600"></i>
                    <div>
                        <p class="font-medium">You have <?= $unread_count ?> unread message(s)</p>
                        <a href="donor-inbox" class="text-red-600 hover:underline">View Messages →</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Donor Details -->
            <div class="detail-card">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Donor Information</h2>
                
                <div class="detail-item">
                    <span class="text-gray-600">Blood Type</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($donor['blood_type']) ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="text-gray-600">Location</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($donor['location']) ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="text-gray-600">Last Donation</span>
                    <span class="font-medium text-gray-800">
                        <?= date('F j, Y', strtotime($donor['last_donation_date'])) ?>
                    </span>
                </div>
                
                <div class="detail-item">
                    <span class="text-gray-600">Contact Number</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($donor['contact_number']) ?></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-grid">
                <!--<a href="edit-donor-profile" class="btn-primary">-->
                <!--    <i class="fas fa-edit"></i>-->
                <!--    Edit Profile-->
                <!--</a>-->
                
                <a href="donor-inbox" class="btn-primary">
                    <i class="fas fa-envelope"></i>
                    Messages
                    <?= $unread_count > 0 ? '<span class="notification-badge">'.$unread_count.'</span>' : '' ?>
                </a>
                
                <a href="logout" class="btn-primary bg-gray-700 hover:bg-gray-800">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </main>

    <?php include('assets/includes/footer.php'); ?>
    <?php include('assets/includes/link-js.php'); ?>
</body>
</html>