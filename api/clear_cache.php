<?php
/**
 * AJAX handler to clear caches
 */

require_once '../config.php';
require_once '../includes/cache.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin login required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    $before = $cache->getStats();
    $ok = cache_clear();
    $after = $cache->getStats();

    echo json_encode([
        'success' => (bool)$ok,
        'message' => $ok ? 'Cache cleared successfully' : 'Failed to clear cache',
        'before' => $before,
        'after' => $after
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
