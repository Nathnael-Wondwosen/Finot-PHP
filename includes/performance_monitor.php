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
        self::logMetric('page_load_time', $executionTime, 'ms', [
            'endpoint' => $endpoint ?? $_SERVER['REQUEST_URI'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'user_id' => $_SESSION['admin_id'] ?? null
        ]);

        self::logMetric('memory_usage', $memoryUsed, 'MB', [
            'endpoint' => $endpoint ?? $_SERVER['REQUEST_URI'],
            'peak_memory' => memory_get_peak_usage(true) / 1024 / 1024
        ]);

        // Log slow requests
        if ($executionTime > 1000) { // More than 1 second
            self::logSlowRequest($endpoint ?? $_SERVER['REQUEST_URI'], $executionTime, $memoryUsed);
        }
    }

    public static function logAPIPerformance($endpoint, $method, $responseTime, $responseCode, $requestSize = 0, $responseSize = 0) {
        try {
            $stmt = self::$pdo->prepare('
                INSERT INTO api_performance
                (endpoint, method, response_time, response_code, request_size, response_size, user_id, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $endpoint,
                $method,
                $responseTime,
                $responseCode,
                $requestSize,
                $responseSize,
                $_SESSION['admin_id'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log('API performance logging failed: ' . $e->getMessage());
        }
    }

    public static function logUserActivity($activityType, $description = '') {
        try {
            $stmt = self::$pdo->prepare('
                INSERT INTO user_activity
                (user_id, activity_type, activity_description, ip_address, user_agent, session_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $_SESSION['admin_id'] ?? null,
                $activityType,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                session_id()
            ]);
        } catch (Exception $e) {
            error_log('User activity logging failed: ' . $e->getMessage());
        }
    }

    private static function logMetric($type, $value, $unit, $context = []) {
        try {
            $stmt = self::$pdo->prepare('
                INSERT INTO system_metrics
                (metric_type, metric_value, metric_unit, context_data)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([
                $type,
                $value,
                $unit,
                json_encode($context)
            ]);
        } catch (Exception $e) {
            error_log('Performance metric logging failed: ' . $e->getMessage());
        }
    }

    private static function logSlowRequest($endpoint, $executionTime, $memoryUsed) {
        $logMessage = sprintf(
            '[%s] SLOW REQUEST: %s took %.2f ms, used %.2f MB memory\n',
            date('Y-m-d H:i:s'),
            $endpoint,
            $executionTime,
            $memoryUsed
        );
        error_log($logMessage, 3, 'logs/slow_requests.log');
    }

    public static function getPerformanceStats($hours = 24) {
        try {
            $stmt = self::$pdo->prepare('
                SELECT
                    metric_type,
                    AVG(metric_value) as avg_value,
                    MIN(metric_value) as min_value,
                    MAX(metric_value) as max_value,
                    COUNT(*) as count
                FROM system_metrics
                WHERE recorded_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY metric_type
            ');
            $stmt->execute([$hours]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public static function getErrorStats($hours = 24) {
        try {
            $stmt = self::$pdo->prepare('
                SELECT
                    error_level,
                    COUNT(*) as count
                FROM error_logs
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY error_level
                ORDER BY count DESC
            ');
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
?>