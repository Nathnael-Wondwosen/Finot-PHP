<?php
// get_instrument_registration.php
require 'config.php';
header('Content-Type: application/json');

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$registration_id = isset($_GET['registration_id']) ? intval($_GET['registration_id']) : 0;

if ($student_id <= 0 && $registration_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

try {
    if ($registration_id > 0) {
        // Fetch by registration ID
        $stmt = $pdo->prepare("SELECT * FROM instrument_registrations WHERE id = ? LIMIT 1");
        $stmt->execute([$registration_id]);
    } else {
        // Fetch by student ID (original functionality)
        $stmt = $pdo->prepare("SELECT * FROM instrument_registrations WHERE student_id = ? LIMIT 1");
        $stmt->execute([$student_id]);
    }
    
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($info) {
        echo json_encode(['success' => true, 'info' => $info]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Registration not found.']);
    }
} catch (Exception $e) {
    error_log("Get instrument registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
