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
        case 'get_classes':
            getClasses();
            break;
            
        case 'get_class_details':
            getClassDetails();
            break;
            
        case 'create_class':
            createClass();
            break;
            
        case 'update_class':
            updateClass();
            break;
            
        case 'delete_class':
            deleteClass();
            break;
            
        case 'get_students_for_class':
            getStudentsForClass();
            break;
            
        case 'assign_students':
            assignStudents();
            break;
            
        case 'remove_student':
            removeStudent();
            break;
            
        case 'get_teachers':
            getTeachers();
            break;
            
        case 'assign_teacher':
            assignTeacher();
            break;
            
        case 'get_class_teachers':
            getClassTeachers();
            break;
            
        case 'get_student_classes':
            getStudentClasses();
            break;
            
        case 'transfer_student':
            transferStudent();
            break;
            
        case 'auto_allocate_students':
            autoAllocateStudents();
            break;
            
        case 'get_grade_student_count':
            getGradeStudentCount();
            break;
            
        case 'create_section_classes':
            createSectionClasses();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
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
            GROUP BY c.id
            ORDER BY c.grade, c.section, c.name
        ");
        $classes = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'classes' => $classes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching classes: ' . $e->getMessage()]);
    }
}

// Get class details
function getClassDetails() {
    global $pdo;
    
    $class_id = (int)($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    try {
        // Get class information
        $stmt = $pdo->prepare("
            SELECT c.*, t.full_name as homeroom_teacher
            FROM classes c
            LEFT JOIN class_teachers ct ON c.id = ct.class_id AND ct.role = 'homeroom' AND ct.is_active = 1
            LEFT JOIN teachers t ON ct.teacher_id = t.id
            WHERE c.id = ?
        ");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch();
        
        if (!$class) {
            echo json_encode(['success' => false, 'message' => 'Class not found']);
            return;
        }
        
        // Get enrolled students
        $stmt = $pdo->prepare("
            SELECT s.id, s.full_name, s.current_grade, s.phone_number, ce.enrollment_date, ce.status
            FROM class_enrollments ce
            JOIN students s ON ce.student_id = s.id
            WHERE ce.class_id = ?
            ORDER BY s.full_name
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll();
        
        // Get assigned teachers
        $stmt = $pdo->prepare("
            SELECT t.id, t.full_name, t.phone, ct.role, ct.assigned_date
            FROM class_teachers ct
            JOIN teachers t ON ct.teacher_id = t.id
            WHERE ct.class_id = ? AND ct.is_active = 1
            ORDER BY ct.role
        ");
        $stmt->execute([$class_id]);
        $teachers = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true, 
            'class' => $class, 
            'students' => $students,
            'teachers' => $teachers
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching class details: ' . $e->getMessage()]);
    }
}

// Create a new class
function createClass() {
    global $pdo;
    
    $name = $_POST['name'] ?? '';
    $grade = $_POST['grade'] ?? '';
    $section = $_POST['section'] ?? '';
    $academic_year = $_POST['academic_year'] ?? date('Y');
    $capacity = $_POST['capacity'] ?? null;
    $description = $_POST['description'] ?? '';
    
    if (empty($name) || empty($grade)) {
        echo json_encode(['success' => false, 'message' => 'Class name and grade are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO classes (name, grade, section, academic_year, capacity, description) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $grade, $section, $academic_year, $capacity, $description]);
        
        $class_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'message' => 'Class created successfully', 'class_id' => $class_id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating class: ' . $e->getMessage()]);
    }
}

// Update class
function updateClass() {
    global $pdo;
    
    $class_id = (int)($_POST['class_id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $grade = $_POST['grade'] ?? '';
    $section = $_POST['section'] ?? '';
    $academic_year = $_POST['academic_year'] ?? date('Y');
    $capacity = $_POST['capacity'] ?? null;
    $description = $_POST['description'] ?? '';
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    if (empty($name) || empty($grade)) {
        echo json_encode(['success' => false, 'message' => 'Class name and grade are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE classes 
            SET name = ?, grade = ?, section = ?, academic_year = ?, capacity = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $grade, $section, $academic_year, $capacity, $description, $class_id]);
        
        echo json_encode(['success' => true, 'message' => 'Class updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating class: ' . $e->getMessage()]);
    }
}

// Delete class
function deleteClass() {
    global $pdo;
    
    $class_id = (int)($_POST['class_id'] ?? 0);
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    try {
        // Check if class has students
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'active'");
        $stmt->execute([$class_id]);
        $student_count = $stmt->fetchColumn();
        
        if ($student_count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete class with active students']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        
        echo json_encode(['success' => true, 'message' => 'Class deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting class: ' . $e->getMessage()]);
    }
}

// Get students for a specific class
function getStudentsForClass() {
    global $pdo;
    
    $class_id = (int)($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
    $grade = $_POST['grade'] ?? '';
    $search = $_POST['search'] ?? '';
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    try {
        // Get students already in this class
        $stmt = $pdo->prepare("
            SELECT student_id 
            FROM class_enrollments 
            WHERE class_id = ? AND status = 'active'
        ");
        $stmt->execute([$class_id]);
        $enrolled_student_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get available students (not already enrolled in this class)
        $sql = "SELECT id, full_name, current_grade, phone_number 
                FROM students 
                WHERE 1=1";
        $params = [];
        
        // Exclude already enrolled students
        if (!empty($enrolled_student_ids)) {
            $placeholders = str_repeat('?,', count($enrolled_student_ids) - 1) . '?';
            $sql .= " AND id NOT IN ($placeholders)";
            $params = array_merge($params, $enrolled_student_ids);
        }
        
        if ($grade) {
            $sql .= " AND current_grade = ?";
            $params[] = $grade;
        }
        
        if ($search) {
            $sql .= " AND (full_name LIKE ? OR phone_number LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY full_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true, 
            'students' => $students,
            'enrolled_count' => count($enrolled_student_ids)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching students: ' . $e->getMessage()]);
    }
}

// Assign students to a class
function assignStudents() {
    global $pdo;
    
    $class_id = (int)($_POST['class_id'] ?? 0);
    $student_ids = $_POST['student_ids'] ?? [];
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    if (empty($student_ids) || !is_array($student_ids)) {
        echo json_encode(['success' => false, 'message' => 'Student IDs are required']);
        return;
    }
    
    try {
        // Check class capacity
        $stmt = $pdo->prepare("SELECT capacity FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        $capacity = $stmt->fetchColumn();
        
        if ($capacity) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'active'");
            $stmt->execute([$class_id]);
            $current_count = $stmt->fetchColumn();
            
            if ($current_count + count($student_ids) > $capacity) {
                echo json_encode(['success' => false, 'message' => 'Adding these students would exceed class capacity']);
                return;
            }
        }
        
        // Assign students
        $pdo->beginTransaction();
        
        foreach ($student_ids as $student_id) {
            // Check if student is already enrolled in this class
            $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE student_id = ? AND class_id = ? AND status = 'active'");
            $stmt->execute([$student_id, $class_id]);
            
            if ($stmt->fetch()) {
                continue; // Already enrolled
            }
            
            // Enroll student
            $stmt = $pdo->prepare("INSERT INTO class_enrollments (class_id, student_id, enrollment_date) VALUES (?, ?, CURDATE())");
            $stmt->execute([$class_id, $student_id]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => count($student_ids) . ' students assigned successfully']);
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Error assigning students: ' . $e->getMessage()]);
    }
}

// Remove student from class
function removeStudent() {
    global $pdo;
    
    $class_id = (int)($_POST['class_id'] ?? 0);
    $student_id = (int)($_POST['student_id'] ?? 0);
    
    if (!$class_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID and Student ID are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE class_enrollments SET status = 'dropped' WHERE class_id = ? AND student_id = ?");
        $stmt->execute([$class_id, $student_id]);
        
        echo json_encode(['success' => true, 'message' => 'Student removed from class']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error removing student: ' . $e->getMessage()]);
    }
}

// Get all teachers
function getTeachers() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT *, 
                   (SELECT COUNT(*) FROM class_teachers ct WHERE ct.teacher_id = t.id AND ct.is_active = 1) as class_count
            FROM teachers t 
            ORDER BY full_name
        ");
        $teachers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'teachers' => $teachers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching teachers: ' . $e->getMessage()]);
    }
}

// Assign teacher to class
function assignTeacher() {
    global $pdo;
    
    $class_id = (int)($_POST['class_id'] ?? 0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $role = $_POST['role'] ?? 'primary';
    
    if (!$class_id || !$teacher_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID and Teacher ID are required']);
        return;
    }
    
    try {
        // For homeroom teacher, deactivate previous homeroom teacher
        if ($role === 'homeroom') {
            $stmt = $pdo->prepare("UPDATE class_teachers SET is_active = 0 WHERE class_id = ? AND role = 'homeroom'");
            $stmt->execute([$class_id]);
        }
        
        // Assign new teacher
        $stmt = $pdo->prepare("
            INSERT INTO class_teachers (class_id, teacher_id, role, assigned_date, is_active) 
            VALUES (?, ?, ?, CURDATE(), 1)
            ON DUPLICATE KEY UPDATE teacher_id = ?, is_active = 1, assigned_date = CURDATE()
        ");
        $stmt->execute([$class_id, $teacher_id, $role, $teacher_id]);
        
        echo json_encode(['success' => true, 'message' => 'Teacher assigned successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error assigning teacher: ' . $e->getMessage()]);
    }
}

// Get teachers for a specific class
function getClassTeachers() {
    global $pdo;
    
    $class_id = (int)($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.full_name, t.phone, ct.role, ct.assigned_date, ct.is_active
            FROM class_teachers ct
            JOIN teachers t ON ct.teacher_id = t.id
            WHERE ct.class_id = ?
            ORDER BY ct.role
        ");
        $stmt->execute([$class_id]);
        $teachers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'teachers' => $teachers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching class teachers: ' . $e->getMessage()]);
    }
}

// Get classes for a specific student
function getStudentClasses() {
    global $pdo;
    
    $student_id = (int)($_POST['student_id'] ?? $_GET['student_id'] ?? 0);
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.grade, c.section, c.academic_year, ce.enrollment_date, ce.status
            FROM class_enrollments ce
            JOIN classes c ON ce.class_id = c.id
            WHERE ce.student_id = ?
            ORDER BY ce.enrollment_date DESC
        ");
        $stmt->execute([$student_id]);
        $classes = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'classes' => $classes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching student classes: ' . $e->getMessage()]);
    }
}

// Transfer student between classes
function transferStudent() {
    global $pdo;
    
    $student_id = (int)($_POST['student_id'] ?? 0);
    $from_class_id = (int)($_POST['from_class_id'] ?? 0);
    $to_class_id = (int)($_POST['to_class_id'] ?? 0);
    
    if (!$student_id || !$from_class_id || !$to_class_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID, From Class ID, and To Class ID are required']);
        return;
    }
    
    if ($from_class_id === $to_class_id) {
        echo json_encode(['success' => false, 'message' => 'Cannot transfer to the same class']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mark student as transferred from current class
        $stmt = $pdo->prepare("UPDATE class_enrollments SET status = 'transferred' WHERE class_id = ? AND student_id = ?");
        $stmt->execute([$from_class_id, $student_id]);
        
        // Check if student is already enrolled in the target class
        $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE student_id = ? AND class_id = ? AND status = 'active'");
        $stmt->execute([$student_id, $to_class_id]);
        
        if ($stmt->fetch()) {
            // Student already enrolled, just reactivate
            $stmt = $pdo->prepare("UPDATE class_enrollments SET status = 'active' WHERE class_id = ? AND student_id = ?");
            $stmt->execute([$to_class_id, $student_id]);
        } else {
            // Enroll student in new class
            $stmt = $pdo->prepare("INSERT INTO class_enrollments (class_id, student_id, enrollment_date, status) VALUES (?, ?, CURDATE(), 'active')");
            $stmt->execute([$to_class_id, $student_id]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Student transferred successfully']);
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Error transferring student: ' . $e->getMessage()]);
    }
}

// Get student count for a specific grade
function getGradeStudentCount() {
    global $pdo;
    
    $grade = $_POST['grade'] ?? '';
    
    if (empty($grade)) {
        echo json_encode(['success' => false, 'message' => 'Grade is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE current_grade = ?");
        $stmt->execute([$grade]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching student count: ' . $e->getMessage()]);
    }
}

// Create section classes for a grade
function createSectionClasses() {
    global $pdo;
    
    $grade = $_POST['grade'] ?? '';
    // Handle sections as array parameter
    $sections = $_POST['sections'] ?? [];
    if (!is_array($sections)) {
        // If it's a JSON string, decode it
        if (is_string($sections)) {
            $sections = json_decode($sections, true) ?: [];
        } else {
            $sections = [];
        }
    }
    $capacity = $_POST['capacity'] ?? null;
    $academic_year = $_POST['academic_year'] ?? date('Y');
    
    if (empty($grade) || empty($sections)) {
        echo json_encode(['success' => false, 'message' => 'Grade and sections are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $created_classes = [];
        foreach ($sections as $section) {
            $name = "Grade $grade Section $section";
            
            $stmt = $pdo->prepare("
                INSERT INTO classes (name, grade, section, academic_year, capacity) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $grade, $section, $academic_year, $capacity]);
            
            $class_id = $pdo->lastInsertId();
            $created_classes[] = [
                'id' => $class_id,
                'name' => $name,
                'grade' => $grade,
                'section' => $section
            ];
        }
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => count($sections) . ' section classes created successfully',
            'classes' => $created_classes
        ]);
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Error creating section classes: ' . $e->getMessage()]);
    }
}

// Automatically allocate students to section classes
function autoAllocateStudents() {
    global $pdo;
    
    $grade = $_POST['grade'] ?? '';
    // Handle class_ids as array parameter
    $class_ids = $_POST['class_ids'] ?? [];
    if (!is_array($class_ids)) {
        // If it's a JSON string, decode it
        if (is_string($class_ids)) {
            $class_ids = json_decode($class_ids, true) ?: [];
        } else {
            $class_ids = [];
        }
    }
    $max_capacity = $_POST['max_capacity'] ?? 50;
    
    if (empty($grade) || empty($class_ids)) {
        echo json_encode(['success' => false, 'message' => 'Grade and class IDs are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get all students for this grade who are not yet assigned to any class
        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM students s
            WHERE s.current_grade = ?
            AND s.id NOT IN (
                SELECT DISTINCT ce.student_id 
                FROM class_enrollments ce 
                JOIN classes c ON ce.class_id = c.id 
                WHERE c.grade = ? AND ce.status = 'active'
            )
            ORDER BY s.full_name
        ");
        $stmt->execute([$grade, $grade]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($students)) {
            echo json_encode(['success' => false, 'message' => 'No unassigned students found for this grade']);
            return;
        }
        
        // Distribute students among classes
        $students_per_class = ceil(count($students) / count($class_ids));
        $allocations = [];
        
        foreach ($class_ids as $index => $class_id) {
            $start_index = $index * $students_per_class;
            $end_index = min(($index + 1) * $students_per_class, count($students));
            
            // Get students for this class
            $class_students = array_slice($students, $start_index, $students_per_class);
            
            // Assign students to class
            foreach ($class_students as $student_id) {
                // Check if student is already enrolled in this class
                $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE student_id = ? AND class_id = ? AND status = 'active'");
                $stmt->execute([$student_id, $class_id]);
                
                if (!$stmt->fetch()) {
                    // Enroll student
                    $stmt = $pdo->prepare("INSERT INTO class_enrollments (class_id, student_id, enrollment_date) VALUES (?, ?, CURDATE())");
                    $stmt->execute([$class_id, $student_id]);
                }
            }
            
            $allocations[] = [
                'class_id' => $class_id,
                'student_count' => count($class_students)
            ];
        }
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => count($students) . ' students allocated to ' . count($class_ids) . ' classes',
            'allocations' => $allocations
        ]);
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Error allocating students: ' . $e->getMessage()]);
    }
}
?>