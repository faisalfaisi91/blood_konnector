<?php 
session_start();
include('assets/lib/openconn.php'); // Database connection

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Fetch user data
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Pass user data to JavaScript
    echo "<script>
        const userData = {
            first_name: '{$user['first_name']}',
            last_name: '{$user['last_name']}',
            email: '{$user['email']}'
        };
    </script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include('assets/includes/link-css.php'); ?>
  <style>
    .alert {
      transition: opacity 0.5s ease-in-out;
    }
    .alert.show { opacity: 1; }
    .alert.d-none {
      opacity: 0;
      visibility: hidden;
    }
    .donor-grp {
      font-size: 35px;
      font-weight: bold;
      color: #fff;
    }
  </style>
</head>

<body>
  <?php 
    include('assets/includes/preloader.php');
    include('assets/includes/scroll-to-top.php');
    include('assets/includes/header.php');
  ?>

  <!-- breadcrumb start -->
  <div class="breadcrumb_section overflow-hidden ptb-150">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-6 col-md-8 col-sm-10 col-12 text-center">
          <h2>Privacy Policy</h2>
          <ul>
            <li><a href="index">Home</a></li>
            <li class="active">Privacy Policy</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <!-- breadcrumb end -->

  <!-- Privacy Policy Content -->
  <section class="km__text__details ptb-120">
    <div class="container">
      <div class="km_policy">
        <h2 class="mb-30 fs-42">Privacy Policy for Blood Konnector</h2>
        <p class="mb-30">Last Updated: Jan 01,2025</p>

        <div class="km_Information mb-30">
          <h4 class="mb-30">1. Data Collection & Usage</h4>
          <p class="mb-30">We collect the following information:</p>
          <ul class="km_pc_list">
            <li><i class="fa-regular fa-square-check"></i> Name, contact details, and location</li>
            <li><i class="fa-regular fa-square-check"></i> Blood type and donation history</li>
            <li><i class="fa-regular fa-square-check"></i> Health-related information voluntarily provided</li>
          </ul>
          <p class="mt-30"><strong>Purpose of Collection:</strong></p>
          <ul class="km_pc_list">
            <li><i class="fa-regular fa-square-check"></i> To connect blood donors and recipients</li>
            <li><i class="fa-regular fa-square-check"></i> To improve user experience and platform security</li>
            <li><i class="fa-regular fa-square-check"></i> To comply with legal and medical guidelines</li>
          </ul>
        </div>

        <div class="km_How mb-mt">
          <h4 class="mb-30">2. Third-Party Sharing & Compliance</h4>
          <p class="mb-30">
            - User data may be shared with hospitals, NGOs, and emergency services when required.<br>
            - We comply with legal authorities in case of medical emergencies or law enforcement requests.
          </p>
        </div>

        <div class="km_logs mt-30">
          <h4 class="mb-30">3. Data Security</h4>
          <ul class="km_pc_list">
            <li><i class="fa-regular fa-square-check"></i> All data is encrypted using SSL technology</li>
            <li><i class="fa-regular fa-square-check"></i> Secure authentication measures protect user accounts</li>
            <li><i class="fa-regular fa-square-check"></i> Users are responsible for keeping login credentials confidential</li>
          </ul>
        </div>

        <div class="km_Partners mt-30">
          <h4 class="mb-30">4. Data Retention & Deletion</h4>
          <p class="mb-30">
            - User data is stored securely for as long as required to provide services.<br>
            - Users can request data deletion by contacting <a href="mailto:info@bloodkonnector.com">info@bloodkonnector.com</a>
          </p>
        </div>

        <div class="km_ccpa mt-30">
          <h4 class="mb-30">5. Medical Disclaimer</h4>
          <p class="mb-30">
            - Blood Konnector does not verify the medical eligibility of donors or recipients.<br>
            - Users should consult healthcare professionals before donating or receiving blood.<br>
            - We are not liable for health-related issues arising from donations arranged through our platform.
          </p>
        </div>

        <div class="km_Blood mt-30">
          <h4 class="mb-30">6. Changes to This Policy</h4>
          <p class="mb-30">
            We may update this policy periodically. Continued use of the platform constitutes acceptance of changes.
          </p>
        </div>

        <div class="km_Consent mt-30">
          <h4 class="mb-30">Contact Us</h4>
          <p class="mb-30">
            For privacy-related questions: <a href="mailto:info@bloodkonnector.com">info@bloodkonnector.com</a>
          </p>
        </div>
      </div>
    </div>
  </section>

  <?php
    include('assets/includes/footer.php');
    include('assets/includes/link-js.php');
  ?>

  <script>
    // Authorization script
    document.addEventListener("DOMContentLoaded", function() {
      const restrictedLinks = document.querySelectorAll('.restricted-link');
      const alertDiv = document.getElementById('alertDiv');

      restrictedLinks.forEach(link => {
        link.addEventListener('click', function(event) {
          <?php if(!isset($_SESSION['user_id'])) { ?>
            event.preventDefault();
            alertDiv.classList.remove('d-none');
            alertDiv.classList.add('show');
            alertDiv.focus();
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            setTimeout(() => {
              window.location.href = "sign-in.php";
            }, 3000);
          <?php } ?>
        });
      });
    });
  </script>
</body>
</html>