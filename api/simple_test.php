<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('API_CONTEXT', true);
require_once '../config.php';
ob_clean();

header('Content-Type: application/json');

try {
    echo json_encode([
        'success' => true,
        'message' => 'Simple API test successful',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
?>