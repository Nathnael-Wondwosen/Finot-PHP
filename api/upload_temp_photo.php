<?php
// api/upload_temp_photo.php
// Accepts a multipart/form-data file field named "photo" and stores it temporarily under uploads/tmp

require_once '../config.php';

header('Content-Type: application/json');

// Admin not required for public registration uploads, but rate limit basics
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['photo']) || !isset($_FILES['photo']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['photo'];
$maxSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

// Basic checks
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Upload error']);
    exit;
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File too large']);
    exit;
}

// Verify mime using finfo
$fi = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($fi, $file['tmp_name']);
finfo_close($fi);
if (!isset($allowedTypes[$mime])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit;
}

$tmpRoot = realpath(__DIR__ . '/../uploads');
if ($tmpRoot === false) {
    $tmpRoot = __DIR__ . '/../uploads';
}
$tmpDir = $tmpRoot . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
}

$ext = $allowedTypes[$mime];
$key = 'itmp_' . uniqid() . '.' . $ext;
$dest = $tmpDir . '/' . $key;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to store temporary file']);
    exit;
}

// Return key only (client stores it and posts with the form)
echo json_encode(['success' => true, 'key' => $key]);
