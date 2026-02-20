<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('recipient', 'sign-in.php');
$userId = $_SESSION['user_id'];

// Fetch emergency notifications for this recipient
$notifications = [];
$notifStmt = $conn->prepare("\n    SELECT ln.*\n    FROM emergency_notifications ln\n    WHERE ln.user_id = ?\n      AND ln.channel = 'in_app'\n    AND ln.template_key IN ('emergency_new_request', 'emergency_donor_approved')\n    ORDER BY ln.created_at DESC\n    LIMIT 20\n");
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
        $reqStmt = $conn->prepare("SELECT lr.id, lr.preferred_date, lr.preferred_time, lr.location, lr.blood_type, lr.city\n                                   FROM emergency_requests lr\n                                   WHERE lr.id = ? LIMIT 1");
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
        $donorStmt = $conn->prepare("SELECT u.first_name AS donor_first, u.last_name AS donor_last\n                                     FROM users u\n                                     WHERE u.user_id = ? LIMIT 1");
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
$unreadStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM emergency_notifications WHERE user_id = ? AND channel = 'in_app' AND status = 'queued' AND template_key IN ('emergency_new_request', 'emergency_donor_approved')");
$unreadStmt->bind_param("s", $userId);
$unreadStmt->execute();
$unreadResult = $unreadStmt->get_result();
if ($unreadResult && $unreadResult->num_rows > 0) {
    $unreadRow = $unreadResult->fetch_assoc();
    $unreadCount = (int)$unreadRow['cnt'];
}
$unreadStmt->close();

// Fetch recent emergency requests for this recipient
$requests = [];
$stmt = $conn->prepare("\n    SELECT lr.*, lc.scheduled_at, lc.donor_id, lc.reschedule_payload,\n           u.first_name AS donor_first, u.last_name AS donor_last,\n           COALESCE(lr.blood_type, r.blood_type) AS requested_blood_type\n    FROM emergency_requests lr\n    LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id\n    LEFT JOIN users u ON u.user_id = lc.donor_id\n    LEFT JOIN recipients r ON r.user_id = lr.recipient_id\n    WHERE lr.recipient_id = ?\n    ORDER BY lr.created_at DESC\n    LIMIT 30\n");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();

// Fetch request IDs where recipient has already submitted feedback
$feedbackGivenRequestIds = [];
$fbStmt = $conn->prepare("SELECT request_id FROM emergency_feedback WHERE from_user_id = ? AND role = 'recipient'");
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
    <title>Emergency Panel - Recipient</title>
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
    <?php include('assets/includes/link-js.php'); ?>
</head>
<body>
<?php include('assets/includes/header.php'); ?>
<section class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="mb-1"><i class="fas fa-heartbeat me-2 text-danger"></i>Emergency Requests</h2>
            <p class="text-muted mb-0">Manage your blood donation requests</p>
        </div>
        <a href="#createRequestForm" class="btn btn-danger btn-lg">
            <i class="fas fa-plus me-2"></i>Create New Request
        </a>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4">
                <h4 class="mb-3">Create Emergency Request</h4>
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
                        <label class="form-label">Number of Donors to Notify</label>
                        <select name="donor_limit" class="form-select" required>
                            <option value="20">20 Donors</option>
                            <option value="50" selected>50 Donors</option>
                            <option value="75">75 Donors</option>
                            <option value="100">100 Donors</option>
                            <option value="unlimited">Unlimited (All Matching Donors)</option>
                        </select>
                        <small class="text-muted">Select how many matching donors should receive this request</small>
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
                        <h4 class="mb-0"><i class="fas fa-list-alt me-2"></i>Your Emergency Requests</h4>
                        <small class="text-muted"><?= count($requests); ?> total request(s)</small>
                    </div>
                </div>
                
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5 class="mt-3">No emergency requests yet</h5>
                        <p class="text-muted">Create your first emergency request using the form on the left.</p>
                    </div>
                <?php else: ?>
                    <div class="requests-list">
                        <?php foreach ($requests as $req): ?>
                            <?php
                                $statusClass = 'status-' . $req['status'];
                                $cardStatusClass = $req['status'];
                                $when = htmlspecialchars(format_display_date($req['preferred_date'] . ' ' . $req['preferred_time']));
                                $donorName = trim(($req['donor_first'] ?? '') . ' ' . ($req['donor_last'] ?? ''));
                                $createdDate = format_display_date($req['created_at'], false);
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
                                        <button class="btn btn-success btn-sm post-check" data-result="completed" data-request-id="<?= (int)$req['id']; ?>">
                                            <i class="fas fa-check-circle me-1"></i> Mark Done
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm post-check" data-result="failed" data-request-id="<?= (int)$req['id']; ?>">
                                            <i class="fas fa-times-circle me-1"></i> Mark Failed
                                        </button>
                                    <?php elseif ($req['status'] === 'rescheduled' && !empty($req['reschedule_payload'])): ?>
                                        <?php $payload = json_decode($req['reschedule_payload'], true); ?>
                                        <div class="d-flex gap-2" style="width: 100%;">
                                            <button class="btn btn-success btn-sm accept-reschedule flex-fill" data-accept="1" data-request-id="<?= (int)$req['id']; ?>">
                                                <i class="fas fa-check me-1"></i> Accept Reschedule
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm accept-reschedule flex-fill" data-accept="0" data-request-id="<?= (int)$req['id']; ?>">
                                                <i class="fas fa-times me-1"></i> Decline
                                            </button>
                                        </div>
                                    <?php elseif (in_array($req['status'], ['completed', 'failed']) && !empty($req['donor_id']) && !in_array((int)$req['id'], $feedbackGivenRequestIds)): ?>
                                        <button class="btn btn-primary btn-sm open-feedback-modal" data-request-id="<?= (int)$req['id']; ?>" data-target-name="<?= htmlspecialchars($donorName ?: 'Donor'); ?>">
                                            <i class="fas fa-star me-1"></i> Rate Donor
                                        </button>
                                    <?php elseif (in_array($req['status'], ['completed', 'failed']) && !empty($req['donor_id']) && in_array((int)$req['id'], $feedbackGivenRequestIds)): ?>
                                        <span class="text-success small"><i class="fas fa-check-circle me-1"></i>Feedback submitted</span>
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

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedbackModalLabel"><i class="fas fa-star me-2"></i>Rate Donor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">How was your experience with <strong id="feedbackTargetName"></strong>? Your feedback helps improve our service.</p>
                <form id="feedbackForm">
                    <input type="hidden" id="feedbackRequestId" name="request_id">
                    <input type="hidden" name="role" value="recipient">
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
                        <textarea class="form-control" id="feedbackRemarks" name="remarks" rows="3" placeholder="e.g., Ease of process, communication clarity, donor responsiveness..."></textarea>
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
<script>
const form = document.getElementById('createRequestForm');
const feedback = document.getElementById('createFeedback');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    feedback.textContent = 'Submitting...';
    const data = new FormData(form);
    data.append('action', 'create_request');
    try {
        const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: data });
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
        const id = btn.dataset.requestId;
        const data = new FormData();
        data.append('action', 'post_check');
        data.append('request_id', id);
        data.append('result', btn.dataset.result);
        btn.disabled = true;
        const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            btn.disabled = false;
            alert(json.error || 'Failed to update.');
        }
    });
});

document.querySelectorAll('.accept-reschedule').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.requestId;
        const accept = btn.dataset.accept;
        const data = new FormData();
        data.append('action', 'accept_reschedule');
        data.append('request_id', id);
        data.append('accept', accept);
        btn.disabled = true;
        const res = await fetch('assets/lib/emergency-api.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            btn.disabled = false;
            alert(json.error || 'Failed to update.');
        }
    });
});

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
            feedbackTargetNameEl.textContent = btn.dataset.targetName || 'Donor';
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
        const formData = new FormData(document.getElementById('feedbackForm'));
        formData.append('action', 'feedback');
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

document.querySelectorAll('a[href="#createRequestForm"]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const form = document.getElementById('createRequestForm');
        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => {
                const firstInput = form.querySelector('input, select, textarea');
                if (firstInput) firstInput.focus();
            }, 500);
        }
    });
});

function markNotificationRead(notificationId) {
    fetch('assets/lib/emergency-api.php', {
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
                updateUnreadCount();
            }
        }
    })
    .catch(err => console.error('Error marking notification as read:', err));
}

function markAllNotificationsRead() {
    fetch('assets/lib/emergency-api.php', {
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

function updateUnreadCount() {
    fetch('assets/lib/emergency-api.php?action=get_unread_count')
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
<?php
// End of emergency-recipient.php
