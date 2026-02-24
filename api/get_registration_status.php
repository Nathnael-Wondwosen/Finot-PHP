<?php
/**
 * Admin API to get registration statuses
 * GET: optional type (youth|instrument|children); if omitted, returns all
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/registration_settings.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$types = ['youth','instrument','children'];

try {
    if ($type) {
        if (!in_array($type, $types, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            exit;
        }
        $cfg = get_registration_config($type, $pdo);
        echo json_encode(['success' => true, 'status' => [$type => [
            'active' => (int)$cfg['active'],
            'title' => $cfg['title'],
            'message' => $cfg['message']
        ]]]);
        exit;
    }
    $out = [];
    foreach ($types as $t) {
        $cfg = get_registration_config($t, $pdo);
        $out[$t] = [
            'active' => (int)$cfg['active'],
            'title' => $cfg['title'],
            'message' => $cfg['message']
        ];
    }
    echo json_encode(['success' => true, 'status' => $out]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
