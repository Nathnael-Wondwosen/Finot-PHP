<?php
/**
 * Admin API to set registration status active/inactive
 * POST: type (youth|instrument|children), active (1|0)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/registration_settings.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$type = isset($_POST['type']) ? strtolower(trim($_POST['type'])) : '';
$active = isset($_POST['active']) ? (int)$_POST['active'] : -1;
$allowed = ['youth','instrument','children'];
if (!in_array($type, $allowed, true) || ($active !== 0 && $active !== 1)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $ok = set_registration_status($type, $active === 1, $pdo);
    echo json_encode(['success' => (bool)$ok, 'type' => $type, 'active' => (int)$active]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
