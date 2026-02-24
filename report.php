<?php
require_once 'config.php';
require_once 'includes/admin_layout.php';

// Check if user is logged in and is admin
requireAdminLogin();

// Start output buffering for content
ob_start();

try {
    // Database connection using existing PDO from config.php
    // The config.php already creates a $pdo instance, so we can use it directly
    
    // Get total student count
    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $total_students = $stmt->fetchColumn();

    // Gender distribution
    $stmt = $pdo->query("SELECT gender, COUNT(*) as count FROM students GROUP BY gender");
    $gender_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $male_students = 0;
    $female_students = 0;
    foreach ($gender_data as $row) {
        if ($row['gender'] === 'male') $male_students = $row['count'];
        if ($row['gender'] === 'female') $female_students = $row['count'];
    }

    // Grade distribution - using current_grade field
    $stmt = $pdo->query("SELECT current_grade, COUNT(*) as count FROM students GROUP BY current_grade ORDER BY current_grade");
    $grade_distribution = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $grade_distribution[$row['current_grade']] = $row['count'];
    }

    // Age distribution
    $stmt = $pdo->query("SELECT birth_date FROM students WHERE birth_date IS NOT NULL");
    $birth_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $age_groups = ['Under 10' => 0, '10-15' => 0, '16-20' => 0, 'Over 20' => 0];
    $current_year = 2017; // Current Ethiopian year

    foreach ($birth_dates as $birth_date) {
        if ($birth_date) {
            $birth_year = (int) substr($birth_date, 0, 4);
            $age = $current_year - $birth_year;
            
            if ($age < 10) $age_groups['Under 10']++;
            elseif ($age >= 10 && $age <= 15) $age_groups['10-15']++;
            elseif ($age >= 16 && $age <= 20) $age_groups['16-20']++;
            else $age_groups['Over 20']++;
        }
    }

    // Sub City distribution
    $stmt = $pdo->query("SELECT sub_city, COUNT(*) as count FROM students WHERE sub_city IS NOT NULL GROUP BY sub_city ORDER BY count DESC");
    $sub_city_distribution = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sub_city_distribution[$row['sub_city']] = $row['count'];
    }

    // Living arrangements
    $stmt = $pdo->query("SELECT living_with, COUNT(*) as count FROM students WHERE living_with IS NOT NULL GROUP BY living_with");
    $living_with_distribution = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $living_with_distribution[$row['living_with']] = $row['count'];
    }

    // Disability statistics - using physical_disability field
    $stmt = $pdo->query("SELECT 
        CASE 
            WHEN physical_disability IS NULL OR physical_disability = '' OR physical_disability = 'የለም' OR physical_disability = 'None' THEN 'No'
            ELSE 'Yes'
        END as has_disability,
        COUNT(*) as count 
        FROM students 
        GROUP BY 
        CASE 
            WHEN physical_disability IS NULL OR physical_disability = '' OR physical_disability = 'የለም' OR physical_disability = 'None' THEN 'No'
            ELSE 'Yes'
        END");
    $disability_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $disability_stats = ['No' => 0, 'Yes' => 0];
    foreach ($disability_data as $row) {
        $disability_stats[$row['has_disability']] = $row['count'];
    }

    // Spiritual father statistics - using has_spiritual_father field
    $stmt = $pdo->query("SELECT has_spiritual_father, COUNT(*) as count FROM students GROUP BY has_spiritual_father");
    $spiritual_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $spiritual_father_stats = ['Has' => 0, 'No' => 0];
    foreach ($spiritual_data as $row) {
        if ($row['has_spiritual_father'] === 'own' || $row['has_spiritual_father'] === 'family') {
            $spiritual_father_stats['Has'] += $row['count'];
        } else {
            $spiritual_father_stats['No'] += $row['count'];
        }
    }

    // Instrumental students statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM instrument_registrations");
    $total_instrument_students = $stmt->fetchColumn();

    // Instrument distribution
    $stmt = $pdo->query("SELECT instrument, COUNT(*) as count FROM instrument_registrations GROUP BY instrument ORDER BY count DESC");
    $instrument_distribution = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $instrument_distribution[$row['instrument']] = $row['count'];
    }

    // Instrument gender distribution
    $stmt = $pdo->query("SELECT gender, COUNT(*) as count FROM instrument_registrations GROUP BY gender");
    $instrument_gender_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $male_instrument_students = 0;
    $female_instrument_students = 0;
    foreach ($instrument_gender_data as $row) {
        if (strtolower($row['gender']) === 'male') $male_instrument_students = $row['count'];
        if (strtolower($row['gender']) === 'female') $female_instrument_students = $row['count'];
    }

    // Student registration trends by month
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM students WHERE created_at IS NOT NULL GROUP BY month ORDER BY month DESC LIMIT 12");
    $registration_trends = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $registration_trends[$row['month']] = $row['count'];
    }

    // Instrument registration trends
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM instrument_registrations WHERE created_at IS NOT NULL GROUP BY month ORDER BY month DESC LIMIT 12");
    $instrument_trends = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $instrument_trends[$row['month']] = $row['count'];
    }

    // Get unique living arrangements from database for filters
    $stmt = $pdo->query("SELECT DISTINCT living_with FROM students WHERE living_with IS NOT NULL AND living_with != '' ORDER BY living_with");
    $living_arrangements = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // Add fallback values if no data
    if (empty($living_arrangements)) {
        $living_arrangements = ['parents', 'relative', 'guardian', 'alone'];
    }

    // Get unique spiritual father values for filters  
    $stmt = $pdo->query("SELECT DISTINCT has_spiritual_father FROM students WHERE has_spiritual_father IS NOT NULL AND has_spiritual_father != '' ORDER BY has_spiritual_father");
    $spiritual_father_values = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // Add fallback values if no data
    if (empty($spiritual_father_values)) {
        $spiritual_father_values = ['own', 'family', 'none'];
    }

    // Get unique gender values from database for filters
    $stmt = $pdo->query("SELECT DISTINCT gender FROM students WHERE gender IS NOT NULL AND gender != '' ORDER BY gender");
    $gender_values = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // Add fallback values if no data
    if (empty($gender_values)) {
        $gender_values = ['male', 'female'];
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    // Set default values
    $total_students = 0;
    $male_students = 0;
    $female_students = 0;
    $grade_distribution = [];
    $age_groups = ['Under 10' => 0, '10-15' => 0, '16-20' => 0, 'Over 20' => 0];
    $sub_city_distribution = [];
    $living_with_distribution = [];
    $spiritual_father_stats = ['Has' => 0, 'No' => 0];
    $disability_stats = ['No' => 0, 'Yes' => 0];
    $total_instrument_students = 0;
    $instrument_distribution = [];
    $male_instrument_students = 0;
    $female_instrument_students = 0;
    $registration_trends = [];
    $instrument_trends = [];
    $living_arrangements = ['parents', 'relative', 'guardian'];
    $spiritual_father_values = ['own', 'family', 'none'];
    $gender_values = ['male', 'female'];
    
    // Display error for debugging
    echo "<div class='alert alert-danger'>Debug: " . htmlspecialchars($error_message) . "</div>";
}


?>

<!-- Student Analytics Dashboard -->
<div x-data="{activeTab: 'overview', mobileMenuOpen: false}" class="space-y-2 sm:space-y-3 p-2 sm:p-0">
    <!-- Tab Navigation -->
    <div class="bg-white dark:bg-gray-800 rounded shadow-sm">
        <!-- Mobile Tab Dropdown (visible on small screens) -->
        <div class="sm:hidden border-b border-gray-200 dark:border-gray-700">
            <div class="px-3 py-2">
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="w-full flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    <span class="flex items-center">
                        <i :class="{
                            'fas fa-chart-bar': activeTab === 'overview',
                            'fas fa-users': activeTab === 'demographics', 
                            'fas fa-graduation-cap': activeTab === 'academic',
                            'fas fa-map-marker-alt': activeTab === 'geographic',
                            'fas fa-home': activeTab === 'family',
                            'fas fa-praying-hands': activeTab === 'spiritual',
                            'fas fa-music': activeTab === 'instruments',
                            'fas fa-file-export': activeTab === 'reports'
                        }" class="mr-2"></i>
                        <span x-text="{
                            'overview': 'Overview',
                            'demographics': 'Demographics',
                            'academic': 'Academic',
                            'geographic': 'Geographic', 
                            'family': 'Family',
                            'spiritual': 'Spiritual',
                            'instruments': 'Instruments',
                            'reports': 'Reports'
                        }[activeTab]"></span>
                    </span>
                    <i class="fas fa-chevron-down transition-transform" :class="{'rotate-180': mobileMenuOpen}"></i>
                </button>
                
                <!-- Mobile Dropdown Menu -->
                <div x-show="mobileMenuOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 transform scale-100" x-transition:leave-end="opacity-0 transform scale-95" class="mt-2 bg-white dark:bg-gray-800 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                    <button @click="activeTab = 'overview'; mobileMenuOpen = false" :class="{'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300': activeTab === 'overview'}" class="w-full text-left px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 transition-colors">
                        <i class="fas fa-chart-bar mr-2 w-3"></i>Overview
                    </button>
                    <button @click="activeTab = 'demographics'; mobileMenuOpen = false" :class="{'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300': activeTab === 'demographics'}" class="w-full text-left px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 transition-colors">
                        <i class="fas fa-users mr-2 w-3"></i>Demographics
                    </button>
                    <button @click="activeTab = 'academic'; mobileMenuOpen = false" :class="{'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300': activeTab === 'academic'}" class="w-full text-left px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 transition-colors">
                        <i class="fas fa-graduation-cap mr-2 w-3"></i>Academic
                    </button>
                    <button @click="activeTab = 'geographic'; mobileMenuOpen = false" :class="{'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300': activeTab === 'geographic'}" class="w-full text-left px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 transition-colors">
                        <i class="fas fa-map-marker-alt mr-2 w-3"></i>Geographic
                    </button>
                    <button @click="activeTab = 'family'; mobileMenuOpen = false" :class="{'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300': activeTab === 'family'}" class="w-full text-left px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 transition-colors">
                        <i class="fas fa-home mr-2 w-3"></i>Family
                    </button>
                    <button @click="activeTab = 'spiritual'; mobileMenuOpen = false" :class="{'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300': activeTab === 'spiritual'}" class="w-full text-left px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 transition-colors">
                        <i class="fas fa-praying-hands mr-2 w-3"></i>Spiritual
                    </button>
                    <button @click="activeTab = 'instruments'; mobileMenuOpen = false" :class="{'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300': activeTab === 'instruments'}" class="w-full text-left px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 transition-colors">
                        <i class="fas fa-music mr-2 w-3"></i>Instruments
                    </button>
                    <button @click="activeTab = 'reports'; mobileMenuOpen = false" :class="{'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300': activeTab === 'reports'}" class="w-full text-left px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-file-export mr-2 w-3"></i>Reports
                    </button>
                </div>
            </div>
        </div>

        <!-- Desktop/Tablet Horizontal Scrollable Tabs (hidden on mobile) -->
        <div class="hidden sm:block border-b border-gray-200 dark:border-gray-700">
            <div class="px-3 sm:px-4">
                <nav class="-mb-px flex space-x-1 md:space-x-2 lg:space-x-4 overflow-x-auto scrollbar-hide" aria-label="Tabs" style="-webkit-overflow-scrolling: touch;">
                    <button @click="activeTab = 'overview'" :class="{'border-blue-500 text-blue-600': activeTab === 'overview', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'overview'}" class="flex-shrink-0 py-2 md:py-3 px-1 md:px-2 border-b-2 font-medium text-xs whitespace-nowrap transition-colors">
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span class="hidden lg:inline">Overview</span>
                        <span class="lg:hidden">Over</span>
                    </button>
                    <button @click="activeTab = 'demographics'" :class="{'border-blue-500 text-blue-600': activeTab === 'demographics', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'demographics'}" class="flex-shrink-0 py-2 md:py-3 px-1 md:px-2 border-b-2 font-medium text-xs whitespace-nowrap transition-colors">
                        <i class="fas fa-users mr-1"></i>
                        <span class="hidden lg:inline">Demographics</span>
                        <span class="lg:hidden">Demo</span>
                    </button>
                    <button @click="activeTab = 'academic'" :class="{'border-blue-500 text-blue-600': activeTab === 'academic', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'academic'}" class="flex-shrink-0 py-2 md:py-3 px-1 md:px-2 border-b-2 font-medium text-xs whitespace-nowrap transition-colors">
                        <i class="fas fa-graduation-cap mr-1"></i>
                        <span class="hidden lg:inline">Academic</span>
                        <span class="lg:hidden">Acad</span>
                    </button>
                    <button @click="activeTab = 'geographic'" :class="{'border-blue-500 text-blue-600': activeTab === 'geographic', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'geographic'}" class="flex-shrink-0 py-2 md:py-3 px-1 md:px-2 border-b-2 font-medium text-xs whitespace-nowrap transition-colors">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        <span class="hidden lg:inline">Geographic</span>
                        <span class="lg:hidden">Geo</span>
                    </button>
                    <button @click="activeTab = 'family'" :class="{'border-blue-500 text-blue-600': activeTab === 'family', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'family'}" class="flex-shrink-0 py-2 md:py-3 px-1 md:px-2 border-b-2 font-medium text-xs whitespace-nowrap transition-colors">
                        <i class="fas fa-home mr-1"></i>
                        <span class="hidden lg:inline">Family</span>
                        <span class="lg:hidden">Fam</span>
                    </button>
                    <button @click="activeTab = 'spiritual'" :class="{'border-blue-500 text-blue-600': activeTab === 'spiritual', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'spiritual'}" class="flex-shrink-0 py-2 md:py-3 px-1 md:px-2 border-b-2 font-medium text-xs whitespace-nowrap transition-colors">
                        <i class="fas fa-praying-hands mr-1"></i>
                        <span class="hidden lg:inline">Spiritual</span>
                        <span class="lg:hidden">Spir</span>
                    </button>
                    <button @click="activeTab = 'instruments'" :class="{'border-blue-500 text-blue-600': activeTab === 'instruments', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'instruments'}" class="flex-shrink-0 py-2 md:py-3 px-1 md:px-2 border-b-2 font-medium text-xs whitespace-nowrap transition-colors">
                        <i class="fas fa-music mr-1"></i>
                        <span class="hidden lg:inline">Instruments</span>
                        <span class="lg:hidden">Inst</span>
                    </button>
                    <button @click="activeTab = 'reports'" :class="{'border-blue-500 text-blue-600': activeTab === 'reports', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'reports'}" class="flex-shrink-0 py-2 md:py-3 px-1 md:px-2 border-b-2 font-medium text-xs whitespace-nowrap transition-colors">
                        <i class="fas fa-file-export mr-1"></i>
                        <span class="hidden lg:inline">Reports</span>
                        <span class="lg:hidden">Rep</span>
                    </button>
                </nav>
            </div>
        </div>
    </div>

    <!-- Overview Tab -->
    <div x-show="activeTab === 'overview'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-3">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Total</p>
                        <p class="text-lg sm:text-xl font-bold text-blue-600"><?= number_format($total_students) ?></p>
                    </div>
                    <div class="p-1 bg-blue-100 dark:bg-blue-900 rounded">
                        <i class="fas fa-users text-blue-600 text-sm"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Male</p>
                        <p class="text-lg sm:text-xl font-bold text-green-600"><?= number_format($male_students) ?></p>
                    </div>
                    <div class="p-1 bg-green-100 dark:bg-green-900 rounded">
                        <i class="fas fa-mars text-green-600 text-sm"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Female</p>
                        <p class="text-lg sm:text-xl font-bold text-pink-600"><?= number_format($female_students) ?></p>
                    </div>
                    <div class="p-1 bg-pink-100 dark:bg-pink-900 rounded">
                        <i class="fas fa-venus text-pink-600 text-sm"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Music</p>
                        <p class="text-lg sm:text-xl font-bold text-purple-600"><?= number_format($total_instrument_students) ?></p>
                    </div>
                    <div class="p-1 bg-purple-100 dark:bg-purple-900 rounded">
                        <i class="fas fa-music text-purple-600 text-sm"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-venus-mars mr-1 text-blue-500 text-xs"></i>
                    <span class="hidden sm:inline">Gender</span>
                    <span class="sm:hidden">M/F</span>
                </h2>
                <div class="chart-container">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-chart-bar mr-1 text-green-500 text-xs"></i>
                    <span class="hidden sm:inline">Grades</span>
                    <span class="sm:hidden">Gr</span>
                </h2>
                <div class="chart-container">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Demographics Tab -->
    <div x-show="activeTab === 'demographics'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-birthday-cake mr-1 text-orange-500 text-xs"></i>
                    Age
                </h2>
                <div class="chart-container">
                    <canvas id="ageChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-wheelchair mr-1 text-red-500 text-xs"></i>
                    Disability
                </h2>
                <div class="chart-container">
                    <canvas id="disabilityChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2">Age Stats</h2>
                <div class="space-y-1">
                    <?php foreach ($age_groups as $group => $count): ?>
                    <div class="flex justify-between">
                        <span class="text-xs"><?= $group ?></span>
                        <span class="text-xs font-medium"><?= $count ?> (<?= $total_students > 0 ? round(($count / $total_students) * 100, 1) : 0 ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2">Disability Stats</h2>
                <div class="space-y-1">
                    <?php foreach ($disability_stats as $status => $count): ?>
                    <div class="flex justify-between">
                        <span class="text-xs"><?= $status ?> Disability</span>
                        <span class="text-xs font-medium"><?= $count ?> (<?= $total_students > 0 ? round(($count / $total_students) * 100, 1) : 0 ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Tab -->
    <div x-show="activeTab === 'academic'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-school mr-1 text-indigo-500 text-xs"></i>
                    Grade Levels
                </h2>
                <div class="chart-container">
                    <canvas id="gradeChartAcademic"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2">Academic Stats</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-1 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Grade</th>
                                <th class="px-3 py-1 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Students</th>
                                <th class="px-3 py-1 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">%</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($grade_distribution as $grade => $count): ?>
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap text-xs font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($grade) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400"><?= $count ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    <?= $total_students > 0 ? round(($count / $total_students) * 100, 1) : 0 ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Geographic Tab -->
    <div x-show="activeTab === 'geographic'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-map-marker-alt mr-1 text-yellow-500 text-xs"></i>
                    Sub Cities
                </h2>
                <div class="chart-container">
                    <canvas id="subCityChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2">Location Stats</h2>
                <div class="space-y-2">
                    <div>
                        <h3 class="font-medium mb-1 text-xs">Top Sub Cities</h3>
                        <?php $top_cities = array_slice($sub_city_distribution, 0, 5, true); ?>
                        <?php foreach ($top_cities as $city => $count): ?>
                        <div class="flex justify-between py-0.5">
                            <span class="text-xs"><?= htmlspecialchars($city) ?></span>
                            <span class="text-xs font-medium"><?= $count ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Family Tab -->
    <div x-show="activeTab === 'family'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-home mr-1 text-green-500 text-xs"></i>
                    Living Arrangements
                </h2>
                <div class="chart-container">
                    <canvas id="livingChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2">Family Stats</h2>
                <div class="space-y-1">
                    <?php foreach ($living_with_distribution as $living => $count): ?>
                    <div class="flex justify-between">
                        <span class="text-xs"><?= ucfirst(str_replace('_', ' ', $living)) ?></span>
                        <span class="text-xs font-medium"><?= $count ?> (<?= $total_students > 0 ? round(($count / $total_students) * 100, 1) : 0 ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Spiritual Tab -->
    <div x-show="activeTab === 'spiritual'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-praying-hands mr-1 text-purple-500 text-xs"></i>
                    Spiritual Father
                </h2>
                <div class="chart-container">
                    <canvas id="spiritualChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2">Spiritual Stats</h2>
                <div class="space-y-1">
                    <?php foreach ($spiritual_father_stats as $status => $count): ?>
                    <div class="flex justify-between">
                        <span class="text-xs"><?= $status ?> Spiritual Father</span>
                        <span class="text-xs font-medium"><?= $count ?> (<?= $total_students > 0 ? round(($count / $total_students) * 100, 1) : 0 ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Instruments Tab -->
    <div x-show="activeTab === 'instruments'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <!-- Summary Cards for Instruments -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-3">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Total Music</p>
                        <p class="text-lg sm:text-xl font-bold text-blue-600"><?= number_format($total_instrument_students) ?></p>
                    </div>
                    <div class="p-1 bg-blue-100 dark:bg-blue-900 rounded">
                        <i class="fas fa-music text-blue-600 text-sm"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Male Music</p>
                        <p class="text-lg sm:text-xl font-bold text-green-600"><?= number_format($male_instrument_students) ?></p>
                    </div>
                    <div class="p-1 bg-green-100 dark:bg-green-900 rounded">
                        <i class="fas fa-mars text-green-600 text-sm"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Female Music</p>
                        <p class="text-lg sm:text-xl font-bold text-pink-600"><?= number_format($female_instrument_students) ?></p>
                    </div>
                    <div class="p-1 bg-pink-100 dark:bg-pink-900 rounded">
                        <i class="fas fa-venus text-pink-600 text-sm"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Types</p>
                        <p class="text-lg sm:text-xl font-bold text-purple-600"><?= count($instrument_distribution) ?></p>
                    </div>
                    <div class="p-1 bg-purple-100 dark:bg-purple-900 rounded">
                        <i class="fas fa-guitar text-purple-600 text-sm"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid for Instruments -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-music mr-1 text-blue-500 text-xs"></i>
                    Instruments
                </h2>
                <div class="chart-container">
                    <canvas id="instrumentChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2 flex items-center">
                    <i class="fas fa-venus-mars mr-1 text-green-500 text-xs"></i>
                    Music Gender
                </h2>
                <div class="chart-container">
                    <canvas id="instrumentGenderChart"></canvas>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2">Instrument Stats</h2>
                <div class="space-y-1 max-h-48 overflow-y-auto">
                    <?php foreach ($instrument_distribution as $instrument => $count): ?>
                    <div class="flex justify-between">
                        <span class="text-xs"><?= htmlspecialchars($instrument) ?></span>
                        <span class="text-xs font-medium"><?= $count ?> (<?= $total_instrument_students > 0 ? round(($count / $total_instrument_students) * 100, 1) : 0 ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-3">
                <h2 class="text-sm font-semibold mb-2">Registration Trends</h2>
                <div class="chart-container">
                    <canvas id="instrumentTrendsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Tab -->
    <div x-show="activeTab === 'reports'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div id="reports-tab-content" x-data="{
            filteredStudents: [],
            loading: false,
            filters: {
                grade: '',
                gender: '',
                birthMonth: '',
                instrument: '',
                spiritualFather: '',
                subCity: '',
                livingWith: '',
                disability: ''
            },
            availableFilters: {
                grades: [],
                instruments: [],
                subCities: [],
                livingArrangements: [],
                spiritualFatherValues: [],
                genderValues: [],
                months: [
                    {value: '01', name: 'መስከረም'},
                    {value: '02', name: 'ጥቅምት'},
                    {value: '03', name: 'ኅዳር'},
                    {value: '04', name: 'ታኅሳስ'},
                    {value: '05', name: 'ጥር'},
                    {value: '06', name: 'የካቲት'},
                    {value: '07', name: 'መጋቢት'},
                    {value: '08', name: 'ሚያዝያ'},
                    {value: '09', name: 'ግንቦት'},
                    {value: '10', name: 'ሰኔ'},
                    {value: '11', name: 'ሐምሌ'},
                    {value: '12', name: 'ነሐሴ'},
                    {value: '13', name: 'ጳጉሜን'}
                ]
            },
            showFilters: true,
            
            init() {
                // Initialize with data from window.filterData when available
                if (window.filterData) {
                    console.log('💾 Loading filter data from window.filterData:', window.filterData);
                    this.availableFilters.grades = window.filterData.grades || [];
                    this.availableFilters.instruments = window.filterData.instruments || [];
                    this.availableFilters.subCities = window.filterData.subCities || [];
                    this.availableFilters.livingArrangements = window.filterData.livingArrangements || [];
                    this.availableFilters.genderValues = window.filterData.genderValues || [];
                    this.availableFilters.spiritualFatherValues = window.filterData.spiritualFatherValues || [];
                } else {
                    console.warn('⚠️ window.filterData not available, using fallback data');
                    // Fallback data
                    this.availableFilters.grades = ['new', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
                    this.availableFilters.instruments = ['begena', 'masenqo', 'kebero', 'krar'];
                    this.availableFilters.subCities = ['ለሚ ኩራ', 'አዲስ አበባ', 'ባህር ዳር'];
                    this.availableFilters.livingArrangements = ['both_parents', 'father_only', 'mother_only', 'relative_or_guardian'];
                    this.availableFilters.genderValues = ['male', 'female'];
                    this.availableFilters.spiritualFatherValues = ['own', 'family', 'none'];
                }
                console.log('🔧 Alpine.js filter data initialized:', this.availableFilters);
                
                // Test basic connectivity by getting all students
                this.testBasicQuery();
            },
            
            async testBasicQuery() {
                console.log('🧪 Testing basic API connectivity...');
                try {
                    const response = await fetch('api/filter_students.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({})
                    });
                    const data = await response.json();
                    console.log('🧪 Basic query result:', data);
                    if (data.success && data.students && data.students.length > 0) {
                        console.log('✅ API working - found', data.students.length, 'total students');
                        console.log('📄 Sample student data:', data.students[0]);
                    } else {
                        console.warn('⚠️ API returned no students or failed:', data);
                    }
                } catch (error) {
                    console.error('❌ API test failed:', error);
                }
            },
            
            async applyFilters() {
                this.loading = true;
                console.log('🔍 Applying filters:', this.filters);
                
                try {
                    const response = await fetch('api/filter_students.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(this.filters)
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const responseText = await response.text();
                    console.log('📜 Raw API response:', responseText);
                    
                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (jsonError) {
                        console.error('❌ JSON parsing failed. Raw response:', responseText);
                        throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}...`);
                    }
                    
                    console.log('📊 Parsed API response:', data);
                    
                    if (data.success) {
                        this.filteredStudents = data.students || [];
                        console.log(`✅ Found ${this.filteredStudents.length} students matching filters`);
                        if (this.filteredStudents.length > 0) {
                            console.log('📄 Sample result:', this.filteredStudents[0]);
                        } else {
                            console.log('🔍 Query info:', data.query_info);
                            console.log('🔍 Filters applied:', data.filters_applied);
                        }
                    } else {
                        console.error('❌ API returned error:', data.error);
                        alert(`Error: ${data.error}`);
                    }
                } catch (error) {
                    console.error('❌ Error filtering students:', error);
                    alert('Error loading students. Check console for details.');
                } finally {
                    this.loading = false;
                }
            },
            
            clearFilters() {
                this.filters = {
                    grade: '',
                    gender: '',
                    birthMonth: '',
                    instrument: '',
                    spiritualFather: '',
                    subCity: '',
                    livingWith: '',
                    disability: ''
                };
                this.filteredStudents = [];
            },
            
            async exportToExcel() {
                if (this.filteredStudents.length === 0) {
                    alert('Please apply filters to get students data before exporting.');
                    return;
                }
                
                try {
                    const response = await fetch('api/export_students.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            students: this.filteredStudents,
                            filters: this.filters
                        })
                    });
                    
                    if (response.ok) {
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = 'Finote_Selam_Students_' + new Date().toISOString().slice(0,19).replace(/[T:]/g, '-') + '.csv';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    } else {
                        throw new Error('Export failed');
                    }
                } catch (error) {
                    console.error('Error exporting to Excel:', error);
                    alert('Error exporting to Excel. Please try again.');
                }
            }
        }" class="space-y-2">
            

            
            <!-- Ultra-Compact Filter Controls -->
            <div class="bg-white dark:bg-gray-800 rounded shadow-sm p-2 border">
                <div class="flex justify-between items-center mb-2">
                    <h2 class="text-xs font-semibold flex items-center text-blue-600">
                        <i class="fas fa-filter mr-1 text-blue-500 text-xs"></i>
                        Filters (8)
                    </h2>
                    <div class="flex items-center space-x-1">
                        <span class="text-xs text-gray-500 bg-gray-100 px-1 py-0.5 rounded" x-show="filteredStudents.length > 0" x-text="filteredStudents.length + ' found'"></span>
                        <span class="text-xs text-green-600 bg-green-50 px-1 py-0.5 rounded">
                            <i class="fas fa-check text-xs"></i>
                        </span>
                    </div>
                </div>
                
                <!-- Ultra-Compact Filter Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-1 mb-2">
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Grade</label>
                        <select x-model="filters.grade" class="w-full px-1 py-1 border rounded text-xs focus:ring-1 focus:ring-blue-500">
                            <option value="">All</option>
                            <template x-for="grade in availableFilters.grades">
                                <option :value="grade" x-text="grade"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Gender</label>
                        <select x-model="filters.gender" class="w-full px-1 py-1 border rounded text-xs focus:ring-1 focus:ring-blue-500">
                            <option value="">All</option>
                            <template x-for="gender in availableFilters.genderValues">
                                <option :value="gender" x-text="gender.charAt(0).toUpperCase() + gender.slice(1)"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Month</label>
                        <select x-model="filters.birthMonth" class="w-full px-1 py-1 border rounded text-xs focus:ring-1 focus:ring-blue-500">
                            <option value="">All</option>
                            <template x-for="month in availableFilters.months">
                                <option :value="month.value" x-text="month.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Instrument</label>
                        <select x-model="filters.instrument" class="w-full px-1 py-1 border rounded text-xs focus:ring-1 focus:ring-blue-500">
                            <option value="">All</option>
                            <template x-for="instrument in availableFilters.instruments">
                                <option :value="instrument" x-text="instrument"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Spiritual</label>
                        <select x-model="filters.spiritualFather" class="w-full px-1 py-1 border rounded text-xs focus:ring-1 focus:ring-blue-500">
                            <option value="">All</option>
                            <option value="yes">Has</option>
                            <option value="no">None</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">City</label>
                        <select x-model="filters.subCity" class="w-full px-1 py-1 border rounded text-xs focus:ring-1 focus:ring-blue-500">
                            <option value="">All</option>
                            <template x-for="city in availableFilters.subCities">
                                <option :value="city" x-text="city"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Living</label>
                        <select x-model="filters.livingWith" class="w-full px-1 py-1 border rounded text-xs focus:ring-1 focus:ring-blue-500">
                            <option value="">All</option>
                            <template x-for="arrangement in availableFilters.livingArrangements">
                                <option :value="arrangement" x-text="arrangement.charAt(0).toUpperCase() + arrangement.slice(1).replace('_', ' ')"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 mb-1 block">Disability</label>
                        <select x-model="filters.disability" class="w-full px-1 py-1 border rounded text-xs focus:ring-1 focus:ring-blue-500">
                            <option value="">All</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                
                <!-- Ultra-Compact Action Buttons -->
                <div class="flex gap-1 justify-between">
                    <div class="flex gap-1">
                        <button @click="applyFilters()" :disabled="loading" class="px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 disabled:opacity-50">
                            <span x-text="loading ? 'Loading...' : 'Apply'"></span>
                        </button>
                        <button @click="clearFilters()" class="px-2 py-1 bg-gray-500 text-white rounded text-xs hover:bg-gray-600">
                            Clear
                        </button>
                    </div>
                    <button @click="exportToExcel()" :disabled="filteredStudents.length === 0" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700 disabled:opacity-50">
                        Export
                    </button>
                </div>
            </div>
            
            <!-- Attractive Loading State -->
            <div x-show="loading" x-transition class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-800 dark:to-gray-900 rounded-lg shadow-lg p-6 text-center border border-blue-200 dark:border-gray-700">
                <div class="flex flex-col items-center space-y-4">
                    <!-- School Logo -->
                    <div class="relative">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white shadow-lg flex items-center justify-center overflow-hidden border-4 border-blue-200">
                            <img src="uploads/689636ec11381_finot logo.png" alt="Finote Selam School" class="w-full h-full object-contain rounded-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                            <div class="hidden w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-lg sm:text-xl">
                                FS
                            </div>
                        </div>
                        <!-- Animated Ring -->
                        <div class="absolute inset-0 w-16 h-16 sm:w-20 sm:h-20 border-4 border-transparent border-t-blue-500 border-r-blue-400 rounded-full animate-spin"></div>
                    </div>
                    
                    <!-- Loading Content -->
                    <div class="space-y-2">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800 dark:text-white">Finote Selam School</h3>
                        <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Loading Student Data...</p>
                        <div class="flex items-center justify-center space-x-1 text-xs text-gray-500">
                            <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                            <div class="w-2 h-2 bg-blue-300 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                        </div>
                    </div>
                    
                    <!-- Progress Indicator -->
                    <div class="w-full max-w-xs">
                        <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full animate-pulse"></div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Please wait while we fetch your results...</p>
                    </div>
                </div>
            </div>
            
            <!-- Compact Results Section -->
            <div x-show="!loading && filteredStudents.length > 0" x-transition class="bg-white dark:bg-gray-800 rounded shadow-sm overflow-hidden">
                <div class="px-3 py-2 border-b bg-gray-50 dark:bg-gray-700 flex justify-between items-center">
                    <h3 class="text-sm font-medium">Results</h3>
                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded" x-text="filteredStudents.length + ' students'"></span>
                </div>
                
                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto max-h-80">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                            <tr>
                                <th class="px-2 py-1 text-left text-xs uppercase">Student</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Phone</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Grade</th>
                                <th class="px-2 py-1 text-left text-xs uppercase hidden lg:table-cell">Birth</th>
                                <th class="px-2 py-1 text-left text-xs uppercase hidden lg:table-cell">City</th>
                                <th class="px-2 py-1 text-left text-xs uppercase hidden xl:table-cell">Instruments</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <template x-for="student in filteredStudents">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-2 py-2">
                                        <div class="flex items-center">
                                            <div class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center mr-2">
                                                <i :class="student.gender === 'male' ? 'fas fa-mars text-blue-500' : 'fas fa-venus text-pink-500'" class="text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-xs" x-text="student.full_name"></div>
                                                <div class="text-xs text-gray-500" x-text="student.christian_name"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2">
                                        <div class="text-xs">
                                            <div x-show="student.phone_number" class="font-medium text-blue-600" x-text="student.phone_number"></div>
                                            <div x-show="student.parent_phone && student.parent_phone !== student.phone_number" class="text-gray-500 text-xs">P: <span x-text="student.emergency_phone"></span></div>
                                            <div x-show="!student.phone_number && !student.emergency_phone" class="text-gray-400 italic">No phone</div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2 text-xs" x-text="student.current_grade"></td>
                                    <td class="px-2 py-2 text-xs text-gray-500 hidden lg:table-cell" x-text="student.birth_date_formatted"></td>
                                    <td class="px-2 py-2 text-xs text-gray-500 hidden lg:table-cell" x-text="student.sub_city || 'N/A'"></td>
                                    <td class="px-2 py-2 text-xs hidden xl:table-cell">
                                        <div class="flex flex-wrap gap-1">
                                            <template x-for="instrument in student.instruments">
                                                <span class="bg-purple-100 text-purple-700 px-1 py-0.5 rounded text-xs" x-text="instrument"></span>
                                            </template>
                                            <span x-show="!student.instruments || student.instruments.length === 0" class="text-gray-400 italic text-xs">None</span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2">
                                        <div class="flex space-x-1">
                                            <span :class="student.has_spiritual_father ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'" class="px-1 py-0.5 rounded text-xs" title="Spiritual Father">
                                                <i :class="student.has_spiritual_father ? 'fas fa-check' : 'fas fa-times'" class="text-xs"></i>
                                            </span>
                                            <span x-show="student.has_disability" class="bg-orange-100 text-orange-700 px-1 py-0.5 rounded text-xs" title="Has Disability">
                                                <i class="fas fa-wheelchair text-xs"></i>
                                            </span>
                                            <span x-show="student.instruments && student.instruments.length > 0" class="bg-blue-100 text-blue-700 px-1 py-0.5 rounded text-xs" title="Plays Instruments">
                                                <i class="fas fa-music text-xs"></i>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Card View -->
                <div class="md:hidden max-h-80 overflow-y-auto">
                    <template x-for="student in filteredStudents">
                        <div class="p-3 border-b border-gray-200 last:border-b-0 hover:bg-gray-50">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center flex-1">
                                    <div class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center mr-2">
                                        <i :class="student.gender === 'male' ? 'fas fa-mars text-blue-500' : 'fas fa-venus text-pink-500'" class="text-xs"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium text-sm" x-text="student.full_name"></div>
                                        <div class="text-xs text-gray-600" x-text="student.current_grade + ' Grade'"></div>
                                    </div>
                                </div>
                                <div class="flex space-x-1">
                                    <span :class="student.has_spiritual_father ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'" class="px-1 py-0.5 rounded text-xs">
                                        <i :class="student.has_spiritual_father ? 'fas fa-check' : 'fas fa-times'" class="text-xs"></i>
                                    </span>
                                    <span x-show="student.has_disability" class="bg-orange-100 text-orange-700 px-1 py-0.5 rounded text-xs">
                                        <i class="fas fa-wheelchair text-xs"></i>
                                    </span>
                                    <span x-show="student.instruments && student.instruments.length > 0" class="bg-blue-100 text-blue-700 px-1 py-0.5 rounded text-xs">
                                        <i class="fas fa-music text-xs"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-gray-500">Phone:</span>
                                    <div x-show="student.phone_number" class="font-medium text-blue-600" x-text="student.phone_number"></div>
                                    <div x-show="student.emergency_phone && student.emergency_phone !== student.phone_number" class="text-gray-500">P: <span x-text="student.emergency_phone"></span></div>
                                    <div x-show="!student.phone_number && !student.emergency_phone" class="text-gray-400 italic">No phone</div>
                                </div>
                                <div>
                                    <span class="text-gray-500">Location:</span>
                                    <div x-text="student.sub_city || 'N/A'"></div>
                                </div>
                            </div>
                            <div x-show="student.instruments && student.instruments.length > 0" class="mt-2">
                                <span class="text-gray-500 text-xs">Instruments:</span>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <template x-for="instrument in student.instruments">
                                        <span class="bg-purple-100 text-purple-700 px-1 py-0.5 rounded text-xs" x-text="instrument"></span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <!-- Enhanced Empty States -->
            <div x-show="!loading && filteredStudents.length === 0 && (Object.values(filters).some(f => f !== ''))" x-transition 
                 class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center border border-yellow-200">
                <div class="w-16 h-16 mx-auto mb-4 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-search text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Students Found</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">No students match your current filter criteria.</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <button @click="clearFilters()" 
                            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center justify-center">
                        <i class="fas fa-eraser mr-2"></i>
                        Clear All Filters
                    </button>
                    <button @click="applyFilters()" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                        <i class="fas fa-refresh mr-2"></i>
                        Try Again
                    </button>
                </div>
            </div>
            
            <div x-show="!loading && filteredStudents.length === 0 && Object.values(filters).every(f => f === '')" x-transition 
                 class="bg-gradient-to-br from-white to-blue-50 dark:from-gray-800 dark:to-gray-900 rounded-lg shadow-lg p-6 text-center border border-blue-200 dark:border-gray-700">
                <!-- School Logo -->
                <div class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-4 rounded-full bg-white shadow-md flex items-center justify-center overflow-hidden border-2 border-blue-200">
                    <img src="uploads/689636ec11381_finot logo.png" alt="Finote Selam School" class="w-full h-full object-contain rounded-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                    <div class="hidden w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                        FS
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Ready to Filter Students</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">Use the filters above to search for specific students, then apply to see results.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 text-sm">
                    <div class="flex items-center justify-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <i class="fas fa-graduation-cap text-indigo-500 mr-2"></i>
                        <span>Filter by Grade Level</span>
                    </div>
                    <div class="flex items-center justify-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <i class="fas fa-venus-mars text-pink-500 mr-2"></i>
                        <span>Filter by Gender</span>
                    </div>
                    <div class="flex items-center justify-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <i class="fas fa-music text-purple-500 mr-2"></i>
                        <span>Filter by Instrument</span>
                    </div>
                    <div class="flex items-center justify-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                        <span>Filter by Location</span>
                    </div>
                </div>
                <button @click="applyFilters()" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center mx-auto">
                    <i class="fas fa-users mr-2"></i>
                    Load All Students
                </button>
            </div>
        </div>
    </div>

</div>
</div>

<?php
$content = ob_get_clean();

// Enhanced page script with Chart.js and all charts
$page_script = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// DEBUG: Log filter data to console
const phpFilterData = {
    grades: ' . json_encode(array_values(array_keys($grade_distribution))) . ',
    instruments: ' . json_encode(array_values(array_keys($instrument_distribution))) . ',
    subCities: ' . json_encode(array_values(array_keys($sub_city_distribution))) . ',
    livingArrangements: ' . json_encode(array_values($living_arrangements)) . ',
    genderValues: ' . json_encode(array_values($gender_values)) . ',
    spiritualFatherValues: ' . json_encode(array_values($spiritual_father_values)) . '
};
console.log("PHP Filter Data:", phpFilterData);

// Fallback data in case PHP data is empty
const fallbackFilterData = {
    grades: ["new", "1st", "2nd", "3rd", "4th", "5th", "6th", "7th", "8th", "9th", "10th", "11th", "12th"],
    instruments: ["begena", "masenqo", "kebero", "krar"],
    subCities: ["ለሚ ኩራ", "አዲስ አበባ", "ባህር ዳር"],
    livingArrangements: ["both_parents", "father_only", "mother_only", "relative_or_guardian"],
    genderValues: ["male", "female"],
    spiritualFatherValues: ["own", "family", "none"]
};

// Merge PHP data with fallbacks
window.filterData = {};
Object.keys(fallbackFilterData).forEach(key => {
    window.filterData[key] = (phpFilterData[key] && phpFilterData[key].length > 0) ? phpFilterData[key] : fallbackFilterData[key];
});

console.log("Final Filter Data:", window.filterData);

// Ensure Alpine.js is loaded
document.addEventListener("DOMContentLoaded", function() {
    console.log("Alpine.js status:", window.Alpine ? "✅ Loaded" : "❌ Not loaded");
    
    // Log the actual PHP data being passed
    console.log("PHP Data being passed to Alpine.js:");
    console.log("- Grades:", ' . json_encode(array_values(array_keys($grade_distribution))) . ');
    console.log("- Instruments:", ' . json_encode(array_values(array_keys($instrument_distribution))) . ');
    console.log("- Sub Cities:", ' . json_encode(array_values(array_keys($sub_city_distribution))) . ');
    console.log("- Living Arrangements:", ' . json_encode(array_values($living_arrangements)) . ');
    console.log("- Gender Values:", ' . json_encode(array_values($gender_values)) . ');
    console.log("- Spiritual Father Values:", ' . json_encode(array_values($spiritual_father_values)) . ');
    
    // Wait for Alpine to fully initialize
    setTimeout(() => {
        const reportsTab = document.querySelector("#reports-tab-content");
        if (reportsTab && reportsTab._x_dataStack) {
            console.log("✅ Alpine.js Reports tab data initialized");
            const alpineData = reportsTab._x_dataStack[0];
            if (alpineData && alpineData.availableFilters) {
                console.log("✅ Alpine.js availableFilters:", alpineData.availableFilters);
            } else {
                console.warn("⚠️ Alpine.js availableFilters not found");
            }
        } else {
            console.warn("⚠️ Alpine.js Reports tab data not initialized");
        }
    }, 2000);
});
</script>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    .chart-container {
        position: relative;
        height: 200px;
    }
    
    /* Hide scrollbar for mobile tab navigation */
    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
    
    /* Ultra-compact responsive improvements */
    @media (max-width: 640px) {
        /* Ensure touch targets are at least 44px */
        .mobile-tab {
            min-height: 32px;
            min-width: 32px;
        }
        
        /* Better spacing for mobile cards */
        .mobile-card {
            padding: 0.75rem;
        }
        
        /* Optimize text sizes for mobile */
        .mobile-text-xs {
            font-size: 0.6rem;
        }
        
        /* Improve button spacing */
        .mobile-btn-spacing {
            margin-bottom: 0.25rem;
        }
        
        /* Ultra-compact chart containers on mobile */
        .chart-container {
            height: 150px;
        }
    }
    
    /* Enhanced mobile responsiveness for very small screens */
    @media (max-width: 380px) {
        .chart-container {
            height: 120px;
        }
        
        /* Stack cards vertically on very small screens */
        .ultra-mobile-stack {
            grid-template-columns: 1fr !important;
        }
        
        /* Make everything even smaller on tiny screens */
        .text-xs {
            font-size: 0.6rem !important;
        }
        
        .p-3 {
            padding: 0.5rem !important;
        }
        
        .p-2 {
            padding: 0.25rem !important;
        }
        
        .gap-3 {
            gap: 0.5rem !important;
        }
        
        .gap-2 {
            gap: 0.25rem !important;
        }
    }
    
    /* Ultra-compact table styling */
    .ultra-compact-table {
        font-size: 0.6rem;
    }
    
    .ultra-compact-table th,
    .ultra-compact-table td {
        padding: 0.25rem;
        border-width: 1px;
    }
    
    /* Ultra-compact buttons and form elements */
    .ultra-compact-select {
        font-size: 0.6rem;
        padding: 0.125rem 0.25rem;
        height: 1.5rem;
    }
    
    .ultra-compact-btn {
        font-size: 0.6rem;
        padding: 0.125rem 0.375rem;
        height: 1.5rem;
        line-height: 1.25rem;
    }
    
    /* Ultra-compact icons */
    .ultra-compact-icon {
        font-size: 0.5rem;
        width: 0.75rem;
        height: 0.75rem;
    }
    
    @media print {
        .no-print { display: none !important; }
        .chart-container { display: none; }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        h1, h2, h3, h4, h5, h6 { page-break-after: avoid; }
        body { font-size: 10pt; line-height: 1.2; }
        @page { margin: 0.75in; size: A4; }
    }
</style>
<script>
// Chart data from PHP
const chartData = {
    gender: {
        labels: ["Male", "Female"],
        data: [' . $male_students . ', ' . $female_students . '],
        colors: ["#3b82f6", "#ec4899"]
    },
    grade: {
        labels: ' . json_encode(array_keys($grade_distribution)) . ',
        data: ' . json_encode(array_values($grade_distribution)) . ',
        colors: ["#6366f1", "#8b5cf6", "#a855f7", "#d946ef", "#ec4899", "#f43f5e", "#10b981", "#f59e0b", "#ef4444", "#06b6d4", "#84cc16", "#f97316"]
    },
    age: {
        labels: ' . json_encode(array_keys($age_groups)) . ',
        data: ' . json_encode(array_values($age_groups)) . ',
        colors: ["#3b82f6", "#10b981", "#f59e0b", "#ef4444"]
    },
    disability: {
        labels: ' . json_encode(array_keys($disability_stats)) . ',
        data: ' . json_encode(array_values($disability_stats)) . ',
        colors: ["#10b981", "#ef4444"]
    },
    subCity: {
        labels: ' . json_encode(array_keys(array_slice($sub_city_distribution, 0, 8, true))) . ',
        data: ' . json_encode(array_values(array_slice($sub_city_distribution, 0, 8, true))) . ',
        colors: ["#f59e0b", "#8b5cf6", "#06b6d4", "#84cc16", "#f97316", "#ef4444", "#6366f1", "#ec4899"]
    },
    living: {
        labels: ' . json_encode(array_keys($living_with_distribution)) . ',
        data: ' . json_encode(array_values($living_with_distribution)) . ',
        colors: ["#10b981", "#3b82f6", "#f59e0b", "#ef4444"]
    },
    spiritual: {
        labels: ' . json_encode(array_keys($spiritual_father_stats)) . ',
        data: ' . json_encode(array_values($spiritual_father_stats)) . ',
        colors: ["#8b5cf6", "#6b7280"]
    },
    instrument: {
        labels: ' . json_encode(array_keys(array_slice($instrument_distribution, 0, 10, true))) . ',
        data: ' . json_encode(array_values(array_slice($instrument_distribution, 0, 10, true))) . ',
        colors: ["#8b5cf6", "#06b6d4", "#f59e0b", "#ef4444", "#10b981", "#6366f1", "#ec4899", "#84cc16", "#f97316", "#64748b"]
    },
    instrumentGender: {
        labels: ["Male", "Female"],
        data: [' . $male_instrument_students . ', ' . $female_instrument_students . '],
        colors: ["#3b82f6", "#ec4899"]
    },
    trends: {
        labels: ' . json_encode(array_keys($registration_trends)) . ',
        data: ' . json_encode(array_values($registration_trends)) . '
    },
    instrumentTrends: {
        labels: ' . json_encode(array_keys($instrument_trends)) . ',
        data: ' . json_encode(array_values($instrument_trends)) . '
    }
};

// Initialize charts when page loads
document.addEventListener("DOMContentLoaded", function() {
    // Gender Chart
    if (document.getElementById("genderChart")) {
        new Chart(document.getElementById("genderChart"), {
            type: "doughnut",
            data: {
                labels: chartData.gender.labels,
                datasets: [{ data: chartData.gender.data, backgroundColor: chartData.gender.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, cutout: "60%" }
        });
    }

    // Grade Chart (Overview)
    if (document.getElementById("gradeChart")) {
        new Chart(document.getElementById("gradeChart"), {
            type: "bar",
            data: {
                labels: chartData.grade.labels,
                datasets: [{ label: "Students", data: chartData.grade.data, backgroundColor: chartData.grade.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    // Age Chart
    if (document.getElementById("ageChart")) {
        new Chart(document.getElementById("ageChart"), {
            type: "pie",
            data: {
                labels: chartData.age.labels,
                datasets: [{ data: chartData.age.data, backgroundColor: chartData.age.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
        });
    }

    // Disability Chart
    if (document.getElementById("disabilityChart")) {
        new Chart(document.getElementById("disabilityChart"), {
            type: "doughnut",
            data: {
                labels: chartData.disability.labels,
                datasets: [{ data: chartData.disability.data, backgroundColor: chartData.disability.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, cutout: "60%" }
        });
    }

    // Grade Chart Academic Tab
    if (document.getElementById("gradeChartAcademic")) {
        new Chart(document.getElementById("gradeChartAcademic"), {
            type: "doughnut",
            data: {
                labels: chartData.grade.labels,
                datasets: [{ data: chartData.grade.data, backgroundColor: chartData.grade.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, cutout: "50%" }
        });
    }

    // Sub City Chart
    if (document.getElementById("subCityChart")) {
        new Chart(document.getElementById("subCityChart"), {
            type: "bar",
            data: {
                labels: chartData.subCity.labels,
                datasets: [{ label: "Students", data: chartData.subCity.data, backgroundColor: chartData.subCity.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    // Living Arrangements Chart
    if (document.getElementById("livingChart")) {
        new Chart(document.getElementById("livingChart"), {
            type: "pie",
            data: {
                labels: chartData.living.labels,
                datasets: [{ data: chartData.living.data, backgroundColor: chartData.living.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
        });
    }

    // Spiritual Father Chart
    if (document.getElementById("spiritualChart")) {
        new Chart(document.getElementById("spiritualChart"), {
            type: "doughnut",
            data: {
                labels: chartData.spiritual.labels,
                datasets: [{ data: chartData.spiritual.data, backgroundColor: chartData.spiritual.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, cutout: "60%" }
        });
    }

    // Instrument Distribution Chart
    if (document.getElementById("instrumentChart")) {
        new Chart(document.getElementById("instrumentChart"), {
            type: "bar",
            data: {
                labels: chartData.instrument.labels,
                datasets: [{ label: "Students", data: chartData.instrument.data, backgroundColor: chartData.instrument.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    // Instrument Gender Chart
    if (document.getElementById("instrumentGenderChart")) {
        new Chart(document.getElementById("instrumentGenderChart"), {
            type: "doughnut",
            data: {
                labels: chartData.instrumentGender.labels,
                datasets: [{ data: chartData.instrumentGender.data, backgroundColor: chartData.instrumentGender.colors, borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, cutout: "60%" }
        });
    }

    // Instrument Trends Chart
    if (document.getElementById("instrumentTrendsChart")) {
        new Chart(document.getElementById("instrumentTrendsChart"), {
            type: "line",
            data: {
                labels: chartData.instrumentTrends.labels,
                datasets: [{
                    label: "Instrument Registrations",
                    data: chartData.instrumentTrends.data,
                    borderColor: "#8b5cf6",
                    backgroundColor: "rgba(139, 92, 246, 0.1)",
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }
});

// Report generation functions
function generateReport(type) {
    const reportContent = document.getElementById("printableReport");
    const originalContent = document.body.innerHTML;
    
    switch(type) {
        case "summary":
            // Show only the executive summary and main statistics
            const summaryContent = reportContent.cloneNode(true);
            // Hide detailed tables for summary view
            const detailedSections = summaryContent.querySelectorAll(".grid.grid-cols-1.lg\\\\:grid-cols-2.gap-8");
            detailedSections.forEach(section => section.style.display = "none");
            
            document.body.innerHTML = summaryContent.outerHTML;
            window.print();
            document.body.innerHTML = originalContent;
            break;
            
        case "academic":
            // Focus on academic statistics
            alert("Academic Report: Detailed grade distribution and academic performance analysis will be generated.");
            break;
            
        case "instrument":
            // Generate comprehensive instrument report
            generateInstrumentReport();
            break;
            
        case "demographic":
            // Focus on demographics
            alert("Demographics Report: Population analysis including age, gender, location, and family structure.");
            break;
            
        case "detailed":
            // Print the complete report
            document.body.innerHTML = reportContent.outerHTML;
            window.print();
            document.body.innerHTML = originalContent;
            break;
    }
}

// Add print styles
const printStyles = `
<style media="print">
    @page {
        margin: 1in;
        size: A4;
    }
    
    body {
        font-size: 12pt;
        line-height: 1.4;
        color: black !important;
        background: white !important;
    }
    
    .print\\\\:shadow-none {
        box-shadow: none !important;
    }
    
    .print\\\\:rounded-none {
        border-radius: 0 !important;
    }
    
    table {
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    h1, h2, h3, h4, h5, h6 {
        page-break-after: avoid;
    }
    
    .chart-container {
        display: none;
    }
    
    .no-print {
        display: none !important;
    }
</style>
`;

// Insert print styles into head
document.head.insertAdjacentHTML("beforeend", printStyles);
</script>';

// Render the admin layout
echo renderAdminLayout('Student Analytics Dashboard', $content, $page_script);
?>