<?php
session_start();
include("assets/lib/openconn.php");
require_once("assets/lib/ProfileManager.php");

// =============== 1. INITIALIZE PROFILE MANAGER ===============
$profileManager = new ProfileManager($conn);

// =============== 2. MUST BE LOGGED IN ===============
$profileManager->requireLogin();

// =============== 3. UPDATE LAST ACTIVITY ===============
$profileManager->updateLastActivity();

// =============== 4. FETCH USER CORE DATA ===============
$user = $profileManager->getUserInfo();
if (!$user) {
    die("User not found");
}

// =============== 5. CHECK USER ROLES ===============
$roles = $profileManager->getUserRoles();
$is_donor = $roles['is_donor'];
$is_recipient = $roles['is_recipient'];

// =============== 6. HANDLE QUICK NAVIGATION ===============
// Optional: Quick navigation to profile pages
// Users can still use the profile switcher in header
if (isset($_GET['view'])) {
    $view = $_GET['view'];
    
    if ($view === 'donor' && $is_donor) {
        header("Location: donor-profile");
        exit();
    }
    
    if ($view === 'recipient' && $is_recipient) {
        header("Location: recipient-profile");
        exit();
    }
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
        $new = "profile_{$user['user_id']}_" . uniqid() . ".$ext";
        $path = $targetDir . $new;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
            $stmt->bind_param("ss", $path, $user['user_id']);
            $stmt->execute();
            $_SESSION['success'] = "Profile picture updated!";
            $stmt->close();
        }
    }
}

// =============== 7. ONLINE STATUS ===============
$online = $profileManager->isUserOnline();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include('assets/includes/link-css.php'); ?>
  <style>
    .profile-container { max-width:900px; margin:0 auto; padding:30px; background:#fff; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,.1); text-align:center; }
    .status-badge { display:inline-block; padding:6px 14px; border-radius:20px; font-size:13px; font-weight:bold; }
    .online { background:#28a745; color:#fff; }
    .offline { background:#dc3545; color:#fff; }
    
    /* Profile Cards Overview */
    .profiles-overview { display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:25px; margin:30px 0; }
    .profile-card { background:linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding:30px; border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,.08); transition:all .3s ease; text-align:center; border:2px solid transparent; }
    .profile-card:hover { transform:translateY(-5px); box-shadow:0 10px 25px rgba(0,0,0,.12); }
    .donor-card { border-color:#28a745; }
    .donor-card:hover { background:linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); }
    .recipient-card { border-color:#EA062B; }
    .recipient-card:hover { background:linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); }
    
    .profile-icon { width:80px; height:80px; margin:0 auto 20px; background:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:35px; box-shadow:0 4px 10px rgba(0,0,0,.1); }
    .donor-card .profile-icon { color:#28a745; }
    .recipient-card .profile-icon { color:#EA062B; }
    
    .profile-card h3 { font-size:22px; margin:15px 0 10px; color:#2c3e50; font-weight:700; }
    .profile-card p { font-size:14px; color:#6c757d; margin-bottom:15px; }
    
    .availability-badge { display:inline-block; padding:8px 16px; border-radius:20px; font-size:12px; font-weight:600; margin:15px 0; }
    .status-available { background:#28a745; color:#fff; }
    .status-busy { background:#ffc107; color:#000; }
    .status-inactive { background:#6c757d; color:#fff; }
    .status-active { background:#007bff; color:#fff; }
    .status-fulfilled { background:#28a745; color:#fff; }
    .status-cancelled { background:#dc3545; color:#fff; }
    
    .view-profile-btn { display:inline-block; margin-top:15px; padding:12px 25px; background:#007bff; color:#fff; text-decoration:none; border-radius:8px; font-weight:600; transition:.3s; }
    .view-profile-btn:hover { background:#0056b3; transform:scale(1.05); }
    .donor-card .view-profile-btn { background:#28a745; }
    .donor-card .view-profile-btn:hover { background:#218838; }
    .recipient-card .view-profile-btn { background:#EA062B; }
    .recipient-card .view-profile-btn:hover { background:#c40524; }
    
    .action-buttons { display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-top:30px; }
    .action-buttons a { padding:12px 25px; border-radius:8px; font-size:15px; color:#fff; text-decoration:none; font-weight:600; transition:.3s; }
    .action-buttons a:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(0,0,0,.2); }
    .logout-btn { background:#dc3545; }
    
    .profile-image-container { position:relative; width:150px; height:150px; margin:0 auto 20px; cursor:pointer; }
    .profile-image { width:100%; height:100%; border-radius:50%; object-fit:cover; border:4px solid #007bff; transition:.3s; }
    .profile-image:hover { transform:scale(1.05); }
    .upload-overlay { position:absolute; bottom:8px; right:8px; background:#007bff; color:#fff; width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; transition:.3s; }
    .upload-overlay:hover { background:#0056b3; }
    
    @media (max-width: 768px) {
      .profiles-overview { grid-template-columns:1fr; gap:20px; }
      .profile-card { padding:25px; }
      .profile-icon { width:70px; height:70px; font-size:30px; }
    }
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

            <!-- USER PROFILES OVERVIEW -->
            <div class="profiles-overview">
              <?php if ($is_donor): ?>
                <div class="profile-card donor-card">
                  <div class="profile-icon">
                    <i class="fas fa-hand-holding-heart"></i>
                  </div>
                  <h3>Donor Profile</h3>
                  <p>Help save lives by donating blood</p>
                  <?php 
                  $donor_availability = $profileManager->getDonorAvailability();
                  $availability_label = $donor_availability === 'available' ? 'Available' : ($donor_availability === 'busy' ? 'Busy' : 'Inactive');
                  $availability_class = $donor_availability === 'available' ? 'status-available' : ($donor_availability === 'busy' ? 'status-busy' : 'status-inactive');
                  ?>
                  <span class="availability-badge <?= $availability_class ?>">
                    <?= $availability_label ?>
                  </span>
                  <a href="donor-profile" class="view-profile-btn">
                    <i class="fas fa-arrow-right"></i> View Profile
                  </a>
                </div>
              <?php endif; ?>
              
              <?php if ($is_recipient): ?>
                <div class="profile-card recipient-card">
                  <div class="profile-icon">
                    <i class="fas fa-hospital-user"></i>
                  </div>
                  <h3>Recipient Profile</h3>
                  <p>Find donors for blood transfusion</p>
                  <?php 
                  $recipient_status = $profileManager->getRecipientStatus();
                  $status_label = $recipient_status === 'active' ? 'Request Active' : ($recipient_status === 'fulfilled' ? 'Fulfilled' : 'Cancelled');
                  $status_class = $recipient_status === 'active' ? 'status-active' : ($recipient_status === 'fulfilled' ? 'status-fulfilled' : 'status-cancelled');
                  ?>
                  <span class="availability-badge <?= $status_class ?>">
                    <?= $status_label ?>
                  </span>
                  <a href="recipient-profile" class="view-profile-btn">
                    <i class="fas fa-arrow-right"></i> View Profile
                  </a>
                </div>
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