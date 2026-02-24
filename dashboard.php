<?php
session_start();
require 'config.php';
require 'includes/admin_layout.php';
require 'includes/mobile_table.php';
require __DIR__ . '/includes/cache_manager.php';
requireAdminLogin();

// Enable output buffering for performance
ob_start();

// List of all possible student fields (columns)
$all_student_fields = [
    'photo_path' => 'Photo',
    'full_name' => 'Full Name',
    'christian_name' => 'Christian Name',
    'gender' => 'Gender',
    'birth_date' => 'Birth Date',
    'current_grade' => 'Current Grade',
    'school_year_start' => 'School Year Start',
    'regular_school_name' => 'Regular School Name',
    'regular_school_grade' => 'Regular School Grade',
    'phone_number' => 'Phone Number',
    // Youth-specific and emergency contact fields
    'education_level' => 'Education Level',
    'field_of_study' => 'Field of Study',
    'emergency_name' => 'Emergency Name',
    'emergency_phone' => 'Emergency Phone',
    'emergency_alt_phone' => 'Emergency Alt Phone',
    'emergency_address' => 'Emergency Address',
    'has_spiritual_father' => 'Has Spiritual Father',
    'spiritual_father_name' => 'Spiritual Father Name',
    'spiritual_father_phone' => 'Spiritual Father Phone',
    'spiritual_father_church' => 'Spiritual Father Church',
    'sub_city' => 'Sub City',
    'district' => 'District',
    'specific_area' => 'Specific Area',
    'house_number' => 'House Number',
    'living_with' => 'Living With',
    'special_interests' => 'Special Interests',
    'siblings_in_school' => 'Siblings In School',
    'physical_disability' => 'Physical Disability',
    'weak_side' => 'Weak Side',
    'transferred_from_other_school' => 'Transferred From Other School',
    'came_from_other_religion' => 'Came From Other Religion',
    'created_at' => 'Registered',
    // Parent info fields
    'father_full_name' => 'Father Name',
    'father_phone' => 'Father Phone',
    'father_occupation' => 'Father Occupation',
    'mother_full_name' => 'Mother Name',
    'mother_phone' => 'Mother Phone',
    'mother_occupation' => 'Mother Occupation',
    'guardian_full_name' => 'Guardian Name',
    'guardian_phone' => 'Guardian Phone',
    'guardian_occupation' => 'Guardian Occupation',
];

// Get admin_id from session
$admin_id = $_SESSION['admin_id'] ?? 1;

// Fetch admin's column preferences from DB
$stmt = $pdo->prepare("SELECT column_list FROM admin_preferences WHERE admin_id = ? AND table_name = 'students' LIMIT 1");
$stmt->execute([$admin_id]);
$pref_row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($pref_row && !empty($pref_row['column_list'])) {
    $selected_fields = explode(',', $pref_row['column_list']);
} else {
    // Default columns if no preference saved
    $selected_fields = ['photo_path','full_name','gender','current_grade','phone_number','field_of_study','created_at'];
}

// Fetch students with caching for better performance
$students = cache_remember('dashboard_students_list', function() use ($pdo) {
    $sql = "SELECT s.id, s.photo_path, s.full_name, s.christian_name, s.gender, s.birth_date, 
            s.current_grade, s.phone_number, s.created_at,
            f.full_name AS father_full_name, f.phone_number AS father_phone, 
            m.full_name AS mother_full_name, m.phone_number AS mother_phone,
            g.full_name AS guardian_full_name, g.phone_number AS guardian_phone
        FROM students s
        LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
        LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
        LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
        ORDER BY s.created_at DESC
        LIMIT 100"; // Limit for dashboard performance
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}, CACHE_TTL_DASHBOARD, 'dashboard');

// Calculate advanced statistics
$total_students = count($students);
$male_students = count(array_filter($students, fn($s) => $s['gender'] === 'male'));
$female_students = count(array_filter($students, fn($s) => $s['gender'] === 'female'));
$new_this_month = count(array_filter($students, fn($s) => strtotime($s['created_at']) > strtotime('-30 days')));
$new_this_week = count(array_filter($students, fn($s) => strtotime($s['created_at']) > strtotime('-7 days')));
$youth_students = count(array_filter($students, function($s) {
    if (empty($s['birth_date'])) return false;
    $parts = explode('-', $s['birth_date']);
    if (count($parts) !== 3) return false;
    $age = 2024 - (int)$parts[0]; // Rough Ethiopian to Gregorian conversion
    return $age >= 18;
}));
$under_18_students = $total_students - $youth_students;

// Grade distribution
$grade_distribution = [];
foreach ($students as $student) {
    $grade = $student['current_grade'] ?: 'Unknown';
    $grade_distribution[$grade] = ($grade_distribution[$grade] ?? 0) + 1;
}
arsort($grade_distribution);

// Recent registrations (last 5 most recent)
$recent_students = array_slice($students, 0, 5);

// Prepare dashboard content
ob_start();
?>

<!-- Welcome Header -->
<div class="mb-6 sm:mb-8">
    <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 rounded-xl p-6 sm:p-8 text-white shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl sm:text-3xl font-bold mb-2">Welcome back, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>!</h1>
                <p class="text-primary-100 text-lg">Here's what's happening with your student management system today.</p>
                    </div>
            <div class="flex items-center space-x-4 text-primary-100">
                <div class="text-center">
                    <div class="text-2xl font-bold"><?= date('j') ?></div>
                    <div class="text-sm"><?= date('M Y') ?></div>
                </div>
                <div class="w-px h-12 bg-primary-500"></div>
                <div class="text-center">
                    <div class="text-2xl font-bold"><?= $new_this_week ?></div>
                    <div class="text-sm">This Week</div>
            </div>
            </div>
        </div>
    </div>
                </div>

<!-- Enhanced Statistics Grid -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <!-- Total Students -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-lg hover:scale-105 transition-all duration-200 group">
            <div class="flex items-center justify-between">
                    <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Students</p>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white"><?= $total_students ?></h3>
                <p class="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center">
                    <i class="fas fa-arrow-up mr-1"></i>
                    +<?= $new_this_month ?> this month
                </p>
                    </div>
            <div class="p-3 sm:p-4 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                <i class="fas fa-users text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>

    <!-- Male Students -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-lg hover:scale-105 transition-all duration-200 group">
                    <div class="flex items-center justify-between">
                        <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Male Students</p>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white"><?= $male_students ?></h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    <?= $total_students > 0 ? round(($male_students / $total_students) * 100, 1) : 0 ?>% of total
                </p>
                        </div>
            <div class="p-3 sm:p-4 rounded-full bg-gradient-to-br from-green-500 to-green-600 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                <i class="fas fa-mars text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                </div>

    <!-- Female Students -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-lg hover:scale-105 transition-all duration-200 group">
                    <div class="flex items-center justify-between">
                        <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Female Students</p>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white"><?= $female_students ?></h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    <?= $total_students > 0 ? round(($female_students / $total_students) * 100, 1) : 0 ?>% of total
                </p>
                        </div>
            <div class="p-3 sm:p-4 rounded-full bg-gradient-to-br from-pink-500 to-pink-600 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                <i class="fas fa-venus text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                </div>

    <!-- Youth vs Under 18 -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-lg hover:scale-105 transition-all duration-200 group">
                    <div class="flex items-center justify-between">
                        <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Youth (18+)</p>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white"><?= $youth_students ?></h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    <?= $under_18_students ?> under 18
                </p>
                        </div>
            <div class="p-3 sm:p-4 rounded-full bg-gradient-to-br from-purple-500 to-purple-600 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                <i class="fas fa-graduation-cap text-xl sm:text-2xl"></i>
                        </div>
                    </div>
    </div>
                </div>

<!-- Class Management Statistics -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <!-- Total Classes -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-lg hover:scale-105 transition-all duration-200 group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Classes</p>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white" id="total-classes">-</h3>
                <p class="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center">
                    <i class="fas fa-chalkboard mr-1"></i>
                    Class Management
                </p>
            </div>
            <div class="p-3 sm:p-4 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                <i class="fas fa-chalkboard text-xl sm:text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Active Students -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-lg hover:scale-105 transition-all duration-200 group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Enrolled Students</p>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white" id="enrolled-students">-</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Currently enrolled
                </p>
            </div>
            <div class="p-3 sm:p-4 rounded-full bg-gradient-to-br from-teal-500 to-teal-600 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                <i class="fas fa-user-graduate text-xl sm:text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Active Teachers -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-lg hover:scale-105 transition-all duration-200 group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Active Teachers</p>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white" id="active-teachers">-</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Currently teaching
                </p>
            </div>
            <div class="p-3 sm:p-4 rounded-full bg-gradient-to-br from-amber-500 to-amber-600 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                <i class="fas fa-chalkboard-teacher text-xl sm:text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Capacity Utilization -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-lg hover:scale-105 transition-all duration-200 group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Avg. Capacity</p>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white" id="avg-capacity">-</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Class utilization
                </p>
            </div>
            <div class="p-3 sm:p-4 rounded-full bg-gradient-to-br from-rose-500 to-rose-600 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                <i class="fas fa-chart-line text-xl sm:text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions (moved above charts) -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mb-6 sm:mb-8">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a href="registration.php" class="flex flex-col items-center p-3 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 rounded-lg hover:shadow-md transition-all group">
            <div class="p-2 bg-blue-500 text-white rounded-lg group-hover:scale-110 transition-transform">
                <i class="fas fa-user-plus text-lg"></i>
            </div>
            <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Add Student</span>
        </a>
        <a href="students.php" class="flex flex-col items-center p-3 bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-800/30 rounded-lg hover:shadow-md transition-all group">
            <div class="p-2 bg-green-500 text-white rounded-lg group-hover:scale-110 transition-transform">
                <i class="fas fa-users text-lg"></i>
            </div>
            <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Manage Students</span>
        </a>
        <a href="classes.php" class="flex flex-col items-center p-3 bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-900/30 dark:to-indigo-800/30 rounded-lg hover:shadow-md transition-all group">
            <div class="p-2 bg-indigo-500 text-white rounded-lg group-hover:scale-110 transition-transform">
                <i class="fas fa-chalkboard text-lg"></i>
            </div>
            <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Manage Classes</span>
        </a>
        <a href="teachers.php" class="flex flex-col items-center p-3 bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/30 dark:to-amber-800/30 rounded-lg hover:shadow-md transition-all group">
            <div class="p-2 bg-amber-500 text-white rounded-lg group-hover:scale-110 transition-transform">
                <i class="fas fa-chalkboard-teacher text-lg"></i>
            </div>
            <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Manage Teachers</span>
        </a>
    </div>
</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 sm:mb-8">
    <!-- Grade Distribution Chart -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Grade Distribution</h3>
            <button onclick="refreshDashboardStats()" class="p-1.5 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <i class="fas fa-sync-alt text-sm"></i>
            </button>
        </div>
        <div class="h-64">
            <canvas id="gradeDistributionChart"></canvas>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Registrations</h3>
        <div class="space-y-3">
            <?php if (empty($recent_students)): ?>
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-user-plus text-2xl mb-2"></i>
                    <p>No recent registrations</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_students as $student): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors cursor-pointer" 
                     onclick="viewStudentDetails(<?= $student['id'] ?>)">
                    <div class="flex items-center space-x-3">
                        <?php if (!empty($student['photo_path']) && file_exists($student['photo_path'])): ?>
                            <img src="<?= htmlspecialchars($student['photo_path']) ?>" alt="Photo" class="w-10 h-10 rounded-full object-cover">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-medium">
                                <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($student['full_name']) ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <?= htmlspecialchars($student['current_grade'] ?: 'N/A') ?> • <?= date('M j', strtotime($student['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 text-sm"></i>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php
$dashboard_content = ob_get_clean();

// Initialize Chart.js if needed
$dashboard_script = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize dashboard charts
document.addEventListener("DOMContentLoaded", function() {
    initializeDashboard();
    
    // Grade distribution chart
    const gradeCtx = document.getElementById("gradeDistributionChart");
    if (gradeCtx) {
        const gradeData = ' . json_encode(array_values($grade_distribution)) . ';
        const gradeLabels = ' . json_encode(array_keys($grade_distribution)) . ';
        
        new Chart(gradeCtx, {
            type: "bar",
            data: {
                labels: gradeLabels,
                datasets: [{
                    label: "Students",
                    data: gradeData,
                    backgroundColor: [
                        "rgba(59, 130, 246, 0.7)",
                        "rgba(34, 197, 94, 0.7)",
                        "rgba(245, 158, 11, 0.7)",
                        "rgba(168, 85, 247, 0.7)",
                        "rgba(239, 68, 68, 0.7)",
                        "rgba(14, 165, 233, 0.7)"
                    ],
                    borderColor: [
                        "rgb(59, 130, 246)",
                        "rgb(34, 197, 94)",
                        "rgb(245, 158, 11)",
                        "rgb(168, 85, 247)",
                        "rgb(239, 68, 68)",
                        "rgb(14, 165, 233)"
                    ],
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
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
});

function initializeDashboard() {
    // Initialize tooltips
    document.querySelectorAll("[title]").forEach(el => {
        el.addEventListener("mouseenter", showTooltip);
        el.addEventListener("mouseleave", hideTooltip);
    });
    
    // Initialize advanced features
    setupKeyboardShortcuts();
    setupRealTimeUpdates();
}

function animateStatistics() {
    const statElements = document.querySelectorAll(".text-2xl, .text-3xl");
    statElements.forEach((el, index) => {
        const finalValue = parseInt(el.textContent);
        if (!isNaN(finalValue)) {
            animateValue(el, 0, finalValue, 1000 + (index * 200));
        }
    });
}

function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const currentValue = Math.floor(progress * (end - start) + start);
        element.textContent = currentValue;
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

function setupKeyboardShortcuts() {
    document.addEventListener("keydown", function(e) {
        if (e.ctrlKey || e.metaKey) {
            switch(e.key) {
                case "n":
                e.preventDefault();
                    window.location.href = "index.php";
                    break;
                case "f":
                    e.preventDefault();
                    const searchInput = document.getElementById("quick-search");
                    if (searchInput) searchInput.focus();
                    break;
                case "m":
                    e.preventDefault();
                    window.location.href = "students.php";
                    break;
            }
        }
    });
}

function setupRealTimeUpdates() {
    // Setup EventSource for real-time updates if needed
    // This is a placeholder for future real-time functionality
    console.log("Real-time updates initialized");
}

function performQuickSearch(query) {
    if (query.length < 2) return;
    
    showToast("Searching...", "info");
    
    // Simulate search API call
    setTimeout(() => {
        window.location.href = `students.php?search=${encodeURIComponent(query)}`;
    }, 500);
}

function refreshDashboardStats() {
    fetch("api/dashboard_stats.php")
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboardStats(data.stats);
                showToast("Dashboard updated", "success");
            }
        })
        .catch(error => {
            console.log("Failed to refresh dashboard stats");
        });
}

function updateDashboardStats(stats) {
    // Update dashboard statistics in real-time
    Object.keys(stats).forEach(key => {
        const element = document.getElementById(`stat-${key}`);
        if (element) {
            animateValue(element, parseInt(element.textContent), stats[key], 800);
        }
    });
}

function showAdvancedConfirmation(title, message, type, callback) {
    const modal = document.createElement("div");
    modal.className = "fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50";
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full animate-scale-in">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="p-3 rounded-full ${type === "danger" ? "bg-red-100 text-red-600" : "bg-blue-100 text-blue-600"}">
                        <i class="fas ${type === "danger" ? "fa-exclamation-triangle" : "fa-question-circle"} text-xl"></i>
                        </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">${title}</h3>
                            </div>
                <p class="text-gray-600 dark:text-gray-400 mb-6">${message}</p>
                <div class="flex space-x-3 justify-end">
                    <button onclick="this.closest(\'div[class*=\"fixed\"]\'). remove()" 
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancel
                        </button>
                    <button onclick="${type === "danger" ? "this.closest(\'div[class*=\"fixed\"]\'). remove(); (" + callback.toString() + ")()" : "this.closest(\'div[class*=\"fixed\"]\'). remove(); (" + callback.toString() + ")()"}"
                            class="px-4 py-2 ${type === "danger" ? "bg-red-600 hover:bg-red-700" : "bg-blue-600 hover:bg-blue-700"} text-white rounded-lg transition-colors">
                        ${type === "danger" ? "Delete" : "Confirm"}
                    </button>
                    </div>
                </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Auto-remove on backdrop click
    modal.addEventListener("click", function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

function showTooltip(e) {
    const tooltip = document.createElement("div");
    tooltip.className = "absolute z-50 px-2 py-1 text-xs text-white bg-gray-900 rounded shadow-lg pointer-events-none";
    tooltip.textContent = e.target.getAttribute("title");
    tooltip.style.top = (e.pageY - 30) + "px";
    tooltip.style.left = e.pageX + "px";
    e.target.removeAttribute("title");
    e.target.setAttribute("data-title", tooltip.textContent);
    document.body.appendChild(tooltip);
    e.target.tooltip = tooltip;
}

function hideTooltip(e) {
    if (e.target.tooltip) {
        e.target.tooltip.remove();
        e.target.tooltip = null;
        e.target.setAttribute("title", e.target.getAttribute("data-title"));
        e.target.removeAttribute("data-title");
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Global keyboard shortcuts info
function showKeyboardShortcuts() {
    showToast("Keyboard shortcuts: Ctrl+N (New Student), Ctrl+F (Search), Ctrl+M (Manage Students)", "info");
}

// Add keyboard shortcuts help
document.addEventListener("keydown", function(e) {
    if (e.key === "?" && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
        showKeyboardShortcuts();
    }
});

// Fetch class management statistics
function fetchClassStats() {
    fetch(\'api/class_dashboard.php\')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update class statistics
                document.getElementById(\'total-classes\').textContent = data.class_stats.total_classes || 0;
                document.getElementById(\'enrolled-students\').textContent = data.enrollment_stats.active_students || 0;
                document.getElementById(\'active-teachers\').textContent = data.teacher_stats.active_teachers || 0;
                
                // Calculate average capacity utilization
                let totalUtilization = 0;
                let classCount = data.capacity_utilization.length;
                data.capacity_utilization.forEach(classData => {
                    totalUtilization += parseFloat(classData.utilization_percent) || 0;
                });
                
                const avgUtilization = classCount > 0 ? (totalUtilization / classCount).toFixed(1) : 0;
                document.getElementById(\'avg-capacity\').textContent = avgUtilization + \'%\';
            }
        })
        .catch(error => {
            console.error(\'Error fetching class stats:\', error);
        });
}

// Load class statistics when page loads
document.addEventListener(\'DOMContentLoaded\', function() {
    fetchClassStats();
});
</script>
';

// Render the complete page using the admin layout
echo renderAdminLayout('Dashboard - Student Management System', $dashboard_content, $dashboard_script);
?>