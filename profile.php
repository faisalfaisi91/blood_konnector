<?php
session_start();
include("assets/lib/openconn.php");

// =============== 1. MUST BE LOGGED IN ===============
if (!isset($_SESSION['user_id'])) {
    header("Location: sign-in");
    exit();
}
$user_id = $_SESSION['user_id'];

// =============== 2. FETCH USER CORE DATA ===============
$user_query = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_query->bind_param("s", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
if ($user_result->num_rows === 0) die("User not found");
$user = $user_result->fetch_assoc();
$user_query->close();

// =============== 3. CHECK ROLES ===============
$is_donor = false;
$is_recipient = false;

$donor_check = $conn->prepare("SELECT donor_id FROM donors WHERE user_id = ?");
$donor_check->bind_param("s", $user_id);
$donor_check->execute();
$is_donor = $donor_check->get_result()->num_rows > 0;
$donor_check->close();

$recip_check = $conn->prepare("SELECT recipient_id FROM recipients WHERE user_id = ?");
$recip_check->bind_param("s", $user_id);
$recip_check->execute();
$is_recipient = $recip_check->get_result()->num_rows > 0;
$recip_check->close();

// =============== 4. ACTIVE PROFILE MANAGEMENT ===============

// Default: no active mode
$active_mode = $_SESSION['active_profile'] ?? null; // 'donor' | 'recipient' | null

// Handle Switch Request
if (isset($_GET['activate'])) {
    $requested = $_GET['activate']; // 'donor' or 'recipient'

    if ($requested === 'donor' && $is_donor) {
        $_SESSION['active_profile'] = 'donor';
        // Deactivate recipient if exists
        if ($is_recipient) {
            $conn->query("UPDATE recipients SET status = 'inactive' WHERE user_id = '$user_id'");
        }
        header("Location: donor-profile");
        exit();
    }

    if ($requested === 'recipient' && $is_recipient) {
        $_SESSION['active_profile'] = 'recipient';
        // Deactivate donor if exists
        if ($is_donor) {
            $conn->query("UPDATE donors SET status = 'inactive' WHERE user_id = '$user_id'");
        }
        header("Location: recipient-profile");
        exit();
    }
}

// Auto-redirect if a profile is already active
if ($active_mode === 'donor' && $is_donor) {
    $conn->query("UPDATE donors SET status = 'active' WHERE user_id = '$user_id'");
    if ($is_recipient) {
        $conn->query("UPDATE recipients SET status = 'inactive' WHERE user_id = '$user_id'");
    }
    header("Location: donor-profile");
    exit();
}

if ($active_mode === 'recipient' && $is_recipient) {
    $conn->query("UPDATE recipients SET status = 'active' WHERE user_id = '$user_id'");
    if ($is_donor) {
        $conn->query("UPDATE donors SET status = 'inactive' WHERE user_id = '$user_id'");
    }
    header("Location: recipient-profile");
    exit();
}

// =============== 5. PROFILE PICTURE UPLOAD (Unchanged) ===============
$targetDir = "assets/images/userprofile-image/";
if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
    $max = 2 * 1024 * 1024;

    if ($file['error'] === 0 && in_array($file['type'], $allowed) && $file['size'] <= $max) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new = "profile_{$user_id}_" . uniqid() . ".$ext";
        $path = $targetDir . $new;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
            $stmt->bind_param("ss", $path, $user_id);
            $stmt->execute();
            $_SESSION['success'] = "Profile picture updated!";
            $stmt->close();
        }
    }
}

// =============== 6. ONLINE STATUS ===============
$conn->query("UPDATE users SET last_activity = NOW() WHERE user_id = '$user_id'");
$online = false;
$la = $conn->query("SELECT last_activity FROM users WHERE user_id = '$user_id'")->fetch_assoc()['last_activity'];
$online = (time() - strtotime($la)) < 300;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include('assets/includes/link-css.php'); ?>
  <style>
    .profile-container { max-width:800px; margin:0 auto; padding:30px; background:#fff; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,.1); text-align:center; }
    .status-badge { display:inline-block; padding:6px 14px; border-radius:20px; font-size:13px; font-weight:bold; }
    .online { background:#28a745; color:#fff; }
    .offline { background:#dc3545; color:#fff; }
    .mode-switch { margin:25px 0; display:flex; justify-content:center; gap:15px; flex-wrap:wrap; }
    .mode-btn { padding:12px 28px; border-radius:8px; font-weight:600; text-decoration:none; transition:.3s; }
    .btn-donor { background:#28a745; color:#fff; }
    .btn-recipient { background:#EA062B; color:#fff; }
    .btn-active { opacity:0.7; cursor:default; pointer-events:none; }
    .action-buttons { display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-top:20px; }
    .action-buttons a { padding:10px 20px; border-radius:6px; font-size:15px; color:#fff; text-decoration:none; }
    .logout-btn { background:#dc3545; }
    .profile-image-container { position:relative; width:150px; height:150px; margin:0 auto 20px; cursor:pointer; }
    .profile-image { width:100%; height:100%; border-radius:50%; object-fit:cover; border:4px solid #007bff; }
    .upload-overlay { position:absolute; bottom:8px; right:8px; background:#007bff; color:#fff; width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; }
    .current-mode { background:#f8f9fa; padding:12px; border-radius:8px; margin:15px 0; font-weight:500; color:#333; }
  </style>
</head>
<body>
  <?php include('assets/includes/preloader.php'); ?>
  <?php include('assets/includes/scroll-to-top.php'); ?>
  <?php include('assets/includes/header.php'); ?>

  <div class="breadcrumb_section ptb-150">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-xl-8">

          <div class="profile-container">

            <!-- Profile Picture -->
            <form id="uploadForm" method="POST" enctype="multipart/form-data">
              <input type="file" name="profile_pic" id="profile_pic" accept="image/*" style="display:none;">
            </form>

            <div class="profile-image-container" onclick="document.getElementById('profile_pic').click()">
              <?php if (!empty($user['profile_pic'])): ?>
                <img src="<?=htmlspecialchars($user['profile_pic'])?>" class="profile-image" alt="Profile">
              <?php else: ?>
                <div class="profile-image" style="background:#007bff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:50px;">
                  <?=strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1))?>
                </div>
              <?php endif; ?>
              <div class="upload-overlay"><i class="fa fa-camera"></i></div>
            </div>

            <!-- Online Status -->
            <span class="status-badge <?= $online ? 'online' : 'offline' ?>">
              <?= $online ? 'Online' : 'Offline' ?>
            </span>

            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
              <div class="alert alert-success" style="margin:15px 0;"> <?= $_SESSION['success']; unset($_SESSION['success']); ?> </div>
            <?php endif; ?>

            <h2><?=htmlspecialchars($user['first_name'].' '.$user['last_name'])?></h2>
            <p><strong>Email:</strong> <?=htmlspecialchars($user['email'])?></p>

            <!-- Current Active Profile -->
            <?php if ($active_mode): ?>
              <div class="current-mode">
                Currently Active: <strong><?=_ucfirst($active_mode)?> Mode</strong>
              </div>
            <?php else: ?>
              <div class="current-mode" style="color:#666;">
                No profile active. Choose one to continue.
              </div>
            <?php endif; ?>

            <!-- SWITCH BUTTONS -->
            <div class="mode-switch">
              <?php if ($is_donor): ?>
                <a href="?activate=donor" class="mode-btn btn-donor <?= ($active_mode==='donor')?'btn-active':'' ?>">
                  <?= ($active_mode==='donor') ? 'Active Donor' : 'Activate Donor' ?>
                </a>
              <?php endif; ?>
              <?php if ($is_recipient): ?>
                <a href="?activate=recipient" class="mode-btn btn-recipient <?= ($active_mode==='recipient')?'btn-active':'' ?>">
                  <?= ($active_mode==='recipient') ? 'Active Recipient' : 'Activate Recipient' ?>
                </a>
              <?php endif; ?>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="action-buttons">
              <?php if (!$is_donor): ?>
                <a href="donors" style="background:#17a2b8;">Register as Donor</a>
              <?php endif; ?>
              <?php if (!$is_recipient): ?>
                <a href="register-as-recipient" style="background:#6f42c1;">Register as Recipient</a>
              <?php endif; ?>
              <a href="logout" class="logout-btn" onclick="return confirm('Logout?')">Logout</a>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include('assets/includes/footer.php'); ?>
  <?php include('assets/includes/link-js.php'); ?>

  <script>
    // Auto upload profile picture
    document.getElementById('profile_pic').addEventListener('change', function(){
      if(this.files[0]){
        const reader = new FileReader();
        reader.onload = e => document.querySelector('.profile-image').src = e.target.result;
        reader.readAsDataURL(this.files[0]);
        document.getElementById('uploadForm').submit();
      }
    });
  </script>
</body>
</html>