<?php
// delete_instrument_registration.php
require 'config.php';
header('Content-Type: application/json');

$reg_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($reg_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid registration ID']);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM instrument_registrations WHERE id = ?');
    $stmt->execute([$reg_id]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
