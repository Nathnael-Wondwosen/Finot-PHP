<?php
session_start();
require 'config.php';
requireAdminLogin();

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id <= 0) {
    echo '<h2>Invalid student ID.</h2>';
    exit;
}

// Fetch student info


// Determine age (if possible) to decide query
$tmp_sql = "SELECT * FROM students WHERE id = ? LIMIT 1";
$tmp_stmt = $pdo->prepare($tmp_sql);
$tmp_stmt->execute([$student_id]);
$tmp_student = $tmp_stmt->fetch(PDO::FETCH_ASSOC);

function get_student_age($s) {
    if (isset($s['birth_year_et']) && $s['birth_year_et']) {
        return ethiopian_age_from_ymd((int)$s['birth_year_et'], (int)$s['birth_month_et'], (int)$s['birth_day_et']);
    }
    if (!empty($s['birth_date'])) {
        [$ey, $em, $ed] = array_map('intval', explode('-', $s['birth_date']));
        if ($ey && $em && $ed) return ethiopian_age_from_ymd($ey, $em, $ed);
    }
    return null;
}

$student_age = get_student_age($tmp_student);
if ($student_age !== null && $student_age < 18) {
$sql = "SELECT s.*, 
        f.full_name AS father_full_name, f.christian_name AS father_christian_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
        m.full_name AS mother_full_name, m.christian_name AS mother_christian_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation,
        g.full_name AS guardian_full_name, g.christian_name AS guardian_christian_name, g.phone_number AS guardian_phone, g.occupation AS guardian_occupation
FROM students s
LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
WHERE s.id = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $student = $tmp_student;
}

if (!$student) {
    echo '<h2>Student not found.</h2>';
    exit;
}
// Ethiopian calendar helpers
function gregorian_to_jdn($y, $m, $d) {
    $a = intdiv(14 - $m, 12);
    $yy = $y + 4800 - $a;
    $mm = $m + 12 * $a - 3;
    return $d + intdiv(153 * $mm + 2, 5) + 365 * $yy + intdiv($yy, 4) - intdiv($yy, 100) + intdiv($yy, 400) - 32045;
}
function jdn_to_ethiopian($jdn) {
    $r = ($jdn - 1723856) % 1461;
    if ($r < 0) $r += 1461;
    $n = ($r % 365) + 365 * intdiv($r, 1460);
    $year = 4 * intdiv(($jdn - 1723856), 1461) + intdiv($r, 365) - intdiv($r, 1460);
    $month = intdiv($n, 30) + 1;
    $day = ($n % 30) + 1;
    return [$year, $month, $day];
}
function ethiopian_today() {
    $t = new DateTime();
    [$ey, $em, $ed] = jdn_to_ethiopian(gregorian_to_jdn((int)$t->format('Y'), (int)$t->format('m'), (int)$t->format('d')));
    return [$ey, $em, $ed];
}
function ethiopian_age_from_ymd($ey, $em, $ed) {
    [$cy, $cm, $cd] = ethiopian_today();
    $age = $cy - $ey;
    if ($cm < $em || ($cm === $em && $cd < $ed)) $age--;
    return $age;
}
function et_month_name($m) {
    $names = ['መስከረም','ጥቅምት','ሕዳር','ታህሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ','ጳጉሜ'];
    return $names[$m - 1] ?? (string)$m;
}
function ethiopian_display_from_student($s) {
    if (isset($s['birth_year_et']) && $s['birth_year_et']) {
        $ey = (int)$s['birth_year_et'];
        $em = (int)$s['birth_month_et'];
        $ed = (int)$s['birth_day_et'];
        return sprintf('%04d-%02d-%02d', $ey, $em, $ed);
    }
    if (!empty($s['birth_date'])) {
        // Stored as Ethiopian YYYY-MM-DD string, return as-is
        return $s['birth_date'];
    }
    return '';
}
function ethiopian_age_from_student($s) {
    if (isset($s['birth_year_et']) && $s['birth_year_et']) {
        return ethiopian_age_from_ymd((int)$s['birth_year_et'], (int)$s['birth_month_et'], (int)$s['birth_day_et']);
    }
    if (!empty($s['birth_date'])) {
        // Parse stored Ethiopian date
        [$ey, $em, $ed] = array_map('intval', explode('-', $s['birth_date']));
        if ($ey && $em && $ed) return ethiopian_age_from_ymd($ey, $em, $ed);
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - <?= htmlspecialchars($student['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'ethiopic': ['Noto Sans Ethiopic', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        @media print {
            .print\:hidden { display: none !important; }
            .print\:block { display: block !important; }
            .print\:break-after { break-after: page; }
            body { 
                font-size: 10px; 
                line-height: 1.2;
                background: white !important;
                color: black !important;
                margin: 0;
                padding: 0;
            }
            .container { max-width: 100%; margin: 0; padding: 0.3rem; }
            .glass-card { 
                background: white !important; 
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                page-break-inside: avoid;
                margin-bottom: 0.5rem !important;
                padding: 0.5rem !important;
            }
            .gradient-bg { 
                background: white !important;
                border-bottom: 2px solid #333 !important;
                padding: 0.5rem !important;
            }
            .print-header {
                display: flex !important;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                padding-bottom: 0.3rem;
                border-bottom: 2px solid #333;
            }
            .print-logo {
                width: 60px;
                height: 60px;
                border-radius: 50%;
            }
            .print-title {
                text-align: center;
                flex-grow: 1;
            }
            .print-title h1 { font-size: 16px !important; margin: 0 !important; }
            .print-title h2 { font-size: 14px !important; margin: 0 !important; }
            .print-title p { font-size: 9px !important; margin: 0 !important; }
            
            /* Compact Profile Section */
            .profile-compact {
                display: flex !important;
                align-items: flex-start !important;
                gap: 1rem !important;
                margin-bottom: 0.5rem !important;
                padding: 0.5rem !important;
                border: 1px solid #ddd !important;
                background: #f9f9f9 !important;
            }
            .profile-photo {
                width: 80px !important;
                height: 80px !important;
                border-radius: 8px !important;
                flex-shrink: 0 !important;
            }
            .profile-info {
                flex: 1 !important;
            }
            .profile-name {
                font-size: 16px !important;
                font-weight: bold !important;
                margin: 0 0 0.2rem 0 !important;
                color: #333 !important;
            }
            .profile-christian {
                font-size: 11px !important;
                color: #666 !important;
                margin: 0 0 0.3rem 0 !important;
            }
            .profile-stats {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 0.3rem !important;
                margin-top: 0.3rem !important;
            }
            .stat-box {
                text-align: center !important;
                padding: 0.2rem !important;
                border: 1px solid #ddd !important;
                border-radius: 4px !important;
                background: white !important;
            }
            .stat-value {
                font-size: 11px !important;
                font-weight: bold !important;
                color: #333 !important;
                display: block !important;
            }
            .stat-label {
                font-size: 8px !important;
                color: #666 !important;
                display: block !important;
            }
            
            /* Compact Information Grid */
            .info-grid {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.5rem !important;
                margin-bottom: 0.5rem !important;
            }
            .info-section {
                border: 1px solid #ddd !important;
                padding: 0.4rem !important;
                background: white !important;
            }
            .info-title {
                font-size: 11px !important;
                font-weight: bold !important;
                color: #333 !important;
                margin-bottom: 0.3rem !important;
                padding-bottom: 0.2rem !important;
                border-bottom: 1px solid #eee !important;
            }
            .info-item {
                display: flex !important;
                margin-bottom: 0.15rem !important;
                font-size: 9px !important;
            }
            .info-label {
                font-weight: 500 !important;
                color: #555 !important;
                width: 60px !important;
                flex-shrink: 0 !important;
            }
            .info-value {
                color: #333 !important;
                flex: 1 !important;
            }
            
            h1, h2, h3 { color: #333 !important; }
            .text-blue-600, .text-green-600, .text-purple-600, .text-orange-600,
            .text-amber-700, .text-teal-500 { color: #333 !important; }
            
            /* Hide large spacing elements */
            .space-y-4 > * + * { margin-top: 0.2rem !important; }
            .space-y-3 > * + * { margin-top: 0.15rem !important; }
            .gap-6 { gap: 0.3rem !important; }
            .mb-6 { margin-bottom: 0.3rem !important; }
            .p-6 { padding: 0.4rem !important; }
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-ethiopic">
    <!-- Print Header (only visible when printing) -->
    <div class="hidden print:block print-header">
        <div class="flex items-center">
            <img src="uploads/689636ec11381_finot logo.png" alt="Finot Logo" class="print-logo object-contain">
        </div>
        <div class="print-title">
            <h1 class="text-2xl font-bold">Student Profile Report</h1>
            <h2 class="text-lg"><?= htmlspecialchars($student['full_name']) ?></h2>
            <p class="text-sm">Generated on: <?= date('F j, Y g:i A') ?></p>
        </div>
        <div class="text-right text-sm">
            <p>ID: #<?= str_pad($student['id'], 4, '0', STR_PAD_LEFT) ?></p>
            <p>Page 1 of 1</p>
        </div>
    </div>
    <!-- Header Section -->
    <div class="gradient-bg py-8 print:bg-white print:py-4">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between print:justify-center">
                <div class="flex items-center space-x-4">
                    <button onclick="history.back()" class="print:hidden bg-white/20 hover:bg-white/30 text-white p-3 rounded-full transition-all duration-200 shadow-lg">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </button>
                    <div class="text-white">
                        <h1 class="text-3xl font-bold mb-1">የተማሪ ዝርዝር መረጃ</h1>
                        <p class="text-white/80 text-sm">Student Profile & Information</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 print:hidden">
                    <button onclick="window.print()" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition-all duration-200 shadow-lg flex items-center space-x-2">
                        <i class="fas fa-print"></i>
                        <span>Print</span>
                    </button>
                    <button onclick="openEditDrawer()" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg transition-all duration-200 shadow-lg flex items-center space-x-2">
                        <i class="fas fa-edit"></i>
                        <span>Edit Profile</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 pb-8 print:mt-0 print:max-w-full">
        <!-- Student Profile Card -->
        <div class="glass-card rounded-2xl shadow-xl p-6 mb-6 print:shadow-none print:border print:profile-compact">
            <div class="flex flex-col lg:flex-row items-start lg:items-center space-y-4 lg:space-y-0 lg:space-x-6 print:flex-row print:space-y-0 print:space-x-4">
                <!-- Photo Section -->
                <div class="relative print:profile-photo">
                    <?php if (!empty($student['photo_path'])): ?>
                        <div class="w-32 h-32 rounded-2xl overflow-hidden shadow-lg ring-4 ring-white print:w-20 print:h-20 print:rounded-lg print:ring-0 print:shadow-none">
                            <img src="<?= htmlspecialchars($student['photo_path']) ?>" alt="Student photo" 
                                 class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <div class="w-32 h-32 rounded-2xl bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center shadow-lg ring-4 ring-white print:w-20 print:h-20 print:rounded-lg print:ring-0 print:shadow-none print:bg-gray-300">
                            <i class="fas fa-user text-white text-4xl print:text-gray-600 print:text-2xl"></i>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Status Badge -->
                    <div class="absolute -bottom-2 -right-2 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-medium shadow-lg print:hidden">
                        <i class="fas fa-check-circle mr-1"></i>
                        Active
                    </div>
                </div>
                
                <!-- Basic Info -->
                <div class="flex-1 print:profile-info">
                    <div class="mb-4 print:mb-0">
                        <h2 class="text-3xl font-bold text-gray-900 mb-2 print:profile-name"><?= htmlspecialchars($student['full_name']) ?></h2>
                        <?php if (!empty($student['christian_name'])): ?>
                            <p class="text-lg text-gray-600 mb-2 print:profile-christian">
                                <i class="fas fa-cross mr-2 text-yellow-500 print:hidden"></i>
                                <span class="print:text-xs">Christian Name: <?= htmlspecialchars($student['christian_name']) ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 print:profile-stats">
                        <?php $etBirth = ethiopian_display_from_student($student); $ageEt = ethiopian_age_from_student($student); ?>
                        <div class="bg-blue-50 rounded-lg p-3 text-center print:stat-box">
                            <div class="text-blue-600 text-xl font-bold print:stat-value"><?= $student['gender'] === 'male' ? 'ወንድ' : 'ሴት' ?></div>
                            <div class="text-blue-500 text-sm print:stat-label">Gender</div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 text-center print:stat-box">
                            <div class="text-green-600 text-xl font-bold print:stat-value"><?= $ageEt !== null ? $ageEt : 'N/A' ?></div>
                            <div class="text-green-500 text-sm print:stat-label">Age</div>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-3 text-center print:stat-box">
                            <div class="text-purple-600 text-lg font-bold print:stat-value"><?= htmlspecialchars($student['current_grade'] ?: 'N/A') ?></div>
                            <div class="text-purple-500 text-sm print:stat-label">Grade</div>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-3 text-center print:stat-box">
                            <div class="text-orange-600 text-sm font-bold print:stat-value"><?= htmlspecialchars($etBirth ?: 'N/A') ?></div>
                            <div class="text-orange-500 text-sm print:stat-label">Birth Date</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Information Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-6 print:info-grid">
            <!-- Contact Information -->
            <div class="glass-card rounded-xl p-6 print:shadow-none print:border print:info-section">
                <div class="flex items-center mb-4 print:mb-2">
                    <div class="bg-blue-500 p-3 rounded-lg mr-3 print:hidden">
                        <i class="fas fa-address-book text-white text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 print:info-title">Contact Information</h3>
                </div>
                <div class="space-y-3 print:space-y-1">
                    <div class="flex items-center print:info-item">
                        <i class="fas fa-phone text-blue-500 w-5 mr-3 print:hidden"></i>
                        <span class="text-gray-600 text-sm mr-2 print:info-label">Phone:</span>
                        <span class="font-medium print:info-value"><?= htmlspecialchars($student['phone_number'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="flex items-center print:info-item">
                        <i class="fas fa-map-marker-alt text-blue-500 w-5 mr-3 print:hidden"></i>
                        <span class="text-gray-600 text-sm mr-2 print:info-label">Sub City:</span>
                        <span class="font-medium print:info-value"><?= htmlspecialchars($student['sub_city'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="flex items-center print:info-item">
                        <i class="fas fa-location-dot text-blue-500 w-5 mr-3 print:hidden"></i>
                        <span class="text-gray-600 text-sm mr-2 print:info-label">District:</span>
                        <span class="font-medium print:info-value"><?= htmlspecialchars($student['district'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="flex items-center print:info-item">
                        <i class="fas fa-map text-blue-500 w-5 mr-3 print:hidden"></i>
                        <span class="text-gray-600 text-sm mr-2 print:info-label">Area:</span>
                        <span class="font-medium print:info-value"><?= htmlspecialchars($student['specific_area'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="flex items-center print:info-item">
                        <i class="fas fa-home text-blue-500 w-5 mr-3 print:hidden"></i>
                        <span class="text-gray-600 text-sm mr-2 print:info-label">House No:</span>
                        <span class="font-medium print:info-value"><?= htmlspecialchars($student['house_number'] ?: 'Not provided') ?></span>
                    </div>
                    <?php if (!empty($student['living_with'])): ?>
                    <div class="flex items-center print:info-item">
                        <i class="fas fa-users text-blue-500 w-5 mr-3 print:hidden"></i>
                        <span class="text-gray-600 text-sm mr-2 print:info-label">Living with:</span>
                        <span class="font-medium print:info-value"><?= htmlspecialchars($student['living_with']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Family Information -->
            <?php $ageEt = ethiopian_age_from_student($student); ?>
            <?php if ($ageEt !== null && $ageEt < 18): ?>
            <div class="glass-card rounded-xl p-6 print:shadow-none print:border print:info-section">
                <div class="flex items-center mb-4 print:mb-2">
                    <div class="bg-green-500 p-3 rounded-lg mr-3 print:hidden">
                        <i class="fas fa-users text-white text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 print:info-title">Family Information</h3>
                </div>
                <div class="space-y-4 print:space-y-1">
                    <!-- Father -->
                    <div class="border-l-4 border-blue-400 pl-4 print:border-l-2 print:pl-2">
                        <h4 class="font-semibold text-gray-900 mb-2 print:text-xs print:mb-1 print:font-bold">
                            <i class="fas fa-male text-blue-600 mr-2 print:hidden"></i>Father
                        </h4>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Name:</strong> <span class="print:info-value"><?= htmlspecialchars($student['father_full_name'] ?: 'Not provided') ?></span></p>
                        <?php if (!empty($student['father_christian_name'])): ?>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Christian:</strong> <span class="print:info-value"><?= htmlspecialchars($student['father_christian_name']) ?></span></p>
                        <?php endif; ?>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Phone:</strong> <span class="print:info-value"><?= htmlspecialchars($student['father_phone'] ?: 'Not provided') ?></span></p>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Work:</strong> <span class="print:info-value"><?= htmlspecialchars($student['father_occupation'] ?: 'Not provided') ?></span></p>
                    </div>
                    
                    <!-- Mother -->
                    <div class="border-l-4 border-pink-400 pl-4 print:border-l-2 print:pl-2">
                        <h4 class="font-semibold text-gray-900 mb-2 print:text-xs print:mb-1 print:font-bold">
                            <i class="fas fa-female text-pink-600 mr-2 print:hidden"></i>Mother
                        </h4>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Name:</strong> <span class="print:info-value"><?= htmlspecialchars($student['mother_full_name'] ?: 'Not provided') ?></span></p>
                        <?php if (!empty($student['mother_christian_name'])): ?>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Christian:</strong> <span class="print:info-value"><?= htmlspecialchars($student['mother_christian_name']) ?></span></p>
                        <?php endif; ?>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Phone:</strong> <span class="print:info-value"><?= htmlspecialchars($student['mother_phone'] ?: 'Not provided') ?></span></p>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Work:</strong> <span class="print:info-value"><?= htmlspecialchars($student['mother_occupation'] ?: 'Not provided') ?></span></p>
                    </div>
                    
                    <!-- Guardian -->
                    <?php if (!empty($student['guardian_full_name'])): ?>
                    <div class="border-l-4 border-yellow-400 pl-4 print:border-l-2 print:pl-2">
                        <h4 class="font-semibold text-gray-900 mb-2 print:text-xs print:mb-1 print:font-bold">
                            <i class="fas fa-user-shield text-yellow-600 mr-2 print:hidden"></i>Guardian
                        </h4>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Name:</strong> <span class="print:info-value"><?= htmlspecialchars($student['guardian_full_name']) ?></span></p>
                        <?php if (!empty($student['guardian_christian_name'])): ?>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Christian:</strong> <span class="print:info-value"><?= htmlspecialchars($student['guardian_christian_name']) ?></span></p>
                        <?php endif; ?>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Phone:</strong> <span class="print:info-value"><?= htmlspecialchars($student['guardian_phone'] ?: 'Not provided') ?></span></p>
                        <p class="text-sm print:info-item"><strong class="print:info-label">Work:</strong> <span class="print:info-value"><?= htmlspecialchars($student['guardian_occupation'] ?: 'Not provided') ?></span></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Emergency Information for 18+ students -->
            <div class="glass-card rounded-xl p-6 print:shadow-none print:border">
                <div class="flex items-center mb-4">
                    <div class="bg-red-500 p-3 rounded-lg mr-3">
                        <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Emergency Contact</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <i class="fas fa-user text-red-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Name:</span>
                        <span class="font-medium"><?= htmlspecialchars($student['emergency_name'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-phone text-red-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Phone:</span>
                        <span class="font-medium"><?= htmlspecialchars($student['emergency_phone'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-phone-alt text-red-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Alt Phone:</span>
                        <span class="font-medium"><?= htmlspecialchars($student['emergency_alt_phone'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-map-marker-alt text-red-500 w-5 mr-3 mt-1"></i>
                        <div>
                            <span class="text-gray-600 text-sm">Address:</span>
                            <p class="font-medium"><?= htmlspecialchars($student['emergency_address'] ?: 'Not provided') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Education Information for 18+ students -->
            <div class="glass-card rounded-xl p-6 print:shadow-none print:border">
                <div class="flex items-center mb-4">
                    <div class="bg-purple-500 p-3 rounded-lg mr-3">
                        <i class="fas fa-graduation-cap text-white text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Education</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <i class="fas fa-university text-purple-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Level:</span>
                        <span class="font-medium"><?= htmlspecialchars($student['education_level'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-book text-purple-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Field:</span>
                        <span class="font-medium"><?= htmlspecialchars($student['field_of_study'] ?: 'Not provided') ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <!-- Spiritual & School Information -->
            <div class="glass-card rounded-xl p-6 print:shadow-none print:border">
                <div class="flex items-center mb-4">
                    <div class="bg-indigo-500 p-3 rounded-lg mr-3">
                        <i class="fas fa-church text-white text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Spiritual & School Info</h3>
                </div>
                <div class="space-y-4">
                    <!-- Spiritual Father -->
                    <div class="border-l-4 border-indigo-400 pl-4">
                        <h4 class="font-semibold text-gray-900 mb-2">
                            <i class="fas fa-cross text-indigo-600 mr-2"></i>Spiritual Father
                        </h4>
                        <p class="text-sm"><strong>Name:</strong> <?= htmlspecialchars($student['spiritual_father_name'] ?: 'Not provided') ?></p>
                        <p class="text-sm"><strong>Phone:</strong> <?= htmlspecialchars($student['spiritual_father_phone'] ?: 'Not provided') ?></p>
                        <?php if (!empty($student['spiritual_father_church'])): ?>
                        <p class="text-sm"><strong>Church:</strong> <?= htmlspecialchars($student['spiritual_father_church']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- School Information -->
                    <?php if (!empty($student['regular_school_name'])): ?>
                    <div class="border-l-4 border-blue-400 pl-4">
                        <h4 class="font-semibold text-gray-900 mb-2">
                            <i class="fas fa-school text-blue-600 mr-2"></i>Regular School
                        </h4>
                        <p class="text-sm"><strong>School:</strong> <?= htmlspecialchars($student['regular_school_name']) ?></p>
                        <p class="text-sm"><strong>Grade:</strong> <?= htmlspecialchars($student['regular_school_grade'] ?: 'Not provided') ?></p>
                        <p class="text-sm"><strong>Year Start:</strong> <?= htmlspecialchars($student['school_year_start'] ?: 'Not provided') ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Additional Information Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Personal Attributes -->
            <div class="glass-card rounded-xl p-6 print:shadow-none print:border">
                <div class="flex items-center mb-4">
                    <div class="bg-orange-500 p-3 rounded-lg mr-3">
                        <i class="fas fa-user-check text-white text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Personal Information</h3>
                </div>
                    <div class="space-y-3">
                        <?php if (!empty($student['special_interests'])): ?>
                        <div class="flex items-start">
                            <i class="fas fa-heart text-orange-500 w-5 mr-3 mt-1"></i>
                            <div>
                                <span class="text-gray-600 text-sm">Special Interests:</span>
                                <p class="font-medium"><?= htmlspecialchars($student['special_interests']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($student['siblings_in_school'])): ?>
                        <div class="flex items-center">
                            <i class="fas fa-users text-orange-500 w-5 mr-3"></i>
                            <span class="text-gray-600 text-sm mr-2">Siblings in School:</span>
                            <span class="font-medium"><?= htmlspecialchars($student['siblings_in_school']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($student['physical_disability'])): ?>
                        <div class="flex items-start">
                            <i class="fas fa-accessibility text-orange-500 w-5 mr-3 mt-1"></i>
                            <div>
                                <span class="text-gray-600 text-sm">Physical Disability:</span>
                                <p class="font-medium"><?= htmlspecialchars($student['physical_disability']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($student['weak_side'])): ?>
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-orange-500 w-5 mr-3 mt-1"></i>
                            <div>
                                <span class="text-gray-600 text-sm">Areas for Improvement:</span>
                                <p class="font-medium"><?= htmlspecialchars($student['weak_side']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Additional Personal Details -->
                        <?php if (!empty($student['has_spiritual_father'])): ?>
                        <div class="flex items-center">
                            <i class="fas fa-pray text-orange-500 w-5 mr-3"></i>
                            <span class="text-gray-600 text-sm mr-2">Spiritual Father Source:</span>
                            <span class="font-medium capitalize"><?= htmlspecialchars(str_replace('_', ' ', $student['has_spiritual_father'])) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($student['living_with'])): ?>
                        <div class="flex items-center">
                            <i class="fas fa-home text-orange-500 w-5 mr-3"></i>
                            <span class="text-gray-600 text-sm mr-2">Living Arrangement:</span>
                            <span class="font-medium capitalize"><?= htmlspecialchars(str_replace('_', ' ', $student['living_with'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
            </div>

            <!-- Background Information -->
            <div class="glass-card rounded-xl p-6 print:shadow-none print:border">
                <div class="flex items-center mb-4">
                    <div class="bg-teal-500 p-3 rounded-lg mr-3">
                        <i class="fas fa-history text-white text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Background Information</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <i class="fas fa-exchange-alt text-teal-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Transferred from Other School:</span>
                        <span class="font-medium">
                            <?= $student['transferred_from_other_school'] === 'yes' ? 'Yes' : 'No' ?>
                        </span>
                    </div>
                    
                    <div class="flex items-center">
                        <i class="fas fa-place-of-worship text-teal-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Came from Other Religion:</span>
                        <span class="font-medium">
                            <?= $student['came_from_other_religion'] === 'yes' ? 'Yes' : 'No' ?>
                        </span>
                    </div>
                    
                    <div class="flex items-center">
                        <i class="fas fa-calendar-plus text-teal-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Registration Date:</span>
                        <span class="font-medium"><?= date('F j, Y', strtotime($student['created_at'])) ?></span>
                    </div>
                    
                    <div class="flex items-center">
                        <i class="fas fa-clock text-teal-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Member Since:</span>
                        <span class="font-medium">
                            <?php 
                            $memberSince = new DateTime($student['created_at']);
                            $now = new DateTime();
                            $diff = $now->diff($memberSince);
                            echo $diff->y > 0 ? $diff->y . ' years' : ($diff->m > 0 ? $diff->m . ' months' : $diff->d . ' days');
                            ?>
                        </span>
                    </div>
                    
                    <!-- Ethiopian Calendar Details if Available -->
                    <?php if (isset($student['birth_year_et']) && $student['birth_year_et']): ?>
                    <div class="flex items-start">
                        <i class="fas fa-calendar-alt text-teal-500 w-5 mr-3 mt-1"></i>
                        <div>
                            <span class="text-gray-600 text-sm">Ethiopian Calendar Birth Details:</span>
                            <p class="font-medium">
                                Year: <?= htmlspecialchars($student['birth_year_et']) ?>, 
                                Month: <?= htmlspecialchars($student['birth_month_et']) ?> (<?= et_month_name((int)$student['birth_month_et']) ?>), 
                                Day: <?= htmlspecialchars($student['birth_day_et']) ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- System Information -->
                    <div class="flex items-center">
                        <i class="fas fa-id-card text-teal-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Student ID:</span>
                        <span class="font-medium">#<?= str_pad($student['id'], 4, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    
                    <?php if (!empty($student['flagged']) && $student['flagged']): ?>
                    <div class="flex items-center">
                        <i class="fas fa-flag text-red-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Status:</span>
                        <span class="font-medium text-red-600">Flagged for Review</span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Additional Database Information -->
                    <?php if (!empty($student['school_year_start'])): ?>
                    <div class="flex items-center">
                        <i class="fas fa-calendar-check text-teal-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">School Year Started:</span>
                        <span class="font-medium"><?= htmlspecialchars($student['school_year_start']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($student['education_level'])): ?>
                    <div class="flex items-center">
                        <i class="fas fa-graduation-cap text-teal-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Education Level:</span>
                        <span class="font-medium"><?= htmlspecialchars($student['education_level']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($student['field_of_study'])): ?>
                    <div class="flex items-center">
                        <i class="fas fa-book-open text-teal-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Field of Study:</span>
                        <span class="font-medium"><?= htmlspecialchars($student['field_of_study']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($student['regular_school_grade'])): ?>
                    <div class="flex items-center">
                        <i class="fas fa-layer-group text-teal-500 w-5 mr-3"></i>
                        <span class="text-gray-600 text-sm mr-2">Regular School Grade:</span>
                        <span class="font-medium"><?= htmlspecialchars($student['regular_school_grade']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Instrument Registration Section -->
        <?php
        // Fetch instrument registration info for this student
        $inst_stmt = $pdo->prepare("SELECT * FROM instrument_registrations WHERE student_id = ? ORDER BY created_at DESC");
        $inst_stmt->execute([$student_id]);
        $instruments = $inst_stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <?php if ($instruments): ?>
        <div class="glass-card rounded-xl p-6 mb-6 print:shadow-none print:border">
        <div class="flex items-center mb-6">
                <div class="bg-amber-500 p-3 rounded-lg mr-3">
                    <i class="fas fa-music text-white text-lg"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Instrument Registration</h3>
                <div class="ml-auto bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-sm font-medium">
                    <?= count($instruments) ?> Instrument<?= count($instruments) > 1 ? 's' : '' ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($instruments as $instrument): ?>
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-lg p-4 border border-amber-200 hover:shadow-lg transition-all duration-200">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center">
                            <div class="bg-amber-500 p-2 rounded-lg mr-3">
                                <i class="fas fa-guitar text-white text-sm"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-amber-800 text-lg"><?= htmlspecialchars($instrument['instrument']) ?></h4>
                                <p class="text-amber-600 text-sm">
                                    <i class="fas fa-calendar mr-1"></i>
                                    Registered: <?= date('M j, Y', strtotime($instrument['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($instrument['person_photo_path'])): ?>
                        <?php
                        $imgPath = $instrument['person_photo_path'];
                        // Normalize slashes and prepend uploads/ if needed
                        $imgPath = str_replace(['\\', '//'], '/', $imgPath);
                        if (!preg_match('/^(https?:)?\//', $imgPath) && strpos($imgPath, 'uploads/') !== 0) {
                            $imgPath = 'uploads/' . ltrim($imgPath, '/');
                        }
                        // Check if file exists on server
                        $imgFile = $_SERVER['DOCUMENT_ROOT'] . '/' . $imgPath;
                        if (file_exists($imgFile)): ?>
                        <div class="mt-3">
                            <div class="relative w-full h-32 rounded-lg overflow-hidden border-2 border-amber-300">
                                <img src="<?= htmlspecialchars($imgPath) ?>" alt="Instrument Photo" 
                                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-200">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Additional instrument details if available -->
                    <?php if (!empty($instrument['notes']) || !empty($instrument['level'])): ?>
                    <div class="mt-3 pt-3 border-t border-amber-200">
                        <?php if (!empty($instrument['level'])): ?>
                        <div class="flex items-center mb-2">
                            <i class="fas fa-star text-amber-500 w-4 mr-2"></i>
                            <span class="text-amber-700 text-sm font-medium">Level: <?= htmlspecialchars($instrument['level']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($instrument['notes'])): ?>
                        <div class="flex items-start">
                            <i class="fas fa-sticky-note text-amber-500 w-4 mr-2 mt-0.5"></i>
                            <p class="text-amber-700 text-sm"><?= htmlspecialchars($instrument['notes']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- No Instruments Registered -->
        <div class="glass-card rounded-xl p-6 mb-6 print:shadow-none print:border">
            <div class="text-center py-8">
                <div class="bg-gray-100 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                    <i class="fas fa-music text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">No Instruments Registered</h3>
                <p class="text-gray-500 text-sm">This student has not registered for any instruments yet.</p>
                <a href="instrument_registration.php?student_id=<?= $student_id ?>" 
                   class="inline-flex items-center mt-4 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors duration-200 print:hidden">
                    <i class="fas fa-plus mr-2"></i>
                    Register Instrument
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="bg-gradient-to-r from-blue-900 to-blue-800 text-white py-6 print:hidden">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="border-t border-blue-700 pt-3">
                    <p class="text-xs text-blue-300">
                        © <?= date('Y') ?> ሰሚት ፍኖተ ሰላም ሰ/ት ት/ቤት - All rights reserved
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Advanced Edit Drawer -->
    <div id="editDrawer" class="fixed inset-0 z-50 flex items-start justify-end bg-black bg-opacity-50 backdrop-blur-sm transform translate-x-full transition-transform duration-300 ease-in-out">
        <!-- Drawer Panel -->
        <div class="w-full max-w-md h-full bg-white shadow-2xl transform transition-transform duration-300 ease-in-out translate-x-full">
            <!-- Drawer Header -->
            <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 px-6 py-4 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white/20 p-2 rounded-lg">
                            <i class="fas fa-user-edit text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold">Edit Student Profile</h2>
                            <p class="text-emerald-100 text-sm"><?= htmlspecialchars($student['full_name']) ?></p>
                        </div>
                    </div>
                    <button onclick="closeEditDrawer()" class="bg-white/20 hover:bg-white/30 p-2 rounded-lg transition-colors duration-200">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Drawer Content -->
            <div class="h-full overflow-y-auto pb-20">
                <!-- Photo Edit Section - Prominent Top Section -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-100 p-6 border-b">
                    <div class="text-center">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center justify-center">
                            <i class="fas fa-camera text-blue-600 mr-2"></i>
                            Profile Photo
                        </h3>
                        
                        <!-- Current Photo Display -->
                        <div class="relative inline-block mb-4">
                            <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-white shadow-lg">
            <?php if (!empty($student['photo_path'])): ?>
                                    <img id="currentPhoto" src="<?= htmlspecialchars($student['photo_path']) ?>" alt="Current photo" class="w-full h-full object-cover">
            <?php else: ?>
                                    <div id="currentPhoto" class="w-full h-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 text-2xl"></i>
                </div>
            <?php endif; ?>
                            </div>
                            <div class="absolute -bottom-1 -right-1 bg-blue-600 text-white p-1.5 rounded-full shadow-lg">
                                <i class="fas fa-camera text-xs"></i>
                            </div>
                        </div>
                        
                        <!-- Photo Actions -->
                        <div class="space-y-3">
                            <button onclick="triggerPhotoUpload()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                <i class="fas fa-upload"></i>
                                <span>Upload New Photo</span>
                            </button>
                            <div class="flex space-x-2">
                                <button onclick="capturePhoto()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-2 px-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                    <i class="fas fa-camera"></i>
                                    <span class="text-sm">Take Photo</span>
                                </button>
                                <?php if (!empty($student['photo_path'])): ?>
                                <button onclick="removePhoto()" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                    <i class="fas fa-trash"></i>
                                    <span class="text-sm">Remove</span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Hidden File Input -->
                        <input type="file" id="photoInput" accept="image/*" class="hidden" onchange="handlePhotoUpload(event)">
                    </div>
                </div>

                <!-- Edit Categories -->
                <div class="p-6 space-y-4">
                    <!-- Personal Information -->
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden transition-all duration-300 hover:shadow-md" id="personalSection">
                        <button onclick="toggleSection('personal')" class="w-full flex items-center justify-between text-left p-4">
                            <div class="flex items-center space-x-3">
                                <div class="bg-purple-100 p-2 rounded-lg">
                                    <i class="fas fa-user text-purple-600"></i>
                                </div>
            <div>
                                    <h3 class="font-semibold text-gray-800">Personal Information</h3>
                                    <p class="text-sm text-gray-500">Name, gender, birth date, grade</p>
            </div>
        </div>
                            <i class="fas fa-chevron-right text-gray-400 transform transition-transform duration-200" id="personalChevron"></i>
                        </button>
                        
                        <!-- Expandable Form -->
                        <div class="hidden border-t border-gray-100 p-4 bg-gray-50" id="personalForm">
                            <form onsubmit="saveSection('personal', event)" class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
            <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                        <input type="text" name="full_name" value="<?= htmlspecialchars($student['full_name']) ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Christian Name</label>
                                        <input type="text" name="christian_name" value="<?= htmlspecialchars($student['christian_name'] ?? '') ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                            <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                                <option value="male" <?= $student['gender'] === 'male' ? 'selected' : '' ?>>ወንድ (Male)</option>
                                                <option value="female" <?= $student['gender'] === 'female' ? 'selected' : '' ?>>ሴት (Female)</option>
                                            </select>
        </div>
        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                                            <input type="text" name="current_grade" value="<?= htmlspecialchars($student['current_grade'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>
        </div>
                                    <div>
                                        <label class="block text-gray-700 mb-1">የተወለዱበት ቀን (ዓ.ም) <span class="text-red-500">*</span></label>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                            <select id="view_birth_year_et" name="birth_year_et" class="px-3 py-2 border rounded" required>
                                                <option value="">ዓመት</option>
                                            </select>
                                            <select id="view_birth_month_et" name="birth_month_et" class="px-3 py-2 border rounded" required>
                                                <option value="">ወር</option>
                                            </select>
                                            <select id="view_birth_day_et" name="birth_day_et" class="px-3 py-2 border rounded" required>
                                                <option value="">ቀን</option>
                                            </select>
                                        </div>
                                        <!-- Hidden field to maintain compatibility with existing backend -->
                                        <input type="hidden" name="birth_date" id="view_birth_date_hidden">
                                    </div>
                                </div>
                                <div class="flex space-x-3 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                        <i class="fas fa-save"></i>
                                        <span>Save Changes</span>
                                    </button>
                                    <button type="button" onclick="cancelEdit('personal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden transition-all duration-300 hover:shadow-md" id="contactSection">
                        <button onclick="toggleSection('contact')" class="w-full flex items-center justify-between text-left p-4">
                            <div class="flex items-center space-x-3">
                                <div class="bg-blue-100 p-2 rounded-lg">
                                    <i class="fas fa-address-book text-blue-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">Contact Information</h3>
                                    <p class="text-sm text-gray-500">Phone, address, location details</p>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 transform transition-transform duration-200" id="contactChevron"></i>
                        </button>
                        
                        <!-- Expandable Form -->
                        <div class="hidden border-t border-gray-100 p-4 bg-gray-50" id="contactForm">
                            <form onsubmit="saveSection('contact', event)" class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                        <input type="tel" name="phone_number" value="<?= htmlspecialchars($student['phone_number'] ?? '') ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Sub City</label>
                                            <input type="text" name="sub_city" value="<?= htmlspecialchars($student['sub_city'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                                            <input type="text" name="district" value="<?= htmlspecialchars($student['district'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Specific Area</label>
                                        <input type="text" name="specific_area" value="<?= htmlspecialchars($student['specific_area'] ?? '') ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">House Number</label>
                                            <input type="text" name="house_number" value="<?= htmlspecialchars($student['house_number'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Living With</label>
                                            <select name="living_with" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                <option value="">Select...</option>
                                                <option value="parents" <?= ($student['living_with'] ?? '') === 'parents' ? 'selected' : '' ?>>Parents</option>
                                                <option value="guardian" <?= ($student['living_with'] ?? '') === 'guardian' ? 'selected' : '' ?>>Guardian</option>
                                                <option value="alone" <?= ($student['living_with'] ?? '') === 'alone' ? 'selected' : '' ?>>Alone</option>
                                                <option value="relatives" <?= ($student['living_with'] ?? '') === 'relatives' ? 'selected' : '' ?>>Relatives</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex space-x-3 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                        <i class="fas fa-save"></i>
                                        <span>Save Changes</span>
                                    </button>
                                    <button type="button" onclick="cancelEdit('contact')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Family Information -->
                    <?php $ageEt = ethiopian_age_from_student($student); ?>
                    <?php if ($ageEt !== null && $ageEt < 18): ?>
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden transition-all duration-300 hover:shadow-md" id="familySection">
                        <button onclick="toggleSection('family')" class="w-full flex items-center justify-between text-left p-4">
                            <div class="flex items-center space-x-3">
                                <div class="bg-green-100 p-2 rounded-lg">
                                    <i class="fas fa-users text-green-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">Family Information</h3>
                                    <p class="text-sm text-gray-500">Parents and guardian details</p>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 transform transition-transform duration-200" id="familyChevron"></i>
                        </button>
                        
                        <!-- Expandable Form -->
                        <div class="hidden border-t border-gray-100 p-4 bg-gray-50" id="familyForm">
                            <form onsubmit="saveSection('family', event)" class="space-y-6">
                                <!-- Father Information -->
                                <div class="border-l-4 border-blue-400 pl-4 space-y-3">
                                    <h4 class="font-semibold text-gray-800 flex items-center"><i class="fas fa-male text-blue-600 mr-2"></i>Father</h4>
                                    <div class="grid grid-cols-1 gap-3">
                                        <input type="text" name="father_full_name" placeholder="Full Name" value="<?= htmlspecialchars($student['father_full_name'] ?? '') ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="tel" name="father_phone" placeholder="Phone" value="<?= htmlspecialchars($student['father_phone'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <input type="text" name="father_occupation" placeholder="Occupation" value="<?= htmlspecialchars($student['father_occupation'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Mother Information -->
                                <div class="border-l-4 border-pink-400 pl-4 space-y-3">
                                    <h4 class="font-semibold text-gray-800 flex items-center"><i class="fas fa-female text-pink-600 mr-2"></i>Mother</h4>
                                    <div class="grid grid-cols-1 gap-3">
                                        <input type="text" name="mother_full_name" placeholder="Full Name" value="<?= htmlspecialchars($student['mother_full_name'] ?? '') ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="tel" name="mother_phone" placeholder="Phone" value="<?= htmlspecialchars($student['mother_phone'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <input type="text" name="mother_occupation" placeholder="Occupation" value="<?= htmlspecialchars($student['mother_occupation'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Guardian Information -->
                                <div class="border-l-4 border-yellow-400 pl-4 space-y-3">
                                    <h4 class="font-semibold text-gray-800 flex items-center"><i class="fas fa-user-shield text-yellow-600 mr-2"></i>Guardian (Optional)</h4>
                                    <div class="grid grid-cols-1 gap-3">
                                        <input type="text" name="guardian_full_name" placeholder="Full Name" value="<?= htmlspecialchars($student['guardian_full_name'] ?? '') ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="tel" name="guardian_phone" placeholder="Phone" value="<?= htmlspecialchars($student['guardian_phone'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <input type="text" name="guardian_occupation" placeholder="Occupation" value="<?= htmlspecialchars($student['guardian_occupation'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-3 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                        <i class="fas fa-save"></i>
                                        <span>Save Changes</span>
                                    </button>
                                    <button type="button" onclick="cancelEdit('family')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Emergency Contact -->
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden transition-all duration-300 hover:shadow-md" id="emergencySection">
                        <button onclick="toggleSection('emergency')" class="w-full flex items-center justify-between text-left p-4">
                            <div class="flex items-center space-x-3">
                                <div class="bg-red-100 p-2 rounded-lg">
                                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">Emergency Contact</h3>
                                    <p class="text-sm text-gray-500">Emergency contact information</p>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 transform transition-transform duration-200" id="emergencyChevron"></i>
                        </button>
                        
                        <!-- Expandable Form -->
                        <div class="hidden border-t border-gray-100 p-4 bg-gray-50" id="emergencyForm">
                            <form onsubmit="saveSection('emergency', event)" class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                                        <input type="text" name="emergency_name" value="<?= htmlspecialchars($student['emergency_name'] ?? '') ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Phone</label>
                                            <input type="tel" name="emergency_phone" value="<?= htmlspecialchars($student['emergency_phone'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Alternative Phone</label>
                                            <input type="tel" name="emergency_alt_phone" value="<?= htmlspecialchars($student['emergency_alt_phone'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Address</label>
                                        <textarea name="emergency_address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"><?= htmlspecialchars($student['emergency_address'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="flex space-x-3 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                        <i class="fas fa-save"></i>
                                        <span>Save Changes</span>
                                    </button>
                                    <button type="button" onclick="cancelEdit('emergency')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Education & School -->
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden transition-all duration-300 hover:shadow-md" id="educationSection">
                        <button onclick="toggleSection('education')" class="w-full flex items-center justify-between text-left p-4">
                            <div class="flex items-center space-x-3">
                                <div class="bg-indigo-100 p-2 rounded-lg">
                                    <i class="fas fa-graduation-cap text-indigo-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">Education & School</h3>
                                    <p class="text-sm text-gray-500">Academic information</p>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 transform transition-transform duration-200" id="educationChevron"></i>
                        </button>
                        
                        <!-- Expandable Form -->
                        <div class="hidden border-t border-gray-100 p-4 bg-gray-50" id="educationForm">
                            <form onsubmit="saveSection('education', event)" class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Current Grade</label>
                                            <select name="current_grade" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                                <option value="">Select Grade</option>
                                                <option value="1st" <?= ($student['current_grade'] ?? '') === '1st' ? 'selected' : '' ?>>1st Grade</option>
                                                <option value="2nd" <?= ($student['current_grade'] ?? '') === '2nd' ? 'selected' : '' ?>>2nd Grade</option>
                                                <option value="3rd" <?= ($student['current_grade'] ?? '') === '3rd' ? 'selected' : '' ?>>3rd Grade</option>
                                                <option value="4th" <?= ($student['current_grade'] ?? '') === '4th' ? 'selected' : '' ?>>4th Grade</option>
                                                <option value="5th" <?= ($student['current_grade'] ?? '') === '5th' ? 'selected' : '' ?>>5th Grade</option>
                                                <option value="6th" <?= ($student['current_grade'] ?? '') === '6th' ? 'selected' : '' ?>>6th Grade</option>
                                                <option value="7th" <?= ($student['current_grade'] ?? '') === '7th' ? 'selected' : '' ?>>7th Grade</option>
                                                <option value="8th" <?= ($student['current_grade'] ?? '') === '8th' ? 'selected' : '' ?>>8th Grade</option>
                                                <option value="9th" <?= ($student['current_grade'] ?? '') === '9th' ? 'selected' : '' ?>>9th Grade</option>
                                                <option value="10th" <?= ($student['current_grade'] ?? '') === '10th' ? 'selected' : '' ?>>10th Grade</option>
                                                <option value="11th" <?= ($student['current_grade'] ?? '') === '11th' ? 'selected' : '' ?>>11th Grade</option>
                                                <option value="12th" <?= ($student['current_grade'] ?? '') === '12th' ? 'selected' : '' ?>>12th Grade</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                                <option value="active" <?= ($student['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="inactive" <?= ($student['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                <option value="graduated" <?= ($student['status'] ?? '') === 'graduated' ? 'selected' : '' ?>>Graduated</option>
                                                <option value="transferred" <?= ($student['status'] ?? '') === 'transferred' ? 'selected' : '' ?>>Transferred</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Field of Study (for higher grades)</label>
                                        <input type="text" name="field_of_study" value="<?= htmlspecialchars($student['field_of_study'] ?? '') ?>" 
                                               placeholder="e.g., Science, Social Science, etc." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Previous School (if transferred)</label>
                                        <input type="text" name="previous_school" value="<?= htmlspecialchars($student['transferred_from_other_school'] ?? '') ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    </div>
                                </div>
                                <div class="flex space-x-3 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                        <i class="fas fa-save"></i>
                                        <span>Save Changes</span>
                                    </button>
                                    <button type="button" onclick="cancelEdit('education')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Spiritual Information -->
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden transition-all duration-300 hover:shadow-md" id="spiritualSection">
                        <button onclick="toggleSection('spiritual')" class="w-full flex items-center justify-between text-left p-4">
                            <div class="flex items-center space-x-3">
                                <div class="bg-yellow-100 p-2 rounded-lg">
                                    <i class="fas fa-cross text-yellow-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">Spiritual Information</h3>
                                    <p class="text-sm text-gray-500">Spiritual father and church info</p>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 transform transition-transform duration-200" id="spiritualChevron"></i>
                        </button>
                        
                        <!-- Expandable Form -->
                        <div class="hidden border-t border-gray-100 p-4 bg-gray-50" id="spiritualForm">
                            <form onsubmit="saveSection('spiritual', event)" class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                            <input type="checkbox" name="has_spiritual_father" <?= !empty($student['has_spiritual_father']) ? 'checked' : '' ?> 
                                                   class="mr-2 rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                                            Has Spiritual Father
                                        </label>
                                    </div>
                                    <div id="spiritualFatherDetails" class="space-y-3 <?= empty($student['has_spiritual_father']) ? 'opacity-50' : '' ?>">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Spiritual Father Name</label>
                                            <input type="text" name="spiritual_father_name" value="<?= htmlspecialchars($student['spiritual_father_name'] ?? '') ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                                <input type="tel" name="spiritual_father_phone" value="<?= htmlspecialchars($student['spiritual_father_phone'] ?? '') ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Church</label>
                                                <input type="text" name="spiritual_father_church" value="<?= htmlspecialchars($student['spiritual_father_church'] ?? '') ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Previous Religion (if converted)</label>
                                        <input type="text" name="came_from_other_religion" value="<?= htmlspecialchars($student['came_from_other_religion'] ?? '') ?>" 
                                               placeholder="e.g., Protestant, Muslim, etc." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                    </div>
                                </div>
                                <div class="flex space-x-3 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                        <i class="fas fa-save"></i>
                                        <span>Save Changes</span>
                                    </button>
                                    <button type="button" onclick="cancelEdit('spiritual')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden transition-all duration-300 hover:shadow-md" id="additionalSection">
                        <button onclick="toggleSection('additional')" class="w-full flex items-center justify-between text-left p-4">
                            <div class="flex items-center space-x-3">
                                <div class="bg-orange-100 p-2 rounded-lg">
                                    <i class="fas fa-info-circle text-orange-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">Additional Information</h3>
                                    <p class="text-sm text-gray-500">Special interests, disabilities, etc.</p>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 transform transition-transform duration-200" id="additionalChevron"></i>
                        </button>
                        
                        <!-- Expandable Form -->
                        <div class="hidden border-t border-gray-100 p-4 bg-gray-50" id="additionalForm">
                            <form onsubmit="saveSection('additional', event)" class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Special Interests</label>
                                        <textarea name="special_interests" rows="3" placeholder="Sports, music, arts, etc." 
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"><?= htmlspecialchars($student['special_interests'] ?? '') ?></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Siblings in School</label>
                                            <select name="siblings_in_school" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                                <option value="0" <?= ($student['siblings_in_school'] ?? '0') === '0' ? 'selected' : '' ?>>None</option>
                                                <option value="1" <?= ($student['siblings_in_school'] ?? '') === '1' ? 'selected' : '' ?>>1 Sibling</option>
                                                <option value="2" <?= ($student['siblings_in_school'] ?? '') === '2' ? 'selected' : '' ?>>2 Siblings</option>
                                                <option value="3" <?= ($student['siblings_in_school'] ?? '') === '3' ? 'selected' : '' ?>>3 Siblings</option>
                                                <option value="4+" <?= ($student['siblings_in_school'] ?? '') === '4+' ? 'selected' : '' ?>>4+ Siblings</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Instrument</label>
                                            <input type="text" name="instrument" value="<?= htmlspecialchars($student['instrument'] ?? '') ?>" 
                                                   placeholder="e.g., Piano, Guitar, etc." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Physical Disability or Special Needs</label>
                                        <textarea name="physical_disability" rows="2" placeholder="Any physical disabilities or special accommodations needed" 
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"><?= htmlspecialchars($student['physical_disability'] ?? '') ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Areas for Improvement</label>
                                        <textarea name="weak_side" rows="2" placeholder="Areas where the student needs support or improvement" 
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"><?= htmlspecialchars($student['weak_side'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="flex space-x-3 pt-3 border-t border-gray-200">
                                    <button type="submit" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                        <i class="fas fa-save"></i>
                                        <span>Save Changes</span>
                                    </button>
                                    <button type="button" onclick="cancelEdit('additional')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="p-6 border-t border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <button onclick="openFullEditor()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                            <i class="fas fa-edit"></i>
                            <span>Open Full Editor</span>
                        </button>
                        <button onclick="duplicateStudent()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                            <i class="fas fa-copy"></i>
                            <span>Duplicate Student</span>
                        </button>
                        <button onclick="exportStudent()" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                            <i class="fas fa-download"></i>
                            <span>Export Data</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Edit Drawer Functions
        function openEditDrawer() {
            const drawer = document.getElementById('editDrawer');
            const panel = drawer.querySelector('.max-w-md');
            
            drawer.classList.remove('translate-x-full');
            setTimeout(() => {
                panel.classList.remove('translate-x-full');
            }, 10);
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function closeEditDrawer() {
            const drawer = document.getElementById('editDrawer');
            const panel = drawer.querySelector('.max-w-md');
            
            panel.classList.add('translate-x-full');
            setTimeout(() => {
                drawer.classList.add('translate-x-full');
                document.body.style.overflow = 'auto';
            }, 300);
        }

        // Close drawer when clicking backdrop
        document.getElementById('editDrawer').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditDrawer();
            }
        });

        // Photo Upload Functions
        function triggerPhotoUpload() {
            document.getElementById('photoInput').click();
        }

        function handlePhotoUpload(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) { // 5MB limit
                    showToast('File size too large. Please choose a file under 5MB.', 'error');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const currentPhoto = document.getElementById('currentPhoto');
                    if (currentPhoto.tagName === 'IMG') {
                        currentPhoto.src = e.target.result;
                    } else {
                        currentPhoto.innerHTML = `<img src="${e.target.result}" alt="New photo" class="w-full h-full object-cover">`;
                    }
                    
                    // Here you would typically upload the file to the server
                    uploadPhotoToServer(file);
                };
                reader.readAsDataURL(file);
            }
        }

        function uploadPhotoToServer(file) {
            const formData = new FormData();
            formData.append('photo', file);
            formData.append('student_id', '<?= $student_id ?>');
            
            showToast('Uploading photo...', 'info');
            
            fetch('api/upload_photo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Photo uploaded successfully!', 'success');
                    // Update the main profile photo as well
                    const mainPhoto = document.querySelector('.glass-card img');
                    if (mainPhoto) {
                        mainPhoto.src = data.photo_url;
                    }
                } else {
                    showToast(data.message || 'Error uploading photo', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error uploading photo', 'error');
            });
        }

        function capturePhoto() {
            // This would integrate with camera API
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(stream => {
                    // Open camera modal for photo capture
                    showToast('Camera feature coming soon!', 'info');
                    stream.getTracks().forEach(track => track.stop());
                })
                .catch(error => {
                    showToast('Camera access not available', 'warning');
                });
        }

        function removePhoto() {
            if (confirm('Are you sure you want to remove the current photo?')) {
                // API call to remove photo
                fetch('api/remove_photo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ student_id: '<?= $student_id ?>' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const currentPhoto = document.getElementById('currentPhoto');
                        currentPhoto.innerHTML = '<div class="w-full h-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center"><i class="fas fa-user text-gray-600 text-2xl"></i></div>';
                        showToast('Photo removed successfully!', 'success');
                    } else {
                        showToast(data.message || 'Error removing photo', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error removing photo', 'error');
                });
            }
        }

        // Section Edit Functions
        function toggleSection(section) {
            const form = document.getElementById(section + 'Form');
            const chevron = document.getElementById(section + 'Chevron');
            const isVisible = !form.classList.contains('hidden');
            
            // Close all other sections first
            const allSections = ['personal', 'contact', 'family', 'emergency', 'education', 'spiritual', 'additional'];
            allSections.forEach(s => {
                if (s !== section) {
                    const otherForm = document.getElementById(s + 'Form');
                    const otherChevron = document.getElementById(s + 'Chevron');
                    if (otherForm && !otherForm.classList.contains('hidden')) {
                        otherForm.classList.add('hidden');
                        if (otherChevron) {
                            otherChevron.classList.remove('rotate-90');
                        }
                    }
                }
            });
            
            // Toggle current section
            if (isVisible) {
                form.classList.add('hidden');
                if (chevron) {
                    chevron.classList.remove('rotate-90');
                }
            } else {
                form.classList.remove('hidden');
                if (chevron) {
                    chevron.classList.add('rotate-90');
                }
                // Focus first input
                const firstInput = form.querySelector('input, select, textarea');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 100);
                }
            }
        }
        
        function cancelEdit(section) {
            const form = document.getElementById(section + 'Form');
            const chevron = document.getElementById(section + 'Chevron');
            
            if (form) {
                form.classList.add('hidden');
            }
            if (chevron) {
                chevron.classList.remove('rotate-90');
            }
            
            // Reset form to original values
            const formElement = form.querySelector('form');
            if (formElement) {
                formElement.reset();
                // Reload current values from server or reset to displayed values
                location.reload();
            }
        }
        
        function saveSection(section, event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('section', section);
            formData.append('student_id', '<?= $student_id ?>');
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving...</span>';
            submitBtn.disabled = true;
            
            fetch('api/update_student_section.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Section updated successfully!', 'success');
                    
                    // Update the main profile display
                    updateProfileDisplay(section, data.updatedData);
                    
                    // Close the form
                    setTimeout(() => {
                        cancelEdit(section);
                    }, 1000);
                } else {
                    showToast(data.message || 'Error updating section', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating section', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        function updateProfileDisplay(section, data) {
            // Update the main profile display with new data
            switch(section) {
                case 'personal':
                    if (data.full_name) {
                        const nameElements = document.querySelectorAll('.profile-name, h2');
                        nameElements.forEach(el => {
                            if (el.textContent.includes('<?= $student["full_name"] ?>')) {
                                el.textContent = data.full_name;
                            }
                        });
                    }
                    break;
                case 'contact':
                    // Update contact information display
                    if (data.phone_number) {
                        const phoneElements = document.querySelectorAll('.info-value');
                        phoneElements.forEach(el => {
                            if (el.previousElementSibling && el.previousElementSibling.textContent.includes('Phone')) {
                                el.textContent = data.phone_number;
                            }
                        });
                    }
                    break;
                // Add more cases for other sections as needed
            }
        }

        function openFullEditor() {
            window.location.href = `student_edit.php?id=<?= $student_id ?>`;
        }

        function duplicateStudent() {
            if (confirm('Create a new student record based on this student\'s information?')) {
                window.location.href = `student_add.php?template=<?= $student_id ?>`;
            }
        }

        function exportStudent() {
            window.open(`api/export_student.php?id=<?= $student_id ?>&format=pdf`, '_blank');
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            // Remove existing toasts
            document.querySelectorAll('.toast').forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = `toast fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300`;
            
            const colors = {
                success: 'bg-green-500 text-white',
                error: 'bg-red-500 text-white',
                warning: 'bg-yellow-500 text-white',
                info: 'bg-blue-500 text-white'
            };
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            toast.className += ' ' + colors[type];
            toast.innerHTML = `<div class="flex items-center space-x-2"><i class="fas ${icons[type]}"></i><span>${message}</span></div>`;
            
            document.body.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.remove('translate-x-full'), 100);
            
            // Hide toast after 3 seconds
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditDrawer();
                // Close any open sections
                const allSections = ['personal', 'contact', 'family', 'emergency', 'education', 'spiritual', 'additional'];
                allSections.forEach(section => {
                    const form = document.getElementById(section + 'Form');
                    const chevron = document.getElementById(section + 'Chevron');
                    if (form && !form.classList.contains('hidden')) {
                        form.classList.add('hidden');
                        if (chevron) {
                            chevron.classList.remove('rotate-90');
                        }
                    }
                });
            }
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                openEditDrawer();
            }
        });
        
        // Additional form interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Spiritual father checkbox toggle
            const spiritualCheckbox = document.querySelector('input[name="has_spiritual_father"]');
            if (spiritualCheckbox) {
                spiritualCheckbox.addEventListener('change', function() {
                    const details = document.getElementById('spiritualFatherDetails');
                    if (details) {
                        if (this.checked) {
                            details.classList.remove('opacity-50');
                            details.querySelectorAll('input').forEach(input => input.disabled = false);
                        } else {
                            details.classList.add('opacity-50');
                            details.querySelectorAll('input').forEach(input => {
                                input.disabled = true;
                                input.value = '';
                            });
                        }
                    }
                });
            }
            
            // Auto-resize textareas
            document.querySelectorAll('textarea').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });
            
            // Form validation styling
            document.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('border-red-300', 'focus:ring-red-500');
                        this.classList.remove('border-gray-300');
                    } else {
                        this.classList.remove('border-red-300', 'focus:ring-red-500');
                        this.classList.add('border-gray-300');
                    }
                });
            });
        });
        
        // Quick save with Ctrl+S
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                // Find active form and submit it
                const allSections = ['personal', 'contact', 'family', 'emergency', 'education', 'spiritual', 'additional'];
                allSections.forEach(section => {
                    const form = document.getElementById(section + 'Form');
                    if (form && !form.classList.contains('hidden')) {
                        const formElement = form.querySelector('form');
                        if (formElement) {
                            formElement.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                        }
                    }
                });
            }
        });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
