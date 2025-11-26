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

// Fetch tutorial video link from settings
$tutorial_video_link = '#';
try {
    $settings_query = "SELECT setting_value FROM settings WHERE setting_key = 'recipient_tutorial_video' LIMIT 1";
    $settings_result = $conn->query($settings_query);
    if ($settings_result && $settings_result->num_rows > 0) {
        $setting_row = $settings_result->fetch_assoc();
        $tutorial_video_link = $setting_row['setting_value'] ?? '#';
    }
} catch (Exception $e) {
    $tutorial_video_link = '#';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnRecipient'])) {
    $errors = [];
    $data = [];
    
    // Get recipient type
    $recipient_type = isset($_POST['recipient_type']) ? mysqli_real_escape_string($conn, $_POST['recipient_type']) : 'self';
    
    // Required fields based on recipient type
    $requiredFields = [
        'full_name' => 'Full Name',
        'age' => 'Age',
        'gender' => 'Gender',
        'email' => 'Email',
        'contact_number' => 'Phone Number',
        'whatsapp_number' => 'WhatsApp Number',
        'cnic' => 'CNIC',
        'home_address' => 'Home Address',
        'blood_type' => 'Blood Type',
        'urgency_level' => 'Urgency Level',
        'hospital_name' => 'Hospital/Clinic Name',
        'ward_name' => 'Ward Name',
        'ward_no' => 'Ward Number',
        'bed_no' => 'Bed Number',
        'hospital_phone_no' => 'Hospital Phone Number',
        'doctors_name' => 'Doctor\'s Name',
        'cause_of_blood_requirement' => 'Cause of Blood Requirement',
        'required_quantity' => 'Units Required'
    ];
    
    // If getting for someone else, add relation field
    if ($recipient_type === 'other') {
        $requiredFields['relation_with_patient'] = 'Relation with Patient';
    }
    
    // Validate required fields
    foreach ($requiredFields as $field => $name) {
        if (empty($_POST[$field])) {
            $errors[$field] = "$name is required";
        } else {
            $data[$field] = mysqli_real_escape_string($conn, $_POST[$field]);
        }
    }

    // Validate CNIC format (upgraded algorithm)
    if (!empty($data['cnic']) && !preg_match('/^\d{5}-\d{7}-\d{1}$/', $data['cnic'])) {
        $errors['cnic'] = "Invalid CNIC format (XXXXX-XXXXXXX-X)";
    }

    // Validate phone numbers
    if (!empty($data['contact_number']) && !preg_match('/^03\d{2}-\d{7}$/', $data['contact_number'])) {
        $errors['contact_number'] = "Invalid phone number format (0300-1234567)";
    }
    
    if (!empty($data['whatsapp_number']) && !preg_match('/^03\d{2}-\d{7}$/', $data['whatsapp_number'])) {
        $errors['whatsapp_number'] = "Invalid WhatsApp number format (0300-1234567)";
    }
    
    if (!empty($data['hospital_phone_no']) && !preg_match('/^0\d{2,3}-\d{7}$/', $data['hospital_phone_no'])) {
        $errors['hospital_phone_no'] = "Invalid hospital phone number format";
    }
    
    // Validate email
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }

    // Validate age
    if (!empty($data['age']) && ($data['age'] < 1 || $data['age'] > 120)) {
        $errors['age'] = "Age must be between 1 and 120";
    }
    
    // Validate urgency level
    if (!empty($data['urgency_level']) && !in_array($data['urgency_level'], ['high', 'normal'])) {
        $errors['urgency_level'] = "Invalid urgency level";
    }
    
    // File upload for Dr's Prescription
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $dr_prescription = '';
    
    if (isset($_FILES['dr_prescription']) && $_FILES['dr_prescription']['error'] === UPLOAD_ERR_OK) {
        $fileExtension = strtolower(pathinfo($_FILES['dr_prescription']['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors['dr_prescription'] = "Only JPG, PNG, PDF, DOC, DOCX files allowed";
        }

        if ($_FILES['dr_prescription']['size'] > $maxFileSize) {
            $errors['dr_prescription'] = "File size exceeds 5MB limit";
        }

        $newFilename = "prescription_" . $userId . "_" . uniqid() . "." . $fileExtension;
        $targetDir = "assets/uploads/prescriptions/";

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $destination = $targetDir . $newFilename;

        if (!move_uploaded_file($_FILES['dr_prescription']['tmp_name'], $destination)) {
            $errors['dr_prescription'] = "Failed to upload file";
        } else {
            $dr_prescription = $destination;
        }
    } else {
        $errors['dr_prescription'] = "Doctor's prescription file is required";
    }
    
    // Check terms and conditions
    if (!isset($_POST['terms_accepted']) || $_POST['terms_accepted'] !== '1') {
        $errors['terms_accepted'] = "You must agree to the terms and conditions";
    }
    
    if (!isset($_POST['info_correct']) || $_POST['info_correct'] !== '1') {
        $errors['info_correct'] = "You must confirm that all information is correct";
    }

    // Insert into database
    if (empty($errors)) {
        $emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact'] ?? '');
        $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
        $message = mysqli_real_escape_string($conn, $_POST['message'] ?? '');
        $relation_with_patient = mysqli_real_escape_string($conn, $_POST['relation_with_patient'] ?? '');
        
        // Split full_name into first_name and last_name
        $nameParts = explode(' ', $data['full_name'], 2);
        $first_name = $nameParts[0] ?? '';
        $last_name = $nameParts[1] ?? '';

        $insertQuery = "INSERT INTO recipients (
            user_id, recipient_type, full_name, first_name, last_name, age, gender, 
            email, contact_number, whatsapp_number, emergency_contact, cnic, 
            home_address, location, relation_with_patient,
            blood_type, urgency_level, required_quantity,
            hospital_name, clinic_name, ward_name, ward_no, bed_no, 
            hospital_phone_no, doctors_name, cause_of_blood_requirement,
            dr_prescription, message, terms_accepted, info_correct
        ) VALUES (
            '$userId', '$recipient_type', '{$data['full_name']}', '$first_name', '$last_name', 
            '{$data['age']}', '{$data['gender']}', '{$data['email']}', 
            '{$data['contact_number']}', '{$data['whatsapp_number']}', '$emergency_contact', 
            '{$data['cnic']}', '{$data['home_address']}', '$location', '$relation_with_patient',
            '{$data['blood_type']}', '{$data['urgency_level']}', '{$data['required_quantity']}',
            '{$data['hospital_name']}', '{$data['hospital_name']}', '{$data['ward_name']}', 
            '{$data['ward_no']}', '{$data['bed_no']}', '{$data['hospital_phone_no']}', 
            '{$data['doctors_name']}', '{$data['cause_of_blood_requirement']}',
            '$dr_prescription', '$message', 1, 1
        )";

        if (mysqli_query($conn, $insertQuery)) {
            mysqli_query($conn, "UPDATE users SET is_recipient = 1 WHERE user_id = '$userId'");
            
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
    <?php include('assets/includes/link-css.php'); ?>
      <style>
        .section-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
        }
        .section-title {
            color: #2c3e50;
            font-weight: 600;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        .recipient-type-radio {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }
        .recipient-type-radio label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.75rem 1.5rem;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .recipient-type-radio input[type="radio"]:checked + label {
            border-color: #b5002a;
            background-color: #fff5f7;
        }
        .recipient-type-radio input[type="radio"] {
            display: none;
        }
        .conditional-section {
            display: none;
        }
        .conditional-section.show {
            display: block;
        }
        .tutorial-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .tutorial-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .checkbox-group {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            margin-bottom: 0.5rem;
        }
        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            display: none;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <?php include('assets/includes/preloader.php'); ?>
  <?php include('assets/includes/scroll-to-top.php'); ?>
  <?php include('assets/includes/header.php'); ?>

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

<section class="km__message__box ptb-120">
  <div class="container">
      <div class="row justify-content-center g-5">
                <div class="col-xl-10">
          <div class="km__box__form">
            <h4 class="mb-4">Request Assistance</h4>
            <p class="mb-3">
                            Please provide all the information correctly and accurately. We will try our best to help you efficiently. 
                            In case of any misuses of this service, your request can be canceled and blocked permanently.
                        </p>
                        
                        <?php if ($tutorial_video_link !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($tutorial_video_link); ?>" target="_blank" class="tutorial-btn">
                            <i class="fas fa-video me-2"></i>Watch Tutorial Video
                        </a>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" class="donor__form" id="recipientForm" onsubmit="return validateForm();">
                            
                            <!-- Recipient Type Selection -->
                            <div class="section-box mb-4">
                                <h4 class="section-title mb-4"><i class="fas fa-user-tag me-2"></i>Recipient Type</h4>
                                <div class="recipient-type-radio">
                                    <input type="radio" name="recipient_type" id="recipient_self" value="self" checked onchange="toggleRecipientType()">
                                    <label for="recipient_self">
                                        <i class="fas fa-user"></i> I'm recipient
                                    </label>
                                    <input type="radio" name="recipient_type" id="recipient_other" value="other" onchange="toggleRecipientType()">
                                    <label for="recipient_other">
                                        <i class="fas fa-user-friends"></i> I'm getting for someone Else
                                    </label>
                                </div>
                            </div>
                            
              <!-- Personal Information Section -->
              <div class="section-box mb-4">
                <h4 class="section-title mb-4"><i class="fas fa-user-circle me-2"></i>Personal Information</h4>
                <div class="row g-3">
                  <div class="col-md-6">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" name="full_name" id="full_name" placeholder="Enter Full Name" class="form-control form-control-lg" required />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Age <span class="text-danger">*</span></label>
                                        <input type="number" name="age" id="age" placeholder="Age" class="form-control form-control-lg" min="1" max="120" required />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                                        <select name="gender" id="gender" class="form-select form-select-lg" required>
                                            <option value="">Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                  </div>
                                    <div class="col-md-12 conditional-section" id="relation_section">
                                        <label class="form-label">Relation with Patient <span class="text-danger">*</span></label>
                                        <input type="text" name="relation_with_patient" id="relation_with_patient" placeholder="e.g., Father, Mother, Brother, Sister, etc." class="form-control form-control-lg" />
                  </div>
                </div>
              </div>

                            <!-- Contact Details Section -->
              <div class="section-box mb-4">
                <h4 class="section-title mb-4"><i class="fas fa-address-card me-2"></i>Contact Details</h4>
                <div class="row g-3">
                  <div class="col-md-6">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" id="email" placeholder="Enter Email Address" class="form-control form-control-lg" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required />
                  </div>
                  <div class="col-md-6">
                                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                        <input type="text" name="contact_number" id="contact_number" placeholder="0300-1234567" class="form-control form-control-lg" required />
                  </div>
                                    <div class="col-md-6">
                                        <label class="form-label">WhatsApp Number <span class="text-danger">*</span></label>
                                        <input type="text" name="whatsapp_number" id="whatsapp_number" placeholder="0300-1234567" class="form-control form-control-lg" required />
                  </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Emergency Contact</label>
                                        <input type="text" name="emergency_contact" id="emergency_contact" placeholder="0300-1234567" class="form-control form-control-lg" />
                </div>
                                    <div class="col-md-6">
                                        <label class="form-label">CNIC <span class="text-danger">*</span></label>
                                        <input type="text" name="cnic" id="cnic" placeholder="XXXXX-XXXXXXX-X" class="form-control form-control-lg" required />
              </div>
                  <div class="col-md-6">
                    <label class="form-label">Location (City/Neighborhood)</label>
                                        <input type="text" name="location" id="location" placeholder="Enter Location" class="form-control form-control-lg" />
                  </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Home Address <span class="text-danger">*</span></label>
                                        <textarea name="home_address" id="home_address" placeholder="Enter Complete Home Address" class="form-control form-control-lg" rows="2" required></textarea>
                  </div>
                </div>
              </div>

                            <!-- Patient's Details Section -->
              <div class="section-box mb-4">
                                <h4 class="section-title mb-4"><i class="fas fa-hospital me-2"></i>Patient's Details</h4>
                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Hospital/Clinic Name <span class="text-danger">*</span></label>
                                        <input type="text" name="hospital_name" id="hospital_name" placeholder="Enter Hospital/Clinic Name" class="form-control form-control-lg" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Hospital Phone Number <span class="text-danger">*</span></label>
                                        <input type="text" name="hospital_phone_no" id="hospital_phone_no" placeholder="042-1234567" class="form-control form-control-lg" required />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Ward Name <span class="text-danger">*</span></label>
                                        <input type="text" name="ward_name" id="ward_name" placeholder="Enter Ward Name" class="form-control form-control-lg" required />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Ward Number <span class="text-danger">*</span></label>
                                        <input type="text" name="ward_no" id="ward_no" placeholder="Enter Ward Number" class="form-control form-control-lg" required />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Bed Number <span class="text-danger">*</span></label>
                                        <input type="text" name="bed_no" id="bed_no" placeholder="Enter Bed Number" class="form-control form-control-lg" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Doctor's Name <span class="text-danger">*</span></label>
                                        <input type="text" name="doctors_name" id="doctors_name" placeholder="Enter Doctor's Name" class="form-control form-control-lg" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cause of Blood Requirement <span class="text-danger">*</span></label>
                                        <input type="text" name="cause_of_blood_requirement" id="cause_of_blood_requirement" placeholder="Enter Cause" class="form-control form-control-lg" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">How many Units Required <span class="text-danger">*</span></label>
                                        <input type="text" name="required_quantity" id="required_quantity" placeholder="e.g., 2 units" class="form-control form-control-lg" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Urgency Level <span class="text-danger">*</span></label>
                                        <select name="urgency_level" id="urgency_level" class="form-select form-select-lg" required>
                                            <option value="">Select Urgency Level</option>
                                            <option value="high">High - within 06 hours</option>
                                            <option value="normal">Normal - 24 to 36 hours</option>
                                        </select>
                                    </div>
                  <div class="col-md-6">
                                        <label class="form-label">Blood Type <span class="text-danger">*</span></label>
                                        <select name="blood_type" id="blood_type" class="form-select form-select-lg" required>
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
                  </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Dr's Prescription (Upload File) <span class="text-danger">*</span></label>
                                        <input type="file" name="dr_prescription" id="dr_prescription" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="form-control form-control-lg" required />
                                        <small class="text-muted">Accepted formats: JPG, PNG, PDF, DOC, DOCX (Max 5MB)</small>
                                        <div class="file-preview" id="file_preview"></div>
                  </div>
                </div>
              </div>

                            <!-- Additional Information -->
              <div class="section-box mb-4">
                                <h4 class="section-title mb-4"><i class="fas fa-comments me-2"></i>Additional Information</h4>
                <div class="row g-3">
                  <div class="col-md-12">
                                        <textarea name="message" id="message" class="form-control form-control-lg" placeholder="Any additional information or message" rows="3"></textarea>
                  </div>
                </div>
              </div>

                            <!-- Terms and Conditions -->
                            <div class="checkbox-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="terms_accepted" id="terms_accepted" value="1" required>
                                    <label class="form-check-label" for="terms_accepted">
                                        I'm agree with terms and conditions and Privacy Policy <span class="text-danger">*</span>
                                    </label>
                  </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="info_correct" id="info_correct" value="1" required>
                                    <label class="form-check-label" for="info_correct">
                                        I'm agree all the information is correct <span class="text-danger">*</span>
                                    </label>
                </div>
              </div>

              <!-- Submit Button -->
              <div class="d-grid mt-4">
                <button type="submit" name="btnRecipient" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Form
                </button>
              </div>
            </form>
        </div>
      </div>
    </div>
  </div>
</section>

  <?php include('assets/includes/footer.php'); ?>
  <?php include('assets/includes/link-js.php'); ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const userData = <?php echo json_encode($user); ?>;
            if (userData) {
                document.getElementById("email").value = userData.email || '';
            }

            <?php if (isset($_SESSION['registration_success'])): ?>
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
            <?php endif; ?>

            <?php if (isset($_SESSION['form_errors'])): ?>
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
            <?php endif; ?>
            
            // CNIC formatting
            document.getElementById('cnic').addEventListener('input', function(e) {
                formatCNIC(this);
            });
            
            // Phone number formatting
            ['contact_number', 'whatsapp_number', 'emergency_contact'].forEach(id => {
                const field = document.getElementById(id);
                if (field) {
                    field.addEventListener('input', function(e) {
                        formatPhone(this);
                    });
                }
            });
            
            // File preview
            document.getElementById('dr_prescription').addEventListener('change', function(e) {
                const file = e.target.files[0];
                const preview = document.getElementById('file_preview');
                if (file) {
                    preview.innerHTML = '<i class="fas fa-file"></i> Selected: ' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                    preview.style.display = 'block';
                } else {
                    preview.style.display = 'none';
                }
            });
        });
        
        function toggleRecipientType() {
            const recipientType = document.querySelector('input[name="recipient_type"]:checked').value;
            const relationSection = document.getElementById('relation_section');
            const relationInput = document.getElementById('relation_with_patient');
            
            if (recipientType === 'other') {
                relationSection.classList.add('show');
                relationInput.required = true;
            } else {
                relationSection.classList.remove('show');
                relationInput.required = false;
                relationInput.value = '';
            }
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
        
        function formatPhone(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.substring(0, 11);
            let formatted = '';
            if (value.length > 4) {
                formatted = value.substring(0, 4) + '-' + value.substring(4);
            } else {
                formatted = value;
            }
            input.value = formatted;
        }
        
  function validateForm() {
    const requiredFields = {
                'full_name': 'Full Name',
                'age': 'Age',
                'gender': 'Gender',
        'email': 'Email',
                'contact_number': 'Phone Number',
                'whatsapp_number': 'WhatsApp Number',
        'cnic': 'CNIC',
                'home_address': 'Home Address',
        'blood_type': 'Blood Type',
        'urgency_level': 'Urgency Level',
                'hospital_name': 'Hospital/Clinic Name',
                'ward_name': 'Ward Name',
                'ward_no': 'Ward Number',
                'bed_no': 'Bed Number',
                'hospital_phone_no': 'Hospital Phone Number',
                'doctors_name': 'Doctor\'s Name',
                'cause_of_blood_requirement': 'Cause of Blood Requirement',
                'required_quantity': 'Units Required'
    };
    
    let errors = [];
    
    // Check required fields
    for (const [field, name] of Object.entries(requiredFields)) {
                const element = document.getElementById(field);
                if (element && !element.value.trim()) {
            errors.push(`${name} is required`);
        }
    }
    
            // Check relation if other type
            const recipientType = document.querySelector('input[name="recipient_type"]:checked').value;
            if (recipientType === 'other') {
                const relation = document.getElementById('relation_with_patient').value.trim();
                if (!relation) {
                    errors.push('Relation with Patient is required');
                }
            }
            
            // Validate CNIC
    const cnic = document.getElementById('cnic').value;
    if (cnic && !/^\d{5}-\d{7}-\d{1}$/.test(cnic)) {
        errors.push('CNIC must be in format XXXXX-XXXXXXX-X');
    }
    
            // Validate phone numbers
            const phoneFields = ['contact_number', 'whatsapp_number'];
            phoneFields.forEach(field => {
                const value = document.getElementById(field).value;
                if (value && !/^03\d{2}-\d{7}$/.test(value)) {
                    errors.push(`${field.replace('_', ' ')} must be in format 0300-1234567`);
                }
            });
    
    // Validate email
    const email = document.getElementById('email').value;
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.push('Please enter a valid email address');
    }
    
            // Check file upload
            const file = document.getElementById('dr_prescription').files[0];
            if (!file) {
                errors.push('Doctor\'s prescription file is required');
    } else {
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                if (!validTypes.includes(file.type)) {
                    errors.push('Only JPG, PNG, PDF, DOC, DOCX files allowed');
                }
                if (file.size > 5 * 1024 * 1024) {
                    errors.push('File must be less than 5MB');
                }
            }
            
            // Check checkboxes
            if (!document.getElementById('terms_accepted').checked) {
                errors.push('You must agree to the terms and conditions');
            }
            if (!document.getElementById('info_correct').checked) {
                errors.push('You must confirm that all information is correct');
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
    </script>
</body>
</html>
