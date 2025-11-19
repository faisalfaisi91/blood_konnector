<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'openconn.php';

// Get counts for dashboard cards
$user_query = "SELECT COUNT(*) as total FROM users";
$user_count = 0;
if ($user_result = $conn->query($user_query)) {
    $user_count = $user_result->fetch_assoc()['total'];
}

$donor_query = "SELECT COUNT(*) as total FROM donors";
$donor_count = 0;
if ($donor_result = $conn->query($donor_query)) {
    $donor_count = $donor_result->fetch_assoc()['total'];
}

$recipient_query = "SELECT COUNT(*) as total FROM recipients";
$recipient_count = 0;
if ($recipient_result = $conn->query($recipient_query)) {
    $recipient_count = $recipient_result->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BloodKonnekt Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="flex flex-col min-h-screen">
        <!-- Header -->
        <header class="bg-gradient-to-r from-red-600 to-red-700 text-white shadow-md">
            <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-heartbeat text-2xl"></i>
                    <h1 class="text-xl font-bold">BloodKonnekt <span class="font-normal">Admin</span></h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="hidden md:inline text-sm"><i class="fas fa-user-shield mr-1"></i> Administrator</span>
                    <a href="logout.php" class="btn btn-primary px-4 py-2 rounded-md text-sm font-medium hover:shadow-md transition-all">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-6 flex-grow">
            <!-- Dashboard Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8 dashboard-cards">
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Total Users</h2>
                            <p class="text-3xl font-bold text-red-600 mt-1"><?php echo $user_count; ?></p>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full">
                            <i class="fas fa-users text-red-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-green-600 text-sm font-medium">
                            <i class="fas fa-arrow-up mr-1"></i> 12% from last month
                        </span>
                    </div>
                </div>

                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Total Donors</h2>
                            <p class="text-3xl font-bold text-red-600 mt-1"><?php echo $donor_count; ?></p>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full">
                            <i class="fas fa-hand-holding-medical text-red-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-green-600 text-sm font-medium">
                            <i class="fas fa-arrow-up mr-1"></i> 8% from last month
                        </span>
                    </div>
                </div>

                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Total Recipients</h2>
                            <p class="text-3xl font-bold text-red-600 mt-1"><?php echo $recipient_count; ?></p>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full">
                            <i class="fas fa-procedures text-red-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-green-600 text-sm font-medium">
                            <i class="fas fa-arrow-up mr-1"></i> 5% from last month
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mb-6">
                <div class="flex space-x-1 border-b border-gray-200">
                    <button class="tab-button px-4 py-3 font-medium text-sm uppercase tracking-wider active" data-tab="users">
                        <i class="fas fa-user-friends mr-2"></i> Users
                    </button>
                    <button class="tab-button px-4 py-3 font-medium text-sm uppercase tracking-wider" data-tab="donors">
                        <i class="fas fa-hand-holding-medical mr-2"></i> Donors
                    </button>
                    <button class="tab-button px-4 py-3 font-medium text-sm uppercase tracking-wider" data-tab="recipients">
                        <i class="fas fa-procedures mr-2"></i> Recipients
                    </button>
                    <button class="tab-button px-4 py-3 font-medium text-sm uppercase tracking-wider" data-tab="requests">
                        <i class="fas fa-tint mr-2"></i> Blood Requests
                    </button>
                    <button class="tab-button px-4 py-3 font-medium text-sm uppercase tracking-wider" data-tab="history">
                        <i class="fas fa-history mr-2"></i> Donation History
                    </button>
                </div>
            </div>

            <!-- Tab Content -->
            <div id="users" class="tab-content">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                    <h2 class="text-xl font-semibold mb-2 sm:mb-0">User Management</h2>
                    <div class="relative w-full sm:w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="userSearch" onkeyup="filterTable('userTable', this.value)" 
                               placeholder="Search users..." 
                               class="search-input pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:outline-none">
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="userTable" class="w-full">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="no-results" style="display: none;">
                                <td colspan="5" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-search fa-lg mb-2"></i>
                                    <p>No matching users found</p>
                                </td>
                            </tr>
                            <?php
                                $users_query = "SELECT * FROM users";
                                if ($users = $conn->query($users_query)) {
                                    while ($row = $users->fetch_assoc()) {
                                        $role = $row['is_donor'] ? 'Donor' : ($row['is_recipient'] ? 'Recipient' : 'User');
                                        $statusClass = ($row['status'] === 'active') ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';

                                        echo "<tr>
                                            <td class='border-b'>{$row['user_id']}</td>
                                            <td class='border-b'>{$row['first_name']} {$row['last_name']}</td>
                                            <td class='border-b'>{$row['email']}</td>
                                            <td class='border-b'><span class='px-2 py-1 text-xs rounded-full {$statusClass}'>{$row['status']}</span></td>
                                            <td class='border-b'>{$role}</td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='p-3 text-red-500'>Error: " . $conn->error . "</td></tr>";
                                }
                            ?>

                        </tbody>
                    </table>
                </div>
            </div>

            <div id="donors" class="tab-content hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                    <h2 class="text-xl font-semibold mb-2 sm:mb-0">Donor Management</h2>
                    <div class="relative w-full sm:w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="donorSearch" onkeyup="filterTable('donorTable', this.value)" 
                               placeholder="Search donors..." 
                               class="search-input pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:outline-none">
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="donorTable" class="w-full">
                        <thead>
                            <tr>
                                <th>Donor ID</th>
                                <th>Name</th>
                                <th>Blood Type</th>
                                <th>Contact</th>
                                <th>Health Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="no-results" style="display: none;">
                                <td colspan="5" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-search fa-lg mb-2"></i>
                                    <p>No matching donors found</p>
                                </td>
                            </tr>
                            <?php
                                $donors_query = "SELECT * FROM donors";
                                if ($donors = $conn->query($donors_query)) {
                                    if ($donors->num_rows === 0) {
                                        echo "<tr><td colspan='5' class='text-center py-8 text-gray-500'>
                                                <i class='fas fa-info-circle mr-2'></i>No donors found
                                              </td></tr>";
                                    } else {
                                        while ($row = $donors->fetch_assoc()) {
                                            $healthClass = ($row['health_status'] === 'good') 
                                                ? 'bg-green-100 text-green-800' 
                                                : 'bg-yellow-100 text-yellow-800';

                                            echo "<tr>
                                                <td class='border-b'>{$row['donor_id']}</td>
                                                <td class='border-b'>{$row['first_name']} {$row['last_name']}</td>
                                                <td class='border-b'><span class='px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full'>{$row['blood_type']}</span></td>
                                                <td class='border-b'>{$row['contact_number']}</td>
                                                <td class='border-b'><span class='px-2 py-1 text-xs rounded-full {$healthClass}'>{$row['health_status']}</span></td>
                                            </tr>";
                                        }
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='p-3 text-red-500'>Error: " . $conn->error . "</td></tr>";
                                }
                            ?>

                        </tbody>
                    </table>
                </div>
            </div>

            <div id="recipients" class="tab-content hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                    <h2 class="text-xl font-semibold mb-2 sm:mb-0">Recipient Management</h2>
                    <div class="relative w-full sm:w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="recipientSearch" onkeyup="filterTable('recipientTable', this.value)" 
                               placeholder="Search recipients..." 
                               class="search-input pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:outline-none">
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="recipientTable" class="w-full">
                        <thead>
                            <tr>
                                <th>Recipient ID</th>
                                <th>Name</th>
                                <th>Blood Type</th>
                                <th>Urgency</th>
                                <th>Hospital</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="no-results" style="display: none;">
                                <td colspan="5" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-search fa-lg mb-2"></i>
                                    <p>No matching recipients found</p>
                                </td>
                            </tr>
                            <?php
                            $recipients_query = "SELECT * FROM recipients";
                            if ($recipients = $conn->query($recipients_query)) {
                                if ($recipients->num_rows === 0) {
                                    echo "<tr><td colspan='5' class='text-center py-8 text-gray-500'><i class='fas fa-info-circle mr-2'></i>No recipients found</td></tr>";
                                } else {
                                    while ($row = $recipients->fetch_assoc()) {
                                        $urgencyClass = '';
                                        if ($row['urgency_level'] === 'high') {
                                            $urgencyClass = 'bg-red-100 text-red-800';
                                        } elseif ($row['urgency_level'] === 'medium') {
                                            $urgencyClass = 'bg-yellow-100 text-yellow-800';
                                        } else {
                                            $urgencyClass = 'bg-blue-100 text-blue-800';
                                        }
                                        
                                        echo "<tr>
                                            <td class='border-b'>{$row['recipient_id']}</td>
                                            <td class='border-b'>{$row['first_name']} {$row['last_name']}</td>
                                            <td class='border-b'><span class='px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full'>{$row['blood_type']}</span></td>
                                            <td class='border-b'><span class='px-2 py-1 text-xs rounded-full {$urgencyClass}'>{$row['urgency_level']}</span></td>
                                            <td class='border-b'>{$row['hospital_name']}</td>
                                        </tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='5' class='p-3 text-red-500'>Error: " . $conn->error . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="requests" class="tab-content hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                    <h2 class="text-xl font-semibold mb-2 sm:mb-0">Blood Requests</h2>
                    <div class="relative w-full sm:w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="requestSearch" onkeyup="filterTable('requestTable', this.value)" 
                               placeholder="Search requests..." 
                               class="search-input pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:outline-none">
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="requestTable" class="w-full">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Recipient ID</th>
                                <th>Blood Type</th>
                                <th>Urgency</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="no-results" style="display: none;">
                                <td colspan="5" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-search fa-lg mb-2"></i>
                                    <p>No matching requests found</p>
                                </td>
                            </tr>
                            <?php
                            $requests_query = "SELECT * FROM blood_requests";
                            if ($requests = $conn->query($requests_query)) {
                                if ($requests->num_rows === 0) {
                                    echo "<tr><td colspan='5' class='text-center py-8 text-gray-500'><i class='fas fa-info-circle mr-2'></i>No blood requests found</td></tr>";
                                } else {
                                    while ($row = $requests->fetch_assoc()) {
                                        $statusClass = '';
                                        if ($row['status'] === 'completed') {
                                            $statusClass = 'bg-green-100 text-green-800';
                                        } elseif ($row['status'] === 'pending') {
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                        } else {
                                            $statusClass = 'bg-red-100 text-red-800';
                                        }
                                        
                                        echo "<tr>
                                            <td class='border-b'>{$row['request_id']}</td>
                                            <td class='border-b'>{$row['recipient_id']}</td>
                                            <td class='border-b'><span class='px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full'>{$row['blood_type']}</span></td>
                                            <td class='border-b'>{$row['urgency']}</td>
                                            <td class='border-b'><span class='px-2 py-1 text-xs rounded-full {$statusClass}'>{$row['status']}</span></td>
                                        </tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='5' class='p-3 text-red-500'>Error: " . $conn->error . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="history" class="tab-content hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                    <h2 class="text-xl font-semibold mb-2 sm:mb-0">Donation History</h2>
                    <div class="relative w-full sm:w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="historySearch" onkeyup="filterTable('historyTable', this.value)" 
                               placeholder="Search history..." 
                               class="search-input pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:outline-none">
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="historyTable" class="w-full">
                        <thead>
                            <tr>
                                <th>History ID</th>
                                <th>Donor ID</th>
                                <th>Recipient ID</th>
                                <th>Blood Type</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="no-results" style="display: none;">
                                <td colspan="5" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-search fa-lg mb-2"></i>
                                    <p>No matching history found</p>
                                </td>
                            </tr>
                            <?php
                            $history_query = "SELECT * FROM donation_history";
                            if ($history = $conn->query($history_query)) {
                                if ($history->num_rows === 0) {
                                    echo "<tr><td colspan='5' class='text-center py-8 text-gray-500'><i class='fas fa-info-circle mr-2'></i>No donation history found</td></tr>";
                                } else {
                                    while ($row = $history->fetch_assoc()) {
                                        $donationDate = date('M d, Y', strtotime($row['donation_date']));
                                        echo "<tr>
                                            <td class='border-b'>{$row['history_id']}</td>
                                            <td class='border-b'>{$row['donor_id']}</td>
                                            <td class='border-b'>{$row['recipient_id']}</td>
                                            <td class='border-b'><span class='px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full'>{$row['blood_type']}</span></td>
                                            <td class='border-b'>{$donationDate}</td>
                                        </tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='5' class='p-3 text-red-500'>Error: " . $conn->error . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-4">
            <div class="container mx-auto px-4 text-center">
                <p class="text-sm">&copy; <?php echo date('Y'); ?> BloodKonnekt. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script src="script.js"></script>
</body>
</html>