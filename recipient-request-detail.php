<?php
session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('recipient', 'sign-in.php');
$userId = $_SESSION['user_id'];

$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($requestId <= 0) {
    header('Location: recipient-dashboard.php');
    exit;
}

// Fetch request - ensure it belongs to this recipient
$stmt = $conn->prepare("
    SELECT lr.*, lc.scheduled_at, lc.donor_id, lc.reschedule_payload,
           u.first_name AS donor_first, u.last_name AS donor_last,
           COALESCE(lr.blood_type, r.blood_type) AS requested_blood_type
    FROM emergency_requests lr
    LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id
    LEFT JOIN users u ON u.user_id = lc.donor_id
    LEFT JOIN recipients r ON r.user_id = lr.recipient_id
    WHERE lr.id = ? AND lr.recipient_id = ?
    LIMIT 1
");
$stmt->bind_param("is", $requestId, $userId);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    header('Location: recipient-dashboard.php');
    exit;
}

$donorName = trim(($req['donor_first'] ?? '') . ' ' . ($req['donor_last'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request #<?= (int)$req['id']; ?> - Blood Konnector</title>
    <?php include('assets/includes/link-css.php'); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .detail-card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .detail-header { border-bottom: 1px solid #e9ecef; padding: 1.25rem 1.5rem; }
        .info-row { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; }
        .info-row i { color: #6c757d; margin-top: 0.25rem; width: 20px; text-align: center; }
        .info-row strong { color: #495057; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-badge { border-radius: 20px; padding: 6px 14px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
        .status-pending { background:#fff4e6; color:#d97706; }
        .status-confirmed { background:#e0f2fe; color:#0369a1; }
        .status-completed { background:#ecfdf3; color:#15803d; }
        .status-failed { background:#fef2f2; color:#b91c1c; }
        .status-rescheduled { background:#f5f3ff; color:#6d28d9; }
        .status-expired { background:#f3f4f6; color:#374151; }
        .urgency-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .urgency-normal { background: #e9ecef; color: #495057; }
        .urgency-high { background: #fff3cd; color: #856404; }
        .urgency-critical { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<?php include('assets/includes/header.php'); ?>

<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="recipient-dashboard">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="emergency-recipient">Emergency</a></li>
            <li class="breadcrumb-item active">Request #<?= (int)$req['id']; ?></li>
        </ol>
    </nav>

    <div class="card detail-card">
        <div class="detail-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="mb-1">Request #<?= (int)$req['id']; ?></h4>
                <span class="status-badge status-<?= htmlspecialchars($req['status']); ?>"><?= htmlspecialchars($req['status']); ?></span>
                <span class="urgency-badge urgency-<?= htmlspecialchars($req['urgency'] ?? 'normal'); ?> ms-2"><?= htmlspecialchars($req['urgency'] ?? 'normal'); ?></span>
            </div>
            <div>
                <a href="emergency-recipient" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <i class="far fa-calendar-alt"></i>
                        <div>
                            <strong>Date & Time</strong><br>
                            <span><?= htmlspecialchars(format_display_date($req['preferred_date'] . ' ' . $req['preferred_time'])); ?></span>
                            <?php if ($req['status'] === 'confirmed' && !empty($req['scheduled_at'])): ?>
                                <div class="small text-info mt-1"><i class="fas fa-clock me-1"></i>Scheduled: <?= htmlspecialchars(format_display_date($req['scheduled_at'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <strong>Location</strong><br>
                            <span><?= htmlspecialchars($req['location'] ?? '—'); ?></span>
                        </div>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-city"></i>
                        <div>
                            <strong>City</strong><br>
                            <span><?= htmlspecialchars($req['city'] ?? '—'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <i class="fas fa-tint"></i>
                        <div>
                            <strong>Blood Type</strong><br>
                            <span class="badge bg-danger"><?= htmlspecialchars($req['requested_blood_type'] ?? '—'); ?></span>
                        </div>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-user"></i>
                        <div>
                            <strong>Donor</strong><br>
                            <span><?= $donorName ? htmlspecialchars($donorName) : '<span class="text-muted">Unassigned</span>'; ?></span>
                        </div>
                    </div>
                    <div class="info-row">
                        <i class="far fa-clock"></i>
                        <div>
                            <strong>Created</strong><br>
                            <span><?= htmlspecialchars(format_display_date($req['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($req['note'])): ?>
            <div class="info-row mt-3 pt-3 border-top">
                <i class="fas fa-sticky-note"></i>
                <div>
                    <strong>Notes</strong><br>
                    <span><?= nl2br(htmlspecialchars($req['note'])); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-4 pt-3 border-top">
                <?php if (!empty($req['donor_id'])): ?>
                    <a href="chat.php?id=<?= urlencode($req['donor_id']); ?>" class="btn btn-primary"><i class="fas fa-comments me-1"></i> Chat with Donor</a>
                <?php endif; ?>
                <a href="emergency-recipient" class="btn btn-outline-secondary ms-2">View All Requests</a>
            </div>
        </div>
    </div>
</div>

<?php include('assets/includes/footer.php'); ?>
<?php include('assets/includes/link-js.php'); ?>
</body>
</html>
