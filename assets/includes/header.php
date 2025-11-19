<header>
    <div class="header_top d-none d-lg-block d-xl-block d-xxl-block">
      <div class="container">
        <div class="row">
          <div class="col-xl-3 col-lg-3">
            <div class="header_top_content">
              <span><i class="fa-solid fa-phone"></i></span>
              <a href="tel:+00 (00) 000 00 00">+00 (00) 000 00 00</a>
            </div>
          </div>
          <div class="col-xl-3 col-lg-3">
            <div class="header_top_content">
              <span><i class="fa-solid fa-envelope"></i></span>
              <a href="mailto:info@bloodkonnector.com">info@bloodkonnector.com</a>
            </div>
          </div>
          <div class="col-xl-3 col-lg-3">
            <div class="header_top_content">
              <span><i class="fa-solid fa-location-dot"></i></span>
              <a href="#">Lahore, Punjab, Pakistan</a>
            </div>
          </div>
          <div class="col-xl-3 col-lg-3">
            <div class="header_top_social">
              <ul class="d-flex">
                <li><a href="#"><i class="fa-brands fa-facebook-f"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-x"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-instagram"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-youtube"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-linkedin"></i></a></li>
                <li><a href="#"><i class="fa-brands fa-tiktok"></i></a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="header_bottom">
      <div class="container">
        <div class="row align-items-center position-relative">
          <div class="col-xl-2 col-lg-2 col-md-4 col-6">
            <div class="header_logo">
                <a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard' : 'index'; ?>">
                    <img src="assets/images/logo.png" alt="Blood Konnector Logo" class="img-fluid">
                </a>
            </div>
          </div>
          <div class="col-xl-7 col-lg-7 d-none d-xxl-block d-xl-block">
            <ul class="main_menu">
              <li class="position-relative">
                <a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard' : 'index'; ?>">Home</a>
              </li>
              <li class="position-relative">
                <a href="about">About Us</a>
              </li>
              <li class="position-relative">
                <a href="donors">Become a Donor</a>
              </li>
              <li class="position-relative">
                <a href="find-a-donor">Find a Donor</a>
              </li>
              <!-- <li class="position-relative">
                <a href="https://blogs.bloodkonnector.com">Blogs and Updates</a>
              </li> -->
              <li class="position-relative">
                <a href="contact">Contact</a>
              </li>
            </ul>
          </div>
          <div class="col-xl-3 col-lg-3 d-none d-xxl-block d-xl-block">
            <div class="header_search_menu d-flex align-items-center justify-content-end">
             <div class="dropdown dropdown_search">
                  <?php if (isset($_SESSION['user_id'])): ?>
                      <a href="profile?user_id=<?php echo $_SESSION['user_id']; ?>" class="red-btn text-danger">
                          <i class="fa-solid fa-user"></i>
                      </a>
                  <?php else: ?>
                      <a href="sign-in" class="red-btn text-danger">
                          <i class="fa-solid fa-user"></i>
                      </a>
                  <?php endif; ?>
              </div>
              
              <!-- Profile Switcher (only show if user is logged in) -->
              <?php 
              if (isset($_SESSION['user_id'])) {
                  // Initialize ProfileManager if not already done
                  if (!isset($profileManager)) {
                      require_once('assets/lib/ProfileManager.php');
                      $profileManager = new ProfileManager($conn);
                  }
                  // Display profile switcher
                  echo $profileManager->getProfileSwitcherHTML();
              }
              ?>

              <div class="dropdown dropdown_search">
                <button class="search-btn " data-bs-toggle="dropdown" aria-expanded="true"><i
                    class="fa-solid fa-magnifying-glass"></i></button>
                <div class="dropdown-menu dropdown-menu-end" data-popper-placement="bottom-end">
                  <form class="search-form d-flex align-items-center gap-2">
                    <input type="text" placeholder="Search..." class="theme-input bg-transparent">
                    <button type="submit" class="submit-btn">Go</button>
                  </form>
                </div>
              </div>

              <!-- right offcanvas -->
              <div class="offcanvas_right">
                <button class="header_toggle_btn bg-transparent offcanvas_btn" type="button" data-bs-toggle="offcanvas"
                  data-bs-target="#offcanvasRight" aria-controls="offcanvasRight">
                  <span></span>
                  <span></span>
                  <span></span>
                </button>
              </div>
            </div>
          </div>

          <!-- mobile menu bar -->
          <div class="col-lg-10 col-md-8 col-6 d-block d-xxl-none d-xl-none">
            <div class="d-flex align-items-center gap-2 justify-content-end">
              <div class="dropdown dropdown_search">
                <button class="search-btn " data-bs-toggle="dropdown" aria-expanded="true"><i
                    class="fa-solid fa-magnifying-glass"></i></button>
                <div class="dropdown-menu dropdown-menu-end" data-popper-placement="bottom-end">
                  <form class="search-form d-flex align-items-center gap-2">
                    <input type="text" placeholder="Search..." class="theme-input bg-transparent">
                    <button type="submit" class="submit-btn">Go</button>
                  </form>
                </div>
              </div>
              <div class="mobile_menu">
                <button class="header_toggle_btn bg-transparent border-0" type="button" data-bs-toggle="offcanvas"
                  data-bs-target="#offcanvas-mobile">
                  <span></span>
                  <span></span>
                  <span></span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>


<!--Start of Tawk.to Script-->
<script type="text/javascript">
    var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
    (function(){
    var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
    s1.async=true;
    s1.src='https://embed.tawk.to/69146a1851087619581a905d/1j9rrst43';
    s1.charset='UTF-8';
    s1.setAttribute('crossorigin','*');
    s0.parentNode.insertBefore(s1,s0);
    })();
</script>
<!--End of Tawk.to Script-->