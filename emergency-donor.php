<?php
// Converted from the previous donor page and updated to Emergency naming
session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('donor', 'sign-in.php');
$userId = $_SESSION['user_id'];

// Fetch emergency notifications for this donor
$notifications = [];
$notifStmt = $conn->prepare("\n    SELECT ln.*\n    FROM emergency_notifications ln\n    WHERE ln.user_id = ?\n      AND ln.channel = 'in_app'\n      AND ln.template_key IN ('emergency_new_request', 'emergency_donor_approved')\n    ORDER BY ln.created_at DESC\n    LIMIT 20\n");
$notifStmt->bind_param("s", $userId);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
while ($row = $notifResult->fetch_assoc()) {
    // Parse payload to get request details
    $payload = json_decode($row['payload'] ?? '{}', true) ?: [];
    $requestId = $payload['request_id'] ?? null;
    
    // Fetch request details if available
    if ($requestId) {
        $reqStmt = $conn->prepare("SELECT lr.id, lr.preferred_date, lr.preferred_time, lr.location, lr.blood_type, lr.city, u.first_name AS recipient_first, u.last_name AS recipient_last\n                                   FROM emergency_requests lr\n                                   LEFT JOIN users u ON u.user_id = lr.recipient_id\n                                   WHERE lr.id = ? LIMIT 1");
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
$unreadStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM emergency_notifications WHERE user_id = ? AND channel = 'in_app' AND status = 'queued' AND template_key IN ('emergency_new_request', 'emergency_donor_approved')");
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
$stmt = $conn->prepare("\n    SELECT lr.*, lc.scheduled_at, lc.reschedule_payload, lc.donor_response,\n           u.first_name AS recipient_first, u.last_name AS recipient_last\n    FROM emergency_requests lr\n    JOIN emergency_confirmations lc ON lc.request_id = lr.id\n    JOIN users u ON u.user_id = lr.recipient_id\n    WHERE lc.donor_id = ?\n    ORDER BY lr.created_at DESC\n    LIMIT 30\n");
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
        $availableStmt = $conn->prepare("\n            SELECT lr.*, lc.scheduled_at, lc.reschedule_payload, lc.donor_response, lc.donor_id,\n                   u.first_name AS recipient_first, u.last_name AS recipient_last\n            FROM emergency_requests lr\n            LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id\n            JOIN users u ON u.user_id = lr.recipient_id\n            WHERE lr.status = 'pending'\n              AND lr.blood_type = ?\n              AND (lr.city = ? OR LOWER(lr.city) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', lr.city, '%')))\n              AND (lc.donor_id IS NULL OR lc.donor_id != ?)\n              AND lr.id NOT IN (SELECT request_id FROM emergency_confirmations WHERE donor_id = ? AND donor_response = 'approve')\n            ORDER BY lr.created_at DESC\n            LIMIT 20\n        ");
        $availableStmt->bind_param("ssssss", $donorBloodType, $donorLocation, $donorLocation, $donorLocation, $userId, $userId);
    } elseif ($hasBloodType) {
        // Match by blood_type only (city column doesn't exist yet)
        $availableStmt = $conn->prepare("\n            SELECT lr.*, lc.scheduled_at, lc.reschedule_payload, lc.donor_response, lc.donor_id,\n                   u.first_name AS recipient_first, u.last_name AS recipient_last\n            FROM emergency_requests lr\n            LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id\n            JOIN users u ON u.user_id = lr.recipient_id\n            WHERE lr.status = 'pending'\n              AND lr.blood_type = ?\n              AND (lc.donor_id IS NULL OR lc.donor_id != ?)\n              AND lr.id NOT IN (SELECT request_id FROM emergency_confirmations WHERE donor_id = ? AND donor_response = 'approve')\n            ORDER BY lr.created_at DESC\n            LIMIT 20\n        ");
        $availableStmt->bind_param("sss", $donorBloodType, $userId, $userId);
    } else {
        // Fallback: match by donor's blood type from recipient profile (old method)
        $availableStmt = $conn->prepare("\n            SELECT lr.*, lc.scheduled_at, lc.reschedule_payload, lc.donor_response, lc.donor_id,\n                   u.first_name AS recipient_first, u.last_name AS recipient_last,\n                   r.blood_type AS recipient_blood_type\n            FROM emergency_requests lr\n            LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id\n            JOIN users u ON u.user_id = lr.recipient_id\n            LEFT JOIN recipients r ON r.user_id = lr.recipient_id\n            WHERE lr.status = 'pending'\n              AND r.blood_type = ?\n              AND (LOWER(r.location) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', r.location, '%')))\n              AND (lc.donor_id IS NULL OR lc.donor_id != ?)\n              AND lr.id NOT IN (SELECT request_id FROM emergency_confirmations WHERE donor_id = ? AND donor_response = 'approve')\n            ORDER BY lr.created_at DESC\n            LIMIT 20\n        ");
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

// Fetch request IDs where donor has already submitted feedback
$feedbackGivenRequestIds = [];
$fbStmt = $conn->prepare("SELECT request_id FROM emergency_feedback WHERE from_user_id = ? AND role = 'donor'");
$fbStmt->bind_param("s", $userId);
$fbStmt->execute();
$fbRes = $fbStmt->get_result();
while ($fbRow = $fbRes->fetch_assoc()) {
    $feedbackGivenRequestIds[] = (int)$fbRow['request_id'];
}
$fbStmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Panel - Donor</title>
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
    <?php include('assets/includes/link-js.php'); ?>
</head>
<body>
<?php include('assets/includes/header.php'); ?>
<section class="container py-5">
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">Emergency Requests</h4>
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
                    <tr><td colspan="8" class="text-center text-muted py-3">No emergency requests available. Check back later!</td></tr>
                <?php else: ?>
                    <?php if (!empty($assignedRequests)): ?>
                        <tr class="table-info">
                            <td colspan="8" class="fw-bold">Assigned Requests</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($assignedRequests as $req): ?>
                        <?php
                            $statusClass = 'status-' . $req['status'];
                            $when = htmlspecialchars(format_display_date($req['preferred_date'] . ' ' . $req['preferred_time']));
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
                                <?php elseif (in_array($req['status'], ['completed','failed']) && !in_array((int)$req['id'], $feedbackGivenRequestIds)): ?>
                                    <button class="btn btn-primary btn-sm open-feedback-modal" data-request-id="<?= (int)$req['id']; ?>" data-target-name="<?= htmlspecialchars(trim($req['recipient_first'] . ' ' . $req['recipient_last'])); ?>">
                                        <i class="fas fa-star me-1"></i> Rate Recipient
                                    </button>
                                <?php elseif (in_array($req['status'], ['completed','failed']) && in_array((int)$req['id'], $feedbackGivenRequestIds)): ?>
                                    <span class="text-success small"><i class="fas fa-check-circle me-1"></i>Feedback submitted</span>
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
                            $when = htmlspecialchars(format_display_date($req['preferred_date'] . ' ' . $req['preferred_time']));
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

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedbackModalLabel"><i class="fas fa-star me-2"></i>Rate Recipient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">How was your experience with <strong id="feedbackTargetName"></strong>? Your feedback helps improve our service.</p>
                <form id="feedbackForm">
                    <input type="hidden" id="feedbackRequestId" name="request_id">
                    <input type="hidden" name="role" value="donor">
                    <div class="mb-3">
                        <label class="form-label">Rating (1-5 stars) <span class="text-danger">*</span></label>
                        <div class="d-flex gap-1 align-items-center">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="feedback-star m-0" title="<?= $i ?> star(s)">
                                    <input type="radio" name="rating" value="<?= $i ?>" required>
                                    <i class="fas fa-star text-warning" style="font-size: 1.8rem; cursor: pointer;"></i>
                                </label>
                            <?php endfor; ?>
                            <span class="ms-2 small text-muted" id="ratingLabel">Select a rating</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="feedbackRemarks">Remarks (optional)</label>
                        <textarea class="form-control" id="feedbackRemarks" name="remarks" rows="3" placeholder="e.g., Recipient coordination, hospital management, overall experience..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitFeedbackBtn">
                    <i class="fas fa-paper-plane me-1"></i>Submit Feedback
                </button>
            </div>
        </div>
    </div>
</div>

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
                    const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: data });
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
            const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: data });
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
    const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: data });
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

// Feedback modal
const feedbackModal = document.getElementById('feedbackModal');
if (feedbackModal) {
    const feedbackTargetNameEl = document.getElementById('feedbackTargetName');
    const feedbackRequestIdEl = document.getElementById('feedbackRequestId');
    const ratingLabel = document.getElementById('ratingLabel');
    const submitFeedbackBtn = document.getElementById('submitFeedbackBtn');
    
    document.querySelectorAll('.open-feedback-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            feedbackRequestIdEl.value = btn.dataset.requestId;
            feedbackTargetNameEl.textContent = btn.dataset.targetName || 'Recipient';
            document.getElementById('feedbackRemarks').value = '';
            document.querySelectorAll('#feedbackForm input[name="rating"]').forEach(r => r.checked = false);
            ratingLabel.textContent = 'Select a rating';
            const modal = new bootstrap.Modal(feedbackModal);
            modal.show();
        });
    });
    
    document.querySelectorAll('#feedbackForm .feedback-star').forEach((label, idx) => {
        label.addEventListener('click', () => {
            ratingLabel.textContent = (idx + 1) + ' star(s)';
        });
    });
    
    submitFeedbackBtn.addEventListener('click', async () => {
        const rating = document.querySelector('#feedbackForm input[name="rating"]:checked');
        if (!rating) {
            alert('Please select a rating (1-5 stars).');
            return;
        }
        submitFeedbackBtn.disabled = true;
        submitFeedbackBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';
        const formData = new FormData();
        formData.append('action', 'feedback');
        formData.append('request_id', feedbackRequestIdEl.value);
        formData.append('role', 'donor');
        formData.append('rating', rating.value);
        formData.append('remarks', document.getElementById('feedbackRemarks').value);
        try {
            const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.success) {
                bootstrap.Modal.getInstance(feedbackModal).hide();
                location.reload();
            } else {
                alert(json.error || 'Failed to submit feedback.');
                submitFeedbackBtn.disabled = false;
                submitFeedbackBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Feedback';
            }
        } catch (err) {
            alert('Network error. Please try again.');
            submitFeedbackBtn.disabled = false;
            submitFeedbackBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Feedback';
        }
    });
}
</script>
</body>
</html>
