<?php
session_start();
require_once '../config.php';
require_once '../includes/security_helpers.php';
require_once '../ethiopian_age.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check admin session
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Authentication required</p></div>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Invalid student ID</p></div>';
    exit;
}

try {
    // Get comprehensive student data with all fields from students table + parent information
    $stmt = $pdo->prepare("SELECT s.*, 
        f.full_name AS father_full_name, f.christian_name AS father_christian_name, 
        f.phone_number AS father_phone, f.occupation AS father_occupation,
        m.full_name AS mother_full_name, m.christian_name AS mother_christian_name, 
        m.phone_number AS mother_phone, m.occupation AS mother_occupation,
        g.full_name AS guardian_full_name, g.christian_name AS guardian_christian_name, 
        g.phone_number AS guardian_phone, g.occupation AS guardian_occupation,
        c.id AS active_class_id, c.name AS active_class_name, c.grade AS active_class_grade, c.section AS active_class_section,
        ce_active.enrollment_date AS active_class_enrollment_date
    FROM students s
    LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
    LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
    LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
    LEFT JOIN (
        SELECT ce1.student_id, ce1.class_id, ce1.enrollment_date
        FROM class_enrollments ce1
        INNER JOIN (
            SELECT student_id, MAX(id) AS max_id
            FROM class_enrollments
            WHERE status = 'active'
            GROUP BY student_id
        ) latest ON latest.max_id = ce1.id
    ) ce_active ON s.id = ce_active.student_id
    LEFT JOIN classes c ON ce_active.class_id = c.id
    WHERE s.id = ? LIMIT 1");
    
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Student not found</p></div>';
        exit;
    }

    // Get all instrument registrations for this student
    $stmtIr = $pdo->prepare('SELECT instrument, created_at, flagged, id as registration_id FROM instrument_registrations WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?)) ORDER BY created_at DESC');
    $stmtIr->execute([$student['full_name']]);
    $instrumentRegs = $stmtIr->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Helper functions to safely display values
    $e = function($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); };
    $display = function($v, $default = '-') use ($e) { return !empty($v) ? $e($v) : $default; };

    // Calculate Ethiopian age from birth_date field (already in Ethiopian calendar format)
    $computedAge = null;
    
    // Ethiopian age calculation functions
    function ethiopian_today_components() {
        $t = new DateTime();
        $gy = (int)$t->format('Y');
        $gm = (int)$t->format('m');
        $gd = (int)$t->format('d');
        
        // Convert Gregorian to Ethiopian (JDN method)
        $a = intdiv(14 - $gm, 12);
        $yy = $gy + 4800 - $a;
        $mm = $gm + 12 * $a - 3;
        $jdn = $gd + intdiv(153 * $mm + 2, 5) + 365 * $yy + intdiv($yy, 4) - intdiv($yy, 100) + intdiv($yy, 400) - 32045;
        
        $r = ($jdn - 1723856) % 1461;
        if ($r < 0) $r += 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);
        $year = 4 * intdiv(($jdn - 1723856), 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $month = intdiv($n, 30) + 1;
        $day = ($n % 30) + 1;
        return [$year, $month, $day];
    }
    
    function ethiopian_age_from_ymd($ey, $em, $ed) {
        [$cy, $cm, $cd] = ethiopian_today_components();
        $age = $cy - $ey;
        if ($cm < $em || ($cm === $em && $cd < $ed)) $age--;
        return $age;
    }
    
    if (!empty($student['birth_date']) && $student['birth_date'] !== '0000-00-00') {
        // Parse Ethiopian birth date (YYYY-MM-DD format)
        $parts = explode('-', $student['birth_date']);
        if (count($parts) === 3) {
            [$ey, $em, $ed] = array_map('intval', $parts);
            if ($ey && $em && $ed) {
                $computedAge = ethiopian_age_from_ymd($ey, $em, $ed);
            }
        }
    }

    // Ethiopian months
    $amharicEthiopicMonths = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ህዳር', 4 => 'ታህሳስ', 5 => 'ጥር', 6 => 'የካቲት',
        7 => 'መጋቢት', 8 => 'ሚያዝያ', 9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
    ];

    // Format Ethiopian birthdate from birth_date field (already in Ethiopian calendar)
    $formatEthiopianBirthdate = function() use ($student, $amharicEthiopicMonths, $e) {
        if (!empty($student['birth_date']) && $student['birth_date'] !== '0000-00-00') {
            // Parse the Ethiopian date format (YYYY-MM-DD)
            $parts = explode('-', $student['birth_date']);
            if (count($parts) === 3) {
                [$ey, $em, $ed] = array_map('intval', $parts);
                if ($ey && $em && $ed) {
                    $monthName = $amharicEthiopicMonths[$em] ?? '';
                    return $e($ed) . ' ' . $monthName . ' ' . $e($ey);
                }
            }
        }
        return '-';
    };

?>
    <div class="space-y-6">
        <!-- Header Section with Photo and Basic Info -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 p-6 rounded-lg border border-blue-200 dark:border-blue-700">
            <div class="flex items-start space-x-4">
                <div>
                    <?php if (!empty($student['photo_path'])): ?>
                        <img src="<?= $e($student['photo_path']) ?>" alt="Photo" class="w-20 h-20 rounded-full object-cover ring-4 ring-white shadow-lg" />
                    <?php else: ?>
                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 text-white flex items-center justify-center text-xl font-bold shadow-lg">
                            <?= $e(strtoupper(substr($student['full_name'] ?? 'U', 0, 1))) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?= $display($student['full_name']) ?></h2>
                        <?php if (!empty($student['flagged'])): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 font-medium">🚩 Flagged</span>
                        <?php endif; ?>
                        <?php if ($computedAge !== null): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">Age: <?= $computedAge ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                        <div><strong>Student ID:</strong> <?= $display($student['id']) ?></div>
                        <div><strong>Christian Name:</strong> <?= $display($student['christian_name']) ?></div>
                        <div><strong>Gender:</strong> <?= $display($student['gender']) ?></div>
                        <div><strong>Phone:</strong> <?= $display($student['phone_number']) ?></div>
                        <div><strong>Current Class:</strong>
                            <?php if (!empty($student['active_class_id'])): ?>
                                <?= $display($student['active_class_name']) ?>
                                <?php if (!empty($student['active_class_grade'])): ?>
                                    (Grade <?= $display($student['active_class_grade']) ?><?= !empty($student['active_class_section']) ? ', Sec ' . $display($student['active_class_section']) : '' ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                        <div><strong>Registration Date:</strong> <?= $student['created_at'] ? $e(date('M j, Y g:i A', strtotime($student['created_at']))) : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Birth Information -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                <i class="fas fa-birthday-cake text-pink-500 mr-2"></i> Birth Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div class="text-sm"><strong>Ethiopian Birth Date:</strong> 
                        <span class="text-gray-900 dark:text-white"><?= $formatEthiopianBirthdate() ?></span>
                    </div>
                    <?php 
                    // Extract Ethiopian date components from birth_date field
                    $ethiopianComponents = ['year' => '-', 'month' => '-', 'day' => '-'];
                    if (!empty($student['birth_date']) && $student['birth_date'] !== '0000-00-00') {
                        $parts = explode('-', $student['birth_date']);
                        if (count($parts) === 3) {
                            [$ey, $em, $ed] = array_map('intval', $parts);
                            if ($ey && $em && $ed) {
                                $ethiopianComponents = ['year' => $ey, 'month' => $em, 'day' => $ed];
                            }
                        }
                    }
                    ?>
                    <div class="text-sm"><strong>Birth Year (ET):</strong> <?= $display($ethiopianComponents['year']) ?></div>
                    <div class="text-sm"><strong>Birth Month (ET):</strong> <?= $display($ethiopianComponents['month']) ?></div>
                    <div class="text-sm"><strong>Birth Day (ET):</strong> <?= $display($ethiopianComponents['day']) ?></div>
                </div>
                <div class="space-y-2">
                    <div class="text-sm"><strong>Birth Date (Database):</strong> <?= $display($student['birth_date']) ?></div>
                    <?php if ($computedAge !== null): ?>
                        <div class="text-sm"><strong>Ethiopian Age:</strong> <?= $computedAge ?> years</div>
                    <?php endif; ?>
                    <div class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Birth date is stored in Ethiopian calendar format in the database.
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                <i class="fas fa-graduation-cap text-blue-500 mr-2"></i> Academic Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div class="text-sm"><strong>Current Grade:</strong> <?= $display($student['current_grade']) ?></div>
                    <div class="text-sm"><strong>Assigned Class:</strong>
                        <?php if (!empty($student['active_class_id'])): ?>
                            <?= $display($student['active_class_name']) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                    <div class="text-sm"><strong>School Year Start:</strong> <?= $display($student['school_year_start']) ?></div>
                    <div class="text-sm"><strong>Regular School Name:</strong> <?= $display($student['regular_school_name']) ?></div>
                    <div class="text-sm"><strong>Regular School Grade:</strong> <?= $display($student['regular_school_grade']) ?></div>
                </div>
                <div class="space-y-2">
                    <div class="text-sm"><strong>Education Level:</strong> <?= $display($student['education_level']) ?></div>
                    <div class="text-sm"><strong>Field of Study:</strong> <?= $display($student['field_of_study']) ?></div>
                    <div class="text-sm"><strong>Transferred From Other School:</strong> <?= $display($student['transferred_from_other_school']) ?></div>
                    <div class="text-sm"><strong>Came From Other Religion:</strong> <?= $display($student['came_from_other_religion']) ?></div>
                </div>
            </div>
        </div>

        <!-- Family Information -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                <i class="fas fa-users text-green-500 mr-2"></i> Family Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Father -->
                <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded">
                    <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">👨 Father</h4>
                    <div class="space-y-1 text-sm">
                        <div><strong>Name:</strong> <?= $display($student['father_full_name']) ?></div>
                        <div><strong>Christian Name:</strong> <?= $display($student['father_christian_name']) ?></div>
                        <div><strong>Phone:</strong> <?= $display($student['father_phone']) ?></div>
                        <div><strong>Occupation:</strong> <?= $display($student['father_occupation']) ?></div>
                    </div>
                </div>
                
                <!-- Mother -->
                <div class="bg-pink-50 dark:bg-pink-900/20 p-3 rounded">
                    <h4 class="font-medium text-pink-800 dark:text-pink-200 mb-2">👩 Mother</h4>
                    <div class="space-y-1 text-sm">
                        <div><strong>Name:</strong> <?= $display($student['mother_full_name']) ?></div>
                        <div><strong>Christian Name:</strong> <?= $display($student['mother_christian_name']) ?></div>
                        <div><strong>Phone:</strong> <?= $display($student['mother_phone']) ?></div>
                        <div><strong>Occupation:</strong> <?= $display($student['mother_occupation']) ?></div>
                    </div>
                </div>
                
                <!-- Guardian -->
                <div class="bg-gray-50 dark:bg-gray-900/20 p-3 rounded">
                    <h4 class="font-medium text-gray-800 dark:text-gray-200 mb-2">👤 Guardian</h4>
                    <div class="space-y-1 text-sm">
                        <div><strong>Name:</strong> <?= $display($student['guardian_full_name']) ?></div>
                        <div><strong>Christian Name:</strong> <?= $display($student['guardian_christian_name']) ?></div>
                        <div><strong>Phone:</strong> <?= $display($student['guardian_phone']) ?></div>
                        <div><strong>Occupation:</strong> <?= $display($student['guardian_occupation']) ?></div>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-sm"><strong>Living With:</strong> <?= $display(str_replace('_', ' ', ucwords($student['living_with'] ?? '', '_'))) ?></div>
                <div class="text-sm"><strong>Siblings in School:</strong> <?= $display($student['siblings_in_school']) ?></div>
            </div>
        </div>

        <!-- Address Information -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                <i class="fas fa-map-marker-alt text-red-500 mr-2"></i> Address Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div class="text-sm"><strong>Sub City:</strong> <?= $display($student['sub_city']) ?></div>
                    <div class="text-sm"><strong>District:</strong> <?= $display($student['district']) ?></div>
                </div>
                <div class="space-y-2">
                    <div class="text-sm"><strong>Specific Area:</strong> <?= $display($student['specific_area']) ?></div>
                    <div class="text-sm"><strong>House Number:</strong> <?= $display($student['house_number']) ?></div>
                </div>
            </div>
        </div>

        <!-- Emergency Contact -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                <i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i> Emergency Contact
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div class="text-sm"><strong>Emergency Contact Name:</strong> <?= $display($student['emergency_name']) ?></div>
                    <div class="text-sm"><strong>Emergency Phone:</strong> <?= $display($student['emergency_phone']) ?></div>
                </div>
                <div class="space-y-2">
                    <div class="text-sm"><strong>Emergency Alt Phone:</strong> <?= $display($student['emergency_alt_phone']) ?></div>
                    <div class="text-sm"><strong>Emergency Address:</strong> <?= $display($student['emergency_address']) ?></div>
                </div>
            </div>
        </div>

        <!-- Spiritual Information -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                <i class="fas fa-church text-purple-500 mr-2"></i> Spiritual Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div class="text-sm"><strong>Has Spiritual Father:</strong> <?= $display(ucfirst($student['has_spiritual_father'] ?? '')) ?></div>
                    <div class="text-sm"><strong>Spiritual Father Name:</strong> <?= $display($student['spiritual_father_name']) ?></div>
                </div>
                <div class="space-y-2">
                    <div class="text-sm"><strong>Spiritual Father Phone:</strong> <?= $display($student['spiritual_father_phone']) ?></div>
                    <div class="text-sm"><strong>Spiritual Father Church:</strong> <?= $display($student['spiritual_father_church']) ?></div>
                </div>
            </div>
        </div>

        <!-- Personal Characteristics -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                <i class="fas fa-user-circle text-indigo-500 mr-2"></i> Personal Characteristics
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div class="text-sm"><strong>Special Interests:</strong> <?= $display($student['special_interests']) ?></div>
                    <div class="text-sm"><strong>Physical Disability:</strong> <?= $display($student['physical_disability']) ?></div>
                </div>
                <div class="space-y-2">
                    <div class="text-sm"><strong>Weak Side:</strong> <?= $display($student['weak_side']) ?></div>
                    <div class="text-sm"><strong>Flagged:</strong> <?= $student['flagged'] ? 'Yes' : 'No' ?></div>
                </div>
            </div>
        </div>

        <!-- Instrument Registrations -->
        <?php if (!empty($instrumentRegs)): ?>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                <i class="fas fa-music text-purple-500 mr-2"></i> Instrument Registrations (<?= count($instrumentRegs) ?>)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($instrumentRegs as $ir): ?>
                <div class="bg-gradient-to-r from-purple-50 to-blue-50 dark:from-purple-900/20 dark:to-blue-900/20 p-3 rounded border border-purple-200 dark:border-purple-700">
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2 py-1 rounded bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300 text-sm font-medium">
                            🎼 <?= $e(ucfirst($ir['instrument'] ?? '-')) ?>
                        </span>
                        <?php if (!empty($ir['flagged'])): ?>
                            <span class="px-1.5 py-0.5 text-xs rounded bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">⚠️ Flagged</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs space-y-1">
                        <div><strong>Registration ID:</strong> <?= $e($ir['registration_id'] ?? '-') ?></div>
                        <div><strong>Date:</strong> <?= !empty($ir['created_at']) ? $e(date('M j, Y', strtotime($ir['created_at']))) : '-' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Database Metadata -->
        <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                <i class="fas fa-database text-gray-500 mr-2"></i> Database Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600 dark:text-gray-400">
                <div class="space-y-1">
                    <div><strong>Record Created:</strong> <?= $student['created_at'] ? $e(date('M j, Y g:i A', strtotime($student['created_at']))) : '-' ?></div>
                    <div><strong>Last Updated:</strong> <?= isset($student['updated_at']) && $student['updated_at'] ? $e(date('M j, Y g:i A', strtotime($student['updated_at']))) : 'Not recorded' ?></div>
                </div>
                <div class="space-y-1">
                    <div><strong>Database ID:</strong> <?= $display($student['id']) ?></div>
                    <div><strong>Record Status:</strong> Active</div>
                </div>
            </div>
        </div>
    </div>

<?php
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    error_log("API Stack Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Server error: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
}
?>
