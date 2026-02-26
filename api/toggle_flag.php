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
    
    // First get current flag status with secure table selection
    if ($table === 'students') {
        $stmt = $pdo->prepare("SELECT flagged FROM students WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT flagged FROM instrument_registrations WHERE id = ?");
    }
    $stmt->execute([$student_id]);
    $current_flag = $stmt->fetchColumn();
    
    if ($current_flag === false) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    // Toggle flag status
    $new_flag = $current_flag ? 0 : 1;
    
    // Update flag with secure table selection
    if ($table === 'students') {
        $stmt = $pdo->prepare("UPDATE students SET flagged = ? WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("UPDATE instrument_registrations SET flagged = ? WHERE id = ?");
    }
    $stmt->execute([$new_flag, $student_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Flag status updated successfully',
        'new_status' => $new_flag
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>