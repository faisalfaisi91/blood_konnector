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
$profileStmt = $conn->prepare("SELECT * FROM emergency_profiles WHERE recipient_id = ? LIMIT 1");
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
    header('Location: recipient-profile.php');
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

// Fetch upcoming blood requests (next 30 days)
$upcomingRequests = [];
$upcomingStmt = $conn->prepare("
    SELECT er.*, ec.donor_id, u.first_name AS donor_first, u.last_name AS donor_last, u.profile_pic AS donor_pic
    FROM emergency_requests er
    LEFT JOIN emergency_confirmations ec ON ec.request_id = er.id
    LEFT JOIN users u ON u.user_id = ec.donor_id
    WHERE er.recipient_id = ? 
    AND er.status IN ('pending', 'confirmed')
    AND er.preferred_date >= CURDATE()
    AND er.preferred_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY er.preferred_date ASC, er.preferred_time ASC
    LIMIT 10
");
$upcomingStmt->bind_param("s", $userId);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();
while ($row = $upcomingResult->fetch_assoc()) {
    $upcomingRequests[] = $row;
}
$upcomingStmt->close();

// Fetch past blood requests (completed/failed)
$pastRequests = [];
$pastStmt = $conn->prepare("
    SELECT er.*, ec.donor_id, ec.scheduled_at, u.first_name AS donor_first, u.last_name AS donor_last, u.profile_pic AS donor_pic
    FROM emergency_requests er
    LEFT JOIN emergency_confirmations ec ON ec.request_id = er.id
    LEFT JOIN users u ON u.user_id = ec.donor_id
    WHERE er.recipient_id = ? 
    AND er.status IN ('completed', 'failed')
    ORDER BY er.preferred_date DESC
    LIMIT 10
");
$pastStmt->bind_param("s", $userId);
$pastStmt->execute();
$pastResult = $pastStmt->get_result();
while ($row = $pastResult->fetch_assoc()) {
    $pastRequests[] = $row;
}
$pastStmt->close();

// Fetch connected donors
$connectedDonors = [];
$donorStmt = $conn->prepare("
    SELECT el.*, u.first_name, u.last_name, u.profile_pic, d.blood_type, d.location
    FROM emergency_links el
    JOIN users u ON u.user_id = el.donor_id
    LEFT JOIN donors d ON d.user_id = el.donor_id
    WHERE el.recipient_id = ? 
    AND el.status = 'active'
    ORDER BY el.last_donation_at DESC
    LIMIT 15
");
$donorStmt->bind_param("s", $userId);
$donorStmt->execute();
$donorResult = $donorStmt->get_result();
while ($row = $donorResult->fetch_assoc()) {
    $connectedDonors[] = $row;
}
$donorStmt->close();

// Fetch statistics
$stats = [
    'total_requests' => 0,
    'completed_donations' => 0,
    'failed_donations' => 0,
    'connected_donors' => count($connectedDonors),
    'pending_requests' => 0
];

// Count total requests
$countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM emergency_requests WHERE recipient_id = ?");
$countStmt->bind_param("s", $userId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$stats['total_requests'] = $countResult->fetch_assoc()['cnt'];
$countStmt->close();

// Count completed
$completedStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM emergency_requests WHERE recipient_id = ? AND status = 'completed'");
$completedStmt->bind_param("s", $userId);
$completedStmt->execute();
$completedResult = $completedStmt->get_result();
$stats['completed_donations'] = $completedResult->fetch_assoc()['cnt'];
$completedStmt->close();

// Count failed
$failedStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM emergency_requests WHERE recipient_id = ? AND status = 'failed'");
$failedStmt->bind_param("s", $userId);
$failedStmt->execute();
$failedResult = $failedStmt->get_result();
$stats['failed_donations'] = $failedResult->fetch_assoc()['cnt'];
$failedStmt->close();

// Count pending
$pendingStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM emergency_requests WHERE recipient_id = ? AND status IN ('pending', 'confirmed')");
$pendingStmt->bind_param("s", $userId);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$stats['pending_requests'] = $pendingResult->fetch_assoc()['cnt'];
$pendingStmt->close();

// Get online status
$onlineStatus = $profileManager->isUserOnline();

// Update last activity
$profileManager->updateLastActivity();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeLine Dashboard - Recipient</title>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        :root {
            --primary-color: #ea062b;
            --secondary-color: #111111;
            --white: #fff;
            --gray: #e7e7e7;
            --gray1: #f5f5f5;
            --gray2: #f1f1f1;
            --yellow: #ffc92e;
            --p-color: #666666;
            --border-color: #cacaca;
            --shadow: 0px 0px 20px #0000002b;
            --gray_bg: #f9f9f9;
            --sidebar-color: #f9f9f9;
            --transition: all 0.3s ease-in;
            --font: "Jost", sans-serif;
            --transition_base: 0.3s;
            --success-color: #4cc9f0;
            --danger-color: #ea062b;
            --warning-color: #ffc92e;
            --info-color: #4895ef;
        }

        body {
            font-family: var(--font);
            background: var(--gray_bg);
            color: var(--secondary-color);
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), #c0052c);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .dashboard-header p {
            margin: 0;
            opacity: 0.95;
            font-size: 1.1rem;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.5rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            font-size: 0.95rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.online {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.offline {
            background: #f8d7da;
            color: #721c24;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0px 10px 30px #0000003b;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0.5rem 0;
        }

        .stat-card-label {
            font-size: 0.9rem;
            color: var(--p-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.7;
        }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Sections */
        .section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray1);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin: 0;
        }

        .view-all-btn {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: opacity 0.3s ease;
        }

        .view-all-btn:hover {
            opacity: 0.7;
        }

        /* Request Cards */
        .request-card {
            border: 1px solid var(--gray);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .request-card:last-child {
            margin-bottom: 0;
        }

        .request-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .request-card.pending {
            border-left-color: var(--warning-color);
        }

        .request-card.confirmed {
            border-left-color: var(--info-color);
        }

        .request-card.completed {
            border-left-color: var(--success-color);
        }

        .request-card.failed {
            border-left-color: var(--danger-color);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .request-date {
            font-weight: 700;
            color: var(--secondary-color);
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

        .request-status.pending {
            background: #fff4e6;
            color: #d97706;
        }

        .request-status.confirmed {
            background: #e0f2fe;
            color: #0369a1;
        }

        .request-status.completed {
            background: #ecfdf3;
            color: #15803d;
        }

        .request-status.failed {
            background: #fef2f2;
            color: #b91c1c;
        }

        .request-details {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.75rem 1rem;
            font-size: 0.9rem;
            color: var(--p-color);
            margin-bottom: 0.75rem;
        }

        .request-details strong {
            color: var(--secondary-color);
        }

        .donor-info-badge {
            background: var(--gray1);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .donor-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }

        /* Donor Cards */
        .donor-card {
            background: var(--gray1);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .donor-card:last-child {
            margin-bottom: 0;
        }

        .donor-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }

        .donor-card-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            margin: 0 auto 0.75rem;
            display: block;
        }

        .donor-card-name {
            font-weight: 700;
            color: var(--secondary-color);
            font-size: 0.95rem;
            margin: 0.5rem 0;
        }

        .donor-card-info {
            font-size: 0.8rem;
            color: var(--p-color);
            margin: 0.25rem 0;
        }

        .donor-card-badge {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--p-color);
        }

        .empty-state-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.5;
        }

        .empty-state-text {
            font-size: 0.95rem;
            margin: 0;
        }

        .empty-state-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            margin-top: 0.5rem;
            display: inline-block;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
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
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #c0052c;
            transform: translateY(-2px);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-small {
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }

            .dashboard-header {
                padding: 1.5rem;
            }

            .dashboard-header h1 {
                font-size: 1.8rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .header-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .section {
                padding: 1rem;
            }

            .section-title {
                font-size: 1.1rem;
            }
        }
    </style>
    <?php include('assets/includes/link-js.php'); ?>
</head>
<body>
    <!-- Header -->
    <?php include('assets/includes/header.php'); ?>

    <!-- Main Content -->
    <main class="py-4">
        <div class="dashboard-container">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h1><i class="fas fa-heartbeat"></i> LifeLine Dashboard</h1>
                <p>Your Personal Blood Donation Management System</p>
                
                <div class="header-info">
                    <div>
                        <div class="user-badge">
                            <i class="fas fa-user-circle"></i> 
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </div>
                    </div>
                    <div>
                        <span class="status-badge <?php echo $onlineStatus ? 'online' : 'offline'; ?>">
                            <i class="fas fa-circle"></i> 
                            <?php echo $onlineStatus ? 'Online' : 'Offline'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-list-check"></i></div>
                    <div class="stat-card-value"><?php echo $stats['total_requests']; ?></div>
                    <div class="stat-card-label">Total Requests</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-card-value"><?php echo $stats['completed_donations']; ?></div>
                    <div class="stat-card-label">Completed</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-card-value"><?php echo $stats['pending_requests']; ?></div>
                    <div class="stat-card-label">Pending</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-card-value"><?php echo $stats['connected_donors']; ?></div>
                    <div class="stat-card-label">Connected Donors</div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Left Column: Requests -->
                <div>
                    <!-- Upcoming Requests Section -->
                    <div class="section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-calendar-check"></i> Upcoming Donations
                            </h2>
                            <a href="emergency-recipient" class="view-all-btn">View All →</a>
                        </div>

                        <?php if (!empty($upcomingRequests)): ?>
                            <?php foreach ($upcomingRequests as $req): ?>
                                <div class="request-card <?php echo htmlspecialchars($req['status']); ?>">
                                    <div class="request-header">
                                        <div class="request-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M d, Y - H:i', strtotime($req['preferred_date'] . ' ' . $req['preferred_time'])); ?>
                                        </div>
                                        <span class="request-status <?php echo htmlspecialchars($req['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($req['status'])); ?>
                                        </span>
                                    </div>

                                    <div class="request-details">
                                        <strong>Location:</strong>
                                        <span><?php echo htmlspecialchars($req['location']); ?></span>

                                        <strong>Urgency:</strong>
                                        <span class="badge" style="background: var(--<?php echo $req['urgency'] === 'critical' ? 'danger' : ($req['urgency'] === 'high' ? 'warning' : 'info'); ?>-color); color: white; padding: 0.25rem 0.75rem; border-radius: 4px;">
                                            <?php echo ucfirst(htmlspecialchars($req['urgency'])); ?>
                                        </span>

                                        <?php if (!empty($req['donor_id'])): ?>
                                            <strong>Assigned Donor:</strong>
                                            <div class="donor-info-badge">
                                                <img src="<?php echo !empty($req['donor_pic']) ? htmlspecialchars($req['donor_pic']) : 'assets/images/default-avatar.png'; ?>" alt="Donor" class="donor-avatar">
                                                <span><?php echo htmlspecialchars($req['donor_first'] . ' ' . $req['donor_last']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <strong>Status:</strong>
                                            <span style="color: var(--warning-color); font-weight: 600;">Waiting for Donor</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($req['note'])): ?>
                                        <div style="background: var(--gray1); padding: 0.75rem; border-radius: 6px; margin-top: 0.75rem; font-size: 0.85rem;">
                                            <strong>Note:</strong> <?php echo htmlspecialchars($req['note']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="action-buttons">
                                        <?php if (!empty($req['donor_id'])): ?>
                                            <a href="chat?id=<?php echo htmlspecialchars($req['donor_id']); ?>" class="btn btn-primary btn-small">
                                                <i class="fas fa-comments"></i> Chat
                                            </a>
                                        <?php endif; ?>
                                        <a href="emergency-recipient" class="btn btn-outline-primary btn-small">
                                            <i class="fas fa-info-circle"></i> Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-calendar-plus"></i></div>
                                <p class="empty-state-text">No upcoming donations scheduled</p>
                                <a href="emergency-recipient" class="empty-state-link">Create New Request →</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Past Requests Section -->
                    <div class="section" style="margin-top: 2rem;">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-history"></i> Past Donations
                            </h2>
                            <a href="emergency-recipient" class="view-all-btn">View All →</a>
                        </div>

                        <?php if (!empty($pastRequests)): ?>
                            <?php foreach ($pastRequests as $req): ?>
                                <div class="request-card <?php echo htmlspecialchars($req['status']); ?>">
                                    <div class="request-header">
                                        <div class="request-date">
                                            <i class="fas fa-calendar-check"></i>
                                            <?php echo date('M d, Y', strtotime($req['preferred_date'])); ?>
                                        </div>
                                        <span class="request-status <?php echo htmlspecialchars($req['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($req['status'])); ?>
                                        </span>
                                    </div>

                                    <div class="request-details">
                                        <strong>Location:</strong>
                                        <span><?php echo htmlspecialchars($req['location']); ?></span>

                                        <?php if (!empty($req['donor_id'])): ?>
                                            <strong>Donor:</strong>
                                            <div class="donor-info-badge">
                                                <img src="<?php echo !empty($req['donor_pic']) ? htmlspecialchars($req['donor_pic']) : 'assets/images/default-avatar.png'; ?>" alt="Donor" class="donor-avatar">
                                                <span><?php echo htmlspecialchars($req['donor_first'] . ' ' . $req['donor_last']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="action-buttons">
                                        <?php if (!empty($req['donor_id'])): ?>
                                            <a href="chat?id=<?php echo htmlspecialchars($req['donor_id']); ?>" class="btn btn-primary btn-small">
                                                <i class="fas fa-comments"></i> Chat
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-box-open"></i></div>
                                <p class="empty-state-text">No past donations yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Connected Donors -->
                <div>
                    <div class="section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-heart"></i> Connected Donors
                            </h2>
                            <a href="find-a-donor" class="view-all-btn">Add More →</a>
                        </div>

                        <?php if (!empty($connectedDonors)): ?>
                            <?php foreach ($connectedDonors as $donor): ?>
                                <div class="donor-card">
                                    <img src="<?php echo !empty($donor['profile_pic']) ? htmlspecialchars($donor['profile_pic']) : 'assets/images/default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($donor['first_name']); ?>" class="donor-card-img">
                                    
                                    <div class="donor-card-name">
                                        <?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?>
                                    </div>
                                    
                                    <div class="donor-card-info">
                                        <i class="fas fa-droplet" style="color: var(--primary-color);"></i>
                                        <?php echo htmlspecialchars($donor['blood_type']); ?>
                                    </div>
                                    
                                    <div class="donor-card-info">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($donor['location']); ?>
                                    </div>

                                    <?php if (!empty($donor['last_donation_at'])): ?>
                                        <div class="donor-card-info" style="font-size: 0.75rem; color: var(--p-color);">
                                            Last donation: <?php echo date('M d, Y', strtotime($donor['last_donation_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="chat?id=<?php echo htmlspecialchars($donor['donor_id']); ?>" class="donor-card-badge">
                                        <i class="fas fa-comment"></i> Contact
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-users"></i></div>
                                <p class="empty-state-text">No connected donors yet</p>
                                <a href="find-a-donor" class="empty-state-link">Find Donors →</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- LifeLine Profile Info -->
                    <div class="section" style="margin-top: 2rem; background: linear-gradient(135deg, rgba(234, 6, 43, 0.1), rgba(192, 5, 44, 0.1)); border-left: 4px solid var(--primary-color);">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-info-circle"></i> LifeLine Info
                            </h2>
                        </div>

                        <div style="font-size: 0.9rem; color: var(--p-color);">
                            <p style="margin: 0.5rem 0;">
                                <strong>Blood Type:</strong><br>
                                <span style="font-size: 1.1rem; color: var(--primary-color); font-weight: 700;">
                                    <?php echo htmlspecialchars($lifelineProfile['blood_type']); ?>
                                </span>
                            </p>

                            <p style="margin: 1rem 0 0.5rem;">
                                <strong>Request Frequency:</strong><br>
                                <span class="badge" style="background: var(--info-color); color: white; padding: 0.35rem 0.75rem; border-radius: 4px;">
                                    <?php echo ucfirst(htmlspecialchars($lifelineProfile['frequency'])); ?>
                                </span>
                            </p>

                            <?php if (!empty($lifelineProfile['hospital_preference'])): ?>
                                <p style="margin: 1rem 0 0.5rem;">
                                    <strong>Hospital Preference:</strong><br>
                                    <?php echo htmlspecialchars($lifelineProfile['hospital_preference']); ?>
                                </p>
                            <?php endif; ?>

                            <p style="margin: 1rem 0 0.5rem;">
                                <strong>Reliability Score:</strong><br>
                                <div style="background: var(--gray1); border-radius: 4px; height: 6px; overflow: hidden; margin-top: 0.5rem;">
                                    <div style="background: var(--primary-color); height: 100%; width: <?php echo min(100, ($lifelineProfile['reliability_score'] / 5 * 100)); ?>%;"></div>
                                </div>
                                <span style="font-size: 0.85rem; color: var(--primary-color); font-weight: 600;">
                                    <?php echo number_format($lifelineProfile['reliability_score'], 1); ?>/5.0
                                </span>
                            </p>

                            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(234, 6, 43, 0.2);">
                                <a href="donor-profile" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include('assets/includes/footer.php'); ?>

    <!-- Scripts -->
    <?php include('assets/includes/link-js.php'); ?>
</body>
</html>
