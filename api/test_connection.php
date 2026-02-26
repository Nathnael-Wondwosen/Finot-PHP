<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Test database connection
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $result = $stmt->fetch();
    
    // Test sample data
    $sample_stmt = $pdo->query("SELECT id, first_name, current_grade, gender FROM students LIMIT 3");
    $samples = $sample_stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'total_students' => $result['total'],
        'sample_students' => $samples,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>