<?php
session_start();
require '../config.php';
require '../includes/security_helpers.php';

// Require admin authentication
requireAdminLogin();

header('Content-Type: application/json');

$student_id = (int)($_POST['student_id'] ?? $_GET['student_id'] ?? 0);

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

try {
    // Get student's current classes
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.grade,
            c.section,
            c.academic_year,
            ce.enrollment_date,
            ce.status,
            t.full_name as teacher_name
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        LEFT JOIN class_teachers ct ON c.id = ct.class_id AND ct.role = 'primary' AND ct.is_active = 1
        LEFT JOIN teachers t ON ct.teacher_id = t.id
        WHERE ce.student_id = ? 
        ORDER BY ce.enrollment_date DESC
    ");
    $stmt->execute([$student_id]);
    $classes = $stmt->fetchAll();
    
    // Get available classes for transfer (same grade)
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.grade, c.section, c.academic_year,
               COUNT(ce.id) as enrolled_count, c.capacity
        FROM classes c
        LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
        WHERE c.grade = (SELECT current_grade FROM students WHERE id = ?)
        GROUP BY c.id
        HAVING c.capacity IS NULL OR COUNT(ce.id) < c.capacity
        ORDER BY c.section
    ");
    $stmt->execute([$student_id]);
    $available_classes = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'available_classes' => $available_classes
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching student classes: ' . $e->getMessage()]);
}
?>