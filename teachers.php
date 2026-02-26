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
            case 'get_teachers':
                $search = $_POST['search'] ?? '';
                
                $sql = "SELECT *, 
                        (SELECT COUNT(*) FROM class_teachers ct WHERE ct.teacher_id = t.id AND ct.is_active = 1) as class_count
                        FROM teachers t WHERE 1=1";
                $params = [];
                
                if ($search) {
                    $sql .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                }
                
                $sql .= " ORDER BY full_name";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $teachers = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'teachers' => $teachers]);
                break;
                
            case 'create_teacher':
                $full_name = $_POST['full_name'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $qualification = $_POST['qualification'] ?? '';
                $experience_years = $_POST['experience_years'] ?? null;
                
                if (empty($full_name)) {
                    echo json_encode(['success' => false, 'message' => 'Teacher name is required']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO teachers (full_name, email, phone, qualification, experience_years) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$full_name, $email, $phone, $qualification, $experience_years]);
                
                $teacher_id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Teacher created successfully', 'teacher_id' => $teacher_id]);
                break;
                
            case 'update_teacher':
                $teacher_id = (int)$_POST['teacher_id'];
                $full_name = $_POST['full_name'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $qualification = $_POST['qualification'] ?? '';
                $experience_years = $_POST['experience_years'] ?? null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($full_name)) {
                    echo json_encode(['success' => false, 'message' => 'Teacher name is required']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE teachers 
                    SET full_name = ?, email = ?, phone = ?, qualification = ?, experience_years = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $phone, $qualification, $experience_years, $is_active, $teacher_id]);
                
                echo json_encode(['success' => true, 'message' => 'Teacher updated successfully']);
                break;
                
            case 'delete_teacher':
                $teacher_id = (int)$_POST['teacher_id'];
                
                // Check if teacher is assigned to any classes
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_teachers WHERE teacher_id = ?");
                $stmt->execute([$teacher_id]);
                $class_count = $stmt->fetchColumn();
                
                if ($class_count > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete teacher assigned to classes']);
                    break;
                }
                
                $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
                $stmt->execute([$teacher_id]);
                
                echo json_encode(['success' => true, 'message' => 'Teacher deleted successfully']);
                break;
                
            case 'get_teacher_details':
                $teacher_id = (int)$_POST['teacher_id'];
                
                // Get teacher details
                $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
                $stmt->execute([$teacher_id]);
                $teacher = $stmt->fetch();
                
                if (!$teacher) {
                    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
                    break;
                }
                
                // Get assigned classes
                $stmt = $pdo->prepare("
                    SELECT c.id, c.name, c.grade, c.section, ct.role, ct.assigned_date
                    FROM class_teachers ct
                    JOIN classes c ON ct.class_id = c.id
                    WHERE ct.teacher_id = ? AND ct.is_active = 1
                    ORDER BY c.grade, c.section
                ");
                $stmt->execute([$teacher_id]);
                $classes = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'teacher' => $teacher, 'classes' => $classes]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

$title = 'Teacher Management';
ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-chalkboard-teacher mr-3 text-primary-600 dark:text-primary-400"></i>
                Teacher Management
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage teachers and their class assignments</p>
        </div>
        <button onclick="openCreateTeacherModal()" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Teacher
        </button>
    </div>

    <!-- Teachers Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Teachers</h2>
                <div class="relative w-full md:w-64">
                    <input type="text" id="teacher-search" placeholder="Search teachers..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qualification</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Experience</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="teachers-table-body">
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
        </div>
    </div>
</div>

<!-- Create/Edit Teacher Modal -->
<div id="teacher-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-xl bg-white dark:bg-gray-800">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white" id="teacher-modal-title">Add Teacher</h3>
            <button onclick="closeTeacherModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="py-4">
            <form id="teacher-form">
                <input type="hidden" id="teacher-id" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                        <input type="text" id="teacher-name" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" id="teacher-email" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                        <input type="text" id="teacher-phone" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Experience (Years)</label>
                        <input type="number" id="teacher-experience" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select id="teacher-status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Qualification</label>
                        <textarea id="teacher-qualification" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeTeacherModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium">Save Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Teacher Details Modal -->
<div id="teacher-details-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 shadow-lg rounded-xl bg-white dark:bg-gray-800 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Teacher Details</h3>
            <button onclick="closeTeacherDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="py-4" id="teacher-details-content">
            <div class="flex justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$page_script = '
<script>
// Global variables
let currentTeacherId = null;

// Initialize page
document.addEventListener("DOMContentLoaded", function() {
    loadTeachers();
    
    // Handle form submission
    document.getElementById("teacher-form").addEventListener("submit", function(e) {
        e.preventDefault();
        saveTeacher();
    });
    
    // Handle search
    document.getElementById("teacher-search").addEventListener("input", function() {
        loadTeachers(this.value);
    });
});

// Load teachers
function loadTeachers(search = "") {
    const tableBody = document.getElementById("teachers-table-body");
    tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center"><div class="flex justify-center"><div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600"></div></div></td></tr>`;
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_teachers" + (search ? "&search=" + encodeURIComponent(search) : "")
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.teachers.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">No teachers found</td></tr>`;
                return;
            }
            
            let html = "";
            data.teachers.forEach(teacher => {
                html += `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900 dark:text-white">${teacher.full_name}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        ${teacher.email ? `<div>${teacher.email}</div>` : ""}
                        ${teacher.phone ? `<div>${teacher.phone}</div>` : ""}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        ${teacher.qualification || "-"}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        ${teacher.experience_years ? teacher.experience_years + " years" : "-"}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        ${teacher.class_count || 0}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        ${teacher.is_active == 1 ? 
                            `<span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>` : 
                            `<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Inactive</span>`}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="viewTeacherDetails(${teacher.id})" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 mr-3">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="openEditTeacherModal(${teacher.id})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteTeacher(${teacher.id})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center text-red-500">Error loading teachers</td></tr>`;
        }
    })
    .catch(error => {
        tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center text-red-500">Network error</td></tr>`;
    });
}

// Create teacher modal
function openCreateTeacherModal() {
    document.getElementById("teacher-modal-title").textContent = "Add Teacher";
    document.getElementById("teacher-id").value = "";
    document.getElementById("teacher-name").value = "";
    document.getElementById("teacher-email").value = "";
    document.getElementById("teacher-phone").value = "";
    document.getElementById("teacher-experience").value = "";
    document.getElementById("teacher-status").value = "1";
    document.getElementById("teacher-qualification").value = "";
    document.getElementById("teacher-modal").classList.remove("hidden");
}

// Edit teacher modal
function openEditTeacherModal(teacherId) {
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_teacher_details&teacher_id=" + teacherId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const teacher = data.teacher;
            document.getElementById("teacher-modal-title").textContent = "Edit Teacher";
            document.getElementById("teacher-id").value = teacher.id;
            document.getElementById("teacher-name").value = teacher.full_name;
            document.getElementById("teacher-email").value = teacher.email || "";
            document.getElementById("teacher-phone").value = teacher.phone || "";
            document.getElementById("teacher-experience").value = teacher.experience_years || "";
            document.getElementById("teacher-status").value = teacher.is_active;
            document.getElementById("teacher-qualification").value = teacher.qualification || "";
            document.getElementById("teacher-modal").classList.remove("hidden");
        } else {
            alert("Error loading teacher: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Close teacher modal
function closeTeacherModal() {
    document.getElementById("teacher-modal").classList.add("hidden");
}

// Save teacher
function saveTeacher() {
    const teacherId = document.getElementById("teacher-id").value;
    const full_name = document.getElementById("teacher-name").value;
    const email = document.getElementById("teacher-email").value;
    const phone = document.getElementById("teacher-phone").value;
    const experience_years = document.getElementById("teacher-experience").value;
    const is_active = document.getElementById("teacher-status").value;
    const qualification = document.getElementById("teacher-qualification").value;
    
    if (!full_name) {
        alert("Teacher name is required");
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append("action", teacherId ? "update_teacher" : "create_teacher");
    formData.append("full_name", full_name);
    formData.append("email", email);
    formData.append("phone", phone);
    if (experience_years) formData.append("experience_years", experience_years);
    formData.append("is_active", is_active);
    formData.append("qualification", qualification);
    if (teacherId) formData.append("teacher_id", teacherId);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeTeacherModal();
            loadTeachers();
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Delete teacher
function deleteTeacher(teacherId) {
    if (!confirm("Are you sure you want to delete this teacher?")) return;
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=delete_teacher&teacher_id=" + teacherId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadTeachers();
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// View teacher details
function viewTeacherDetails(teacherId) {
    currentTeacherId = teacherId;
    document.getElementById("teacher-details-modal").classList.remove("hidden");
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_teacher_details&teacher_id=" + teacherId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const teacher = data.teacher;
            const classes = data.classes;
            
            let html = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Teacher Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Name:</span>
                            <span class="font-medium">${teacher.full_name}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Email:</span>
                            <span class="font-medium">${teacher.email || "-"}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Phone:</span>
                            <span class="font-medium">${teacher.phone || "-"}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Experience:</span>
                            <span class="font-medium">${teacher.experience_years ? teacher.experience_years + " years" : "-"}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Status:</span>
                            <span class="font-medium">${teacher.is_active == 1 ? "Active" : "Inactive"}</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Qualifications</h4>
                    <p class="text-gray-600 dark:text-gray-400">${teacher.qualification || "No qualifications provided"}</p>
                </div>
            </div>
            
            <div class="mb-4">
                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Assigned Classes (${classes.length})</h4>
                ${
                    classes.length > 0 ? 
                    `<div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Grade</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Section</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Assigned Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                ${classes.map(cls => `
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">${cls.name}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${cls.grade}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${cls.section || "-"}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${cls.role}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${cls.assigned_date}</td>
                                    </tr>
                                `).join("")}
                            </tbody>
                        </table>
                    </div>` : 
                    `<p class="text-gray-500 dark:text-gray-400 py-4 text-center">No classes assigned</p>`
                }
            </div>
            `;
            
            document.getElementById("teacher-details-content").innerHTML = html;
        } else {
            document.getElementById("teacher-details-content").innerHTML = `<p class="text-red-500 py-4 text-center">Error loading teacher details: ${data.message}</p>`;
        }
    })
    .catch(error => {
        document.getElementById("teacher-details-content").innerHTML = `<p class="text-red-500 py-4 text-center">Network error</p>`;
    });
}

// Close teacher details modal
function closeTeacherDetailsModal() {
    document.getElementById("teacher-details-modal").classList.add("hidden");
}
</script>
';

echo renderAdminLayout($title, $content, $page_script);
?>