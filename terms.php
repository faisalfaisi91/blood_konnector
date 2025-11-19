<?php 
session_start();
include('assets/lib/openconn.php');

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
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
    .alert { transition: opacity 0.5s ease-in-out; }
    .alert.show { opacity: 1; }
    .alert.d-none { opacity: 0; visibility: hidden; }
    .donor-grp { font-size: 35px; font-weight: bold; color: #fff; }
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
          <h2>Terms & Conditions</h2>
          <ul>
            <li><a href="index.php">Home</a></li>
            <li class="active">Terms & Conditions</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <!-- breadcrumb end -->

  <!-- Terms & Conditions Content -->
  <section class="km__terms__conditions__Section ptb-120">
    <div class="container">
      <div class="km_reasons mt-30">
        <h2 class="fs-42 mb-30">Terms of Use for Blood Konnector</h2>
        <p class="mb-30">Last Updated: [Insert Date]</p>
      </div>

      <div class="km_agreement mt-30">
        <h4 class="mb-30">1. Acceptance of Terms</h4>
        <p class="mb-30">
          By using bloodkonnector.com (the "Website"), you agree to these Terms of Use and our Privacy Policy. 
          If you disagree, do not access the Website.
        </p>
      </div>

      <div class="mt-30 km_essential">
        <h4 class="mb-30">2. Eligibility</h4>
        <ul class="km_pc_list">
          <li><i class="fa-regular fa-square-check"></i> Users must be 16+ (with parental consent) or 17+</li>
          <li><i class="fa-regular fa-square-check"></i> Must reside in Pakistan or its territories</li>
        </ul>
      </div>

      <div class="mt-30 km_understanding">
        <h4 class="mb-30">3. Account Security</h4>
        <p class="mb-30">You are responsible for:</p>
        <ul class="km_pc_list">
          <li><i class="fa-regular fa-square-check"></i> Maintaining account confidentiality</li>
          <li><i class="fa-regular fa-square-check"></i> Notifying us of unauthorized access</li>
          <li><i class="fa-regular fa-square-check"></i> Exiting your account after each session</li>
        </ul>
      </div>

      <div class="mt-30 km_dealing">
        <h4 class="mb-30">4. Prohibited Uses</h4>
        <p class="mb-30">You may not:</p>
        <ul class="km_pc_list">
          <li><i class="fa-regular fa-square-check"></i> Violate laws or harass others</li>
          <li><i class="fa-regular fa-square-check"></i> Transmit spam or malicious code</li>
          <li><i class="fa-regular fa-square-check"></i> Impersonate Blood Konnector or users</li>
          <li><i class="fa-regular fa-square-check"></i> Use automated tools to scrape data</li>
        </ul>
      </div>

      <div class="mt-30">
        <h4 class="mb-30">5. Intellectual Property</h4>
        <p class="mb-30">
          "Blood Konnector" trademarks and content are owned by BK. Unauthorized use of logos, designs, 
          or slogans is prohibited.
        </p>
      </div>

      <div class="mt-30 km_dealing">
        <h4 class="mb-30">6. Disclaimers</h4>
        <ul class="km_pc_list">
          <li><i class="fa-regular fa-square-check"></i> Website provided "as is" without warranties</li>
          <li><i class="fa-regular fa-square-check"></i> We don't guarantee service accuracy or uptime</li>
          <li><i class="fa-regular fa-square-check"></i> Not liable for third-party content</li>
        </ul>
      </div>

      <div class="mt-30">
        <h4 class="mb-30">7. Limitation of Liability</h4>
        <p class="mb-30">
          BK is not liable for indirect, incidental, or consequential damages arising from platform use.
        </p>
      </div>

      <div class="mt-30">
        <h4 class="mb-30">8. Governing Law</h4>
        <p class="mb-30">
          Governed by Pakistani law. Disputes must be resolved in Pakistani courts.
        </p>
      </div>

      <div class="mt-30">
        <h4 class="mb-30">9. Contact Us</h4>
        <p class="mb-30">
          For questions: <a href="mailto:info@bloodkonnector.com">info@bloodkonnector.com</a>
        </p>
      </div>

      <div class="km_fr-images mt-30">
        <div class="row g-4 text-center">
          <div class="col-md-6">
            <img src="assets/images/terms/t1.jpg" alt="Blood donation illustration" class="img-fluid">
          </div>
          <div class="col-md-6">
            <img src="assets/images/terms/t2.jpg" alt="Community support" class="img-fluid">
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php
    include('assets/includes/footer.php');
    include('assets/includes/link-js.php');
  ?>

  <script>
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