<?php
session_start();
require_once __DIR__ . '/openconn.php';

if (empty($_SESSION['super_admin_logged_in'])) {
    header('Location: superadmin-login.php');
    exit();
}

$adminName = $_SESSION['super_admin_name'] ?? 'Super Admin';

// Filters
$allowedStatuses = ['pending','confirmed','completed','failed','rescheduled','expired'];
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true) ? $_GET['status'] : '';
$dateFrom = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
$dateTo = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';

$where = "WHERE 1=1";
if ($statusFilter) {
    $esc = $conn->real_escape_string($statusFilter);
    $where .= " AND lr.status = '{$esc}'";
}
if ($dateFrom) {
    $esc = $conn->real_escape_string($dateFrom);
    $where .= " AND lr.preferred_date >= '{$esc}'";
}
if ($dateTo) {
    $esc = $conn->real_escape_string($dateTo);
    $where .= " AND lr.preferred_date <= '{$esc}'";
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lifeline_requests.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Recipient','Donor','Status','Date','Time','Urgency','Location']);
    $exp = $conn->query("
        SELECT lr.id, lr.status, lr.preferred_date, lr.preferred_time, lr.location, lr.urgency,
               u1.first_name AS recipient_first, u1.last_name AS recipient_last,
               u2.first_name AS donor_first, u2.last_name AS donor_last
        FROM lifeline_requests lr
        LEFT JOIN lifeline_confirmations lc ON lc.request_id = lr.id
        LEFT JOIN users u1 ON u1.user_id = lr.recipient_id
        LEFT JOIN users u2 ON u2.user_id = lc.donor_id
        {$where}
        ORDER BY lr.updated_at DESC
    ");
    while ($r = $exp->fetch_assoc()) {
        fputcsv($out, [
            $r['id'],
            trim($r['recipient_first'] . ' ' . $r['recipient_last']),
            trim(($r['donor_first'] ?? '') . ' ' . ($r['donor_last'] ?? '')),
            $r['status'],
            $r['preferred_date'],
            $r['preferred_time'],
            $r['urgency'],
            $r['location']
        ]);
    }
    fclose($out);
    exit();
}

// Handle manual assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_request'])) {
    $reqId = (int)($_POST['request_id'] ?? 0);
    $donorId = trim($_POST['donor_id'] ?? '');
    if ($reqId && $donorId) {
        // Validate donor exists and is donor
        $chk = $conn->prepare("SELECT u.user_id FROM users u LEFT JOIN donors d ON d.user_id = u.user_id WHERE u.user_id = ? AND (u.is_donor = 1 OR d.user_id IS NOT NULL) LIMIT 1");
        $chk->bind_param("s", $donorId);
        $chk->execute();
        $res = $chk->get_result();
        if ($res->num_rows === 0) {
            $assignError = "User {$donorId} not found or not a donor.";
        } else {
            // Validate request exists
            $rq = $conn->prepare("SELECT id FROM lifeline_requests WHERE id = ? LIMIT 1");
            $rq->bind_param("i", $reqId);
            $rq->execute();
            $hasReq = $rq->get_result()->num_rows > 0;
            $rq->close();
            if (!$hasReq) {
                $assignError = "Request #{$reqId} not found.";
            } else {
                $stmt = $conn->prepare("UPDATE lifeline_confirmations SET donor_id=? WHERE request_id=?");
                $stmt->bind_param("si", $donorId, $reqId);
                $stmt->execute();
                $stmt->close();
                $stmt2 = $conn->prepare("UPDATE lifeline_requests SET status='pending', responder_timeout_at=DATE_ADD(NOW(), INTERVAL 12 HOUR) WHERE id=?");
                $stmt2->bind_param("i", $reqId);
                $stmt2->execute();
                $stmt2->close();
                $assignMessage = "Assigned donor to request #{$reqId}.";
            }
        }
        $chk->close();
    } else {
        $assignError = "Missing request id or donor id.";
    }
}

// Basic metrics
$metrics = [
    'total_requests' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'failed' => 0,
    'links' => 0,
    'feedback' => 0,
];

$counts = $conn->query("SELECT status, COUNT(*) as total FROM lifeline_requests GROUP BY status");
if ($counts) {
    while ($row = $counts->fetch_assoc()) {
        $metrics[$row['status']] = (int)$row['total'];
        $metrics['total_requests'] += (int)$row['total'];
    }
}

$linksRes = $conn->query("SELECT COUNT(*) as total FROM lifeline_links");
if ($linksRes && $row = $linksRes->fetch_assoc()) {
    $metrics['links'] = (int)$row['total'];
}

$feedbackRes = $conn->query("SELECT COUNT(*) as total FROM lifeline_feedback");
if ($feedbackRes && $row = $feedbackRes->fetch_assoc()) {
    $metrics['feedback'] = (int)$row['total'];
}

$recent = $conn->query("
    SELECT lr.id, lr.status, lr.preferred_date, lr.preferred_time, lr.location, lr.urgency,
           u1.first_name AS recipient_first, u1.last_name AS recipient_last,
           u2.first_name AS donor_first, u2.last_name AS donor_last
    FROM lifeline_requests lr
    LEFT JOIN lifeline_confirmations lc ON lc.request_id = lr.id
    LEFT JOIN users u1 ON u1.user_id = lr.recipient_id
    LEFT JOIN users u2 ON u2.user_id = lc.donor_id
    {$where}
    ORDER BY lr.updated_at DESC
    LIMIT 30
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifeline Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-6xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Lifeline Oversight</h1>
                <p class="text-sm text-gray-500">Super Admin: <?= htmlspecialchars($adminName) ?></p>
            </div>
            <div class="flex items-center space-x-2">
                <a href="superadmin-logout.php" class="text-sm text-red-600 hover:underline">Logout</a>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Total Requests</p>
                <p class="text-2xl font-semibold text-gray-800"><?= $metrics['total_requests'] ?></p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Pending</p>
                <p class="text-2xl font-semibold text-yellow-600"><?= $metrics['pending'] ?></p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Confirmed</p>
                <p class="text-2xl font-semibold text-blue-600"><?= $metrics['confirmed'] ?></p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Completed</p>
                <p class="text-2xl font-semibold text-green-600"><?= $metrics['completed'] ?></p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Failed / Declined</p>
                <p class="text-2xl font-semibold text-red-600"><?= $metrics['failed'] ?></p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Active Links</p>
                <p class="text-2xl font-semibold text-indigo-600"><?= $metrics['links'] ?></p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Feedback Received</p>
                <p class="text-2xl font-semibold text-purple-600"><?= $metrics['feedback'] ?></p>
            </div>
        </div>

        <div class="bg-white shadow rounded mt-6">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Recent Lifeline Requests</h2>
                    <?php if (!empty($assignMessage)): ?>
                        <p class="text-sm text-green-600"><?= htmlspecialchars($assignMessage); ?></p>
                    <?php elseif (!empty($assignError)): ?>
                        <p class="text-sm text-red-600"><?= htmlspecialchars($assignError); ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-2">
                    <form method="GET" class="flex items-center space-x-2">
                        <select name="status" class="border rounded px-2 py-1 text-sm">
                            <option value="">All statuses</option>
                            <?php foreach ($allowedStatuses as $st): ?>
                                <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" class="border rounded px-2 py-1 text-sm">
                        <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" class="border rounded px-2 py-1 text-sm">
                        <button type="submit" class="bg-gray-700 text-white px-3 py-1 rounded text-sm">Filter</button>
                        <a href="?export=csv<?= $statusFilter ? '&status='.$statusFilter : '' ?><?= $dateFrom ? '&from='.$dateFrom : '' ?><?= $dateTo ? '&to='.$dateTo : '' ?>" class="text-blue-600 text-sm underline">Export CSV</a>
                    </form>
                    <form method="POST" class="flex items-center space-x-2">
                        <input type="hidden" name="assign_request" value="1">
                        <input type="number" name="request_id" placeholder="Request ID" class="border rounded px-2 py-1 text-sm" required>
                        <input type="text" name="donor_id" placeholder="Donor User ID" class="border rounded px-2 py-1 text-sm" required>
                        <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Assign</button>
                    </form>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-600">ID</th>
                            <th class="px-4 py-2 text-left text-gray-600">Recipient</th>
                            <th class="px-4 py-2 text-left text-gray-600">Donor</th>
                            <th class="px-4 py-2 text-left text-gray-600">Status</th>
                            <th class="px-4 py-2 text-left text-gray-600">When</th>
                            <th class="px-4 py-2 text-left text-gray-600">Urgency</th>
                            <th class="px-4 py-2 text-left text-gray-600">Location</th>
                            <th class="px-4 py-2 text-left text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent && $recent->num_rows > 0): ?>
                            <?php while ($row = $recent->fetch_assoc()): ?>
                                <tr class="border-t">
                                    <td class="px-4 py-2 font-medium text-gray-800">#<?= (int)$row['id'] ?></td>
                                    <td class="px-4 py-2 text-gray-700">
                                        <?= htmlspecialchars(trim($row['recipient_first'] . ' ' . $row['recipient_last'])) ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        <?= htmlspecialchars(trim(($row['donor_first'] ?? '') . ' ' . ($row['donor_last'] ?? ''))) ?: 'Unassigned' ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        <span class="px-2 py-1 rounded text-xs bg-gray-100"><?= htmlspecialchars($row['status']) ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        <?= htmlspecialchars($row['preferred_date'] . ' ' . $row['preferred_time']) ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($row['urgency']) ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($row['location']) ?></td>
                                    <td class="px-4 py-2 text-gray-700">
                                        <form method="POST" class="flex items-center space-x-2">
                                            <input type="hidden" name="assign_request" value="1">
                                            <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                            <input type="text" name="donor_id" placeholder="Donor user id" class="border rounded px-2 py-1 text-sm w-32" required>
                                            <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm">Assign</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-4 py-4 text-center text-gray-500">No lifeline requests found yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

