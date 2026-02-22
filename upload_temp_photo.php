<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No photo uploaded']);
        exit;
    }

    $f = $_FILES['photo'];
    $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png'];
    if (!isset($allowed[$f['type']])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    if ($f['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File too large']);
        exit;
    }

    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    $tmpDir = $baseDir . DIRECTORY_SEPARATOR . 'tmp';
    if (!is_dir($baseDir)) {
        if (!mkdir($baseDir, 0775, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create uploads directory']);
            exit;
        }
    }
    if (!is_dir($tmpDir)) {
        if (!mkdir($tmpDir, 0775, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create temp directory']);
            exit;
        }
    }

    $ext = $allowed[$f['type']];
    $key = uniqid('ytmp_', true);
    $fileName = $key . $ext;
    $dest = $tmpDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save temp photo']);
        exit;
    }

    $url = 'uploads/tmp/' . $fileName;

    echo json_encode(['success' => true, 'temp_key' => $fileName, 'url' => $url]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
