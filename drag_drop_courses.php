<?php
// Note: config.php already handles session initialization, so we don't need to call session_start() directly
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
                $stmt = $pdo->query("
                    SELECT id, name, code 
                    FROM courses 
                    WHERE is_active = 1 
                    ORDER BY name
                ");
                $courses = $stmt->fetchAll();
                echo json_encode(['success' => true, 'courses' => $courses]);
                break;
                
            case 'get_classes':
                $stmt = $pdo->query("
                    SELECT c.*, 
                           COUNT(ce.id) as student_count
                    FROM classes c
                    LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
                    GROUP BY c.id
                    ORDER BY c.grade, c.section
                ");
                $classes = $stmt->fetchAll();
                
                // Get assigned courses for each class
                foreach ($classes as &$class) {
                    $stmt = $pdo->prepare("
                        SELECT ct.id, ct.course_id, ct.teacher_id, ct.semester,
                               c.name as course_name, c.code as course_code,
                               t.full_name as teacher_name
                        FROM course_teachers ct
                        JOIN courses c ON ct.course_id = c.id
                        JOIN teachers t ON ct.teacher_id = t.id
                        WHERE ct.class_id = ? AND ct.is_active = 1
                        ORDER BY c.name
                    ");
                    $stmt->execute([$class['id']]);
                    $class['courses'] = $stmt->fetchAll();
                }
                
                echo json_encode(['success' => true, 'classes' => $classes]);
                break;
                
            case 'get_teachers':
                $stmt = $pdo->query("SELECT id, full_name, phone FROM teachers WHERE is_active = 1 ORDER BY full_name");
                $teachers = $stmt->fetchAll();
                echo json_encode(['success' => true, 'teachers' => $teachers]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

$title = 'Course Assignment - Drag & Drop';
ob_start();
?>

<style>
/* Drag and Drop Styles */
.draggable {
    cursor: move;
    user-select: none;
    transition: all 0.2s ease;
}

.draggable:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.draggable.dragging {
    opacity: 0.5;
    background-color: #dbeafe;
}

.drop-zone {
    transition: all 0.2s ease;
    min-height: 100px;
    border: 2px dashed #d1d5db;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.drop-zone.drag-over {
    background-color: #dbeafe;
    border: 2px dashed #3b82f6;
}

.drop-zone-header {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.drop-zone-content {
    min-height: 80px;
}

.course-card {
    background-color: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.course-card.assigned {
    background-color: #dcfce7;
    border-color: #bbf7d0;
}

.teacher-badge {
    background-color: #eff6ff;
    color: #3b82f6;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.class-card {
    background-color: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.class-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.class-title {
    font-weight: 600;
    color: #1f2937;
}

.class-info {
    font-size: 0.875rem;
    color: #6b7280;
}

.assigned-course {
    background-color: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 0.375rem;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.remove-course {
    color: #ef4444;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 0.25rem;
}

.remove-course:hover {
    background-color: #fee2e2;
}

.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-left-color: #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #9ca3af;
}

.empty-state i {
    font-size: 2rem;
    margin-bottom: 1rem;
}
</style>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-exchange-alt mr-3 text-primary-600 dark:text-primary-400"></i>
                Course Assignment
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Drag and drop courses onto classes to assign them</p>
        </div>
        <button onclick="refreshData()" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg flex items-center">
            <i class="fas fa-sync-alt mr-2"></i> Refresh
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Courses Panel -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Available Courses</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Drag courses to classes on the right</p>
            </div>
            <div class="p-5">
                <div class="mb-4">
                    <input type="text" id="course-search" placeholder="Search courses..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div id="courses-container" class="space-y-3">
                    <div class="flex justify-center py-8">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classes Panel -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Classes</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Drop courses here to assign them</p>
            </div>
            <div class="p-5">
                <div class="mb-4">
                    <input type="text" id="class-search" placeholder="Search classes..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div id="classes-container" class="space-y-4">
                    <div class="flex justify-center py-8">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Course Modal -->
<div id="assign-course-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/5 shadow-lg rounded-md bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assign Course</h3>
            <button onclick="closeAssignCourseModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="py-4">
            <input type="hidden" id="modal-class-id" value="">
            <input type="hidden" id="modal-course-id" value="">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Course</label>
                <div id="modal-course-info" class="px-3 py-2 bg-gray-50 dark:bg-gray-700 rounded-md"></div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Class</label>
                <div id="modal-class-info" class="px-3 py-2 bg-gray-50 dark:bg-gray-700 rounded-md"></div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Teacher *</label>
                <select id="modal-teacher-select" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Loading teachers...</option>
                </select>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Semester</label>
                    <select id="modal-semester-select" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Academic Year</label>
                    <input type="number" id="modal-academic-year" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="<?= date('Y') ?>">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hours Per Week</label>
                <input type="number" id="modal-hours-per-week" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="3" min="1" max="20">
            </div>
            
            <div class="flex justify-end gap-3">
                <button onclick="closeAssignCourseModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md">Cancel</button>
                <button onclick="assignCourse()" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md">Assign Course</button>
            </div>
        </div>
    </div>
</div>

<script>
let coursesData = [];
let classesData = [];

// Initialize page
document.addEventListener("DOMContentLoaded", function() {
    loadData();
    
    // Add search functionality
    document.getElementById("course-search").addEventListener("input", filterCourses);
    document.getElementById("class-search").addEventListener("input", filterClasses);
});

// Load all data
function loadData() {
    Promise.all([
        fetch(window.location.href, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=get_courses"
        }).then(response => response.json()),
        fetch(window.location.href, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=get_classes"
        }).then(response => response.json()),
        fetch(window.location.href, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=get_teachers"
        }).then(response => response.json())
    ])
    .then(([coursesResult, classesResult, teachersResult]) => {
        if (coursesResult.success && classesResult.success && teachersResult.success) {
            coursesData = coursesResult.courses;
            classesData = classesResult.classes;
            window.teachersData = teachersResult.teachers;
            renderCourses();
            renderClasses();
        } else {
            alert("Error loading data: " + (coursesResult.message || classesResult.message || teachersResult.message));
        }
    })
    .catch(error => {
        alert("Network error: " + error.message);
    });
}

// Refresh data
function refreshData() {
    loadData();
}

// Render courses
function renderCourses() {
    const container = document.getElementById("courses-container");
    const searchTerm = document.getElementById("course-search").value.toLowerCase();
    
    const filteredCourses = coursesData.filter(course => 
        course.name.toLowerCase().includes(searchTerm) || 
        course.code.toLowerCase().includes(searchTerm)
    );
    
    if (filteredCourses.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>No courses found</p>
            </div>
        `;
        return;
    }
    
    let html = "";
    filteredCourses.forEach(course => {
        html += `
            <div class="course-card draggable" 
                 draggable="true" 
                 data-course-id="${course.id}"
                 data-course-name="${course.name}"
                 data-course-code="${course.code}">
                <div>
                    <div class="font-medium text-gray-900 dark:text-white">${course.name}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">${course.code}</div>
                </div>
                <div class="text-gray-400">
                    <i class="fas fa-grip-vertical"></i>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Add drag event listeners
    const draggables = document.querySelectorAll('.draggable');
    draggables.forEach(draggable => {
        draggable.addEventListener('dragstart', dragStart);
        draggable.addEventListener('dragend', dragEnd);
    });
}

// Render classes
function renderClasses() {
    const container = document.getElementById("classes-container");
    const searchTerm = document.getElementById("class-search").value.toLowerCase();
    
    const filteredClasses = classesData.filter(cls => 
        cls.name.toLowerCase().includes(searchTerm) || 
        cls.grade.toLowerCase().includes(searchTerm) ||
        (cls.section && cls.section.toLowerCase().includes(searchTerm))
    );
    
    if (filteredClasses.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-chalkboard"></i>
                <p>No classes found</p>
            </div>
        `;
        return;
    }
    
    let html = "";
    filteredClasses.forEach(cls => {
        html += `
            <div class="class-card drop-zone" data-class-id="${cls.id}">
                <div class="class-header">
                    <div>
                        <div class="class-title">${cls.name}</div>
                        <div class="class-info">Grade ${cls.grade}${cls.section ? `, Section ${cls.section}` : ''}</div>
                    </div>
                    <div class="text-sm text-gray-500">
                        ${cls.student_count || 0} students
                    </div>
                </div>
                <div class="drop-zone-header">Assigned Courses</div>
                <div class="drop-zone-content" id="class-courses-${cls.id}">
                    ${cls.courses && cls.courses.length > 0 ? 
                        cls.courses.map(course => `
                            <div class="assigned-course">
                                <div>
                                    <div class="font-medium">${course.course_name}</div>
                                    <div class="text-sm text-gray-500">${course.teacher_name}</div>
                                </div>
                                <div class="remove-course" onclick="removeCourseAssignment(${course.id}, ${cls.id})">
                                    <i class="fas fa-times"></i>
                                </div>
                            </div>
                        `).join('') :
                        `<div class="text-center text-gray-400 py-4">
                            <i class="fas fa-plus-circle mb-2"></i>
                            <p>Drag courses here</p>
                        </div>`
                    }
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Add drop zone event listeners
    const dropZones = document.querySelectorAll('.drop-zone');
    dropZones.forEach(zone => {
        zone.addEventListener('dragover', handleDragOver);
        zone.addEventListener('dragleave', handleDragLeave);
        zone.addEventListener('drop', handleDrop);
    });
}

// Filter courses
function filterCourses() {
    renderCourses();
}

// Filter classes
function filterClasses() {
    renderClasses();
}

// Drag and drop functions
function dragStart(e) {
    e.dataTransfer.setData("text/plain", e.target.dataset.courseId);
    setTimeout(() => {
        e.target.classList.add("dragging");
    }, 0);
}

function dragEnd(e) {
    e.target.classList.remove("dragging");
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.add('drag-over');
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('drag-over');
    
    const courseId = e.dataTransfer.getData("text/plain");
    const classId = this.dataset.classId;
    
    // Find course and class data
    const course = coursesData.find(c => c.id == courseId);
    const cls = classesData.find(c => c.id == classId);
    
    if (course && cls) {
        openAssignCourseModal(course, cls);
    }
}

// Open assign course modal
function openAssignCourseModal(course, cls) {
    document.getElementById("modal-class-id").value = cls.id;
    document.getElementById("modal-course-id").value = course.id;
    
    document.getElementById("modal-course-info").innerHTML = `
        <div class="font-medium">${course.name}</div>
        <div class="text-sm text-gray-500">${course.code}</div>
    `;
    
    document.getElementById("modal-class-info").innerHTML = `
        <div class="font-medium">${cls.name}</div>
        <div class="text-sm text-gray-500">Grade ${cls.grade}${cls.section ? `, Section ${cls.section}` : ''}</div>
    `;
    
    // Populate teachers
    const teacherSelect = document.getElementById("modal-teacher-select");
    teacherSelect.innerHTML = `<option value="">Select a teacher</option>`;
    window.teachersData.forEach(teacher => {
        teacherSelect.innerHTML += `<option value="${teacher.id}">${teacher.full_name} ${teacher.phone ? "(" + teacher.phone + ")" : ""}</option>`;
    });
    
    document.getElementById("assign-course-modal").classList.remove("hidden");
}

// Close assign course modal
function closeAssignCourseModal() {
    document.getElementById("assign-course-modal").classList.add("hidden");
}

// Assign course
function assignCourse() {
    const classId = document.getElementById("modal-class-id").value;
    const courseId = document.getElementById("modal-course-id").value;
    const teacherId = document.getElementById("modal-teacher-select").value;
    const semester = document.getElementById("modal-semester-select").value;
    const academicYear = document.getElementById("modal-academic-year").value;
    const hoursPerWeek = document.getElementById("modal-hours-per-week").value;
    
    if (!teacherId) {
        alert("Please select a teacher");
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append("action", "assign_course");
    formData.append("class_id", classId);
    formData.append("course_id", courseId);
    formData.append("teacher_id", teacherId);
    formData.append("semester", semester);
    formData.append("academic_year", academicYear);
    formData.append("hours_per_week", hoursPerWeek);
    
    fetch('classes.php', {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAssignCourseModal();
            loadData(); // Refresh data
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Remove course assignment
function removeCourseAssignment(assignmentId, classId) {
    if (!confirm("Are you sure you want to remove this course assignment?")) return;
    
    const formData = new URLSearchParams();
    formData.append("action", "remove_course_assignment");
    formData.append("assignment_id", assignmentId);
    
    fetch('classes.php', {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadData(); // Refresh data
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

<?php
$content = ob_get_clean();

// Now render the page using the admin layout
echo renderAdminLayout($title, $content);
?>