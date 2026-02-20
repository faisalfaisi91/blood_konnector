<?php
session_start();
require_once __DIR__ . '/assets/lib/openconn.php';
require_once __DIR__ . '/assets/lib/ProfileManager.php';

$profileManager = new ProfileManager($conn);
$profileManager->requireRole('donor', 'sign-in.php');
$userId = $_SESSION['user_id'];

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

// Fetch notifications for this donor (requests that match donor's profile)
$notifications = [];
$notifStmt = $conn->prepare("
    SELECT lr.*, 
           u.first_name AS recipient_first, u.last_name AS recipient_last, u.profile_pic
    FROM lifeline_requests lr
    LEFT JOIN users u ON u.user_id = lr.recipient_id
    WHERE lr.blood_type = ? AND lr.status IN ('open', 'pending')
    ORDER BY lr.created_at DESC
    LIMIT 50
");
$notifStmt->bind_param("s", $donorBloodType);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
while ($row = $notifResult->fetch_assoc()) {
    $notifications[] = $row;
}
$notifStmt->close();

// Fetch available matching requests (pending requests matching donor's blood type and city)
$availableRequests = [];
if (!empty($donorBloodType)) {
    $availableStmt = $conn->prepare("
        SELECT lr.*, 
               u.first_name AS recipient_first, u.last_name AS recipient_last,
               u.profile_pic AS recipient_pic
        FROM lifeline_requests lr
        JOIN users u ON u.user_id = lr.recipient_id
        WHERE lr.status IN ('open', 'pending')
          AND lr.blood_type = ?
          AND (? = '' OR LOWER(lr.city) LIKE LOWER(CONCAT('%', ?, '%')))
        ORDER BY lr.created_at DESC
        LIMIT 30
    ");
    $availableStmt->bind_param("sss", $donorBloodType, $donorLocation, $donorLocation);
    $availableStmt->execute();
    $availableRes = $availableStmt->get_result();
    while ($row = $availableRes->fetch_assoc()) {
        $availableRequests[] = $row;
    }
    $availableStmt->close();
}

// Fetch accepted requests (where donor has accepted)
$acceptedRequests = [];
$acceptedStmt = $conn->prepare("
    SELECT lr.*, 
           u.first_name AS recipient_first, u.last_name AS recipient_last,
           u.profile_pic AS recipient_pic
    FROM lifeline_requests lr
    JOIN users u ON u.user_id = lr.recipient_id
    WHERE lr.status IN ('confirmed', 'completed')
    ORDER BY lr.created_at DESC
    LIMIT 30
");
$acceptedStmt->execute();
$acceptedRes = $acceptedStmt->get_result();
while ($row = $acceptedRes->fetch_assoc()) {
    $acceptedRequests[] = $row;
}
$acceptedStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeLine Requests - Blood Konnector</title>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        .lifeline-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .request-card {
            border-left: 4px solid #dee2e6;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .request-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .request-card.pending { border-left-color: #ffc107; }
        .request-card.accepted { border-left-color: #0d6efd; }
        .request-card.completed { border-left-color: #198754; }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-pending { background:#fff4e6; color:#d97706; }
        .status-accepted { background:#e0f2fe; color:#0369a1; }
        .status-completed { background:#ecfdf3; color:#15803d; }
        
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
        
        .recipient-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .recipient-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
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
        .section-header {
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e9ecef;
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
    <?php /* include('assets/includes/link-js.php'); */ ?>
</head>
<body>
<?php 
include('assets/includes/header.php'); 
include('assets/includes/link-css.php');
?>

<section class="lifeline-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-danger mb-1"><i class="fas fa-heartbeat me-2"></i>LifeLine Requests</h2>
            <p class="text-muted mb-0">Help recipients in need by accepting their blood donation requests</p>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center p-3">
                <h3 class="text-warning mb-0"><?= count($availableRequests); ?></h3>
                <small class="text-muted">Available Requests</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3">
                <h3 class="text-info mb-0"><?= count($acceptedRequests); ?></h3>
                <small class="text-muted">Accepted Requests</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3">
                <h3 class="text-success mb-0"><?= count(array_filter($acceptedRequests, fn($r) => $r['status'] === 'completed')); ?></h3>
                <small class="text-muted">Completed</small>
            </div>
        </div>
    </div>
    
    <!-- Accepted Requests Section -->
    <?php if (!empty($acceptedRequests)): ?>
        <div class="section-header">
            <h4 class="mb-0"><i class="fas fa-check-circle me-2 text-info"></i>My Accepted Requests</h4>
        </div>
        <div class="requests-list">
            <?php foreach ($acceptedRequests as $req): ?>
                <div class="request-card <?= $req['status']; ?>" data-request-id="<?= (int)$req['id']; ?>">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <div>
                            <span class="text-muted small">Request #<?= (int)$req['id']; ?></span>
                            <span class="status-badge status-<?= $req['status']; ?> ms-2"><?= htmlspecialchars($req['status']); ?></span>
                            <span class="urgency-badge urgency-<?= $req['urgency'] ?? 'normal'; ?> ms-2"><?= htmlspecialchars($req['urgency'] ?? 'normal'); ?></span>
                        </div>
                        <small class="text-muted"><?= format_display_date($req['created_at']); ?></small>
                    </div>
                    
                    <div class="recipient-info">
                        <img src="<?= !empty($req['recipient_pic']) ? htmlspecialchars($req['recipient_pic']) : 'assets/images/default-avatar.png'; ?>" 
                             alt="Recipient" class="recipient-avatar">
                        <div class="flex-grow-1">
                            <strong>Recipient:</strong> <?= htmlspecialchars(trim(($req['recipient_first'] ?? '') . ' ' . ($req['recipient_last'] ?? ''))); ?>
                            <br>
                            <small class="text-muted">
                                <strong>Blood Type:</strong> <?= htmlspecialchars($req['blood_type']); ?> | 
                                <strong>City:</strong> <?= htmlspecialchars($req['city']); ?>
                            </small>
                        </div>
                        <?php if ($req['status'] === 'accepted'): ?>
                            <a href="chat.php?id=<?= urlencode($req['recipient_id']); ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-comments me-1"></i>Chat
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($req['note'])): ?>
                        <div class="mt-2 p-2 bg-light rounded">
                            <small><strong>Note:</strong> <?= htmlspecialchars($req['note']); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Available Requests Section -->
    <div class="section-header">
        <h4 class="mb-0"><i class="fas fa-bell me-2 text-warning"></i>Available Requests</h4>
        <small class="text-muted">These requests match your blood type and location</small>
    </div>
    
    <?php if (empty($availableRequests)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h5 class="mt-3">No available requests</h5>
            <p class="text-muted">There are currently no LifeLine requests matching your blood type and location. Check back later!</p>
        </div>
    <?php else: ?>
        <div class="requests-list">
            <?php foreach ($availableRequests as $req): ?>
                <div class="request-card pending position-relative" data-request-id="<?= (int)$req['id']; ?>">
                    <?php if ($req['status'] === 'pending'): ?>
                        <span class="notification-badge">!</span>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <div>
                            <span class="text-muted small">Request #<?= (int)$req['id']; ?></span>
                            <span class="status-badge status-pending ms-2">Pending</span>
                            <span class="urgency-badge urgency-<?= $req['urgency'] ?? 'normal'; ?> ms-2"><?= htmlspecialchars($req['urgency'] ?? 'normal'); ?></span>
                        </div>
                        <small class="text-muted"><?= format_display_date($req['created_at']); ?></small>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Blood Type:</strong> <?= htmlspecialchars($req['blood_type']); ?></p>
                            <p class="mb-1"><strong>City:</strong> <?= htmlspecialchars($req['city']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="recipient-info">
                                <img src="<?= !empty($req['recipient_pic']) ? htmlspecialchars($req['recipient_pic']) : 'assets/images/default-avatar.png'; ?>" 
                                     alt="Recipient" class="recipient-avatar">
                                <div>
                                    <strong>Recipient:</strong><br>
                                    <small><?= htmlspecialchars(trim(($req['recipient_first'] ?? '') . ' ' . ($req['recipient_last'] ?? ''))); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($req['note'])): ?>
                        <div class="mt-2 p-2 bg-light rounded">
                            <small><strong>Note:</strong> <?= htmlspecialchars($req['note']); ?></small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-sm btn-success accept-request-btn" data-request-id="<?= (int)$req['id']; ?>">
                            <i class="fas fa-check me-1"></i>Accept Request
                        </button>
                        <button class="btn btn-sm btn-secondary decline-request-btn" data-request-id="<?= (int)$req['id']; ?>">
                            <i class="fas fa-times me-1"></i>Decline
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>


<?php 
include('assets/includes/footer.php'); 
// Only include link-js.php ONCE per page to avoid JS redeclaration errors
if (!defined('LINK_JS_INCLUDED')) {
    define('LINK_JS_INCLUDED', true);
    include('assets/includes/link-js.php');
}
?>

<script>
// Accept request
document.querySelectorAll('.accept-request-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const requestId = this.dataset.requestId;
        const message = prompt('Optional message to recipient:');
        
        if (message === null) return; // User cancelled
        
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Accepting...';
        
        try {
            const response = await fetch('assets/lib/lifeline-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'donor_accept', 
                    request_id: requestId,
                    message: message || ''
                })
            });
            const result = await response.json();
            
            if (result.success) {
                alert('Request accepted! You can now chat with the recipient.');
                location.reload();
            } else {
                alert(result.error || 'Failed to accept request');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-check me-1"></i>Accept Request';
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-check me-1"></i>Accept Request';
        }
    });
});

// Decline request
document.querySelectorAll('.decline-request-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const requestId = this.dataset.requestId;
        
        if (!confirm('Decline this request?')) {
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Declining...';
        
        try {
            const response = await fetch('assets/lib/lifeline-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'donor_decline', 
                    request_id: requestId 
                })
            });
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert(result.error || 'Failed to decline request');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-times me-1"></i>Decline';
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-times me-1"></i>Decline';
        }
    });
});
</script>
</body>
</html>
