<?php
/**
 * Blood Donation Request Detail - accessible by recipient, donor, and admin
 */
session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$userId = $_SESSION['user_id'] ?? null;
$isAdmin = !empty($_SESSION['super_admin_logged_in']);
$isDonor = $profileManager->hasRole('donor');
$isRecipient = $profileManager->hasRole('recipient');

if (!$isAdmin && !$isDonor && !$isRecipient) {
    header('Location: sign-in.php');
    exit;
}

$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($requestId <= 0) {
    header('Location: donation-requests-manager');
    exit;
}

// Fetch request with access check
$where = "lr.id = ?";
$params = [$requestId];
$types = 'i';

if (!$isAdmin) {
    if ($isRecipient && !$isDonor) {
        $where .= " AND lr.recipient_id = ?";
        $params[] = $userId;
        $types .= 's';
    } elseif ($isDonor && !$isRecipient) {
        $where .= " AND lc.donor_id = ?";
        $params[] = $userId;
        $types .= 's';
    } else {
        $where .= " AND (lr.recipient_id = ? OR lc.donor_id = ?)";
        $params[] = $userId;
        $params[] = $userId;
        $types .= 'ss';
    }
}

$stmt = $conn->prepare("
    SELECT lr.*, lc.scheduled_at, lc.donor_id, lc.reschedule_payload, lc.donor_response, lc.completion_status, lc.donor_remarks, lc.recipient_remarks,
           u1.first_name AS recipient_first, u1.last_name AS recipient_last, u1.email AS recipient_email,
           u2.first_name AS donor_first, u2.last_name AS donor_last, u2.email AS donor_email,
           COALESCE(lr.blood_type, r.blood_type) AS requested_blood_type
    FROM emergency_requests lr
    LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id
    LEFT JOIN users u1 ON u1.user_id = lr.recipient_id
    LEFT JOIN users u2 ON u2.user_id = lc.donor_id
    LEFT JOIN recipients r ON r.user_id = lr.recipient_id
    WHERE $where
    LIMIT 1
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    header('Location: donation-requests-manager');
    exit;
}

$donorName = trim(($req['donor_first'] ?? '') . ' ' . ($req['donor_last'] ?? ''));
$recipientName = trim(($req['recipient_first'] ?? '') . ' ' . ($req['recipient_last'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation Request #<?= (int)$req['id']; ?> - Blood Konnector</title>
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
        .urgency-high, .urgency-urgent { background: #fff3cd; color: #856404; }
        .urgency-critical { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<?php include('assets/includes/header.php'); ?>

<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="donation-requests-manager">Blood Donation Requests</a></li>
            <li class="breadcrumb-item active">Request #<?= (int)$req['id']; ?></li>
        </ol>
    </nav>

    <div class="card detail-card">
        <div class="detail-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="mb-1">Blood Donation Request #<?= (int)$req['id']; ?></h4>
                <span class="status-badge status-<?= htmlspecialchars($req['status'] ?? 'pending'); ?>"><?= htmlspecialchars($req['status'] ?? 'pending'); ?></span>
                <span class="urgency-badge urgency-<?= htmlspecialchars($req['urgency'] ?? 'normal'); ?> ms-2"><?= htmlspecialchars($req['urgency'] ?? 'normal'); ?></span>
            </div>
            <div>
                <a href="donation-requests-manager" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <i class="far fa-calendar-alt"></i>
                        <div>
                            <strong>Date & Time</strong><br>
                            <span><?= htmlspecialchars(format_display_date($req['preferred_date'] . ' ' . ($req['preferred_time'] ?? '00:00'))); ?></span>
                            <?php if (!empty($req['scheduled_at'])): ?>
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
                        <i class="fas fa-user-injured"></i>
                        <div>
                            <strong>Recipient</strong><br>
                            <span><?= htmlspecialchars($recipientName ?: '—'); ?></span>
                            <?php if (!empty($req['recipient_email'])): ?>
                                <div class="small text-muted"><?= htmlspecialchars($req['recipient_email']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-hand-holding-heart"></i>
                        <div>
                            <strong>Donor</strong><br>
                            <span><?= $donorName ? htmlspecialchars($donorName) : '<span class="text-muted">Unassigned</span>'; ?></span>
                            <?php if (!empty($req['donor_email'])): ?>
                                <div class="small text-muted"><?= htmlspecialchars($req['donor_email']); ?></div>
                            <?php endif; ?>
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
            <?php if (!empty($req['donor_remarks']) || !empty($req['recipient_remarks'])): ?>
            <div class="info-row mt-2 pt-2 border-top">
                <i class="fas fa-comment-dots"></i>
                <div>
                    <strong>Remarks</strong><br>
                    <?php if (!empty($req['donor_remarks'])): ?>
                        <span class="d-block"><strong>Donor:</strong> <?= htmlspecialchars($req['donor_remarks']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($req['recipient_remarks'])): ?>
                        <span class="d-block"><strong>Recipient:</strong> <?= htmlspecialchars($req['recipient_remarks']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-4 pt-3 border-top">
                <?php if (!empty($req['donor_id']) && ($isRecipient || $isAdmin)): ?>
                    <a href="chat.php?id=<?= urlencode($req['donor_id']); ?>" class="btn btn-primary"><i class="fas fa-comments me-1"></i> Chat with Donor</a>
                <?php endif; ?>
                <?php if (!empty($req['recipient_id']) && ($isDonor || $isAdmin)): ?>
                    <a href="chat.php?id=<?= urlencode($req['recipient_id']); ?>" class="btn btn-primary ms-2"><i class="fas fa-comments me-1"></i> Chat with Recipient</a>
                <?php endif; ?>
                <a href="donation-requests-manager" class="btn btn-outline-secondary ms-2">View All Requests</a>
            </div>
        </div>
    </div>
</div>

<?php include('assets/includes/footer.php'); ?>
<?php include('assets/includes/link-js.php'); ?>
</body>
</html>
