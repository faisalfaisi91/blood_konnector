<?php
session_start();
require_once __DIR__ . '/openconn.php';

if (empty($_SESSION['super_admin_logged_in'])) {
    header('Location: superadmin-login.php');
    exit();
}

$adminName = $_SESSION['super_admin_name'] ?? 'Super Admin';

// Check if lifeline_profiles has approval_status column
$hasApprovalStatus = false;
$colCheck = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lifeline_profiles' AND COLUMN_NAME = 'approval_status'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasApprovalStatus = true;
}
$colCheck && $colCheck->free();

// Handle approval/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lifeline_action'])) {
    $profileId = (int)($_POST['profile_id'] ?? 0);
    $action = $_POST['lifeline_action'] ?? '';
    if ($profileId && in_array($action, ['approve', 'reject']) && $hasApprovalStatus) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE lifeline_profiles SET approval_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $profileId);
        if ($stmt->execute()) {
            $actionMessage = $action === 'approve' ? "Lifeline profile #{$profileId} approved." : "Lifeline profile #{$profileId} rejected.";
        }
        $stmt->close();
    }
}

// Lifeline members (all profiles)
// lifeline_profiles may be the real table (with full_name, cnic_national_id, city, health_condition)
// or a view of emergency_profiles (with health_notes, no full_name/cnic/city)
$hasFullName = false;
$hasCnic = false;
$hasCity = false;
$hasHealthCondition = false;
$colCheck2 = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lifeline_profiles' AND COLUMN_NAME IN ('full_name','cnic_national_id','city','health_condition','health_notes')");
if ($colCheck2) {
    while ($c = $colCheck2->fetch_assoc()) {
        if ($c['COLUMN_NAME'] === 'full_name') $hasFullName = true;
        if ($c['COLUMN_NAME'] === 'cnic_national_id') $hasCnic = true;
        if ($c['COLUMN_NAME'] === 'city') $hasCity = true;
        if ($c['COLUMN_NAME'] === 'health_condition') $hasHealthCondition = true;
    }
    $colCheck2->free();
}
$nameExpr = $hasFullName ? "lp.full_name" : "CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))";
$cnicExpr = $hasCnic ? "lp.cnic_national_id" : "''";
$cityExpr = $hasCity ? "lp.city" : "COALESCE(r.location, '')";
$healthExpr = $hasHealthCondition ? "lp.health_condition" : "COALESCE(lp.health_notes, '')";
$membersQuery = "
    SELECT lp.id, lp.recipient_id, {$nameExpr} AS full_name, {$cnicExpr} AS cnic_national_id,
           lp.blood_type, {$cityExpr} AS city, {$healthExpr} AS health_condition,
           lp.created_at" . ($hasApprovalStatus ? ", lp.approval_status" : "") . ",
           u.email
    FROM lifeline_profiles lp
    LEFT JOIN users u ON u.user_id = lp.recipient_id
    LEFT JOIN recipients r ON r.user_id = lp.recipient_id
    ORDER BY lp.created_at DESC
    LIMIT 100
";
$membersResult = $conn->query($membersQuery);
$members = [];
if ($membersResult) {
    while ($row = $membersResult->fetch_assoc()) {
        $members[] = $row;
    }
    $membersResult->free();
}

// Lifeline stats (use lifeline_requests if table exists)
$stats = [
    'total_members' => count($members),
    'total_requests' => 0,
    'pending' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'pending_approval' => 0,
];

$tblCheck = $conn->query("SHOW TABLES LIKE 'lifeline_requests'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $counts = $conn->query("SELECT status, COUNT(*) as total FROM lifeline_requests GROUP BY status");
    if ($counts) {
        while ($row = $counts->fetch_assoc()) {
            $stats[$row['status']] = (int)$row['total'];
            $stats['total_requests'] += (int)$row['total'];
        }
        $counts->free();
    }
}

if ($hasApprovalStatus) {
    $paRes = $conn->query("SELECT COUNT(*) as cnt FROM lifeline_profiles WHERE approval_status = 'pending'");
    if ($paRes && $row = $paRes->fetch_assoc()) {
        $stats['pending_approval'] = (int)$row['cnt'];
    }
    $paRes && $paRes->free();
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lifeline_members.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Recipient ID', 'Full Name', 'CNIC', 'Blood Type', 'City', 'Health Condition', 'Email', 'Joined' . ($hasApprovalStatus ? ', Status' : '')]);
    foreach ($members as $m) {
        fputcsv($out, [
            $m['id'],
            $m['recipient_id'],
            $m['full_name'],
            $m['cnic_national_id'],
            $m['blood_type'],
            $m['city'],
            $m['health_condition'] ?? '',
            $m['email'] ?? '',
            date('Y-m-d', strtotime($m['created_at'])) . ($hasApprovalStatus ? ',' . ($m['approval_status'] ?? 'approved') : '')
        ]);
    }
    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifeline Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/../assets/includes/link-js.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-6xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">LifeLine Oversight</h1>
                <p class="text-sm text-gray-500">Super Admin: <?= htmlspecialchars($adminName) ?></p>
            </div>
            <div class="flex items-center space-x-2">
                <a href="emergency-dashboard.php" class="text-sm text-blue-600 hover:underline">Emergency Dashboard</a>
                <span class="text-gray-400">|</span>
                <a href="superadmin-logout.php" class="text-sm text-red-600 hover:underline">Logout</a>
            </div>
        </div>

        <?php if (!empty($actionMessage)): ?>
            <p class="text-sm text-green-600 mb-4"><?= htmlspecialchars($actionMessage); ?></p>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Total Lifeline Members</p>
                <p class="text-2xl font-semibold text-gray-800"><?= $stats['total_members'] ?></p>
            </div>
            <?php if ($hasApprovalStatus): ?>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Pending Approval</p>
                <p class="text-2xl font-semibold text-yellow-600"><?= $stats['pending_approval'] ?></p>
            </div>
            <?php endif; ?>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Total Requests</p>
                <p class="text-2xl font-semibold text-blue-600"><?= $stats['total_requests'] ?></p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Completed</p>
                <p class="text-2xl font-semibold text-green-600"><?= $stats['completed'] ?? 0 ?></p>
            </div>
        </div>

        <div class="bg-white shadow rounded mt-6">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Lifeline Members</h2>
                <a href="?export=csv" class="text-blue-600 text-sm underline">Export CSV</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-600">ID</th>
                            <th class="px-4 py-2 text-left text-gray-600">Name</th>
                            <th class="px-4 py-2 text-left text-gray-600">Blood Type</th>
                            <th class="px-4 py-2 text-left text-gray-600">City</th>
                            <th class="px-4 py-2 text-left text-gray-600">Health Condition</th>
                            <th class="px-4 py-2 text-left text-gray-600">Joined</th>
                            <?php if ($hasApprovalStatus): ?>
                            <th class="px-4 py-2 text-left text-gray-600">Status</th>
                            <th class="px-4 py-2 text-left text-gray-600">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($members)): ?>
                            <?php foreach ($members as $m): ?>
                                <tr class="border-t">
                                    <td class="px-4 py-2 font-medium text-gray-800">#<?= (int)$m['id'] ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($m['full_name']) ?></td>
                                    <td class="px-4 py-2 text-gray-700"><span class="px-2 py-1 rounded bg-red-100 text-red-800"><?= htmlspecialchars($m['blood_type']) ?></span></td>
                                    <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($m['city']) ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($m['health_condition'] ?? '-') ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?= format_display_date($m['created_at'], false) ?></td>
                                    <?php if ($hasApprovalStatus): ?>
                                    <td class="px-4 py-2 text-gray-700">
                                        <?php $ast = $m['approval_status'] ?? 'approved'; ?>
                                        <span class="px-2 py-1 rounded text-xs <?= $ast === 'approved' ? 'bg-green-100 text-green-800' : ($ast === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>"><?= htmlspecialchars($ast) ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        <?php if (($m['approval_status'] ?? 'approved') === 'pending'): ?>
                                        <form method="POST" class="inline-flex gap-1">
                                            <input type="hidden" name="lifeline_action" value="approve">
                                            <input type="hidden" name="profile_id" value="<?= (int)$m['id'] ?>">
                                            <button type="submit" class="bg-green-600 text-white px-2 py-1 rounded text-xs">Approve</button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="lifeline_action" value="reject">
                                            <input type="hidden" name="profile_id" value="<?= (int)$m['id'] ?>">
                                            <button type="submit" class="bg-red-600 text-white px-2 py-1 rounded text-xs">Reject</button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $hasApprovalStatus ? 8 : 6 ?>" class="px-4 py-4 text-center text-gray-500">No Lifeline members yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!$hasApprovalStatus): ?>
        <p class="text-sm text-gray-500 mt-4">To enable Approve/Reject for join requests, run: <code class="bg-gray-200 px-1 rounded">ALTER TABLE lifeline_profiles ADD COLUMN approval_status ENUM('pending','approved','rejected') DEFAULT 'approved';</code></p>
        <?php endif; ?>
    </div>
</body>
</html>
