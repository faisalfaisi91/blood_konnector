<?php
session_start();
include('assets/lib/openconn.php');

// Check if user is logged in as donor
if (!isset($_SESSION['user_id']) || !is_donor($_SESSION['user_id'])) {
    header("Location: sign-in");
    exit();
}

$donor_id = $_SESSION['user_id'];

// Update last activity
$currentTime = date('Y-m-d H:i:s');
$updateQuery = "UPDATE users SET last_activity = ? WHERE user_id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("si", $currentTime, $donor_id);
$stmt->execute();

// Get donor details
$donor_query = "SELECT d.*, u.email FROM donors d 
                JOIN users u ON d.user_id = u.user_id 
                WHERE d.user_id = ?";
$stmt = $conn->prepare($donor_query);
$stmt->bind_param("s", $donor_id);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();

// Get unread message count
$message_query = "SELECT COUNT(*) AS unread FROM messages 
                 WHERE recipient_id = ? AND is_read = 0";
$stmt = $conn->prepare($message_query);
$stmt->bind_param("s", $donor_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread'];

// Profile display name (full_name or first_name + last_name)
$display_name = trim($donor['full_name'] ?? '') ?: trim(($donor['first_name'] ?? '') . ' ' . ($donor['last_name'] ?? '')) ?: 'Donor';
$contact_display = $donor['contact_number'] ?? $donor['phone_number'] ?? 'Not provided';
$location_display = $donor['location'] ?? $donor['city'] ?? 'Not specified';

// Active logic: donor is INACTIVE if donated within 4 months
$last_donation = !empty($donor['last_donation_date']) ? strtotime($donor['last_donation_date']) : null;
$four_months_ago = strtotime('-4 months');
$is_active_for_donation = ($last_donation === null || $last_donation <= $four_months_ago);
$status_label = $is_active_for_donation ? 'Active' : 'Inactive (donated within 4 months)';

// Next donation date (4 months from last donation)
$next_donation_date = null;
if ($last_donation) {
    $next_donation_date = strtotime('+4 months', $last_donation);
}

// Total donations count (from blood_donations if table exists)
$total_donations = 0;
$donation_history = [];
$check_table = @$conn->query("SHOW TABLES LIKE 'blood_donations'");
if ($check_table && $check_table->num_rows > 0) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM blood_donations WHERE donor_id = ? AND status = 'completed'");
    if ($count_stmt) {
        $count_stmt->bind_param("s", $donor_id);
        $count_stmt->execute();
        $row = $count_stmt->get_result()->fetch_assoc();
        $total_donations = (int)($row['cnt'] ?? 0);
        $count_stmt->close();
    }
    $hist_stmt = $conn->prepare("SELECT * FROM blood_donations WHERE donor_id = ? ORDER BY donation_date DESC, created_at DESC LIMIT 10");
    if ($hist_stmt) {
        $hist_stmt->bind_param("s", $donor_id);
        $hist_stmt->execute();
        $hist_res = $hist_stmt->get_result();
        while ($r = $hist_res->fetch_assoc()) $donation_history[] = $r;
        $hist_stmt->close();
    }
}

// Donor availability toggle (is_available: 1=visible, 0=deactivated)
$is_available = isset($donor['is_available']) ? (int)$donor['is_available'] : 1;
$emergency_avail = ($donor['emergency_availability'] ?? $donor['emergency_contact'] ?? 'no') === 'yes';

// Pending donor confirmations (scheduled donations awaiting donor to confirm date)
$pending_confirmations = [];
$completion_asks = [];
$chk = @$conn->query("SHOW TABLES LIKE 'blood_donations'");
if ($chk && $chk->num_rows > 0) {
    $colChk = @$conn->query("SHOW COLUMNS FROM blood_donations LIKE 'donor_confirmed'");
    if ($colChk && $colChk->num_rows > 0) {
        $q = $conn->prepare("SELECT bd.*, u.first_name AS recipient_first, u.last_name AS recipient_last FROM blood_donations bd JOIN users u ON u.user_id = bd.recipient_id WHERE bd.donor_id = ? AND bd.status = 'scheduled' AND COALESCE(bd.donor_confirmed, 0) = 0");
        $q->bind_param("s", $donor_id);
        $q->execute();
        $res = $q->get_result();
        while ($r = $res->fetch_assoc()) $pending_confirmations[] = $r;
        $q->close();
    }
    $colChk2 = @$conn->query("SHOW COLUMNS FROM blood_donations LIKE 'completion_asked_at'");
    if ($colChk2 && $colChk2->num_rows > 0) {
        $q2 = $conn->prepare("SELECT bd.*, u.first_name AS recipient_first, u.last_name AS recipient_last FROM blood_donations bd JOIN users u ON u.user_id = bd.recipient_id WHERE bd.donor_id = ? AND bd.status = 'scheduled' AND bd.completion_asked_at IS NOT NULL");
        $q2->bind_param("s", $donor_id);
        $q2->execute();
        $res2 = $q2->get_result();
        while ($r = $res2->fetch_assoc()) $completion_asks[] = $r;
        $q2->close();
    }
}

// Get blood type
$blood_type = $donor['blood_type'] ?? 'Not specified';

// Debug: Log the blood type
// echo "<!-- Debug: Blood type: $blood_type -->";

// Get pending emergency requests (if any) - check both emergency_requests and recipients tables
$pending_emergencies = 0;
if (!empty($blood_type) && $blood_type !== 'Not specified') {
    // First try counting from emergency_requests table
    $emergency_query = "SELECT COUNT(*) AS pending FROM emergency_requests 
                       WHERE status = 'pending' AND blood_type = ?";
    $stmt = $conn->prepare($emergency_query);
    $stmt->bind_param("s", $blood_type);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $pending_emergencies = $count_result ? $count_result->fetch_assoc()['pending'] : 0;
    $stmt->close();
    
    // If not found, try counting from recipients table (fallback)
    if ($pending_emergencies == 0) {
        $donorLocation = $donor['location'] ?? $donor['city'] ?? '';
        $emergency_query2 = "SELECT COUNT(DISTINCT lr.id) AS pending FROM emergency_requests lr
                            LEFT JOIN recipients r ON r.user_id = lr.recipient_id
                            WHERE lr.status = 'pending' AND r.blood_type = ?";
        $stmt = $conn->prepare($emergency_query2);
        $stmt->bind_param("s", $blood_type);
        $stmt->execute();
        $count_result = $stmt->get_result();
        $pending_emergencies = $count_result ? $count_result->fetch_assoc()['pending'] : 0;
        $stmt->close();
    }
}

// Get list of available matching requests for this donor
$availableRequests = [];
if (!empty($blood_type) && $blood_type !== 'Not specified') {
    // Get donor's location for matching (needed for all query paths)
    $donorLocation = $donor['location'] ?? $donor['city'] ?? '';
    
    // Try to fetch available matching requests
    // This follows the same logic as emergency-donor.php
    
    // First, check if blood_type and city columns exist in emergency_requests
    $checkCols = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'emergency_requests' AND COLUMN_NAME IN ('blood_type', 'city')");
    $hasBloodType = false;
    $hasCity = false;
    if ($checkCols) {
        while ($col = $checkCols->fetch_assoc()) {
            if ($col['COLUMN_NAME'] === 'blood_type') $hasBloodType = true;
            if ($col['COLUMN_NAME'] === 'city') $hasCity = true;
        }
    }
    
    if ($hasBloodType && $hasCity) {
        // Match by blood_type and city from request table
        $available_query = "SELECT lr.*, u.first_name AS recipient_first, u.last_name AS recipient_last,
                                   lc.donor_id, lc.donor_response
                           FROM emergency_requests lr
                           LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id
                           JOIN users u ON u.user_id = lr.recipient_id
                           WHERE lr.status = 'pending'
                             AND lr.blood_type = ?
                             AND (lr.city = ? OR LOWER(lr.city) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', lr.city, '%')))
                             AND (lc.donor_id IS NULL OR lc.donor_id != ?)
                             AND lr.id NOT IN (SELECT request_id FROM emergency_confirmations WHERE donor_id = ? AND donor_response = 'approve')
                           ORDER BY lr.created_at DESC
                           LIMIT 5";
        $availableStmt = $conn->prepare($available_query);
        if ($availableStmt) {
            $availableStmt->bind_param("ssssss", $blood_type, $donorLocation, $donorLocation, $donorLocation, $donor_id, $donor_id);
            $availableStmt->execute();
            $res = $availableStmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $availableRequests[] = $row;
            }
            $availableStmt->close();
        }
    } elseif ($hasBloodType) {
        // Match by blood_type only (city column doesn't exist yet)
        $available_query = "SELECT lr.*, u.first_name AS recipient_first, u.last_name AS recipient_last,
                                   lc.donor_id, lc.donor_response
                           FROM emergency_requests lr
                           LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id
                           JOIN users u ON u.user_id = lr.recipient_id
                           WHERE lr.status = 'pending'
                             AND lr.blood_type = ?
                             AND (lc.donor_id IS NULL OR lc.donor_id != ?)
                             AND lr.id NOT IN (SELECT request_id FROM emergency_confirmations WHERE donor_id = ? AND donor_response = 'approve')
                           ORDER BY lr.created_at DESC
                           LIMIT 5";
        $availableStmt = $conn->prepare($available_query);
        if ($availableStmt) {
            $availableStmt->bind_param("sss", $blood_type, $donor_id, $donor_id);
            $availableStmt->execute();
            $res = $availableStmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $availableRequests[] = $row;
            }
            $availableStmt->close();
        }
    } else {
        // Fallback: match by donor's blood type from recipient profile (old method)
        $available_query = "SELECT lr.*, u.first_name AS recipient_first, u.last_name AS recipient_last,
                                   lc.donor_id, lc.donor_response,
                                   r.blood_type AS recipient_blood_type
                           FROM emergency_requests lr
                           LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id
                           JOIN users u ON u.user_id = lr.recipient_id
                           LEFT JOIN recipients r ON r.user_id = lr.recipient_id
                           WHERE lr.status = 'pending'
                             AND r.blood_type = ?
                             AND (LOWER(r.location) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', r.location, '%')))
                             AND (lc.donor_id IS NULL OR lc.donor_id != ?)
                             AND lr.id NOT IN (SELECT request_id FROM emergency_confirmations WHERE donor_id = ? AND donor_response = 'approve')
                           ORDER BY lr.created_at DESC
                           LIMIT 5";
        $availableStmt = $conn->prepare($available_query);
        if ($availableStmt) {
            $availableStmt->bind_param("sssss", $blood_type, $donorLocation, $donorLocation, $donor_id, $donor_id);
            $availableStmt->execute();
            $res = $availableStmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $availableRequests[] = $row;
            }
            $availableStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        :root {
            --primary: #EA062B;
            --primary-light: #ffe6ea;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --dark: #2c3e50;
            --light: #ecf0f1;
            --text: #2d3748;
            --text-light: #718096;
        }

        .dashboard-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 5px solid var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.success {
            border-left-color: var(--success);
        }

        .stat-card.warning {
            border-left-color: var(--warning);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-card.success .stat-number {
            color: var(--success);
        }

        .stat-card.warning .stat-number {
            color: var(--warning);
        }

        .stat-label {
            font-size: 0.95rem;
            color: var(--text-light);
            font-weight: 500;
        }

        .stat-card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: var(--primary-light);
            border-radius: 10px;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .stat-card.success .stat-card-icon {
            background: #d5f4e6;
            color: var(--success);
        }

        .stat-card.warning .stat-card-icon {
            background: #fdeaa8;
            color: var(--warning);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: var(--primary);
            border-radius: 2px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .recent-donations {
            max-height: 400px;
            overflow-y: auto;
        }

        .donation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .donation-item:last-child {
            border-bottom: none;
        }

        .donation-info {
            flex: 1;
        }

        .donation-date {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.25rem;
        }

        .donation-type {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-success {
            background: #d5f4e6;
            color: var(--success);
        }

        .profile-summary {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--primary);
            font-weight: 700;
        }

        .profile-info h3 {
            margin: 0;
            color: var(--text);
            font-weight: 600;
            font-size: 1.2rem;
        }

        .profile-info p {
            margin: 0.25rem 0 0 0;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .blood-type-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            font-weight: 700;
            font-size: 1.3rem;
            margin-top: 0.5rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-dashboard {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-align: center;
        }

        .btn-primary-dashboard {
            background: var(--primary);
            color: white;
        }

        .btn-primary-dashboard:hover {
            background: #c80523;
            transform: translateY(-2px);
        }

        .btn-secondary-dashboard {
            background: var(--light);
            color: var(--text);
            border: 1px solid #ddd;
        }

        .btn-secondary-dashboard:hover {
            background: #e0e0e0;
            border-color: #999;
        }

        .emergency-alert {
            background: #fff3cd;
            border-left: 4px solid var(--warning);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .emergency-alert-icon {
            font-size: 1.5rem;
            color: var(--warning);
            flex-shrink: 0;
        }

        .emergency-alert-content h4 {
            margin: 0 0 0.5rem 0;
            color: var(--text);
        }

        .emergency-alert-content p {
            margin: 0;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--text-light);
        }

        .no-data-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 28px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        .toggle-switch input:checked + .toggle-slider {
            background-color: var(--success);
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .profile-summary {
                flex-direction: column;
                text-align: center;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php include('assets/includes/link-js.php'); ?>
</head>
<body>
    <?php include('assets/includes/header.php'); ?>

    <main class="dashboard-container">
        <div class="container">
            <!-- Page Title -->
            <h1 class="page-title">
                <i class="fas fa-heartbeat" style="color: var(--primary);"></i> Donor Dashboard
            </h1>

            <!-- Statistics Cards -->
            <div class="dashboard-grid">
                <div class="stat-card <?= $is_active_for_donation ? 'success' : 'warning' ?>">
                    <div class="stat-card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number" style="font-size: 1.1rem;"><?= htmlspecialchars($status_label) ?></div>
                    <div class="stat-label">Donor Status</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?= htmlspecialchars($blood_type) ?></div>
                    <div class="stat-label">Blood Type</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="stat-number"><?= $total_donations ?></div>
                    <div class="stat-label">Total Donations</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-card-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-number"><?= $pending_emergencies ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-number"><?= $unread_count ?></div>
                    <div class="stat-label">Unread Messages</div>
                </div>
            </div>

            <!-- Emergency Alert (if any) -->
            <?php if ($pending_emergencies > 0): ?>
                <div class="emergency-alert">
                    <div class="emergency-alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="emergency-alert-content">
                        <h4>Emergency Requests Available</h4>
                        <p>There are <?= $pending_emergencies ?> pending emergency blood request(s) matching your blood type. Consider helping save lives!</p>
                        <div style="margin-top: 1rem;">
                            <a href="emergency-donor" class="btn btn-warning" style="background-color: var(--warning); border: none; color: #fff;">
                                <i class="fas fa-arrow-right"></i> View All Requests
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pending Donor Confirmations (Schedule Date) -->
            <?php if (count($pending_confirmations) > 0): ?>
            <div class="card" style="margin-bottom: 2rem; border-left: 4px solid var(--warning);">
                <h2 class="section-title"><i class="fas fa-calendar-check"></i> Pending Confirmation</h2>
                <p style="color: var(--text-light); margin-bottom: 1rem;">Please confirm the donation date below.</p>
                <?php foreach ($pending_confirmations as $pc): ?>
                <div class="donation-item" style="padding: 1rem; background: #fff9e6; border-radius: 8px; margin-bottom: 1rem;">
                    <div class="donation-info">
                        <strong><?= htmlspecialchars($pc['recipient_first'] . ' ' . $pc['recipient_last']) ?></strong>
                        <div><?= htmlspecialchars($pc['donation_date'] ?? '') ?> <?= htmlspecialchars($pc['donation_time'] ?? '') ?></div>
                        <div><?= htmlspecialchars($pc['location'] ?? '') ?></div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-success btn-sm confirm-scheduling-btn" data-id="<?= (int)$pc['id'] ?>">Confirm</button>
                        <button type="button" class="btn btn-secondary btn-sm decline-scheduling-btn" data-id="<?= (int)$pc['id'] ?>">Decline</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Completion Confirmation (Yes/No/Reschedule with Remarks) -->
            <?php if (count($completion_asks) > 0): ?>
            <div class="card" style="margin-bottom: 2rem; border-left: 4px solid var(--primary);">
                <h2 class="section-title"><i class="fas fa-question-circle"></i> Was the donation completed?</h2>
                <?php foreach ($completion_asks as $ca): ?>
                <div class="donation-item" style="padding: 1rem; background: #f0f9ff; border-radius: 8px; margin-bottom: 1rem;">
                    <div class="donation-info">
                        <strong><?= htmlspecialchars($ca['recipient_first'] . ' ' . $ca['recipient_last']) ?></strong>
                        <div><?= htmlspecialchars(format_display_date(($ca['donation_date'] ?? '') . ' ' . ($ca['donation_time'] ?? '00:00'))) ?> - <?= htmlspecialchars($ca['location'] ?? '') ?></div>
                    </div>
                    <div class="completion-response" data-id="<?= (int)$ca['id'] ?>">
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                            <button type="button" class="btn btn-success btn-sm completion-yes">Yes</button>
                            <button type="button" class="btn btn-danger btn-sm completion-no">No</button>
                            <button type="button" class="btn btn-warning btn-sm completion-reschedule">Reschedule</button>
                        </div>
                        <div class="remarks-row" style="display: none;">
                            <input type="text" class="form-control remarks-input" placeholder="Remarks (optional)" style="margin-bottom: 0.5rem;">
                            <div class="reschedule-fields" style="display: none;">
                                <input type="date" class="form-control new-date-input" style="margin-bottom: 0.25rem;">
                                <input type="time" class="form-control new-time-input" style="margin-bottom: 0.5rem;">
                            </div>
                            <button type="button" class="btn btn-primary btn-sm submit-completion">Submit</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Profile Summary -->
                <div class="card">
                    <h2 class="section-title">Profile Summary</h2>
                    
                    <div class="profile-summary">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($display_name, 0, 1)) ?>
                        </div>
                        <div class="profile-info">
                            <h3><?= htmlspecialchars($display_name) ?></h3>
                            <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($donor['email']) ?></p>
                            <p><i class="fas fa-phone"></i> <?= htmlspecialchars($contact_display) ?></p>
                            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($location_display) ?></p>
                            <div class="blood-type-badge"><?= htmlspecialchars($blood_type) ?></div>
                        </div>
                    </div>

                    <?php if ($next_donation_date && $next_donation_date > time()): ?>
                    <div class="countdown-box" style="background: #f0f9ff; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid var(--primary);">
                        <strong><i class="fas fa-clock"></i> Next Donation:</strong> 
                        <span id="donation-countdown"><?= format_display_date($next_donation_date, false) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Donor Availability Toggle -->
                    <div class="toggle-row" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 0; border-top: 1px solid #e2e8f0; margin-top: 1rem;">
                        <div>
                            <strong>Profile Visibility</strong>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; color: var(--text-light);">When off, your profile won't show in lists and you won't get requests</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="availability-toggle" <?= $is_available ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Emergency Availability Toggle -->
                    <div class="toggle-row" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 0; border-top: 1px solid #e2e8f0;">
                        <div>
                            <strong>Emergency Availability</strong>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; color: var(--text-light);">Can donate within 6–8 hours for emergencies</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="emergency-toggle" <?= $emergency_avail ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="action-buttons">
                        <a href="donor-profile" class="btn-dashboard btn-primary-dashboard">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                        <a href="donor-inbox" class="btn-dashboard btn-secondary-dashboard">
                            <i class="fas fa-inbox"></i> Messages
                        </a>
                        <a href="emergency-donor" class="btn-dashboard btn-secondary-dashboard">
                            <i class="fas fa-ambulance"></i> Emergency
                        </a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <h2 class="section-title">Quick Actions</h2>
                    
                    <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                        <div style="padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--success);">
                            <h4 style="margin: 0 0 0.5rem 0; color: var(--text);">Help Someone in Need</h4>
                            <p style="margin: 0 0 1rem 0; color: var(--text-light); font-size: 0.95rem;">Check if there are any emergency blood requests matching your blood type.</p>
                            <a href="emergency-donor" class="btn-dashboard btn-primary-dashboard" style="width: 100%;">
                                <i class="fas fa-ambulance"></i> View Emergency Requests
                            </a>
                        </div>
                        
                        <div style="padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--primary);">
                            <h4 style="margin: 0 0 0.5rem 0; color: var(--text);">Schedule Donation</h4>
                            <p style="margin: 0 0 1rem 0; color: var(--text-light); font-size: 0.95rem;">Find blood banks and schedule your next donation appointment.</p>
                            <a href="find-a-donor" class="btn-dashboard btn-secondary-dashboard" style="width: 100%;">
                                <i class="fas fa-hospital"></i> Find Blood Bank
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Blood Donation History -->
            <div class="card">
                <h2 class="section-title">Blood Donation History</h2>
                <?php if (count($donation_history) > 0): ?>
                <div class="recent-donations">
                    <?php foreach ($donation_history as $dh): ?>
                    <div class="donation-item">
                        <div class="donation-info">
                            <div class="donation-date"><?= htmlspecialchars(format_display_date($dh['donation_date'] ?? $dh['created_at'], false)) ?></div>
                            <div class="donation-type"><?= htmlspecialchars($dh['status'] ?? 'completed') ?> &middot; <?= htmlspecialchars($dh['urgency'] ?? 'normal') ?></div>
                        </div>
                        <span class="badge badge-<?= ($dh['status'] ?? '') === 'completed' ? 'success' : 'warning' ?>"><?= htmlspecialchars($dh['status'] ?? 'completed') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="donation-requests-manager" class="btn-dashboard btn-secondary-dashboard" style="margin-top: 1rem;">View All Blood Donation Requests</a>
                <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon"><i class="fas fa-tint"></i></div>
                    <p>No donation history yet. Your completed donations will appear here.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Health Reports (Optional Upload) -->
            <div class="card">
                <h2 class="section-title">Health Reports</h2>
                <p style="color: var(--text-light); margin-bottom: 1rem;">Upload or replace your blood test and medical reports (optional).</p>
                <a href="edit-donor-profile" class="btn-dashboard btn-primary-dashboard" style="width: 100%;">
                    <i class="fas fa-upload"></i> Upload / Replace Reports
                </a>
            </div>

            <!-- Donor Insights -->
            <div class="card">
                <h2 class="section-title">Donor Insights</h2>
                <div style="display: grid; gap: 1rem;">
                    <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 48px; height: 48px; background: var(--primary-light); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-heart"></i></div>
                        <div>
                            <strong>Lives Impacted</strong>
                            <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;"><?= $total_donations ?> donation(s) completed</p>
                        </div>
                    </div>
                    <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 48px; height: 48px; background: #d5f4e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--success);"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <strong>Status</strong>
                            <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;"><?= $is_active_for_donation ? 'Eligible to donate' : 'Next eligible in ' . ($next_donation_date ? format_display_date($next_donation_date, false) : '') ?></p>
                        </div>
                    </div>
                    <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 48px; height: 48px; background: #fff3cd; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--warning);"><i class="fas fa-tint"></i></div>
                        <div>
                            <strong>Blood Donation Requests</strong>
                            <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;">View and manage your blood donation requests.</p>
                            <a href="donation-requests-manager" class="btn-dashboard btn-secondary-dashboard" style="margin-top: 0.5rem; padding: 0.5rem 1rem; font-size: 0.9rem;">View Requests</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links & Actions -->
            <div class="card">
                <h2 class="section-title">Quick Links & Actions</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="donor-profile" class="btn-dashboard btn-secondary-dashboard" style="padding: 1rem;">
                        <i class="fas fa-id-card"></i> View Full Profile
                    </a>
                    <a href="donation-requests-manager" class="btn-dashboard btn-secondary-dashboard" style="padding: 1rem;">
                        <i class="fas fa-list"></i> Donation Requests
                    </a>
                    <a href="contact" class="btn-dashboard btn-secondary-dashboard" style="padding: 1rem;">
                        <i class="fas fa-headset"></i> Contact Support
                    </a>
                    <a href="edit-donor-profile" class="btn-dashboard btn-secondary-dashboard" style="padding: 1rem;">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
            </div>

        </div>
    </main>

    <?php include('assets/includes/footer.php'); ?>
    <?php include('assets/includes/link-js.php'); ?>

    <script>
    // Donor availability toggle
    document.getElementById('availability-toggle')?.addEventListener('change', async function() {
        const checked = this.checked;
        const fd = new FormData();
        fd.append('action', 'toggle_availability');
        fd.append('checked', checked ? 'true' : 'false');
        try {
            const res = await fetch('assets/lib/donor-dashboard-api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) alert(json.error || 'Failed to update profile visibility');
        } catch (e) { alert('Failed to update'); }
    });

    // Emergency availability toggle
    document.getElementById('emergency-toggle')?.addEventListener('change', async function() {
        const checked = this.checked;
        const fd = new FormData();
        fd.append('action', 'toggle_emergency');
        fd.append('checked', checked ? 'true' : 'false');
        try {
            const res = await fetch('assets/lib/donor-dashboard-api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) alert(json.error || 'Failed to update emergency availability');
        } catch (e) { alert('Failed to update'); }
    });

    // Next donation countdown (if element exists)
    <?php if ($next_donation_date && $next_donation_date > time()): ?>
    (function() {
        const target = <?= $next_donation_date ?> * 1000;
        function update() {
            const now = Date.now();
            if (now >= target) {
                document.getElementById('donation-countdown').textContent = 'Eligible now';
                return;
            }
            const d = Math.floor((target - now) / 86400000);
            const h = Math.floor(((target - now) % 86400000) / 3600000);
            document.getElementById('donation-countdown').textContent = d + ' days, ' + h + ' hours';
        }
        update();
        setInterval(update, 3600000);
    })();
    <?php endif; ?>

    // Pending confirmation: Confirm/Decline scheduling
    document.querySelectorAll('.confirm-scheduling-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append('action', 'confirm_donor');
            fd.append('blood_donation_id', id);
            fd.append('confirmed', '1');
            btn.disabled = true;
            try {
                const res = await fetch('assets/lib/scheduling-api.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) location.reload();
                else alert(json.error || 'Failed');
            } catch (e) { alert('Error'); btn.disabled = false; }
        });
    });
    document.querySelectorAll('.decline-scheduling-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append('action', 'confirm_donor');
            fd.append('blood_donation_id', id);
            fd.append('confirmed', '0');
            btn.disabled = true;
            try {
                const res = await fetch('assets/lib/scheduling-api.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) location.reload();
                else alert(json.error || 'Failed');
            } catch (e) { alert('Error'); btn.disabled = false; }
        });
    });

    // Completion response: Yes/No/Reschedule with remarks
    document.querySelectorAll('.completion-response').forEach(block => {
        const id = block.dataset.id;
        const yesBtn = block.querySelector('.completion-yes');
        const noBtn = block.querySelector('.completion-no');
        const rescheduleBtn = block.querySelector('.completion-reschedule');
        const remarksRow = block.querySelector('.remarks-row');
        const remarksInput = block.querySelector('.remarks-input');
        const rescheduleFields = block.querySelector('.reschedule-fields');
        const submitBtn = block.querySelector('.submit-completion');
        let chosenResponse = null;

        function showRemarks(showReschedule = false) {
            remarksRow.style.display = 'block';
            rescheduleFields.style.display = showReschedule ? 'block' : 'none';
        }

        yesBtn?.addEventListener('click', () => { chosenResponse = 'yes'; showRemarks(false); });
        noBtn?.addEventListener('click', () => { chosenResponse = 'no'; showRemarks(false); });
        rescheduleBtn?.addEventListener('click', () => { chosenResponse = 'reschedule'; showRemarks(true); });

        submitBtn?.addEventListener('click', async function() {
            const fd = new FormData();
            fd.append('action', 'completion_response');
            fd.append('blood_donation_id', id);
            fd.append('response', chosenResponse);
            fd.append('remarks', remarksInput?.value || '');
            if (chosenResponse === 'reschedule') {
                fd.append('new_date', block.querySelector('.new-date-input')?.value || '');
                fd.append('new_time', block.querySelector('.new-time-input')?.value || '');
            }
            submitBtn.disabled = true;
            try {
                const res = await fetch('assets/lib/scheduling-api.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) location.reload();
                else alert(json.error || 'Failed');
            } catch (e) { alert('Error'); }
        });
    });

    // Handle donor response buttons
    document.querySelectorAll('.donor-response').forEach(btn => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('tr');
            const id = row.dataset.requestId;
            const response = btn.dataset.response;
            const preferredDate = row.dataset.date || '';
            const preferredTime = row.dataset.time || '';
            const preferredLocation = row.dataset.location || '';

            if (response === 'approve') {
                // Quick approve: use recipient's preferred date/time/location
                const scheduledAt = `${preferredDate} ${preferredTime}`.trim();
                const data = new FormData();
                data.append('action', 'donor_response');
                data.append('request_id', id);
                data.append('response', response);
                data.append('scheduled_at', scheduledAt);
                data.append('location', preferredLocation);
                btn.disabled = true;
                const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: data });
                const json = await res.json();
                if (json.success) {
                    location.reload();
                } else {
                    btn.disabled = false;
                    const errorMsg = json.error || 'Failed to submit response.';
                    if (errorMsg.includes('already been assigned') || errorMsg.includes('already been accepted')) {
                        alert('⚠️ ' + errorMsg + '\n\nThis request is no longer available. Please refresh the page.');
                    } else {
                        alert(errorMsg);
                    }
                }
                return;
            }

            if (response === 'decline') {
                const data = new FormData();
                data.append('action', 'donor_response');
                data.append('request_id', id);
                data.append('response', response);
                btn.disabled = true;
                const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: data });
                const json = await res.json();
                if (json.success) {
                    location.reload();
                } else {
                    btn.disabled = false;
                    const errorMsg = json.error || 'Failed to submit response.';
                    if (errorMsg.includes('already been assigned') || errorMsg.includes('already been accepted')) {
                        alert('⚠️ ' + errorMsg + '\n\nThis request is no longer available. Please refresh the page.');
                    } else {
                        alert(errorMsg);
                    }
                }
                return;
            }
        });
    });
    </script>
</body>
</html>
