<?php
/**
 * Admin API to get form field configuration
 * GET: type (youth|instrument|children)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/form_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$allowed = ['youth','instrument','children'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

try {
    $rows = get_form_config($type, $pdo);
    echo json_encode(['success' => true, 'fields' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
