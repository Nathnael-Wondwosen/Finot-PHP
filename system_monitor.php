<?php
/**
 * Advanced Monitoring and Logging System for Finot-PHP
 * Provides comprehensive system monitoring, error tracking, and performance analytics
 */

require_once 'config.php';

class SystemMonitor {
    private $pdo;
    private $logFile;
    private $performanceLog;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logFile = 'logs/system.log';
        $this->performanceLog = 'logs/performance.log';

        // Create logs directory if it doesn't exist
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
    }

    /**
     * Initialize monitoring system
     */
    public function initializeMonitoring() {
        $this->createMonitoringTables();
        $this->setupErrorHandling();
        $this->createHealthCheckEndpoint();
        $this->setupPerformanceMonitoring();
        $this->createLogRotation();
    }

    /**
     * Create monitoring database tables
     */
    private function createMonitoringTables() {
        // System performance metrics
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS system_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                metric_type VARCHAR(50) NOT NULL,
                metric_value DECIMAL(10,4) NOT NULL,
                metric_unit VARCHAR(20) DEFAULT 'ms',
                context_data JSON,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_metric_type (metric_type),
                INDEX idx_recorded_at (recorded_at)
            )
        ");

        // Error logs table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS error_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_level VARCHAR(20) NOT NULL,
                error_message TEXT NOT NULL,
                error_file VARCHAR(255),
                error_line INT,
                error_context JSON,
                user_id INT DEFAULT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                request_uri VARCHAR(500),
                request_method VARCHAR(10),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_error_level (error_level),
                INDEX idx_created_at (created_at),
                INDEX idx_user_id (user_id)
            )
        ");

        // API performance logs
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS api_performance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                endpoint VARCHAR(255) NOT NULL,
                method VARCHAR(10) NOT NULL,
                response_time DECIMAL(8,4) NOT NULL,
                response_code INT NOT NULL,
                request_size INT DEFAULT 0,
                response_size INT DEFAULT 0,
                user_id INT DEFAULT NULL,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_endpoint (endpoint),
                INDEX idx_created_at (created_at),
                INDEX idx_response_time (response_time)
            )
        ");

        // User activity logs
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS user_activity (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                activity_type VARCHAR(50) NOT NULL,
                activity_description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                session_id VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_activity_type (activity_type),
                INDEX idx_created_at (created_at)
            )
        ");
    }

    /**
     * Setup advanced error handling
     */
    private function setupErrorHandling() {
        $errorHandler = '
<?php
/**
 * Advanced Error Handling and Logging System
 */

class ErrorHandler {
    private static $pdo;
    private static $logFile;

    public static function initialize($pdo, $logFile = "logs/errors.log") {
        self::$pdo = $pdo;
        self::$logFile = $logFile;

        // Set error reporting
        error_reporting(E_ALL);
        ini_set("display_errors", 0);
        ini_set("log_errors", 1);
        ini_set("error_log", $logFile);

        // Register error handlers
        set_error_handler([self::class, "handleError"]);
        set_exception_handler([self::class, "handleException"]);
        register_shutdown_function([self::class, "handleShutdown"]);
    }

    public static function handleError($errno, $errstr, $errfile, $errline) {
        $errorTypes = [
            E_ERROR => "E_ERROR",
            E_WARNING => "E_WARNING",
            E_PARSE => "E_PARSE",
            E_NOTICE => "E_NOTICE",
            E_CORE_ERROR => "E_CORE_ERROR",
            E_CORE_WARNING => "E_CORE_WARNING",
            E_COMPILE_ERROR => "E_COMPILE_ERROR",
            E_COMPILE_WARNING => "E_COMPILE_WARNING",
            E_USER_ERROR => "E_USER_ERROR",
            E_USER_WARNING => "E_USER_WARNING",
            E_USER_NOTICE => "E_USER_NOTICE",
            E_STRICT => "E_STRICT",
            E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
            E_DEPRECATED => "E_DEPRECATED",
            E_USER_DEPRECATED => "E_USER_DEPRECATED"
        ];

        $errorType = $errorTypes[$errno] ?? "UNKNOWN";

        self::logError($errorType, $errstr, $errfile, $errline, debug_backtrace());

        // Don\'t execute PHP internal error handler
        return true;
    }

    public static function handleException($exception) {
        self::logError(
            "EXCEPTION",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTrace()
        );

        self::displayErrorPage($exception);
    }

    public static function handleShutdown() {
        $error = error_get_last();
        if ($error !== null) {
            self::logError(
                "FATAL_ERROR",
                $error["message"],
                $error["file"],
                $error["line"],
                debug_backtrace()
            );
        }
    }

    private static function logError($level, $message, $file, $line, $context) {
        $errorData = [
            "error_level" => $level,
            "error_message" => $message,
            "error_file" => $file,
            "error_line" => $line,
            "error_context" => json_encode($context),
            "user_id" => $_SESSION["admin_id"] ?? null,
            "ip_address" => $_SERVER["REMOTE_ADDR"] ?? "unknown",
            "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "",
            "request_uri" => $_SERVER["REQUEST_URI"] ?? "",
            "request_method" => $_SERVER["REQUEST_METHOD"] ?? ""
        ];

        try {
            if (self::$pdo) {
                $stmt = self::$pdo->prepare("
                    INSERT INTO error_logs
                    (error_level, error_message, error_file, error_line, error_context,
                     user_id, ip_address, user_agent, request_uri, request_method)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute(array_values($errorData));
            }
        } catch (Exception $e) {
            // Fallback to file logging
            $logMessage = sprintf(
                "[%s] %s: %s in %s:%d\nContext: %s\n\n",
                date("Y-m-d H:i:s"),
                $level,
                $message,
                $file,
                $line,
                json_encode($context)
            );
            error_log($logMessage, 3, self::$logFile);
        }
    }

    private static function displayErrorPage($exception) {
        if (!headers_sent()) {
            http_response_code(500);
            header("Content-Type: text/html; charset=utf-8");
        }

        if (getenv("APP_ENV") === "production") {
            include "templates/error_500.php";
        } else {
            echo "<h1>Application Error</h1>";
            echo "<p><strong>" . htmlspecialchars($exception->getMessage()) . "</strong></p>";
            echo "<p>File: " . htmlspecialchars($exception->getFile()) . " Line: " . $exception->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        }
        exit;
    }
}

// Initialize error handling
if (isset($pdo)) {
    ErrorHandler::initialize($pdo);
}
?>';

        file_put_contents('includes/error_handler.php', $errorHandler);
    }

    /**
     * Create health check endpoint
     */
    private function createHealthCheckEndpoint() {
        $healthCheck = '
<?php
/**
 * System Health Check Endpoint
 * Provides comprehensive system status information
 */

require_once "../config.php";

header("Content-Type: application/json");
header("Cache-Control: no-cache, no-store, must-revalidate");

$health = [
    "status" => "healthy",
    "timestamp" => date("c"),
    "checks" => []
];

// Database connectivity check
try {
    $stmt = $pdo->query("SELECT 1");
    $stmt->fetch();
    $health["checks"]["database"] = [
        "status" => "healthy",
        "message" => "Database connection successful"
    ];
} catch (Exception $e) {
    $health["checks"]["database"] = [
        "status" => "unhealthy",
        "message" => "Database connection failed: " . $e->getMessage()
    ];
    $health["status"] = "unhealthy";
}

// File system permissions check
$writableDirs = ["uploads", "backups", "logs", "cache"];
foreach ($writableDirs as $dir) {
    $fullPath = "../" . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }

    if (is_writable($fullPath)) {
        $health["checks"]["filesystem_" . $dir] = [
            "status" => "healthy",
            "message" => "Directory $dir is writable"
        ];
    } else {
        $health["checks"]["filesystem_" . $dir] = [
            "status" => "unhealthy",
            "message" => "Directory $dir is not writable"
        ];
        $health["status"] = "unhealthy";
    }
}

// PHP configuration check
$requiredExtensions = ["pdo", "pdo_mysql", "gd", "mbstring", "json"];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        $health["checks"]["php_extension_" . $ext] = [
            "status" => "healthy",
            "message" => "PHP extension $ext is loaded"
        ];
    } else {
        $health["checks"]["php_extension_" . $ext] = [
            "status" => "unhealthy",
            "message" => "PHP extension $ext is not loaded"
        ];
        $health["status"] = "unhealthy";
    }
}

// Memory usage check
$memoryUsage = memory_get_peak_usage(true) / 1024 / 1024;
$memoryLimit = ini_get("memory_limit");
$memoryLimitBytes = return_bytes($memoryLimit);

if ($memoryUsage < $memoryLimitBytes * 0.8) {
    $health["checks"]["memory"] = [
        "status" => "healthy",
        "message" => sprintf("Memory usage: %.2f MB / %s", $memoryUsage, $memoryLimit)
    ];
} else {
    $health["checks"]["memory"] = [
        "status" => "warning",
        "message" => sprintf("High memory usage: %.2f MB / %s", $memoryUsage, $memoryLimit)
    ];
}

// System load check (if available)
if (function_exists("sys_getloadavg")) {
    $load = sys_getloadavg();
    $cpuCores = 1; // Default for single core

    if (is_readable("/proc/cpuinfo")) {
        $cpuinfo = file_get_contents("/proc/cpuinfo");
        $cpuCores = substr_count($cpuinfo, "processor");
    }

    $loadPercentage = ($load[0] / $cpuCores) * 100;

    if ($loadPercentage < 70) {
        $health["checks"]["system_load"] = [
            "status" => "healthy",
            "message" => sprintf("System load: %.2f%% (%d cores)", $loadPercentage, $cpuCores)
        ];
    } else {
        $health["checks"]["system_load"] = [
            "status" => "warning",
            "message" => sprintf("High system load: %.2f%% (%d cores)", $loadPercentage, $cpuCores)
        ];
    }
}

// Database performance check
try {
    $start = microtime(true);
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $result = $stmt->fetch();
    $queryTime = (microtime(true) - $start) * 1000;

    if ($queryTime < 100) { // Less than 100ms
        $health["checks"]["database_performance"] = [
            "status" => "healthy",
            "message" => sprintf("Database query time: %.2f ms (%d records)", $queryTime, $result["total"])
        ];
    } else {
        $health["checks"]["database_performance"] = [
            "status" => "warning",
            "message" => sprintf("Slow database query: %.2f ms (%d records)", $queryTime, $result["total"])
        ];
    }
} catch (Exception $e) {
    $health["checks"]["database_performance"] = [
        "status" => "unhealthy",
        "message" => "Database performance check failed: " . $e->getMessage()
    ];
}

// Disk space check
$diskFree = disk_free_space("/");
$diskTotal = disk_total_space("/");
$diskUsedPercentage = (($diskTotal - $diskFree) / $diskTotal) * 100;

if ($diskUsedPercentage < 90) {
    $health["checks"]["disk_space"] = [
        "status" => "healthy",
        "message" => sprintf("Disk usage: %.1f%% (%.2f GB free)", $diskUsedPercentage, $diskFree / 1024 / 1024 / 1024)
    ];
} else {
    $health["checks"]["disk_space"] = [
        "status" => "warning",
        "message" => sprintf("Low disk space: %.1f%% (%.2f GB free)", $diskUsedPercentage, $diskFree / 1024 / 1024 / 1024)
    ];
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case "g":
            $val *= 1024 * 1024 * 1024;
            break;
        case "m":
            $val *= 1024 * 1024;
            break;
        case "k":
            $val *= 1024;
            break;
    }
    return $val;
}

echo json_encode($health, JSON_PRETTY_PRINT);
?>';

        // Create health directory if it doesn't exist
        if (!is_dir('health')) {
            mkdir('health', 0755, true);
        }

        file_put_contents('health/check.php', $healthCheck);
    }

    /**
     * Setup performance monitoring
     */
    private function setupPerformanceMonitoring() {
        $performanceMonitor = '
<?php
/**
 * Performance Monitoring System
 */

class PerformanceMonitor {
    private static $pdo;
    private static $startTime;
    private static $memoryStart;

    public static function initialize($pdo) {
        self::$pdo = $pdo;
        self::$startTime = microtime(true);
        self::$memoryStart = memory_get_usage(true);
    }

    public static function startRequest() {
        self::$startTime = microtime(true);
        self::$memoryStart = memory_get_usage(true);
    }

    public static function endRequest($endpoint = null) {
        if (!self::$startTime) return;

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $executionTime = ($endTime - self::$startTime) * 1000; // Convert to milliseconds
        $memoryUsed = ($endMemory - self::$memoryStart) / 1024 / 1024; // Convert to MB

        // Log performance metrics
        self::logMetric("page_load_time", $executionTime, "ms", [
            "endpoint" => $endpoint ?? $_SERVER["REQUEST_URI"],
            "method" => $_SERVER["REQUEST_METHOD"],
            "user_id" => $_SESSION["admin_id"] ?? null
        ]);

        self::logMetric("memory_usage", $memoryUsed, "MB", [
            "endpoint" => $endpoint ?? $_SERVER["REQUEST_URI"],
            "peak_memory" => memory_get_peak_usage(true) / 1024 / 1024
        ]);

        // Log slow requests
        if ($executionTime > 1000) { // More than 1 second
            self::logSlowRequest($endpoint ?? $_SERVER["REQUEST_URI"], $executionTime, $memoryUsed);
        }
    }

    public static function logAPIPerformance($endpoint, $method, $responseTime, $responseCode, $requestSize = 0, $responseSize = 0) {
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO api_performance
                (endpoint, method, response_time, response_code, request_size, response_size, user_id, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $endpoint,
                $method,
                $responseTime,
                $responseCode,
                $requestSize,
                $responseSize,
                $_SESSION["admin_id"] ?? null,
                $_SERVER["REMOTE_ADDR"] ?? "unknown"
            ]);
        } catch (Exception $e) {
            error_log("API performance logging failed: " . $e->getMessage());
        }
    }

    public static function logUserActivity($activityType, $description = "") {
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO user_activity
                (user_id, activity_type, activity_description, ip_address, user_agent, session_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION["admin_id"] ?? null,
                $activityType,
                $description,
                $_SERVER["REMOTE_ADDR"] ?? "unknown",
                $_SERVER["HTTP_USER_AGENT"] ?? "",
                session_id()
            ]);
        } catch (Exception $e) {
            error_log("User activity logging failed: " . $e->getMessage());
        }
    }

    private static function logMetric($type, $value, $unit, $context = []) {
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO system_metrics
                (metric_type, metric_value, metric_unit, context_data)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $type,
                $value,
                $unit,
                json_encode($context)
            ]);
        } catch (Exception $e) {
            error_log("Performance metric logging failed: " . $e->getMessage());
        }
    }

    private static function logSlowRequest($endpoint, $executionTime, $memoryUsed) {
        $logMessage = sprintf(
            "[%s] SLOW REQUEST: %s took %.2f ms, used %.2f MB memory\n",
            date("Y-m-d H:i:s"),
            $endpoint,
            $executionTime,
            $memoryUsed
        );
        error_log($logMessage, 3, "logs/slow_requests.log");
    }

    public static function getPerformanceStats($hours = 24) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT
                    metric_type,
                    AVG(metric_value) as avg_value,
                    MIN(metric_value) as min_value,
                    MAX(metric_value) as max_value,
                    COUNT(*) as count
                FROM system_metrics
                WHERE recorded_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY metric_type
            ");
            $stmt->execute([$hours]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public static function getErrorStats($hours = 24) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT
                    error_level,
                    COUNT(*) as count
                FROM error_logs
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY error_level
                ORDER BY count DESC
            ");
            $stmt->execute([$hours]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

// Initialize performance monitoring
if (isset($pdo)) {
    PerformanceMonitor::initialize($pdo);
}
?>';

        file_put_contents('includes/performance_monitor.php', $performanceMonitor);
    }

    /**
     * Create log rotation system
     */
    private function createLogRotation() {
        $logRotation = '
<?php
/**
 * Log Rotation and Maintenance System
 */

class LogRotator {
    private $logDir;
    private $maxFileSize; // 10MB
    private $maxFiles;

    public function __construct($logDir = "logs/", $maxFileSize = 10485760, $maxFiles = 5) {
        $this->logDir = $logDir;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function rotateLogs() {
        $logFiles = glob($this->logDir . "*.log");

        foreach ($logFiles as $logFile) {
            if (filesize($logFile) > $this->maxFileSize) {
                $this->rotateFile($logFile);
            }
        }

        $this->cleanupOldLogs();
    }

    private function rotateFile($filePath) {
        $fileName = basename($filePath);
        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
        $fileBase = pathinfo($fileName, PATHINFO_FILENAME);

        // Move current log to .1
        $newFile = $this->logDir . $fileBase . ".1." . $fileExt;
        if (file_exists($newFile)) {
            unlink($newFile); // Remove old .1 if exists
        }
        rename($filePath, $newFile);

        // Rotate existing numbered logs
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $currentFile = $this->logDir . $fileBase . "." . $i . "." . $fileExt;
            $nextFile = $this->logDir . $fileBase . "." . ($i + 1) . "." . $fileExt;

            if (file_exists($currentFile)) {
                if ($i + 1 > $this->maxFiles) {
                    unlink($currentFile);
                } else {
                    rename($currentFile, $nextFile);
                }
            }
        }
    }

    private function cleanupOldLogs() {
        $logFiles = glob($this->logDir . "*.log*");

        foreach ($logFiles as $file) {
            $fileName = basename($file);
            if (preg_match("/\.(\d+)\.log$/", $fileName, $matches)) {
                $number = (int)$matches[1];
                if ($number > $this->maxFiles) {
                    unlink($file);
                }
            }
        }
    }

    public function compressOldLogs() {
        $logFiles = glob($this->logDir . "*.log.*");

        foreach ($logFiles as $file) {
            if (filesize($file) > $this->maxFileSize / 2) {
                $gzFile = $file . ".gz";
                if (!file_exists($gzFile)) {
                    $content = file_get_contents($file);
                    file_put_contents("compress.zlib://" . $gzFile, $content);
                    unlink($file);
                }
            }
        }
    }

    public function getLogStats() {
        $stats = [];
        $logFiles = glob($this->logDir . "*.log*");

        foreach ($logFiles as $file) {
            $stats[] = [
                "file" => basename($file),
                "size" => filesize($file),
                "modified" => date("Y-m-d H:i:s", filemtime($file))
            ];
        }

        return $stats;
    }
}

// Auto-rotate logs daily via cron or manual call
function performLogMaintenance() {
    $rotator = new LogRotator();
    $rotator->rotateLogs();
    $rotator->compressOldLogs();

    // Clean up old database logs (older than 90 days)
    global $pdo;
    if (isset($pdo)) {
        try {
            $pdo->exec("DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $pdo->exec("DELETE FROM system_metrics WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $pdo->exec("DELETE FROM api_performance WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $pdo->exec("DELETE FROM user_activity WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        } catch (Exception $e) {
            error_log("Database log cleanup failed: " . $e->getMessage());
        }
    }
}
?>';

        file_put_contents('includes/log_rotator.php', $logRotation);
    }

    /**
     * Get monitoring system report
     */
    public function getReport() {
        return [
            "monitoring_tables" => "System monitoring tables created",
            "error_handling" => "Advanced error handling implemented",
            "health_check" => "Health check endpoint created at /health/check.php",
            "performance_monitoring" => "Performance monitoring system enabled",
            "log_rotation" => "Log rotation and maintenance system implemented",
            "timestamp" => date("Y-m-d H:i:s")
        ];
    }
}

// Run monitoring initialization if called directly
if (basename(__FILE__) === basename($_SERVER["PHP_SELF"])) {
    if (!isset($_SESSION["admin_id"])) {
        die("Admin access required");
    }

    $monitor = new SystemMonitor($pdo);
    $monitor->initializeMonitoring();

    $report = $monitor->getReport();

    header("Content-Type: application/json");
    echo json_encode([
        "success" => true,
        "message" => "Monitoring system initialized successfully!",
        "report" => $report
    ]);
}
?>