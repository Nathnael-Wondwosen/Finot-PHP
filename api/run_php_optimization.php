<?php
/**
 * AJAX handler for PHP runtime optimization
 */

require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin login required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$changes = [];
$failed = [];

$runtimeSettings = [
    'memory_limit' => '512M',
    'max_execution_time' => 300,
    'realpath_cache_size' => '4096K',
    'realpath_cache_ttl' => 600
];

foreach ($runtimeSettings as $k => $v) {
    $ok = @ini_set($k, $v);
    if ($ok !== false) {
        $changes[] = $k;
    } else {
        $failed[] = $k;
    }
}

$opcache = [
    'available' => function_exists('opcache_get_status'),
    'enabled' => false
];

if ($opcache['available']) {
    $st = @opcache_get_status();
    $opcache['enabled'] = $st && !empty($st['opcache_enabled']);
}

echo json_encode([
    'success' => true,
    'changed' => $changes,
    'failed' => $failed,
    'opcache' => $opcache
]);
