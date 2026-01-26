<?php
session_start();
require_once __DIR__ . '/assets/lib/openconn.php';

// Check if user is logged in as donor
if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$donor_id = $_SESSION['user_id'];

// Get donor details
$donor_query = "SELECT d.*, u.email, u.first_name, u.last_name FROM donors d 
                JOIN users u ON d.user_id = u.user_id 
                WHERE d.user_id = ?";
$stmt = $conn->prepare($donor_query);
$stmt->bind_param("s", $donor_id);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();

echo "<h2>Donor Info</h2>";
echo "ID: " . $donor_id . "<br>";
echo "Name: " . ($donor['first_name'] ?? 'N/A') . " " . ($donor['last_name'] ?? 'N/A') . "<br>";
echo "Blood Type: " . ($donor['blood_type'] ?? 'N/A') . "<br>";
echo "Location: " . ($donor['location'] ?? 'N/A') . "<br>";
echo "City: " . ($donor['city'] ?? 'N/A') . "<br>";

$blood_type = $donor['blood_type'] ?? 'Not specified';
$donorLocation = $donor['location'] ?? $donor['city'] ?? '';

echo "<h2>Pending Emergency Requests (All)</h2>";
$all_requests = "SELECT id, recipient_id, blood_type, city, status, preferred_date, preferred_time FROM emergency_requests WHERE status = 'pending'";
$stmt = $conn->prepare($all_requests);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "Request ID: " . $row['id'] . " | Blood Type: " . ($row['blood_type'] ?? 'NULL') . " | City: " . ($row['city'] ?? 'NULL') . " | Status: " . $row['status'] . "<br>";
}

echo "<h2>Recipients Blood Types</h2>";
$recipients_query = "SELECT user_id, blood_type, location FROM recipients";
$stmt = $conn->prepare($recipients_query);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "User ID: " . $row['user_id'] . " | Blood Type: " . ($row['blood_type'] ?? 'NULL') . " | Location: " . ($row['location'] ?? 'NULL') . "<br>";
}

echo "<h2>Testing Query 1: Match by emergency_requests blood_type and city</h2>";
$test_query1 = "SELECT lr.*, u.first_name AS recipient_first, u.last_name AS recipient_last,
                lc.donor_id, lc.donor_response
        FROM emergency_requests lr
        LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id
        JOIN users u ON u.user_id = lr.recipient_id
        WHERE lr.status = 'pending'
          AND lr.blood_type = ?
          AND (lr.city = ? OR LOWER(lr.city) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', lr.city, '%')))
          AND (lc.donor_id IS NULL OR lc.donor_id != ?)
          AND lr.id NOT IN (SELECT request_id FROM emergency_confirmations WHERE donor_id = ? AND donor_response = 'approve')
        ORDER BY lr.created_at DESC
        LIMIT 5";
$stmt = $conn->prepare($test_query1);
if ($stmt) {
    $stmt->bind_param("ssssss", $blood_type, $donorLocation, $donorLocation, $donorLocation, $donor_id, $donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "Results: " . $result->num_rows . " rows<br>";
    while ($row = $result->fetch_assoc()) {
        echo "Found: ID=" . $row['id'] . " BT=" . $row['blood_type'] . " City=" . $row['city'] . "<br>";
    }
}

echo "<h2>Testing Query 3 (Fallback): Match by recipients blood_type and location</h2>";
$test_query3 = "SELECT lr.*, u.first_name AS recipient_first, u.last_name AS recipient_last,
               lc.donor_id, lc.donor_response,
               r.blood_type AS recipient_blood_type
        FROM emergency_requests lr
        LEFT JOIN emergency_confirmations lc ON lc.request_id = lr.id
        JOIN users u ON u.user_id = lr.recipient_id
        LEFT JOIN recipients r ON r.user_id = lr.recipient_id
        WHERE lr.status = 'pending'
          AND r.blood_type = ?
          AND (LOWER(r.location) LIKE LOWER(CONCAT('%', ?, '%')) OR LOWER(?) LIKE LOWER(CONCAT('%', r.location, '%')))
          AND (lc.donor_id IS NULL OR lc.donor_id != ?)
          AND lr.id NOT IN (SELECT request_id FROM emergency_confirmations WHERE donor_id = ? AND donor_response = 'approve')
        ORDER BY lr.created_at DESC
        LIMIT 5";
$stmt = $conn->prepare($test_query3);
if ($stmt) {
    $stmt->bind_param("sssss", $blood_type, $donorLocation, $donorLocation, $donor_id, $donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "Results: " . $result->num_rows . " rows<br>";
    while ($row = $result->fetch_assoc()) {
        echo "Found: ID=" . $row['id'] . " Recipient BT=" . ($row['recipient_blood_type'] ?? 'NULL') . "<br>";
    }
}

echo "<h2>Schema Check</h2>";
$checkCols = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'emergency_requests' AND COLUMN_NAME IN ('blood_type', 'city')");
$hasBloodType = false;
$hasCity = false;
if ($checkCols) {
    while ($col = $checkCols->fetch_assoc()) {
        echo "Found column: " . $col['COLUMN_NAME'] . "<br>";
        if ($col['COLUMN_NAME'] === 'blood_type') $hasBloodType = true;
        if ($col['COLUMN_NAME'] === 'city') $hasCity = true;
    }
}
echo "Has blood_type: " . ($hasBloodType ? 'YES' : 'NO') . "<br>";
echo "Has city: " . ($hasCity ? 'YES' : 'NO') . "<br>";

?>
