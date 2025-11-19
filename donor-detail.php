<?php
session_start();
include('assets/lib/openconn.php');

// =============== 1. AUTO-SYNC ACTIVE PROFILE ===============
if (isset($_SESSION['user_id']) && isset($_SESSION['active_profile'])) {
    $userId = $_SESSION['user_id'];
    $mode = $_SESSION['active_profile'];

    if ($mode === 'recipient') {
        // Ensure recipient is active in DB
        $conn->query("UPDATE recipients SET status = 'active' WHERE user_id = '$userId'");
        $conn->query("UPDATE donors SET status = 'inactive' WHERE user_id = '$userId'");
    }
    if ($mode === 'donor') {
        $conn->query("UPDATE donors SET status = 'active' WHERE user_id = '$userId'");
        $conn->query("UPDATE recipients SET status = 'inactive' WHERE user_id = '$userId'");
    }
}

// =============== 2. CHECK IF VIEWER IS ACTIVE RECIPIENT .===============
$is_active_recipient = false;
$has_recipient_profile = false;

if (isset($_SESSION['user_id'])) {
    $loggedId = $_SESSION['user_id'];

    // Check session first
    if (isset($_SESSION['active_profile']) && $_SESSION['active_profile'] === 'recipient') {
        $is_active_recipient = true;
    }

    // Double-check with DB
    $check = $conn->prepare("SELECT status FROM recipients WHERE user_id = ?");
    $check->bind_param("s", $loggedId);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows > 0) {
        $has_recipient_profile = true;
        $db_status = $res->fetch_assoc()['status'];
        if ($db_status === 'active') {
            $is_active_recipient = true;
        }
    }
    $check->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .donor-card { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); transition: .3s; }
        .donor-card:hover { transform: translateY(-10px); }
        .donor-card-header { text-align: center; margin-bottom: 20px; }
        .donor-image-wrapper { position: relative; display: inline-block; }
        .donor-image { width: 180px; height: 180px; border-radius: 50%; object-fit: cover; border: 6px solid #EA062B; }
        .donor-status { background: #28a745; color: #fff; padding: 5px 15px; border-radius: 25px; font-weight: bold; margin-top: 15px; font-size: 0.9rem; }
        .donor-status.offline { background: #dc3545; }
        .donor-card-body h3 { font-size: 28px; font-weight: bold; color: #333; margin-bottom: 15px; }
        .donor-info p, .donor-description p { font-size: 16px; color: #555; margin-bottom: 10px; }
        .donor-info i, .donor-description i { color: #EA062B; margin-right: 10px; }
        .donor-card-footer { text-align: center; margin-top: 25px; }
        .btn-chat {
            background: #EA062B; color: #fff; padding: 15px 35px; font-size: 18px;
            border-radius: 30px; text-decoration: none; display: inline-block; transition: .3s;
        }
        .btn-chat:hover { background: #c50522; }
        .btn-chat.disabled { background: #ccc; color: #666; cursor: not-allowed; pointer-events: none; }

        @media (max-width: 768px) {
            .donor-image { width: 150px; height: 150px; }
            .donor-card-body h3 { font-size: 24px; }
            .btn-chat { padding: 12px 30px; font-size: 16px; }
        }
    </style>
</head>
<body>
    <?php include('assets/includes/preloader.php'); ?>
    <?php include('assets/includes/scroll-to-top.php'); ?>
    <?php include('assets/includes/header.php'); ?>

    <div class="breadcrumb_section overflow-hidden ptb-150">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-6 text-center">
                    <h2>Donor Detail</h2>
                    <ul><li><a href="index">Home</a></li><li class="active">Donor</li></ul>
                </div>
            </div>
        </div>
    </div>

    <?php
    if (isset($_GET['id'])) {
        $donorId = $_GET['id'];
        $query = "SELECT d.*, u.last_activity FROM donors d JOIN users u ON d.user_id = u.user_id WHERE d.user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $donorId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $donor = $result->fetch_assoc();
            $online = (time() - strtotime($donor['last_activity'])) < 300;
    ?>
            <section class="donor-detail my-5">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            <div class="donor-card" style="background:#fefef0;">
                                <div class="donor-card-header">
                                    <div class="donor-image-wrapper">
                                        <img src="<?= htmlspecialchars($donor['profile_pic'] ?: 'assets/images/donor.jpg') ?>" class="donor-image" alt="Donor">
                                        <?php if ($is_active_recipient): ?>
                                            <div class="donor-status <?= $online ? '' : 'offline' ?>">
                                                <?= $online ? 'Online' : 'Offline' ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="donor-card-body">
                                    <h3><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></h3>
                                    <div class="donor-info">
                                        <p><i class="fas fa-tint"></i> Blood Group: <strong class="bg-primary text-white py-1 px-2 rounded"><?= $donor['blood_type'] ?></strong></p>
                                        <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($donor['location'] ?: 'N/A') ?></p>
                                        <p><i class="fas fa-calendar-alt"></i> Last Donation: <?= $donor['last_donation_date'] ? date('F j, Y', strtotime($donor['last_donation_date'])) : 'Never' ?></p>
                                    </div>
                                    <div class="donor-description">
                                        <p><strong>Health:</strong> <?= nl2br(htmlspecialchars($donor['health_status'] ?: 'N/A')) ?></p>
                                        <p><strong>Conditions:</strong> <?= nl2br(htmlspecialchars($donor['medical_conditions'] ?: 'None')) ?></p>
                                        <p><strong>Emergency:</strong> <?= htmlspecialchars($donor['emergency_contact'] ?: 'N/A') ?></p>
                                        <p><strong>About:</strong> <?= nl2br(htmlspecialchars($donor['about'] ?: 'No info')) ?></p>
                                    </div>
                                </div>

                                <!-- CHAT BUTTON -->
                                <div class="donor-card-footer">
                                    <?php if (!isset($_SESSION['user_id'])): ?>
                                        <button onclick="showLoginAlert()" class="btn-chat">Log in to Chat</button>

                                    <?php elseif (!$has_recipient_profile): ?>
                                        <button onclick="showRegisterAlert()" class="btn-chat">Register as Recipient</button>

                                    <?php elseif (!$is_active_recipient): ?>
                                        <button onclick="showActivateAlert()" class="btn-chat">Activate Recipient Mode</button>

                                    <?php else: ?>
                                        <a href="chat.php?id=<?= urlencode($donor['user_id']) ?>" class="btn-chat">
                                            Chat with Donor
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
    <?php
        } else {
            echo "<p class='text-center text-danger'>Donor not found.</p>";
        }
        $stmt->close();
    } else {
        echo "<p class='text-center text-danger'>No donor selected.</p>";
    }
    ?>

    <?php include('assets/includes/footer.php'); ?>
    <?php include('assets/includes/link-js.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function showLoginAlert() {
        Swal.fire({ icon: 'info', title: 'Login Required', text: 'Please log in to chat.', confirmButtonText: 'Log In', confirmButtonColor: '#EA062B' })
            .then(r => r.isConfirmed && (location.href = 'sign-in'));
    }
    function showRegisterAlert() {
        Swal.fire({ icon: 'info', title: 'Register Required', text: 'Register as recipient to chat.', confirmButtonText: 'Register Now', confirmButtonColor: '#EA062B' })
            .then(r => r.isConfirmed && (location.href = 'register-as-recipient'));
    }
    function showActivateAlert() {
        Swal.fire({ icon: 'info', title: 'Activate Profile', text: 'Activate your Recipient profile to chat.', confirmButtonText: 'Activate Now', confirmButtonColor: '#EA062B' })
            .then(r => r.isConfirmed && (location.href = 'profile.php?activate=recipient'));
    }
    </script>
</body>
</html>