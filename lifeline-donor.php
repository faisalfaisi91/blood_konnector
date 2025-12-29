<?php
session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('donor', 'sign-in.php');
$userId = $_SESSION['user_id'];

// Fetch lifeline notifications for this donor
$notifications = [];
$notifStmt = $conn->prepare("
    SELECT ln.*
    FROM lifeline_notifications ln
    WHERE ln.user_id = ?
      AND ln.channel = 'in_app'
      AND ln.template_key IN ('lifeline_new_request', 'lifeline_donor_approved')
    ORDER BY ln.created_at DESC
    LIMIT 20
");
$notifStmt->bind_param("s", $userId);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
while ($row = $notifResult->fetch_assoc()) {
    // Parse payload to get request details
    $payload = json_decode($row['payload'] ?? '{}', true) ?: [];
    $requestId = $payload['request_id'] ?? null;
    
    // Fetch request details if available
    if ($requestId) {
        $reqStmt = $conn->prepare("SELECT lr.id, lr.preferred_date, lr.preferred_time, lr.location, lr.blood_type, lr.city, u.first_name AS recipient_first, u.last_name AS recipient_last
                                   FROM lifeline_requests lr
                                   LEFT JOIN users u ON u.user_id = lr.recipient_id
                                   WHERE lr.id = ? LIMIT 1");
        $reqStmt->bind_param("i", $requestId);
        $reqStmt->execute();
        $reqResult = $reqStmt->get_result();
        if ($reqResult && $reqResult->num_rows > 0) {
            $reqData = $reqResult->fetch_assoc();
            $row = array_merge($row, $reqData);
        }
        $reqStmt->close();
    }
    
    $notifications[] = $row;
}
$notifStmt->close();

// Count unread notifications
$unreadCount = 0;
$unreadStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM lifeline_notifications WHERE user_id = ? AND channel = 'in_app' AND status = 'queued' AND template_key IN ('lifeline_new_request', 'lifeline_donor_approved')");
$unreadStmt->bind_param("s", $userId);
$unreadStmt->execute();
$unreadResult = $unreadStmt->get_result();
if ($unreadResult && $unreadResult->num_rows > 0) {
    $unreadRow = $unreadResult->fetch_assoc();
    $unreadCount = (int)$unreadRow['cnt'];
}
$unreadStmt->close();

// Get donor's blood type and location for matching
$donorInfo = [];
$donorStmt = $conn->prepare("SELECT blood_type, location FROM donors WHERE user_id = ? LIMIT 1");
$donorStmt->bind_param("s", $userId);
$donorStmt->execute();
$donorResult = $donorStmt->get_result();
if ($donorResult && $donorResult->num_rows > 0) {
    $donorInfo = $donorResult->fetch_assoc();
}
$donorStmt->close();

$donorBloodType = $donorInfo['blood_type'] ?? '';
$donorLocation = $donorInfo['location'] ?? '';

// Fetch assigned requests (where donor is already assigned)
$assignedRequests = [];
$stmt = $conn->prepare("
    SELECT lr.*, lc.scheduled_at, lc.reschedule_payload, lc.donor_response,
           u.first_name AS recipient_first, u.last_name AS recipient_last
    FROM lifeline_requests lr
    JOIN lifeline_confirmations lc ON lc.request_id = lr.id
    JOIN users u ON u.user_id = lr.recipient_id
    WHERE lc.donor_id = ?
    ORDER BY lr.created_at DESC
    LIMIT 30
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $assignedRequests[] = $row;
}
$stmt->close();

// Fetch available matching requests (pending requests matching donor's blood type and city)
$availableRequests = [];
if (!empty($donorBloodType)) {
    // Check if blood_type and city columns exist
    $checkCols = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lifeline_requests' AND COLUMN_NAME IN ('blood_type', 'city')");
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
        $availableStmt = $conn->prepare("
            SELECT lr.*, lc.scheduled_at, lc.reschedule_payload, lc.donor_response, lc.donor_id,
                   u.first_name AS recipient_first, u.last_name AS recipient_last
            FROM lifeline_requests lr
            LEFT JOIN lifeline_confirmations lc ON lc.request_id = lr.id
            JOIN users u ON u.user_id = lr.recipient_id
            WHERE lr.status = 'pending'
              AND lr.blood_type = ?
              AND (lr.city = ? OR LOWER(lr.city) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', lr.city, '%')))
              AND (lc.donor_id IS NULL OR lc.donor_id != ?)
              AND lr.id NOT IN (SELECT request_id FROM lifeline_confirmations WHERE donor_id = ? AND donor_response = 'approve')
            ORDER BY lr.created_at DESC
            LIMIT 20
        ");
        $availableStmt->bind_param("ssssss", $donorBloodType, $donorLocation, $donorLocation, $donorLocation, $userId, $userId);
    } elseif ($hasBloodType) {
        // Match by blood_type only (city column doesn't exist yet)
        $availableStmt = $conn->prepare("
            SELECT lr.*, lc.scheduled_at, lc.reschedule_payload, lc.donor_response, lc.donor_id,
                   u.first_name AS recipient_first, u.last_name AS recipient_last
            FROM lifeline_requests lr
            LEFT JOIN lifeline_confirmations lc ON lc.request_id = lr.id
            JOIN users u ON u.user_id = lr.recipient_id
            WHERE lr.status = 'pending'
              AND lr.blood_type = ?
              AND (lc.donor_id IS NULL OR lc.donor_id != ?)
              AND lr.id NOT IN (SELECT request_id FROM lifeline_confirmations WHERE donor_id = ? AND donor_response = 'approve')
            ORDER BY lr.created_at DESC
            LIMIT 20
        ");
        $availableStmt->bind_param("sss", $donorBloodType, $userId, $userId);
    } else {
        // Fallback: match by donor's blood type from recipient profile (old method)
        $availableStmt = $conn->prepare("
            SELECT lr.*, lc.scheduled_at, lc.reschedule_payload, lc.donor_response, lc.donor_id,
                   u.first_name AS recipient_first, u.last_name AS recipient_last,
                   r.blood_type AS recipient_blood_type
            FROM lifeline_requests lr
            LEFT JOIN lifeline_confirmations lc ON lc.request_id = lr.id
            JOIN users u ON u.user_id = lr.recipient_id
            LEFT JOIN recipients r ON r.user_id = lr.recipient_id
            WHERE lr.status = 'pending'
              AND r.blood_type = ?
              AND (LOWER(r.location) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', r.location, '%')))
              AND (lc.donor_id IS NULL OR lc.donor_id != ?)
              AND lr.id NOT IN (SELECT request_id FROM lifeline_confirmations WHERE donor_id = ? AND donor_response = 'approve')
            ORDER BY lr.created_at DESC
            LIMIT 20
        ");
        $availableStmt->bind_param("sssss", $donorBloodType, $donorLocation, $donorLocation, $userId, $userId);
    }
    
    if (isset($availableStmt)) {
        $availableStmt->execute();
        $availableRes = $availableStmt->get_result();
        while ($row = $availableRes->fetch_assoc()) {
            $availableRequests[] = $row;
        }
        $availableStmt->close();
    }
}

// Combine assigned and available requests (assigned first)
$requests = array_merge($assignedRequests, $availableRequests);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifeline Link - Donor</title>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        .notification-item {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .notification-item:hover {
            background-color: #f8f9fa !important;
            transform: translateX(5px);
        }
        .notification-item.bg-light {
            font-weight: 500;
        }
        .notifications-list {
            scrollbar-width: thin;
        }
        .notifications-list::-webkit-scrollbar {
            width: 6px;
        }
        .notifications-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .notifications-list::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .notifications-list::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .card { box-shadow: 0 10px 30px rgba(0,0,0,0.05); border-radius: 16px; }
        .badge { border-radius: 999px; padding: 6px 12px; font-size: 12px; }
        .status-pending { background:#fff4e6; color:#d97706; }
        .status-confirmed { background:#e0f2fe; color:#0369a1; }
        .status-completed { background:#ecfdf3; color:#15803d; }
        .status-failed { background:#fef2f2; color:#b91c1c; }
        .status-rescheduled { background:#f5f3ff; color:#6d28d9; }
        .status-expired { background:#f3f4f6; color:#374151; }
        .countdown { font-weight:600; color:#111827; }
    </style>
</head>
<body>
<?php include('assets/includes/header.php'); ?>
<section class="container py-5">
    <!-- Notifications Tab -->
    <?php if ($unreadCount > 0 || !empty($notifications)): ?>
    <div class="card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="fas fa-bell me-2"></i>Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger"><?= $unreadCount; ?> new</span>
                <?php endif; ?>
            </h5>
            <button class="btn btn-sm btn-outline-secondary" onclick="markAllNotificationsRead()">Mark all as read</button>
        </div>
        <div class="notifications-list" style="max-height: 400px; overflow-y: auto;">
            <?php if (empty($notifications)): ?>
                <p class="text-muted text-center py-3">No notifications yet.</p>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <?php
                        $payload = json_decode($notif['payload'] ?? '{}', true) ?: [];
                        $isRead = $notif['status'] === 'sent';
                        $notifClass = $isRead ? '' : 'bg-light border-start border-3 border-primary';
                        $requestId = $payload['request_id'] ?? $notif['request_id'] ?? null;
                    ?>
                    <div class="notification-item p-3 mb-2 rounded <?= $notifClass; ?>" data-notification-id="<?= $notif['id']; ?>" data-request-id="<?= $requestId; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <?php if ($notif['template_key'] === 'lifeline_new_request'): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fas fa-heartbeat text-danger"></i>
                                        <strong>New Lifeline Request</strong>
                                    </div>
                                    <p class="mb-1">
                                        A new lifeline request matches your profile (Blood Type: <?= htmlspecialchars($notif['blood_type'] ?? 'N/A'); ?>, City: <?= htmlspecialchars($notif['city'] ?? 'N/A'); ?>)
                                    </p>
                                    <?php if ($requestId): ?>
                                        <a href="lifeline-donor.php#request-<?= $requestId; ?>" class="btn btn-sm btn-primary mt-2">View Request</a>
                                    <?php endif; ?>
                                <?php elseif ($notif['template_key'] === 'lifeline_donor_approved'): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <strong>Request Approved</strong>
                                    </div>
                                    <p class="mb-1">
                                        Your lifeline request has been approved by a donor.
                                    </p>
                                    <?php if ($requestId): ?>
                                        <a href="lifeline-recipient.php#request-<?= $requestId; ?>" class="btn btn-sm btn-success mt-2">View Request</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <i class="far fa-clock me-1"></i><?= date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                                </small>
                            </div>
                            <?php if (!$isRead): ?>
                                <button class="btn btn-sm btn-link text-primary mark-read-btn" onclick="markNotificationRead(<?= $notif['id']; ?>)">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">Lifeline Requests</h4>
                <small class="text-muted">
                    <?php if (!empty($assignedRequests)): ?>
                        <?= count($assignedRequests); ?> assigned, 
                    <?php endif; ?>
                    <?= count($availableRequests); ?> available matching requests
                </small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Recipient</th>
                        <th>Blood Type</th>
                        <th>Status</th>
                        <th>When</th>
                        <th>Location</th>
                        <th>Urgency</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No lifeline requests available. Check back later!</td></tr>
                <?php else: ?>
                    <?php if (!empty($assignedRequests)): ?>
                        <tr class="table-info">
                            <td colspan="8" class="fw-bold">Assigned Requests</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($assignedRequests as $req): ?>
                        <?php
                            $statusClass = 'status-' . $req['status'];
                            $when = htmlspecialchars($req['preferred_date'] . ' ' . $req['preferred_time']);
                            $recipientName = trim($req['recipient_first'] . ' ' . $req['recipient_last']);
                            $payload = $req['reschedule_payload'] ? json_decode($req['reschedule_payload'], true) : null;
                            $requestBloodType = $req['blood_type'] ?? 'N/A';
                        ?>
                        <tr data-request-id="<?= (int)$req['id']; ?>"
                            data-date="<?= htmlspecialchars($req['preferred_date']); ?>"
                            data-time="<?= htmlspecialchars($req['preferred_time']); ?>"
                            data-location="<?= htmlspecialchars($req['location']); ?>">
                            <td>#<?= (int)$req['id']; ?></td>
                            <td><?= htmlspecialchars($recipientName); ?></td>
                            <td><span class="badge bg-danger"><?= htmlspecialchars($requestBloodType); ?></span></td>
                            <td>
                                <span class="badge <?= $statusClass; ?>"><?= htmlspecialchars($req['status']); ?></span>
                                <?php if ($req['status'] === 'confirmed' && !empty($req['scheduled_at'])): ?>
                                    <div class="countdown small" data-countdown="<?= htmlspecialchars($req['scheduled_at']); ?>"></div>
                                <?php elseif ($req['status'] === 'rescheduled' && $payload): ?>
                                    <div class="small text-muted">
                                        Waiting recipient decision<br>
                                        Suggested: <?= htmlspecialchars($payload['suggested_at'] ?? ''); ?><br>
                                        At: <?= htmlspecialchars($payload['location'] ?? ''); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= $when; ?></td>
                            <td><?= htmlspecialchars($req['location']); ?></td>
                            <td class="text-capitalize"><?= htmlspecialchars($req['urgency']); ?></td>
                            <td>
                                <?php if (in_array($req['status'], ['pending','rescheduled'])): ?>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-success donor-response" data-response="approve">Approve</button>
                                        <button class="btn btn-outline-danger donor-response" data-response="decline">Decline</button>
                                        <button class="btn btn-outline-primary donor-response" data-response="reschedule">Resched.</button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($availableRequests)): ?>
                        <tr class="table-warning">
                            <td colspan="8" class="fw-bold">Available Matching Requests (Your Blood Type: <?= htmlspecialchars($donorBloodType); ?>, Your City: <?= htmlspecialchars($donorLocation); ?>)</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($availableRequests as $req): ?>
                        <?php
                            $statusClass = 'status-' . $req['status'];
                            $when = htmlspecialchars($req['preferred_date'] . ' ' . $req['preferred_time']);
                            $recipientName = trim($req['recipient_first'] . ' ' . $req['recipient_last']);
                            $payload = $req['reschedule_payload'] ? json_decode($req['reschedule_payload'], true) : null;
                            $isAssigned = !empty($req['donor_id']) && $req['donor_id'] === $userId;
                            $requestBloodType = $req['blood_type'] ?? 'N/A';
                        ?>
                        <tr data-request-id="<?= (int)$req['id']; ?>"
                            data-date="<?= htmlspecialchars($req['preferred_date']); ?>"
                            data-time="<?= htmlspecialchars($req['preferred_time']); ?>"
                            data-location="<?= htmlspecialchars($req['location'] ?? ''); ?>">
                            <td>#<?= (int)$req['id']; ?></td>
                            <td><?= htmlspecialchars($recipientName); ?></td>
                            <td><span class="badge bg-danger"><?= htmlspecialchars($requestBloodType); ?></span></td>
                            <td>
                                <span class="badge <?= $statusClass; ?>"><?= htmlspecialchars($req['status']); ?></span>
                                <?php if ($req['status'] === 'confirmed' && !empty($req['scheduled_at'])): ?>
                                    <div class="countdown small" data-countdown="<?= htmlspecialchars($req['scheduled_at']); ?>"></div>
                                <?php elseif ($req['status'] === 'rescheduled' && $payload): ?>
                                    <div class="small text-muted">
                                        Waiting recipient decision<br>
                                        Suggested: <?= htmlspecialchars($payload['suggested_at'] ?? ''); ?><br>
                                        At: <?= htmlspecialchars($payload['location'] ?? ''); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= $when; ?></td>
                            <td><?= htmlspecialchars($req['location'] ?? ''); ?></td>
                            <td class="text-capitalize"><?= htmlspecialchars($req['urgency'] ?? 'normal'); ?></td>
                            <td>
                                <?php if ($req['status'] === 'pending' && !$isAssigned): ?>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-success donor-response" data-response="approve">Approve</button>
                                        <button class="btn btn-outline-danger donor-response" data-response="decline">Decline</button>
                                        <button class="btn btn-outline-primary donor-response" data-response="reschedule">Resched.</button>
                                    </div>
                                <?php elseif (in_array($req['status'], ['pending','rescheduled']) && $isAssigned): ?>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-success donor-response" data-response="approve">Approve</button>
                                        <button class="btn btn-outline-danger donor-response" data-response="decline">Decline</button>
                                        <button class="btn btn-outline-primary donor-response" data-response="reschedule">Resched.</button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include('assets/includes/footer.php'); ?>
<?php include('assets/includes/link-js.php'); ?>

<!-- Reschedule Modal -->
<div class="modal fade" id="reschedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Suggest New Schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="reschedForm">
            <input type="hidden" id="reschedRequestId">
            <div class="mb-3">
                <label class="form-label">Date & Time</label>
                <input type="datetime-local" id="reschedDateTime" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Location / Hospital</label>
                <input type="text" id="reschedLocation" class="form-control" required placeholder="Hospital / address">
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="reschedSubmit">Send Reschedule</button>
      </div>
    </div>
  </div>
</div>

<script>
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
            const res = await fetch('assets/lib/lifeline-api.php', { method: 'POST', body: data });
            const json = await res.json();
            if (json.success) {
                location.reload();
            } else {
                btn.disabled = false;
                // Show a more user-friendly message if request is already assigned
                const errorMsg = json.error || 'Failed to submit response.';
                if (errorMsg.includes('already been assigned') || errorMsg.includes('already been accepted')) {
                    alert('⚠️ ' + errorMsg + '\n\nThis request is no longer available. Please check other available requests.');
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
            const res = await fetch('assets/lib/lifeline-api.php', { method: 'POST', body: data });
            const json = await res.json();
            if (json.success) {
                location.reload();
            } else {
                btn.disabled = false;
                // Show a more user-friendly message if request is already assigned
                const errorMsg = json.error || 'Failed to submit response.';
                if (errorMsg.includes('already been assigned') || errorMsg.includes('already been accepted')) {
                    alert('⚠️ ' + errorMsg + '\n\nThis request is no longer available. Please check other available requests.');
                } else {
                    alert(errorMsg);
                }
            }
            return;
        }

        if (response === 'reschedule') {
            // Open modal
            const modalEl = document.getElementById('reschedModal');
            const modal = new bootstrap.Modal(modalEl);
            document.getElementById('reschedRequestId').value = id;
            // Pre-fill datetime-local with preferred date/time if present
            if (preferredDate && preferredTime) {
                document.getElementById('reschedDateTime').value = `${preferredDate}T${preferredTime}`;
            } else {
                document.getElementById('reschedDateTime').value = '';
            }
            document.getElementById('reschedLocation').value = preferredLocation;
            modal.show();
        }
    });
});

document.getElementById('reschedSubmit').addEventListener('click', async () => {
    const id = document.getElementById('reschedRequestId').value;
    const dt = document.getElementById('reschedDateTime').value;
    const loc = document.getElementById('reschedLocation').value.trim();
    if (!id || !dt || !loc) {
        alert('Please fill date/time and location.');
        return;
    }
    const scheduledAt = dt.replace('T', ' ');
    const data = new FormData();
    data.append('action', 'donor_response');
    data.append('request_id', id);
    data.append('response', 'reschedule');
    data.append('scheduled_at', scheduledAt);
    data.append('location', loc);
    const res = await fetch('assets/lib/lifeline-api.php', { method: 'POST', body: data });
    const json = await res.json();
    if (json.success) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('reschedModal'));
        modal.hide();
        location.reload();
    } else {
        alert(json.error || 'Failed to submit reschedule.');
    }
});

function startCountdown() {
    document.querySelectorAll('[data-countdown]').forEach(el => {
        const target = new Date(el.dataset.countdown).getTime();
        const tick = () => {
            const now = Date.now();
            const diff = target - now;
            if (diff <= 0) {
                el.textContent = 'Due now';
                return;
            }
            const h = Math.floor(diff / (1000*60*60));
            const m = Math.floor((diff % (1000*60*60)) / (1000*60));
            el.textContent = `${h}h ${m}m`;
            requestAnimationFrame(tick);
        };
        tick();
    });
}
startCountdown();
</script>
</body>
</html>

