<?php
    session_start();
    include('assets/lib/openconn.php');

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Please login to view this page!";
        header("Location: sign-in.php");
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
        header("Location: register-as-recipients.php");
        exit();
    }

    $recipient = $result->fetch_assoc();

    // Calculate online status
    $onlineStatus = false;
    $lastActivityQuery = "SELECT last_activity FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($lastActivityQuery);
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $lastActivityResult = $stmt->get_result();

    if ($lastActivityResult->num_rows > 0) {
        $lastActivity = $lastActivityResult->fetch_assoc()['last_activity'];
        $currentTime = time();
        $lastActivityTime = strtotime($lastActivity);
        $onlineStatus = ($currentTime - $lastActivityTime) < 300; // 5 minutes
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
            --sidebar-color: #f9f9f9;
            --transition: all 0.3s ease-in;
            --font: "Jost", sans-serif;
            --transition_base: 0.3s;
            --success-color: #4cc9f0;
            --danger-color: #ea062b;
            --warning-color: #ffc92e;
            --info-color: #4895ef;
        }

        /* Modern Profile Container */
        .profile-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            font-family: var(--font);
        }

        /* Profile Header */
        .profile-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .profile-header h2 {
            font-size: 2.5rem;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .profile-header .status-badge {
            position: absolute;
            top: 0;
            right: 0;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: var(--shadow);
        }

        .status-badge.online {
            background: var(--success-color);
            color: var(--white);
        }

        .status-badge.offline {
            background: var(--danger-color);
            color: var(--white);
        }

        /* Profile Image */
        .profile-image-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 1.5rem;
        }

        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary-color);
            box-shadow: 0 8px 24px rgba(234, 6, 43, 0.2);
            transition: var(--transition);
        }

        .profile-image:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 28px rgba(234, 6, 43, 0.3);
        }

        /* Blood Group */
        .blood-circle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: var(--white);
            font-weight: bold;
            font-size: 1.25rem;
            margin-left: 0.5rem;
            box-shadow: 0 4px 8px rgba(234, 6, 43, 0.3);
            transition: var(--transition);
        }

        .blood-circle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 12px rgba(234, 6, 43, 0.4);
        }

        /* Profile Details Sections */
        .profile-details {
            margin-bottom: 2.5rem;
            padding: 1.5rem;
            background: var(--gray_bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .profile-details:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .profile-details h3 {
            font-size: 1.5rem;
            color: var(--secondary-color);
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary-color);
            font-weight: 600;
        }

        .detail-item {
            display: flex;
            margin-bottom: 1rem;
            align-items: flex-start;
        }

        .detail-label {
            flex: 0 0 180px;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .detail-value {
            flex: 1;
            color: var(--p-color);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 2.5rem;
            flex-wrap: wrap;
        }

        .action-buttons a {
            text-decoration: none;
            padding: 0.75rem 1.75rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--white);
            background: var(--primary-color);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(234, 6, 43, 0.2);
            font-family: var(--font);
        }

        .action-buttons a:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(234, 6, 43, 0.3);
            background: #c40524;
        }

        .action-buttons a.logout-button {
            background: var(--secondary-color);
            box-shadow: 0 4px 12px rgba(17, 17, 17, 0.2);
        }

        .action-buttons a.logout-button:hover {
            background: #000;
            box-shadow: 0 8px 16px rgba(17, 17, 17, 0.3);
        }

        /* Doctor Receipt Section */
        .receipt-container {
            text-align: center;
            margin-top: 2rem;
        }

        .receipt-container h3 {
            margin-bottom: 1.5rem;
        }

        .receipt-image {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            max-height: 400px;
            object-fit: contain;
            border: 1px solid var(--border-color);
        }

        .receipt-image:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .profile-container {
                padding: 1.5rem;
            }
            
            .profile-header h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .profile-container {
                margin: 1rem;
                padding: 1.25rem;
            }
            
            .profile-header h2 {
                font-size: 1.75rem;
            }
            
            .detail-item {
                flex-direction: column;
            }
            
            .detail-label {
                flex: 1;
                margin-bottom: 0.25rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 1rem;
            }
            
            .action-buttons a {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .profile-header h2 {
                font-size: 1.5rem;
            }
            
            .profile-details h3 {
                font-size: 1.25rem;
            }
            
            .profile-image-container {
                width: 120px;
                height: 120px;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-container {
            animation: fadeIn 0.6s ease-out forwards;
        }
    </style>

</body>
</html>
</head>
<body>
    <!-- Preloader -->
    <?php include('assets/includes/preloader.php'); ?>

    <!-- Scroll to Top -->
    <?php include('assets/includes/scroll-to-top.php'); ?>

    <!-- Header -->
    <?php include('assets/includes/header.php'); ?>

    <!-- Recipient Profile Section -->
    <div class="breadcrumb_section overflow-hidden ptb-150">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-10 col-md-12 col-12">
                    <div class="profile-container">
                        <!-- Profile Header -->
                        <div class="profile-header">
                            <span class="status-badge <?= $onlineStatus ? 'online' : 'offline' ?>">
                                <?= $onlineStatus ? 'Online' : 'Offline' ?>
                            </span>
                            
                            <div class="profile-image-container">
                                <img src="<?= htmlspecialchars($recipient['profile_pic']) ?>" 
                                     alt="Profile Picture" 
                                     class="profile-image">
                            </div>
                            
                            <h2><?= htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']) ?></h2>
                            
                            <p class="blood-type my-3">
                                <strong>Blood Group:</strong> 
                                <span class="blood-circle"><?= htmlspecialchars($recipient['blood_type']) ?></span>
                            </p>
                        </div>

                        <!-- Personal Information -->
                        <div class="profile-details">
                            <h3>Personal Information</h3>
                            <div class="detail-item">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?= htmlspecialchars($recipient['email']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Contact Number:</span>
                                <span class="detail-value"><?= htmlspecialchars($recipient['contact_number']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">CNIC:</span>
                                <span class="detail-value"><?= htmlspecialchars($recipient['cnic']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Location:</span>
                                <span class="detail-value"><?= htmlspecialchars($recipient['location']) ?></span>
                            </div>
                        </div>

                        <!-- Medical Information -->
                        <div class="profile-details">
                            <h3>Medical Information</h3>
                            <div class="detail-item">
                                <span class="detail-label">Urgency level:</span>
                                <span class="detail-value"><?= htmlspecialchars($recipient['urgency_level']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Reason:</span>
                                <span class="detail-value"><?= htmlspecialchars($recipient['reason']) ?></span>
                            </div>
                        </div>

                        <!-- Doctor Receipt -->
                        <div class="profile-details receipt-container">
                            <h3>Doctor Receipt</h3>
                            <img src="<?= htmlspecialchars($recipient['profile_pic']) ?>" 
                                 alt="Doctor Receipt" 
                                 class="receipt-image">
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="edit-recipient-profile">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                            <a href="logout" class="logout-button">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
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