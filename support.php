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
    .support-card {
      background: #fff;
      border-radius: 10px;
      padding: 30px;
      box-shadow: 0 5px 25px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }
    .emergency-contact {
      background: #ffe5e5;
      border-left: 4px solid #ff0000;
      padding: 20px;
      margin: 20px 0;
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
          <h2>Support Center</h2>
          <ul>
            <li><a href="index.php">Home</a></li>
            <li class="active">Support</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <!-- breadcrumb end -->

  <!-- Support Content Section -->
  <section class="km__support__section ptb-120">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="support-card">
            <h3 class="mb-4">Frequently Asked Questions</h3>
            
            <div class="accordion" id="faqAccordion">
              <!-- General Questions -->
              <div class="accordion-item">
                <h4 class="accordion-header" id="headingGeneral">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneral">
                    General Questions
                  </button>
                </h4>
                <div id="collapseGeneral" class="accordion-collapse collapse show">
                  <div class="accordion-body">
                    <ul class="km_pc_list">
                      <li>
                        <i class="fa-regular fa-circle-question me-2"></i>
                        <strong>How do I register as a donor?</strong>
                        <p>Create an account, complete your profile, and verify your health information through our simple onboarding process.</p>
                      </li>
                      <li>
                        <i class="fa-regular fa-circle-question me-2"></i>
                        <strong>Is my personal information secure?</strong>
                        <p>Yes, we use military-grade encryption and strict privacy controls to protect your data.</p>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Account Support -->
              <div class="accordion-item">
                <h4 class="accordion-header" id="headingAccount">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAccount">
                    Account Support
                  </button>
                </h4>
                <div id="collapseAccount" class="accordion-collapse collapse">
                  <div class="accordion-body">
                    <ul class="km_pc_list">
                      <li>
                        <i class="fa-regular fa-circle-question me-2"></i>
                        <strong>Forgot password?</strong>
                        <p>Use our password reset tool or contact our support team for assistance.</p>
                      </li>
                      <li>
                        <i class="fa-regular fa-circle-question me-2"></i>
                        <strong>Update personal information</strong>
                        <p>Edit your profile through the 'My Account' section after login.</p>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="support-card mt-4">
            <h3 class="mb-4">Contact Support Team</h3>
            <form id="supportForm" action="submit-support.php" method="POST">
              <div class="row g-3">
                <div class="col-md-6">
                  <input type="text" class="form-control" name="name" placeholder="Your Name" required>
                </div>
                <div class="col-md-6">
                  <input type="email" class="form-control" name="email" placeholder="Your Email" required>
                </div>
                <div class="col-12">
                  <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                </div>
                <div class="col-12">
                  <textarea class="form-control" rows="5" name="message" placeholder="Your Message" required></textarea>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary">
                    <i class="fa-regular fa-paper-plane me-2"></i>Send Message
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
          <div class="support-card">
            <h4 class="mb-3">Emergency Contact</h4>
            <div class="emergency-contact">
              <h5 class="text-danger"><i class="fa-solid fa-phone-volume me-2"></i>24/7 Support</h5>
              <ul class="list-unstyled">
                <li><strong>Medical Emergency:</strong> +92-XXX-XXXXXXX</li>
                <li><strong>Technical Support:</strong> support@bloodkonnector.com</li>
                <li><strong>Blood Emergency:</strong> emergency@bloodkonnector.com</li>
              </ul>
            </div>

            <div class="mt-4">
              <h5>Support Hours</h5>
              <ul class="list-unstyled">
                <li><i class="fa-regular fa-clock me-2"></i>Mon-Fri: 8 AM - 10 PM</li>
                <li><i class="fa-regular fa-clock me-2"></i>Sat-Sun: 9 AM - 6 PM</li>
              </ul>
            </div>

            <div class="mt-4">
              <h5>Live Chat</h5>
              <button class="btn btn-success w-100">
                <i class="fa-regular fa-comments me-2"></i>Start Chat
              </button>
            </div>
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
    // Support Page Specific Scripts
    document.addEventListener("DOMContentLoaded", function() {
      // Handle form submission
      const form = document.getElementById('supportForm');
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        // Add your submission logic here
        alert('Support request submitted successfully!');
        form.reset();
      });

      // Restricted access handling
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