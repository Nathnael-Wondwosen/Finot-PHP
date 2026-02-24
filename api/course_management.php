<?php
session_start();
require '../config.php';
require '../includes/security_helpers.php';

// Require admin authentication
requireAdminLogin();

header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_courses':
            getCourses();
            break;
            
        case 'get_course_details':
            getCourseDetails();
            break;
            
        case 'create_course':
            createCourse();
            break;
            
        case 'update_course':
            updateCourse();
            break;
            
        case 'delete_course':
            deleteCourse();
            break;
            
        case 'get_teachers':
            getTeachers();
            break;
            
        case 'get_classes':
            getClasses();
            break;
            
        case 'assign_teacher_to_course':
            assignTeacherToCourse();
            break;
            
        case 'get_course_teachers':
            getCourseTeachers();
            break;
            
        case 'remove_teacher_from_course':
            removeTeacherFromCourse();
            break;
            
        case 'get_dashboard_stats':
            getDashboardStats();
            break;
            
        case 'get_teacher_assignments_summary':
            getTeacherAssignmentsSummary();
            break;
            
        case 'get_class_assignments_summary':
            getClassAssignmentsSummary();
            break;
            
        case 'get_detailed_assignments':
            getDetailedAssignments();
            break;
            
        case 'remove_assignment':
            removeAssignment();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Get all courses
function getCourses() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT c.*, COUNT(ct.id) as teacher_count
            FROM courses c
            LEFT JOIN course_teachers ct ON c.id = ct.course_id AND ct.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.name
        ");
        $courses = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'courses' => $courses]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching courses: ' . $e->getMessage()]);
    }
}

// Get course details
function getCourseDetails() {
    global $pdo;
    
    $course_id = (int)($_POST['course_id'] ?? $_GET['course_id'] ?? 0);
    
    if (!$course_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID is required']);
        return;
    }
    
    try {
        // Get course information
        $stmt = $pdo->prepare("
            SELECT * FROM courses WHERE id = ?
        ");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            echo json_encode(['success' => false, 'message' => 'Course not found']);
            return;
        }
        
        // Get assigned teachers
        $stmt = $pdo->prepare("
            SELECT ct.*, t.full_name as teacher_name, cl.name as class_name
            FROM course_teachers ct
            JOIN teachers t ON ct.teacher_id = t.id
            JOIN classes cl ON ct.class_id = cl.id
            WHERE ct.course_id = ? AND ct.is_active = 1
            ORDER BY ct.semester, cl.name
        ");
        $stmt->execute([$course_id]);
        $teachers = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true, 
            'course' => $course, 
            'teachers' => $teachers
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching course details: ' . $e->getMessage()]);
    }
}

// Create a new course
function createCourse() {
    global $pdo;
    
    $name = $_POST['name'] ?? '';
    $code = $_POST['code'] ?? '';
    $description = $_POST['description'] ?? '';
    $credits = $_POST['credits'] ?? 3;
    
    if (empty($name) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Course name and code are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO courses (name, code, description, credits) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $code, $description, $credits]);
        
        $course_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'message' => 'Course created successfully', 'course_id' => $course_id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating course: ' . $e->getMessage()]);
    }
}

// Update course
function updateCourse() {
    global $pdo;
    
    $course_id = (int)($_POST['course_id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $code = $_POST['code'] ?? '';
    $description = $_POST['description'] ?? '';
    $credits = $_POST['credits'] ?? 3;
    $is_active = $_POST['is_active'] ?? 1;
    
    if (!$course_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID is required']);
        return;
    }
    
    if (empty($name) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Course name and code are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE courses 
            SET name = ?, code = ?, description = ?, credits = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $code, $description, $credits, $is_active, $course_id]);
        
        echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating course: ' . $e->getMessage()]);
    }
}

// Delete course
function deleteCourse() {
    global $pdo;
    
    $course_id = (int)($_POST['course_id'] ?? 0);
    
    if (!$course_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID is required']);
        return;
    }
    
    try {
        // Check if course has active teachers
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_teachers WHERE course_id = ? AND is_active = 1");
        $stmt->execute([$course_id]);
        $teacher_count = $stmt->fetchColumn();
        
        if ($teacher_count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete course with active teachers. Deactivate teachers first.']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE courses SET is_active = 0 WHERE id = ?");
        $stmt->execute([$course_id]);
        
        echo json_encode(['success' => true, 'message' => 'Course deactivated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deactivating course: ' . $e->getMessage()]);
    }
}

// Get all teachers
function getTeachers() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT *, 
                   (SELECT COUNT(*) FROM course_teachers ct WHERE ct.teacher_id = t.id AND ct.is_active = 1) as course_count
            FROM teachers t 
            WHERE t.is_active = 1
            ORDER BY full_name
        ");
        $teachers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'teachers' => $teachers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching teachers: ' . $e->getMessage()]);
    }
}

// Get all classes
function getClasses() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT c.*, 
                   t.full_name as homeroom_teacher,
                   COUNT(ce.id) as student_count
            FROM classes c
            LEFT JOIN class_teachers ct ON c.id = ct.class_id AND ct.role = 'homeroom' AND ct.is_active = 1
            LEFT JOIN teachers t ON ct.teacher_id = t.id
            LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
            WHERE c.academic_year = YEAR(CURDATE())
            GROUP BY c.id
            ORDER BY c.grade, c.section, c.name
        ");
        $classes = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'classes' => $classes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching classes: ' . $e->getMessage()]);
    }
}

// Assign teacher to course
function assignTeacherToCourse() {
    global $pdo;
    
    $course_id = (int)($_POST['course_id'] ?? 0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $semester = $_POST['semester'] ?? '1st';
    $academic_year = $_POST['academic_year'] ?? date('Y');
    $hours_per_week = $_POST['hours_per_week'] ?? 3;
    
    if (!$course_id || !$teacher_id || !$class_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID, Teacher ID, and Class ID are required']);
        return;
    }
    
    try {
        // Check if this assignment already exists
        $stmt = $pdo->prepare("
            SELECT id FROM course_teachers 
            WHERE course_id = ? AND teacher_id = ? AND class_id = ? AND semester = ? AND academic_year = ? AND is_active = 1
        ");
        $stmt->execute([$course_id, $teacher_id, $class_id, $semester, $academic_year]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This teacher is already assigned to this course for this class, semester, and academic year']);
            return;
        }
        
        // Assign teacher to course
        $stmt = $pdo->prepare("
            INSERT INTO course_teachers (course_id, teacher_id, class_id, semester, academic_year, hours_per_week) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$course_id, $teacher_id, $class_id, $semester, $academic_year, $hours_per_week]);
        
        echo json_encode(['success' => true, 'message' => 'Teacher assigned to course successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error assigning teacher to course: ' . $e->getMessage()]);
    }
}

// Get teachers for a specific course
function getCourseTeachers() {
    global $pdo;
    
    $course_id = (int)($_POST['course_id'] ?? $_GET['course_id'] ?? 0);
    
    if (!$course_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT ct.*, t.full_name as teacher_name, cl.name as class_name, cl.grade
            FROM course_teachers ct
            JOIN teachers t ON ct.teacher_id = t.id
            JOIN classes cl ON ct.class_id = cl.id
            WHERE ct.course_id = ? AND ct.is_active = 1
            ORDER BY ct.semester, cl.name
        ");
        $stmt->execute([$course_id]);
        $teachers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'teachers' => $teachers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching course teachers: ' . $e->getMessage()]);
    }
}

// Remove teacher from course
function removeTeacherFromCourse() {
    global $pdo;
    
    $course_id = (int)($_POST['course_id'] ?? 0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $semester = $_POST['semester'] ?? '1st';
    $academic_year = $_POST['academic_year'] ?? date('Y');
    
    if (!$course_id || !$teacher_id || !$class_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID, Teacher ID, and Class ID are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE course_teachers 
            SET is_active = 0 
            WHERE course_id = ? AND teacher_id = ? AND class_id = ? AND semester = ? AND academic_year = ?
        ");
        $stmt->execute([$course_id, $teacher_id, $class_id, $semester, $academic_year]);
        
        echo json_encode(['success' => true, 'message' => 'Teacher removed from course successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error removing teacher from course: ' . $e->getMessage()]);
    }
}

// Get dashboard statistics
function getDashboardStats() {
    global $pdo;
    
    try {
        // Get total courses
        $stmt = $pdo->query("SELECT COUNT(*) FROM courses WHERE is_active = 1");
        $total_courses = $stmt->fetchColumn();
        
        // Get active teachers
        $stmt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_active = 1");
        $active_teachers = $stmt->fetchColumn();
        
        // Get active classes
        $stmt = $pdo->query("SELECT COUNT(*) FROM classes WHERE academic_year = YEAR(CURDATE())");
        $active_classes = $stmt->fetchColumn();
        
        // Get current semester info
        $current_year = date('Y');
        $current_month = date('n');
        $current_semester = ($current_month >= 9 || $current_month <= 2) ? '1st' : '2nd';
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_courses' => $total_courses,
                'active_teachers' => $active_teachers,
                'active_classes' => $active_classes,
                'current_year' => $current_year,
                'current_semester' => $current_semester
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching dashboard stats: ' . $e->getMessage()]);
    }
}

// Get teacher assignments summary
function getTeacherAssignmentsSummary() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                t.full_name as teacher_name,
                COUNT(DISTINCT ct.course_id) as course_count,
                COUNT(DISTINCT ct.class_id) as class_count,
                SUM(ct.hours_per_week) as total_hours
            FROM course_teachers ct
            JOIN teachers t ON ct.teacher_id = t.id
            WHERE ct.is_active = 1 AND ct.academic_year = YEAR(CURDATE())
            GROUP BY ct.teacher_id, t.full_name
            ORDER BY course_count DESC, class_count DESC
        ");
        $assignments = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'assignments' => $assignments]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching teacher assignments: ' . $e->getMessage()]);
    }
}

// Get class assignments summary
function getClassAssignmentsSummary() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                c.name as class_name,
                c.grade,
                COUNT(DISTINCT ct.course_id) as course_count,
                COUNT(DISTINCT ct.teacher_id) as teacher_count
            FROM course_teachers ct
            JOIN classes c ON ct.class_id = c.id
            WHERE ct.is_active = 1 AND ct.academic_year = YEAR(CURDATE())
            GROUP BY ct.class_id, c.name, c.grade
            ORDER BY c.grade, c.name
        ");
        $assignments = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'assignments' => $assignments]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching class assignments: ' . $e->getMessage()]);
    }
}

// Get detailed assignments
function getDetailedAssignments() {
    global $pdo;
    
    $search = $_POST['search'] ?? $_GET['search'] ?? '';
    $semester = $_POST['semester'] ?? $_GET['semester'] ?? '';
    $year = $_POST['year'] ?? $_GET['year'] ?? '';
    $page = (int)($_POST['page'] ?? $_GET['page'] ?? 1);
    $limit = (int)($_POST['limit'] ?? $_GET['limit'] ?? 10);
    
    // Ensure page and limit are valid
    $page = max(1, $page);
    $limit = max(1, min(100, $limit));
    $offset = ($page - 1) * $limit;
    
    try {
        // First get total count
        $countSql = "
            SELECT COUNT(*)
            FROM course_teachers ct
            JOIN courses c ON ct.course_id = c.id
            JOIN teachers t ON ct.teacher_id = t.id
            JOIN classes cl ON ct.class_id = cl.id
            WHERE ct.is_active = 1
        ";
        
        $countParams = [];
        
        if ($search) {
            $countSql .= " AND (c.name LIKE ? OR c.code LIKE ? OR t.full_name LIKE ? OR cl.name LIKE ?)";
            $countParams = array_merge($countParams, ["%$search%", "%$search%", "%$search%", "%$search%"]);
        }
        
        if ($semester) {
            $countSql .= " AND ct.semester = ?";
            $countParams[] = $semester;
        }
        
        if ($year) {
            $countSql .= " AND ct.academic_year = ?";
            $countParams[] = $year;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();
        
        // Now get the actual data
        $sql = "
            SELECT 
                ct.id,
                c.name as course_name,
                c.code as course_code,
                t.full_name as teacher_name,
                cl.name as class_name,
                cl.grade,
                ct.semester,
                ct.academic_year,
                ct.hours_per_week
            FROM course_teachers ct
            JOIN courses c ON ct.course_id = c.id
            JOIN teachers t ON ct.teacher_id = t.id
            JOIN classes cl ON ct.class_id = cl.id
            WHERE ct.is_active = 1
        ";
        
        $params = $countParams; // Reuse the same parameters
        
        if ($search) {
            $sql .= " AND (c.name LIKE ? OR c.code LIKE ? OR t.full_name LIKE ? OR cl.name LIKE ?)";
            $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
        }
        
        if ($semester) {
            $sql .= " AND ct.semester = ?";
            $params[] = $semester;
        }
        
        if ($year) {
            $sql .= " AND ct.academic_year = ?";
            $params[] = $year;
        }
        
        $sql .= " ORDER BY ct.academic_year DESC, ct.semester, c.name, t.full_name";
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $assignments = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true, 
            'assignments' => $assignments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching detailed assignments: ' . $e->getMessage()]);
    }
}

// Remove assignment
function removeAssignment() {
    global $pdo;
    
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    
    if (!$assignment_id) {
        echo json_encode(['success' => false, 'message' => 'Assignment ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE course_teachers SET is_active = 0 WHERE id = ?");
        $stmt->execute([$assignment_id]);
        
        echo json_encode(['success' => true, 'message' => 'Assignment removed successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error removing assignment: ' . $e->getMessage()]);
    }
}
?>