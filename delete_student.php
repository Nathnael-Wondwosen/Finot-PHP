<?php
// delete_student.php
session_start();
require_once 'config.php';
requireAdminLogin();
header('Content-Type: application/json');

$ids = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (is_array($payload['ids'] ?? null)) {
        foreach ($payload['ids'] as $v) {
            if (is_numeric($v)) $ids[] = (int)$v;
        }
    }
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $ids[] = (int)$_GET['id'];
}

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid student ID(s)']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch photo paths before deletion
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('SELECT id, photo_path FROM students WHERE id IN ('.$in.')');
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $idToPhoto = [];
    foreach ($rows as $r) { $idToPhoto[(int)$r['id']] = $r['photo_path'] ?? ''; }

    // Delete instrument registrations
    $stmt = $pdo->prepare('DELETE FROM instrument_registrations WHERE student_id IN ('.$in.')');
    $stmt->execute($ids);

    // Delete parent info
    $stmt = $pdo->prepare('DELETE FROM parents WHERE student_id IN ('.$in.')');
    $stmt->execute($ids);

    // Delete students
    $stmt = $pdo->prepare('DELETE FROM students WHERE id IN ('.$in.')');
    $stmt->execute($ids);

    $pdo->commit();

    // Attempt to delete local photo files
    foreach ($ids as $sid) {
        $photo_path = $idToPhoto[$sid] ?? '';
        if (!$photo_path) continue;
        $isRemote = preg_match('/^https?:\/\//i', $photo_path) === 1 || (function_exists('str_starts_with') ? str_starts_with($photo_path, '//') : (substr($photo_path,0,2)==='//'));
        if ($isRemote) continue;
        $baseDir = __DIR__ . DIRECTORY_SEPARATOR;
        $relative = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $photo_path), DIRECTORY_SEPARATOR);
        $absolutePath = $baseDir . $relative;
        $uploadsDir = $baseDir . 'uploads' . DIRECTORY_SEPARATOR;
        $absNorm = realpath($absolutePath);
        $uploadsNorm = realpath($uploadsDir) ?: $uploadsDir;
        $startsWith = function($haystack, $needle){ return strpos($haystack, $needle) === 0; };
        if ($absNorm && $uploadsNorm && $startsWith($absNorm, rtrim($uploadsNorm, DIRECTORY_SEPARATOR))) {
            if (is_file($absNorm)) { @unlink($absNorm); }
        } else if ($startsWith($absolutePath, $uploadsDir) && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
