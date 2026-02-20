<?php
/**
 * Blood Donations Request Manager
 * Shows donation requests with filters: All, Successful, Failed, Rescheduled, Normal, Urgent
 * Filters: time-wise, month-wise, year-wise
 * Access: recipient, donor, admin
 */
session_start();
include('assets/lib/openconn.php');
require_once('assets/lib/ProfileManager.php');

$profileManager = new ProfileManager($conn);
$userId = $_SESSION['user_id'] ?? null;
$isAdmin = !empty($_SESSION['super_admin_logged_in']);
$isDonor = $profileManager->hasRole('donor');
$isRecipient = $profileManager->hasRole('recipient');

if (!$isAdmin && !$isDonor && !$isRecipient) {
    header("Location: sign-in");
    exit();
}

// Filters
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$allowedStatus = ['all','pending','confirmed','completed','failed','rescheduled','expired'];
if (!in_array($statusFilter, $allowedStatus, true)) $statusFilter = 'all';

$urgencyFilter = isset($_GET['urgency']) ? trim($_GET['urgency']) : 'all';
if (!in_array($urgencyFilter, ['all','normal','urgent','high','critical','low'])) $urgencyFilter = 'all';

$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$dateFrom = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
$dateTo = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';

// Build where clause for emergency_requests
$where = "1=1";
$params = [];
$types = '';

if ($statusFilter !== 'all') {
    $where .= " AND lr.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
if ($urgencyFilter !== 'all') {
    $where .= " AND lr.urgency = ?";
    $params[] = $urgencyFilter;
    $types .= 's';
}
if ($yearFilter > 0) {
    $where .= " AND YEAR(lr.preferred_date) = ?";
    $params[] = $yearFilter;
    $types .= 'i';
}
if ($monthFilter > 0 && $monthFilter <= 12) {
    $where .= " AND MONTH(lr.preferred_date) = ?";
    $params[] = $monthFilter;
    $types .= 'i';
}
if ($dateFrom) {
    $where .= " AND lr.preferred_date >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}
if ($dateTo) {
    $where .= " AND lr.preferred_date <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

// Role-specific filtering
if ($isAdmin) {
    // Admin sees all
} elseif ($isRecipient && !$isDonor) {
    $where .= " AND lr.recipient_id = ?";
    $params[] = $userId;
    $types .= 's';
} elseif ($isDonor && !$isRecipient) {
    $where .= " AND lc.donor_id = ?";
    $params[] = $userId;
    $types .= 's';
} else {
    // Both roles: show requests where user is donor or recipient
    $where .= " AND (lr.recipient_id = ? OR lc.donor_id = ?)";
    $params[] = $userId;
    $params[] = $userId;
    $types .= 'ss';
}

$baseQuery = "
    SELECT lr.id, lr.recipient_id, lr.status, lr.preferred_date, lr.preferred_time, lr.location, lr.urgency, lr.note, lr.created_at,
           u1.first_name AS recipient_first, u1.last_name AS recipient_last, u1.email AS recipient_email,
           u2.first_name AS donor_first, u2.last_name AS donor_last, u2.email AS donor_email,
           lc.donor_id, lc.donor_response, lc.scheduled_at, lc.completion_status, lc.donor_remarks, lc.recipient_remarks
    FROM emergency_requests lr
    LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id
    LEFT JOIN users u1 ON u1.user_id = lr.recipient_id
    LEFT JOIN users u2 ON u2.user_id = lc.donor_id
    WHERE " . $where . "
    ORDER BY lr.preferred_date DESC, lr.preferred_time DESC
";

$stmt = $conn->prepare($baseQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats
$stats = ['all'=>0,'successful'=>0,'failed'=>0,'rescheduled'=>0,'normal'=>0,'urgent'=>0];
foreach ($requests as $r) {
    $stats['all']++;
    if (in_array($r['status'], ['completed'])) $stats['successful']++;
    if (in_array($r['status'], ['failed'])) $stats['failed']++;
    if (in_array($r['status'], ['rescheduled'])) $stats['rescheduled']++;
    $u = strtolower($r['urgency'] ?? 'normal');
    if ($u === 'normal' || $u === 'low') $stats['normal']++;
    if (in_array($u, ['urgent','high','critical'])) $stats['urgent']++;
}

$pageTitle = $isAdmin ? 'Blood Donations Request Manager (Admin)' : 'My Donation Requests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        .filter-bar { background: #fff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .filter-bar select, .filter-bar input { padding: 0.5rem; border-radius: 6px; border: 1px solid #ddd; }
        .stat-pill { display: inline-block; padding: 0.35rem 0.8rem; border-radius: 20px; font-size: 0.85rem; margin: 0 0.25rem 0.25rem 0; cursor: pointer; }
        .stat-pill.active { background: #b5002a; color: #fff; }
        .stat-pill:not(.active) { background: #f0f0f0; color: #333; }
        .request-card { background: #fff; border-radius: 8px; padding: 1rem 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-left: 4px solid #b5002a; }
        .request-card.completed { border-left-color: #27ae60; }
        .request-card.failed { border-left-color: #e74c3c; }
        .request-card.rescheduled { border-left-color: #f39c12; }
    </style>
</head>
<body>
    <?php include('assets/includes/header.php'); ?>
    <main class="container py-5">
        <h1 class="mb-4"><i class="fas fa-tint"></i> <?= htmlspecialchars($pageTitle) ?></h1>

        <!-- Stats pills -->
        <div class="mb-3">
            <a href="?<?= http_build_query(array_merge($_GET, ['status'=>'all'])) ?>" class="stat-pill <?= $statusFilter==='all'?'active':'' ?>">All (<?= $stats['all'] ?>)</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['status'=>'completed'])) ?>" class="stat-pill <?= $statusFilter==='completed'?'active':'' ?>">Successful (<?= $stats['successful'] ?>)</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['status'=>'failed'])) ?>" class="stat-pill <?= $statusFilter==='failed'?'active':'' ?>">Failed (<?= $stats['failed'] ?>)</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['status'=>'rescheduled'])) ?>" class="stat-pill <?= $statusFilter==='rescheduled'?'active':'' ?>">Rescheduled (<?= $stats['rescheduled'] ?>)</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['urgency'=>'normal'])) ?>" class="stat-pill <?= $urgencyFilter==='normal'?'active':'' ?>">Normal</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['urgency'=>'high'])) ?>" class="stat-pill <?= $urgencyFilter==='high'?'active':'' ?>">Urgent</a>
        </div>

        <!-- Filters -->
        <form class="filter-bar" method="get">
            <?php foreach ($_GET as $k => $v): if ($k === 'status' || $k === 'urgency' || $k === 'year' || $k === 'month' || $k === 'from' || $k === 'to') continue; ?>
            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
            <?php endforeach; ?>
            <label>Year:</label>
            <select name="year">
                <option value="">Any</option>
                <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                <option value="<?= $y ?>" <?= $yearFilter===$y?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <label class="ml-2">Month:</label>
            <select name="month">
                <option value="">Any</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $monthFilter===$m?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
            <label class="ml-2">From:</label>
            <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
            <label class="ml-2">To:</label>
            <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="urgency" value="<?= htmlspecialchars($urgencyFilter) ?>">
            <button type="submit" class="btn btn-primary ml-2">Apply</button>
        </form>

        <!-- Request list -->
        <?php if (count($requests) === 0): ?>
        <p class="text-muted">No donation requests found.</p>
        <?php else: ?>
        <?php foreach ($requests as $r): ?>
        <div class="request-card <?= htmlspecialchars($r['status'] ?? '') ?>">
            <div class="d-flex justify-content-between flex-wrap">
                <div>
                    <strong>#<?= (int)$r['id'] ?></strong>
                    <span class="badge ml-2"><?= htmlspecialchars($r['status'] ?? 'pending') ?></span>
                    <span class="badge badge-info ml-1"><?= htmlspecialchars($r['urgency'] ?? 'normal') ?></span>
                </div>
                <div><?= htmlspecialchars(format_display_date(($r['preferred_date'] ?? $r['created_at']) . ' ' . ($r['preferred_time'] ?? '00:00'))) ?></div>
            </div>
            <div class="mt-2">
                <strong>Recipient:</strong> <?= htmlspecialchars(trim(($r['recipient_first']??'').' '.($r['recipient_last']??''))) ?>
                <?php if (!empty($r['donor_first']) || !empty($r['donor_last'])): ?>
                | <strong>Donor:</strong> <?= htmlspecialchars(trim(($r['donor_first']??'').' '.($r['donor_last']??''))) ?>
                <?php endif; ?>
            </div>
            <div class="mt-1"><strong>Location:</strong> <?= htmlspecialchars($r['location'] ?? '') ?></div>
            <?php if (!empty($r['note'])): ?><div class="mt-1 text-muted"><small><?= htmlspecialchars($r['note']) ?></small></div><?php endif; ?>
            <?php if (!empty($r['donor_remarks']) || !empty($r['recipient_remarks'])): ?>
            <div class="mt-2 text-muted"><small><strong>Remarks:</strong> <?= htmlspecialchars($r['donor_remarks'] ?? $r['recipient_remarks'] ?? '') ?></small></div>
            <?php endif; ?>
            <div class="mt-2">
                <a href="donation-request-detail?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i> View Details</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>
    <?php include('assets/includes/footer.php'); ?>
</body>
</html>
