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
    
    // Fetch tutorial video link from settings (if table exists)
    $tutorial_video_link = '#';
    try {
        $settings_query = "SELECT setting_value FROM settings WHERE setting_key = 'donor_tutorial_video' LIMIT 1";
        $settings_result = $conn->query($settings_query);
        if ($settings_result && $settings_result->num_rows > 0) {
            $setting_row = $settings_result->fetch_assoc();
            $tutorial_video_link = $setting_row['setting_value'] ?? '#';
        }
    } catch (Exception $e) {
        // Settings table doesn't exist yet, use default
        $tutorial_video_link = '#';
    }
    
    // Handle Form Submission (NEW CODE)
    $alert_script = ''; // For SweetAlert
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize and get inputs
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');
        $father_name = mysqli_real_escape_string($conn, $_POST['father_name'] ?? '');
        $age = (int)($_POST['age'] ?? 0);
        $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number'] ?? '');
        $whatsapp_number = mysqli_real_escape_string($conn, $_POST['whatsapp_number'] ?? '');
        $emergency_contacts = mysqli_real_escape_string($conn, $_POST['emergency_contacts'] ?? '');
        $cnic = mysqli_real_escape_string($conn, $_POST['cnic'] ?? '');
        $occupation = mysqli_real_escape_string($conn, $_POST['occupation'] ?? '');
        $full_address = mysqli_real_escape_string($conn, $_POST['full_address'] ?? '');
        $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
        $blood_type = mysqli_real_escape_string($conn, $_POST['blood_type'] ?? '');
        $contact_method = mysqli_real_escape_string($conn, $_POST['contact_method'] ?? 'app');
        $emergency_availability = mysqli_real_escape_string($conn, $_POST['emergency_availability'] ?? 'no');
        $last_donation_date = !empty($_POST['last_donation_date']) ? date('Y-m-d', strtotime($_POST['last_donation_date'])) : NULL;
        
        // Health Status Fields
        $chronic_diseases = mysqli_real_escape_string($conn, $_POST['chronic_diseases'] ?? 'no');
        $chronic_diseases_details = mysqli_real_escape_string($conn, $_POST['chronic_diseases_details'] ?? '');
        $rejected_donation = mysqli_real_escape_string($conn, $_POST['rejected_donation'] ?? 'no');
        $rejected_donation_details = mysqli_real_escape_string($conn, $_POST['rejected_donation_details'] ?? '');
        $hepatitis_history = mysqli_real_escape_string($conn, $_POST['hepatitis_history'] ?? 'no');
        $hepatitis_history_details = mysqli_real_escape_string($conn, $_POST['hepatitis_history_details'] ?? '');
        
        $about = mysqli_real_escape_string($conn, $_POST['about'] ?? '');
    
        // Server-side validation
        $errors = [];
        if (empty($full_name) || empty($email) || empty($contact_number) || empty($cnic) || empty($blood_type) || empty($gender) || empty($age)) {
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
        
        // Blood Test Report Upload (Optional)
        $blood_test_report = NULL;
        if (isset($_FILES['blood_test_report']) && $_FILES['blood_test_report']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['blood_test_report'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
            
            if (in_array($file_ext, $allowed_extensions) && $file['size'] <= $max_size) {
                $target_dir = 'assets/uploads/blood_reports/';
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $new_filename = "blood_test_" . $userId . "_" . uniqid() . "." . $file_ext;
                $blood_test_report = $target_dir . $new_filename;
                if (!move_uploaded_file($file['tmp_name'], $blood_test_report)) {
                    $errors[] = "Failed to upload blood test report.";
                }
            } else {
                $errors[] = "Invalid file type or size for blood test report (JPG/PNG/GIF/PDF/DOC/DOCX, max 5MB).";
            }
        }
        
        // Medical Reports Upload (Optional)
        $medical_reports = NULL;
        if (isset($_FILES['medical_reports']) && $_FILES['medical_reports']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['medical_reports'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
            
            if (in_array($file_ext, $allowed_extensions) && $file['size'] <= $max_size) {
                $target_dir = 'assets/uploads/medical_reports/';
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $new_filename = "medical_report_" . $userId . "_" . uniqid() . "." . $file_ext;
                $medical_reports = $target_dir . $new_filename;
                if (!move_uploaded_file($file['tmp_name'], $medical_reports)) {
                    $errors[] = "Failed to upload medical reports.";
                }
            } else {
                $errors[] = "Invalid file type or size for medical reports (JPG/PNG/GIF/PDF/DOC/DOCX, max 5MB).";
            }
        }
    
        if (empty($errors)) {
            // Insert into donors table
            // Check if new columns exist, if not use old structure
            $check_columns = $conn->query("SHOW COLUMNS FROM donors LIKE 'full_name'");
            $has_new_columns = $check_columns && $check_columns->num_rows > 0;
            
            if ($has_new_columns) {
                $insert_query = "INSERT INTO donors (user_id, full_name, first_name, last_name, father_name, age, gender, email, contact_number, whatsapp_number, emergency_contacts, cnic, occupation, full_address, location, blood_type, contact_method, emergency_availability, last_donation_date, chronic_diseases, chronic_diseases_details, rejected_donation, rejected_donation_details, hepatitis_history, hepatitis_history_details, blood_test_report, medical_reports, about, profile_pic) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("sssssisssssssssssssssssssssss", $userId, $full_name, $first_name, $last_name, $father_name, $age, $gender, $email, $contact_number, $whatsapp_number, $emergency_contacts, $cnic, $occupation, $full_address, $location, $blood_type, $contact_method, $emergency_availability, $last_donation_date, $chronic_diseases, $chronic_diseases_details, $rejected_donation, $rejected_donation_details, $hepatitis_history, $hepatitis_history_details, $blood_test_report, $medical_reports, $about, $profile_pic);
            } else {
                // Fallback to old structure for backward compatibility
                $insert_query = "INSERT INTO donors (user_id, first_name, last_name, age, gender, email, contact_number, whatsapp_number, cnic, full_address, location, blood_type, contact_method, emergency_contact, health_status, medical_conditions, last_donation_date, availability, about, profile_pic) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                // Combine full_name into first_name and last_name if needed
                if (empty($first_name) && !empty($full_name)) {
                    $name_parts = explode(' ', $full_name, 2);
                    $first_name = $name_parts[0];
                    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                }
                $emergency_contact = $emergency_availability;
                $health_status = 'eligible';
                $medical_conditions = '';
                $availability = '';
                $stmt->bind_param("ssisssssssssssssssss", $userId, $first_name, $last_name, $age, $gender, $email, $contact_number, $whatsapp_number, $cnic, $full_address, $location, $blood_type, $contact_method, $emergency_contact, $health_status, $medical_conditions, $last_donation_date, $availability, $about, $profile_pic);
            }
            
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
    <?php include('assets/includes/link-js.php'); ?>
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
                            <!-- Tutorial Button -->
                            <?php if (!empty($tutorial_video_link) && $tutorial_video_link !== '#'): ?>
                            <div class="text-center mb-4">
                                <a href="<?php echo htmlspecialchars($tutorial_video_link); ?>" target="_blank" class="btn btn-outline-danger btn-lg">
                                    <i class="fas fa-play-circle me-2"></i>Tutorial
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <form method="post" enctype="multipart/form-data" id="donorForm">
                                <!-- Personal Information -->
                                <div class="section-box">
                                    <h4 class="section-title">Personal Information</h4>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name *</label>
                                            <input type="text" name="full_name" id="full_name" class="form-control" 
                                                   value="<?php echo htmlspecialchars(($_SESSION['form_data']['full_name'] ?? '') ?: ($user['first_name'] . ' ' . $user['last_name'])); ?>" required>
                                            <div class="invalid-feedback" id="full_name_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Father Name *</label>
                                            <input type="text" name="father_name" id="father_name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['father_name'] ?? ''); ?>" required>
                                            <div class="invalid-feedback" id="father_name_error"></div>
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
                                        <!-- Hidden fields for backward compatibility -->
                                        <input type="hidden" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                        <input type="hidden" name="last_name" id="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- Contact Details -->
                                <div class="section-box">
                                    <h4 class="section-title">Contact Details</h4>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Contact No *</label>
                                            <input type="text" name="contact_number" id="contact_number" class="form-control" maxlength="12" 
                                                   placeholder="0300-1234567" required value="<?php echo htmlspecialchars($_SESSION['form_data']['contact_number'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="contact_number_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">WhatsApp No *</label>
                                            <input type="text" name="whatsapp_number" id="whatsapp_number" class="form-control" maxlength="12" 
                                                   placeholder="0300-1234567" required value="<?php echo htmlspecialchars($_SESSION['form_data']['whatsapp_number'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="whatsapp_number_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Emergency Contacts *</label>
                                            <input type="text" name="emergency_contacts" id="emergency_contacts" class="form-control" 
                                                   placeholder="Enter emergency contact numbers" required value="<?php echo htmlspecialchars($_SESSION['form_data']['emergency_contacts'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="emergency_contacts_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CNIC *</label>
                                            <input type="text" name="cnic" id="cnic" class="form-control" maxlength="15" 
                                                   placeholder="XXXXX-XXXXXXX-X" required value="<?php echo htmlspecialchars($_SESSION['form_data']['cnic'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="cnic_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Occupation *</label>
                                            <input type="text" name="occupation" id="occupation" class="form-control" 
                                                   placeholder="Enter your occupation" required value="<?php echo htmlspecialchars($_SESSION['form_data']['occupation'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="occupation_error"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email *</label>
                                            <input type="email" name="email" id="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                            <div class="invalid-feedback" id="email_error"></div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Full Address *</label>
                                            <textarea name="full_address" id="full_address" class="form-control" rows="2" required><?php echo htmlspecialchars($_SESSION['form_data']['full_address'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Communication Preferences -->
                                <div class="section-box">
                                    <h4 class="section-title">Communication Preferences</h4>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">App Messages / WhatsApp</label>
                                            <select name="contact_method" id="contact_method" class="form-select">
                                                <option value="app" <?php echo (($_SESSION['form_data']['contact_method'] ?? 'app') === 'app') ? 'selected' : ''; ?>>App Messages</option>
                                                <option value="whatsapp" <?php echo (($_SESSION['form_data']['contact_method'] ?? '') === 'whatsapp') ? 'selected' : ''; ?>>WhatsApp</option>
                                                <option value="both" <?php echo (($_SESSION['form_data']['contact_method'] ?? '') === 'both') ? 'selected' : ''; ?>>Both</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Emergency Availability *</label>
                                            <select name="emergency_availability" id="emergency_availability" class="form-select" required>
                                                <option value="no" <?php echo (($_SESSION['form_data']['emergency_availability'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No</option>
                                                <option value="yes" <?php echo (($_SESSION['form_data']['emergency_availability'] ?? '') === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                            <small class="text-muted">Note: You can donate blood within 06 to 08 Hours.</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Blood Information -->
                                <div class="section-box">
                                    <h4 class="section-title">Blood Information</h4>
                                    <div class="row g-3">
                                        <div class="col-md-4">
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
                                        <div class="col-md-4">
                                            <label class="form-label">Location *</label>
                                            <input type="text" name="location" id="location" class="form-control" 
                                                   placeholder="Enter your location" required value="<?php echo htmlspecialchars($_SESSION['form_data']['location'] ?? ''); ?>">
                                            <div class="invalid-feedback" id="location_error"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Last Donation Date</label>
                                            <input type="date" name="last_donation_date" id="last_donation_date" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['last_donation_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Current Health Status -->
                                <div class="section-box">
                                    <h4 class="section-title">Current Health Status</h4>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Any Chronic Diseases? (Diabetes, heart diseases, etc.) *</label>
                                            <select name="chronic_diseases" id="chronic_diseases" class="form-select" required>
                                                <option value="no" <?php echo (($_SESSION['form_data']['chronic_diseases'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No</option>
                                                <option value="yes" <?php echo (($_SESSION['form_data']['chronic_diseases'] ?? '') === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                        </div>
                                        <div class="col-12" id="chronic_diseases_details_wrapper" style="display: <?php echo (($_SESSION['form_data']['chronic_diseases'] ?? 'no') === 'yes') ? 'block' : 'none'; ?>;">
                                            <label class="form-label">Please provide details:</label>
                                            <textarea name="chronic_diseases_details" id="chronic_diseases_details" class="form-control" rows="3" 
                                                      placeholder="Enter details about chronic diseases"><?php echo htmlspecialchars($_SESSION['form_data']['chronic_diseases_details'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Have you ever been rejected for Blood Donation? *</label>
                                            <select name="rejected_donation" id="rejected_donation" class="form-select" required>
                                                <option value="no" <?php echo (($_SESSION['form_data']['rejected_donation'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No</option>
                                                <option value="yes" <?php echo (($_SESSION['form_data']['rejected_donation'] ?? '') === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                        </div>
                                        <div class="col-12" id="rejected_donation_details_wrapper" style="display: <?php echo (($_SESSION['form_data']['rejected_donation'] ?? 'no') === 'yes') ? 'block' : 'none'; ?>;">
                                            <label class="form-label">Please provide details:</label>
                                            <textarea name="rejected_donation_details" id="rejected_donation_details" class="form-control" rows="3" 
                                                      placeholder="Enter details about rejection"><?php echo htmlspecialchars($_SESSION['form_data']['rejected_donation_details'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Any History of Hepatitis B/C, HIV, Malaria or STD? *</label>
                                            <select name="hepatitis_history" id="hepatitis_history" class="form-select" required>
                                                <option value="no" <?php echo (($_SESSION['form_data']['hepatitis_history'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No</option>
                                                <option value="yes" <?php echo (($_SESSION['form_data']['hepatitis_history'] ?? '') === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                        </div>
                                        <div class="col-12" id="hepatitis_history_details_wrapper" style="display: <?php echo (($_SESSION['form_data']['hepatitis_history'] ?? 'no') === 'yes') ? 'block' : 'none'; ?>;">
                                            <label class="form-label">Please provide details:</label>
                                            <textarea name="hepatitis_history_details" id="hepatitis_history_details" class="form-control" rows="3" 
                                                      placeholder="Enter details about medical history"><?php echo htmlspecialchars($_SESSION['form_data']['hepatitis_history_details'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- File Uploads -->
                                <div class="section-box">
                                    <h4 class="section-title">Medical Reports (Optional)</h4>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Upload Your Blood Test Report</label>
                                            <div class="upload-btn-wrapper">
                                                <button type="button" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt me-2"></i>Choose File (JPG, DOC, PDF, etc.)
                                                </button>
                                                <input type="file" name="blood_test_report" id="blood_test_report" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                                            </div>
                                            <small class="text-muted">Max file size: 5MB</small>
                                            <div id="blood_test_report_name" class="mt-2 text-muted"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Upload Your Recent Medical Reports</label>
                                            <div class="upload-btn-wrapper">
                                                <button type="button" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt me-2"></i>Choose File (JPG, DOC, PDF, etc.)
                                                </button>
                                                <input type="file" name="medical_reports" id="medical_reports" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                                            </div>
                                            <small class="text-muted">Max file size: 5MB</small>
                                            <div id="medical_reports_name" class="mt-2 text-muted"></div>
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
                                                <button type="button" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt me-2"></i>Choose Profile Picture
                                                </button>
                                                <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                                            </div>
                                            <div class="invalid-feedback text-center" id="profile_pic_error"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tell Us About Yourself -->
                                <div class="section-box">
                                    <h4 class="section-title">Tell Us About Yourself</h4>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Add Details</label>
                                            <textarea name="about" id="about" class="form-control" rows="4" 
                                                      placeholder="Tell us about yourself"><?php echo htmlspecialchars($_SESSION['form_data']['about'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Important Note -->
                                <div class="alert alert-info mt-4">
                                    <strong><i class="fas fa-info-circle me-2"></i>Important Note:</strong> Please provide all the information correctly and accurately. We will try our best to Connect with Needy. In case of any misuses of this service, your request can be canceled and blocked permanently.
                                </div>
                                
                                <!-- Terms and Conditions -->
                                <div class="form-check mt-4 mb-3">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I'm agree with terms and conditions and Privacy Policy.
                                    </label>
                                    <div class="invalid-feedback">You must agree to the terms</div>
                                </div>
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="info_correct" required>
                                    <label class="form-check-label" for="info_correct">
                                        I'm agree all the information is correct.
                                    </label>
                                    <div class="invalid-feedback">You must confirm that all information is correct</div>
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
            
            // Conditional fields for health status
            document.getElementById('chronic_diseases').addEventListener('change', function() {
                const detailsWrapper = document.getElementById('chronic_diseases_details_wrapper');
                if (this.value === 'yes') {
                    detailsWrapper.style.display = 'block';
                    document.getElementById('chronic_diseases_details').required = true;
                } else {
                    detailsWrapper.style.display = 'none';
                    document.getElementById('chronic_diseases_details').required = false;
                    document.getElementById('chronic_diseases_details').value = '';
                }
            });
            
            document.getElementById('rejected_donation').addEventListener('change', function() {
                const detailsWrapper = document.getElementById('rejected_donation_details_wrapper');
                if (this.value === 'yes') {
                    detailsWrapper.style.display = 'block';
                    document.getElementById('rejected_donation_details').required = true;
                } else {
                    detailsWrapper.style.display = 'none';
                    document.getElementById('rejected_donation_details').required = false;
                    document.getElementById('rejected_donation_details').value = '';
                }
            });
            
            document.getElementById('hepatitis_history').addEventListener('change', function() {
                const detailsWrapper = document.getElementById('hepatitis_history_details_wrapper');
                if (this.value === 'yes') {
                    detailsWrapper.style.display = 'block';
                    document.getElementById('hepatitis_history_details').required = true;
                } else {
                    detailsWrapper.style.display = 'none';
                    document.getElementById('hepatitis_history_details').required = false;
                    document.getElementById('hepatitis_history_details').value = '';
                }
            });
            
            // File upload name display
            document.getElementById('blood_test_report').addEventListener('change', function(e) {
                const file = e.target.files[0];
                const nameDiv = document.getElementById('blood_test_report_name');
                if (file) {
                    nameDiv.textContent = 'Selected: ' + file.name;
                    nameDiv.classList.remove('text-muted');
                    nameDiv.classList.add('text-success');
                } else {
                    nameDiv.textContent = '';
                }
            });
            
            document.getElementById('medical_reports').addEventListener('change', function(e) {
                const file = e.target.files[0];
                const nameDiv = document.getElementById('medical_reports_name');
                if (file) {
                    nameDiv.textContent = 'Selected: ' + file.name;
                    nameDiv.classList.remove('text-muted');
                    nameDiv.classList.add('text-success');
                } else {
                    nameDiv.textContent = '';
                }
            });
            
            // Split full name for backward compatibility
            document.getElementById('full_name').addEventListener('blur', function() {
                const fullName = this.value.trim();
                if (fullName) {
                    const nameParts = fullName.split(' ', 2);
                    document.getElementById('first_name').value = nameParts[0] || '';
                    document.getElementById('last_name').value = nameParts[1] || '';
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
                    { id: 'full_name', label: 'Full Name' },
                    { id: 'father_name', label: 'Father Name' },
                    { id: 'email', label: 'Email' },
                    { id: 'contact_number', label: 'Contact Number' },
                    { id: 'whatsapp_number', label: 'WhatsApp Number' },
                    { id: 'emergency_contacts', label: 'Emergency Contacts' },
                    { id: 'cnic', label: 'CNIC' },
                    { id: 'occupation', label: 'Occupation' },
                    { id: 'full_address', label: 'Full Address' },
                    { id: 'location', label: 'Location' },
                    { id: 'blood_type', label: 'Blood Type' },
                    { id: 'gender', label: 'Gender' },
                    { id: 'age', label: 'Age' },
                    { id: 'emergency_availability', label: 'Emergency Availability' },
                    { id: 'chronic_diseases', label: 'Chronic Diseases' },
                    { id: 'rejected_donation', label: 'Rejected Donation' },
                    { id: 'hepatitis_history', label: 'Hepatitis History' }
                ];
                
                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (!element || !element.value || !element.value.trim()) {
                        if (element) element.classList.add('is-invalid');
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
                
                // Validate file uploads (optional but validate format if provided)
                const bloodTestReport = document.getElementById('blood_test_report');
                if (bloodTestReport.files && bloodTestReport.files.length > 0) {
                    const file = bloodTestReport.files[0];
                    const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
                    const fileExt = file.name.split('.').pop().toLowerCase();
                    if (!allowedExtensions.includes(fileExt)) {
                        errorMessages.push('Blood test report: Invalid file type. Allowed: JPG, PNG, GIF, PDF, DOC, DOCX');
                        isValid = false;
                    } else if (file.size > 5 * 1024 * 1024) {
                        errorMessages.push('Blood test report: File size must be less than 5MB');
                        isValid = false;
                    }
                }
                
                const medicalReports = document.getElementById('medical_reports');
                if (medicalReports.files && medicalReports.files.length > 0) {
                    const file = medicalReports.files[0];
                    const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
                    const fileExt = file.name.split('.').pop().toLowerCase();
                    if (!allowedExtensions.includes(fileExt)) {
                        errorMessages.push('Medical reports: Invalid file type. Allowed: JPG, PNG, GIF, PDF, DOC, DOCX');
                        isValid = false;
                    } else if (file.size > 5 * 1024 * 1024) {
                        errorMessages.push('Medical reports: File size must be less than 5MB');
                        isValid = false;
                    }
                }
                
                // Validate conditional required fields
                const chronicDiseases = document.getElementById('chronic_diseases').value;
                if (chronicDiseases === 'yes') {
                    const details = document.getElementById('chronic_diseases_details');
                    if (!details.value.trim()) {
                        details.classList.add('is-invalid');
                        errorMessages.push('Please provide details about chronic diseases');
                        isValid = false;
                    }
                }
                
                const rejectedDonation = document.getElementById('rejected_donation').value;
                if (rejectedDonation === 'yes') {
                    const details = document.getElementById('rejected_donation_details');
                    if (!details.value.trim()) {
                        details.classList.add('is-invalid');
                        errorMessages.push('Please provide details about rejection');
                        isValid = false;
                    }
                }
                
                const hepatitisHistory = document.getElementById('hepatitis_history').value;
                if (hepatitisHistory === 'yes') {
                    const details = document.getElementById('hepatitis_history_details');
                    if (!details.value.trim()) {
                        details.classList.add('is-invalid');
                        errorMessages.push('Please provide details about medical history');
                        isValid = false;
                    }
                }
                
                // Validate terms checkboxes
                if (!document.getElementById('terms').checked) {
                    document.getElementById('terms').classList.add('is-invalid');
                    document.querySelector('#terms + .invalid-feedback').style.display = 'block';
                    errorMessages.push('You must agree to the terms and conditions');
                    isValid = false;
                }
                
                if (!document.getElementById('info_correct').checked) {
                    document.getElementById('info_correct').classList.add('is-invalid');
                    document.querySelector('#info_correct + .invalid-feedback').style.display = 'block';
                    errorMessages.push('You must confirm that all information is correct');
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