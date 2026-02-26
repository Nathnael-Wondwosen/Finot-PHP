<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['student_ids']) || !isset($input['table']) || !is_array($input['student_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
    exit;
}

$student_ids = $input['student_ids'];
$table = $input['table'];

// Validate table name for security
$allowed_tables = ['students', 'instrument_registrations'];
if (!in_array($table, $allowed_tables)) {
    echo json_encode(['success' => false, 'message' => 'Invalid table']);
    exit;
}

if (empty($student_ids)) {
    echo json_encode(['success' => false, 'message' => 'No students selected']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
    
    // Get photo paths before deletion for cleanup
    if ($table === 'instrument_registrations') {
        $stmt = $pdo->prepare("SELECT person_photo_path FROM $table WHERE id IN ($placeholders)");
    } else {
        $stmt = $pdo->prepare("SELECT photo_path FROM $table WHERE id IN ($placeholders)");
    }
    $stmt->execute($student_ids);
    $photo_paths = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Delete the student records
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id IN ($placeholders)");
    $stmt->execute($student_ids);
    
    $deleted_count = $stmt->rowCount();
    
    // Clean up photo files
    foreach ($photo_paths as $photo_path) {
        if ($photo_path && file_exists($photo_path)) {
            unlink($photo_path);
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully deleted $deleted_count students"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>