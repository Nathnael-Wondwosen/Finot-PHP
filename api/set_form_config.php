<?php
/**
 * Admin API to set form field configuration
 * POST: type (youth|instrument|children), fields (JSON array of {field_key,label,placeholder,required,sort_order})
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/form_config.php';
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
$fieldsJson = $_POST['fields'] ?? '';
$allowed = ['youth','instrument','children'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

try {
    $fields = json_decode($fieldsJson, true);
    if (!is_array($fields)) {
        throw new Exception('Invalid fields payload');
    }
    $ok = set_form_config($type, $fields, $pdo);
    echo json_encode(['success' => (bool)$ok]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
