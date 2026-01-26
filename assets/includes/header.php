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
                <a href="/">
                    <img src="assets/images/logo.png" alt="Blood Konnector Logo" class="img-fluid">
                </a>
            </div>
          </div>
          <div class="col-xl-7 col-lg-7 d-none d-xxl-block d-xl-block">
            <ul class="main_menu" style="margin-right: 50px;">
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
              <?php
                if (isset($_SESSION['user_id'])) {
                  if (!isset($profileManager)) {
                    require_once('assets/lib/ProfileManager.php');
                    $profileManager = new ProfileManager($conn);
                  }
                  $hasRecipient = $profileManager->hasRole('recipient');
                  $hasDonor = $profileManager->hasRole('donor');
                  $currentProfile = $profileManager->getCurrentProfile();
                  $hasLifeline = $profileManager->hasLifelineProfile();
                  
                  // Only show Emergency dropdown if user has at least one role
                  if ($hasRecipient || $hasDonor): ?>
                    <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="javascript:void(0);" id="emergencyMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">Emergency</a>
                    <ul class="dropdown-menu" aria-labelledby="emergencyMenu">
                      <?php 
                      // Show items based on CURRENT profile viewing
                      if ($currentProfile === 'donor'): 
                      ?>
                        <li><a class="dropdown-item" href="emergency-donor"><i class="fa-solid fa-ambulance"></i> Emergency Donor</a></li>
                        <li><a class="dropdown-item" href="donor-dashboard"><i class="fa-solid fa-heart"></i> Donor Dashboard</a></li>
                      <?php 
                      elseif ($currentProfile === 'lifeline'): 
                      ?>
                        <li><a class="dropdown-item" href="lifeline-recipient-dashboard"><i class="fa-solid fa-heartbeat"></i> LifeLine Dashboard</a></li>
                        <li><a class="dropdown-item" href="emergency-recipient"><i class="fa-solid fa-droplet"></i> Emergency Recipient</a></li>
                        <li><a class="dropdown-item" href="recipient-profile"><i class="fa-solid fa-user"></i> Recipient Profile</a></li>
                      <?php 
                      elseif ($currentProfile === 'recipient'): 
                      ?>
                        <li><a class="dropdown-item" href="emergency-recipient"><i class="fa-solid fa-droplet"></i> Emergency Recipient</a></li>
                        <li><a class="dropdown-item" href="recipient-dashboard"><i class="fa-solid fa-tachometer-alt"></i> Recipient Dashboard</a></li>
                        <?php if ($hasLifeline): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="lifeline-recipient-dashboard"><i class="fa-solid fa-heartbeat"></i> LifeLine Dashboard</a></li>
                        <?php endif; ?>
                      <?php 
                      else:
                        // No specific profile selected, show all available
                        if ($hasDonor):
                      ?>
                        <li><a class="dropdown-item" href="emergency-donor"><i class="fa-solid fa-ambulance"></i> Emergency Donor</a></li>
                        <li><a class="dropdown-item" href="donor-dashboard"><i class="fa-solid fa-heart"></i> Donor Dashboard</a></li>
                      <?php 
                        endif;
                        if ($hasRecipient):
                          if ($hasDonor): ?>
                        <li><hr class="dropdown-divider"></li>
                          <?php endif; ?>
                        <li><a class="dropdown-item" href="emergency-recipient"><i class="fa-solid fa-droplet"></i> Emergency Recipient</a></li>
                        <li><a class="dropdown-item" href="recipient-dashboard"><i class="fa-solid fa-tachometer-alt"></i> Recipient Dashboard</a></li>
                        <?php 
                          if ($hasLifeline):
                        ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="lifeline-recipient-dashboard"><i class="fa-solid fa-heartbeat"></i> LifeLine Dashboard</a></li>
                        <?php 
                          endif;
                        endif;
                      endif; ?>
                    </ul>
                    </li>
                  <?php endif;
                }
              ?>
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
                  
                  // Check if user has emergency roles (donor or recipient)
                  $hasRecipient = $profileManager->hasRole('recipient');
                  $hasDonor = $profileManager->hasRole('donor');
                  if ($hasRecipient || $hasDonor):
              ?>
                  <!-- Notification Bell Icon -->
                  <div class="dropdown notification-dropdown">
                      <button class="notification-btn position-relative" type="button" id="notificationBell" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="fa-solid fa-bell"></i>
                          <span class="notification-badge badge bg-danger" id="notificationBadge" style="display: none;">0</span>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end notification-dropdown-menu" id="notificationDropdown" style="min-width: 350px; max-width: 400px; max-height: 500px; overflow-y: auto;">
                          <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                              <h6 class="mb-0"><strong>Notifications</strong></h6>
                              <button class="btn btn-sm btn-link text-primary p-0" onclick="markAllNotificationsRead()">Mark all as read</button>
                          </div>
                          <div id="notificationList" class="notification-list">
                              <div class="text-center p-4 text-muted">
                                  <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                  <p class="mb-0">Loading notifications...</p>
                              </div>
                          </div>
                          <div class="p-2 border-top text-center">
                              <a href="<?php echo $hasRecipient ? 'emergency-recipient' : 'emergency-donor'; ?>" class="btn btn-sm btn-outline-primary">View All</a>
                          </div>
                      </div>
                  </div>
              <?php 
                  endif;
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
          
          <!-- Load notification bell script if user is logged in and has emergency role -->
          <?php 
          if (isset($_SESSION['user_id'])) {
              if (!isset($profileManager)) {
                  require_once('assets/lib/ProfileManager.php');
                  $profileManager = new ProfileManager($conn);
              }
              $hasRecipient = $profileManager->hasRole('recipient');
              $hasDonor = $profileManager->hasRole('donor');
              if ($hasRecipient || $hasDonor):
          ?>
              <script src="assets/js/notification-bell.js"></script>
          <?php 
              endif;
          }
          ?>

          <!-- mobile menu bar -->
          <div class="col-lg-10 col-md-8 col-6 d-block d-xxl-none d-xl-none">
            <div class="d-flex align-items-center gap-2 justify-content-end">
              <?php 
              if (isset($_SESSION['user_id'])) {
                  if (!isset($profileManager)) {
                      require_once('assets/lib/ProfileManager.php');
                      $profileManager = new ProfileManager($conn);
                  }
                  $hasRecipient = $profileManager->hasRole('recipient');
                  $hasDonor = $profileManager->hasRole('donor');
                  if ($hasRecipient || $hasDonor):
              ?>
                  <!-- Notification Bell Icon (Mobile) -->
                  <div class="dropdown notification-dropdown">
                      <button class="notification-btn position-relative" type="button" id="notificationBellMobile" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="fa-solid fa-bell"></i>
                          <span class="notification-badge badge bg-danger" id="notificationBadgeMobile" style="display: none;">0</span>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end notification-dropdown-menu" id="notificationDropdownMobile" style="min-width: 300px; max-width: 90vw; max-height: 500px; overflow-y: auto;">
                          <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                              <h6 class="mb-0"><strong>Notifications</strong></h6>
                              <button class="btn btn-sm btn-link text-primary p-0" onclick="markAllNotificationsRead()">Mark all as read</button>
                          </div>
                          <div id="notificationListMobile" class="notification-list">
                              <div class="text-center p-4 text-muted">
                                  <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                  <p class="mb-0">Loading notifications...</p>
                              </div>
                          </div>
                          <div class="p-2 border-top text-center">
                              <a href="<?php echo $hasRecipient ? 'emergency-recipient' : 'emergency-donor'; ?>" class="btn btn-sm btn-outline-primary">View All</a>
                          </div>
                      </div>
                  </div>
              <?php 
                  endif;
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