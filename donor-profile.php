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

// =============== 5. GET ONLINE STATUS ===============
$onlineStatus = $profileManager->isUserOnline();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <style>
        /* Modern UI Styles for Donor Profile Page */
        .profile-container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .profile-header {
            background: linear-gradient(135deg, #3498db, #2ecc71);
            padding: 40px 20px;
            text-align: center;
            position: relative;
        }

        .profile-image-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .profile-image:hover {
            transform: scale(1.05);
        }

        .status-badge {
            position: absolute;
            bottom: 0;
            right: -10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
        }

        .status-badge::before {
            content: '';
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #2ecc71;
        }

        .status-badge.offline::before {
            background: #e74c3c;
        }

        .profile-header h2 {
            font-size: 2.2rem;
            color: #fff;
            margin: 10px 0;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .blood-circle {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            background: #e74c3c;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            margin: 10px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .profile-content {
            padding: 30px;
        }

        .profile-details {
            margin-bottom: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .profile-details:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .profile-details h3 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 15px;
            position: relative;
            padding-bottom: 10px;
        }

        .profile-details h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: #3498db;
            border-radius: 2px;
        }

        .profile-details p {
            font-size: 1rem;
            color: #555;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .profile-details p strong {
            color: #2c3e50;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .action-buttons a {
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: #3498db;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .action-buttons a:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .inbox-button {
            background: #2ecc71;
        }

        .inbox-button:hover {
            background: #27ae60;
        }

        .logout-button {
            background: #e74c3c;
        }

        .logout-button:hover {
            background: #c82333;
        }

        /* New Donor Button Styles */
        .donor-button {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 20px;
            background: #ffffff;
            color: #3498db;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .donor-button:hover {
            background: #f1f1f1;
            color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .profile-container {
                margin: 20px;
            }

            .profile-header {
                padding: 30px 15px;
            }

            .profile-image {
                width: 100px;
                height: 100px;
            }

            .profile-header h2 {
                font-size: 1.8rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .action-buttons a {
                width: 100%;
                text-align: center;
            }

            .donor-button {
                top: 15px;
                right: 15px;
                padding: 8px 16px;
                font-size: 0.9rem;
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

    <!-- Donor Profile Section -->
    <div class="breadcrumb_section overflow-hidden ptb-150">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="profile-container">
                        <!-- Profile Header -->
                        <div class="profile-header">
                            <a href="donor-inbox" class="donor-button"> <i class="fa fa-envelope"></i> Inbox</a>
                            <div class="profile-image-container">
                                <img src="<?= htmlspecialchars($donor['profile_pic'] ? $donor['profile_pic'] : 'assets/images/default-avatar.png') ?>" 
                                     alt="Profile Picture" 
                                     class="profile-image">
                                <div class="status-badge <?= $onlineStatus ? 'online' : 'offline' ?>"></div>
                            </div>
                            
                            <h2><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></h2>
                            
                            <div class="blood-type">
                                Blood Group: <span class="blood-circle"><?= htmlspecialchars($donor['blood_type']) ?></span>
                            </div>
                        </div>

                        <!-- Profile Content -->
                        <div class="profile-content">
                            <!-- Personal Information -->
                            <div class="profile-details">
                                <h3>Personal Information</h3>
                                <p><strong>Email:</strong> <?= htmlspecialchars($donor['email'] ? $donor['email'] : 'Not specified') ?></p>
                                <p><strong>Contact Number:</strong> <?= htmlspecialchars($donor['contact_number'] ? $donor['contact_number'] : 'Not specified') ?></p>
                                <p><strong>CNIC:</strong> <?= htmlspecialchars($donor['cnic'] ? $donor['cnic'] : 'Not specified') ?></p>
                                <p><strong>Location:</strong> <?= htmlspecialchars($donor['location'] ? $donor['location'] : 'Not specified') ?></p>
                                <p><strong>Full Address:</strong> <?= htmlspecialchars($donor['full_address'] ? $donor['full_address'] : 'Not specified') ?></p>
                            </div>

                            <!-- Medical Information -->
                            <div class="profile-details">
                                <h3>Medical Information</h3>
                                <p><strong>Health Status:</strong> <?= htmlspecialchars($donor['health_status'] ? $donor['health_status'] : 'Not specified') ?></p>
                                <p><strong>Medical Conditions:</strong> <?= htmlspecialchars($donor['medical_conditions'] ? $donor['medical_conditions'] : 'None') ?></p>
                                <p><strong>Last Donation Date:</strong> <?= $donor['last_donation_date'] ? htmlspecialchars(date('F j, Y', strtotime($donor['last_donation_date']))) : 'Not available' ?></p>
                            </div>

                            <!-- Availability -->
                            <div class="profile-details">
                                <h3>Availability</h3>
                                <p><?= htmlspecialchars($donor['availability'] ? $donor['availability'] : 'Not specified') ?></p>
                            </div>

                            <!-- About Section -->
                            <div class="profile-details">
                                <h3>About</h3>
                                <p><?= htmlspecialchars($donor['about'] ? $donor['about'] : 'No information provided') ?></p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <a href="edit-donor-profile" class="btn btn-primary">Edit Profile</a>
                                <a href="logout" class="logout-button">Logout</a>
                            </div>
                        </div>
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
</DOCUMENT>