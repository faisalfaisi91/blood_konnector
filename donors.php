<?php
    session_start();
    include('assets/lib/openconn.php');
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Please sign in to register as a donor!";
        header("Location: sign-in");
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    
    // Check if user is already a donor
    $checkDonorQuery = "SELECT * FROM donors WHERE user_id = ?";
    $stmt = $conn->prepare($checkDonorQuery);
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $donorResult = $stmt->get_result();
    
    if ($donorResult->num_rows > 0) {
        $_SESSION['info'] = "You are already registered as a donor!";
        header("Location: donor-profile");
        exit();
    }
    
    // Fetch user data
    $query = "SELECT first_name, last_name, email, profile_pic FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    } else {
        $_SESSION['error'] = "User not found!";
        header("Location: sign-in");
        exit();
    }
    
    // Handle Form Submission (NEW CODE)
    $alert_script = ''; // For SweetAlert
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize and get inputs
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $age = (int)$_POST['age'];
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
        $whatsapp_number = mysqli_real_escape_string($conn, $_POST['whatsapp_number'] ?? '');
        $cnic = mysqli_real_escape_string($conn, $_POST['cnic']);
        $full_address = mysqli_real_escape_string($conn, $_POST['full_address'] ?? '');
        $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
        $blood_type = mysqli_real_escape_string($conn, $_POST['blood_type']);
        $contact_method = mysqli_real_escape_string($conn, $_POST['contact_method'] ?? 'app');
        $emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact'] ?? 'no');
        $health_status = mysqli_real_escape_string($conn, $_POST['health_status'] ?? 'eligible');
        $medical_conditions = mysqli_real_escape_string($conn, $_POST['medical_conditions'] ?? '');
        $last_donation_date = $_POST['last_donation_date'] ? date('Y-m-d', strtotime($_POST['last_donation_date'])) : NULL;
        $availability = mysqli_real_escape_string($conn, $_POST['availability'] ?? '');
        $about = mysqli_real_escape_string($conn, $_POST['about'] ?? '');
    
        // Server-side validation
        $errors = [];
        if (empty($first_name) || empty($last_name) || empty($email) || empty($contact_number) || empty($cnic) || empty($blood_type) || empty($gender) || empty($age)) {
            $errors[] = "All required fields must be filled.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if ($age < 18 || $age > 65) {
            $errors[] = "Age must be between 18 and 65.";
        }
        if (!preg_match('/^\d{5}-\d{7}-\d{1}$/', $cnic)) {
            $errors[] = "Invalid CNIC format (XXXXX-XXXXXXX-X).";
        }
        if (!preg_match('/^03\d{2}-\d{7}$/', $contact_number)) {
            $errors[] = "Invalid contact number format (0300-1234567).";
        }
    
        // Profile Picture Upload
        $profile_pic = NULL;
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $target_dir = 'assets/images/profiles/';
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = "donor_" . $userId . "_" . uniqid() . "." . $file_ext;
                $profile_pic = $target_dir . $new_filename;
                if (!move_uploaded_file($file['tmp_name'], $profile_pic)) {
                    $errors[] = "Failed to upload profile picture.";
                }
            } else {
                $errors[] = "Invalid file type or size (JPG/PNG/GIF, max 2MB).";
            }
        }
    
        if (empty($errors)) {
            // Insert into donors table
            $insert_query = "INSERT INTO donors (user_id, first_name, last_name, age, gender, email, contact_number, whatsapp_number, cnic, full_address, location, blood_type, contact_method, emergency_contact, health_status, medical_conditions, last_donation_date, availability, about, profile_pic) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssisssssssssssssssss", $userId, $first_name, $last_name, $age, $gender, $email, $contact_number, $whatsapp_number, $cnic, $full_address, $location, $blood_type, $contact_method, $emergency_contact, $health_status, $medical_conditions, $last_donation_date, $availability, $about, $profile_pic);
            
            if ($stmt->execute()) {
                // Update users table to mark as donor
                $update_user_query = "UPDATE users SET is_donor = 1 WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_user_query);
                $update_stmt->bind_param("s", $userId);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Success Alert and Redirect
                $alert_script = "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Registration Successful!',
                        text: 'You are now registered as a donor. Redirecting to your profile...',
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'donor-profile';
                    });
                </script>";
            } else {
                $errors[] = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
    
        // Error Alert
        if (!empty($errors)) {
            $error_msg = implode('<br>', $errors);
            $alert_script = "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Registration Failed',
                    html: '$error_msg',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#EA062B'
                });
            </script>";
        }
    }
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <title>Become a Donor | BloodKonnector</title>
    
    <style>
        :root {
            --primary-color: #EA062B;
            --secondary-color: #2c3e50;
            --light-gray: #f8f9fa;
            --border-color: #e9ecef;
            --error-color: #dc3545;
            --success-color: #28a745;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
        }
        
        .form-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .form-header {
            background: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .form-body {
            padding: 30px;
        }
        
        .section-box {
            background: var(--light-gray);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }
        
        .section-title {
            color: var(--secondary-color);
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 8px;
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            height: 48px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(234, 6, 43, 0.25);
        }
        
        .is-invalid {
            border-color: var(--error-color) !important;
        }
        
        .invalid-feedback {
            color: var(--error-color);
            font-size: 0.85rem;
            display: none;
        }
        
        .is-invalid + .invalid-feedback {
            display: block;
        }
        
        .profile-pic-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary-color);
        }
        
        .profile-pic-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-btn-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .upload-btn {
            border: 2px dashed #ced4da;
            color: #6c757d;
            background-color: white;
            padding: 15px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            text-align: center;
            cursor: pointer;
        }
        
        .upload-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .upload-btn-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 6px;
            width: 100%;
        }
        
        .btn-submit:hover {
            background-color: #c10a24;
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <?php include('assets/includes/preloader.php'); ?>
    
    <!-- Scroll to Top -->
    <?php include('assets/includes/scroll-to-top.php'); ?>
    
    <!-- Header -->
    <?php include('assets/includes/header.php'); ?>
    
    <!-- Breadcrumb -->
    <div class="breadcrumb_section overflow-hidden ptb-150">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-6 col-lg-6 col-md-8 col-sm-10 col-12 text-center">
                    <h2>Become a Donor</h2>
                    <ul>
                        <li><a href="index">Home</a></li>
                        <li class="active">Donor Registration</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Registration Form -->
    <section class="km__message__box ptb-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="form-section">
                        <div class="form-header">
                            <h2 class="mb-0">Donor Registration</h2>
                            <p class="mb-0 mt-2">Join our community of life-savers</p>
                        </div>
                        
                        <div class="form-body">
                            <form method="post" enctype="multipart/form-data" id="donorForm">
                                <!-- Personal Information -->
                                <div class="section-box">
                                    <h4 class="section-title">Personal Information</h4>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" name="first_name" id="first_name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                            <div class="invalid-feedback" id="first_name_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Name *</label>
                                            <input type="text" name="last_name" id="last_name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                            <div class="invalid-feedback" id="last_name_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Age *</label>
                                            <input type="number" name="age" id="age" class="form-control" min="18" max="65" required
                                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['age'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="age_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Gender *</label>
                                            <select name="gender" id="gender" class="form-select" required>
                                                <option value="">Select Gender</option>
                                                <option value="male" <?php echo (($_SESSION['form_data']['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                                <option value="female" <?php echo (($_SESSION['form_data']['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                                <option value="custom" <?php echo (($_SESSION['form_data']['gender'] ?? '') === 'custom') ? 'selected' : ''; ?>>Custom</option>
                                            </select>
                                            <div class="invalid-feedback" id="gender_error"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contact Information -->
                                <div class="section-box">
                                    <h4 class="section-title">Contact Information</h4>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Email *</label>
                                            <input type="email" name="email" id="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                            <div class="invalid-feedback" id="email_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Contact Number *</label>
                                            <input type="text" name="contact_number" id="contact_number" class="form-control" maxlength="12" 
                                                   placeholder="0300-1234567" required value="<?php echo htmlspecialchars($_SESSION['form_data']['contact_number'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="contact_number_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">WhatsApp Number *</label>
                                            <input type="text" name="whatsapp_number" id="whatsapp_number" class="form-control" maxlength="12" 
                                                   placeholder="0300-1234567" required value="<?php echo htmlspecialchars($_SESSION['form_data']['whatsapp_number'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="whatsapp_number_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CNIC *</label>
                                            <input type="text" name="cnic" id="cnic" class="form-control" maxlength="15" 
                                                   placeholder="XXXXX-XXXXXXX-X" required value="<?php echo htmlspecialchars($_SESSION['form_data']['cnic'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="cnic_error"></div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Full Address</label>
                                            <textarea name="full_address" class="form-control" rows="2"><?php echo htmlspecialchars($_SESSION['form_data']['full_address'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Location</label>
                                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($_SESSION['form_data']['location'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Blood Information -->
                                <div class="section-box">
                                    <h4 class="section-title">Blood Information</h4>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Blood Type *</label>
                                            <select name="blood_type" id="blood_type" class="form-select" required>
                                                <option value="">Select Blood Type</option>
                                                <option value="A+" <?php echo (($_SESSION['form_data']['blood_type'] ?? '') === 'A+') ? 'selected' : ''; ?>>A+</option>
                                                <option value="A-" <?php echo (($_SESSION['form_data']['blood_type'] ?? '') === 'A-') ? 'selected' : ''; ?>>A-</option>
                                                <option value="B+" <?php echo (($_SESSION['form_data']['blood_type'] ?? '') === 'B+') ? 'selected' : ''; ?>>B+</option>
                                                <option value="B-" <?php echo (($_SESSION['form_data']['blood_type'] ?? '') === 'B-') ? 'selected' : ''; ?>>B-</option>
                                                <option value="O+" <?php echo (($_SESSION['form_data']['blood_type'] ?? '') === 'O+') ? 'selected' : ''; ?>>O+</option>
                                                <option value="O-" <?php echo (($_SESSION['form_data']['blood_type'] ?? '') === 'O-') ? 'selected' : ''; ?>>O-</option>
                                                <option value="AB+" <?php echo (($_SESSION['form_data']['blood_type'] ?? '') === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                                <option value="AB-" <?php echo (($_SESSION['form_data']['blood_type'] ?? '') === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                            </select>
                                            <div class="invalid-feedback" id="blood_type_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Preferred Contact Method</label>
                                            <select name="contact_method" class="form-select">
                                                <option value="app" <?php echo (($_SESSION['form_data']['contact_method'] ?? 'app') === 'app') ? 'selected' : ''; ?>>App</option>
                                                <option value="sms" <?php echo (($_SESSION['form_data']['contact_method'] ?? '') === 'sms') ? 'selected' : ''; ?>>SMS</option>
                                                <option value="email" <?php echo (($_SESSION['form_data']['contact_method'] ?? '') === 'email') ? 'selected' : ''; ?>>Email</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Available for Emergency?</label>
                                            <select name="emergency_contact" class="form-select">
                                                <option value="no" <?php echo (($_SESSION['form_data']['emergency_contact'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No</option>
                                                <option value="yes" <?php echo (($_SESSION['form_data']['emergency_contact'] ?? '') === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Health Status</label>
                                            <select name="health_status" class="form-select">
                                                <option value="eligible" <?php echo (($_SESSION['form_data']['health_status'] ?? 'eligible') === 'eligible') ? 'selected' : ''; ?>>Eligible to Donate</option>
                                                <option value="not_eligible" <?php echo (($_SESSION['form_data']['health_status'] ?? '') === 'not_eligible') ? 'selected' : ''; ?>>Not Eligible</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Medical Conditions (if any)</label>
                                            <textarea name="medical_conditions" class="form-control" rows="3"><?php echo htmlspecialchars($_SESSION['form_data']['medical_conditions'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Donation Date</label>
                                            <input type="date" name="last_donation_date" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['last_donation_date'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Availability</label>
                                            <input type="text" name="availability" class="form-control" 
                                                   placeholder="e.g., Weekends, Evenings" value="<?php echo htmlspecialchars($_SESSION['form_data']['availability'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">About You</label>
                                            <textarea name="about" class="form-control" rows="3" 
                                                      placeholder="Tell us about yourself"><?php echo htmlspecialchars($_SESSION['form_data']['about'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Profile Picture -->
                                <div class="section-box">
                                    <h4 class="section-title">Profile Picture</h4>
                                    <div class="row g-3">
                                        <div class="col-12 text-center">
                                            <div class="profile-pic-container">
                                                <img id="profile_pic_preview" class="profile-pic-preview" 
                                                     src="<?php echo !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'assets/images/default-profile.jpg'; ?>" 
                                                     alt="Profile Preview">
                                            </div>
                                            <div class="upload-btn-wrapper">
                                                <button class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt me-2"></i>Choose Profile Picture
                                                </button>
                                                <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                                            </div>
                                            <div class="invalid-feedback text-center" id="profile_pic_error"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Terms and Conditions -->
                                <div class="form-check mt-4 mb-4">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="Privacy Policy & terms of Conditions BloodKonnector.pdf" class="text-danger">Terms and Conditions</a>
                                    </label>
                                    <div class="invalid-feedback">You must agree to the terms</div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="d-grid mt-4">
                                    <button type="submit" name="btnDonors" class="btn btn-submit">
                                        <i class="fas fa-heart me-2"></i>Register as Donor
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
    
    <!-- JavaScript -->
    <?php include('assets/includes/link-js.php'); ?>
    
     <!-- Include SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Echo the alert script -->
    <?php echo $alert_script; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Display success or error messages
            <?php if (isset($_SESSION['success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                    timer: 3000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'donor-profile';
                });
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                    confirmButtonColor: '#EA062B'
                });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['form_errors'])): ?>
                const errors = <?php echo json_encode($_SESSION['form_errors']); ?>;
                let errorMessages = Object.values(errors);
                
                for (const [field, message] of Object.entries(errors)) {
                    const element = document.getElementById(field);
                    if (element) {
                        element.classList.add('is-invalid');
                        const errorElement = document.getElementById(`${field}_error`);
                        if (errorElement) {
                            errorElement.textContent = message;
                        }
                    }
                }
                
                if (errorMessages.length > 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Please fix these errors',
                        html: errorMessages.join('<br>'),
                        confirmButtonColor: '#EA062B'
                    });
                    
                    // Scroll to first error
                    const firstError = document.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
                <?php unset($_SESSION['form_errors']); ?>
                <?php unset($_SESSION['form_data']); ?>
            <?php endif; ?>
            
            // Phone number formatting
            document.getElementById('contact_number').addEventListener('input', function(e) {
                formatPhoneNumber(this);
            });
            
            document.getElementById('whatsapp_number').addEventListener('input', function(e) {
                formatPhoneNumber(this);
            });
            
            // CNIC formatting
            document.getElementById('cnic').addEventListener('input', function(e) {
                formatCNIC(this);
            });
            
            // Profile picture preview
            document.getElementById('profile_pic').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        document.getElementById('profile_pic_preview').src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Form validation
            document.getElementById('donorForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Reset validation
                document.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });
                document.querySelectorAll('.invalid-feedback').forEach(el => {
                    el.textContent = '';
                    el.style.display = 'none';
                });
                
                let isValid = true;
                let errorMessages = [];
                
                // Validate required fields
                const requiredFields = [
                    { id: 'first_name', label: 'First Name' },
                    { id: 'last_name', label: 'Last Name' },
                    { id: 'email', label: 'Email' },
                    { id: 'contact_number', label: 'Contact Number' },
                    { id: 'whatsapp_number', label: 'WhatsApp Number' },
                    { id: 'cnic', label: 'CNIC' },
                    { id: 'blood_type', label: 'Blood Type' },
                    { id: 'gender', label: 'Gender' },
                    { id: 'age', label: 'Age' }
                ];
                
                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (!element.value.trim()) {
                        element.classList.add('is-invalid');
                        const errorElement = document.getElementById(`${field.id}_error`);
                        if (errorElement) {
                            errorElement.textContent = `${field.label} is required`;
                            errorElement.style.display = 'block';
                        }
                        errorMessages.push(`${field.label} is required`);
                        isValid = false;
                    }
                });
                
                // Validate CNIC format
                const cnic = document.getElementById('cnic');
                if (cnic.value && !/^\d{5}-\d{7}-\d{1}$/.test(cnic.value)) {
                    cnic.classList.add('is-invalid');
                    document.getElementById('cnic_error').textContent = 'Invalid CNIC format (XXXXX-XXXXXXX-X)';
                    document.getElementById('cnic_error').style.display = 'block';
                    errorMessages.push('Invalid CNIC format (XXXXX-XXXXXXX-X)');
                    isValid = false;
                }
                
                // Validate phone numbers
                const phoneFields = [
                    { id: 'contact_number', label: 'Contact Number' },
                    { id: 'whatsapp_number', label: 'WhatsApp Number' }
                ];
                phoneFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (element.value && !/^03\d{2}-\d{7}$/.test(element.value)) {
                        element.classList.add('is-invalid');
                        document.getElementById(`${field.id}_error`).textContent = `Invalid ${field.label} format (0300-1234567)`;
                        document.getElementById(`${field.id}_error`).style.display = 'block';
                        errorMessages.push(`Invalid ${field.label} format (0300-1234567)`);
                        isValid = false;
                    }
                });
                
                // Validate email
                const email = document.getElementById('email');
                if (email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                    email.classList.add('is-invalid');
                    document.getElementById('email_error').textContent = 'Invalid email format';
                    document.getElementById('email_error').style.display = 'block';
                    errorMessages.push('Invalid email format');
                    isValid = false;
                }
                
                // Validate age
                const age = document.getElementById('age');
                if (age.value) {
                    const ageNum = parseInt(age.value);
                    if (isNaN(ageNum) || ageNum < 18 || ageNum > 65) {
                        age.classList.add('is-invalid');
                        document.getElementById('age_error').textContent = 'Age must be 18-65';
                        document.getElementById('age_error').style.display = 'block';
                        errorMessages.push('Age must be 18-65');
                        isValid = false;
                    }
                }
                
                // Validate profile picture (optional, only if no existing profile pic)
                const profilePic = document.getElementById('profile_pic');
                const previewSrc = document.getElementById('profile_pic_preview').src;
                const isDefault = previewSrc.includes('default-profile.jpg');
                const hasExistingPic = '<?php echo !empty($user['profile_pic']) ? 'true' : 'false'; ?>';
                if (isDefault && hasExistingPic === 'false' && (!profilePic.files || profilePic.files.length === 0)) {
                    document.getElementById('profile_pic_error').textContent = 'Profile picture is required';
                    document.getElementById('profile_pic_error').style.display = 'block';
                    errorMessages.push('Profile picture is required');
                    isValid = false;
                } else if (profilePic.files && profilePic.files.length > 0) {
                    const file = profilePic.files[0];
                    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!validTypes.includes(file.type)) {
                        document.getElementById('profile_pic_error').textContent = 'Only JPG/PNG/GIF images allowed';
                        document.getElementById('profile_pic_error').style.display = 'block';
                        errorMessages.push('Only JPG/PNG/GIF images allowed');
                        isValid = false;
                    } else if (file.size > 2 * 1024 * 1024) {
                        document.getElementById('profile_pic_error').textContent = 'Image must be less than 2MB';
                        document.getElementById('profile_pic_error').style.display = 'block';
                        errorMessages.push('Image must be less than 2MB');
                        isValid = false;
                    }
                }
                
                // Validate terms checkbox
                if (!document.getElementById('terms').checked) {
                    document.getElementById('terms').classList.add('is-invalid');
                    document.querySelector('#terms + .invalid-feedback').style.display = 'block';
                    errorMessages.push('You must agree to the terms');
                    isValid = false;
                }
                
                if (isValid) {
                    this.submit();
                } else {
                    // Scroll to first error
                    const firstError = document.querySelector('.is-invalid, .invalid-feedback[style*="block"]');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Please fix the errors',
                        html: errorMessages.join('<br>'),
                        confirmButtonColor: '#EA062B'
                    });
                }
            });
            
            function formatPhoneNumber(input) {
                let value = input.value.replace(/\D/g, '');
                if (value.length > 4) {
                    value = value.substring(0, 4) + '-' + value.substring(4, 11);
                }
                input.value = value;
            }
            
            function formatCNIC(input) {
                let value = input.value.replace(/\D/g, '');
                value = value.substring(0, 13);
                let formatted = '';
                if (value.length > 5) {
                    formatted += value.substring(0, 5) + '-';
                    if (value.length > 12) {
                        formatted += value.substring(5, 12) + '-' + value.substring(12);
                    } else {
                        formatted += value.substring(5);
                    }
                } else {
                    formatted = value;
                }
                input.value = formatted;
            }
        });
    </script>
</body>
</html>