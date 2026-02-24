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
    
    // Update flagged status for all selected students
    $stmt = $pdo->prepare("UPDATE $table SET flagged = 1 WHERE id IN ($placeholders)");
    $stmt->execute($student_ids);
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully flagged {$stmt->rowCount()} students"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>