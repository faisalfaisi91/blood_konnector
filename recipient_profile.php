<?php
session_start();
include("assets/lib/openconn.php");

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Fetch recipient data if available
    $query = "SELECT * FROM recipients WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipient = $result->fetch_assoc();
    
    // Determine if the user has registered as a recipient
    $isRecipient = $recipient ? true : false;
} else {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include('assets/includes/link-css.php'); ?>
</head>

<body>

  <?php include('assets/includes/preloader.php'); ?>
  <?php include('assets/includes/scroll-to-top.php'); ?>
  <?php include('assets/includes/header.php'); ?>

  <div class="breadcrumb_section overflow-hidden ptb-150">
    <div class="container">
        <div class="row justify-content-center">
        <div class="col-xl-12 col-lg-12 col-md-12 col-12 mb-5 mb-xl-0 mb-lg-0 mb-md-0">
          <div class="cam_details_left">
             <?php if ($isRecipient): ?>
            <div class="side_catagory side_donate text-center mb-5">
                
              <div class="row d-flex">
                <div class="col">
                  <img src="<?php echo htmlspecialchars($recipient['profile_pic'] ?: 'assets/images/default-user.jpg'); ?>" style="width: 350px; height: 250px;" alt="Profile Image" class="img-fluid">
                  <p style="text-transform: uppercase; width: 350px !important; display: flex !important; align-items: center !important; font-weight: bold;" class="mb-5 red_btn explore_now d-block text-center mx-auto px-3 text-white"><?php echo htmlspecialchars($recipient['first_name']) . ' ' . htmlspecialchars($recipient['last_name']); ?></p>
                </div>
                <div class="col d-flex">
                  <p style="width: 350px !important; display: flex !important; align-items: center !important; font-weight: bold;" class="mb-2 mx-2 red_btn explore_now d-block text-center mx-auto px-3 text-white"><?php echo htmlspecialchars($recipient['cnic']); ?></p>
                  <p style="width: 350px !important; display: flex !important; align-items: center !important; font-weight: bold;" class="mb-2 mx-2 red_btn explore_now d-block text-center mx-auto px-3 text-white"><?php echo htmlspecialchars($recipient['email']); ?></p>
                  <p style="width: 350px !important; display: flex !important; align-items: center !important; font-weight: bold;" class="mb-2 mx-2 red_btn explore_now d-block text-center mx-auto px-3 text-white">Blood Type: <?php echo htmlspecialchars($recipient['blood_type']); ?></p>
                </div>
              </div>
              <div class="d-flex">
                <p style="width: 350px !important; display: flex !important; align-items: center !important; font-weight: bold;" class="mb-2 mx-2 red_btn explore_now d-block text-center mx-auto px-3 text-white">Urgency Level: <?php echo htmlspecialchars($recipient['urgency_level']); ?></p>
                <p style="width: 720px !important; display: flex !important; align-items: center !important; font-weight: bold;" class="mb-2 mx-2 red_btn explore_now d-block text-center mx-auto px-3 text-white"><?php echo htmlspecialchars($recipient['address']) . ' ' . htmlspecialchars($recipient['location']); ?></p>
              </div>

              <div class="d-block">
                <p style="width: 98% !important; display: flex !important; align-items: center !important; font-weight: bold;" class="mb-2 mx-2 red_btn explore_now d-block text-center mx-auto px-3 text-white">Reason: <?php echo htmlspecialchars($recipient['reason']) . ' ' . htmlspecialchars($recipient['location']); ?></p>
                <p style="width: 98% !important; display: flex !important; align-items: center !important; font-weight: bold;" class="mb-2 mx-2 red_btn explore_now d-block text-center mx-auto px-3 text-white">Message: <?php echo htmlspecialchars($recipient['message']) . ' ' . htmlspecialchars($recipient['location']); ?></p>
              </div>

            </div>
            <?php else: ?>
              <!-- Show Register Button if Not Registered as Recipient -->
              <div class="side_catagory side_donate text-center mb-5">
                <h5 class="mb-5">Register as a Recipient</h5>
                <p class="mb-5">Join us to help save lives. Register as a blood recipient to make your request known to donors.</p>
                <a href="register_recipient.php" class="red_btn explore_now mx-3" style="width: 250px !important;">Register as Recipient</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include('assets/includes/footer.php'); ?>
  <?php include('assets/includes/link-js.php'); ?>

</body>
</html>
