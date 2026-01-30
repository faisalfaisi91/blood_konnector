<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('recipient', 'sign-in.php');
$userId = $_SESSION['user_id'];

// Check if recipient has LifeLine profile/membership
$hasLifeLine = false;
$lifelineProfile = null;
$profileStmt = $conn->prepare("SELECT * FROM lifeline_profiles WHERE recipient_id = ? LIMIT 1");
$profileStmt->bind_param("s", $userId);
$profileStmt->execute();
$profileResult = $profileStmt->get_result();
if ($profileResult && $profileResult->num_rows > 0) {
    $hasLifeLine = true;
    $lifelineProfile = $profileResult->fetch_assoc();
}
$profileStmt->close();

// If no LifeLine profile, redirect to registration
if (!$hasLifeLine) {
    $_SESSION['error'] = "You are not registered for LifeLine. Please register first.";
    header('Location: lifeline-panel.php');
    exit();
}

// Fetch recipient basic info
$recipientStmt = $conn->prepare("SELECT * FROM recipients WHERE user_id = ? LIMIT 1");
$recipientStmt->bind_param("s", $userId);
$recipientStmt->execute();
$recipientResult = $recipientStmt->get_result();
$recipient = $recipientResult->fetch_assoc();
$recipientStmt->close();

// Fetch user info
$userStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$userStmt->bind_param("s", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

// ============================================
// SECTION 1: DASHBOARD OVERVIEW STATISTICS
// ============================================
$stats = [
    'active_requests' => 0,
    'upcoming_confirmed' => 0,
    'pending_confirmations' => 0,
    'total_successful' => 0,
    'failed_donations' => 0,
    'connected_donors' => 0,
    'unread_notifications' => 0
];

// Count active requests
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM lifeline_requests WHERE recipient_id = ? AND status IN ('pending', 'open')");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stats['active_requests'] = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Count upcoming confirmed donations
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM lifeline_requests WHERE recipient_id = ? AND status = 'confirmed' AND preferred_date >= CURDATE()");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stats['upcoming_confirmed'] = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Count pending confirmations
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM lifeline_requests WHERE recipient_id = ? AND status = 'awaiting_confirmation'");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stats['pending_confirmations'] = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Count total successful donations
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM lifeline_requests WHERE recipient_id = ? AND status = 'completed'");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stats['total_successful'] = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Count failed donations
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM lifeline_requests WHERE recipient_id = ? AND status = 'failed'");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stats['failed_donations'] = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Count connected donors
$stmt = $conn->prepare("SELECT COUNT(DISTINCT donor_id) as cnt FROM lifeline_requests WHERE recipient_id = ? AND status != 'cancelled'");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stats['connected_donors'] = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Count unread notifications
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM lifeline_notifications WHERE recipient_id = ? AND read_at IS NULL");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stats['unread_notifications'] = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ============================================
// SECTION 3: ACTIVE DONATION REQUESTS
// ============================================
$activeRequests = [];
$stmt = $conn->prepare("
    SELECT lr.*, u.first_name AS donor_first, u.last_name AS donor_last, u.profile_pic AS donor_pic
    FROM lifeline_requests lr
    LEFT JOIN users u ON u.user_id = lr.accepted_donor_id
    WHERE lr.recipient_id = ? AND lr.status IN ('pending', 'open', 'awaiting_confirmation')
    ORDER BY lr.created_at DESC
    LIMIT 5
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $activeRequests[] = $row;
}
$stmt->close();

// ============================================
// SECTION 5: SCHEDULED DONATIONS (Upcoming)
// ============================================
$scheduledDonations = [];
$stmt = $conn->prepare("
    SELECT lr.*, u.first_name AS donor_first, u.last_name AS donor_last, u.profile_pic AS donor_pic
    FROM lifeline_requests lr
    LEFT JOIN users u ON u.user_id = lr.accepted_donor_id
    WHERE lr.recipient_id = ? AND lr.status = 'confirmed' AND lr.preferred_date >= CURDATE()
    ORDER BY lr.preferred_date ASC, lr.preferred_time ASC
    LIMIT 10
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $scheduledDonations[] = $row;
}
$stmt->close();

// ============================================
// SECTION 8: DONATION FEEDBACK - Pending Feedback
// ============================================
$pendingFeedback = [];
$stmt = $conn->prepare("
    SELECT lr.*, u.first_name AS donor_first, u.last_name AS donor_last, u.profile_pic AS donor_pic
    FROM lifeline_requests lr
    LEFT JOIN users u ON u.user_id = lr.accepted_donor_id
    WHERE lr.recipient_id = ? AND lr.status = 'awaiting_feedback' 
      AND lr.preferred_date < CURDATE()
    ORDER BY lr.preferred_date DESC
    LIMIT 5
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $pendingFeedback[] = $row;
}
$stmt->close();

// ============================================
// SECTION 9: HISTORY & REPORTS
// ============================================
$completedDonations = [];
$stmt = $conn->prepare("
    SELECT lr.*, u.first_name AS donor_first, u.last_name AS donor_last, u.profile_pic AS donor_pic
    FROM lifeline_requests lr
    LEFT JOIN users u ON u.user_id = lr.accepted_donor_id
    WHERE lr.recipient_id = ? AND lr.status = 'completed'
    ORDER BY lr.preferred_date DESC
    LIMIT 10
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $completedDonations[] = $row;
}
$stmt->close();

// ============================================
// SECTION 7: NOTIFICATION CENTER
// ============================================
$notifications = [];
$stmt = $conn->prepare("
    SELECT * FROM lifeline_notifications 
    WHERE recipient_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Get online status
$onlineStatus = $profileManager->isUserOnline();

// Update last activity
$profileManager->updateLastActivity();

// Calculate success rate
$totalRequests = $stats['total_successful'] + $stats['failed_donations'];
$successRate = $totalRequests > 0 ? round(($stats['total_successful'] / $totalRequests) * 100) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeLine Panel - Recipient Dashboard</title>
    <?php include('assets/includes/link-css.php'); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #ea062b;
            --secondary: #111111;
            --white: #fff;
            --light: #f5f5f5;
            --gray: #e7e7e7;
            --text: #333;
            --text-muted: #666;
            --success: #4cc9f0;
            --danger: #ea062b;
            --warning: #ffc92e;
            --info: #4895ef;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Jost', sans-serif;
            background: #f9f9f9;
            color: var(--text);
        }

        .lifeline-dashboard {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Dashboard Header */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary), #c0052c);
            color: white;
            padding: 2.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 25px rgba(234, 6, 43, 0.2);
        }

        .dashboard-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .dashboard-header p {
            margin: 0;
            opacity: 0.95;
            font-size: 1rem;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .user-badge, .status-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            backdrop-filter: blur(10px);
            font-size: 0.95rem;
            font-weight: 600;
        }

        .status-online {
            background: #d4edda;
            color: #155724;
        }

        /* Tab Navigation */
        .nav-tabs-custom {
            display: flex;
            gap: 0;
            border-bottom: 2px solid var(--gray);
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .nav-tabs-custom .nav-link {
            background: white;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--text-muted);
            padding: 1rem 1.5rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            border-radius: 0;
        }

        .nav-tabs-custom .nav-link:hover {
            color: var(--primary);
        }

        .nav-tabs-custom .nav-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* Content Sections */
        .tab-content-section {
            display: none;
        }

        .tab-content-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0.5rem 0;
        }

        .stat-card-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        /* Card Styling */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #f9f9f9, #f0f0f0);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 1.2rem;
            margin: 0;
            color: var(--secondary);
            font-weight: 700;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-footer {
            padding: 1rem 1.5rem;
            background: #f9f9f9;
            border-top: 1px solid var(--gray);
            text-align: center;
        }

        /* Request Item */
        .request-item {
            border: 1px solid var(--gray);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .request-item:last-child {
            margin-bottom: 0;
        }

        .request-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateX(4px);
        }

        .request-item.pending {
            border-left-color: var(--warning);
        }

        .request-item.confirmed {
            border-left-color: var(--info);
        }

        .request-item.completed {
            border-left-color: var(--success);
        }

        .request-item.failed {
            border-left-color: var(--danger);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .request-date {
            font-weight: 700;
            color: var(--secondary);
            font-size: 1rem;
        }

        .request-status {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending {
            background: #fff4e6;
            color: #d97706;
        }

        .status-confirmed {
            background: #e0f2fe;
            color: #0369a1;
        }

        .status-completed {
            background: #ecfdf3;
            color: #15803d;
        }

        .status-failed {
            background: #fef2f2;
            color: #b91c1c;
        }

        .request-info {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.5rem 1rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
        }

        .request-info strong {
            color: var(--secondary);
        }

        .donor-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--light);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .donor-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Countdown Timer */
        .countdown-timer {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
            font-size: 0.85rem;
        }

        .countdown-item {
            background: var(--primary);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            text-align: center;
        }

        .countdown-item strong {
            display: block;
            font-size: 1.2rem;
        }

        .countdown-item small {
            display: block;
            font-size: 0.7rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #c0052c;
            transform: translateY(-2px);
        }

        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-small {
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state-text {
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .empty-state-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        /* Notification Item */
        .notification-item {
            border-left: 4px solid var(--info);
            padding: 1rem;
            background: var(--light);
            border-radius: 6px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .notification-item.unread {
            background: #f0f7ff;
            border-left-color: var(--primary);
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 0.25rem;
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }

        .notification-badge {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }

        /* Profile Card */
        .profile-card {
            background: linear-gradient(135deg, rgba(234, 6, 43, 0.1), rgba(192, 5, 44, 0.1));
            border-left: 4px solid var(--primary);
            padding: 1.5rem;
            border-radius: 8px;
        }

        .profile-info-item {
            margin-bottom: 1.5rem;
        }

        .profile-info-item:last-child {
            margin-bottom: 0;
        }

        .profile-info-label {
            font-weight: 700;
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-info-value {
            font-size: 1rem;
            color: var(--text);
        }

        .blood-type-badge {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 700;
            font-size: 1.2rem;
            display: inline-block;
        }

        .progress-bar {
            background: var(--gray);
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            background: var(--primary);
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        /* Feedback Form */
        .feedback-form {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .feedback-options {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }

        .feedback-option {
            flex: 1;
            min-width: 150px;
        }

        .feedback-option input[type="radio"] {
            display: none;
        }

        .feedback-option label {
            display: block;
            padding: 1rem;
            border: 2px solid var(--gray);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .feedback-option input[type="radio"]:checked + label {
            border-color: var(--primary);
            background: rgba(234, 6, 43, 0.1);
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .lifeline-dashboard {
                padding: 1rem;
            }

            .dashboard-header {
                padding: 1.5rem;
            }

            .dashboard-header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .header-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .nav-tabs-custom {
                overflow-x: auto;
                white-space: nowrap;
            }

            .feedback-options {
                flex-direction: column;
            }

            .feedback-option {
                min-width: auto;
            }
        }

        /* Utility Classes */
        .mt-2 { margin-top: 1rem; }
        .mt-4 { margin-top: 2rem; }
        .mb-2 { margin-bottom: 1rem; }
        .mb-4 { margin-bottom: 2rem; }
        .text-center { text-align: center; }
        .text-muted { color: var(--text-muted); }
    </style>
</head>
<body>
    <?php include('assets/includes/header.php'); ?>

    <main class="py-4">
        <div class="lifeline-dashboard">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h1><i class="fas fa-heartbeat"></i> LifeLine Panel - Recipient Dashboard</h1>
                <p>Manage your blood donation needs and track your requests</p>
                <div class="header-info">
                    <div class="user-badge">
                        <i class="fas fa-user-circle"></i> 
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </div>
                    <div class="status-badge <?php echo $onlineStatus ? 'status-online' : ''; ?>">
                        <i class="fas fa-circle"></i> 
                        <?php echo $onlineStatus ? 'Online' : 'Offline'; ?>
                    </div>
                </div>
            </div>

            <!-- SECTION 1: Dashboard Overview - Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-list-check"></i></div>
                    <div class="stat-card-value"><?php echo $stats['active_requests']; ?></div>
                    <div class="stat-card-label">Active Requests</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-card-value"><?php echo $stats['upcoming_confirmed']; ?></div>
                    <div class="stat-card-label">Upcoming Confirmed</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-card-value"><?php echo $stats['pending_confirmations']; ?></div>
                    <div class="stat-card-label">Pending Confirmations</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-card-value"><?php echo $stats['total_successful']; ?></div>
                    <div class="stat-card-label">Successful Donations</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-card-value"><?php echo $stats['connected_donors']; ?></div>
                    <div class="stat-card-label">Connected Donors</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-percent"></i></div>
                    <div class="stat-card-value"><?php echo $successRate; ?>%</div>
                    <div class="stat-card-label">Success Rate</div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="nav-tabs-custom">
                <button class="nav-link active" data-tab="overview">
                    <i class="fas fa-chart-line"></i> Overview
                </button>
                <button class="nav-link" data-tab="profile">
                    <i class="fas fa-id-card"></i> Profile & Verification
                </button>
                <button class="nav-link" data-tab="create-request">
                    <i class="fas fa-plus-circle"></i> Create Request
                </button>
                <button class="nav-link" data-tab="active-requests">
                    <i class="fas fa-clipboard"></i> Active Requests
                </button>
                <button class="nav-link" data-tab="scheduled">
                    <i class="fas fa-calendar-alt"></i> Scheduled Donations
                </button>
                <button class="nav-link" data-tab="notifications">
                    <i class="fas fa-bell"></i> Notifications <span style="background: var(--primary); color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;"><?php echo $stats['unread_notifications']; ?></span>
                </button>
                <button class="nav-link" data-tab="feedback">
                    <i class="fas fa-comment-dots"></i> Feedback
                </button>
                <button class="nav-link" data-tab="history">
                    <i class="fas fa-history"></i> History & Reports
                </button>
                <button class="nav-link" data-tab="support">
                    <i class="fas fa-headset"></i> Support
                </button>
            </div>

            <!-- TAB 1: OVERVIEW -->
            <div id="overview" class="tab-content-section active">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Dashboard Overview</h3>
                    </div>
                    <div class="card-body">
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                            Welcome to your LifeLine Panel. Below you can see a quick overview of your current status, active requests, and upcoming scheduled donations.
                        </p>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                            <!-- Analytics Cards -->
                            <div style="background: var(--light); padding: 1.5rem; border-radius: 8px;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">
                                    <?php echo $stats['total_successful'] + $stats['failed_donations']; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">Total Requests</div>
                            </div>

                            <div style="background: var(--light); padding: 1.5rem; border-radius: 8px;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--success); margin-bottom: 0.5rem;">
                                    <?php echo $stats['total_successful']; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">Successful Completions</div>
                            </div>

                            <div style="background: var(--light); padding: 1.5rem; border-radius: 8px;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--danger); margin-bottom: 0.5rem;">
                                    <?php echo $stats['failed_donations']; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">Failed Donations</div>
                            </div>

                            <div style="background: var(--light); padding: 1.5rem; border-radius: 8px;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--info); margin-bottom: 0.5rem;">
                                    <?php echo $successRate; ?>%
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">Success Rate</div>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--gray);">
                            <h4 style="margin-bottom: 1rem; font-weight: 700;">Quick Actions</h4>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <a href="#create-request" class="btn btn-primary" onclick="switchTab('create-request')">
                                    <i class="fas fa-plus"></i> Create New Request
                                </a>
                                <a href="find-a-donor.php" class="btn btn-outline">
                                    <i class="fas fa-search"></i> Find Donors
                                </a>
                                <a href="lifeline-panel.php" class="btn btn-outline">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: LIFELINE PROFILE & VERIFICATION -->
            <div id="profile" class="tab-content-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-id-card"></i> LifeLine Profile & Verification</h3>
                    </div>
                    <div class="card-body">
                        <div class="profile-card">
                            <div class="profile-info-item">
                                <div class="profile-info-label">Blood Type</div>
                                <div class="blood-type-badge"><?php echo htmlspecialchars($lifelineProfile['blood_type']); ?></div>
                            </div>

                            <div class="profile-info-item">
                                <div class="profile-info-label">Request Frequency</div>
                                <div class="profile-info-value">
                                    <span style="background: var(--info); color: white; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars(ucfirst($lifelineProfile['frequency'])); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="profile-info-item">
                                <div class="profile-info-label">Hospital Preference</div>
                                <div class="profile-info-value">
                                    <?php echo !empty($lifelineProfile['hospital_preference']) ? htmlspecialchars($lifelineProfile['hospital_preference']) : 'Not specified'; ?>
                                </div>
                            </div>

                            <div class="profile-info-item">
                                <div class="profile-info-label">Verified Status</div>
                                <div class="profile-info-value">
                                    <?php if ($lifelineProfile['verified'] == 1): ?>
                                        <span style="color: var(--success); font-weight: 700;">
                                            <i class="fas fa-check-circle"></i> Verified
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--warning); font-weight: 700;">
                                            <i class="fas fa-hourglass-half"></i> Pending Verification
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="profile-info-item">
                                <div class="profile-info-label">Reliability Score</div>
                                <div class="profile-info-value">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo min(100, ($lifelineProfile['reliability_score'] / 5 * 100)); ?>%;"></div>
                                    </div>
                                    <span style="color: var(--primary); font-weight: 700;">
                                        <?php echo number_format($lifelineProfile['reliability_score'], 1); ?>/5.0
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($lifelineProfile['emergency_contacts'])): ?>
                                <div class="profile-info-item">
                                    <div class="profile-info-label">Emergency Contacts on File</div>
                                    <div class="profile-info-value">
                                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Yes
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(234, 6, 43, 0.2);">
                                <a href="lifeline-panel.php?edit=1" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </a>
                            </div>
                        </div>

                        <!-- Medical Verification Info -->
                        <div class="card" style="margin-top: 2rem; border: 2px solid var(--info);">
                            <div class="card-header">
                                <h4><i class="fas fa-stethoscope"></i> Medical Verification</h4>
                            </div>
                            <div class="card-body">
                                <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                    Your medical records have been submitted and verified by our medical team. All required documentation is on file.
                                </p>
                                <button class="btn btn-outline" onclick="alert('Medical records display feature coming soon')">
                                    <i class="fas fa-file-medical"></i> View Medical Records
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: CREATE DONATION REQUEST -->
            <div id="create-request" class="tab-content-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Create New Donation Request</h3>
                    </div>
                    <div class="card-body">
                        <form action="lifeline-create-request.php" method="POST" style="max-width: 600px;">
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Hospital/Clinic Name *</label>
                                <input type="text" name="hospital" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray); border-radius: 6px; font-size: 1rem;">
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">City/Location *</label>
                                <input type="text" name="city" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray); border-radius: 6px; font-size: 1rem;">
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                                <div>
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Preferred Date *</label>
                                    <input type="date" name="preferred_date" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray); border-radius: 6px; font-size: 1rem;">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Preferred Time *</label>
                                    <input type="time" name="preferred_time" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray); border-radius: 6px; font-size: 1rem;">
                                </div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Urgency Level *</label>
                                <select name="urgency" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray); border-radius: 6px; font-size: 1rem;">
                                    <option value="">Select Urgency</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Additional Notes</label>
                                <textarea name="notes" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray); border-radius: 6px; font-size: 1rem; font-family: inherit;"></textarea>
                            </div>

                            <div style="display: flex; gap: 1rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TAB 4: ACTIVE REQUESTS -->
            <div id="active-requests" class="tab-content-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clipboard"></i> Active Donation Requests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($activeRequests)): ?>
                            <?php foreach ($activeRequests as $req): ?>
                                <div class="request-item <?php echo htmlspecialchars($req['status']); ?>">
                                    <div class="request-header">
                                        <div class="request-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M d, Y - H:i', strtotime($req['preferred_date'] . ' ' . $req['preferred_time'])); ?>
                                        </div>
                                        <span class="request-status status-<?php echo htmlspecialchars($req['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($req['status']))); ?>
                                        </span>
                                    </div>

                                    <div class="request-info">
                                        <strong>Hospital:</strong>
                                        <span><?php echo htmlspecialchars($req['hospital']); ?></span>

                                        <strong>Location:</strong>
                                        <span><?php echo htmlspecialchars($req['city']); ?></span>

                                        <strong>Urgency:</strong>
                                        <span style="font-weight: 600; color: <?php echo $req['urgency'] === 'critical' ? '#dc3545' : ($req['urgency'] === 'high' ? '#ffc107' : '#28a745'); ?>;">
                                            <?php echo ucfirst(htmlspecialchars($req['urgency'])); ?>
                                        </span>

                                        <?php if (!empty($req['accepted_donor_id'])): ?>
                                            <strong>Assigned Donor:</strong>
                                            <div class="donor-badge">
                                                <img src="<?php echo !empty($req['donor_pic']) ? htmlspecialchars($req['donor_pic']) : 'assets/images/default-avatar.png'; ?>" alt="Donor" class="donor-avatar">
                                                <span><?php echo htmlspecialchars($req['donor_first'] . ' ' . $req['donor_last']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <strong style="color: var(--warning);">Status:</strong>
                                            <span style="color: var(--warning); font-weight: 600;">
                                                <i class="fas fa-hourglass-half"></i> Waiting for Donor
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="action-buttons">
                                        <?php if ($req['status'] == 'awaiting_confirmation'): ?>
                                            <button class="btn btn-primary btn-small" onclick="confirmDonor(<?php echo $req['id']; ?>)">
                                                <i class="fas fa-check"></i> Confirm Donor
                                            </button>
                                            <button class="btn btn-outline btn-small" onclick="rejectDonor(<?php echo $req['id']; ?>)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!empty($req['accepted_donor_id'])): ?>
                                            <a href="chat.php?donor_id=<?php echo htmlspecialchars($req['accepted_donor_id']); ?>" class="btn btn-primary btn-small">
                                                <i class="fas fa-comments"></i> Chat
                                            </a>
                                        <?php endif; ?>
                                        <a href="lifeline-request-detail.php?id=<?php echo htmlspecialchars($req['id']); ?>" class="btn btn-outline btn-small">
                                            <i class="fas fa-info-circle"></i> Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                                <p class="empty-state-text">No active requests</p>
                                <a href="#create-request" class="empty-state-link" onclick="switchTab('create-request')">Create Your First Request →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 5: SCHEDULED DONATIONS -->
            <div id="scheduled" class="tab-content-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Scheduled Donations</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($scheduledDonations)): ?>
                            <?php foreach ($scheduledDonations as $donation): ?>
                                <div class="request-item confirmed">
                                    <div class="request-header">
                                        <div class="request-date">
                                            <i class="fas fa-calendar-check"></i>
                                            <?php echo date('M d, Y - H:i', strtotime($donation['preferred_date'] . ' ' . $donation['preferred_time'])); ?>
                                        </div>
                                        <span class="request-status status-confirmed">Confirmed</span>
                                    </div>

                                    <!-- Countdown Timer -->
                                    <div class="countdown-timer">
                                        <?php 
                                            $donationTime = strtotime($donation['preferred_date'] . ' ' . $donation['preferred_time']);
                                            $now = time();
                                            $diff = $donationTime - $now;
                                            
                                            if ($diff > 0) {
                                                $days = floor($diff / 86400);
                                                $hours = floor(($diff % 86400) / 3600);
                                                $minutes = floor(($diff % 3600) / 60);
                                        ?>
                                            <div class="countdown-item">
                                                <strong><?php echo $days; ?></strong>
                                                <small>Days</small>
                                            </div>
                                            <div class="countdown-item">
                                                <strong><?php echo $hours; ?></strong>
                                                <small>Hrs</small>
                                            </div>
                                            <div class="countdown-item">
                                                <strong><?php echo $minutes; ?></strong>
                                                <small>Min</small>
                                            </div>
                                        <?php } ?>
                                    </div>

                                    <div class="request-info" style="margin-top: 1rem;">
                                        <strong>Hospital:</strong>
                                        <span><?php echo htmlspecialchars($donation['hospital']); ?></span>

                                        <strong>Location:</strong>
                                        <span><?php echo htmlspecialchars($donation['city']); ?></span>

                                        <strong>Donor:</strong>
                                        <div class="donor-badge">
                                            <img src="<?php echo !empty($donation['donor_pic']) ? htmlspecialchars($donation['donor_pic']) : 'assets/images/default-avatar.png'; ?>" alt="Donor" class="donor-avatar">
                                            <span><?php echo htmlspecialchars($donation['donor_first'] . ' ' . $donation['donor_last']); ?></span>
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        <a href="chat.php?donor_id=<?php echo htmlspecialchars($donation['accepted_donor_id']); ?>" class="btn btn-primary btn-small">
                                            <i class="fas fa-comments"></i> Chat with Donor
                                        </a>
                                        <button class="btn btn-outline btn-small" onclick="reschedule(<?php echo $donation['id']; ?>)">
                                            <i class="fas fa-sync-alt"></i> Reschedule
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-calendar-plus"></i></div>
                                <p class="empty-state-text">No scheduled donations yet</p>
                                <a href="#create-request" class="empty-state-link" onclick="switchTab('create-request')">Create a Request →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 6: NOTIFICATION CENTER -->
            <div id="notifications" class="tab-content-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bell"></i> Notification Center</h3>
                        <span style="font-size: 0.9rem; color: var(--text-muted);">
                            You must respond to confirmation prompts within 12 hours
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notification-item <?php echo is_null($notif['read_at']) ? 'unread' : ''; ?>">
                                    <div class="notification-content">
                                        <div class="notification-title">
                                            <?php echo htmlspecialchars($notif['title']); ?>
                                            <?php if (is_null($notif['read_at'])): ?>
                                                <span class="notification-badge">NEW</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-message">
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                        </div>
                                        <div class="notification-time">
                                            <i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($notif['type'] == 'confirmation_required'): ?>
                                            <button class="btn btn-primary btn-small" onclick="handleNotificationAction(<?php echo $notif['id']; ?>, 'confirm')">
                                                <i class="fas fa-check"></i> Confirm
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-bell-slash"></i></div>
                                <p class="empty-state-text">No notifications at this time</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 7: DONATION FEEDBACK -->
            <div id="feedback" class="tab-content-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-comment-dots"></i> Donation Feedback</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pendingFeedback)): ?>
                            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                                The system is asking for your feedback on the following donations that were scheduled:
                            </p>

                            <?php foreach ($pendingFeedback as $feedback): ?>
                                <div class="request-item" style="border-left-color: var(--warning);">
                                    <div class="request-header">
                                        <div class="request-date">
                                            <i class="fas fa-calendar-check"></i>
                                            <?php echo date('M d, Y - H:i', strtotime($feedback['preferred_date'] . ' ' . $feedback['preferred_time'])); ?>
                                        </div>
                                    </div>

                                    <div class="request-info">
                                        <strong>Hospital:</strong>
                                        <span><?php echo htmlspecialchars($feedback['hospital']); ?></span>

                                        <strong>Donor:</strong>
                                        <div class="donor-badge">
                                            <img src="<?php echo !empty($feedback['donor_pic']) ? htmlspecialchars($feedback['donor_pic']) : 'assets/images/default-avatar.png'; ?>" alt="Donor" class="donor-avatar">
                                            <span><?php echo htmlspecialchars($feedback['donor_first'] . ' ' . $feedback['donor_last']); ?></span>
                                        </div>
                                    </div>

                                    <div class="feedback-form">
                                        <p style="font-weight: 600; margin-bottom: 1rem;">
                                            <i class="fas fa-question-circle"></i> Did you receive the blood donation?
                                        </p>

                                        <form action="lifeline-submit-feedback.php" method="POST">
                                            <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($feedback['id']); ?>">

                                            <div class="feedback-options">
                                                <div class="feedback-option">
                                                    <input type="radio" id="yes_<?php echo $feedback['id']; ?>" name="feedback" value="yes" required>
                                                    <label for="yes_<?php echo $feedback['id']; ?>">
                                                        <i class="fas fa-check-circle"></i><br>
                                                        Yes
                                                    </label>
                                                </div>

                                                <div class="feedback-option">
                                                    <input type="radio" id="no_<?php echo $feedback['id']; ?>" name="feedback" value="no" required>
                                                    <label for="no_<?php echo $feedback['id']; ?>">
                                                        <i class="fas fa-times-circle"></i><br>
                                                        No
                                                    </label>
                                                </div>

                                                <div class="feedback-option">
                                                    <input type="radio" id="reschedule_<?php echo $feedback['id']; ?>" name="feedback" value="reschedule" required>
                                                    <label for="reschedule_<?php echo $feedback['id']; ?>">
                                                        <i class="fas fa-sync-alt"></i><br>
                                                        Reschedule
                                                    </label>
                                                </div>
                                            </div>

                                            <div style="margin-top: 1rem; display: flex; gap: 0.75rem;">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane"></i> Submit Feedback
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-smile"></i></div>
                                <p class="empty-state-text">No pending feedback at this time</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 8: HISTORY & REPORTS -->
            <div id="history" class="tab-content-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Donation History & Reports</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($completedDonations)): ?>
                            <div style="margin-bottom: 2rem;">
                                <h4 style="margin-bottom: 1rem; font-weight: 700;">Completed Donations</h4>
                                
                                <?php foreach ($completedDonations as $donation): ?>
                                    <div class="request-item completed">
                                        <div class="request-header">
                                            <div class="request-date">
                                                <i class="fas fa-calendar-check"></i>
                                                <?php echo date('M d, Y - H:i', strtotime($donation['preferred_date'] . ' ' . $donation['preferred_time'])); ?>
                                            </div>
                                            <span class="request-status status-completed">Completed</span>
                                        </div>

                                        <div class="request-info">
                                            <strong>Hospital:</strong>
                                            <span><?php echo htmlspecialchars($donation['hospital']); ?></span>

                                            <strong>Donor:</strong>
                                            <div class="donor-badge">
                                                <img src="<?php echo !empty($donation['donor_pic']) ? htmlspecialchars($donation['donor_pic']) : 'assets/images/default-avatar.png'; ?>" alt="Donor" class="donor-avatar">
                                                <span><?php echo htmlspecialchars($donation['donor_first'] . ' ' . $donation['donor_last']); ?></span>
                                            </div>
                                        </div>

                                        <div class="action-buttons">
                                            <a href="chat.php?donor_id=<?php echo htmlspecialchars($donation['accepted_donor_id']); ?>" class="btn btn-primary btn-small">
                                                <i class="fas fa-comments"></i> Chat
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                                <p class="empty-state-text">No completed donations yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Analytics & Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem;">
                            <div style="background: var(--light); padding: 1.5rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--success); margin-bottom: 0.5rem;">
                                    <?php echo $stats['total_successful']; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">Successful Donations</div>
                            </div>

                            <div style="background: var(--light); padding: 1.5rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--danger); margin-bottom: 0.5rem;">
                                    <?php echo $stats['failed_donations']; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">Failed Donations</div>
                            </div>

                            <div style="background: var(--light); padding: 1.5rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--info); margin-bottom: 0.5rem;">
                                    <?php echo $successRate; ?>%
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">Success Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 9: SUPPORT & MEDICAL VERIFICATION -->
            <div id="support" class="tab-content-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-headset"></i> Support & Medical Verification</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                            <!-- Support Ticket -->
                            <div style="border: 2px solid var(--info); border-radius: 8px; padding: 1.5rem;">
                                <h4 style="color: var(--info); margin-bottom: 1rem; font-weight: 700;">
                                    <i class="fas fa-life-ring"></i> Contact Support
                                </h4>
                                <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                    Need help? Our support team is here to assist you with any questions or issues.
                                </p>
                                <a href="support.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-envelope"></i> Open Support Ticket
                                </a>
                            </div>

                            <!-- Medical Verification -->
                            <div style="border: 2px solid var(--success); border-radius: 8px; padding: 1.5rem;">
                                <h4 style="color: var(--success); margin-bottom: 1rem; font-weight: 700;">
                                    <i class="fas fa-stethoscope"></i> Medical Verification
                                </h4>
                                <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                    Submit or update your medical documents for verification.
                                </p>
                                <button class="btn btn-primary" style="width: 100%; justify-content: center;" onclick="alert('Medical verification upload feature coming soon')">
                                    <i class="fas fa-file-upload"></i> Upload Documents
                                </button>
                            </div>

                            <!-- Hospital Coordination -->
                            <div style="border: 2px solid var(--primary); border-radius: 8px; padding: 1.5rem;">
                                <h4 style="color: var(--primary); margin-bottom: 1rem; font-weight: 700;">
                                    <i class="fas fa-hospital"></i> Hospital Coordination
                                </h4>
                                <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                    Coordinate with hospitals and manage your medical records.
                                </p>
                                <button class="btn btn-primary" style="width: 100%; justify-content: center;" onclick="alert('Hospital coordination panel coming soon')">
                                    <i class="fas fa-hospital-user"></i> View Hospitals
                                </button>
                            </div>
                        </div>

                        <!-- FAQ Section -->
                        <div style="margin-top: 2rem; padding: 2rem; background: var(--light); border-radius: 8px;">
                            <h4 style="margin-bottom: 1rem; font-weight: 700;">
                                <i class="fas fa-question-circle"></i> Frequently Asked Questions
                            </h4>
                            <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                Have questions about the LifeLine program? Check our FAQ section for answers.
                            </p>
                            <a href="faq.php" class="btn btn-outline">
                                <i class="fas fa-book"></i> View FAQ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include('assets/includes/footer.php'); ?>

    <script>
        // Tab Switching
        function switchTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content-section');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Show selected tab
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }

            // Update nav links
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => link.classList.remove('active'));
            event.target.classList.add('active');
        }

        // Tab Button Event Listeners
        document.querySelectorAll('.nav-link').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const tabName = this.getAttribute('data-tab');
                switchTab(tabName);
            });
        });

        // Confirm Donor
        function confirmDonor(requestId) {
            if (confirm('Confirm that you have agreed with this donor?')) {
                // Submit confirmation
                fetch('lifeline-confirm-donor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ request_id: requestId })
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          alert('Donor confirmed successfully!');
                          location.reload();
                      }
                  });
            }
        }

        // Reject Donor
        function rejectDonor(requestId) {
            if (confirm('Are you sure you want to reject this donor?')) {
                fetch('lifeline-reject-donor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ request_id: requestId })
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          alert('Donor rejected. Request will remain open for other donors.');
                          location.reload();
                      }
                  });
            }
        }

        // Reschedule
        function reschedule(requestId) {
            const newDate = prompt('Enter new date (YYYY-MM-DD):');
            if (newDate) {
                const newTime = prompt('Enter new time (HH:MM):');
                if (newTime) {
                    fetch('lifeline-reschedule.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ request_id: requestId, date: newDate, time: newTime })
                    }).then(response => response.json())
                      .then(data => {
                          if (data.success) {
                              alert('Donation rescheduled successfully!');
                              location.reload();
                          }
                      });
                }
            }
        }

        // Handle Notification Action
        function handleNotificationAction(notificationId, action) {
            fetch('lifeline-handle-notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId, action: action })
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      location.reload();
                  }
              });
        }
    </script>
</body>
</html>
