<?php
session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('recipient', 'sign-in.php');
$userId = $_SESSION['user_id'];

// Fetch lifeline notifications for this recipient
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
    // Parse payload to get request and donor details
    $payload = json_decode($row['payload'] ?? '{}', true) ?: [];
    $requestId = $payload['request_id'] ?? null;
    $donorId = $payload['donor_id'] ?? null;
    
    // Fetch request details if available
    if ($requestId) {
        $reqStmt = $conn->prepare("SELECT lr.id, lr.preferred_date, lr.preferred_time, lr.location, lr.blood_type, lr.city
                                   FROM lifeline_requests lr
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
    
    // Fetch donor details if available
    if ($donorId) {
        $donorStmt = $conn->prepare("SELECT u.first_name AS donor_first, u.last_name AS donor_last
                                     FROM users u
                                     WHERE u.user_id = ? LIMIT 1");
        $donorStmt->bind_param("s", $donorId);
        $donorStmt->execute();
        $donorResult = $donorStmt->get_result();
        if ($donorResult && $donorResult->num_rows > 0) {
            $donorData = $donorResult->fetch_assoc();
            $row = array_merge($row, $donorData);
        }
        $donorStmt->close();
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

// Fetch recent lifeline requests for this recipient
$requests = [];
$stmt = $conn->prepare("
    SELECT lr.*, lc.scheduled_at, lc.donor_id, lc.reschedule_payload,
           u.first_name AS donor_first, u.last_name AS donor_last,
           COALESCE(lr.blood_type, r.blood_type) AS requested_blood_type
    FROM lifeline_requests lr
    LEFT JOIN lifeline_confirmations lc ON lc.request_id = lr.id
    LEFT JOIN users u ON u.user_id = lc.donor_id
    LEFT JOIN recipients r ON r.user_id = lr.recipient_id
    WHERE lr.recipient_id = ?
    ORDER BY lr.created_at DESC
    LIMIT 30
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifeline Panel - Recipient</title>
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
        .card { 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            border-radius: 12px; 
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
        }
        .request-card {
            border-left: 4px solid #dee2e6;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: #fff;
        }
        .request-card.pending { border-left-color: #d97706; }
        .request-card.confirmed { border-left-color: #0369a1; }
        .request-card.completed { border-left-color: #15803d; }
        .request-card.failed { border-left-color: #b91c1c; }
        .request-card.rescheduled { border-left-color: #6d28d9; }
        .request-card.expired { border-left-color: #374151; }
        
        .badge { 
            border-radius: 20px; 
            padding: 6px 14px; 
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-pending { background:#fff4e6; color:#d97706; }
        .status-confirmed { background:#e0f2fe; color:#0369a1; }
        .status-completed { background:#ecfdf3; color:#15803d; }
        .status-failed { background:#fef2f2; color:#b91c1c; }
        .status-rescheduled { background:#f5f3ff; color:#6d28d9; }
        .status-expired { background:#f3f4f6; color:#374151; }
        
        .countdown { 
            font-weight: 600; 
            color: #0369a1;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .request-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .info-item i {
            color: #6c757d;
            margin-top: 0.2rem;
            width: 18px;
        }
        .info-item strong {
            color: #495057;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-item span {
            color: #212529;
            font-size: 0.95rem;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        .action-buttons .btn {
            margin: 0;
            white-space: nowrap;
        }
        
        .urgency-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .urgency-normal { background: #e9ecef; color: #495057; }
        .urgency-high { background: #fff3cd; color: #856404; }
        .urgency-critical { background: #f8d7da; color: #721c24; }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .request-id {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .request-info {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .action-buttons .btn {
                width: 100%;
            }
        }
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
                        $donorName = trim(($notif['donor_first'] ?? '') . ' ' . ($notif['donor_last'] ?? ''));
                    ?>
                    <div class="notification-item p-3 mb-2 rounded <?= $notifClass; ?>" data-notification-id="<?= $notif['id']; ?>" data-request-id="<?= $requestId; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <?php if ($notif['template_key'] === 'lifeline_donor_approved'): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <strong>Donor Approved Your Request</strong>
                                    </div>
                                    <p class="mb-1">
                                        <?php if ($donorName): ?>
                                            <strong><?= htmlspecialchars($donorName); ?></strong> has approved your lifeline request.
                                        <?php else: ?>
                                            A donor has approved your lifeline request.
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($requestId): ?>
                                        <a href="lifeline-recipient.php#request-<?= $requestId; ?>" class="btn btn-sm btn-success mt-2">View Request</a>
                                    <?php endif; ?>
                                <?php elseif ($notif['template_key'] === 'lifeline_new_request'): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fas fa-heartbeat text-danger"></i>
                                        <strong>New Request Created</strong>
                                    </div>
                                    <p class="mb-1">Your lifeline request has been created and matching donors have been notified.</p>
                                    <?php if ($requestId): ?>
                                        <a href="lifeline-recipient.php#request-<?= $requestId; ?>" class="btn btn-sm btn-primary mt-2">View Request</a>
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
    
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="mb-1"><i class="fas fa-heartbeat me-2 text-danger"></i>Lifeline Requests</h2>
            <p class="text-muted mb-0">Manage your blood donation requests</p>
        </div>
        <a href="#createRequestForm" class="btn btn-danger btn-lg">
            <i class="fas fa-plus me-2"></i>Create New Request
        </a>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4">
                <h4 class="mb-3">Create Lifeline Request</h4>
                <form id="createRequestForm">
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="preferred_date" class="form-control" required min="<?= date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Time</label>
                        <input type="time" name="preferred_time" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">City <span class="text-danger">*</span></label>
                        <input type="text" name="city" class="form-control" placeholder="Enter city name (e.g., Lahore, Karachi)" required>
                        <small class="text-muted">This helps match you with nearby donors</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location / Hospital</label>
                        <input type="text" name="location" class="form-control" placeholder="Hospital / address" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Urgency</label>
                        <select name="urgency" class="form-select">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Blood Type</label>
                        <select name="blood_type" class="form-select" required>
                            <option value="">Select blood type</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link a donor (optional)</label>
                        <input type="text" name="donor_id" class="form-control" placeholder="Donor user ID if agreed in chat">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Any instructions"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Submit Request</button>
                </form>
                <div id="createFeedback" class="mt-3 text-sm text-muted"></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0"><i class="fas fa-list-alt me-2"></i>Your Lifeline Requests</h4>
                        <small class="text-muted"><?= count($requests); ?> total request(s)</small>
                    </div>
                </div>
                
                        <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5 class="mt-3">No lifeline requests yet</h5>
                        <p class="text-muted">Create your first lifeline request using the form on the left.</p>
                    </div>
                        <?php else: ?>
                    <div class="requests-list">
                            <?php foreach ($requests as $req): ?>
                                <?php
                                    $statusClass = 'status-' . $req['status'];
                                $cardStatusClass = $req['status'];
                                    $when = htmlspecialchars($req['preferred_date'] . ' ' . $req['preferred_time']);
                                    $donorName = trim(($req['donor_first'] ?? '') . ' ' . ($req['donor_last'] ?? ''));
                                $createdDate = date('M d, Y', strtotime($req['created_at']));
                                $urgencyClass = 'urgency-' . ($req['urgency'] ?? 'normal');
                            ?>
                            <div class="request-card card <?= $cardStatusClass; ?>" data-request-id="<?= (int)$req['id']; ?>" data-scheduled="<?= htmlspecialchars($req['scheduled_at'] ?? ''); ?>">
                                <div class="request-header">
                                    <div>
                                        <span class="request-id">Request #<?= (int)$req['id']; ?></span>
                                        <span class="badge <?= $statusClass; ?> ms-2"><?= htmlspecialchars($req['status']); ?></span>
                                        <span class="urgency-badge <?= $urgencyClass; ?> ms-2"><?= htmlspecialchars($req['urgency'] ?? 'normal'); ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="far fa-calendar me-1"></i><?= $createdDate; ?>
                                    </small>
                                </div>
                                
                                <div class="request-info">
                                    <div class="info-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <div>
                                            <strong>Date & Time</strong><br>
                                            <span><?= $when; ?></span>
                                        <?php if ($req['status'] === 'confirmed' && !empty($req['scheduled_at'])): ?>
                                                <div class="countdown" data-countdown="<?= htmlspecialchars($req['scheduled_at']); ?>">
                                                    <i class="fas fa-clock me-1"></i>Time remaining
                                                </div>
                                        <?php elseif ($req['status'] === 'rescheduled' && !empty($req['reschedule_payload'])): ?>
                                            <?php $payload = json_decode($req['reschedule_payload'], true); ?>
                                                <div class="small text-muted mt-1">
                                                    <i class="fas fa-redo me-1"></i>Suggested: <?= htmlspecialchars($payload['suggested_at'] ?? ''); ?>
                                                    <?php if (!empty($payload['location'])): ?>
                                                        <br><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($payload['location']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div>
                                            <strong>Location</strong><br>
                                            <span><?= htmlspecialchars($req['location'] ?? 'Not specified'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <i class="fas fa-tint"></i>
                                        <div>
                                            <strong>Blood Type</strong><br>
                                            <span class="badge bg-danger"><?= htmlspecialchars($req['requested_blood_type'] ?? 'Not specified'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <i class="fas fa-user"></i>
                                        <div>
                                            <strong>Donor</strong><br>
                                            <span><?= $donorName ?: '<span class="text-muted">Unassigned</span>'; ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($req['note'])): ?>
                                    <div class="info-item" style="grid-column: 1 / -1;">
                                        <i class="fas fa-sticky-note"></i>
                                        <div>
                                            <strong>Notes</strong><br>
                                            <span><?= htmlspecialchars($req['note']); ?></span>
                                        </div>
                                            </div>
                                        <?php endif; ?>
                                </div>
                                
                                <div class="action-buttons">
                                    <?php if (!empty($req['donor_id'])): ?>
                                        <a href="chat.php?id=<?= htmlspecialchars($req['donor_id']); ?>" class="btn btn-primary btn-sm" title="Chat with donor">
                                            <i class="fas fa-comments me-1"></i> Chat with Donor
                                        </a>
                                    <?php endif; ?>
                                    
                                        <?php if ($req['status'] === 'confirmed'): ?>
                                        <button class="btn btn-success btn-sm post-check" data-result="completed">
                                            <i class="fas fa-check-circle me-1"></i> Mark Done
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm post-check" data-result="failed">
                                            <i class="fas fa-times-circle me-1"></i> Mark Failed
                                        </button>
                                        <?php elseif ($req['status'] === 'rescheduled' && !empty($req['reschedule_payload'])): ?>
                                        <?php $payload = json_decode($req['reschedule_payload'], true); ?>
                                        <div class="d-flex gap-2" style="width: 100%;">
                                            <button class="btn btn-success btn-sm accept-reschedule flex-fill" data-accept="1">
                                                <i class="fas fa-check me-1"></i> Accept Reschedule
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm accept-reschedule flex-fill" data-accept="0">
                                                <i class="fas fa-times me-1"></i> Decline
                                            </button>
                                            </div>
                                        <?php else: ?>
                                        <span class="text-muted small">
                                            <i class="fas fa-info-circle me-1"></i>No actions available
                                        </span>
                                        <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                    </div>
                        <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php include('assets/includes/footer.php'); ?>
<?php include('assets/includes/link-js.php'); ?>
<script>
const form = document.getElementById('createRequestForm');
const feedback = document.getElementById('createFeedback');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    feedback.textContent = 'Submitting...';
    const data = new FormData(form);
    data.append('action', 'create_request');
    try {
        const res = await fetch('assets/lib/lifeline-api.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            feedback.textContent = 'Request created. Refreshing...';
            setTimeout(() => location.reload(), 800);
        } else {
            feedback.textContent = json.error || 'Failed to create request.';
        }
    } catch (err) {
        feedback.textContent = 'Network error.';
    }
});

document.querySelectorAll('.post-check').forEach(btn => {
    btn.addEventListener('click', async () => {
        const row = btn.closest('tr');
        const id = row.dataset.requestId;
        const data = new FormData();
        data.append('action', 'post_check');
        data.append('request_id', id);
        data.append('result', btn.dataset.result);
        btn.disabled = true;
        const res = await fetch('assets/lib/lifeline-api.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            btn.disabled = false;
            alert(json.error || 'Failed to update.');
        }
    });
});

// Accept / decline reschedule
document.querySelectorAll('.accept-reschedule').forEach(btn => {
    btn.addEventListener('click', async () => {
        const row = btn.closest('tr');
        const id = row.dataset.requestId;
        const accept = btn.dataset.accept;
        const data = new FormData();
        data.append('action', 'accept_reschedule');
        data.append('request_id', id);
        data.append('accept', accept);
        btn.disabled = true;
        const res = await fetch('assets/lib/lifeline-api.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            btn.disabled = false;
            alert(json.error || 'Failed to update.');
        }
    });
});

// Countdown timers for confirmed requests
function startCountdown() {
    document.querySelectorAll('[data-countdown]').forEach(el => {
        const target = new Date(el.dataset.countdown).getTime();
        const tick = () => {
            const now = Date.now();
            const diff = target - now;
            if (diff <= 0) {
                el.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>Due now';
                el.style.color = '#b91c1c';
                return;
            }
            const days = Math.floor(diff / (1000*60*60*24));
            const h = Math.floor((diff % (1000*60*60*24)) / (1000*60*60));
            const m = Math.floor((diff % (1000*60*60)) / (1000*60));
            const s = Math.floor((diff % (1000*60)) / 1000);
            
            let timeStr = '';
            if (days > 0) {
                timeStr = `${days}d ${h}h ${m}m`;
            } else if (h > 0) {
                timeStr = `${h}h ${m}m`;
            } else {
                timeStr = `${m}m ${s}s`;
            }
            
            el.innerHTML = `<i class="fas fa-clock me-1"></i>${timeStr} remaining`;
            requestAnimationFrame(tick);
        };
        tick();
    });
}
startCountdown();

// Smooth scroll to form when clicking "Create New Request" buttons
document.querySelectorAll('a[href="#createRequestForm"]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const form = document.getElementById('createRequestForm');
        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Focus on first input after scroll
            setTimeout(() => {
                const firstInput = form.querySelector('input, select, textarea');
                if (firstInput) firstInput.focus();
            }, 500);
        }
    });
});

// Mark notification as read
function markNotificationRead(notificationId) {
    fetch('assets/lib/lifeline-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=mark_notification_read&notification_id=${notificationId}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const notifItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notifItem) {
                notifItem.classList.remove('bg-light', 'border-start', 'border-3', 'border-primary');
                const markReadBtn = notifItem.querySelector('.mark-read-btn');
                if (markReadBtn) markReadBtn.remove();
                // Update unread count
                updateUnreadCount();
            }
        }
    })
    .catch(err => console.error('Error marking notification as read:', err));
}

// Mark all notifications as read
function markAllNotificationsRead() {
    fetch('assets/lib/lifeline-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_notifications_read'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(err => console.error('Error marking all notifications as read:', err));
}

// Update unread count badge
function updateUnreadCount() {
    fetch('assets/lib/lifeline-api.php?action=get_unread_count')
        .then(res => res.json())
        .then(data => {
            const badge = document.querySelector('.badge.bg-danger');
            if (data.unread_count > 0) {
                if (badge) {
                    badge.textContent = data.unread_count + ' new';
                } else {
                    const h5 = document.querySelector('h5');
                    if (h5) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'badge bg-danger';
                        newBadge.textContent = data.unread_count + ' new';
                        h5.appendChild(newBadge);
                    }
                }
            } else {
                if (badge) badge.remove();
            }
        })
        .catch(err => console.error('Error updating unread count:', err));
}
</script>
</body>
</html>

