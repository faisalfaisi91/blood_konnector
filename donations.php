<?php 
  session_start();
  include('assets/lib/openconn.php'); // Database connection

  if (isset($_POST['btnDonate'])) {
      $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
      $email = mysqli_real_escape_string($conn, $_POST['email']);
      $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
      $donation_amount = mysqli_real_escape_string($conn, $_POST['donation_amount']);
      $donation_message = mysqli_real_escape_string($conn, $_POST['donation_message']);

      $targetDir = "assets/images/donation-images/";
      if (!is_dir($targetDir)) {
          mkdir($targetDir, 0755, true);
      }
      
      $fileName = basename($_FILES['donation_receipt']['name']);
      $filePath = $targetDir . time() . "_" . $fileName;
      
      if (move_uploaded_file($_FILES['donation_receipt']['tmp_name'], $filePath)) {
          $query = "INSERT INTO donations (full_name, email, phone_number, donation_amount, donation_message, receipt_path) 
                    VALUES ('$full_name', '$email', '$phone_number', '$donation_amount', '$donation_message', '$filePath')";
          
          if (mysqli_query($conn, $query)) {
              $_SESSION['success_message'] = "Donation submitted successfully!";
          } else {
              $_SESSION['error_message'] = "Error: " . mysqli_error($conn);
          }
      } else {
          $_SESSION['error_message'] = "Failed to upload receipt.";
      }
      header("Location: donations");
      exit();
  }
?>
<script>
    function validateDonationForm() {
        let fullName = document.getElementById("full_name").value.trim();
        let email = document.getElementById("email").value.trim();
        let phone = document.getElementById("phone_number").value.trim();
        let amount = document.getElementById("donation_amount").value.trim();
        let terms = document.getElementById("terms_conditions").checked;
        let file = document.getElementById("donation_receipt").files.length;
        
        let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        let phonePattern = /^[0-9\-\+]{10,15}$/;
        
        if (fullName === "" || email === "" || phone === "" || amount === "" || file === 0) {
            alert("All fields are required.");
            return false;
        }
        
        if (!emailPattern.test(email)) {
            alert("Enter a valid email address.");
            return false;
        }
        
        if (!phonePattern.test(phone)) {
            alert("Enter a valid phone number.");
            return false;
        }
        
        if (!terms) {
            alert("You must agree to the terms and conditions.");
            return false;
        }
        
        return true;
    }
</script>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php 
      // Link Css...
      include('assets/includes/link-css.php');
    ?>
    <style>
      /* Style for live preview */
      #profile_pic_preview {
          display: none;
          width: 100%;
          height: auto;
          margin-top: 10px;
      }
      .error-message {
          color: red;
          margin-bottom: 10px;
      }
    </style>
</head>
<body>
    <!-- Preloader Start -->
    <?php 
      include('assets/includes/preloader.php');
    ?>
    <!-- Preloader End -->

    <!-- Scroll to Top -->
    <?php 
      include('assets/includes/scroll-to-top.php');
    ?>
    <!-- Scroll to Top End -->

    <!-- Header Start -->
    <?php
      include('assets/includes/header.php');
    ?>
    <!-- Header End -->

    <!-- Breadcrumb Start -->
    <div class="breadcrumb_section overflow-hidden ptb-150">
      <div class="container">
          <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-6 col-md-8 col-sm-10 col-12 text-center">
                <h2>Make a Donation</h2>
                <ul>
                    <li><a href="index">Home</a></li>
                    <li class="active">Donation Form</li>
                </ul>
            </div>
          </div>
      </div>
    </div>
    <!-- Breadcrumb End -->

    <?php if(isset($_SESSION['success_message'])) { echo "<div class='alert alert-success'>" . $_SESSION['success_message'] . "</div>"; unset($_SESSION['success_message']); } ?>
    <?php if(isset($_SESSION['error_message'])) { echo "<div class='alert alert-danger'>" . $_SESSION['error_message'] . "</div>"; unset($_SESSION['error_message']); } ?>

    <!-- Message Box Start -->
    <section class="km__message__box ptb-120">
        <div class="container">
            <div class="km__contact__form">
                <div class="row">
    <!-- Donation Form on the Left -->
    <div class="col-lg-8">
        <form method="post" enctype="multipart/form-data" class="donation__form" onsubmit="return validateDonationForm()">
            <h2 class="text-center my-3 bg-primary text-white py-3 rounded">Make a Donation</h2>

            <!-- Full Name -->
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" name="full_name" id="full_name" placeholder="Enter your full name" class="form-control" required>
            </div>

            <!-- Email Address -->
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" placeholder="Enter your email" class="form-control" required>
            </div>

            <!-- Phone Number -->
            <div class="mb-3">
                <label for="phone_number" class="form-label">Phone Number</label>
                <input type="text" name="phone_number" id="phone_number" placeholder="+92-300-0000000" class="form-control" required>
                <small class="text-muted">Include country code if outside the US</small>
            </div>

            <!-- Donation Amount -->
            <div class="mb-3">
                <label for="donation_amount" class="form-label">Donation Amount (PKR)</label>
                <input type="number" name="donation_amount" id="donation_amount" placeholder="250 PKR" class="form-control" min="1" required>
            </div>

            <!-- Donation Receipt Upload -->
            <div class="mb-3">
                <label for="donation_receipt" class="form-label">Upload Donation Receipt</label>
                <input type="file" name="donation_receipt" accept="image/*,application/pdf" id="donation_receipt" class="form-control" required>
                <small class="text-muted">Upload the receipt for your donation</small>
            </div>

            <!-- Donation Message -->
            <div class="mb-3">
                <label for="donation_message" class="form-label">Tell Us More About Your Donation</label>
                <textarea name="donation_message" id="donation_message" class="form-control" placeholder="Share why you decided to donate today" rows="3"></textarea>
            </div>

            <!-- Terms and Conditions -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="" id="terms_conditions" required>
                <label class="form-check-label" for="terms_conditions">
                    I agree to the terms and conditions of the donation and understand the privacy policy associated with the use of my personal data.
                </label>
            </div>

            <button type="submit" name="btnDonate" class="btn btn-lg btn-success btn-block mt-4">
                Submit Donation
                <i class="fa-solid fa-heart"></i>
            </button>
        </form>
    </div>

    <!-- Sticky Notes for Admin Accounts -->
    <div class="col-lg-4">
        <h3 class="text-center mb-4">Admin Bank Accounts</h3>
        <div class="row g-3">
            <!-- Sticky Note 1 -->
            <div class="col-12">
                <div class="p-4 rounded text-dark" style="background-color: #ffeb99; box-shadow: 2px 2px 10px rgba(0,0,0,0.1);">
                    <strong>Account Name:</strong> John Doe<br>
                    <strong>Bank:</strong> ABC Bank<br>
                    <strong>Account Number:</strong> 1234567890<br>
                    <strong>IBAN:</strong> ABC1234567890<br>
                </div>
            </div>

            <!-- Sticky Note 2 -->
            <div class="col-12">
                <div class="p-4 rounded text-dark" style="background-color: #d1f7c4; box-shadow: 2px 2px 10px rgba(0,0,0,0.1);">
                    <strong>Account Name:</strong> Jane Smith<br>
                    <strong>Bank:</strong> XYZ Bank<br>
                    <strong>Account Number:</strong> 9876543210<br>
                    <strong>IBAN:</strong> XYZ0987654321<br>
                </div>
            </div>

            <!-- Add more sticky notes dynamically -->
        </div>
    </div>
</div>

            </div>
        </div>
    </section>
    <!-- Message Box Ends -->

    <!-- Footer Section Start -->
    <?php
      include('assets/includes/footer.php');
    ?>
    <!-- Footer Section End -->

    <!-- Javascript Files -->
    <?php
      include('assets/includes/link-js.php');
    ?>
  