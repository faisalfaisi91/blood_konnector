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
  <!-- CSS for alert (if not already included) -->
  <style>
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

  <!-- breadcrumb start -->
  <div class="breadcrumb_section overflow-hidden ptb-150">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-6 col-md-8 col-sm-10 col-12 text-center">
          <h2>About Us</h2>
          <ul>
            <li><a href="index">Home</a></li>
            <li class="active">About Us</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <!-- breadcrumb end -->

  <!-- wellcome section start -->
  <section class="km__Who__section ptb-120" style="padding: 20px 0;">
    <div class="container">
      <div class="row align-items-center g-0 g-xxl-5 g-xl-5 g-lg-5">
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
          <div class="km__who__content">
            <h2 class="mb-30">Blood Konnector!</h2>
            <h5 class="mb-30">We don’t just connect people – We connect lives.</h5>
            <p class="mb-30">
              Our platform is powered by cutting-edge technology, designed to revolutionize the way blood donors and recipients find each other. With a few clicks, you can become a part of a life-saving network that spans communities, regions, and beyond. 
            </p>
            <!-- <a href="about.html" class="primary__btn">Explore Now</a> -->
          </div>
        </div>
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
          <div class="km__who__imgbx img">
            <img src="assets/images/about/doctor.jpg" alt="images not found" class="img-fluid" />
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- wellcome section ends -->

  <!-- counter start -->
  <!-- <div class="km__counterup___section">
    <div class="container">
      <div class="row g-4 justify-content-center">
        <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6 col-12 mb-4 mb-xl-0 mb-lg-0 mb-md-0">
          <ul class="km__counterup___box text-center">
            <li class="h1 counter mb-30"><span class="count">25</span></li>
            <li class="counter__content">Years of Experience</li>
          </ul>
        </div>
        <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6 col-12 mb-4 mb-xl-0 mb-lg-0 mb-md-0">
          <ul class="km__counterup___box text-center">
            <li class="h1 counter mb-30"><span class="count">430</span></li>
            <li class="counter__content">Blood Donations</li>
          </ul>
        </div>
        <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6 col-12 mb-4 mb-xl-0 mb-lg-0 mb-md-0">
          <ul class="km__counterup___box text-center">
            <li class="h1 counter mb-30"><span class="count">90</span></li>
            <li class="counter__content">Total Awards</li>
          </ul>
        </div>
        <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6 col-12">
          <ul class="km__counterup___box text-center">
            <li class="h1 counter mb-30"><span class="count">35</span></li>
            <li class="counter__content">Blood Cooperations</li>
          </ul>
        </div>
      </div>
    </div>
  </div> -->
  <!-- counter end -->

  <!-- help the people start -->
  <section class="help_people ptb-115" style="padding: 20px 0;">
    <div class="container">
      <div class="row align-items-center g-lg-5 g-xl-5 g-xxl-5">
        <div class="col-xl-6 col-lg-6 col-md-6 col-12 mb-5 mb-xl-0 mb-lg-0 mb-md-0">
          <div class="help_wrap position-relative">
            <img src="assets/images/a2.png" class="help_3" alt="" />
            <img src="assets/images/a2.jpg" class="help_4" alt="" />
            <img src="assets/images/help2.png" alt="" class="help_over" />
          </div>
        </div>
        <div class="col-xl-6 col-lg-6 col-md-6 col-12">
          <div class="help_content">
            <p class="red_color">Help The People in Need</p>
            <h2>Welcome to the Blood Konnector</h2>
            <p>At Blood Konnector, we connect lives. Using Automation, we match blood donors and recipients in real-time based on location, type, and urgency, ensuring quick help. Secure, impactful, and community-driven, we're redefining the future of blood donation</p>
            <div class="d-flex justify-content-between">
              <ul>
                <li><i class="fa-solid fa-angles-right"></i> Smart Matching</li>
                <li><i class="fa-solid fa-angles-right"></i> Instant Alerts</li>
                <li><i class="fa-solid fa-angles-right"></i> Data Security</li>
                <li><i class="fa-solid fa-angles-right"></i> Quality With Speed </li>
              </ul>
              <ul>
                <li><i class="fa-solid fa-angles-right"></i> 24/7 Support </li>
                <li><i class="fa-solid fa-angles-right"></i> Ai Powered Innovation</li>
                <li><i class="fa-solid fa-angles-right"></i>Real-Time Impact</li>
                <li><i class="fa-solid fa-angles-right"></i> 100% Free</li>                
              </ul>
            </div>
            <!-- <a href="about" class="explore_now red_btn">Explore Now</a> -->
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- help the people end -->

  <!-- process section start -->
  <section class="km__campaigns ptb-115" style="padding: 20px 0;">
    <div class="container">
      <div class="row align-items-center g-0 g-xxl-5 g-xl-5 g-lg-5">
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
          <div class="km__who__content">
            <h2 class="mb-30">Our Process</h2>
            <h5 class="mb-30">We don’t just connect people – We connect lives.</h5>
            <p class="mb-30">
              Blood Konnector was built with a singular goal: to ensure that no one faces the fear of losing a loved one due to the lack of blood. Using advanced algorithms, we match blood donors with recipients in real time, ensuring help arrives exactly when it’s needed.
            </p>
            <!-- <a href="about.html" class="primary__btn">Explore Now</a> -->
          </div>
        </div>
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
          <div class="km__who__imgbx img">
            <img src="assets/images/about/doctor.jpg" alt="images not found" class="img-fluid" />
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- process section ends -->

  <!-- how section start -->
  <section class="help_people ptb-115" style="padding: 20px 0;">
    <div class="container">
      <div class="row align-items-center g-lg-5 g-xl-5 g-xxl-5">
        <div class="col-xl-6 col-lg-6 col-md-6 col-12 mb-4 mb-xl-0 mb-lg-0 mb-md-0">
          <div class="help_wrap position-relative">
            <img src="assets/images/help1.png" alt="">
            <img src="assets/images/help2.png" alt="" class="help_over">
          </div>
        </div>
        <div class="col-xl-6 col-lg-6 col-md-6 col-12">
          <div class="help_content">
            <p class="red_color">How We Work</p>
            <h2>Welcome to the Blood Konnector</h2>
            <p>Imagine a world where every blood requirement meets its match within minutes. That’s the reality Blood Konnector is creating. Our platform uses AI-driven data analysis to identify and connect compatible donors with recipients based on location, blood type, and urgency.  </p>
            <div class="d-flex justify-content-between">
              <ul>
                <li><i class="fa-solid fa-angles-right"></i> Smart Matching</li>
                <li><i class="fa-solid fa-angles-right"></i> Instant Alerts</li>
                <li><i class="fa-solid fa-angles-right"></i> Data Security</li>
                <li><i class="fa-solid fa-angles-right"></i> Quality With Speed </li>
              </ul>
              <ul>
                <li><i class="fa-solid fa-angles-right"></i> 24/7 Support </li>
                <li><i class="fa-solid fa-angles-right"></i> Ai Powered Innovation</li>
                <li><i class="fa-solid fa-angles-right"></i>Real-Time Impact</li>
                <li><i class="fa-solid fa-angles-right"></i> 100% Free</li>                
              </ul>
            </div>
            <!-- <a href="about" class="explore_now red_btn">Explore Now</a> -->
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- how section ends -->

  <!-- Testimonials section start -->
  <!-- <section class="km__testimonials__section ptb-115">
    <div class="container">
      <div class="row mb-5 ">
        <div class="col-12">
          <div class="common_title text-center">
            <p>Testimonials</p>
            <h2>What Our Clients Say</h2>
          </div>
        </div>
      </div>

      <div class="testimonials__slider">
        <div class="slide__items">
          <div class="km_testimonials__bx text-center">
            <div class="row justify-content-center">
              <div class="col-xl-6 col-lg-6 col-md-7 col-sm-8 col-10">
                <div class="km__testimonial__content">
                  <span>
                    "
                  </span>
                  <h4 class="text-white mb-30">Professional services all the way</h4>
                  <p class="text-white mb-30"> These cases are perfectly simple and easy to distinguish. In a free hour,
                    when our power of choice is untrammelled and when nothing prevents our being able to do what we like
                    best, every pleasure is to be welcomed and every pain avoided. </p>
                </div>
                <div class="user mt-30">
                  <img src="assets/images/about/user.png" alt="images not found">
                  <h6 class="mt-30 text-white">Jhon Alexis <span>Marketing Staff</span></h6>
                </div>
              </div>
            </div>

          </div>
        </div>
        <div class="slide__items">
          <div class="km_testimonials__bx text-center">
            <div class="row justify-content-center">
              <div class="col-xl-6 col-lg-6 col-md-7 col-sm-8 col-10">
                <div class="km__testimonial__content">
                  <span>
                    "
                  </span>
                  <h4 class="text-white mb-30">Professional services all the way</h4>
                  <p class="text-white mb-30"> These cases are perfectly simple and easy to distinguish. In a free hour,
                    when our power of choice is untrammelled and when nothing prevents our being able to do what we like
                    best, every pleasure is to be welcomed and every pain avoided. </p>
                </div>
                <div class="user mt-30">
                  <img src="assets/images/about/user.png" alt="images not found">
                  <h6 class="mt-30 text-white">Jhon Alexis <span>Marketing Staff</span></h6>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </section> -->
  <!-- Testimonials section ends -->

  <!-- call now start -->
  <section class="hm1_counter call_now">
    <div class="container">
      <div class="row">
        <div class="col-12">
          <div class="call_content text-center">
            <span class="call_over"><i class="fa-solid fa-user-tie"></i></span>
            <p>MESSAGE FROM OUR CEO</p>
            <h2>Leadership with Vision and Purpose</h2>
            <blockquote>
              <p>
                Blood Konnector isn’t just a service; it’s a commitment to saving lives and building a world where compassion meets innovation. Together, we can create a future where every drop of blood reaches those who need it the most. Thank you for joining us on this life-saving journey.
              </p>
            </blockquote>
            <h4 style="color:#fff">- Mr. M Zoraiz Frukkh, Founder & CEO at Blood Konnector</h4>
            <ul class="d-flex gap-4 justify-content-center flex-wrap">
              <li>
                <span><i class="fa-solid fa-envelope"></i></span>
                <a href="mailto:ceo@bloodkonnector.com">ceo@bloodkonnector.com</a>
              </li>
              <li>
                <span><i class="fa-solid fa-building"></i></span>
                <span>Head Office: Lahore, Punjab, Pakistan</span>
              </li>
            </ul>            
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- call now end -->

  <!-- what we do start -->
  <section class="whatdo ptb-115">
    <div class="container">
      <div class="row mb-5">
        <div class="col-12">
          <div class="common_title text-center">
            <p>what we do</p>
            <h2>Donation Process</h2>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-12">
          <div class="what_progress">
            <ul>
              <img src="assets/images/p_line.png" class="progress_line" alt="" />
              <li>
                <div class="row">
                  <div class="col-xl-6 col-lg-6 col-md-7 col-sm-9 col-12">
                    <div class="progress_content d-flex align-items-center gap-xl-5 gap-lg-5 gap-md-4 gap-sm-3 gap-3">
                      <div class="p_content_left">
                        <h5>AI-Powered Innovation</h5>
                        <p>
                          We harness the potential of artificial intelligence to make life-saving connections faster and more efficient.  
                        </p>
                      </div>
                      <span class="progress_number"><i class="fa-solid fa-cogs"></i></span>
                    </div>
                  </div>
                </div>
              </li>
              <li>
                <div class="row justify-content-end">
                  <div class="col-xl-6 col-lg-6 col-md-7 col-sm-9 col-12">
                    <div class="progress_content d-flex align-items-center gap-xl-5 gap-lg-5 gap-md-4 gap-sm-3 gap-3">
                      <span class="progress_number"><i class="fa-solid fa-bolt"></i></span>
                      <div class="p_content_left p_content_right">
                        <h5>Real-Time Impact</h5>
                        <p>
                           Our technology ensures that every second counts when it comes to saving lives.  
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
              <li>
                <div class="row">
                  <div class="col-xl-6 col-lg-6 col-md-7 col-sm-9 col-12">
                    <div class="progress_content d-flex align-items-center gap-xl-5 gap-lg-5 gap-md-4 gap-sm-3 gap-3">
                      <div class="p_content_left">
                        <h5>Community-Driven</h5>
                        <p>
                          Blood Konnector thrives on the generosity and compassion of its users, creating a ripple effect of hope.  
                        </p>
                      </div>
                      <span class="progress_number"><i class="fa-solid fa-handshake"></i></span>
                    </div>
                  </div>
                </div>
              </li>
              <li>
                <div class="row justify-content-end">
                  <div class="col-xl-6 col-lg-6 col-md-7 col-sm-9 col-12">
                    <div class="progress_content d-flex align-items-center gap-xl-5 gap-lg-5 gap-md-4 gap-sm-3 gap-3">
                      <span class="progress_number"><i class="fa-solid fa-globe"></i></span>
                      <div class="p_content_left p_content_right">
                        <h5>A Future Redefined</h5>
                        <p>
                          With Blood Konnector, the future of blood donation is no longer about searching — it’s about finding. 
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>            
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- what we do end -->

  <!-- team start -->
  <section class="team ptb-115">
    <div class="container">
      <div class="row mb-5">
        <div class="col-12">
          <div class="common_title text-center">
            <p>Team members</p>
            <h2>Meet Volunteers</h2>
          </div>
        </div>
      </div>
      <div class="row justify-content-center">
        <div class="col-xl-4 col-lg-4 col-md-6 col-12 mb-4">
          <div class="team_details">
            <div class="team_img ">
              <img src="assets/images/t1.jpg" alt="" class="w-100">
              <ul class="d-flex">
                <li><a href="#"><i class="fa-brands fa-facebook-f"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-twitter"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-instagram"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-pinterest-p"></i></a></li>
              </ul>
            </div>
            <div class="team_content text-center">
              <a href="team-member.html">
                <h5>Nora Khaypeia</h5>
              </a>
              <p>Co-Founder</p>
            </div>
          </div>
        </div>
        <div class="col-xl-4 col-lg-4 col-md-6 col-12 mb-4">
          <div class="team_details">
            <div class="team_img ">
              <img src="assets/images/t2.jpg" alt="" class="w-100">
              <ul class="d-flex">
                <li><a href="#"><i class="fa-brands fa-facebook-f"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-twitter"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-instagram"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-pinterest-p"></i></a></li>
              </ul>
            </div>
            <div class="team_content text-center">
              <a href="team-member.html">
                <h5>Alex Joshan Deo</h5>
              </a>
              <p>Co-Founder</p>
            </div>
          </div>
        </div>
        <div class="col-xl-4 col-lg-4 col-md-6 col-12 mb-4">
          <div class="team_details">
            <div class="team_img ">
              <img src="assets/images/t3.jpg" alt="" class="w-100">
              <ul class="d-flex">
                <li><a href="#"><i class="fa-brands fa-facebook-f"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-twitter"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-instagram"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-pinterest-p"></i></a></li>
              </ul>
            </div>
            <div class="team_content text-center">
              <a href="team-member.html">
                <h5>Joshan Khaypeia</h5>
              </a>
              <p>Co-Founder</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- team end -->

  <!-- lets change start -->
 <!--  <section class="change red_bg">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-xl-9 col-lg-9 col-12">
          <div class="change_content">
            <h2>Let's change the world, Join us now!</h2>
            <p>
              Nor again is there anyone who loves or pursues or desires to
              obtain pain of itself, because it is pain, but occasionally
              circumstances occur in which toil and pain can procure reat
              pleasure.
            </p>
          </div>
        </div>
        <div class="col-xl-3 col-lg-3 col-12 text-xl-end text-lg-end text-center">
          <a href="contact.html">Contact Us</a>
        </div>
      </div>
    </div>
  </section> -->
  <!-- lets change end -->


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

  </body>
  </html>
  <script>
    // Check if user is logged in before allowing access
    function checkLoginStatus(page) {
      <?php if (isset($_SESSION['user_id'])) { ?>
        // If the user is logged in, redirect to the desired page
        window.location.href = page;
      <?php } else { ?>
        // If the user is not logged in, show the alert message
        showAlert();
        setTimeout(function() {
          window.location.href = "sign-in"; // Redirect to the sign-in page
        }, 3000); // Wait for 3 seconds before redirecting
      <?php } ?>
    }

    // Function to show the alert message
    function showAlert() {
      var alertDiv = document.getElementById("alertDiv");
      alertDiv.classList.remove("d-none");
      alertDiv.classList.add("show");

      // Hide the alert message after 3 seconds
      setTimeout(function() {
        alertDiv.classList.remove("show");
        alertDiv.classList.add("d-none");
      }, 3000);
    }

    document.addEventListener("DOMContentLoaded", function() {
      // Select restricted links and the alert div
      const restrictedLinks = document.querySelectorAll('.restricted-link');
        const alertDiv = document.getElementById('alertDiv');

        restrictedLinks.forEach(link => {
            link.addEventListener('click', function(event) {
                if (!isUserLoggedIn) {
                    event.preventDefault(); // Prevent link from working
                    alertDiv.classList.remove('d-none'); // Show the alert
                    alertDiv.classList.add('show'); // Bootstrap class to display alert
                    alertDiv.focus(); // Set focus on the alert div

                    // Scroll to alert div for visibility
                    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });
    });
  </script>