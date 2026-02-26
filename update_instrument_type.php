<?php
// update_instrument_type.php
require 'config.php';
header('Content-Type: application/json');

$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
$instrument = isset($_POST['instrument']) ? trim($_POST['instrument']) : '';

if (($student_id <= 0 && $registration_id <= 0) || !$instrument) {
    echo json_encode(['success' => false, 'error' => 'Invalid input.']);
    exit;
}

try {
    if ($registration_id > 0) {
        // Update by registration ID (preferred method)
        $stmt = $pdo->prepare("UPDATE instrument_registrations SET instrument = ? WHERE id = ?");
        $result = $stmt->execute([$instrument, $registration_id]);
    } else {
        // Update by student ID (legacy support)
        $stmt = $pdo->prepare("UPDATE instrument_registrations SET instrument = ? WHERE student_id = ?");
        $result = $stmt->execute([$instrument, $student_id]);
    }
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No records updated. Registration may not exist.']);
    }
} catch (Exception $e) {
    error_log("Update instrument type error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database update failed.']);
}
