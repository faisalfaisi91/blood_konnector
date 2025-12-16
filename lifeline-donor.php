<?php
session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('donor', 'sign-in.php');
$userId = $_SESSION['user_id'];

$requests = [];
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
    $requests[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifeline Link - Donor</title>
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
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Assigned Lifeline Requests</h4>
            <span class="text-muted small">Respond promptly to avoid expiry.</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Recipient</th>
                        <th>Status</th>
                        <th>When</th>
                        <th>Location</th>
                        <th>Urgency</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No assigned lifeline requests yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <?php
                            $statusClass = 'status-' . $req['status'];
                            $when = htmlspecialchars($req['preferred_date'] . ' ' . $req['preferred_time']);
                            $recipientName = trim($req['recipient_first'] . ' ' . $req['recipient_last']);
                            $payload = $req['reschedule_payload'] ? json_decode($req['reschedule_payload'], true) : null;
                        ?>
                        <tr data-request-id="<?= (int)$req['id']; ?>"
                            data-date="<?= htmlspecialchars($req['preferred_date']); ?>"
                            data-time="<?= htmlspecialchars($req['preferred_time']); ?>"
                            data-location="<?= htmlspecialchars($req['location']); ?>">
                            <td>#<?= (int)$req['id']; ?></td>
                            <td><?= htmlspecialchars($recipientName); ?></td>
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
                alert(json.error || 'Failed to submit response.');
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
                alert(json.error || 'Failed to submit response.');
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

