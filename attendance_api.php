<?php
// Simple Attendance API for Student Registration System
// This is a standalone file that can be easily deployed to cPanel
// No modifications needed to existing project files

// Set headers for API responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Use the existing config.php for database connection
require_once 'config.php';

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Route the request based on the endpoint
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

switch ($endpoint) {
    case 'students':
        handleStudents($method);
        break;
    case 'attendance':
        handleAttendance($method);
        break;
    case 'student':
        handleStudentDetail($method);
        break;
    case 'instrument_students':
        handleInstrumentStudents($method);
        break;
    case 'attendance_report':
        handleAttendanceReport($method);
        break;
    case 'student_attendance':
        handleStudentAttendance($method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found. Available endpoints: students, attendance, student, instrument_students, attendance_report, student_attendance']);
}

// Handle getting all students for attendance
function handleStudents($method) {
    global $pdo;
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    try {
        // Get all students (both regular students and instrument students) with parent information
        $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.christian_name, s.gender, s.current_grade, s.photo_path, s.phone_number, s.created_at,
                              f.full_name AS father_full_name, 
                              f.phone_number AS father_phone,
                              m.full_name AS mother_full_name,
                              m.phone_number AS mother_phone,
                              g.full_name AS guardian_full_name,
                              g.phone_number AS guardian_phone,
                              'student' AS source_type
                              FROM students s
                              LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
                              LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
                              LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
                              UNION ALL
                              SELECT ir.id, ir.full_name, ir.christian_name, ir.gender, NULL as current_grade, ir.person_photo_path as photo_path, ir.phone_number, ir.created_at,
                              NULL as father_full_name, 
                              NULL as father_phone,
                              NULL as mother_full_name,
                              NULL as mother_phone,
                              NULL as guardian_full_name,
                              NULL as guardian_phone,
                              'instrument' AS source_type
                              FROM instrument_registrations ir
                              ORDER BY full_name ASC");
        $stmt->execute();
        $students = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $students,
            'count' => count($students)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch students: ' . $e->getMessage()]);
    }
}

// Handle getting specific student details
function handleStudentDetail($method) {
    global $pdo;
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $source_type = isset($_GET['source_type']) ? $_GET['source_type'] : 'student'; // 'student' or 'instrument'
    
    if (!$student_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID is required']);
        return;
    }
    
    
    try {
        if ($source_type === 'instrument') {
            // Get instrument student details
            $stmt = $pdo->prepare("SELECT ir.*, 
                                  s.id AS linked_student_id,
                                  s.full_name AS linked_student_full_name,
                                  s.christian_name AS linked_student_christian_name,
                                  s.gender AS linked_student_gender,
                                  s.current_grade AS linked_student_grade
                                  FROM instrument_registrations ir
                                  LEFT JOIN students s ON LOWER(TRIM(ir.full_name)) = LOWER(TRIM(s.full_name))
                                  WHERE ir.id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
        } else {
            // Get regular student details
            $stmt = $pdo->prepare("SELECT s.*, 
                                  f.full_name AS father_full_name, 
                                  f.phone_number AS father_phone,
                                  m.full_name AS mother_full_name,
                                  m.phone_number AS mother_phone,
                                  g.full_name AS guardian_full_name,
                                  g.phone_number AS guardian_phone
                                  FROM students s
                                  LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
                                  LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
                                  LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
                                  WHERE s.id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
        }
        
        if ($student) {
            echo json_encode([
                'success' => true,
                'data' => $student,
                'source_type' => $source_type
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Student not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch student: ' . $e->getMessage()]);
    }
}

// Handle attendance recording
function handleAttendance($method) {
    global $pdo;
    
    if ($method === 'GET') {
        // Get attendance records
        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
        
        try {
            if ($student_id) {
                // Get attendance for specific student
                $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND date = ?");
                $stmt->execute([$student_id, $date]);
            } else {
                // Get all attendance for the date
                $stmt = $pdo->prepare("SELECT a.*, s.full_name, s.source_type FROM attendance a 
                                      LEFT JOIN (
                                        SELECT id, full_name, 'student' as source_type FROM students
                                        UNION ALL
                                        SELECT id, full_name, 'instrument' as source_type FROM instrument_registrations
                                      ) s ON a.student_id = s.id 
                                      WHERE a.date = ? 
                                      ORDER BY s.full_name ASC");
                $stmt->execute([$date]);
            }
            
            $attendance = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $attendance,
                'date' => $date
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch attendance: ' . $e->getMessage()]);
        }
    } elseif ($method === 'POST') {
        // Record attendance
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }
        
        $student_id = isset($input['student_id']) ? (int)$input['student_id'] : 0;
        $source_type = isset($input['source_type']) ? $input['source_type'] : 'student'; // 'student' or 'instrument'
        $date = isset($input['date']) ? $input['date'] : date('Y-m-d');
        $status = isset($input['status']) ? $input['status'] : 'present'; // present, absent, late
        $notes = isset($input['notes']) ? $input['notes'] : '';
        
        if (!$student_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Student ID is required']);
            return;
        }
        
        try {
            // Check if attendance record already exists
            $checkStmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND source_type = ? AND date = ?");
            $checkStmt->execute([$student_id, $source_type, $date]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE attendance SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$status, $notes, $existing['id']]);
                $attendance_id = $existing['id'];
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO attendance (student_id, source_type, date, status, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $result = $stmt->execute([$student_id, $source_type, $date, $status, $notes]);
                $attendance_id = $pdo->lastInsertId();
            }
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Attendance recorded successfully',
                    'attendance_id' => $attendance_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to record attendance']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to record attendance: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

// Handle instrument students
function handleInstrumentStudents($method) {
    global $pdo;
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    try {
        // Get all instrument students with linked student information
        $stmt = $pdo->prepare("SELECT ir.*, 
                              s.id AS linked_student_id,
                              s.full_name AS linked_student_full_name,
                              s.christian_name AS linked_student_christian_name,
                              s.gender AS linked_student_gender,
                              s.current_grade AS linked_student_grade,
                              s.phone_number AS linked_student_phone
                              FROM instrument_registrations ir
                              LEFT JOIN students s ON LOWER(TRIM(ir.full_name)) = LOWER(TRIM(s.full_name))
                              ORDER BY ir.full_name ASC");
        $stmt->execute();
        $instrumentStudents = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $instrumentStudents,
            'count' => count($instrumentStudents)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch instrument students: ' . $e->getMessage()]);
    }
}

// Handle attendance report generation
function handleAttendanceReport($method) {
    global $pdo;
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    try {
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30; // Default to last 30 days
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime("-$days days"));
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        
        // Get attendance summary
        $stmt = $pdo->prepare("SELECT 
            COUNT(DISTINCT student_id) as total_students,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
            COUNT(*) as total_attendance_records
            FROM attendance 
            WHERE date BETWEEN ? AND ?");
        $stmt->execute([$date_from, $date_to]);
        $summary = $stmt->fetch();
        
        // Get daily attendance trend
        $stmt = $pdo->prepare("SELECT 
            date,
            COUNT(*) as total_records,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late
            FROM attendance 
            WHERE date BETWEEN ? AND ?
            GROUP BY date
            ORDER BY date DESC");
        $stmt->execute([$date_from, $date_to]);
        $daily_trend = $stmt->fetchAll();
        
        // Get most attended students
        $stmt = $pdo->prepare("SELECT 
            a.student_id,
            s.full_name,
            s.source_type,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(*) as total_attendance,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_rate
            FROM attendance a
            JOIN (
                SELECT id, full_name, 'student' as source_type FROM students
                UNION ALL
                SELECT id, full_name, 'instrument' as source_type FROM instrument_registrations
            ) s ON a.student_id = s.id
            WHERE a.date BETWEEN ? AND ?
            GROUP BY a.student_id, s.full_name, s.source_type
            ORDER BY present_count DESC
            LIMIT 20");
        $stmt->execute([$date_from, $date_to]);
        $top_students = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'daily_trend' => $daily_trend,
            'top_students' => $top_students,
            'date_range' => [
                'from' => $date_from,
                'to' => $date_to
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate attendance report: ' . $e->getMessage()]);
    }
}

// Handle individual student attendance tracking
function handleStudentAttendance($method) {
    global $pdo;
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    $source_type = isset($_GET['source_type']) ? $_GET['source_type'] : 'student';
    
    if (!$student_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID is required']);
        return;
    }
    
    try {
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30; // Default to last 30 days
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime("-$days days"));
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        
        // Get student attendance history
        $stmt = $pdo->prepare("SELECT 
            date,
            status,
            notes,
            created_at,
            updated_at
            FROM attendance 
            WHERE student_id = ? AND source_type = ? AND date BETWEEN ? AND ?
            ORDER BY date DESC");
        $stmt->execute([$student_id, $source_type, $date_from, $date_to]);
        $attendance_history = $stmt->fetchAll();
        
        // Get student attendance summary
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total_days,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
            ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_rate
            FROM attendance 
            WHERE student_id = ? AND source_type = ? AND date BETWEEN ? AND ?");
        $stmt->execute([$student_id, $source_type, $date_from, $date_to]);
        $attendance_summary = $stmt->fetch();
        
        // Get student information
        if ($source_type === 'instrument') {
            $stmt = $pdo->prepare("SELECT full_name, christian_name, gender, instrument, person_photo_path as photo_path FROM instrument_registrations WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("SELECT full_name, christian_name, gender, current_grade, photo_path FROM students WHERE id = ?");
        }
        $stmt->execute([$student_id]);
        $student_info = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'student' => $student_info,
            'summary' => $attendance_summary,
            'history' => $attendance_history,
            'date_range' => [
                'from' => $date_from,
                'to' => $date_to
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch student attendance: ' . $e->getMessage()]);
    }
}
