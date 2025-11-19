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
          <h2>FAQ’S</h2>
          <ul>
            <li><a href="index">Home</a></li>
            <li class="active">FAQ’S</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <!-- breadcrumb end -->

  <!-- faq section start -->
  <section class="km__faq__section ptb-120">
    <div class="container">
      <div class="row mb-5">
        <div class="col-12">
          <div class="common_title text-center">
            <p>FAQ'S</p>
            <h2>Frequently Asked Question</h2>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col">
          <div class="accordion km_accordion" id="km_accordion">
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                  How can I donate blood?
                </button>
              </h2>
              <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    To donate blood, simply sign up on our platform and provide your blood group and location. We'll connect you with recipients in need.
                  </p>        
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#collapseTwo">
                  What are the requirements for donating blood?
                </button>
              </h2>
              <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    To donate blood, you should be at least 18 years old, in good health, and weigh more than 50 kg. Additionally, make sure you haven't donated blood in the past 3 months.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#collapseThree">
                  Can I donate blood if I am on medication?
                </button>
              </h2>
              <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    It depends on the medication you're taking. Please check with a healthcare professional or refer to our donation guidelines for more details.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#four">
                  How often can I donate blood?
                </button>
              </h2>
              <div id="four" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    You can donate whole blood once every 56 days, or about every 8 weeks. For platelets or plasma, the donation frequency may vary.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#five">
                  I need blood urgently, how can I find a donor?
                </button>
              </h2>
              <div id="five" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Please provide your blood type and location, and we’ll help connect you with the nearest available donor. We prioritize urgent requests.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#six">
                  How do I request blood on Blood Konnector?
                </button>
              </h2>
              <div id="six" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    You can request blood by signing up on the platform, specifying your blood group and location, and our system will match you with suitable donors.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#seven">
                  What should I do if I cannot find a donor on the platform?
                </button>
              </h2>
              <div id="seven" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    If you can’t find a donor, our chatbot will suggest nearby blood donation centers or emergency options. You can also try extending your search radius.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#eight">
                  Can I donate blood to someone with a different blood group?
                </button>
              </h2>
              <div id="eight" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Blood donation compatibility depends on blood types. For example, type O negative is a universal donor, while type AB positive can receive from any blood group. Let me know the blood type and I can confirm compatibility.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#nin">
                  What are the blood types that can donate to me?
                </button>
              </h2>
              <div id="nin" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    If you’re AB+, you can receive blood from any group (A, B, AB, O). If you’re O-, you can only receive blood from O- donors. Let me know your blood type for more details!
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#ten">
                  How does Blood Konnector work?
                </button>
              </h2>
              <div id="ten" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Blood Konnector uses AI to match blood donors with recipients based on blood type and location. It connects you with those in need and makes donation easy and safe.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#elev">
                  Is my information safe on this platform?
                </button>
              </h2>
              <div id="elev" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Yes, we prioritize your privacy and use encryption to secure your personal data. Your information is only shared with users involved in the donation process.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#twl">
                  Can I track the status of my donation request?
                </button>
              </h2>
              <div id="twl" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Yes, once you request blood, you’ll receive updates on your request status and any available donors in your area.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#thrtn">
                  What should I do if I need blood in an emergency?
                </button>
              </h2>
              <div id="thrtn" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    In an emergency, please provide your blood type and location. We’ll immediately search for the closest donors and help you connect with them. You can also reach out to nearby blood banks if necessary.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#frtn">
                  How quickly can I get blood?
                </button>
              </h2>
              <div id="frtn" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    The time it takes depends on the availability of donors in your area. Our platform helps connect you with the nearest donor as quickly as possible.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#fiftn">
                  Why is blood donation important?
                </button>
              </h2>
              <div id="fiftn" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Blood donation saves lives by providing blood for surgeries, trauma care, cancer treatment, and more. Every donation can help multiple people in need.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#sixtn">
                  Can I donate blood if I have a health condition?
                </button>
              </h2>
              <div id="sixtn" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    It depends on the condition. Please consult with a healthcare provider before donating if you have any health concerns. You can also check our guidelines for specific conditions.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#svntn">
                  How long does a blood donation take?
                </button>
              </h2>
              <div id="svntn" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    The blood donation process usually takes around 10-15 minutes. However, the entire visit to the donation center may take 30-45 minutes, including registration and recovery time.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#eghtn">
                  How does the platform match donors and recipients?
                </button>
              </h2>
              <div id="eghtn" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Our AI-powered system matches donors with recipients based on blood type, location, and urgency of the request. We ensure the most suitable donor is selected to help save a life.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#nintn">
                  Can I specify the location where I want to donate blood?
                </button>
              </h2>
              <div id="nintn" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Yes, when registering, you can specify your location, and we’ll match you with recipients in need within that area.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#twnty">
                  How can I get help if I face issues on the platform?
                </button>
              </h2>
              <div id="twnty" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    If you need assistance, you can reach out to our support team via chat or email. We’ll help resolve any issues you encounter.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#twntyOne">
                  What should I do if the chatbot doesn't understand my request?
                </button>
              </h2>
              <div id="twntyOne" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    If the chatbot doesn't understand your query, please try rephrasing it or contact our support team for direct help.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#twntytwo">
                  Can I set up blood donation reminders?
                </button>
              </h2>
              <div id="twntytwo" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Yes, you can set up reminders for future blood donations or be notified when your blood group is in demand.
                  </p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#twntythree">
                  Does the platform support multiple languages?
                </button>
              </h2>
              <div id="twntythree" class="accordion-collapse collapse" data-bs-parent="#km_accordion">
                <div class="accordion-body">
                  <p>
                    Yes, our platform is expanding to include multiple languages. Let us know if you need assistance in a specific language!
                  </p>
                </div>
              </div>
            </div>


          </div>

        </div>
      </div>
    </div>
  </section>
  <!-- faq section start -->

  <!-- call now start -->
  <!-- <section class="hm1_counter call_now">
    <div class="container">
      <div class="row">
        <div class="col-12 ">
          <div class="call_content text-center">
            <span class="call_over"><i class="fa-solid fa-phone"></i></span>
            <p>START DONATING</p>
            <a href="tell:3335559090">
              <h2>Call Now: <span>333 555 9090</span></h2>
            </a>
            <ul class="d-flex gap-4 justify-content-center flex-wrap">
              <li>
                <span><i class="fa-solid fa-location-dot"></i></span>
                <span>New York - 1075 Firs Avenue</span>
              </li>
              <li>
                <span><i class="fa-solid fa-envelope"></i></span>
                <a href="mailto:company@domin.com">Donate@gmail.com</a>
              </li>

            </ul>
          </div>
        </div>
      </div>
    </div>
  </section> -->
  <!-- call now end -->

  <!-- submit area start -->
  <section class="km__submit__section ptb-120">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col">
          <h2 class="tittle text-center mb-60">Did’nt Find Answer? Submit Your Question.</h2>
        </div>
      </div>
      <div class="km__submit__form">
        <form action="#">
          <div class="row">
            <div class="col-12 col-sm-4">
              <input type="text" placeholder="Your Name*">
            </div>
            <div class="col-12 col-sm-4">
              <input type="email" placeholder="Your Email*">
            </div>
            <div class="col-12 col-sm-4">
              <input type="text" placeholder="Subject*">
            </div>
          </div>
          <div class="row">
            <div class="col">
              <textarea placeholder="Your Qustion*"></textarea>
            </div>
          </div>
          <div class="row">
            <div class="col">
              <button type="submit" class="primary__btn border-0 w-100">Submit Qustion</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </section>
  <!-- submit area start -->

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