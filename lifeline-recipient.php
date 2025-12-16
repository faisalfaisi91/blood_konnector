<?php
session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('recipient', 'sign-in.php');
$userId = $_SESSION['user_id'];

// Fetch recent lifeline requests for this recipient
$requests = [];
$stmt = $conn->prepare("
    SELECT lr.*, lc.scheduled_at, lc.donor_id,
           u.first_name AS donor_first, u.last_name AS donor_last
    FROM lifeline_requests lr
    LEFT JOIN lifeline_confirmations lc ON lc.request_id = lr.id
    LEFT JOIN users u ON u.user_id = lc.donor_id
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
    <div class="row g-4">
        <div class="col-lg-5">
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
        <div class="col-lg-7">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Your Lifeline Requests</h4>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>When</th>
                                <th>Donor</th>
                                <th>Urgency</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No lifeline requests yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                                <?php
                                    $statusClass = 'status-' . $req['status'];
                                    $when = htmlspecialchars($req['preferred_date'] . ' ' . $req['preferred_time']);
                                    $donorName = trim(($req['donor_first'] ?? '') . ' ' . ($req['donor_last'] ?? ''));
                                ?>
                                <tr data-request-id="<?= (int)$req['id']; ?>" data-scheduled="<?= htmlspecialchars($req['scheduled_at'] ?? ''); ?>">
                                    <td>#<?= (int)$req['id']; ?></td>
                                    <td><span class="badge <?= $statusClass; ?>"><?= htmlspecialchars($req['status']); ?></span></td>
                                    <td>
                                        <?= $when; ?>
                                        <?php if ($req['status'] === 'confirmed' && !empty($req['scheduled_at'])): ?>
                                            <div class="countdown small" data-countdown="<?= htmlspecialchars($req['scheduled_at']); ?>"></div>
                                        <?php elseif ($req['status'] === 'rescheduled' && !empty($req['reschedule_payload'])): ?>
                                            <?php $payload = json_decode($req['reschedule_payload'], true); ?>
                                            <div class="small text-muted">
                                                Suggested: <?= htmlspecialchars($payload['suggested_at'] ?? ''); ?><br>
                                                At: <?= htmlspecialchars($payload['location'] ?? ''); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $donorName ?: 'Unassigned'; ?></td>
                                    <td class="text-capitalize"><?= htmlspecialchars($req['urgency']); ?></td>
                                    <td>
                                        <?php if ($req['status'] === 'confirmed'): ?>
                                            <button class="btn btn-sm btn-success post-check" data-result="completed">Mark Done</button>
                                            <button class="btn btn-sm btn-outline-danger post-check" data-result="failed">Mark Failed</button>
                                        <?php elseif ($req['status'] === 'rescheduled' && !empty($req['reschedule_payload'])): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success accept-reschedule" data-accept="1">Accept</button>
                                                <button class="btn btn-outline-danger accept-reschedule" data-accept="0">Decline</button>
                                            </div>
                                        <?php elseif ($req['status'] === 'rescheduled' || $req['status'] === 'pending'): ?>
                                            <a href="#createRequestForm" class="btn btn-sm btn-outline-primary">Create new</a>
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

