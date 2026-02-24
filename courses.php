<?php
session_start();
require 'config.php';
require 'includes/admin_layout.php';
require 'includes/security_helpers.php';

// Require admin authentication
requireAdminLogin();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_courses':
                // Get courses with additional statistics
                $stmt = $pdo->query("
                    SELECT 
                        c.*, 
                        COUNT(ct.id) as teacher_count,
                        COUNT(DISTINCT ct.class_id) as class_count,
                        SUM(ct.hours_per_week) as total_hours
                    FROM courses c
                    LEFT JOIN course_teachers ct ON c.id = ct.course_id AND ct.is_active = 1
                    WHERE c.is_active = 1
                    GROUP BY c.id
                    ORDER BY c.name
                ");
                $courses = $stmt->fetchAll();
                echo json_encode(['success' => true, 'courses' => $courses]);
                break;
                
            case 'get_course_details':
                $course_id = (int)$_POST['course_id'];
                
                // Get course information
                $stmt = $pdo->prepare("
                    SELECT * FROM courses WHERE id = ?
                ");
                $stmt->execute([$course_id]);
                $course = $stmt->fetch();
                
                if (!$course) {
                    echo json_encode(['success' => false, 'message' => 'Course not found']);
                    break;
                }
                
                // Get assigned teachers with class details
                $stmt = $pdo->prepare("
                    SELECT ct.*, t.full_name as teacher_name, cl.name as class_name, cl.grade
                    FROM course_teachers ct
                    JOIN teachers t ON ct.teacher_id = t.id
                    JOIN classes cl ON ct.class_id = cl.id
                    WHERE ct.course_id = ? AND ct.is_active = 1
                    ORDER BY ct.academic_year DESC, ct.semester, cl.grade, cl.name
                ");
                $stmt->execute([$course_id]);
                $teachers = $stmt->fetchAll();
                
                // Get enrollment statistics
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT ce.student_id) as total_students,
                        COUNT(DISTINCT cl.id) as total_classes
                    FROM course_teachers ct
                    JOIN classes cl ON ct.class_id = cl.id
                    JOIN class_enrollments ce ON cl.id = ce.class_id
                    WHERE ct.course_id = ? AND ct.is_active = 1 AND ce.status = 'active'
                ");
                $stmt->execute([$course_id]);
                $stats = $stmt->fetch();
                
                echo json_encode([
                    'success' => true, 
                    'course' => $course, 
                    'teachers' => $teachers,
                    'stats' => $stats
                ]);
                break;
                
            case 'create_course':
                $name = $_POST['name'] ?? '';
                $code = $_POST['code'] ?? '';
                $description = $_POST['description'] ?? '';
                $credits = $_POST['credits'] ?? 3;
                
                if (empty($name) || empty($code)) {
                    echo json_encode(['success' => false, 'message' => 'Course name and code are required']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO courses (name, code, description, credits) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$name, $code, $description, $credits]);
                
                $course_id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Course created successfully', 'course_id' => $course_id]);
                break;
                
            case 'update_course':
                $course_id = (int)$_POST['course_id'];
                $name = $_POST['name'] ?? '';
                $code = $_POST['code'] ?? '';
                $description = $_POST['description'] ?? '';
                $credits = $_POST['credits'] ?? 3;
                $is_active = $_POST['is_active'] ?? 1;
                
                if (empty($name) || empty($code)) {
                    echo json_encode(['success' => false, 'message' => 'Course name and code are required']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE courses 
                    SET name = ?, code = ?, description = ?, credits = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $code, $description, $credits, $is_active, $course_id]);
                
                echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
                break;
                
            case 'delete_course':
                $course_id = (int)$_POST['course_id'];
                
                // Check if course has active teachers
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_teachers WHERE course_id = ? AND is_active = 1");
                $stmt->execute([$course_id]);
                $teacher_count = $stmt->fetchColumn();
                
                if ($teacher_count > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete course with active teachers. Deactivate teachers first.']);
                    break;
                }
                
                $stmt = $pdo->prepare("UPDATE courses SET is_active = 0 WHERE id = ?");
                $stmt->execute([$course_id]);
                
                echo json_encode(['success' => true, 'message' => 'Course deactivated successfully']);
                break;
                
            case 'get_teachers':
                $stmt = $pdo->query("
                    SELECT *, 
                           (SELECT COUNT(*) FROM course_teachers ct WHERE ct.teacher_id = t.id AND ct.is_active = 1) as course_count
                    FROM teachers t 
                    WHERE t.is_active = 1
                    ORDER BY full_name
                ");
                $teachers = $stmt->fetchAll();
                echo json_encode(['success' => true, 'teachers' => $teachers]);
                break;
                
            case 'get_classes':
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
                break;
                
            case 'assign_teacher_to_course':
                $course_id = (int)$_POST['course_id'];
                $teacher_id = (int)$_POST['teacher_id'];
                $class_id = (int)$_POST['class_id'];
                $semester = $_POST['semester'] ?? '1st';
                $academic_year = $_POST['academic_year'] ?? date('Y');
                $hours_per_week = $_POST['hours_per_week'] ?? 3;
                
                if (!$course_id || !$teacher_id || !$class_id) {
                    echo json_encode(['success' => false, 'message' => 'Course ID, Teacher ID, and Class ID are required']);
                    break;
                }
                
                // Check if this assignment already exists
                $stmt = $pdo->prepare("
                    SELECT id FROM course_teachers 
                    WHERE course_id = ? AND teacher_id = ? AND class_id = ? AND semester = ? AND academic_year = ? AND is_active = 1
                ");
                $stmt->execute([$course_id, $teacher_id, $class_id, $semester, $academic_year]);
                
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'This teacher is already assigned to this course for this class, semester, and academic year']);
                    break;
                }
                
                // Assign teacher to course
                $stmt = $pdo->prepare("
                    INSERT INTO course_teachers (course_id, teacher_id, class_id, semester, academic_year, hours_per_week) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$course_id, $teacher_id, $class_id, $semester, $academic_year, $hours_per_week]);
                
                echo json_encode(['success' => true, 'message' => 'Teacher assigned to course successfully']);
                break;
                
            case 'remove_teacher_from_course':
                $course_id = (int)$_POST['course_id'];
                $teacher_id = (int)$_POST['teacher_id'];
                $class_id = (int)$_POST['class_id'];
                $semester = $_POST['semester'] ?? '1st';
                $academic_year = $_POST['academic_year'] ?? date('Y');
                
                $stmt = $pdo->prepare("
                    UPDATE course_teachers 
                    SET is_active = 0 
                    WHERE course_id = ? AND teacher_id = ? AND class_id = ? AND semester = ? AND academic_year = ?
                ");
                $stmt->execute([$course_id, $teacher_id, $class_id, $semester, $academic_year]);
                
                echo json_encode(['success' => true, 'message' => 'Teacher removed from course successfully']);
                break;
                
            case 'get_course_statistics':
                // Get overall course statistics
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_courses,
                        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_courses,
                        SUM(CASE WHEN is_active = 1 THEN credits ELSE 0 END) as total_credits
                    FROM courses
                ");
                $course_stats = $stmt->fetch();
                
                // Get teacher assignment statistics
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(DISTINCT ct.teacher_id) as total_teachers,
                        COUNT(ct.id) as total_assignments,
                        AVG(ct.hours_per_week) as avg_hours_per_week
                    FROM course_teachers ct
                    WHERE ct.is_active = 1
                ");
                $teacher_stats = $stmt->fetch();
                
                // Get class distribution
                $stmt = $pdo->query("
                    SELECT 
                        cl.grade,
                        COUNT(DISTINCT ct.class_id) as class_count,
                        COUNT(ct.id) as assignment_count
                    FROM course_teachers ct
                    JOIN classes cl ON ct.class_id = cl.id
                    WHERE ct.is_active = 1
                    GROUP BY cl.grade
                    ORDER BY 
                        CASE cl.grade
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
                    'course_stats' => $course_stats,
                    'teacher_stats' => $teacher_stats,
                    'grade_distribution' => $grade_distribution
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

$title = 'Advanced Course Management';
ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-book mr-3 text-primary-600 dark:text-primary-400"></i>
                Advanced Course Management
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Professional course management with advanced analytics</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button onclick="openCreateCourseModal()" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium flex items-center">
                <i class="fas fa-plus mr-2"></i> Create Course
            </button>
            <button onclick="refreshCourses()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium flex items-center">
                <i class="fas fa-sync-alt mr-2"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 transition-all duration-300 hover:shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-blue-100 dark:bg-blue-900/50">
                    <i class="fas fa-book text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Courses</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="total-courses">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 transition-all duration-300 hover:shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900/50">
                    <i class="fas fa-chalkboard-teacher text-green-600 dark:text-green-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Teachers</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="active-teachers">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 transition-all duration-300 hover:shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/50">
                    <i class="fas fa-users-class text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Classes</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="active-classes">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 transition-all duration-300 hover:shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-amber-100 dark:bg-amber-900/50">
                    <i class="fas fa-clock text-amber-600 dark:text-amber-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg. Hours/Week</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="avg-hours">0</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Course List</h2>
                <div class="relative w-full md:w-64">
                    <input type="text" id="course-search" placeholder="Search courses..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credits</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teachers</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classes</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours/Week</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="courses-table-body">
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center">
                            <div class="flex justify-center">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing <span id="showing-start">0</span> to <span id="showing-end">0</span> of <span id="total-records">0</span> results
            </div>
            <div class="flex space-x-2">
                <button id="prev-page" class="px-3 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50" disabled>
                    Previous
                </button>
                <button id="next-page" class="px-3 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50" disabled>
                    Next
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Course Modal -->
<div id="course-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-4 border w-11/12 md:w-2/5 shadow-lg rounded-lg bg-white dark:bg-gray-800">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="course-modal-title">Create Course</h3>
            <button onclick="closeCourseModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="py-3">
            <form id="course-form">
                <input type="hidden" id="course-id" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Course Name *</label>
                        <input type="text" id="course-name" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Course Code *</label>
                        <input type="text" id="course-code" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Credits</label>
                        <input type="number" id="course-credits" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="3" min="1" max="10">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select id="course-status" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea id="course-description" rows="3" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeCourseModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md text-sm font-medium">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-sm font-medium">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Course Details Modal -->
<div id="course-details-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-4 border w-11/12 md:w-3/5 shadow-lg rounded-lg bg-white dark:bg-gray-800 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Course Details</h3>
            <button onclick="closeCourseDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="py-3" id="course-details-content">
            <div class="flex justify-center py-6">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600"></div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Teacher Modal -->
<div id="assign-teacher-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-4 border w-11/12 md:w-2/5 shadow-lg rounded-lg bg-white dark:bg-gray-800 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assign Teacher to Course</h3>
            <button onclick="closeAssignTeacherModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="py-3">
            <input type="hidden" id="assign-course-id" value="">
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Select Teacher *</label>
                <select id="assign-teacher-id" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Loading teachers...</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Select Class *</label>
                <select id="assign-class-id" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Loading classes...</option>
                </select>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Semester</label>
                    <select id="assign-semester" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <option value="1st">First Semester</option>
                        <option value="2nd">Second Semester</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Academic Year</label>
                    <input type="number" id="assign-academic-year" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="<?= date('Y') ?>">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Hours Per Week</label>
                <input type="number" id="assign-hours-per-week" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="3" min="1" max="20">
            </div>
            
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeAssignTeacherModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md text-sm font-medium">Cancel</button>
                <button type="button" onclick="assignTeacherToCourse()" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-sm font-medium">Assign Teacher</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$page_script = '
<script>
// Global variables
let currentCourseId = null;
let currentPage = 1;
let totalPages = 1;
let totalRecords = 0;

// Initialize page
document.addEventListener("DOMContentLoaded", function() {
    loadCourses();
    loadCourseStatistics();
    
    // Handle form submission
    document.getElementById("course-form").addEventListener("submit", function(e) {
        e.preventDefault();
        saveCourse();
    });
    
    // Handle search
    document.getElementById("course-search").addEventListener("input", function() {
        currentPage = 1;
        loadCourses(this.value);
    });
    
    // Handle pagination
    document.getElementById("prev-page").addEventListener("click", function() {
        if (currentPage > 1) {
            currentPage--;
            loadCourses();
        }
    });
    
    document.getElementById("next-page").addEventListener("click", function() {
        if (currentPage < totalPages) {
            currentPage++;
            loadCourses();
        }
    });
});

// Refresh courses
function refreshCourses() {
    loadCourses();
    loadCourseStatistics();
}

// Load course statistics
function loadCourseStatistics() {
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_course_statistics"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById("total-courses").textContent = data.course_stats.active_courses;
            document.getElementById("active-teachers").textContent = data.teacher_stats.total_teachers;
            document.getElementById("active-classes").textContent = data.grade_distribution.reduce((sum, item) => sum + parseInt(item.class_count), 0);
            document.getElementById("avg-hours").textContent = Math.round(data.teacher_stats.avg_hours_per_week || 0);
        }
    })
    .catch(error => {
        console.error("Error loading statistics:", error);
    });
}

// Load courses
function loadCourses(search = "") {
    const tableBody = document.getElementById("courses-table-body");
    tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center"><div class="flex justify-center"><div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600"></div></div></td></tr>`;
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_courses" + (search ? "&search=" + encodeURIComponent(search) : "")
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            totalRecords = data.courses.length;
            totalPages = Math.ceil(totalRecords / 10);
            
            // Update pagination controls
            document.getElementById("showing-start").textContent = totalRecords > 0 ? (currentPage - 1) * 10 + 1 : 0;
            document.getElementById("showing-end").textContent = Math.min(currentPage * 10, totalRecords);
            document.getElementById("total-records").textContent = totalRecords;
            
            document.getElementById("prev-page").disabled = currentPage <= 1;
            document.getElementById("next-page").disabled = currentPage >= totalPages;
            
            if (data.courses.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500 text-sm">No courses found</td></tr>`;
                return;
            }
            
            let html = "";
            data.courses.forEach(course => {
                html += `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900 dark:text-white text-sm">${course.name}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        ${course.code}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        ${course.credits}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        ${course.teacher_count || 0}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        ${course.class_count || 0}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        ${course.total_hours || 0}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <button onclick="viewCourseDetails(${course.id})" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 mr-2 p-1.5 rounded-md hover:bg-primary-50 dark:hover:bg-primary-900/50">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="openEditCourseModal(${course.id})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-2 p-1.5 rounded-md hover:bg-blue-50 dark:hover:bg-blue-900/50">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteCourse(${course.id})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 p-1.5 rounded-md hover:bg-red-50 dark:hover:bg-red-900/50">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center text-red-500 text-sm">Error loading courses</td></tr>`;
        }
    })
    .catch(error => {
        tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center text-red-500 text-sm">Network error</td></tr>`;
    });
}

// Create course modal
function openCreateCourseModal() {
    document.getElementById("course-modal-title").textContent = "Create Course";
    document.getElementById("course-id").value = "";
    document.getElementById("course-name").value = "";
    document.getElementById("course-code").value = "";
    document.getElementById("course-credits").value = "3";
    document.getElementById("course-description").value = "";
    document.getElementById("course-status").value = "1";
    document.getElementById("course-modal").classList.remove("hidden");
}

// Edit course modal
function openEditCourseModal(courseId) {
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_course_details&course_id=" + courseId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const course = data.course;
            document.getElementById("course-modal-title").textContent = "Edit Course";
            document.getElementById("course-id").value = course.id;
            document.getElementById("course-name").value = course.name;
            document.getElementById("course-code").value = course.code;
            document.getElementById("course-credits").value = course.credits;
            document.getElementById("course-description").value = course.description || "";
            document.getElementById("course-status").value = course.is_active;
            document.getElementById("course-modal").classList.remove("hidden");
        } else {
            alert("Error loading course: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Close course modal
function closeCourseModal() {
    document.getElementById("course-modal").classList.add("hidden");
}

// Save course
function saveCourse() {
    const courseId = document.getElementById("course-id").value;
    const name = document.getElementById("course-name").value;
    const code = document.getElementById("course-code").value;
    const credits = document.getElementById("course-credits").value;
    const description = document.getElementById("course-description").value;
    const is_active = document.getElementById("course-status").value;
    
    if (!name || !code) {
        alert("Course name and code are required");
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append("action", courseId ? "update_course" : "create_course");
    formData.append("name", name);
    formData.append("code", code);
    formData.append("credits", credits);
    formData.append("description", description);
    formData.append("is_active", is_active);
    if (courseId) formData.append("course_id", courseId);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeCourseModal();
            loadCourses();
            loadCourseStatistics();
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Delete course
function deleteCourse(courseId) {
    if (!confirm("Are you sure you want to deactivate this course?")) return;
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=delete_course&course_id=" + courseId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCourses();
            loadCourseStatistics();
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// View course details
function viewCourseDetails(courseId) {
    currentCourseId = courseId;
    document.getElementById("course-details-modal").classList.remove("hidden");
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_course_details&course_id=" + courseId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const course = data.course;
            const teachers = data.teachers;
            const stats = data.stats;
            
            let html = `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="md:col-span-2 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-3">Course Information</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Name</p>
                            <p class="font-medium text-sm">${course.name}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Code</p>
                            <p class="font-medium text-sm">${course.code}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Credits</p>
                            <p class="font-medium text-sm">${course.credits}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
                            <p class="font-medium text-sm">${course.is_active == 1 ? "Active" : "Inactive"}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-3">Statistics</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Students</span>
                            <span class="font-medium text-sm">${stats.total_students || 0}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Classes</span>
                            <span class="font-medium text-sm">${stats.total_classes || 0}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Teachers</span>
                            <span class="font-medium text-sm">${teachers.length}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Description</h4>
                </div>
                <p class="text-gray-600 dark:text-gray-400 text-sm">${course.description || "No description provided"}</p>
            </div>
            
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Assigned Teachers (${teachers.length})</h4>
                    <button onclick="openAssignTeacherModal(${course.id})" class="text-xs text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 flex items-center">
                        <i class="fas fa-plus mr-1"></i> Assign Teacher
                    </button>
                </div>
                ${
                    teachers.length > 0 ? 
                    `<div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Teacher</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Class</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Grade</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Semester</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Hours/Week</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                ${teachers.map(teacher => `
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900 dark:text-white">${teacher.teacher_name}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">${teacher.class_name}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">${teacher.grade}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full dark:bg-blue-900/30 dark:text-blue-200">
                                                ${teacher.semester} ${teacher.academic_year}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">${teacher.hours_per_week}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <button onclick="removeTeacherFromCourse(${course.id}, ${teacher.teacher_id}, ${teacher.class_id}, \"${teacher.semester}\", ${teacher.academic_year})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 p-1.5 rounded-md hover:bg-red-50 dark:hover:bg-red-900/50">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `).join("")}
                            </tbody>
                        </table>
                    </div>` : 
                    `<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 text-center">
                        <i class="fas fa-chalkboard-teacher text-gray-300 dark:text-gray-600 text-2xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No teachers assigned to this course</p>
                        <button onclick="openAssignTeacherModal(${course.id})" class="mt-3 text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 flex items-center justify-center mx-auto">
                            <i class="fas fa-plus mr-1"></i> Assign a Teacher
                        </button>
                    </div>`
                }
            </div>
            
            <div class="flex justify-end space-x-2">
                <button onclick="closeCourseDetailsModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md text-sm font-medium">Close</button>
                <button onclick="openEditCourseModal(${course.id})" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-sm font-medium">Edit Course</button>
            </div>
            `;
            
            document.getElementById("course-details-content").innerHTML = html;
        } else {
            document.getElementById("course-details-content").innerHTML = `<p class="text-red-500 py-6 text-center text-sm">Error loading course details: ${data.message}</p>`;
        }
    })
    .catch(error => {
        document.getElementById("course-details-content").innerHTML = `<p class="text-red-500 py-6 text-center text-sm">Network error</p>`;
    });
}

// Close course details modal
function closeCourseDetailsModal() {
    document.getElementById("course-details-modal").classList.add("hidden");
}

// Open assign teacher modal
function openAssignTeacherModal(courseId) {
    document.getElementById("assign-course-id").value = courseId;
    document.getElementById("assign-teacher-modal").classList.remove("hidden");
    
    // Load teachers
    loadTeachersForAssignment();
    
    // Load classes
    loadClassesForAssignment();
}

// Load teachers for assignment
function loadTeachersForAssignment() {
    const select = document.getElementById("assign-teacher-id");
    select.innerHTML = `<option value="">Loading teachers...</option>`;
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_teachers"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.teachers.length === 0) {
                select.innerHTML = `<option value="">No teachers available</option>`;
                return;
            }
            
            let options = `<option value="">Select a teacher</option>`;
            data.teachers.forEach(teacher => {
                options += `<option value="${teacher.id}">${teacher.full_name} (${teacher.course_count} courses)</option>`;
            });
            select.innerHTML = options;
        } else {
            select.innerHTML = `<option value="">Error loading teachers</option>`;
        }
    })
    .catch(error => {
        select.innerHTML = `<option value="">Network error</option>`;
    });
}

// Load classes for assignment
function loadClassesForAssignment() {
    const select = document.getElementById("assign-class-id");
    select.innerHTML = `<option value="">Loading classes...</option>`;
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_classes"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.classes.length === 0) {
                select.innerHTML = `<option value="">No classes available</option>`;
                return;
            }
            
            let options = `<option value="">Select a class</option>`;
            data.classes.forEach(cls => {
                options += `<option value="${cls.id}">${cls.name} (Grade ${cls.grade}) - ${cls.student_count} students</option>`;
            });
            select.innerHTML = options;
        } else {
            select.innerHTML = `<option value="">Error loading classes</option>`;
        }
    })
    .catch(error => {
        select.innerHTML = `<option value="">Network error</option>`;
    });
}

// Close assign teacher modal
function closeAssignTeacherModal() {
    document.getElementById("assign-teacher-modal").classList.add("hidden");
}

// Assign teacher to course
function assignTeacherToCourse() {
    const courseId = document.getElementById("assign-course-id").value;
    const teacherId = document.getElementById("assign-teacher-id").value;
    const classId = document.getElementById("assign-class-id").value;
    const semester = document.getElementById("assign-semester").value;
    const academicYear = document.getElementById("assign-academic-year").value;
    const hoursPerWeek = document.getElementById("assign-hours-per-week").value;
    
    if (!courseId || !teacherId || !classId) {
        alert("Please select course, teacher, and class");
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append("action", "assign_teacher_to_course");
    formData.append("course_id", courseId);
    formData.append("teacher_id", teacherId);
    formData.append("class_id", classId);
    formData.append("semester", semester);
    formData.append("academic_year", academicYear);
    formData.append("hours_per_week", hoursPerWeek);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAssignTeacherModal();
            viewCourseDetails(courseId); // Refresh course details
            loadCourseStatistics(); // Refresh statistics
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Remove teacher from course
function removeTeacherFromCourse(courseId, teacherId, classId, semester, academicYear) {
    if (!confirm("Are you sure you want to remove this teacher from the course?")) return;
    
    const formData = new URLSearchParams();
    formData.append("action", "remove_teacher_from_course");
    formData.append("course_id", courseId);
    formData.append("teacher_id", teacherId);
    formData.append("class_id", classId);
    formData.append("semester", semester);
    formData.append("academic_year", academicYear);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            viewCourseDetails(courseId); // Refresh course details
            loadCourseStatistics(); // Refresh statistics
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}
</script>
';

echo renderAdminLayout($title, $content, $page_script);
?>