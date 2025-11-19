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
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);
    $whatsapp_number = filter_input(INPUT_POST, 'whatsapp_number', FILTER_SANITIZE_STRING);
    $cnic = filter_input(INPUT_POST, 'cnic', FILTER_SANITIZE_STRING);
    $full_address = filter_input(INPUT_POST, 'full_address', FILTER_SANITIZE_STRING);
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $blood_type = filter_input(INPUT_POST, 'blood_type', FILTER_SANITIZE_STRING);
    $contact_method = filter_input(INPUT_POST, 'contact_method', FILTER_SANITIZE_STRING);
    $emergency_contact = filter_input(INPUT_POST, 'emergency_contact', FILTER_SANITIZE_STRING);
    $health_status = filter_input(INPUT_POST, 'health_status', FILTER_SANITIZE_STRING);
    $medical_conditions = filter_input(INPUT_POST, 'medical_conditions', FILTER_SANITIZE_STRING);
    $last_donation_date = filter_input(INPUT_POST, 'last_donation_date', FILTER_SANITIZE_STRING);
    $availability = filter_input(INPUT_POST, 'availability', FILTER_SANITIZE_STRING);
    $about = filter_input(INPUT_POST, 'about', FILTER_SANITIZE_STRING);

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($contact_number) || empty($cnic) || empty($blood_type)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!in_array($gender, ['male', 'female', 'custom'])) {
        $error = "Invalid gender selected.";
    } elseif (!in_array($blood_type, ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])) {
        $error = "Invalid blood type selected.";
    } elseif (!in_array($contact_method, ['app', 'sms', 'email'])) {
        $error = "Invalid contact method selected.";
    } elseif (!in_array($emergency_contact, ['yes', 'no'])) {
        $error = "Invalid emergency contact option selected.";
    } elseif (!in_array($health_status, ['eligible', 'not_eligible'])) {
        $error = "Invalid health status selected.";
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

        // Update database if no error
        if (empty($error)) {
            $update_query = "UPDATE donors SET 
                first_name = ?, last_name = ?, age = ?, gender = ?, email = ?, 
                contact_number = ?, whatsapp_number = ?, cnic = ?, full_address = ?, 
                location = ?, blood_type = ?, contact_method = ?, emergency_contact = ?, 
                health_status = ?, medical_conditions = ?, last_donation_date = ?, 
                availability = ?, about = ?, profile_pic = ? 
                WHERE user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param(
                "ssisssssssssssssssss",
                $first_name, $last_name, $age, $gender, $email,
                $contact_number, $whatsapp_number, $cnic, $full_address,
                $location, $blood_type, $contact_method, $emergency_contact,
                $health_status, $medical_conditions, $last_donation_date,
                $availability, $about, $profile_pic, $userId
            );
        
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
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($donor['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($donor['last_name']); ?>" required>
                            </div>
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
                                <label for="whatsapp_number">WhatsApp Number</label>
                                <input type="text" name="whatsapp_number" id="whatsapp_number" value="<?php echo htmlspecialchars($donor['whatsapp_number']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="cnic">CNIC *</label>
                                <input type="text" name="cnic" id="cnic" value="<?php echo htmlspecialchars($donor['cnic']); ?>" required>
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
                            <div class="form-group">
                                <label for="contact_method">Preferred Contact Method</label>
                                <select name="contact_method" id="contact_method">
                                    <option value="app" <?php echo $donor['contact_method'] === 'app' ? 'selected' : ''; ?>>App</option>
                                    <option value="sms" <?php echo $donor['contact_method'] === 'sms' ? 'selected' : ''; ?>>SMS</option>
                                    <option value="email" <?php echo $donor['contact_method'] === 'email' ? 'selected' : ''; ?>>Email</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="emergency_contact">Emergency Contact</label>
                                <select name="emergency_contact" id="emergency_contact">
                                    <option value="yes" <?php echo $donor['emergency_contact'] === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="no" <?php echo $donor['emergency_contact'] === 'no' ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="health_status">Health Status</label>
                                <select name="health_status" id="health_status">
                                    <option value="eligible" <?php echo $donor['health_status'] === 'eligible' ? 'selected' : ''; ?>>Eligible</option>
                                    <option value="not_eligible" <?php echo $donor['health_status'] === 'not_eligible' ? 'selected' : ''; ?>>Not Eligible</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="medical_conditions">Medical Conditions</label>
                                <textarea name="medical_conditions" id="medical_conditions"><?php echo htmlspecialchars($donor['medical_conditions']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="last_donation_date">Last Donation Date</label>
                                <input type="date" name="last_donation_date" id="last_donation_date" value="<?php echo htmlspecialchars($donor['last_donation_date']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="availability">Availability</label>
                                <textarea name="availability" id="availability"><?php echo htmlspecialchars($donor['availability']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="about">About</label>
                                <textarea name="about" id="about"><?php echo htmlspecialchars($donor['about']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="profile_pic">Profile Picture</label>
                                <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
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
</body>
</html>