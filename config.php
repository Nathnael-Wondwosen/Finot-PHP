<?php
// config.php - Production-Optimized Configuration

// Disable error display in production (set to 1 for debugging only)
define('DEBUG_MODE', 0);
if (!DEBUG_MODE) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// OPcache optimization settings (for production)
ini_set('opcache.enable', 1);
ini_set('opcache.memory_consumption', 256);
ini_set('opcache.max_accelerated_files', 10000);
ini_set('opcache.revalidate_freq', 2);
ini_set('opcache.validate_timestamps', DEBUG_MODE ? 1 : 0);

// Memory limit optimization
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    session_start();
}

// Gzip compression
if (!ob_get_level() && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}

// Database configuration - UPDATE THESE FOR PRODUCTION
$host = 'localhost';
$dbname = 'finotdb';
$username = 'root';
$password = '';

// Production security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');

// Cache control for dynamic content
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Database connection with optimized settings
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => DEBUG_MODE ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_SILENT,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
            SET SESSION wait_timeout=28800,
            SET SESSION interactive_timeout=28800",
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL
    ]);
    
    // Set connection charset
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    if (DEBUG_MODE) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact administrator.");
    }
}

// Enhanced authentication functions
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Redirect to login if not authenticated with security logging
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        // Log unauthorized access attempt
        error_log('Unauthorized access attempt from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        header('Location: login.php');
        exit;
    }
}

// Rate limiting function
function checkRateLimit($action, $limit = 10, $window = 60) {
    $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start' => time()];
    }
    
    $data = $_SESSION[$key];
    
    // Reset if window expired
    if (time() - $data['start'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    // Check limit
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}
?>