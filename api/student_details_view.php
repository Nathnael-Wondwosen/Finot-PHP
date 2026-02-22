<?php
session_start();
require_once '../config.php';
require_once '../includes/security_helpers.php';
require_once '../ethiopian_age.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log incoming parameters for debugging
error_log("API Debug: ID=" . ($_GET['id'] ?? 'null') . ", Table=" . ($_GET['table'] ?? 'null'));
error_log("API Debug: Session admin_id=" . ($_SESSION['admin_id'] ?? 'not set'));
error_log("API Debug: Session data=" . print_r($_SESSION, true));

// Check admin session before requiring login
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    error_log("API Debug: Admin not logged in, session missing");
    http_response_code(403);
    echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Authentication required</p></div>';
    exit;
}

// Require admin auth for details view
// requireAdminLogin(); // Commented out since we already checked above

header('Content-Type: text/html; charset=UTF-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$table = isset($_GET['table']) ? $_GET['table'] : 'students';

if ($id <= 0) {
    http_response_code(400);
    echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Invalid request</p></div>';
    exit;
}

$allowedTables = ['students', 'instruments'];
if (!in_array($table, $allowedTables, true)) {
    http_response_code(400);
    echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Invalid table</p></div>';
    exit;
}

try {
    if ($table === 'instruments') {
        // Instrument registration with comprehensive linked student data including parents/guardians
        // Note: Ethiopian date fields (birth_year_et, birth_month_et, birth_day_et) only exist in instrument_registrations table
        $stmt = $pdo->prepare('
            SELECT ir.id, ir.full_name, ir.christian_name, ir.gender, ir.birth_year_et, ir.birth_month_et, ir.birth_day_et, 
                   ir.phone_number, ir.person_photo_path, ir.instrument, ir.created_at, ir.flagged,
                   ir.has_spiritual_father, ir.spiritual_father_name, ir.spiritual_father_phone, ir.spiritual_father_church,
                   ir.sub_city, ir.district, ir.specific_area, ir.house_number,
                   ir.emergency_name, ir.emergency_phone, ir.emergency_alt_phone, ir.emergency_address,
                   s.id AS student_id, s.full_name AS s_full_name, s.christian_name AS s_christian_name,
                   s.gender AS s_gender, s.current_grade AS s_current_grade, s.photo_path AS s_photo_path,
                   s.phone_number AS s_phone_number, s.created_at AS s_created_at, s.birth_date AS s_birth_date,
                   s.sub_city AS s_sub_city, s.district AS s_district, s.specific_area AS s_specific_area, s.house_number AS s_house_number, s.living_with,
                   s.emergency_name AS s_emergency_name, s.emergency_phone AS s_emergency_phone, s.emergency_alt_phone AS s_emergency_alt_phone, s.emergency_address AS s_emergency_address,
                   s.has_spiritual_father AS s_has_spiritual_father, s.spiritual_father_name AS s_spiritual_father_name, s.spiritual_father_phone AS s_spiritual_father_phone, s.spiritual_father_church AS s_spiritual_father_church,
                   s.school_year_start, s.regular_school_name, s.regular_school_grade, s.education_level, s.field_of_study,
                   s.special_interests, s.siblings_in_school, s.physical_disability, s.weak_side,
                   s.transferred_from_other_school, s.came_from_other_religion,
                   f.full_name AS father_full_name, f.christian_name AS father_christian_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
                   m.full_name AS mother_full_name, m.christian_name AS mother_christian_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation,
                   g.full_name AS guardian_full_name, g.christian_name AS guardian_christian_name, g.phone_number AS guardian_phone, g.occupation AS guardian_occupation
            FROM instrument_registrations ir
            LEFT JOIN students s ON LOWER(TRIM(ir.full_name)) = LOWER(TRIM(s.full_name))
            LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = \'father\'
            LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = \'mother\'
            LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = \'guardian\'
            WHERE ir.id = ?
        ');
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get all instrument registrations for this student (for both linked and unlinked)
        $instrumentRegs = [];
        $studentName = '';
        if ($student) {
            // Use the primary full_name from instrument registration record
            $studentName = $student['full_name'] ?? '';
            if (!empty($studentName)) {
                $stmtIr = $pdo->prepare('SELECT instrument, created_at, flagged, id as registration_id FROM instrument_registrations WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?)) ORDER BY created_at DESC');
                $stmtIr->execute([$studentName]);
                $rawInstrumentRegs = $stmtIr->fetchAll(PDO::FETCH_ASSOC) ?: [];
                
                // Process instrument registrations to handle comma-separated instruments
                foreach ($rawInstrumentRegs as $reg) {
                    $instruments = !empty($reg['instrument']) ? array_map('trim', explode(',', $reg['instrument'])) : [''];
                    
                    // Create a separate entry for each instrument
                    foreach ($instruments as $instrument) {
                        if (!empty($instrument)) {
                            $instrumentRegs[] = [
                                'instrument' => $instrument,
                                'created_at' => $reg['created_at'],
                                'flagged' => $reg['flagged'],
                                'registration_id' => $reg['registration_id']
                            ];
                        }
                    }
                }
            }
        }
    } else {
        // Full student record with comprehensive parent/guardian details and all information
        $stmt = $pdo->prepare("SELECT s.*, 
            f.full_name AS father_full_name, f.christian_name AS father_christian_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
            m.full_name AS mother_full_name, m.christian_name AS mother_christian_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation,
            g.full_name AS guardian_full_name, g.christian_name AS guardian_christian_name, g.phone_number AS guardian_phone, g.occupation AS guardian_occupation
        FROM students s
        LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
        LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
        LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
        WHERE s.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        // Normalize expected keys
        if ($student && !isset($student['registration_date']) && isset($student['created_at'])) {
            $student['registration_date'] = $student['created_at'];
        }
        // Get ALL instrument registrations for this student (match by normalized full_name)
        $instrumentRegs = [];
        if ($student && !empty($student['full_name'])) {
            $stmtIr = $pdo->prepare('SELECT instrument, created_at, flagged, id as registration_id FROM instrument_registrations WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?)) ORDER BY created_at DESC');
            $stmtIr->execute([$student['full_name']]);
            $rawInstrumentRegs = $stmtIr->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Process instrument registrations to handle comma-separated instruments
            foreach ($rawInstrumentRegs as $reg) {
                $instruments = !empty($reg['instrument']) ? array_map('trim', explode(',', $reg['instrument'])) : [''];
                
                // Create a separate entry for each instrument
                foreach ($instruments as $instrument) {
                    if (!empty($instrument)) {
                        $instrumentRegs[] = [
                            'instrument' => $instrument,
                            'created_at' => $reg['created_at'],
                            'flagged' => $reg['flagged'],
                            'registration_id' => $reg['registration_id']
                        ];
                    }
                }
            }
        }
    }

    if (!$student) {
        echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Student not found</p></div>';
        exit;
    }

    // Derive display fields for instruments view
    if ($table === 'instruments') {
        $display = [
            'id' => $student['id'] ?? null,
            'full_name' => $student['full_name'] ?? ($student['s_full_name'] ?? 'N/A'),
            'christian_name' => $student['christian_name'] ?? ($student['s_christian_name'] ?? null),
            'gender' => $student['gender'] ?? ($student['s_gender'] ?? null),
            'current_grade' => $student['s_current_grade'] ?? null,
            'phone_number' => $student['phone_number'] ?? ($student['s_phone_number'] ?? null),
            'photo_path' => $student['person_photo_path'] ?? ($student['s_photo_path'] ?? ''),
            'age' => $student['age'] ?? null,
            'instrument' => $student['instrument'] ?? null,
            'created_at' => $student['created_at'] ?? $student['s_created_at'] ?? null,
            'flagged' => $student['flagged'] ?? 0,
            'student_id' => $student['student_id'] ?? null,
            'birth_date' => $student['birth_date'] ?? ($student['s_birth_date'] ?? null),
            'birth_year_et' => $student['birth_year_et'] ?? null,
            'birth_month_et' => $student['birth_month_et'] ?? null,
            'birth_day_et' => $student['birth_day_et'] ?? null,
            // Map all additional information fields - prioritize instrument_registrations data for unlinked records
            'sub_city' => $student['sub_city'] ?? ($student['s_sub_city'] ?? null),
            'district' => $student['district'] ?? ($student['s_district'] ?? null),
            'specific_area' => $student['specific_area'] ?? ($student['s_specific_area'] ?? null),
            'house_number' => $student['house_number'] ?? ($student['s_house_number'] ?? null),
            'living_with' => $student['living_with'] ?? null,
            'emergency_name' => $student['emergency_name'] ?? ($student['s_emergency_name'] ?? null),
            'emergency_phone' => $student['emergency_phone'] ?? ($student['s_emergency_phone'] ?? null),
            'emergency_alt_phone' => $student['emergency_alt_phone'] ?? ($student['s_emergency_alt_phone'] ?? null),
            'emergency_address' => $student['emergency_address'] ?? ($student['s_emergency_address'] ?? null),
            'has_spiritual_father' => $student['has_spiritual_father'] ?? ($student['s_has_spiritual_father'] ?? null),
            'spiritual_father_name' => $student['spiritual_father_name'] ?? ($student['s_spiritual_father_name'] ?? null),
            'spiritual_father_phone' => $student['spiritual_father_phone'] ?? ($student['s_spiritual_father_phone'] ?? null),
            'spiritual_father_church' => $student['spiritual_father_church'] ?? ($student['s_spiritual_father_church'] ?? null),
            'school_year_start' => $student['school_year_start'] ?? null,
            'regular_school_name' => $student['regular_school_name'] ?? null,
            'regular_school_grade' => $student['regular_school_grade'] ?? null,
            'education_level' => $student['education_level'] ?? null,
            'field_of_study' => $student['field_of_study'] ?? null,
            'special_interests' => $student['special_interests'] ?? null,
            'siblings_in_school' => $student['siblings_in_school'] ?? null,
            'physical_disability' => $student['physical_disability'] ?? null,
            'weak_side' => $student['weak_side'] ?? null,
            'transferred_from_other_school' => $student['transferred_from_other_school'] ?? null,
            'came_from_other_religion' => $student['came_from_other_religion'] ?? null,
            // Map parent information
            'father_full_name' => $student['father_full_name'] ?? null,
            'father_christian_name' => $student['father_christian_name'] ?? null,
            'father_phone' => $student['father_phone'] ?? null,
            'father_occupation' => $student['father_occupation'] ?? null,
            'mother_full_name' => $student['mother_full_name'] ?? null,
            'mother_christian_name' => $student['mother_christian_name'] ?? null,
            'mother_phone' => $student['mother_phone'] ?? null,
            'mother_occupation' => $student['mother_occupation'] ?? null,
            'guardian_full_name' => $student['guardian_full_name'] ?? null,
            'guardian_christian_name' => $student['guardian_christian_name'] ?? null,
            'guardian_phone' => $student['guardian_phone'] ?? null,
            'guardian_occupation' => $student['guardian_occupation'] ?? null,
        ];
    } else {
        $display = $student;
    }

    // Helper escape
    $e = function($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); };

    // Compute age and adult flag using Ethiopian age for consistency
    $isAdult = false;
    $computedAge = null;
    
    // Ethiopian age calculation functions
    function ethiopian_today_components() {
        $t = new DateTime();
        $gy = (int)$t->format('Y');
        $gm = (int)$t->format('m');
        $gd = (int)$t->format('d');
        
        // Convert to JDN then to Ethiopian
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
    
    // Calculate age based on available data
    if ($table === 'instruments') {
        if (isset($display['age']) && is_numeric($display['age'])) {
            $computedAge = (int)$display['age'];
            $isAdult = $computedAge >= 18;
        } elseif (!empty($display['birth_year_et']) && !empty($display['birth_month_et']) && !empty($display['birth_day_et'])) {
            $computedAge = ethiopian_age_from_ymd((int)$display['birth_year_et'], (int)$display['birth_month_et'], (int)$display['birth_day_et']);
            $isAdult = $computedAge >= 18;
        } elseif (!empty($display['birth_date']) && $display['birth_date'] !== '0000-00-00') {
            // Treat birth_date as Ethiopian date (YYYY-MM-DD)
            [$ey, $em, $ed] = array_map('intval', explode('-', $display['birth_date']));
            if ($ey && $em && $ed) {
                $computedAge = ethiopian_age_from_ymd($ey, $em, $ed);
                $isAdult = $computedAge >= 18;
            }
        }
    } else {
        if (!empty($display['birth_year_et']) && !empty($display['birth_month_et']) && !empty($display['birth_day_et'])) {
            $computedAge = ethiopian_age_from_ymd((int)$display['birth_year_et'], (int)$display['birth_month_et'], (int)$display['birth_day_et']);
            $isAdult = $computedAge >= 18;
        } elseif (!empty($display['birth_date']) && $display['birth_date'] !== '0000-00-00') {
            // Treat birth_date as Ethiopian date (YYYY-MM-DD)
            [$ey, $em, $ed] = array_map('intval', explode('-', $display['birth_date']));
            if ($ey && $em && $ed) {
                $computedAge = ethiopian_age_from_ymd($ey, $em, $ed);
                $isAdult = $computedAge >= 18;
            }
        }
    }

    // Ethiopic month helpers
    $amharicEthiopicMonths = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ህዳር', 4 => 'ታህሳስ', 5 => 'ጥር', 6 => 'የካቲት',
        7 => 'መጋቢት', 8 => 'ሚያዝያ', 9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
    ];

    $formatDobEthiopic = function(array $display, string $table) use ($amharicEthiopicMonths, $e) {
        // Prefer explicit Ethiopic components if provided (instrument reg or student record)
        if (!empty($display['birth_year_et']) && !empty($display['birth_month_et']) && !empty($display['birth_day_et'])) {
            $m = (int)$display['birth_month_et'];
            $monthName = $amharicEthiopicMonths[$m] ?? '';
            return trim($e((string)$display['birth_day_et']) . ' ' . $monthName . ' ' . $e((string)$display['birth_year_et']));
        }
        // If we have a birth_date stored as Ethiopian YYYY-MM-DD format
        if (!empty($display['birth_date']) && $display['birth_date'] !== '0000-00-00') {
            $parts = explode('-', $display['birth_date']);
            if (count($parts) === 3) {
                [$ey, $em, $ed] = array_map('intval', $parts);
                if ($ey && $em && $ed) {
                    // Treat as Ethiopian date components
                    $monthName = $amharicEthiopicMonths[$em] ?? '';
                    return trim($e((string)$ed) . ' ' . $monthName . ' ' . $e((string)$ey));
                }
            }
        }
        return '';
    };

    // Build HTML output
    ob_start();
?>
    <div class="space-y-4">
        <div class="flex items-start space-x-3">
            <div>
                <?php if (!empty($display['photo_path'])): ?>
                    <img src="<?= $e($display['photo_path']) ?>" alt="Photo" class="w-16 h-16 rounded-full object-cover ring-2 ring-white shadow" />
                <?php else: ?>
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-400 to-purple-600 text-white flex items-center justify-center text-lg font-semibold">
                        <?= $e(strtoupper(substr($display['full_name'] ?? 'U', 0, 1))) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-1">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mr-2"><?= $e($display['full_name'] ?? 'N/A') ?></h3>
                    <?php if (!empty($display['student_id'])): ?>
                        <span class="px-1.5 py-0.5 text-xs rounded bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">Linked</span>
                    <?php endif; ?>
                    <?php if (!empty($display['flagged'])): ?>
                        <span class="px-1.5 py-0.5 text-xs rounded bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">Flagged</span>
                    <?php endif; ?>
                    <?php if (!empty($instrumentRegs) && count($instrumentRegs) > 0): ?>
                        <?php foreach (array_slice($instrumentRegs, 0, 3) as $ir): ?>
                            <span class="px-1.5 py-0.5 text-xs rounded bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300"><?= $e(ucfirst($ir['instrument'] ?? '')) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($instrumentRegs) > 3): ?>
                            <span class="px-1.5 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">+<?= count($instrumentRegs) - 3 ?> more</span>
                        <?php endif; ?>
                    <?php elseif (!empty($display['instrument'])): ?>
                        <span class="px-1.5 py-0.5 text-xs rounded bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300"><?= $e(ucfirst($display['instrument'])) ?></span>
                    <?php endif; ?>
                    <?php if ($computedAge !== null): ?>
                        <span class="px-1.5 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">Age: <?= $e($computedAge) ?></span>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                    <?php if (!empty($display['christian_name'])): ?>
                        <span class="mr-2">Christian: <?= $e($display['christian_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($display['gender'])): ?>
                        <span class="mr-2">Gender: <?= $e($display['gender']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($display['current_grade'])): ?>
                        <span>Grade: <?= $e($display['current_grade']) ?></span>
                    <?php endif; ?>
                    <?php $dobEt = $formatDobEthiopic($display, $table); if ($dobEt !== ''): ?>
                        <span class="ml-2">DOB: <?= $dobEt ?></span>
                    <?php endif; ?>
                    <?php if ($table === 'instruments' && isset($display['age']) && $display['age'] !== ''): ?>
                        <span class="ml-2">Age: <?= $e($display['age']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="space-y-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Instrument Registrations (<?= count($instrumentRegs) ?>)</h4>
                <div class="text-xs text-gray-700 dark:text-gray-300 space-y-1">
                    <?php if (!empty($instrumentRegs)): ?>
                        <?php foreach ($instrumentRegs as $index => $ir): ?>
                            <div class="flex items-center justify-between py-1 <?= $index > 0 ? 'border-t border-gray-200 dark:border-gray-600' : '' ?>">
                                <div>
                                    <span class="font-medium"><?= $e(ucfirst($ir['instrument'] ?? '-')) ?></span>
                                    <span class="text-gray-500 dark:text-gray-400 ml-2"><?= !empty($ir['created_at']) ? $e(date('M j, Y', strtotime($ir['created_at']))) : '-' ?></span>
                                </div>
                                <div class="flex space-x-1">
                                    <?php if (!empty($ir['flagged'])): ?>
                                        <span class="px-1 py-0.5 text-xs rounded bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">⚠</span>
                                    <?php endif; ?>
                                    <span class="px-1 py-0.5 text-xs rounded bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">#<?= $e($ir['registration_id'] ?? '') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div>No instrument registrations found</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="space-y-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Registration Status</h4>
                <div class="text-xs text-gray-700 dark:text-gray-300 space-y-1">
                    <?php if (!empty($display['student_id'])): ?>
                        <div class="text-green-600 dark:text-green-400"><i class="fas fa-link mr-1"></i> Linked to Student Record</div>
                        <div class="text-gray-600 dark:text-gray-400 text-xs">ID: <?= $e($display['student_id']) ?></div>
                    <?php else: ?>
                        <div class="text-orange-600 dark:text-orange-400"><i class="fas fa-unlink mr-1"></i> No Linked Student Record</div>
                        <div class="text-blue-600 dark:text-blue-400 text-xs"><i class="fas fa-info-circle mr-1"></i> Showing instrument registration data</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($display['student_id'])): ?>
        <!-- Comprehensive Family Information for Linked Students -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">👨‍👩‍👧‍👦 Family Information</h4>
                <div class="text-xs text-gray-700 dark:text-gray-300 space-y-2">
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-2 rounded">
                        <div class="font-medium text-blue-800 dark:text-blue-200">Father</div>
                        <div>Name: <?= $e($display['father_full_name'] ?? '-') ?></div>
                        <?php if (!empty($display['father_christian_name'])): ?>
                            <div>Christian: <?= $e($display['father_christian_name']) ?></div>
                        <?php endif; ?>
                        <div>Phone: <?= $e($display['father_phone'] ?? '-') ?></div>
                        <div>Occupation: <?= $e($display['father_occupation'] ?? '-') ?></div>
                    </div>
                    <div class="bg-pink-50 dark:bg-pink-900/20 p-2 rounded">
                        <div class="font-medium text-pink-800 dark:text-pink-200">Mother</div>
                        <div>Name: <?= $e($display['mother_full_name'] ?? '-') ?></div>
                        <?php if (!empty($display['mother_christian_name'])): ?>
                            <div>Christian: <?= $e($display['mother_christian_name']) ?></div>
                        <?php endif; ?>
                        <div>Phone: <?= $e($display['mother_phone'] ?? '-') ?></div>
                        <div>Occupation: <?= $e($display['mother_occupation'] ?? '-') ?></div>
                    </div>
                    <?php if (!empty($display['guardian_full_name'])): ?>
                    <div class="bg-gray-50 dark:bg-gray-900/20 p-2 rounded">
                        <div class="font-medium text-gray-800 dark:text-gray-200">Guardian</div>
                        <div>Name: <?= $e($display['guardian_full_name']) ?></div>
                        <?php if (!empty($display['guardian_christian_name'])): ?>
                            <div>Christian: <?= $e($display['guardian_christian_name']) ?></div>
                        <?php endif; ?>
                        <div>Phone: <?= $e($display['guardian_phone'] ?? '-') ?></div>
                        <div>Occupation: <?= $e($display['guardian_occupation'] ?? '-') ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <div><strong>Living With:</strong> <?= $e(str_replace('_', ' ', ucwords($display['living_with'] ?? '-', '_'))) ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Address & Contact Information -->
            <div class="space-y-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">📍 Address & Contact</h4>
                <div class="text-xs text-gray-700 dark:text-gray-300 space-y-2">
                    <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded">
                        <div class="font-medium text-green-800 dark:text-green-200">Address</div>
                        <div>Sub City: <?= $e($display['sub_city'] ?? '-') ?></div>
                        <div>District: <?= $e($display['district'] ?? '-') ?></div>
                        <div>Specific Area: <?= $e($display['specific_area'] ?? '-') ?></div>
                        <div>House Number: <?= $e($display['house_number'] ?? '-') ?></div>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 p-2 rounded">
                        <div class="font-medium text-red-800 dark:text-red-200">Emergency Contact</div>
                        <div>Name: <?= $e($display['emergency_name'] ?? '-') ?></div>
                        <div>Phone: <?= $e($display['emergency_phone'] ?? '-') ?></div>
                        <?php if (!empty($display['emergency_alt_phone'])): ?>
                            <div>Alt Phone: <?= $e($display['emergency_alt_phone']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($display['emergency_address'])): ?>
                            <div>Address: <?= $e($display['emergency_address']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Academic & School Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">🎓 Academic Information</h4>
                <div class="text-xs text-gray-700 dark:text-gray-300 space-y-1">
                    <div>Current Grade: <?= $e($display['current_grade'] ?? '-') ?></div>
                    <div>School Year Start: <?= $e($display['school_year_start'] ?? '-') ?></div>
                    <div>Regular School: <?= $e($display['regular_school_name'] ?? '-') ?></div>
                    <div>Regular School Grade: <?= $e($display['regular_school_grade'] ?? '-') ?></div>
                    <?php if (!empty($display['education_level'])): ?>
                        <div>Education Level: <?= $e($display['education_level']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($display['field_of_study'])): ?>
                        <div>Field of Study: <?= $e($display['field_of_study']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Personal Information -->
            <div class="space-y-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">👤 Personal Details</h4>
                <div class="text-xs text-gray-700 dark:text-gray-300 space-y-1">
                    <div>Phone: <?= $e($display['phone_number'] ?? '-') ?></div>
                    <div>Registration: <?= $e(isset($display['created_at']) && $display['created_at'] ? date('M j, Y g:i A', strtotime($display['created_at'])) : '-') ?></div>
                    <?php if (!empty($display['special_interests'])): ?>
                        <div>Special Interests: <?= $e($display['special_interests']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($display['siblings_in_school'])): ?>
                        <div>Siblings in School: <?= $e($display['siblings_in_school']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($display['physical_disability']) && $display['physical_disability'] !== '-'): ?>
                        <div>Physical Disability: <?= $e($display['physical_disability']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($display['weak_side']) && $display['weak_side'] !== '-'): ?>
                        <div>Weak Side: <?= $e($display['weak_side']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($display['transferred_from_other_school']) && $display['transferred_from_other_school'] !== '-'): ?>
                        <div>Transferred From: <?= $e($display['transferred_from_other_school']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($display['came_from_other_religion']) && $display['came_from_other_religion'] !== '-'): ?>
                        <div>Came From Other Religion: <?= $e($display['came_from_other_religion']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Spiritual Information -->
        <div class="space-y-2">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">⛪ Spiritual Information</h4>
            <div class="bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg">
                <div class="text-xs text-gray-700 dark:text-gray-300 space-y-1">
                    <div><strong>Has Spiritual Father:</strong> <?= $e(ucfirst($display['has_spiritual_father'] ?? '-')) ?></div>
                    <?php if (!empty($display['spiritual_father_name'])): ?>
                        <div><strong>Spiritual Father Name:</strong> <?= $e($display['spiritual_father_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($display['spiritual_father_phone'])): ?>
                        <div><strong>Spiritual Father Phone:</strong> <?= $e($display['spiritual_father_phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($display['spiritual_father_church'])): ?>
                        <div><strong>Church:</strong> <?= $e($display['spiritual_father_church']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- All Instrument Registrations for this Student -->
        <?php if (!empty($instrumentRegs)): ?>
            <div class="space-y-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">🎵 All Instrument Registrations (<?= count($instrumentRegs) ?>)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($instrumentRegs as $index => $ir): ?>
                        <div class="bg-gradient-to-r from-purple-50 to-blue-50 dark:from-purple-900/20 dark:to-blue-900/20 p-3 rounded-lg border border-purple-200 dark:border-purple-700">
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300 text-sm font-medium">
                                    🎼 <?= $e(ucfirst($ir['instrument'] ?? '-')) ?>
                                </span>
                                <?php if (!empty($ir['flagged'])): ?>
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 font-medium">⚠️ Flagged</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                <div><strong>Registration #:</strong> <?= $e($ir['registration_id'] ?? '-') ?></div>
                                <div><strong>Date:</strong> <?= !empty($ir['created_at']) ? $e(date('M j, Y g:i A', strtotime($ir['created_at']))) : '-' ?></div>
                                <div><strong>Status:</strong> 
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 text-xs">
                                        ✅ Active
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Advanced Print-Optimized Instrument Registration Details for Unlinked Records -->
        <div class="print-optimized space-y-4">
            <!-- Compact Header with Photo -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 rounded-lg shadow-sm print:bg-gray-100 print:text-black print:border print:border-gray-300">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <?php if (!empty($display['photo_path'])): ?>
                            <img src="<?= $e($display['photo_path']) ?>" alt="Photo" class="w-12 h-12 rounded-full object-cover border-2 border-white" />
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-white/20 text-white flex items-center justify-center text-sm font-bold print:bg-gray-200 print:text-black">
                                <?= $e(strtoupper(substr($display['full_name'] ?? 'U', 0, 1))) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h2 class="text-lg font-bold print:text-base"><?= $e($display['full_name'] ?? 'N/A') ?></h2>
                            <div class="flex items-center space-x-2 text-xs opacity-90 print:text-black">
                                <?php if (!empty($instrumentRegs) && count($instrumentRegs) > 0): ?>
                                    <?php foreach (array_slice($instrumentRegs, 0, 2) as $ir): ?>
                                        <span class="px-1.5 py-0.5 rounded bg-white/20 print:bg-gray-200">🎵 <?= $e(ucfirst($ir['instrument'] ?? '')) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($instrumentRegs) > 2): ?>
                                        <span class="px-1.5 py-0.5 rounded bg-white/20 print:bg-gray-200">+<?= count($instrumentRegs) - 2 ?> more</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>🎵 <?= $e(ucfirst($display['instrument'] ?? '-')) ?></span>
                                <?php endif; ?>
                                <span>📅 <?= $e(isset($display['created_at']) && $display['created_at'] ? date('M j, Y', strtotime($display['created_at'])) : '-') ?></span>
                                <span>ID: <?= $e($display['id'] ?? '-') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 text-xs rounded-full bg-white/20 print:bg-gray-200 print:text-black">Unlinked Record</span>
                        <?php if (!empty($display['flagged'])): ?>
                            <div class="mt-1"><span class="px-2 py-1 text-xs rounded-full bg-red-500 text-white print:bg-red-100 print:text-red-800">⚠ Flagged</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Main Information Grid - Optimized for Print -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 print:grid-cols-3 print:gap-2">
                
                <!-- Personal Details -->
                <div class="bg-white border border-gray-200 rounded p-3 print:border-gray-400">
                    <h3 class="text-xs font-semibold text-gray-800 mb-2 pb-1 border-b border-gray-200 print:text-black">👤 PERSONAL INFORMATION</h3>
                    <div class="space-y-1.5 text-xs">
                        <div class="grid grid-cols-3 gap-1">
                            <span class="text-gray-500 font-medium print:text-black">Name:</span>
                            <span class="col-span-2 text-gray-900 font-medium print:text-black"><?= $e($display['full_name'] ?? '-') ?></span>
                        </div>
                        <?php if (!empty($display['christian_name'])): ?>
                        <div class="grid grid-cols-3 gap-1">
                            <span class="text-gray-500 font-medium print:text-black">Christian:</span>
                            <span class="col-span-2 text-gray-900 print:text-black"><?= $e($display['christian_name']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="grid grid-cols-3 gap-1">
                            <span class="text-gray-500 font-medium print:text-black">Gender:</span>
                            <span class="col-span-2 text-gray-900 print:text-black"><?= $e(ucfirst($display['gender'] ?? '-')) ?></span>
                        </div>
                        <?php 
                        // Format birth date elegantly
                        $birthDisplay = '';
                        $ageDisplay = '';
                        if (!empty($display['birth_year_et']) && !empty($display['birth_month_et']) && !empty($display['birth_day_et'])) {
                            $monthName = $amharicEthiopicMonths[(int)$display['birth_month_et']] ?? '';
                            $birthDisplay = $e($display['birth_day_et']) . ' ' . $monthName . ' ' . $e($display['birth_year_et']);
                        }
                        if ($computedAge !== null) {
                            $ageDisplay = $computedAge . ' years';
                        } elseif (!empty($display['age'])) {
                            $ageDisplay = $e($display['age']) . ' years';
                        }
                        ?>
                        <?php if ($birthDisplay): ?>
                        <div class="grid grid-cols-3 gap-1">
                            <span class="text-gray-500 font-medium print:text-black">Birth (ET):</span>
                            <span class="col-span-2 text-gray-900 print:text-black text-xs"><?= $birthDisplay ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($ageDisplay): ?>
                        <div class="grid grid-cols-3 gap-1">
                            <span class="text-gray-500 font-medium print:text-black">Age:</span>
                            <span class="col-span-2 text-gray-900 print:text-black"><?= $ageDisplay ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($display['phone_number'])): ?>
                        <div class="grid grid-cols-3 gap-1">
                            <span class="text-gray-500 font-medium print:text-black">Phone:</span>
                            <span class="col-span-2 text-gray-900 font-mono print:text-black text-xs"><?= $e($display['phone_number']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Address & Emergency Contact -->
                <div class="bg-white border border-gray-200 rounded p-3 print:border-gray-400">
                    <h3 class="text-xs font-semibold text-gray-800 mb-2 pb-1 border-b border-gray-200 print:text-black">📍 ADDRESS & EMERGENCY</h3>
                    <div class="space-y-2 text-xs">
                        
                        <!-- Address Section -->
                        <?php if (!empty($display['sub_city']) || !empty($display['district']) || !empty($display['specific_area']) || !empty($display['house_number'])): ?>
                        <div class="bg-gray-50 p-2 rounded print:bg-gray-100">
                            <div class="text-xs font-medium text-gray-700 mb-1 print:text-black">📍 Address</div>
                            <?php if (!empty($display['specific_area'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Area: <?= $e($display['specific_area']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['house_number'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">House: <?= $e($display['house_number']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['district'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">District: <?= $e($display['district']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['sub_city'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Sub City: <?= $e($display['sub_city']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Emergency Contact Section -->
                        <?php if (!empty($display['emergency_name']) || !empty($display['emergency_phone'])): ?>
                        <div class="bg-red-50 p-2 rounded print:bg-gray-100">
                            <div class="text-xs font-medium text-red-700 mb-1 print:text-black">🚨 Emergency Contact</div>
                            <?php if (!empty($display['emergency_name'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Name: <?= $e($display['emergency_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['emergency_phone'])): ?>
                            <div class="text-xs text-gray-600 font-mono print:text-black">Phone: <?= $e($display['emergency_phone']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['emergency_alt_phone'])): ?>
                            <div class="text-xs text-gray-600 font-mono print:text-black">Alt: <?= $e($display['emergency_alt_phone']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['emergency_address'])): ?>
                            <div class="text-xs text-gray-600 print:text-black mt-1">Address: <?= $e($display['emergency_address']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="bg-white border border-gray-200 rounded p-3 print:border-gray-400">
                    <h3 class="text-xs font-semibold text-gray-800 mb-2 pb-1 border-b border-gray-200 print:text-black">ℹ️ ADDITIONAL DETAILS</h3>
                    <div class="space-y-2 text-xs">
                        
                        <!-- Spiritual Information -->
                        <?php if (!empty($display['has_spiritual_father']) || !empty($display['spiritual_father_name']) || !empty($display['spiritual_father_phone']) || !empty($display['spiritual_father_church'])): ?>
                        <div class="bg-purple-50 p-2 rounded print:bg-gray-100">
                            <div class="text-xs font-medium text-purple-700 mb-1 print:text-black">⛪ Spiritual</div>
                            <?php if (!empty($display['has_spiritual_father'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Has Father: <?= $e(ucfirst($display['has_spiritual_father'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['spiritual_father_name'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Name: <?= $e($display['spiritual_father_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['spiritual_father_phone'])): ?>
                            <div class="text-xs text-gray-600 font-mono print:text-black">Phone: <?= $e($display['spiritual_father_phone']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['spiritual_father_church'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Church: <?= $e($display['spiritual_father_church']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Academic Information -->
                        <?php if (!empty($display['current_grade']) || !empty($display['school_year_start']) || !empty($display['regular_school_name']) || !empty($display['education_level'])): ?>
                        <div class="bg-blue-50 p-2 rounded print:bg-gray-100">
                            <div class="text-xs font-medium text-blue-700 mb-1 print:text-black">🎓 Academic</div>
                            <?php if (!empty($display['current_grade'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Grade: <?= $e($display['current_grade']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['school_year_start'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Year: <?= $e($display['school_year_start']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['regular_school_name'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">School: <?= $e($display['regular_school_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['education_level'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Level: <?= $e($display['education_level']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['field_of_study'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Field: <?= $e($display['field_of_study']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Other Details -->
                        <?php $hasOtherDetails = !empty($display['special_interests']) || !empty($display['siblings_in_school']) || !empty($display['physical_disability']) || !empty($display['living_with']); ?>
                        <?php if ($hasOtherDetails): ?>
                        <div class="bg-amber-50 p-2 rounded print:bg-gray-100">
                            <div class="text-xs font-medium text-amber-700 mb-1 print:text-black">📋 Other</div>
                            <?php if (!empty($display['living_with'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Living: <?= $e(str_replace('_', ' ', ucwords($display['living_with'], '_'))) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['special_interests'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Interests: <?= $e($display['special_interests']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['siblings_in_school'])): ?>
                            <div class="text-xs text-gray-600 print:text-black">Siblings: <?= $e($display['siblings_in_school']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($display['physical_disability']) && $display['physical_disability'] !== '-'): ?>
                            <div class="text-xs text-gray-600 print:text-black">Disability: <?= $e($display['physical_disability']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- All Instrument Registrations Section for Unlinked Students -->
        <?php if (!empty($instrumentRegs) && count($instrumentRegs) > 0): ?>
        <div class="mt-4">
            <div class="bg-white border border-gray-200 rounded p-3 print:border-gray-400">
                <h3 class="text-xs font-semibold text-gray-800 mb-3 pb-1 border-b border-gray-200 print:text-black">🎵 ALL INSTRUMENT REGISTRATIONS (<?= count($instrumentRegs) ?>)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 print:grid-cols-3 print:gap-2">
                    <?php foreach ($instrumentRegs as $index => $ir): ?>
                        <div class="bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-lg p-3 print:bg-gray-100 print:border-gray-400">
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-purple-100 text-purple-800 text-xs font-medium print:bg-gray-200 print:text-black">
                                    🎼 <?= $e(ucfirst($ir['instrument'] ?? '-')) ?>
                                </span>
                                <?php if (!empty($ir['flagged'])): ?>
                                    <span class="px-1.5 py-0.5 text-xs rounded-full bg-red-100 text-red-700 font-medium print:bg-red-200 print:text-black">⚠️ Flagged</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-600 space-y-1 print:text-black">
                                <div><strong>Registration #:</strong> <?= $e($ir['registration_id'] ?? '-') ?></div>
                                <div><strong>Date:</strong> <?= !empty($ir['created_at']) ? $e(date('M j, Y g:i A', strtotime($ir['created_at']))) : '-' ?></div>
                                <div><strong>Status:</strong> 
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-green-100 text-green-700 text-xs print:bg-green-200 print:text-black">
                                        ✅ Active
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Compact Footer -->
        <div class="bg-gray-50 border border-gray-200 rounded p-2 print:bg-gray-100 print:border-gray-400 mt-4">
            <div class="flex items-center justify-between text-xs text-gray-500 print:text-black">
                <div class="flex items-center space-x-4">
                    <span>📊 Source: Instrument Registration System</span>
                    <span>🕒 Generated: <?= date('M j, Y g:i A') ?></span>
                </div>
                <span class="font-mono">ID: <?= $e($display['id'] ?? '-') ?></span>
            </div>
        </div>

        <!-- Print-specific styles -->
        <style>
            @media print {
                .print-optimized {
                    font-size: 11px !important;
                    line-height: 1.3 !important;
                }
                .print-optimized h2 {
                    font-size: 14px !important;
                    font-weight: bold !important;
                }
                .print-optimized h3 {
                    font-size: 10px !important;
                    font-weight: bold !important;
                    text-transform: uppercase !important;
                }
                .print-optimized .text-xs {
                    font-size: 9px !important;
                }
                .print-optimized * {
                    color: black !important;
                    background: white !important;
                }
                .print-optimized .bg-gray-50,
                .print-optimized .bg-purple-50,
                .print-optimized .bg-blue-50,
                .print-optimized .bg-red-50,
                .print-optimized .bg-amber-50 {
                    background: #f5f5f5 !important;
                    border: 1px solid #d1d5db !important;
                }
            }
        </style>
        <?php endif; ?>
    </div>
<?php
    echo ob_get_clean();
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    error_log("API Stack Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Server error: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
}
?>