<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/openconn.php';

if (isset($_SESSION['super_admin_logged_in']) && $_SESSION['super_admin_logged_in'] === true) {
    header('Location: emergency-dashboard.php');
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Username and password are required.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash, full_name, is_active FROM super_admins WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $error = 'Invalid credentials.';
        } else {
            $row = $res->fetch_assoc();
            // Support legacy MD5 hashes: if stored hash is MD5, verify and rehash to a secure password_hash
            if ((int)$row['is_active'] !== 1) {
                $error = 'Account disabled.';
            } else {
                $storedHash = $row['password_hash'];
                $passwordVerified = false;

                // Prefer modern password_verify (bcrypt/argon2) if applicable
                if (password_verify($password, $storedHash)) {
                    $passwordVerified = true;
                    // Rehash if algorithm changed/needs rehash
                    if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $uStmt = $conn->prepare("UPDATE super_admins SET password_hash = ? WHERE id = ?");
                        $uStmt->bind_param("si", $newHash, $row['id']);
                        $uStmt->execute();
                        $uStmt->close();
                    }
                } elseif (hash_equals($storedHash, md5($password))) {
                    // Legacy MD5 match - upgrade to secure hash
                    $passwordVerified = true;
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $uStmt = $conn->prepare("UPDATE super_admins SET password_hash = ? WHERE id = ?");
                    $uStmt->bind_param("si", $newHash, $row['id']);
                    $uStmt->execute();
                    $uStmt->close();
                }

                if (!$passwordVerified) {
                    $error = 'Invalid credentials.';
                } else {
                session_regenerate_id(true);
                $_SESSION['super_admin_logged_in'] = true;
                $_SESSION['super_admin_name'] = $row['full_name'] ?: $row['username'];
                $_SESSION['super_admin_id'] = (int)$row['id'];
                header('Location: emergency-dashboard.php');
                exit();
            }
        }
        $stmt->close();
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login - Emergency Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white shadow-lg rounded-xl w-full max-w-md">
        <div class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-4 rounded-t-xl">
            <h1 class="text-xl font-semibold">Emergency Super Admin</h1>
            <p class="text-sm text-red-100">Restricted access</p>
        </div>
        <div class="p-6 space-y-4">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700 transition">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>

