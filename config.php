<?php
// config.php - cPanel-safe Production Configuration

define('CACHE_TTL_DASHBOARD', 300);
define('CACHE_TTL_GENERAL', 600);
define('APP_NAME', 'Finote Selam Learning Management System');
define('APP_ENV', 'production');

// -----------------------------
// 1) DEBUG / ERROR HANDLING
// -----------------------------
define('DEBUG_MODE', 1); // set to 1 temporarily to debug on server

if (!DEBUG_MODE) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// -----------------------------
// 2) PERFORMANCE SETTINGS
// -----------------------------
// Some shared hosts ignore these; that's OK.
@ini_set('opcache.enable', 1);
@ini_set('opcache.memory_consumption', 256);
@ini_set('opcache.max_accelerated_files', 10000);
@ini_set('opcache.revalidate_freq', 2);
@ini_set('opcache.validate_timestamps', DEBUG_MODE ? 1 : 0);

@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', 300);

// -----------------------------
// 3) SECURE SESSION SETTINGS
// -----------------------------
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', 1);

    // cookie_secure should be 1 only if HTTPS is actually on
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    @ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    @ini_set('session.use_strict_mode', 1);

    // Some cPanel PHP versions may not support this ini_set; safe to suppress warnings
    @ini_set('session.cookie_samesite', 'Strict');

    @ini_set('session.gc_maxlifetime', 3600); // 1 hour
    @ini_set('session.gc_probability', 1);
    @ini_set('session.gc_divisor', 100);

    session_start();
}

// -----------------------------
// 4) GZIP OUTPUT (Optional)
// -----------------------------
if (!ob_get_level() && !ini_get('zlib.output_compression')) {
    // Only enable if headers not already sent
    if (!headers_sent()) {
        ob_start('ob_gzhandler');
    }
}

// -----------------------------
// 5) DATABASE CONFIG (cPanel)
// -----------------------------
// On cPanel, 'localhost' is usually best because it uses the MySQL socket.
// 127.0.0.1 forces TCP and can fail on some shared hosts.
$host     = 'localhost';
$dbname   = 'finotekv_Finot';
$username = 'finotekv_system';
$password = 'Finoteselam.27';

// -----------------------------
// 6) SECURITY HEADERS (Safe)
// -----------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');

    // Cache control for dynamic content
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
}

// -----------------------------
// 7) PDO CONNECTION (FIXED)
// -----------------------------
try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password, [
        // Use exception mode when debugging. In production you can keep silent if you want,
        // but exception mode is better for detecting failures during deployment.
        PDO::ATTR_ERRMODE => DEBUG_MODE ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,

        // Persistent connections often cause trouble on shared hosting
        // PDO::ATTR_PERSISTENT => true,  // removed
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
    ]);

    // Run session-level MySQL settings AFTER connect (most compatible on cPanel)
    $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    $pdo->exec("SET SESSION wait_timeout=28800");
    $pdo->exec("SET SESSION interactive_timeout=28800");
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());

    if (DEBUG_MODE) {
        die("Database connection failed: " . $e->getMessage());
    }

    die("Database connection failed. Please contact administrator.");
}

// -----------------------------
// 8) AUTH FUNCTIONS
// -----------------------------
function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireAdminLogin(): void {
    if (!isAdminLoggedIn()) {
        error_log('Unauthorized access attempt from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        header('Location: login.php');
        exit;
    }
}

// -----------------------------
// 9) RATE LIMIT (Session based)
// -----------------------------
function checkRateLimit(string $action, int $limit = 10, int $window = 60): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_limit_' . $action . '_' . $ip;

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