<?php
/**
 * Security Hardening Script for Finot-PHP
 * Implements production-ready security measures
 */

require_once 'config.php';

class SecurityHardener {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Run all security hardening measures
     */
    public function hardenSecurity() {
        $this->secureDatabase();
        $this->implementCSRFProtection();
        $this->addInputValidation();
        $this->setupSecurityHeaders();
        $this->createSecurityLogs();
        $this->addRateLimiting();
        $this->secureFileUploads();
        $this->addSQLInjectionProtection();
    }

    /**
     * Database security enhancements
     */
    private function secureDatabase() {
        // Create security audit table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS security_audit (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                user_id INT DEFAULT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                event_data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_created_at (created_at),
                INDEX idx_ip_address (ip_address)
            )
        ");

        // Create failed login attempts table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS failed_login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_username_ip (username, ip_address),
                INDEX idx_attempt_time (attempt_time)
            )
        ");

        // Add security columns to existing tables
        try {
            $this->pdo->exec("ALTER TABLE admins ADD COLUMN last_password_change TIMESTAMP NULL DEFAULT NULL");
            $this->pdo->exec("ALTER TABLE admins ADD COLUMN password_reset_token VARCHAR(255) NULL DEFAULT NULL");
            $this->pdo->exec("ALTER TABLE admins ADD COLUMN password_reset_expires TIMESTAMP NULL DEFAULT NULL");
            $this->pdo->exec("ALTER TABLE admins ADD COLUMN account_locked_until TIMESTAMP NULL DEFAULT NULL");
            $this->pdo->exec("ALTER TABLE admins ADD COLUMN failed_login_count INT DEFAULT 0");
        } catch (Exception $e) {
            // Columns might already exist
        }
    }

    /**
     * Implement CSRF protection
     */
    private function implementCSRFProtection() {
        // Create CSRF token functions
        $csrfFunctions = '
<?php
/**
 * CSRF Protection Functions
 */

function generateCSRFToken() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION["csrf_token"]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION["csrf_token"], $token);
}

function getCSRFTokenField() {
    $token = generateCSRFToken();
    return \'<input type="hidden" name="csrf_token" value="\' . htmlspecialchars($token) . \'">\';
}

function requireCSRFToken() {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (!isset($_POST["csrf_token"]) || !validateCSRFToken($_POST["csrf_token"])) {
            logSecurityEvent("csrf_attempt", null, $_SERVER["REMOTE_ADDR"], [
                "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "",
                "request_uri" => $_SERVER["REQUEST_URI"] ?? "",
                "post_data" => array_keys($_POST)
            ]);
            http_response_code(403);
            die("CSRF token validation failed");
        }
    }
}
?>';

        file_put_contents('includes/csrf_protection.php', $csrfFunctions);
    }

    /**
     * Add comprehensive input validation
     */
    private function addInputValidation() {
        $validationFunctions = '
<?php
/**
 * Input Validation and Sanitization Functions
 */

function sanitizeInput($input, $type = "string") {
    switch ($type) {
        case "email":
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case "url":
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        case "int":
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case "float":
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case "string":
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, "UTF-8");
    }
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    // Ethiopian phone number validation
    $phone = preg_replace("/[^0-9]/", "", $phone);
    return preg_match("/^(09|\\+2519|2519)[0-9]{8}$/", $phone);
}

function validateEthiopianName($name) {
    // Allow Ethiopian characters and basic Latin
    return preg_match("/^[\p{L}\p{M}\s\'-]{2,50}$/u", $name);
}

function validateAge($birthDate) {
    try {
        $birth = new DateTime($birthDate);
        $now = new DateTime();
        $age = $now->diff($birth)->y;
        return $age >= 3 && $age <= 100;
    } catch (Exception $e) {
        return false;
    }
}

function validateFileUpload($file, $allowedTypes = ["image/jpeg", "image/png", "image/webp"], $maxSize = 5242880) {
    if (!isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file["size"] > $maxSize) {
        return false;
    }

    if (!in_array($file["type"], $allowedTypes)) {
        return false;
    }

    // Check file extension
    $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowedExtensions = ["jpg", "jpeg", "png", "webp"];
    if (!in_array($extension, $allowedExtensions)) {
        return false;
    }

    return true;
}

function validateStudentData($data) {
    $errors = [];

    // Required fields
    $required = ["full_name", "gender", "birth_date"];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace("_", " ", $field)) . " is required";
        }
    }

    // Name validation
    if (!empty($data["full_name"]) && !validateEthiopianName($data["full_name"])) {
        $errors[] = "Invalid full name format";
    }

    // Email validation
    if (!empty($data["email"]) && !validateEmail($data["email"])) {
        $errors[] = "Invalid email address";
    }

    // Phone validation
    if (!empty($data["phone_number"]) && !validatePhone($data["phone_number"])) {
        $errors[] = "Invalid phone number";
    }

    // Age validation
    if (!empty($data["birth_date"]) && !validateAge($data["birth_date"])) {
        $errors[] = "Invalid birth date or age";
    }

    // Gender validation
    if (!empty($data["gender"]) && !in_array($data["gender"], ["male", "female"])) {
        $errors[] = "Invalid gender";
    }

    return $errors;
}
?>';

        file_put_contents('includes/input_validation.php', $validationFunctions);
    }

    /**
     * Setup security headers
     */
    private function setupSecurityHeaders() {
        $securityHeaders = '
<?php
/**
 * Security Headers Configuration
 */

// Set security headers
function setSecurityHeaders() {
    // Prevent clickjacking
    header("X-Frame-Options: SAMEORIGIN");

    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");

    // Enable XSS protection
    header("X-XSS-Protection: 1; mode=block");

    // Referrer policy
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // Content Security Policy
    $csp = "default-src \'self\'; " .
           "script-src \'self\' \'unsafe-inline\' https://code.jquery.com https://cdnjs.cloudflare.com; " .
           "style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; " .
           "font-src \'self\' https://fonts.gstatic.com; " .
           "img-src \'self\' data: https:; " .
           "connect-src \'self\'; " .
           "frame-ancestors \'self\'; " .
           "base-uri \'self\'; " .
           "form-action \'self\';";

    header("Content-Security-Policy: " . $csp);

    // Permissions policy
    header("Permissions-Policy: geolocation=(), microphone=(), camera=(self), payment=()");

    // HSTS (HTTP Strict Transport Security) - Only enable in production with HTTPS
    // header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Set headers for all pages
setSecurityHeaders();
?>';

        file_put_contents('includes/security_headers.php', $securityHeaders);
    }

    /**
     * Create security logging system
     */
    private function createSecurityLogs() {
        $securityLogger = '
<?php
/**
 * Security Event Logging System
 */

function logSecurityEvent($eventType, $userId = null, $ipAddress = null, $eventData = []) {
    global $pdo;

    if (!$ipAddress) {
        $ipAddress = $_SERVER["HTTP_X_FORWARDED_FOR"] ??
                    $_SERVER["HTTP_X_REAL_IP"] ??
                    $_SERVER["REMOTE_ADDR"] ??
                    "unknown";
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO security_audit
            (event_type, user_id, ip_address, user_agent, event_data)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $eventType,
            $userId,
            $ipAddress,
            $_SERVER["HTTP_USER_AGENT"] ?? "",
            json_encode($eventData)
        ]);
    } catch (Exception $e) {
        // Log to file if database logging fails
        error_log("Security event logging failed: " . $e->getMessage());
        error_log("Event: $eventType, IP: $ipAddress, Data: " . json_encode($eventData));
    }
}

function logFailedLogin($username, $ipAddress = null) {
    global $pdo;

    if (!$ipAddress) {
        $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO failed_login_attempts
            (username, ip_address) VALUES (?, ?)
        ");
        $stmt->execute([$username, $ipAddress]);

        // Check for brute force attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts
            FROM failed_login_attempts
            WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ipAddress]);
        $result = $stmt->fetch();

        if ($result["attempts"] >= 5) {
            logSecurityEvent("brute_force_attempt", null, $ipAddress, [
                "username" => $username,
                "attempts" => $result["attempts"]
            ]);
        }
    } catch (Exception $e) {
        error_log("Failed login logging failed: " . $e->getMessage());
    }
}

function isIPBlocked($ipAddress = null) {
    global $pdo;

    if (!$ipAddress) {
        $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as recent_attempts
            FROM failed_login_attempts
            WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ipAddress]);
        $result = $stmt->fetch();

        return $result["recent_attempts"] >= 10; // Block after 10 failed attempts
    } catch (Exception $e) {
        return false;
    }
}
?>';

        file_put_contents('includes/security_logger.php', $securityLogger);
    }

    /**
     * Enhanced rate limiting
     */
    private function addRateLimiting() {
        $rateLimiter = '
<?php
/**
 * Advanced Rate Limiting System
 */

class RateLimiter {
    private $pdo;
    private $maxAttempts;
    private $timeWindow;

    public function __construct($pdo, $maxAttempts = 100, $timeWindow = 60) {
        $this->pdo = $pdo;
        $this->maxAttempts = $maxAttempts;
        $this->timeWindow = $timeWindow;
    }

    public function checkLimit($identifier, $action = "general") {
        $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
        $key = md5($ip . $identifier . $action);

        try {
            // Clean old entries
            $stmt = $this->pdo->prepare("
                DELETE FROM rate_limits
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$this->timeWindow]);

            // Count current attempts
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as attempts
                FROM rate_limits
                WHERE rate_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$key, $this->timeWindow]);
            $result = $stmt->fetch();

            if ($result["attempts"] >= $this->maxAttempts) {
                logSecurityEvent("rate_limit_exceeded", null, $ip, [
                    "action" => $action,
                    "attempts" => $result["attempts"]
                ]);
                return false;
            }

            // Record this attempt
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (rate_key, ip_address, action, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$key, $ip, $action]);

            return true;

        } catch (Exception $e) {
            // If database fails, allow request but log error
            error_log("Rate limiting failed: " . $e->getMessage());
            return true;
        }
    }
}

// Create rate limits table
function createRateLimitsTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rate_key VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) DEFAULT \'general\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rate_key (rate_key),
            INDEX idx_ip_action (ip_address, action),
            INDEX idx_created_at (created_at)
        )
    ");
}
?>';

        file_put_contents('includes/rate_limiter.php', $rateLimiter);
    }

    /**
     * Secure file upload handling
     */
    private function secureFileUploads() {
        $secureUpload = '
<?php
/**
 * Secure File Upload Handler
 */

function handleSecureFileUpload($fileInput, $uploadDir = "uploads/", $allowedTypes = ["image/jpeg", "image/png", "image/webp"]) {
    $errors = [];

    // Check if file was uploaded
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]["error"] !== UPLOAD_ERR_OK) {
        $errors[] = "No file uploaded or upload error";
        return ["success" => false, "errors" => $errors];
    }

    $file = $_FILES[$fileInput];

    // Validate file
    if (!validateFileUpload($file, $allowedTypes)) {
        $errors[] = "Invalid file type or size";
        logSecurityEvent("invalid_file_upload", null, $_SERVER["REMOTE_ADDR"], [
            "file_name" => $file["name"],
            "file_type" => $file["type"],
            "file_size" => $file["size"]
        ]);
        return ["success" => false, "errors" => $errors];
    }

    // Create upload directory if it doesn\'t exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate secure filename
    $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $secureName = bin2hex(random_bytes(16)) . "." . $extension;
    $filePath = $uploadDir . $secureName;

    // Move uploaded file
    if (move_uploaded_file($file["tmp_name"], $filePath)) {
        // Set proper permissions
        chmod($filePath, 0644);

        // Log successful upload
        logSecurityEvent("file_upload_success", $_SESSION["admin_id"] ?? null, $_SERVER["REMOTE_ADDR"], [
            "original_name" => $file["name"],
            "secure_name" => $secureName,
            "file_size" => $file["size"]
        ]);

        return [
            "success" => true,
            "file_path" => $filePath,
            "file_name" => $secureName,
            "original_name" => $file["name"]
        ];
    } else {
        $errors[] = "Failed to save uploaded file";
        return ["success" => false, "errors" => $errors];
    }
}

function cleanupOldUploads($directory = "uploads/", $maxAge = 86400) { // 24 hours
    if (!is_dir($directory)) return;

    $files = glob($directory . "*");
    $now = time();

    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
            unlink($file);
        }
    }
}
?>';

        file_put_contents('includes/secure_upload.php', $secureUpload);
    }

    /**
     * Add SQL injection protection
     */
    private function addSQLInjectionProtection() {
        $sqlProtection = '
<?php
/**
 * SQL Injection Protection Utilities
 */

function prepareSecureStatement($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);

        // Bind parameters with proper types
        foreach ($params as $key => $value) {
            $paramType = PDO::PARAM_STR;

            if (is_int($value)) {
                $paramType = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $paramType = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $paramType = PDO::PARAM_NULL;
            }

            $stmt->bindValue($key, $value, $paramType);
        }

        return $stmt;
    } catch (Exception $e) {
        logSecurityEvent("sql_preparation_error", $_SESSION["admin_id"] ?? null, $_SERVER["REMOTE_ADDR"], [
            "query" => substr($query, 0, 100) . "...",
            "error" => $e->getMessage()
        ]);
        throw $e;
    }
}

function executeSecureQuery($pdo, $query, $params = []) {
    $stmt = prepareSecureStatement($pdo, $query, $params);
    $stmt->execute();
    return $stmt;
}

function sanitizeSearchInput($input) {
    // Remove potentially dangerous characters but allow Ethiopian characters
    $input = trim($input);
    $input = preg_replace("/[^\p{L}\p{N}\s\'-]/u", "", $input);
    return $input;
}

function buildSecureSearchQuery($searchTerm, $fields) {
    if (empty($searchTerm) || empty($fields)) {
        return ["", []];
    }

    $sanitizedTerm = sanitizeSearchInput($searchTerm);
    $conditions = [];
    $params = [];

    foreach ($fields as $field) {
        $conditions[] = "$field LIKE ?";
        $params[] = "%$sanitizedTerm%";
    }

    $whereClause = "(" . implode(" OR ", $conditions) . ")";
    return [$whereClause, $params];
}
?>';

        file_put_contents('includes/sql_protection.php', $sqlProtection);
    }

    /**
     * Get security hardening report
     */
    public function getReport() {
        return [
            "database_security" => "Security audit tables created",
            "csrf_protection" => "CSRF protection implemented",
            "input_validation" => "Comprehensive validation added",
            "security_headers" => "Security headers configured",
            "security_logging" => "Security event logging enabled",
            "rate_limiting" => "Advanced rate limiting implemented",
            "file_security" => "Secure file upload handling added",
            "sql_protection" => "SQL injection protection enhanced",
            "timestamp" => date("Y-m-d H:i:s")
        ];
    }
}

// Run security hardening if called directly
if (basename(__FILE__) === basename($_SERVER["PHP_SELF"])) {
    echo "🔒 Starting Security Hardening...\n";

    if (!isset($_SESSION["admin_id"])) {
        die("❌ Admin access required");
    }

    echo "✅ Admin access verified\n";

    $hardener = new SecurityHardener($pdo);
    echo "✅ SecurityHardener initialized\n";

    $hardener->hardenSecurity();
    echo "✅ Security hardening completed\n";

    $report = $hardener->getReport();
    echo "✅ Report generated\n";

    header("Content-Type: application/json");
    echo json_encode([
        "success" => true,
        "message" => "Security hardening completed successfully!",
        "report" => $report
    ]);
}
?>