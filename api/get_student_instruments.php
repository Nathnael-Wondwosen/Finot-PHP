<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_name = isset($_GET['student_name']) ? trim($_GET['student_name']) : '';

if (empty($student_name)) {
    echo json_encode(['success' => false, 'message' => 'Student name is required']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all instrument registrations for this student
    $stmt = $pdo->prepare("
        SELECT ir.*, 
               s.id as student_id,
               s.current_grade,
               s.phone_number as s_phone_number,
               s.photo_path as s_photo_path
        FROM instrument_registrations ir 
        LEFT JOIN students s ON LOWER(TRIM(ir.full_name)) = LOWER(TRIM(s.full_name))
        WHERE LOWER(TRIM(ir.full_name)) = LOWER(TRIM(?))
        ORDER BY ir.created_at DESC
    ");
    $stmt->execute([$student_name]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($registrations)) {
        echo json_encode(['success' => false, 'message' => 'No registrations found for this student']);
        exit;
    }
    
    // Organize the data
    $student_data = [
        'full_name' => $registrations[0]['full_name'],
        'christian_name' => $registrations[0]['christian_name'],
        'gender' => $registrations[0]['gender'],
        'phone_number' => $registrations[0]['phone_number'] ?: $registrations[0]['s_phone_number'],
        'photo_path' => $registrations[0]['person_photo_path'] ?: $registrations[0]['s_photo_path'],
        'student_id' => $registrations[0]['student_id'],
        'current_grade' => $registrations[0]['current_grade'],
        'birth_info' => [
            'year' => $registrations[0]['birth_year_et'],
            'month' => $registrations[0]['birth_month_et'],
            'day' => $registrations[0]['birth_day_et']
        ],
        'instruments' => [],
        'registration_count' => count($registrations),
        'is_linked' => !empty($registrations[0]['student_id'])
    ];
    
    // Add instruments
    foreach ($registrations as $reg) {
        $student_data['instruments'][] = [
            'id' => $reg['id'],
            'instrument' => $reg['instrument'],
            'created_at' => $reg['created_at'],
            'flagged' => $reg['flagged'] ?? 0
        ];
    }
    
    echo json_encode(['success' => true, 'student' => $student_data]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>