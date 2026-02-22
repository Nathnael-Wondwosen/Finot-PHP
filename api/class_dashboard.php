<?php
session_start();
require '../config.php';
require '../includes/security_helpers.php';

// Require admin authentication
requireAdminLogin();

header('Content-Type: application/json');

try {
    // Get class statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_classes,
            SUM(CASE WHEN c.academic_year = YEAR(CURDATE()) THEN 1 ELSE 0 END) as current_year_classes
        FROM classes c
    ");
    $class_stats = $stmt->fetch();
    
    // Get student enrollment statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT ce.student_id) as total_enrolled_students,
            COUNT(DISTINCT CASE WHEN ce.status = 'active' THEN ce.student_id END) as active_students,
            COUNT(ce.id) as total_enrollments
        FROM class_enrollments ce
    ");
    $enrollment_stats = $stmt->fetch();
    
    // Get teacher statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_teachers,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_teachers
        FROM teachers
    ");
    $teacher_stats = $stmt->fetch();
    
    // Get class capacity utilization
    $stmt = $pdo->query("
        SELECT 
            c.name,
            c.grade,
            c.section,
            c.capacity,
            COUNT(ce.id) as enrolled_count,
            ROUND((COUNT(ce.id) / c.capacity) * 100, 1) as utilization_percent
        FROM classes c
        LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
        WHERE c.capacity IS NOT NULL
        GROUP BY c.id
        ORDER BY utilization_percent DESC
        LIMIT 5
    ");
    $capacity_utilization = $stmt->fetchAll();
    
    // Get recent enrollments
    $stmt = $pdo->query("
        SELECT 
            s.full_name as student_name,
            c.name as class_name,
            c.grade,
            ce.enrollment_date
        FROM class_enrollments ce
        JOIN students s ON ce.student_id = s.id
        JOIN classes c ON ce.class_id = c.id
        WHERE ce.status = 'active'
        ORDER BY ce.enrollment_date DESC
        LIMIT 5
    ");
    $recent_enrollments = $stmt->fetchAll();
    
    // Get class distribution by grade
    $stmt = $pdo->query("
        SELECT 
            c.grade,
            COUNT(*) as class_count,
            COUNT(ce.id) as student_count
        FROM classes c
        LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
        GROUP BY c.grade
        ORDER BY 
            CASE c.grade
                WHEN 'new' THEN 1
                WHEN '1st' THEN 2
                WHEN '2nd' THEN 3
                WHEN '3rd' THEN 4
                WHEN '4th' THEN 5
                WHEN '5th' THEN 6
                WHEN '6th' THEN 7
                WHEN '7th' THEN 8
                WHEN '8th' THEN 9
                WHEN '9th' THEN 10
                WHEN '10th' THEN 11
                WHEN '11th' THEN 12
                WHEN '12th' THEN 13
                ELSE 14
            END
    ");
    $grade_distribution = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'class_stats' => $class_stats,
        'enrollment_stats' => $enrollment_stats,
        'teacher_stats' => $teacher_stats,
        'capacity_utilization' => $capacity_utilization,
        'recent_enrollments' => $recent_enrollments,
        'grade_distribution' => $grade_distribution
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching dashboard data: ' . $e->getMessage()]);
}
?>