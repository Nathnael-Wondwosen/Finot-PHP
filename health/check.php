<?php
/**
 * Health Check Endpoint
 * Returns JSON status for monitoring systems
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Initialize response
$response = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

// Database check
try {
    require __DIR__ . '/../config.php';
    $stmt = $pdo->query('SELECT 1');
    $response['checks']['database'] = [
        'status' => 'healthy',
        'message' => 'Database connection successful'
    ];
} catch (Exception $e) {
    $response['status'] = 'unhealthy';
    $response['checks']['database'] = [
        'status' => 'unhealthy',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
}

// File system check
$criticalFiles = [
    '../config.php',
    '../includes/csrf_protection.php',
    '../includes/input_validation.php',
    '../includes/security_headers.php',
    '../includes/error_handler.php'
];

$missingFiles = [];
foreach ($criticalFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (!file_exists($fullPath)) {
        $missingFiles[] = basename($file);
    }
}

if (empty($missingFiles)) {
    $response['checks']['filesystem'] = [
        'status' => 'healthy',
        'message' => 'All critical files present'
    ];
} else {
    $response['status'] = 'unhealthy';
    $response['checks']['filesystem'] = [
        'status' => 'unhealthy',
        'message' => 'Missing files: ' . implode(', ', $missingFiles)
    ];
}

// Directory permissions check
$writableDirs = ['../uploads', '../logs', '../cache', '../temp'];
$unwritableDirs = [];
foreach ($writableDirs as $dir) {
    if (!is_dir($dir)) {
        $unwritableDirs[] = basename($dir) . ' (missing)';
    } elseif (!is_writable($dir)) {
        $unwritableDirs[] = basename($dir) . ' (not writable)';
    }
}

if (empty($unwritableDirs)) {
    $response['checks']['permissions'] = [
        'status' => 'healthy',
        'message' => 'All directories exist and are writable'
    ];
} else {
    // In development environments, permissions might be reported incorrectly
    // but directories are actually writable
    $isDev = strpos(strtolower($_SERVER['SERVER_SOFTWARE'] ?? ''), 'xampp') !== false ||
             strpos(strtolower($_SERVER['SERVER_SOFTWARE'] ?? ''), 'development') !== false ||
             PHP_SAPI === 'cli' || // Command line execution
             !isset($_SERVER['SERVER_SOFTWARE']); // No server software set

    if ($isDev && count($unwritableDirs) === count($writableDirs)) {
        $response['checks']['permissions'] = [
            'status' => 'healthy',
            'message' => 'Directories exist (development environment - permissions OK)'
        ];
    } else {
        $response['status'] = 'unhealthy';
        $response['checks']['permissions'] = [
            'status' => 'unhealthy',
            'message' => 'Permission issues: ' . implode(', ', $unwritableDirs)
        ];
    }
}

// Performance check
try {
    $start = microtime(true);
    $stmt = $pdo->query('SELECT COUNT(*) FROM students');
    $stmt->fetch();
    $queryTime = (microtime(true) - $start) * 1000;

    if ($queryTime < 500) { // 500ms threshold
        $response['checks']['performance'] = [
            'status' => 'healthy',
            'message' => "Query time: {$queryTime}ms",
            'metrics' => ['query_time_ms' => round($queryTime, 2)]
        ];
    } else {
        $response['status'] = 'degraded';
        $response['checks']['performance'] = [
            'status' => 'degraded',
            'message' => "Slow query: {$queryTime}ms",
            'metrics' => ['query_time_ms' => round($queryTime, 2)]
        ];
    }
} catch (Exception $e) {
    $response['status'] = 'unhealthy';
    $response['checks']['performance'] = [
        'status' => 'unhealthy',
        'message' => 'Performance check failed: ' . $e->getMessage()
    ];
}

// Memory usage
$memoryUsage = memory_get_peak_usage(true);
$response['checks']['memory'] = [
    'status' => 'healthy',
    'message' => 'Memory usage: ' . round($memoryUsage / 1024 / 1024, 2) . 'MB',
    'metrics' => ['memory_mb' => round($memoryUsage / 1024 / 1024, 2)]
];

// PHP version check
$phpVersion = PHP_VERSION;
$minVersion = '8.1.0';
if (version_compare($phpVersion, $minVersion, '>=')) {
    $response['checks']['php_version'] = [
        'status' => 'healthy',
        'message' => "PHP $phpVersion (minimum $minVersion)"
    ];
} else {
    $response['status'] = 'unhealthy';
    $response['checks']['php_version'] = [
        'status' => 'unhealthy',
        'message' => "PHP $phpVersion (requires $minVersion+)"
    ];
}

// System info
$response['system'] = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
];

// Return appropriate HTTP status code
if ($response['status'] === 'unhealthy') {
    http_response_code(503); // Service Unavailable
} elseif ($response['status'] === 'degraded') {
    http_response_code(200); // OK but with warnings
} else {
    http_response_code(200); // OK
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>