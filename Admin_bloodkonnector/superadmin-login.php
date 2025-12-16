<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/openconn.php';

if (isset($_SESSION['super_admin_logged_in']) && $_SESSION['super_admin_logged_in'] === true) {
    header('Location: lifeline-dashboard.php');
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
            if ((int)$row['is_active'] !== 1) {
                $error = 'Account disabled.';
            } elseif (!password_verify($password, $row['password_hash'])) {
                $error = 'Invalid credentials.';
            } else {
                session_regenerate_id(true);
                $_SESSION['super_admin_logged_in'] = true;
                $_SESSION['super_admin_name'] = $row['full_name'] ?: $row['username'];
                $_SESSION['super_admin_id'] = (int)$row['id'];
                header('Location: lifeline-dashboard.php');
                exit();
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login - Lifeline Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white shadow-lg rounded-xl w-full max-w-md">
        <div class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-4 rounded-t-xl">
            <h1 class="text-xl font-semibold">Lifeline Super Admin</h1>
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
                    <p class="text-xs text-gray-500 mt-1">Set env SUPERADMIN_PASS_HASH (bcrypt) or SUPERADMIN_PASS.</p>
                </div>
                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700 transition">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>

