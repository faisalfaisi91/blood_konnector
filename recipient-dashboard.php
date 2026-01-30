<?php
session_start();

require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/includes/header.php';

// Dummy user data for demonstration (replace with real DB queries)



// Fetch recipient info from recipients table
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM recipients WHERE user_id = ? LIMIT 1");
$stmt->bind_param("s", $userId);
$stmt->execute();
$recipient = $stmt->get_result()->fetch_assoc();
$stmt->close();
$user = [
    'profile_pic' => !empty($recipient['profile_pic']) ? $recipient['profile_pic'] : 'assets/images/b1.png',
    'name' => trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
    'age' => $recipient['age'] ?? '--',
    'gender' => $recipient['gender'] ?? '--',
    'blood_group' => $recipient['blood_type'] ?? '--',
    'verified' => !empty($recipient['verified']) && $recipient['verified'] == 1
];

// Dummy requests (replace with real DB queries)
$activeRequests = [
    [
        'id' => 'REQ1234',
        'status' => 'Pending',
        'blood_group' => 'A+',
        'units' => 2,
        'urgency' => 'High',
        'hospital' => 'City Hospital',
        'city' => 'Metropolis',
        'doctor' => 'Dr. Smith',
        'created_at' => '2026-01-28',
    ]
];

// Dummy matched donors (replace with real DB queries)
$matchedDonors = [
    [
        'name' => 'Jane Donor',
        'blood_group' => 'A+',
        'location' => 'Metropolis',
        'last_donation' => '2025-10-01',
        'available' => true
    ]
];

// Dummy donation history (replace with real DB queries)
$donationHistory = [
    [
        'id' => 'DON5678',
        'donor_name' => 'Jane Donor',
        'contact' => 'ID12345',
        'blood_group' => 'A+',
        'units' => 2,
        'hospital' => 'City Hospital',
        'date' => '2025-10-02',
        'status' => 'Completed',
        'proof' => '',
        'feedback' => 'Thank you!'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipient Dashboard</title>
    <?php include('assets/includes/link-css.php'); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem; }
        .stat-card.success .stat-number { color: var(--success); }
        .stat-card.warning .stat-number { color: var(--warning); }
        .stat-label { font-size: 0.95rem; color: var(--text-light); font-weight: 500; }
        .stat-card-icon { display: flex; align-items: center; justify-content: center; width: 50px; height: 50px; background: var(--primary-light); border-radius: 10px; font-size: 1.8rem; margin-bottom: 1rem; color: var(--primary); }
        .stat-card.success .stat-card-icon { background: #d5f4e6; color: var(--success); }
        .stat-card.warning .stat-card-icon { background: #fdeaa8; color: var(--warning); }
        .profile-summary {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding: 1.5rem 1rem 1rem 1rem;
        }
        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--primary);
            font-weight: 700;
            flex-shrink: 0;
        }
        .profile-info {
            flex: 1;
            min-width: 220px;
        }
        .profile-info h3 {
            margin: 0 0 0.25rem 0;
            color: var(--text);
            font-weight: 700;
            font-size: 1.4rem;
        }
        .profile-info p {
            margin: 0.15rem 0 0 0;
            color: var(--text-light);
            font-size: 1rem;
        }
        .blood-type-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: var(--primary);
            color: white;
            font-weight: 700;
            font-size: 1.3rem;
            margin-top: 0.7rem;
            margin-bottom: 0.5rem;
        }
        .action-buttons {
            display: flex;
            gap: 0;
            margin-top: 0;
        }
        .btn-dashboard {
            flex: 1 1 0;
            padding: 1.1rem 0;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.7rem;
            text-align: center;
            font-size: 1.1rem;
        }
        .btn-primary-dashboard {
            background: var(--primary);
            color: white;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .btn-primary-dashboard:hover {
            background: #c80523;
            transform: translateY(-2px);
        }
        .btn-secondary-dashboard {
            background: #f3f5f7;
            color: var(--text);
            border: 1px solid #e0e0e0;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        .btn-secondary-dashboard:hover {
            background: #e0e0e0;
            border-color: #999;
        }
        @media (max-width: 900px) {
            .profile-summary { flex-direction: column; align-items: center; text-align: center; }
            .action-buttons { flex-direction: column; gap: 1rem; width: 100%; }
            .btn-dashboard { border-radius: 12px !important; }
        }
    </style>
    <?php include('assets/includes/link-js.php'); ?>
</head>
<body>
<div class="dashboard-container">
    <div class="container">
        <!-- Page Title -->
        <h1 class="page-title">
            <i class="fas fa-heartbeat" style="color: var(--primary);"></i> Recipient Dashboard
        </h1>
        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-number">Active</div>
                <div class="stat-label">Recipient Status</div>
            </div>
            <div class="stat-card success">
                <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= $user['blood_group'] ?></div>
                <div class="stat-label">Blood Type</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-card-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-number"><?= count($activeRequests) ?></div>
                <div class="stat-label">Active Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-envelope"></i></div>
                <div class="stat-number">0</div>
                <div class="stat-label">Unread Messages</div>
            </div>
        </div>
        <!-- Profile Summary -->
        <div class="card mb-4">
            <h2 class="section-title">Profile Summary</h2>
            <div class="profile-summary">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h3><?= htmlspecialchars($user['name']) ?></h3>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($recipient['email'] ?? '') ?></p>
                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($recipient['phone_number'] ?? 'Not provided') ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($recipient['city'] ?? 'Not specified') ?></p>
                    <div class="blood-type-badge"><?= htmlspecialchars($user['blood_group']) ?></div>
                    <?php if ($user['verified']): ?>
                        <span class="badge bg-success ms-2">Verified</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="action-buttons">
                <a href="recipient-profile.php" class="btn-dashboard btn-primary-dashboard">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </a>
                <a href="recipient-inbox.php" class="btn-dashboard btn-secondary-dashboard">
                    <i class="fas fa-inbox"></i> Messages
                </a>
            </div>
        </div>

    <!-- Active Blood Requests -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">Active Blood Requests</div>
        <div class="card-body">
            <?php if (empty($activeRequests)): ?>
                <div class="alert alert-info">No active requests.</div>
            <?php else: ?>
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>ID</th><th>Status</th><th>Blood Group</th><th>Units</th><th>Urgency</th><th>Hospital</th><th>City</th><th>Doctor</th><th>Date</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeRequests as $req): ?>
                        <tr>
                            <td><?= htmlspecialchars($req['id']) ?></td>
                            <td><?= htmlspecialchars($req['status']) ?></td>
                            <td><?= htmlspecialchars($req['blood_group']) ?></td>
                            <td><?= htmlspecialchars($req['units']) ?></td>
                            <td><?= htmlspecialchars($req['urgency']) ?></td>
                            <td><?= htmlspecialchars($req['hospital']) ?></td>
                            <td><?= htmlspecialchars($req['city']) ?></td>
                            <td><?= htmlspecialchars($req['doctor']) ?></td>
                            <td><?= htmlspecialchars($req['created_at']) ?></td>
                            <td>
                                <a href="recipient-request-detail.php?id=<?= urlencode($req['id']) ?>" class="btn btn-sm btn-info">View</a>
                                <a href="edit-recipient-request.php?id=<?= urlencode($req['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="cancel-recipient-request.php?id=<?= urlencode($req['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this request?');">Cancel</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Matched Donors & Chat System -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Matched Donors & Chat</div>
        <div class="card-body">
            <?php if (empty($matchedDonors)): ?>
                <div class="alert alert-info">No matched donors yet.</div>
            <?php else: ?>
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Name</th><th>Blood Group</th><th>Location</th><th>Last Donation</th><th>Available</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matchedDonors as $donor): ?>
                        <tr>
                            <td><?= htmlspecialchars($donor['name']) ?></td>
                            <td><?= htmlspecialchars($donor['blood_group']) ?></td>
                            <td><?= htmlspecialchars($donor['location']) ?></td>
                            <td><?= htmlspecialchars($donor['last_donation']) ?></td>
                            <td><?= $donor['available'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                            <td>
                                <a href="chat.php?user=<?= urlencode($donor['name']) ?>" class="btn btn-sm btn-success">Chat Now</a>
                                <a href="tel:+0000000000" class="btn btn-sm btn-outline-primary">Request Call</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Donation History -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">Donation History</div>
        <div class="card-body">
            <?php if (empty($donationHistory)): ?>
                <div class="alert alert-info">No donation history yet.</div>
            <?php else: ?>
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>ID</th><th>Donor Name</th><th>Contact</th><th>Blood Group</th><th>Units</th><th>Hospital</th><th>Date</th><th>Status</th><th>Proof</th><th>Feedback</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donationHistory as $don): ?>
                        <tr>
                            <td><?= htmlspecialchars($don['id']) ?></td>
                            <td><?= htmlspecialchars($don['donor_name']) ?></td>
                            <td><?= htmlspecialchars($don['contact']) ?></td>
                            <td><?= htmlspecialchars($don['blood_group']) ?></td>
                            <td><?= htmlspecialchars($don['units']) ?></td>
                            <td><?= htmlspecialchars($don['hospital']) ?></td>
                            <td><?= htmlspecialchars($don['date']) ?></td>
                            <td><?= htmlspecialchars($don['status']) ?></td>
                            <td><?= $don['proof'] ? '<a href="#">View</a>' : '-' ?></td>
                            <td><?= htmlspecialchars($don['feedback']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reports & Documents -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">Reports & Documents</div>
        <div class="card-body">
            <?php
            $uploadsDir = __DIR__ . '/assets/uploads/recipient_reports/';
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
            $uploadMsg = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['reportUpload'])) {
                $file = $_FILES['reportUpload'];
                $allowed = ['jpg','jpeg','png','pdf','doc','docx'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file['error'] === 0 && in_array($ext, $allowed)) {
                    $fname = uniqid('report_') . '.' . $ext;
                    $dest = $uploadsDir . $fname;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $uploadMsg = '<div class="alert alert-success">File uploaded!</div>';
                    } else {
                        $uploadMsg = '<div class="alert alert-danger">Upload failed.</div>';
                    }
                } else {
                    $uploadMsg = '<div class="alert alert-danger">Invalid file type or error.</div>';
                }
            }
            $uploadedFiles = array_filter(
                is_dir($uploadsDir) ? scandir($uploadsDir) : [],
                function($f) { return !in_array($f, ['.','..']); }
            );
            ?>
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="reportUpload" class="form-label">Upload Medical/Lab Report</label>
                    <input class="form-control" type="file" name="reportUpload" id="reportUpload" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="shareWithAdmin" id="shareWithAdmin">
                    <label class="form-check-label" for="shareWithAdmin">Share with Admin</label>
                </div>
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
            <?= $uploadMsg ?>
            <div class="mt-3">
                <h6>Previous Uploads</h6>
                <div class="d-flex flex-wrap gap-3">
                    <?php if (empty($uploadedFiles)): ?>
                        <span class="text-muted">No uploads yet.</span>
                    <?php else: foreach ($uploadedFiles as $file):
                        $isImg = preg_match('/\.(jpg|jpeg|png)$/i', $file);
                    ?>
                        <div class="position-relative" style="display:inline-block;">
                            <?php if ($isImg): ?>
                                <img src="assets/uploads/recipient_reports/<?= urlencode($file) ?>" alt="Report" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #ccc;">
                            <?php else: ?>
                                <a href="assets/uploads/recipient_reports/<?= urlencode($file) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">View File</a>
                            <?php endif; ?>
                            <form method="post" action="" style="position:absolute;top:0;right:0;">
                                <input type="hidden" name="delete_file" value="<?= htmlspecialchars($file) ?>">
                                <button type="submit" class="btn btn-sm btn-danger" style="padding:2px 6px;line-height:1;" title="Delete" onclick="return confirm('Delete this file?');">&times;</button>
                            </form>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <?php
        $uploadsDir = __DIR__ . '/assets/uploads/recipient_reports/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
        $uploadMsg = '';
        // Handle file deletion
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
            $delFile = basename($_POST['delete_file']);
            $delPath = $uploadsDir . $delFile;
            if (file_exists($delPath)) {
                unlink($delPath);
                // Optionally, update DB to remove reference to this file for the user
                $stmt = $conn->prepare("UPDATE users SET profile_pic = NULL WHERE user_id = ? AND profile_pic = ?");
                $stmt->bind_param("ss", $_SESSION['user_id'], $delFile);
                $stmt->execute();
                $stmt->close();
                $uploadMsg = '<div class="alert alert-success">File deleted.</div>';
            } else {
                $uploadMsg = '<div class="alert alert-danger">File not found.</div>';
            }
        }
        ?>
        </div>
    </div>

    <!-- Account & Support -->
    <div class="card mb-4">
        <div class="card-header bg-light">Account & Support</div>
        <div class="card-body">
            <a href="recipient-profile.php" class="btn btn-outline-secondary">Profile Settings</a>
            <a href="#" class="btn btn-outline-info">Help Center</a>
            <a href="#" class="btn btn-outline-danger">Report a Problem</a>
            <a href="logout.php" class="btn btn-outline-dark float-end">Logout</a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/assets/includes/footer.php'; ?>
</body>
</html>
