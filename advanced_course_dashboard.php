<?php
session_start();
require 'config.php';
require 'includes/admin_layout.php';
require 'includes/security_helpers.php';

// Require admin authentication
requireAdminLogin();

$title = 'Advanced Course Dashboard';
ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-graduation-cap mr-3 text-primary-600 dark:text-primary-400"></i>
                Advanced Course Dashboard
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Comprehensive course management and analytics platform</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button onclick="refreshDashboard()" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium flex items-center">
                <i class="fas fa-sync-alt mr-2"></i> Refresh
            </button>
            <button onclick="exportDashboard()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium flex items-center">
                <i class="fas fa-download mr-2"></i> Export
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
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                    <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                    <span id="courses-change">+0%</span> from last month
                </p>
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
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                    <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                    <span id="teachers-change">+0%</span> from last month
                </p>
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
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                    <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                    <span id="classes-change">+0%</span> from last month
                </p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 transition-all duration-300 hover:shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-amber-100 dark:bg-amber-900/50">
                    <i class="fas fa-calendar-alt text-amber-600 dark:text-amber-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Semester</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="current-semester">2025/1st</p>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400">Academic Year</p>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Course Distribution Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Course Distribution by Grade</h2>
            </div>
            <div class="p-5">
                <canvas id="courseDistributionChart" height="300"></canvas>
            </div>
        </div>

        <!-- Teacher Workload Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Teacher Workload (Hours/Week)</h2>
            </div>
            <div class="p-5">
                <canvas id="teacherWorkloadChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Course Assignments Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Course Assignments by Teacher -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Course Assignments by Teacher</h2>
                <button onclick="viewAllTeachers()" class="text-xs text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All
                </button>
            </div>
            <div class="p-5">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courses</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classes</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours/Week</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="teacher-assignments-body">
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center">
                                    <div class="flex justify-center">
                                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Course Assignments by Class -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Course Assignments by Class</h2>
                <button onclick="viewAllClasses()" class="text-xs text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All
                </button>
            </div>
            <div class="p-5">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courses</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teachers</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="class-assignments-body">
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center">
                                    <div class="flex justify-center">
                                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Assignments -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Detailed Course Assignments</h2>
            <div class="flex flex-col md:flex-row md:items-center gap-3">
                <div class="relative w-full md:w-64">
                    <input type="text" id="assignment-search" placeholder="Search assignments..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
                <div class="flex gap-2">
                    <select id="semester-filter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <option value="">All Semesters</option>
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                    </select>
                    <select id="year-filter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <option value="">All Years</option>
                        <option value="2025">2025</option>
                        <option value="2024">2024</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="p-5">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours/Week</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="assignments-table-body">
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center">
                                <div class="flex justify-center">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex items-center justify-between">
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
</div>

<?php
$content = ob_get_clean();

$page_script = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Global variables
let currentPage = 1;
let totalPages = 1;
let totalRecords = 0;
let chart1, chart2;

// Initialize dashboard
document.addEventListener("DOMContentLoaded", function() {
    loadDashboardData();
    
    // Handle search
    document.getElementById("assignment-search").addEventListener("input", function() {
        currentPage = 1;
        loadAssignments(this.value);
    });
    
    // Handle filters
    document.getElementById("semester-filter").addEventListener("change", () => {
        currentPage = 1;
        loadAssignments();
    });
    document.getElementById("year-filter").addEventListener("change", () => {
        currentPage = 1;
        loadAssignments();
    });
    
    // Handle pagination
    document.getElementById("prev-page").addEventListener("click", function() {
        if (currentPage > 1) {
            currentPage--;
            loadAssignments();
        }
    });
    
    document.getElementById("next-page").addEventListener("click", function() {
        if (currentPage < totalPages) {
            currentPage++;
            loadAssignments();
        }
    });
});

// Refresh dashboard
function refreshDashboard() {
    loadDashboardData();
}

// Export dashboard data
function exportDashboard() {
    alert("Export functionality would be implemented here");
}

// View all teachers
function viewAllTeachers() {
    alert("View all teachers functionality would be implemented here");
}

// View all classes
function viewAllClasses() {
    alert("View all classes functionality would be implemented here");
}

// Load dashboard data
function loadDashboardData() {
    // Load stats
    loadStats();
    
    // Load teacher assignments
    loadTeacherAssignments();
    
    // Load class assignments
    loadClassAssignments();
    
    // Load detailed assignments
    loadAssignments();
    
    // Load charts
    loadCharts();
}

// Load statistics
function loadStats() {
    fetch("api/course_management.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_dashboard_stats"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById("total-courses").textContent = data.stats.total_courses;
            document.getElementById("active-teachers").textContent = data.stats.active_teachers;
            document.getElementById("active-classes").textContent = data.stats.active_classes;
            document.getElementById("current-semester").textContent = data.stats.current_year + "/" + data.stats.current_semester;
            
            // Simulate change percentages
            document.getElementById("courses-change").textContent = "+5.2%";
            document.getElementById("teachers-change").textContent = "+2.1%";
            document.getElementById("classes-change").textContent = "+3.7%";
        }
    })
    .catch(error => {
        console.error("Error loading stats:", error);
    });
}

// Load teacher assignments
function loadTeacherAssignments() {
    const tableBody = document.getElementById("teacher-assignments-body");
    tableBody.innerHTML = `<tr><td colspan="4" class="px-4 py-4 text-center"><div class="flex justify-center"><div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600"></div></div></td></tr>`;
    
    fetch("api/course_management.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_teacher_assignments_summary"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.assignments.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No teacher assignments found</td></tr>`;
                return;
            }
            
            let html = "";
            // Show only top 5
            const assignments = data.assignments.slice(0, 5);
            assignments.forEach(assignment => {
                html += `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">${assignment.teacher_name}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${assignment.course_count}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${assignment.class_count}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${assignment.total_hours}</td>
                </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="px-4 py-4 text-center text-red-500">Error loading teacher assignments</td></tr>`;
        }
    })
    .catch(error => {
        tableBody.innerHTML = `<tr><td colspan="4" class="px-4 py-4 text-center text-red-500">Network error</td></tr>`;
    });
}

// Load class assignments
function loadClassAssignments() {
    const tableBody = document.getElementById("class-assignments-body");
    tableBody.innerHTML = `<tr><td colspan="4" class="px-4 py-4 text-center"><div class="flex justify-center"><div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600"></div></div></td></tr>`;
    
    fetch("api/course_management.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_class_assignments_summary"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.assignments.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No class assignments found</td></tr>`;
                return;
            }
            
            let html = "";
            // Show only top 5
            const assignments = data.assignments.slice(0, 5);
            assignments.forEach(assignment => {
                html += `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">${assignment.class_name}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${assignment.grade}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${assignment.course_count}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${assignment.teacher_count}</td>
                </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="px-4 py-4 text-center text-red-500">Error loading class assignments</td></tr>`;
        }
    })
    .catch(error => {
        tableBody.innerHTML = `<tr><td colspan="4" class="px-4 py-4 text-center text-red-500">Network error</td></tr>`;
    });
}

// Load detailed assignments
function loadAssignments(search = "") {
    const tableBody = document.getElementById("assignments-table-body");
    tableBody.innerHTML = `<tr><td colspan="6" class="px-4 py-4 text-center"><div class="flex justify-center"><div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600"></div></div></td></tr>`;
    
    const semester = document.getElementById("semester-filter").value;
    const year = document.getElementById("year-filter").value;
    
    const formData = new URLSearchParams();
    formData.append("action", "get_detailed_assignments");
    if (search) formData.append("search", search);
    if (semester) formData.append("semester", semester);
    if (year) formData.append("year", year);
    formData.append("page", currentPage);
    formData.append("limit", 10);
    
    fetch("api/course_management.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            totalRecords = data.total || data.assignments.length;
            totalPages = Math.ceil(totalRecords / 10);
            
            // Update pagination controls
            document.getElementById("showing-start").textContent = totalRecords > 0 ? (currentPage - 1) * 10 + 1 : 0;
            document.getElementById("showing-end").textContent = Math.min(currentPage * 10, totalRecords);
            document.getElementById("total-records").textContent = totalRecords;
            
            document.getElementById("prev-page").disabled = currentPage <= 1;
            document.getElementById("next-page").disabled = currentPage >= totalPages;
            
            if (data.assignments.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">No assignments found</td></tr>`;
                return;
            }
            
            let html = "";
            data.assignments.forEach(assignment => {
                html += `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                        <div>${assignment.course_name}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">${assignment.course_code}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${assignment.teacher_name}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        <div>${assignment.class_name}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Grade ${assignment.grade}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full dark:bg-blue-900/30 dark:text-blue-200">${assignment.semester} ${assignment.academic_year}</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${assignment.hours_per_week}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                        <button onclick="viewAssignmentDetails(${assignment.id})" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 mr-2 p-1">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="removeAssignment(${assignment.id})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 p-1">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `<tr><td colspan="6" class="px-4 py-4 text-center text-red-500">Error loading assignments</td></tr>`;
        }
    })
    .catch(error => {
        tableBody.innerHTML = `<tr><td colspan="6" class="px-4 py-4 text-center text-red-500">Network error</td></tr>`;
    });
}

// View assignment details
function viewAssignmentDetails(assignmentId) {
    alert("Viewing details for assignment ID: " + assignmentId);
    // In a real implementation, this would open a modal with detailed information
}

// Remove assignment
function removeAssignment(assignmentId) {
    if (!confirm("Are you sure you want to remove this assignment?")) return;
    
    const formData = new URLSearchParams();
    formData.append("action", "remove_assignment");
    formData.append("assignment_id", assignmentId);
    
    fetch("api/course_management.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAssignments();
            showToast("Assignment removed successfully", "success");
        } else {
            showToast("Error removing assignment: " + data.message, "error");
        }
    })
    .catch(error => {
        showToast("Network error", "error");
    });
}

// Load charts
function loadCharts() {
    // Destroy existing charts if they exist
    if (chart1) chart1.destroy();
    if (chart2) chart2.destroy();
    
    // Course Distribution Chart
    const ctx1 = document.getElementById("courseDistributionChart").getContext("2d");
    chart1 = new Chart(ctx1, {
        type: "bar",
        data: {
            labels: ["Grade 1", "Grade 2", "Grade 3", "Grade 4", "Grade 5", "Grade 6"],
            datasets: [{
                label: "Number of Courses",
                data: [12, 15, 18, 20, 17, 14],
                backgroundColor: "rgba(54, 162, 235, 0.6)",
                borderColor: "rgba(54, 162, 235, 1)",
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Teacher Workload Chart
    const ctx2 = document.getElementById("teacherWorkloadChart").getContext("2d");
    chart2 = new Chart(ctx2, {
        type: "doughnut",
        data: {
            labels: ["0-5 hrs", "6-10 hrs", "11-15 hrs", "16-20 hrs"],
            datasets: [{
                data: [8, 15, 12, 5],
                backgroundColor: [
                    "rgba(255, 99, 132, 0.6)",
                    "rgba(54, 162, 235, 0.6)",
                    "rgba(255, 205, 86, 0.6)",
                    "rgba(75, 192, 192, 0.6)"
                ],
                borderColor: [
                    "rgba(255, 99, 132, 1)",
                    "rgba(54, 162, 235, 1)",
                    "rgba(255, 205, 86, 1)",
                    "rgba(75, 192, 192, 1)"
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "right",
                }
            }
        }
    });
}

// Show toast notification
function showToast(message, type = "info") {
    // Create toast element
    const toast = document.createElement("div");
    toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 text-white font-medium transform transition-transform duration-300 ${
        type === "success" ? "bg-green-500" : 
        type === "error" ? "bg-red-500" : "bg-blue-500"
    }`;
    toast.textContent = message;
    toast.style.transform = "translateX(100%)";
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = "translateX(0)";
    }, 10);
    
    // Remove after delay
    setTimeout(() => {
        toast.style.transform = "translateX(100%)";
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}
</script>
';

echo renderAdminLayout($title, $content, $page_script);
?>