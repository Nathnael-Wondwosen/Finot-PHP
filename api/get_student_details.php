<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !isset($_GET['table'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$student_id = $_GET['id'];
$table = $_GET['table'];

// Validate table name for security
$allowed_tables = ['students', 'instrument_registrations'];
if (!in_array($table, $allowed_tables)) {
    echo json_encode(['success' => false, 'message' => 'Invalid table']);
    exit;
}

try {
    // Use existing PDO connection from config.php
    
    if ($table === 'instrument_registrations') {
        $stmt = $pdo->prepare("
            SELECT id, full_name, phone_number, gender, age, education_level, 
                   instrument, photo_path, registration_date, flagged
            FROM instrument_registrations 
            WHERE id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT id, full_name, phone_number, gender, age, education_level, 
                   photo_path, registration_date, flagged
            FROM students 
            WHERE id = ?
        ");
    }
    
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        // Format registration date
        if ($student['registration_date']) {
            $student['registration_date'] = date('M j, Y', strtotime($student['registration_date']));
        }
        
        echo json_encode(['success' => true, 'student' => $student]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>