<?php
session_start();
include('assets/lib/openconn.php');

// Check login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please Sign In Your Account First!";
    header("Location: sign-in.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Check if already a recipient
$checkRecipientQuery = "SELECT * FROM recipients WHERE user_id = '$userId'";
$recipientResult = mysqli_query($conn, $checkRecipientQuery);

if (mysqli_num_rows($recipientResult) > 0) {
    $_SESSION['info'] = "You already have an active blood request!";
    header("Location: recipient-profile.php");
    exit();
}

// Fetch user data
$userQuery = "SELECT first_name, last_name, email FROM users WHERE user_id = '$userId'";
$userResult = mysqli_query($conn, $userQuery);

if (mysqli_num_rows($userResult) > 0) {
    $user = mysqli_fetch_assoc($userResult);
} else {
    $_SESSION['error'] = "User not found!";
    header("Location: sign-in.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnRecipient'])) {
    $requiredFields = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'contact_number' => 'Contact Number',
        'cnic' => 'CNIC',
        'location' => 'Location',
        'blood_type' => 'Blood Type',
        'urgency_level' => 'Urgency Level',
        'reason' => 'Reason'
    ];

    $errors = [];
    $data = [];

    foreach ($requiredFields as $field => $name) {
        if (empty($_POST[$field])) {
            $errors[$field] = "$name is required";
        } else {
            $data[$field] = mysqli_real_escape_string($conn, $_POST[$field]);
        }
    }

    // Validations
    if (!preg_match('/^\d{5}-\d{7}-\d{1}$/', $data['cnic'])) {
        $errors['cnic'] = "Invalid CNIC format (XXXXX-XXXXXXX-X)";
    }

    if (!preg_match('/^03\d{2}-\d{7}$/', $data['contact_number'])) {
        $errors['contact_number'] = "Invalid contact number format (0300-1234567)";
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }

    // File upload
    $allowedExtensions = ['jpg', 'jpeg', 'png'];
    $maxFileSize = 2 * 1024 * 1024;
    $profilePic = '';

    if ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $fileExtension = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors['profile_pic'] = "Only JPG, JPEG & PNG files allowed";
        }

        if ($_FILES['profile_pic']['size'] > $maxFileSize) {
            $errors['profile_pic'] = "File size exceeds 2MB limit";
        }

        $newFilename = "recipient_" . $userId . "_" . uniqid() . "." . $fileExtension;
        $targetDir = "assets/images/recipient-images/";

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $destination = $targetDir . $newFilename;

        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
            $errors['profile_pic'] = "Failed to upload image";
        } else {
            $profilePic = $destination;
        }
    } else {
        $errors['profile_pic'] = "Doctor's receipt image is required";
    }

    // Insert into database
    if (empty($errors)) {
        $message = mysqli_real_escape_string($conn, $_POST['message']);
        $hospital_name = mysqli_real_escape_string($conn, $_POST['hospital_name'] ?? '');
        $required_quantity = mysqli_real_escape_string($conn, $_POST['required_quantity'] ?? '1 unit');

        $insertQuery = "INSERT INTO recipients (
            user_id, first_name, last_name, email, contact_number, cnic, location, 
            blood_type, urgency_level, reason, profile_pic, message, hospital_name, required_quantity
        ) VALUES (
            '$userId', '{$data['first_name']}', '{$data['last_name']}', '{$data['email']}', 
            '{$data['contact_number']}', '{$data['cnic']}', '{$data['location']}', 
            '{$data['blood_type']}', '{$data['urgency_level']}', '{$data['reason']}', 
            '$profilePic', '$message', '$hospital_name', '$required_quantity'
        )";

        if (mysqli_query($conn, $insertQuery)) {
            mysqli_query($conn, "UPDATE users SET is_recipient = 1 WHERE user_id = '$userId'");
            
            // Store success message in session
            $_SESSION['registration_success'] = true;
            header("Location: register-as-recipient.php");
            exit();
        } else {
            $_SESSION['error'] = "Submission failed: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php 
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
        .section-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
        }

        .section-title {
            color: #2c3e50;
            font-weight: 600;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.5rem;
        }

        .profile-pic-container {
            position: relative;
            width: 150px;
            height: 150px;
        }

        .profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .upload-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: #3498db;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-btn:hover {
            background: #2980b9;
            transform: scale(1.1);
        }

        .form-control:read-only {
            background-color: #f8f9fa;
            opacity: 1;
        }

    </style>

    <!-- SweetAlert CSS and JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const userData = <?php echo json_encode($user); ?>;
            if (userData) {
                document.getElementById("first_name").value = userData.first_name || '';
                document.getElementById("last_name").value = userData.last_name || '';
                document.getElementById("email").value = userData.email || '';
            }

            // Show SweetAlert if registration was successful
            <?php if (isset($_SESSION['registration_success'])) { ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Registration Successful!',
                    text: 'Your blood request has been submitted successfully.',
                    confirmButtonColor: '#EA062B',
                    timer: 2000,
                    timerProgressBar: true,
                    willClose: () => {
                        window.location.href = 'recipient-profile.php';
                    }
                });
                <?php unset($_SESSION['registration_success']); ?>
            <?php } ?>

            // Show validation errors if any
            <?php if (isset($_SESSION['form_errors'])) { ?>
                const errors = <?php echo json_encode($_SESSION['form_errors']); ?>;
                let errorMessages = [];
                for (const [field, message] of Object.entries(errors)) {
                    errorMessages.push(message);
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: 'Please fix the following errors:<br><br>' + errorMessages.join('<br>'),
                    confirmButtonColor: '#EA062B'
                });
                <?php unset($_SESSION['form_errors']); ?>
            <?php } ?>
        });
    </script>

</head>

<body>

  <!-- Preloader -->
  <?php include('assets/includes/preloader.php'); ?>

  <!-- Scroll to Top -->
  <?php include('assets/includes/scroll-to-top.php'); ?>

  <!-- Header -->
  <?php include('assets/includes/header.php'); ?>

  <!-- Breadcrumb Section -->
  <div class="breadcrumb_section overflow-hidden ptb-150">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-6 col-md-8 col-sm-10 col-12 text-center">
          <h2>Request for Blood Donation</h2>
          <ul>
            <li><a href="index">Home</a></li>
            <li class="active">Recipient Registration</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Recipient Registration Form Section -->
<section class="km__message__box ptb-120">
  <div class="container">
    <div class="km__contact__form">
      <div class="row justify-content-center g-5">
        <div class="col-xl-8">
            <div id="alert-msg" class="alert alert-success d-none">
                <strong>Alert!</strong> Your request has been submitted successfully!
            </div>
        </div>

        <div class="col-xl-8">
          <div class="km__box__form">
            <h4 class="mb-4">Request Assistance</h4>
            <p class="mb-3">
              If you or someone you know is in need of a blood donation, please fill out the form below. We'll do our best to connect you with available donors.
            </p>
            <form method="POST" enctype="multipart/form-data" class="donor__form" onsubmit="return validateForm();">
              <!-- Personal Information Section -->
              <div class="section-box mb-4">
                <h4 class="section-title mb-4"><i class="fas fa-user-circle me-2"></i>Personal Information</h4>

                <div class="row g-3">
                  <!-- First Name -->
                  <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" id="first_name" placeholder="Enter First Name" class="form-control form-control-lg" />
                    <div class="invalid-feedback" id="first_name_error"></div>
                  </div>

                  <!-- Last Name -->
                  <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" id="last_name" placeholder="Enter Last Name" class="form-control form-control-lg" />
                    <div class="invalid-feedback" id="last_name_error"></div>
                  </div>
                </div>
              </div>

              <!-- Contact Information Section -->
              <div class="section-box mb-4">
                <h4 class="section-title mb-4"><i class="fas fa-address-card me-2"></i>Contact Details</h4>

                <div class="row g-3">
                  <!-- Email -->
                  <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" placeholder="Enter Email Address" class="form-control form-control-lg" />
                    <div class="invalid-feedback" id="email_error"></div>
                  </div>

                  <!-- Contact Number -->
                  <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" id="contact_number" placeholder="Enter Contact Number" class="form-control form-control-lg" />
                    <div class="invalid-feedback" id="contact_number_error"></div>
                  </div>

                  <!-- CNIC -->
                  <div class="col-md-12">
                    <label class="form-label">CNIC Number</label>
                    <input type="text" name="cnic" id="cnic" class="form-control form-control-lg" placeholder="Enter CNIC Number" />
                    <div class="invalid-feedback" id="cnic_error"></div>
                  </div>
                </div>
              </div>

              <!-- Location & Address Section -->
              <div class="section-box mb-4">
                <h4 class="section-title mb-4"><i class="fas fa-map-marker-alt me-2"></i>Location & Address</h4>

                <div class="row g-3">
                  <!-- Location -->
                  <div class="col-md-6">
                    <label class="form-label">Location (City/Neighborhood)</label>
                    <input type="text" name="location" id="location" class="form-control form-control-lg" placeholder="Enter Location" />
                    <div class="invalid-feedback" id="location_error"></div>
                  </div>

                  <!-- Reason for Blood Request -->
                  <div class="col-md-6">
                    <label class="form-label">Reason for Blood Request</label>
                    <input type="text" name="reason" id="reason" class="form-control form-control-lg" placeholder="Enter Reason" />
                    <div class="invalid-feedback" id="reason_error"></div>
                  </div>
                </div>
              </div>

              <!-- Blood Type and Urgency Level Section -->
              <div class="section-box mb-4">
                <h4 class="section-title mb-4"><i class="fas fa-tint me-2"></i>Blood Type & Urgency</h4>

                <div class="row g-3">
                  <!-- Blood Type -->
                  <div class="col-md-6">
                    <label class="form-label">Blood Type</label>
                    <select name="blood_type" id="blood_type" class="form-select form-select-lg">
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
                    <div class="invalid-feedback" id="blood_type_error"></div>
                  </div>

                  <!-- Urgency Level -->
                  <div class="col-md-6">
                    <label class="form-label">Urgency Level</label>
                    <select name="urgency_level" id="urgency_level" class="form-select form-select-lg">
                      <option value="">Select Urgency Level</option>
                      <option value="urgent">Urgent</option>
                      <option value="high">High</option>
                      <option value="medium">Medium</option>
                      <option value="low">Low</option>
                    </select>
                    <div class="invalid-feedback" id="urgency_level_error"></div>
                  </div>
                </div>
              </div>

              <!-- Doctor Recipt Image Upload -->
              <div class="section-box mb-4">
                <h4 class="section-title mb-4"><i class="fas fa-camera me-2"></i>Doctor Recipt Image</h4>

                <div class="row g-3">
                  <div class="col-md-12">
                    <input type="file" name="profile_pic" accept="image/*" id="profile_pic" class="form-control form-control-lg" onchange="previewImage(event)" />
                    <img id="profile_pic_preview" alt="Doctor Recipt Preview" style="max-width: 150px; display: none; margin-top: 10px;" class="rounded shadow" />
                  </div>
                </div>
              </div>

              <!-- Additional Message Section -->
              <div class="section-box mb-4">
                <h4 class="section-title mb-4"><i class="fas fa-comments me-2"></i>Additional Information</h4>

                <div class="row g-3">
                  <div class="col-md-12">
                    <textarea name="message" id="message" class="form-control form-control-lg" placeholder="Additional Information or Message" rows="3"></textarea>
                    <div class="invalid-feedback" id="message_error"></div>
                  </div>
                </div>
              </div>

              <!-- Submit Button -->
              <div class="d-grid mt-4">
                <button type="submit" name="btnRecipient" class="btn btn-primary btn-lg">
                  <i class="fas fa-paper-plane me-2"></i>Submit Request
                </button>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>
</section>

  <!-- Footer -->
  <?php include('assets/includes/footer.php'); ?>

  <!-- Javascript Files -->
  <?php include('assets/includes/link-js.php'); ?>

</body>
</html>

<script>
  function validateForm() {
    // Client-side validation
    const requiredFields = {
        'first_name': 'First Name',
        'last_name': 'Last Name',
        'email': 'Email',
        'contact_number': 'Contact Number',
        'cnic': 'CNIC',
        'location': 'Location',
        'blood_type': 'Blood Type',
        'urgency_level': 'Urgency Level',
        'reason': 'Reason'
    };
    
    let errors = [];
    
    // Check required fields
    for (const [field, name] of Object.entries(requiredFields)) {
        const value = document.getElementById(field).value.trim();
        if (!value) {
            errors.push(`${name} is required`);
        }
    }
    
    // Validate CNIC format
    const cnic = document.getElementById('cnic').value;
    if (cnic && !/^\d{5}-\d{7}-\d{1}$/.test(cnic)) {
        errors.push('CNIC must be in format XXXXX-XXXXXXX-X');
    }
    
    // Validate contact number
    const contactNumber = document.getElementById('contact_number').value;
    if (contactNumber && !/^03\d{2}-\d{7}$/.test(contactNumber)) {
        errors.push('Contact number must be in format 0300-1234567');
    }
    
    // Validate email
    const email = document.getElementById('email').value;
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.push('Please enter a valid email address');
    }
    
    // Check profile picture
    const profilePic = document.getElementById('profile_pic').files[0];
    if (!profilePic) {
        errors.push('Doctor\'s receipt image is required');
    } else {
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!validTypes.includes(profilePic.type)) {
            errors.push('Only JPG, JPEG & PNG files allowed');
        }
        if (profilePic.size > 2 * 1024 * 1024) {
            errors.push('Image must be less than 2MB');
        }
    }
    
    if (errors.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Form Validation Failed',
            html: errors.join('<br>'),
            confirmButtonColor: '#EA062B'
        });
        return false;
    }
    
    return true;
}

function previewImage(event) {
    const input = event.target;
    const preview = document.getElementById('profile_pic_preview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>