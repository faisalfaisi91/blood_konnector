<?php
session_start();
include('assets/lib/openconn.php');
require_once('assets/lib/ProfileManager.php');

// =============== 1. INITIALIZE PROFILE MANAGER ===============
$profileManager = new ProfileManager($conn);

// =============== 2. REQUIRE LOGIN & DONOR ROLE ===============
$profileManager->requireRole('donor', 'profile');

// =============== 3. UPDATE LAST ACTIVITY ===============
$profileManager->updateLastActivity();

// =============== 4. FETCH DONOR DATA ===============
$userId = $_SESSION['user_id'];
$query = "SELECT * FROM donors WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Donor profile not found!";
    header("Location: donors");
    exit();
}

$donor = $result->fetch_assoc();
$stmt->close();

// Handle form submission
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $father_name = mysqli_real_escape_string($conn, $_POST['father_name'] ?? '');
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);
    $whatsapp_number = filter_input(INPUT_POST, 'whatsapp_number', FILTER_SANITIZE_STRING);
    $emergency_contacts = mysqli_real_escape_string($conn, $_POST['emergency_contacts'] ?? '');
    $cnic = filter_input(INPUT_POST, 'cnic', FILTER_SANITIZE_STRING);
    $occupation = mysqli_real_escape_string($conn, $_POST['occupation'] ?? '');
    $full_address = filter_input(INPUT_POST, 'full_address', FILTER_SANITIZE_STRING);
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $blood_type = filter_input(INPUT_POST, 'blood_type', FILTER_SANITIZE_STRING);
    $contact_method = filter_input(INPUT_POST, 'contact_method', FILTER_SANITIZE_STRING);
    $emergency_availability = mysqli_real_escape_string($conn, $_POST['emergency_availability'] ?? 'no');
    $last_donation_date = !empty($_POST['last_donation_date']) ? date('Y-m-d', strtotime($_POST['last_donation_date'])) : NULL;
    
    // Health Status Fields
    $chronic_diseases = mysqli_real_escape_string($conn, $_POST['chronic_diseases'] ?? 'no');
    $chronic_diseases_details = mysqli_real_escape_string($conn, $_POST['chronic_diseases_details'] ?? '');
    $rejected_donation = mysqli_real_escape_string($conn, $_POST['rejected_donation'] ?? 'no');
    $rejected_donation_details = mysqli_real_escape_string($conn, $_POST['rejected_donation_details'] ?? '');
    $hepatitis_history = mysqli_real_escape_string($conn, $_POST['hepatitis_history'] ?? 'no');
    $hepatitis_history_details = mysqli_real_escape_string($conn, $_POST['hepatitis_history_details'] ?? '');
    
    $about = filter_input(INPUT_POST, 'about', FILTER_SANITIZE_STRING);

    // Validate required fields
    if (empty($full_name) || empty($email) || empty($contact_number) || empty($cnic) || empty($blood_type) || empty($gender) || empty($age)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!in_array($gender, ['male', 'female', 'custom'])) {
        $error = "Invalid gender selected.";
    } elseif (!in_array($blood_type, ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])) {
        $error = "Invalid blood type selected.";
    } elseif (!in_array($contact_method, ['app', 'whatsapp', 'both'])) {
        $error = "Invalid contact method selected.";
    } elseif (!in_array($emergency_availability, ['yes', 'no'])) {
        $error = "Invalid emergency availability option selected.";
    } else {
        // Handle profile picture upload
        $profile_pic = $donor['profile_pic'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "assets/images/profiles/";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file = $_FILES['profile_pic'];

            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = "donor_" . $userId . "_" . uniqid() . "." . $file_ext;
                $targetPath = $targetDir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $profile_pic = $targetPath;
                } else {
                    $error = "Failed to upload profile picture.";
                }
            } else {
                $error = "Invalid file type or size (max 2MB allowed).";
            }
        }
        
        // Handle blood test report upload (optional)
        $blood_test_report = $donor['blood_test_report'] ?? NULL;
        if (isset($_FILES['blood_test_report']) && $_FILES['blood_test_report']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['blood_test_report'];
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_extensions) && $file['size'] <= $max_size) {
                $target_dir = 'assets/uploads/blood_reports/';
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $new_filename = "blood_test_" . $userId . "_" . uniqid() . "." . $file_ext;
                $targetPath = $target_dir . $new_filename;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $blood_test_report = $targetPath;
                } else {
                    $error = "Failed to upload blood test report.";
                }
            } else {
                $error = "Invalid file type or size for blood test report (JPG/PNG/GIF/PDF/DOC/DOCX, max 5MB).";
            }
        }
        
        // Handle medical reports upload (optional)
        $medical_reports = $donor['medical_reports'] ?? NULL;
        if (isset($_FILES['medical_reports']) && $_FILES['medical_reports']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['medical_reports'];
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_extensions) && $file['size'] <= $max_size) {
                $target_dir = 'assets/uploads/medical_reports/';
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $new_filename = "medical_report_" . $userId . "_" . uniqid() . "." . $file_ext;
                $targetPath = $target_dir . $new_filename;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $medical_reports = $targetPath;
                } else {
                    $error = "Failed to upload medical reports.";
                }
            } else {
                $error = "Invalid file type or size for medical reports (JPG/PNG/GIF/PDF/DOC/DOCX, max 5MB).";
            }
        }

        // Update database if no error
        if (empty($error)) {
            // Check if new columns exist
            $check_columns = $conn->query("SHOW COLUMNS FROM donors LIKE 'full_name'");
            $has_new_columns = $check_columns && $check_columns->num_rows > 0;
            
            if ($has_new_columns) {
                $update_query = "UPDATE donors SET 
                    full_name = ?, first_name = ?, last_name = ?, father_name = ?, age = ?, gender = ?, email = ?, 
                    contact_number = ?, whatsapp_number = ?, emergency_contacts = ?, cnic = ?, occupation = ?, 
                    full_address = ?, location = ?, blood_type = ?, contact_method = ?, emergency_availability = ?, 
                    last_donation_date = ?, chronic_diseases = ?, chronic_diseases_details = ?, 
                    rejected_donation = ?, rejected_donation_details = ?, hepatitis_history = ?, 
                    hepatitis_history_details = ?, blood_test_report = ?, medical_reports = ?, 
                    about = ?, profile_pic = ? 
                    WHERE user_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param(
                    "ssssisssssssssssssssssssssssss",
                    $full_name, $first_name, $last_name, $father_name, $age, $gender, $email,
                    $contact_number, $whatsapp_number, $emergency_contacts, $cnic, $occupation,
                    $full_address, $location, $blood_type, $contact_method, $emergency_availability,
                    $last_donation_date, $chronic_diseases, $chronic_diseases_details,
                    $rejected_donation, $rejected_donation_details, $hepatitis_history,
                    $hepatitis_history_details, $blood_test_report, $medical_reports,
                    $about, $profile_pic, $userId
                );
            } else {
                // Fallback to old structure for backward compatibility
                $update_query = "UPDATE donors SET 
                    first_name = ?, last_name = ?, age = ?, gender = ?, email = ?, 
                    contact_number = ?, whatsapp_number = ?, cnic = ?, full_address = ?, 
                    location = ?, blood_type = ?, contact_method = ?, emergency_contact = ?, 
                    health_status = ?, medical_conditions = ?, last_donation_date = ?, 
                    availability = ?, about = ?, profile_pic = ? 
                    WHERE user_id = ?";
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
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param(
                    "ssisssssssssssssssss",
                    $first_name, $last_name, $age, $gender, $email,
                    $contact_number, $whatsapp_number, $cnic, $full_address,
                    $location, $blood_type, $contact_method, $emergency_contact,
                    $health_status, $medical_conditions, $last_donation_date,
                    $availability, $about, $profile_pic, $userId
                );
            }
        
            if ($stmt->execute()) {
                // Update users table for first_name, last_name, email, and profile_pic
                $update_users_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_pic = ? WHERE user_id = ?";
                $users_stmt = $conn->prepare($update_users_query);
                $users_stmt->bind_param("sssss", $first_name, $last_name, $email, $profile_pic, $userId);
                $users_stmt->execute();
                $users_stmt->close();
        
                $success = "Profile updated successfully!";
                // Clear success message after setting it
                $_SESSION['success'] = $success;
                header("Location: edit-donor-profile?updated=1");
                exit();
            } else {
                $error = "Failed to update profile.";
            }
            $stmt->close();
        }
    }
}

// Check for success message in session and clear it
$display_success = '';
if (isset($_GET['updated']) && $_GET['updated'] == '1' && isset($_SESSION['success'])) {
    $display_success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <!-- Adding SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .edit-profile-container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            padding: 30px;
        }
        .edit-profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .edit-profile-header h2 {
            font-size: 2rem;
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            font-weight: 600;
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        .action-buttons button {
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: #3498db;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .action-buttons button:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        .cancel-button {
            background: #e74c3c;
        }
        .cancel-button:hover {
            background: #c82333;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 768px) {
            .edit-profile-container {
                margin: 20px;
                padding: 20px;
            }
            .action-buttons {
                flex-direction: column;
            }
            .action-buttons button {
                width: 100%;
                text-align: center;
            }
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

    <!-- Edit Donor Profile Section -->
    <div class="breadcrumb_section overflow-hidden ptb-150">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="edit-profile-container">
                        <div class="edit-profile-header">
                            <h2>Edit Donor Profile</h2>
                        </div>

                        <!-- Edit Profile Form -->
                        <form method="POST" enctype="multipart/form-data" id="donorEditForm">
                            <h3 style="color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #3498db;">Personal Information</h3>
                            
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($donor['full_name'] ?? ($donor['first_name'] . ' ' . $donor['last_name'])); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="father_name">Father Name *</label>
                                <input type="text" name="father_name" id="father_name" value="<?php echo htmlspecialchars($donor['father_name'] ?? ''); ?>" required>
                            </div>
                            <!-- Hidden fields for backward compatibility -->
                            <input type="hidden" name="first_name" id="first_name" value="<?php echo htmlspecialchars($donor['first_name']); ?>">
                            <input type="hidden" name="last_name" id="last_name" value="<?php echo htmlspecialchars($donor['last_name']); ?>">
                            <div class="form-group">
                                <label for="age">Age</label>
                                <input type="number" name="age" id="age" value="<?php echo htmlspecialchars($donor['age']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender *</label>
                                <select name="gender" id="gender" required>
                                    <option value="male" <?php echo $donor['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $donor['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="custom" <?php echo $donor['gender'] === 'custom' ? 'selected' : ''; ?>>Custom</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($donor['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_number">Contact Number *</label>
                                <input type="text" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($donor['contact_number']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="whatsapp_number">WhatsApp Number *</label>
                                <input type="text" name="whatsapp_number" id="whatsapp_number" value="<?php echo htmlspecialchars($donor['whatsapp_number']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="emergency_contacts">Emergency Contacts *</label>
                                <input type="text" name="emergency_contacts" id="emergency_contacts" value="<?php echo htmlspecialchars($donor['emergency_contacts'] ?? ''); ?>" placeholder="Enter emergency contact numbers" required>
                            </div>
                            <div class="form-group">
                                <label for="cnic">CNIC *</label>
                                <input type="text" name="cnic" id="cnic" value="<?php echo htmlspecialchars($donor['cnic']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="occupation">Occupation *</label>
                                <input type="text" name="occupation" id="occupation" value="<?php echo htmlspecialchars($donor['occupation'] ?? ''); ?>" placeholder="Enter your occupation" required>
                            </div>
                            <div class="form-group">
                                <label for="full_address">Full Address</label>
                                <textarea name="full_address" id="full_address"><?php echo htmlspecialchars($donor['full_address']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="location">Location *</label>
                                <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($donor['location']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="blood_type">Blood Type *</label>
                                <select name="blood_type" id="blood_type" required>
                                    <option value="A+" <?php echo $donor['blood_type'] === 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo $donor['blood_type'] === 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo $donor['blood_type'] === 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo $donor['blood_type'] === 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="O+" <?php echo $donor['blood_type'] === 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo $donor['blood_type'] === 'O-' ? 'selected' : ''; ?>>O-</option>
                                    <option value="AB+" <?php echo $donor['blood_type'] === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo $donor['blood_type'] === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                </select>
                            </div>
                            <h3 style="color: #2c3e50; margin: 30px 0 20px 0; padding-top: 20px; padding-bottom: 10px; border-top: 2px solid #e0e0e0; border-bottom: 2px solid #3498db;">Communication Preferences</h3>
                            
                            <div class="form-group">
                                <label for="contact_method">App Messages / WhatsApp</label>
                                <select name="contact_method" id="contact_method">
                                    <option value="app" <?php echo ($donor['contact_method'] ?? 'app') === 'app' ? 'selected' : ''; ?>>App Messages</option>
                                    <option value="whatsapp" <?php echo ($donor['contact_method'] ?? '') === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                                    <option value="both" <?php echo ($donor['contact_method'] ?? '') === 'both' ? 'selected' : ''; ?>>Both</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="emergency_availability">Emergency Availability *</label>
                                <select name="emergency_availability" id="emergency_availability" required>
                                    <option value="no" <?php echo ($donor['emergency_availability'] ?? $donor['emergency_contact'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
                                    <option value="yes" <?php echo ($donor['emergency_availability'] ?? $donor['emergency_contact'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                </select>
                                <small style="color: #666; display: block; margin-top: 5px;">Note: You can donate blood within 06 to 08 Hours.</small>
                            </div>
                            
                            <h3 style="color: #2c3e50; margin: 30px 0 20px 0; padding-top: 20px; padding-bottom: 10px; border-top: 2px solid #e0e0e0; border-bottom: 2px solid #3498db;">Current Health Status</h3>
                            
                            <div class="form-group">
                                <label for="chronic_diseases">Any Chronic Diseases? (Diabetes, heart diseases, etc.) *</label>
                                <select name="chronic_diseases" id="chronic_diseases" required>
                                    <option value="no" <?php echo ($donor['chronic_diseases'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
                                    <option value="yes" <?php echo ($donor['chronic_diseases'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                </select>
                            </div>
                            <div class="form-group" id="chronic_diseases_details_wrapper" style="display: <?php echo ($donor['chronic_diseases'] ?? 'no') === 'yes' ? 'block' : 'none'; ?>;">
                                <label for="chronic_diseases_details">Please provide details:</label>
                                <textarea name="chronic_diseases_details" id="chronic_diseases_details" rows="3"><?php echo htmlspecialchars($donor['chronic_diseases_details'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="rejected_donation">Have you ever been rejected for Blood Donation? *</label>
                                <select name="rejected_donation" id="rejected_donation" required>
                                    <option value="no" <?php echo ($donor['rejected_donation'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
                                    <option value="yes" <?php echo ($donor['rejected_donation'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                </select>
                            </div>
                            <div class="form-group" id="rejected_donation_details_wrapper" style="display: <?php echo ($donor['rejected_donation'] ?? 'no') === 'yes' ? 'block' : 'none'; ?>;">
                                <label for="rejected_donation_details">Please provide details:</label>
                                <textarea name="rejected_donation_details" id="rejected_donation_details" rows="3"><?php echo htmlspecialchars($donor['rejected_donation_details'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="hepatitis_history">Any History of Hepatitis B/C, HIV, Malaria or STD? *</label>
                                <select name="hepatitis_history" id="hepatitis_history" required>
                                    <option value="no" <?php echo ($donor['hepatitis_history'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
                                    <option value="yes" <?php echo ($donor['hepatitis_history'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                </select>
                            </div>
                            <div class="form-group" id="hepatitis_history_details_wrapper" style="display: <?php echo ($donor['hepatitis_history'] ?? 'no') === 'yes' ? 'block' : 'none'; ?>;">
                                <label for="hepatitis_history_details">Please provide details:</label>
                                <textarea name="hepatitis_history_details" id="hepatitis_history_details" rows="3"><?php echo htmlspecialchars($donor['hepatitis_history_details'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="last_donation_date">Last Donation Date</label>
                                <input type="date" name="last_donation_date" id="last_donation_date" value="<?php echo htmlspecialchars($donor['last_donation_date']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="availability">Availability</label>
                                <textarea name="availability" id="availability"><?php echo htmlspecialchars($donor['availability']); ?></textarea>
                            </div>
                            <h3 style="color: #2c3e50; margin: 30px 0 20px 0; padding-top: 20px; padding-bottom: 10px; border-top: 2px solid #e0e0e0; border-bottom: 2px solid #3498db;">Tell Us About Yourself</h3>
                            
                            <div class="form-group">
                                <label for="about">Add Details</label>
                                <textarea name="about" id="about" rows="4" placeholder="Tell us about yourself"><?php echo htmlspecialchars($donor['about'] ?? ''); ?></textarea>
                            </div>
                            <h3 style="color: #2c3e50; margin: 30px 0 20px 0; padding-top: 20px; padding-bottom: 10px; border-top: 2px solid #e0e0e0; border-bottom: 2px solid #3498db;">Medical Reports (Optional)</h3>
                            
                            <div class="form-group">
                                <label for="blood_test_report">Upload Your Blood Test Report</label>
                                <input type="file" name="blood_test_report" id="blood_test_report" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                                <small style="color: #666; display: block; margin-top: 5px;">Max file size: 5MB (JPG, PNG, GIF, PDF, DOC, DOCX)</small>
                                <?php if (!empty($donor['blood_test_report'])): ?>
                                    <p style="margin-top: 5px;"><a href="<?php echo htmlspecialchars($donor['blood_test_report']); ?>" target="_blank">View current file</a></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="medical_reports">Upload Your Recent Medical Reports</label>
                                <input type="file" name="medical_reports" id="medical_reports" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                                <small style="color: #666; display: block; margin-top: 5px;">Max file size: 5MB (JPG, PNG, GIF, PDF, DOC, DOCX)</small>
                                <?php if (!empty($donor['medical_reports'])): ?>
                                    <p style="margin-top: 5px;"><a href="<?php echo htmlspecialchars($donor['medical_reports']); ?>" target="_blank">View current file</a></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="profile_pic">Profile Picture</label>
                                <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                                <?php if (!empty($donor['profile_pic'])): ?>
                                    <p style="margin-top: 5px;"><img src="<?php echo htmlspecialchars($donor['profile_pic']); ?>" alt="Current Profile" style="max-width: 150px; border-radius: 8px; margin-top: 10px;"></p>
                                <?php endif; ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <button type="submit">Save Changes</button>
                                <button type="button" class="cancel-button" onclick="window.location.href='donor-profile.php'">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 Script -->
    <?php if (!empty($error)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?php echo addslashes($error); ?>',
                confirmButtonColor: '#e74c3c'
            });
        </script>
    <?php endif; ?>
    <?php if (!empty($display_success)): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?php echo addslashes($display_success); ?>',
                confirmButtonColor: '#3498db'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'edit-donor-profile.php';
                }
            });
        </script>
    <?php endif; ?>

    <!-- Footer -->
    <?php include('assets/includes/footer.php'); ?>

    <!-- Javascript Files -->
    <?php include('assets/includes/link-js.php'); ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Split full name for backward compatibility
            document.getElementById('full_name').addEventListener('blur', function() {
                const fullName = this.value.trim();
                if (fullName) {
                    const nameParts = fullName.split(' ', 2);
                    document.getElementById('first_name').value = nameParts[0] || '';
                    document.getElementById('last_name').value = nameParts[1] || '';
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
                }
            });
            
            // File upload name display
            document.getElementById('blood_test_report').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    console.log('Blood test report selected:', file.name);
                }
            });
            
            document.getElementById('medical_reports').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    console.log('Medical reports selected:', file.name);
                }
            });
        });
    </script>
</body>
</html>
</body>
</html>