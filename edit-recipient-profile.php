<?php
session_start();
include('assets/lib/openconn.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to view this page!";
    header("Location: sign-in");
    exit();
}

$userId = $_SESSION['user_id'];

// Set and maintain active profile as recipient
$_SESSION['active_profile'] = 'recipient';

// Update recipient status to active in database
$conn->query("UPDATE recipients SET status = 'active' WHERE user_id = '$userId'");
// Deactivate donor profile if exists
$conn->query("UPDATE donors SET status = 'inactive' WHERE user_id = '$userId'");

// Fetch recipient data
$query = "SELECT * FROM recipients WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Recipient profile not found!";
    header("Location: register-as-recipients");
    exit();
}

$recipient = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $cnic = trim($_POST['cnic']);
    $location = trim($_POST['location']);
    $blood_type = $_POST['blood_type'];
    $urgency_level = $_POST['urgency_level'];
    $reason = trim($_POST['reason']);
    $hospital_name = trim($_POST['hospital_name']);
    $required_quantity = trim($_POST['required_quantity']);
    
    // Handle profile picture upload
    $profile_pic = $recipient['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'assets/images/recipient-images/';
        $file_name = 'recipient_' . $userId . '_' . time() . '.' . pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $file_path)) {
            $profile_pic = $file_path;
        }
    }

    // Update recipient data
    $update_query = "UPDATE recipients SET 
        first_name = ?, 
        last_name = ?, 
        email = ?, 
        contact_number = ?, 
        cnic = ?, 
        location = ?, 
        blood_type = ?, 
        urgency_level = ?, 
        reason = ?, 
        profile_pic = ?,
        hospital_name = ?,
        required_quantity = ?
        WHERE user_id = ?";
        
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssssssssssss", 
        $first_name, 
        $last_name, 
        $email, 
        $contact_number, 
        $cnic, 
        $location, 
        $blood_type, 
        $urgency_level, 
        $reason, 
        $profile_pic,
        $hospital_name,
        $required_quantity,
        $userId
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: recipient-profile");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update profile. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>

    <style>
        :root {
            --primary-color: #ea062b;
            --secondary-color: #111111;
            --white: #fff;
            --gray: #e7e7e7;
            --gray1: #f5f5f5;
            --gray2: #f1f1f1;
            --yellow: #ffc92e;
            --p-color: #666666;
            --border-color: #cacaca;
            --shadow: 0px 0px 20px #0000002b;
            --gray_bg: #f9f9f9;
            --transition: all 0.3s ease-in;
            --font: "Jost", sans-serif;
            --success-color: #4cc9f0;
            --danger-color: #ea062b;
            --warning-color: #ffc92e;
            --info-color: #4895ef;
        }

        .edit-profile-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            font-family: var(--font);
        }

        .edit-profile-container h2 {
            font-size: 2rem;
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            color: var(--p-color);
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 8px rgba(234, 6, 43, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input[type="file"] {
            padding: 0.5rem;
        }

        .submit-button {
            display: block;
            width: 100%;
            padding: 0.75rem;
            background: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .submit-button:hover {
            background: #c40524;
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(234, 6, 43, 0.3);
        }

        .error-message,
        .success-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .error-message {
            background: var(--danger-color);
            color: var(--white);
        }

        .success-message {
            background: var(--success-color);
            color: var(--white);
        }

        @media (max-width: 768px) {
            .edit-profile-container {
                margin: 1rem;
                padding: 1.25rem;
            }

            .edit-profile-container h2 {
                font-size: 1.75rem;
            }
        }

        @media (max-width: 576px) {
            .edit-profile-container h2 {
                font-size: 1.5rem;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .edit-profile-container {
            animation: fadeIn 0.6s ease-out forwards;
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

    <!-- Edit Profile Section -->
    <div class="breadcrumb_section overflow-hidden ptb-150">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-10 col-md-12 col-12">
                    <div class="edit-profile-container">
                        <h2>Edit Recipient Profile</h2>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="success-message"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($recipient['first_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($recipient['last_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($recipient['email']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_number">Contact Number</label>
                                <input type="text" id="contact_number" name="contact_number" value="<?= htmlspecialchars($recipient['contact_number']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="cnic">CNIC</label>
                                <input type="text" id="cnic" name="cnic" value="<?= htmlspecialchars($recipient['cnic']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="location">Location</label>
                                <input type="text" id="location" name="location" value="<?= htmlspecialchars($recipient['location']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="blood_type">Blood Type</label>
                                <select id="blood_type" name="blood_type" required>
                                    <option value="A+" <?= $recipient['blood_type'] == 'A+' ? 'selected' : '' ?>>A+</option>
                                    <option value="A-" <?= $recipient['blood_type'] == 'A-' ? 'selected' : '' ?>>A-</option>
                                    <option value="B+" <?= $recipient['blood_type'] == 'B+' ? 'selected' : '' ?>>B+</option>
                                    <option value="B-" <?= $recipient['blood_type'] == 'B-' ? 'selected' : '' ?>>B-</option>
                                    <option value="O+" <?= $recipient['blood_type'] == 'O+' ? 'selected' : '' ?>>O+</option>
                                    <option value="O-" <?= $recipient['blood_type'] == 'O-' ? 'selected' : '' ?>>O-</option>
                                    <option value="AB+" <?= $recipient['blood_type'] == 'AB+' ? 'selected' : '' ?>>AB+</option>
                                    <option value="AB-" <?= $recipient['blood_type'] == 'AB-' ? 'selected' : '' ?>>AB-</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="urgency_level">Urgency Level</label>
                                <select id="urgency_level" name="urgency_level" required>
                                    <option value="urgent" <?= $recipient['urgency_level'] == 'urgent' ? 'selected' : '' ?>>Urgent</option>
                                    <option value="high" <?= $recipient['urgency_level'] == 'high' ? 'selected' : '' ?>>High</option>
                                    <option value="medium" <?= $recipient['urgency_level'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="low" <?= $recipient['urgency_level'] == 'low' ? 'selected' : '' ?>>Low</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="reason">Reason</label>
                                <textarea id="reason" name="reason" required><?= htmlspecialchars($recipient['reason']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="hospital_name">Hospital Name</label>
                                <input type="text" id="hospital_name" name="hospital_name" value="<?= htmlspecialchars($recipient['hospital_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="required_quantity">Required Quantity</label>
                                <input type="text" id="required_quantity" name="required_quantity" value="<?= htmlspecialchars($recipient['required_quantity']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="profile_pic">Profile Picture</label>
                                <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
                            </div>
                            <button type="submit" class="submit-button">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include('assets/includes/footer.php'); ?>

    <!-- Javascript Files -->
    <?php include('assets/includes/link-js.php'); ?>
</body>
</html>