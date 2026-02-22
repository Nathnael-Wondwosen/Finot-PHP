<?php
/**
 * Admin API to set registration custom title/message for closed page
 * POST: type (youth|instrument|children), title (optional), message (optional)
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
$title = isset($_POST['title']) ? trim($_POST['title']) : null;
$message = isset($_POST['message']) ? trim($_POST['message']) : null;
$allowed = ['youth','instrument','children'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

try {
    $ok = set_registration_config($type, $title, $message, $pdo);
    echo json_encode(['success' => (bool)$ok, 'type' => $type, 'title' => $title, 'message' => $message]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
