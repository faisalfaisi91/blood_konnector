<?php
    session_start();
    include('assets/lib/openconn.php');
    require_once('assets/lib/ProfileManager.php');
    
    // =============== 1. INITIALIZE PROFILE MANAGER ===============
    $profileManager = new ProfileManager($conn);
    
    // =============== 2. CHECK IF USER IS AN ACTIVE RECIPIENT ===============
    $is_active_recipient = false;
    $has_recipient_profile = false;
    $logged_in = false;
    
    if ($profileManager->isLoggedIn()) {
        $logged_in = true;
        
        // Update last activity
        $profileManager->updateLastActivity();
        
        // Check if user has recipient role
        $has_recipient_profile = $profileManager->hasRole('recipient');
        
        // Check if currently viewing as recipient
        $is_active_recipient = ($profileManager->getCurrentProfile() === 'recipient') && $has_recipient_profile;
    }

    // Get filter parameters
    $bloodType = isset($_POST['bloodType']) ? $_POST['bloodType'] : '';
    $location = isset($_POST['location']) ? $_POST['location'] : '';
    $availability = isset($_POST['availability']) ? $_POST['availability'] : '';
    $emergency = isset($_POST['emergency']) ? $_POST['emergency'] : '';
    $lastDonation = isset($_POST['lastDonation']) ? $_POST['lastDonation'] : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $age = isset($_POST['age']) ? $_POST['age'] : '';
    $healthStatus = isset($_POST['healthStatus']) ? $_POST['healthStatus'] : '';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    // Define the number of donors per page
    $donorsPerPage = 6;
    $offset = ($page - 1) * $donorsPerPage;

    // Build the query with filters
    $query = "SELECT d.*, u.last_activity 
              FROM donors d 
              JOIN users u ON d.user_id = u.user_id 
              WHERE 1=1";

    $params = array();
    $types = '';

    // Add filters to query
    if (!empty($bloodType)) {
        $query .= " AND d.blood_type = ?";
        $params[] = $bloodType;
        $types .= 's';
    }

    if (!empty($location)) {
        $query .= " AND d.location LIKE ?";
        $params[] = '%' . $location . '%';
        $types .= 's';
    }

    if (!empty($availability)) {
        if ($availability === 'weekdays') {
            $query .= " AND d.availability LIKE '%weekday%'";
        } elseif ($availability === 'weekends') {
            $query .= " AND d.availability LIKE '%weekend%'";
        } elseif ($availability === 'emergency') {
            $query .= " AND d.emergency_contact = 'yes'";
        } elseif ($availability === 'anytime') {
            $query .= " AND d.availability LIKE '%anytime%'";
        }
    }

    if (!empty($emergency)) {
        $query .= " AND d.emergency_contact = ?";
        $params[] = $emergency;
        $types .= 's';
    }

    if (!empty($lastDonation)) {
        $months = (int)$lastDonation;
        $query .= " AND d.last_donation_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)";
        $params[] = $months;
        $types .= 'i';
    }

    if (!empty($gender)) {
        $query .= " AND d.gender = ?";
        $params[] = $gender;
        $types .= 's';
    }

    if (!empty($age)) {
        list($minAge, $maxAge) = explode('-', $age);
        $query .= " AND d.age BETWEEN ? AND ?";
        $params[] = $minAge;
        $params[] = $maxAge;
        $types .= 'ii';
    }

    if (!empty($healthStatus)) {
        $query .= " AND d.health_status = ?";
        $params[] = $healthStatus;
        $types .= 's';
    }

    // Count total donors for pagination
    $countQuery = "SELECT COUNT(*) AS total FROM ($query) AS filtered_donors";
    $stmt = $conn->prepare($countQuery);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $countResult = $stmt->get_result();
    $totalDonors = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalDonors / $donorsPerPage);

    // Add pagination to main query
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $donorsPerPage;
    $params[] = $offset;
    $types .= 'ii';

    // Execute main query
    $stmt = $conn->prepare($query);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $donorsHtml = '';
    if ($result->num_rows > 0) {
        while ($donor = $result->fetch_assoc()) {
            // Calculate online status
            $lastActivity = strtotime($donor['last_activity']);
            $currentTime = time();
            $onlineStatus = ($currentTime - $lastActivity) < 300; // 5 minutes
            
            // Use default image if profile_pic is empty or null
            $profileImage = !empty($donor['profile_pic']) ? htmlspecialchars($donor['profile_pic']) : 'assets/images/donor.jpg';
            
            $donorsHtml .= '
            <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                <div class="donor-card">
                    <div class="donor-card-header">
                        <img src="' . $profileImage . '" alt="Profile Picture" class="donor-image">
                        <div class="donor-status ' . ($onlineStatus ? 'online' : 'offline') . '">
                            <i class="fas fa-circle me-1"></i>' . ($onlineStatus ? 'Online' : 'Offline') . '
                        </div>
                    </div>
                    <div class="donor-card-body">
                        <h3>' . htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) . '</h3>
                        <p class="blood-group">
                            <i class="fas fa-tint me-2"></i>Blood Group: ' . htmlspecialchars($donor['blood_type']) . '
                        </p>
                        <p class="location">
                            <i class="fas fa-map-marker-alt me-2"></i>' . htmlspecialchars($donor['location']) . '
                        </p>
                        <p class="last-donation">
                            <i class="fas fa-calendar-alt me-2"></i>Last Donation: ' . 
                            ($donor['last_donation_date'] ? date('F j, Y', strtotime($donor['last_donation_date'])) : 'Never') . '
                        </p>
                    </div>
                    <div class="donor-card-footer">
                        <a href="donor-detail.php?id=' . $donor['user_id'] . '" class="btn btn-danger">
                            <i class="fas fa-user-circle me-2"></i>View Profile
                        </a>';
            
            // Add chat button based on user status
            if (!$logged_in) {
                $donorsHtml .= '
                        <button onclick="showLoginAlert()" class="btn btn-chat" style="background:#3498db;">
                            <i class="fas fa-comments me-2"></i>Chat
                        </button>';
            } elseif (!$has_recipient_profile) {
                $donorsHtml .= '
                        <button onclick="showRegisterAlert()" class="btn btn-chat" style="background:#3498db;">
                            <i class="fas fa-comments me-2"></i>Chat
                        </button>';
            } elseif (!$is_active_recipient) {
                $donorsHtml .= '
                        <button onclick="showActivateAlert()" class="btn btn-chat" style="background:#3498db;">
                            <i class="fas fa-comments me-2"></i>Chat
                        </button>';
            } else {
                $donorsHtml .= '
                        <a href="chat.php?id=' . $donor['user_id'] . '" class="btn btn-chat" style="background:#3498db;">
                            <i class="fas fa-comments me-2"></i>Chat
                        </a>';
            }
            
            $donorsHtml .= '
                    </div>
                </div>
            </div>';
        }
    } else {
        $donorsHtml = '';
    }

    // Generate pagination
    $paginationHtml = '';
    if ($totalPages > 1) {
        $paginationHtml .= '<li class="page-item ' . ($page == 1 ? 'disabled' : '') . '">
            <a class="page-link" href="#" data-page="' . ($page - 1) . '">Previous</a>
        </li>';
        
        for ($i = 1; $i <= $totalPages; $i++) {
            $paginationHtml .= '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                <a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a>
            </li>';
        }
        
        $paginationHtml .= '<li class="page-item ' . ($page == $totalPages ? 'disabled' : '') . '">
            <a class="page-link" href="#" data-page="' . ($page + 1) . '">Next</a>
        </li>';
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'donors' => $donorsHtml,
        'pagination' => $paginationHtml
    ]);
?>