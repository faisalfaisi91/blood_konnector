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

    @media screen and (max-width: 380px) {
      .km__address .cn{
        font-size: 12px !important;
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

  <!-- breadcrumb start -->
  <div class="breadcrumb_section overflow-hidden ptb-150">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-6 col-md-8 col-sm-10 col-12 text-center">
          <h2>Contact Us</h2>
          <ul>
            <li><a href="index">Home</a></li>
            <li class="active">Contact Us</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <!-- breadcrumb end -->
  <!-- message box start -->
  <section class="km__message__box ptb-120">
    <div class="container">
      <div class="km__contact__form">
        <div class="row g-5">
          <div class="col-xl-7">
            <div class="km__box__form">
              <h4 class="mb-40">Get In Touch</h4>
              <p class="mb-30">
                For any queries, feedback, or support, feel free to contact us. We’re here to help!
              </p>
              <form action="#" class="km__main__form">
                <div class="row">
                  <div class="col-sm">
                    <input type="text" placeholder="Frist Name">
                  </div>
                  <div class="col-sm">
                    <input type="text" placeholder="Last Name">
                  </div>
                </div>
                <div class="row">
                  <div class="col-sm">
                    <input type="email" placeholder="Email">
                  </div>
                  <div class="col">
                    <input type="text" placeholder="Subject">
                  </div>
                </div>
                <div class="row">
                  <div class="col-sm">
                    <input type="number" placeholder="Enter Phone Number">
                  </div>
                  <div class="col">
                    <input type="number" placeholder="Enter Active WhatsApp Number">
                  </div>
                </div>
                <div class="row">
                  <div class="col-sm">
                    <textarea placeholder="Message" rows="3"></textarea>
                  </div>
                </div>
                <button type="submit" class="contact__btn">
                  Send Message
                  <i class="fa-solid fa-angles-right"></i>
                </button>
              </form>
            </div>
          </div>
          <div class="col-xl-5">
            <div class="km__form__content">
              <span class="sub__title">Direct Contact</span>
              <!-- <h4 class="km__form__title">Expanded Blood Donate Services Here</h4>
              <p class="form_des">
                For any queries, feedback, or support, feel free to contact us. We’re here to help!
              </p> -->
              <ul class="km__address">
                <li class="cn">
                  <i class="fa fa-phone-alt"></i>
                  <span>Emergency Line: (002) 012612457</span>
                </li>
                <li class="cn">
                  <i class="fa-solid fa-envelope"></i>
                  <span><a href="mailto:emergency@bloodkonnector.com" style="color: #fff">emergency@bloodkonnector.com</a> </span>
                </li>
                <li class="cn">
                  <i class="fa-solid fa-envelope"></i>
                  <span>General Queries: <br><a href="mailto:info@bloodkonnector.com" style="color: #fff">info@bloodkonnector.com</a> </span>
                </li>
                <li class="cn">
                  <i class="fa-solid fa-envelope"></i>
                  <span>Support: <br><a href="mailto:support@bloodkonnector.com" style="color: #fff">support@bloodkonnector.com</a> </span>
                </li>
                <li class="cn">
                  <i class="fa-solid fa-envelope"></i>
                  <span>For Partnerships & Collaborations: <br> <a href="mailto:collaboration@bloodkonnector.com" style="color: #fff">collaboration@bloodkonnector.com</a> </span>
                </li>
                <li class="cn">
                  <i class="fas fa-location-dot"></i>
                  <span>Location: Lahore, Punjab, <br> Pakistan</span>
                </li>
                <li class="cn">
                  <i class="fas fa-clock"></i>
                  <span>Mon - Fri: 8:00 am - 7:00 pm</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- message box ends -->

  <!-- social btn start -->
  <!-- <div class="km__social__btn">
    <div class="container">
      <div class="d-flex justify-content-sm-between justify-content-center align-items-center flex-wrap gap-20">
        <a href="#" class="facebok social-btn">Facebook</a>
        <a href="#" class="facebok social-btn">Instagram</a>
        <a href="#" class="twitter social-btn">X</a>
        <a href="#" class="google__plus social-btn">Youtube</a>
        <a href="#" class="Pinterest social-btn">LinkedIn</a>
      </div>
    </div>
  </div> -->
  <!-- social btn start -->

  <!-- testimonials sction start -->
  <!-- <section class="km__testimonial__slider__section ptb-120">
    <div class="container">
      <div class="km__main__slider slider-spacing">
        <div class="slider__item">
          <div class="row g-4 align-items-center">
            <div class=" col-md-5">
              <img src="assets/images/about/t-man.png" alt="images not found" class="img-fluidw-">
            </div>
            <div class="col-md-7">
              <div class="km__testimonial__content">
                <h4 class="mb-30">Satisfied Users Over The Globe</h4>
                <p class="mb-40 fs-24"> On the other hand, we denounce with righteous indignation and dislike men who
                  are so beguiled and demoralized by the charms of pleasure of the moment, so blinded by desire, that
                  they cannot foresee the pain and trouble</p>
              </div>
            </div>
          </div>
        </div>
        <div class="slider__item">
          <div class="row g-4 align-items-center">
            <div class="col-md-5">
              <img src="assets/images/about/t-man.png" alt="images not found" class="img-fluidw-">
            </div>
            <div class="col-md-7">
              <div class="km__testimonial__content">
                <h4 class="mb-30">Satisfied Users Over The Globe</h4>
                <p class="mb-40 fs-24"> On the other hand, we denounce with righteous indignation and dislike men who
                  are so beguiled and demoralized by the charms of pleasure of the moment, so blinded by desire, that
                  they cannot foresee the pain and trouble</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="km__bottom_slider slider-spacing">
        <div class="bottom__slider">
          <div class="slider__item d-flex gap-20">
            <div class="flex-shrink-0">
              <img src="assets/images/about/t-user.png" alt="">
            </div>
            <div class="content">
              <span>John Deo</span>
              <p>Volunteer</p>
            </div>
          </div>
          <div class="slider__item d-flex gap-20">
            <div class="flex-shrink-0">
              <img src="assets/images/about/t-user.png" alt="">
            </div>
            <div class="content">
              <span>John Deo</span>
              <p>Volunteer</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section> -->
  <!-- testimonials sction start -->
  <!-- map section start -->
  <div class="km__map">
    <!-- <iframe
      src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d48350.96166403442!2d-74.01578431620874!3d40.76345205485858!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c25992d302a075%3A0x5c6ba32d9744316!2s68%20Morton%20Street!5e0!3m2!1sbn!2sbd!4v1699265289408!5m2!1sbn!2sbd"
      style="border:0;" allowfullscreen="" loading="lazy"></iframe> -->
      <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d435519.22743715707!2d74.00471731731074!3d31.48310365553622!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39190483e58107d9%3A0xc23abe6ccc7e2462!2sLahore%2C%20Punjab%2C%20Pakistan!5e0!3m2!1sen!2s!4v1734104140247!5m2!1sen!2s" width="800" height="600" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
  </div>
  <!-- map section ends -->
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