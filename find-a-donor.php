<?php
  session_start();
  include('assets/lib/openconn.php');

  if (isset($_SESSION['user_id'])) {
      $userId = $_SESSION['user_id'];
      $currentTime = date('Y-m-d H:i:s'); // Current timestamp
      $updateQuery = "UPDATE users SET last_activity = ? WHERE user_id = ?";
      $stmt = $conn->prepare($updateQuery);
      $stmt->bind_param("si", $currentTime, $userId);
      $stmt->execute();
      
      // Maintain active profile if set (for recipients searching for donors)
      if (isset($_SESSION['active_profile']) && $_SESSION['active_profile'] === 'recipient') {
          $conn->query("UPDATE recipients SET status = 'active' WHERE user_id = '$userId'");
          $conn->query("UPDATE donors SET status = 'inactive' WHERE user_id = '$userId'");
      }
  }

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php 
    // Link Css...
    include('assets/includes/link-css.php');
  ?>
  <!-- CSS for alert (if not already included) -->
  <style>
    .status-badge.offline {
        background: #e74c3c;
    }
    /* Add animation for alert */
    .alert {
      transition: opacity 0.5s ease-in-out;
    }

    .alert.show {
      opacity: 1;
    }

    .alert.d-none {
      opacity: 0;
      visibility: hidden;
    }
    .donor-grp {
      font-size: 35px;
      font-weight: bold;
      color: #fff;
    }
    @media(max-width: 991px){
      .process {
        height: 50vw;
      }
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
  
  .donor-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #ddd;
  }

  .donor-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
  }

  .donor-card-header {
    position: relative;
    height: 220px;
  }

  .donor-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .donor-status {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 8px 20px;
    border-radius: 25px;
    font-size: 16px;
    font-weight: bold;
    color: white;
    background-color: #28a745;
    display: flex;
    align-items: center;
  }

  .donor-status.offline {
    background-color: #dc3545;
  }

  .donor-status i {
    margin-right: 8px;
  }

  .donor-card-body {
    padding: 20px;
    text-align: center;
    background: #f9f9f9;
  }

  .donor-card-body h3 {
    font-size: 24px;
    color: #333;
    font-weight: 700;
    margin: 10px 0;
  }

  .donor-card-body .blood-group,
  .donor-card-body .location,
  .donor-card-body .last-donation {
    font-size: 18px;
    color: #777;
    margin-bottom: 12px;
  }

  .donor-card-body .blood-group {
    font-weight: 600;
    color: #e74c3c;
  }

  .donor-card-body .location i,
  .donor-card-body .last-donation i {
    font-size: 20px;
    color: #3498db;
    margin-right: 10px;
  }

  .donor-card-footer {
    padding: 15px 20px;
    background: #ffffff;
    text-align: center;
    display: flex;
    justify-content: center;
    gap: 10px;
  }

  .donor-card-footer .btn {
    background-color: #e74c3c;
    color: white;
    padding: 12px 25px;
    font-size: 16px;
    text-transform: uppercase;
    border-radius: 30px;
    border: none;
    transition: background-color 0.3s ease;
  }

  .donor-card-footer .btn:hover {
    background-color: #c0392b;
  }

  .donor-card-footer .btn-chat {
    background-color: #3498db;
    padding: 12px 25px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    font-size: 16px;
  }

  .donor-card-footer .btn-chat:hover {
    background-color: #2980b9;
  }

  .donor-card-body .location,
  .donor-card-body .last-donation {
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .donor-card-body .location i,
  .donor-card-body .last-donation i {
    font-size: 20px;
    margin-right: 8px;
  }

  @media (max-width: 767px) {
    .donor-card {
      margin-bottom: 20px;
    }

    .donor-card-header {
      height: 180px;
    }

    .donor-card-body h3 {
      font-size: 20px;
    }

    .donor-card-body .blood-group,
    .donor-card-body .location,
    .donor-card-body .last-donation {
      font-size: 16px;
    }
  }


  </style>

</head>
<body>

  <!--preloader start-->
  <?php 
    // Preloader...
    include('assets/includes/preloader.php');
  ?>
  <!--preloader end-->

  <!-- scroll to top -->
  <?php 
    // scroll to top...
    include('assets/includes/scroll-to-top.php');
  ?>
  <!-- scroll to top -->

  <!-- header start -->
  <?php
    // Header
    include('assets/includes/header.php');
  ?>
  <!-- header end -->

    <!-- Breadcrumb Start -->
    <div class="breadcrumb_section overflow-hidden ptb-150">
      <div class="container">
          <div class="row justify-content-center">
              <div class="col-xl-6 col-lg-6 col-md-8 col-sm-10 col-12 text-center">
                  <h2>Find a donor</h2>
                  <ul>
                      <li><a href="index">Home</a></li>
                      <li class="active">Find Donor</li>
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

        <div class="col-md-12 col-sm-12 col-12">
          <!-- Advanced Filters -->
          <div class="filters mb-4" style="background-color: #EA062B; border-radius: 15px;">
            <div class="row g-4 py-4 px-3">
              <!-- Blood Type Filter -->
              <div class="col-md-3 col-sm-6 col-12">
                <select id="bloodTypeFilter" class="form-select form-control">
                  <option value="">All Blood Types</option>
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
              <div class="col-md-3 col-sm-6 col-12">
                <input type="text" id="locationFilter" class="form-control" placeholder="City or Area">
              </div>

              <!-- Availability Filter -->
              <div class="col-md-2 col-sm-6 col-12">
                <select id="availabilityFilter" class="form-select form-control">
                  <option value="">Any Availability</option>
                  <option value="weekdays">Weekdays</option>
                  <option value="weekends">Weekends</option>
                  <option value="emergency">Emergency Only</option>
                  <option value="anytime">Anytime</option>
                </select>
              </div>

              <!-- Emergency Contact Filter -->
              <div class="col-md-2 col-sm-6 col-12">
                <select id="emergencyFilter" class="form-select form-control">
                  <option value="">Emergency Contact</option>
                  <option value="yes">Yes</option>
                  <option value="no">No</option>
                </select>
              </div>

              <!-- Search Button -->
              <div class="col-md-2 col-sm-6 col-12">
                <button id="searchButton" style="background-color: #000;" class="btn w-100 text-white">
                  <i class="fas fa-search me-2"></i>Search
                </button>
              </div>
            </div>
            
            <!-- Advanced Options Toggle -->
            <div class="row px-3 pb-3">
              <div class="col-12">
                <a href="#" id="advancedToggle" class="text-white" style="text-decoration: none;">
                  <i class="fas fa-sliders-h me-2"></i>Advanced Filters
                </a>
              </div>
            </div>
            
            <!-- Advanced Filters (Hidden by default) -->
            <div id="advancedFilters" class="row g-4 px-3 pb-3 d-none">
              <div class="col-md-3 col-sm-6 col-12">
                <label class="text-white mb-2">Last Donation</label>
                <select id="lastDonationFilter" class="form-select form-control">
                  <option value="">Any Time</option>
                  <option value="1">Within 1 Month</option>
                  <option value="3">Within 3 Months</option>
                  <option value="6">Within 6 Months</option>
                  <option value="12">Within 1 Year</option>
                </select>
              </div>
              
              <div class="col-md-3 col-sm-6 col-12">
                <label class="text-white mb-2">Gender</label>
                <select id="genderFilter" class="form-select form-control">
                  <option value="">Any Gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              
              <div class="col-md-3 col-sm-6 col-12">
                <label class="text-white mb-2">Age Range</label>
                <select id="ageFilter" class="form-select form-control">
                  <option value="">Any Age</option>
                  <option value="18-25">18-25</option>
                  <option value="26-35">26-35</option>
                  <option value="36-45">36-45</option>
                  <option value="46-65">46-65</option>
                </select>
              </div>
              
              <div class="col-md-3 col-sm-6 col-12">
                <label class="text-white mb-2">Health Status</label>
                <select id="healthFilter" class="form-select form-control">
                  <option value="">Any Status</option>
                  <option value="eligible">Eligible</option>
                  <option value="not_eligible">Not Eligible</option>
                </select>
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

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="text-center d-none">
          <div class="spinner-border text-danger" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2">Finding donors...</p>
        </div>

        <!-- No Results Message -->
        <div id="noResults" class="col-12 text-center d-none">
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> No donors found matching your criteria. Please try different filters.
          </div>
        </div>

        <!-- Dynamic Donor List -->
        <div class="row justify-content-center" id="donorList">
          <!-- Donors will be loaded here by AJAX -->
        </div>

        <!-- Pagination -->
        <div class="row mt-4">
          <div class="col-12">
            <nav aria-label="Donor pagination">
              <ul class="pagination justify-content-center" id="pagination">
                <!-- Pagination will be loaded here by AJAX -->
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </section>



  <!-- footer section start -->
  <?php
    // Footer
    include('assets/includes/footer.php');
  ?>
  <!-- footer section end -->

  <!-- Javascript Files -->
  <?php
    // Link Js..
    include('assets/includes/link-js.php');
  ?>
  
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    // Toggle advanced filters
    $('#advancedToggle').click(function(e) {
        e.preventDefault();
        $('#advancedFilters').toggleClass('d-none');
        $(this).find('i').toggleClass('fa-sliders-h fa-times');
    });

    // Function to fetch donors with filters
    function fetchDonors(page = 1) {
        // Show loading indicator
        $('#loadingIndicator').removeClass('d-none');
        $('#donorList').html('');
        $('#noResults').addClass('d-none');
        
        // Get filter values
        const bloodType = $('#bloodTypeFilter').val();
        const location = $('#locationFilter').val();
        const availability = $('#availabilityFilter').val();
        const emergency = $('#emergencyFilter').val();
        const lastDonation = $('#lastDonationFilter').val();
        const gender = $('#genderFilter').val();
        const age = $('#ageFilter').val();
        const healthStatus = $('#healthFilter').val();
        
        $.ajax({
            url: 'fetch_donors.php',
            type: 'POST',
            data: {
                bloodType: bloodType,
                location: location,
                availability: availability,
                emergency: emergency,
                lastDonation: lastDonation,
                gender: gender,
                age: age,
                healthStatus: healthStatus,
                page: page
            },
            success: function(response) {
                $('#loadingIndicator').addClass('d-none');
                
                if (response.donors) {
                    $('#donorList').html(response.donors);
                    
                    // Update pagination
                    if (response.pagination) {
                        $('#pagination').html(response.pagination);
                    }
                    
                    // Show no results message if empty
                    if (response.donors.trim() === '') {
                        $('#noResults').removeClass('d-none');
                    }
                } else {
                    $('#noResults').removeClass('d-none');
                }
            },
            error: function() {
                $('#loadingIndicator').addClass('d-none');
                $('#noResults').removeClass('d-none');
                $('#noResults').html('<div class="alert alert-danger">Error loading donors. Please try again.</div>');
            }
        });
    }

    // Initial load
    fetchDonors();

    // Search button click
    $('#searchButton').click(function() {
        fetchDonors();
    });

    // Filter change events
    $('#bloodTypeFilter, #availabilityFilter, #emergencyFilter, #lastDonationFilter, #genderFilter, #ageFilter, #healthFilter').change(function() {
        fetchDonors();
    });

    // Location filter with debounce
    let locationTimer;
    $('#locationFilter').keyup(function() {
        clearTimeout(locationTimer);
        locationTimer = setTimeout(function() {
            fetchDonors();
        }, 500);
    });

    // Pagination click
    $(document).on('click', '.page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        fetchDonors(page);
    });
});

// Alert functions for chat button
function showLoginAlert() {
    Swal.fire({ 
        icon: 'info', 
        title: 'Login Required', 
        text: 'Please log in to chat with donors.', 
        confirmButtonText: 'Log In', 
        confirmButtonColor: '#EA062B' 
    }).then(r => r.isConfirmed && (location.href = 'sign-in'));
}

function showRegisterAlert() {
    Swal.fire({ 
        icon: 'info', 
        title: 'Register Required', 
        text: 'Register as recipient to chat with donors.', 
        confirmButtonText: 'Register Now', 
        confirmButtonColor: '#EA062B' 
    }).then(r => r.isConfirmed && (location.href = 'register-as-recipient'));
}

function showActivateAlert() {
    Swal.fire({ 
        icon: 'info', 
        title: 'Activate Profile', 
        text: 'Activate your Recipient profile to chat with donors.', 
        confirmButtonText: 'Activate Now', 
        confirmButtonColor: '#EA062B' 
    }).then(r => r.isConfirmed && (location.href = 'profile.php?activate=recipient'));
}
</script>
