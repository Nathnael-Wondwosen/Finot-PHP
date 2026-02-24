<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['student_id']) || !isset($input['table'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$student_id = $input['student_id'];
$table = $input['table'];

// Validate table name for security
$allowed_tables = ['students', 'instrument_registrations'];
if (!in_array($table, $allowed_tables)) {
    echo json_encode(['success' => false, 'message' => 'Invalid table']);
    exit;
}

try {
    // Use existing PDO connection from config.php
    
    // Get photo path before deletion for cleanup
    if ($table === 'instrument_registrations') {
        $stmt = $pdo->prepare("SELECT person_photo_path FROM $table WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT photo_path FROM $table WHERE id = ?");
    }
    $stmt->execute([$student_id]);
    $photo_path = $stmt->fetchColumn();
    
    // Delete the student record
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->execute([$student_id]);
    
    if ($stmt->rowCount() > 0) {
        // Clean up photo file if it exists
        if ($photo_path && file_exists($photo_path)) {
            unlink($photo_path);
        }
        
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>