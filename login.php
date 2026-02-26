<?php
session_start();
require 'config.php';

function app_base_path(): string {
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/login.php'));
    $base = rtrim(dirname($scriptName), '/');
    return $base === '/' ? '' : $base;
}

function redirect_to(string $path): void {
    $base = app_base_path();
    $target = ($base !== '' ? $base : '') . '/' . ltrim($path, '/');
    header('Location: ' . $target);
    exit;
}

function tableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            unset($_SESSION['portal_user_id'], $_SESSION['portal_role'], $_SESSION['portal_teacher_id']);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            redirect_to('dashboard.php');
        }

        if (tableExists($pdo, 'portal_users')) {
            $stmt = $pdo->prepare("
                SELECT pu.id, pu.username, pu.password_hash, pu.role, pu.teacher_id, pu.is_active
                FROM portal_users pu
                WHERE pu.username = ?
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $portalUser = $stmt->fetch();

            if ($portalUser && (int)$portalUser['is_active'] === 1 && password_verify($password, $portalUser['password_hash'])) {
                unset($_SESSION['admin_id'], $_SESSION['admin_username']);
                $_SESSION['portal_user_id'] = (int)$portalUser['id'];
                $_SESSION['portal_role'] = (string)$portalUser['role'];
                $_SESSION['portal_teacher_id'] = (int)$portalUser['teacher_id'];

                $upd = $pdo->prepare("UPDATE portal_users SET last_login_at = NOW() WHERE id = ?");
                $upd->execute([(int)$portalUser['id']]);

                if ($portalUser['role'] === 'teacher') {
                    redirect_to('portal/teacher/dashboard.php');
                }
                if ($portalUser['role'] === 'homeroom') {
                    redirect_to('portal/homeroom/dashboard.php');
                }
            }
        }

        $error = "Invalid username or password";
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold text-center mb-6">System Login</h1>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label for="username" class="block text-gray-700 mb-2">Username</label>
                <input type="text" id="username" name="username" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <div>
                <label for="password" class="block text-gray-700 mb-2">Password</label>
                <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Login
            </button>
        </form>
        
        <div class="mt-4 text-center">
            <p class="text-gray-600 text-sm">Admin, Teacher, and Homeroom users can login here.</p>
        </div>
    </div>
</body>
</html>
