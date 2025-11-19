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
          .filters {
          background: #f8f9fa;
          padding: 20px;
          border-radius: 10px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
    
        .filters .form-select,
        .filters .form-control {
          height: 45px;
          border-radius: 5px;
        }
    
        .filters .btn {
          height: 45px;
          border-radius: 5px;
        }
        .col-12.text-center p {
          color: red;
          font-size: 18px;
          font-weight: bold;
        }
        #alertDiv {
          position: fixed;
          top: 20px;
          right: 20px;
          width: 100%;
          max-width: 400px;
          z-index: 9999;
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
                  <h2>All Donors</h2>
                  <ul>
                      <li><a href="index">Home</a></li>
                      <li class="active">Donors</li>
                  </ul>
              </div>
          </div>
      </div>
    </div>
    <!-- Breadcrumb End -->

    <!-- register & donate start -->
    <section class="register_donate ptb-115 gray">
      <div class="container">
        <div class="row g-0 register_top justify-content-center">
          <div class="col-xl-12 col-lg-12 col-md-12 col-12">
            <div id="alertDiv" class="alert alert-danger d-none" role="alert">
              <strong>Alert!</strong> Please sign in to access this feature.
            </div>
          </div>

          <div class="col-md-10 col-sm-12 col-12">
            <!-- filters here -->
            <div class="filters mb-4"  style="background-color: #EA062B;">
              <div class="row g-5 py-5">
                <!-- Blood Type Filter -->
                <div class="col-md-5">
                  <select id="bloodTypeFilter" class="form-select form-control">
                    <option value="">Select Blood Type</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                  </select>
                </div>

                <!-- Location Filter -->
                <div class="col-md-5">
                  <input type="text" id="locationFilter" class="form-control" placeholder="Enter City or Area">
                </div>

                <!-- Search Button -->
                <div class="col-md-2">
                  <button id="searchButton" style="background-color: #000;" class="btn w-100 text-white">Search</button>
                </div>
              </div>
             </div>
          </div>
        </div>

        <div class="row justify-content-center">
          <div class="col-12 mb-5">
            <div class="common_title text-center">
              <p>Our Life-Saving Heroes</p>
              <h2>Heroes saving lives through blood</h2>
            </div>
          </div>

          <!-- Dynamic donor list -->
          <div class="row justify-content-center" id="donorList">
            <!-- Donors will be loaded here by AJAX -->
          </div>
        </div>
      </div>
    </section>
    <!-- register & donate end -->

  

    <!-- Footer Section Start -->
    <?php
      include('assets/includes/footer.php');
    ?>
    <!-- Footer Section End -->

    <!-- Javascript Files -->
    <?php
      include('assets/includes/link-js.php');
    ?>
</body>
</html>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function () {
    // Function to fetch donors based on selected filters
    function fetchDonors(bloodType = '', location = '') {
      $.ajax({
        url: 'fetch_all_donors.php',
        type: 'POST',
        data: {
          bloodType: bloodType,
          location: location
        },
        success: function (response) {
          // Display the result
          if (response.trim() === '') {
            $("#donorList").html(""); // Clear the donor list
            // Show the alert
            $("#alertDiv").removeClass('d-none');
          } else {
            $("#donorList").html(response); // Display the donors
            // Hide the alert if donors are found
            $("#alertDiv").addClass('d-none');
          }
        },
        error: function () {
          alert("Error fetching donors. Please try again.");
        }
      });
    }

    // Initial load to fetch all donors by default (no filters)
    fetchDonors();

    // Event handler for the search button click
    $("#searchButton").click(function () {
      var bloodType = $("#bloodTypeFilter").val();
      var location = $("#locationFilter").val();

      // Validate inputs
      if (!bloodType && !location) {
        alert("Please select a blood type or enter a location.");
        return;
      }

      // Fetch filtered donors
      fetchDonors(bloodType, location);
    });

    // Dismiss the alert and refresh the page when the dismiss button is clicked
    $("#dismissAlert").click(function () {
      $("#alertDiv").addClass('d-none'); // Hide the alert
      location.reload(); // Refresh the page
    });
  });
</script>
