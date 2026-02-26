<?php
session_start();
require_once '../config.php';
require_once '../includes/security_helpers.php';
require_once '../ethiopian_age.php';

requireAdminLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$table = isset($_GET['table']) ? $_GET['table'] : 'students';
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$allowedTables = ['students', 'instruments'];
if (!in_array($table, $allowedTables, true)) {
    http_response_code(400);
    echo 'Invalid table';
    exit;
}

// Fetch record (reuse logic similar to student_details_view)
try {
    if ($table === 'instruments') {
        $stmt = $pdo->prepare('
            SELECT ir.*, 
                   s.id AS student_id, s.full_name AS s_full_name, s.christian_name AS s_christian_name,
                   s.gender AS s_gender, s.current_grade AS s_current_grade, s.photo_path AS s_photo_path,
                   s.phone_number AS s_phone_number, s.created_at AS s_created_at,
                   s.birth_date AS s_birth_date
            FROM instrument_registrations ir
            LEFT JOIN students s ON LOWER(TRIM(ir.full_name)) = LOWER(TRIM(s.full_name))
            WHERE ir.id = ?
        ');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) throw new Exception('Not found');
        // Build display
        $display = [
            'full_name' => $record['full_name'] ?? ($record['s_full_name'] ?? 'N/A'),
            'christian_name' => $record['christian_name'] ?? ($record['s_christian_name'] ?? null),
            'gender' => $record['gender'] ?? ($record['s_gender'] ?? null),
            'current_grade' => $record['s_current_grade'] ?? null,
            'phone_number' => $record['phone_number'] ?? ($record['s_phone_number'] ?? null),
            'photo_path' => $record['person_photo_path'] ?? ($record['s_photo_path'] ?? ''),
            'birth_date' => $record['s_birth_date'] ?? null,
            'birth_year_et' => $record['birth_year_et'] ?? null,
            'birth_month_et' => $record['birth_month_et'] ?? null,
            'birth_day_et' => $record['birth_day_et'] ?? null,
            'instrument' => $record['instrument'] ?? null,
            'created_at' => $record['created_at'] ?? $record['s_created_at'] ?? null,
            'flagged' => $record['flagged'] ?? 0,
            'student_id' => $record['student_id'] ?? null,
        ];
        $instrumentRegs = [];
    } else {
        // Student with parent/guardian
        $stmt = $pdo->prepare("SELECT s.*, 
            f.full_name AS father_full_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
            m.full_name AS mother_full_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation,
            g.full_name AS guardian_full_name, g.phone_number AS guardian_phone, g.occupation AS guardian_occupation
        FROM students s
        LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
        LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
        LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
        WHERE s.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) throw new Exception('Not found');
        $display = $record;
        // Load instrument registrations list by full name
        $instrumentRegs = [];
        if (!empty($record['full_name'])) {
            $stmtIr = $pdo->prepare('SELECT instrument, created_at, flagged FROM instrument_registrations WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?)) ORDER BY created_at DESC');
            $stmtIr->execute([$record['full_name']]);
            $instrumentRegs = $stmtIr->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    // Helpers
    $e = function($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); };
    $isAbsoluteUrl = function($p) { return is_string($p) && (stripos($p, 'http://') === 0 || stripos($p, 'https://') === 0 || stripos($p, 'data:') === 0); };
    $rootDir = dirname(__DIR__); // ../fn
    $toFsPath = function($rel) use ($rootDir) {
        $rel = ltrim((string)$rel, '/\\');
        return $rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    };
    $toWebPathFromApi = function($rel) {
        $rel = ltrim((string)$rel, '/');
        $apiDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : 'api';
        $rootWeb = rtrim(dirname($apiDir), '/\\'); // go up from /fn/api -> /fn
        if ($rootWeb === '' || $rootWeb === '.') { $rootWeb = ''; }
        return ($rootWeb ? $rootWeb . '/' : '/') . $rel;
    };
    $resolveImage = function($rawPath, array $extraCandidates = []) use ($isAbsoluteUrl, $toFsPath, $toWebPathFromApi) {
        if (!$rawPath && empty($extraCandidates)) return '';
        $candidates = [];
        if ($rawPath) $candidates[] = $rawPath;
        foreach ($extraCandidates as $c) { $candidates[] = $c; }
        foreach ($candidates as $cand) {
            if (!$cand) continue;
            if ($isAbsoluteUrl($cand)) return $cand;
            $fs = $toFsPath($cand);
            if (file_exists($fs)) return $toWebPathFromApi($cand);
        }
        return '';
    };

    // Ethiopic month names
    $amharicEthiopicMonths = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ህዳር', 4 => 'ታህሳስ', 5 => 'ጥር', 6 => 'የካቲት',
        7 => 'መጋቢት', 8 => 'ሚያዝያ', 9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
    ];
    $gregorian_to_jdn = function($y, $m, $d) {
        $a = intdiv(14 - $m, 12);
        $yy = $y + 4800 - $a;
        $mm = $m + 12 * $a - 3;
        return $d + intdiv(153 * $mm + 2, 5) + 365 * $yy + intdiv($yy, 4) - intdiv($yy, 100) + intdiv($yy, 400) - 32045;
    };
    $jdn_to_ethiopian = function($jdn) {
        $r = ($jdn - 1723856) % 1461;
        if ($r < 0) $r += 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);
        $year = 4 * intdiv(($jdn - 1723856), 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $month = intdiv($n, 30) + 1;
        $day = ($n % 30) + 1;
        return [$year, $month, $day];
    };
    $formatDob = function(array $display) use ($amharicEthiopicMonths, $gregorian_to_jdn, $jdn_to_ethiopian, $e) {
        if (!empty($display['birth_year_et']) && !empty($display['birth_month_et']) && !empty($display['birth_day_et'])) {
            $m = (int)$display['birth_month_et'];
            $mn = $amharicEthiopicMonths[$m] ?? '';
            return trim($e((string)$display['birth_day_et']).' '.$mn.' '.$e((string)$display['birth_year_et']));
        }
        if (!empty($display['birth_date']) && $display['birth_date'] !== '0000-00-00') {
            [$gy,$gm,$gd] = array_map('intval', explode('-', $display['birth_date']));
            if ($gy && $gm && $gd) {
                $jdn = $gregorian_to_jdn($gy,$gm,$gd);
                [$ey,$em,$ed] = $jdn_to_ethiopian($jdn);
                $mn = $amharicEthiopicMonths[$em] ?? '';
                return trim($e((string)$ed).' '.$mn.' '.$e((string)$ey));
            }
        }
        return '';
    };

    // Age
    $age = null;
    if (!empty($display['birth_date']) && $display['birth_date'] !== '0000-00-00') {
        $age = (int)ethiopian_age($display['birth_date']);
    } elseif (!empty($display['age'])) {
        $age = (int)$display['age'];
    }

    // HTML output
    // Resolve logo and photo
    $logoOverride = isset($_GET['logo']) ? trim((string)$_GET['logo']) : '';
    if ($logoOverride !== '') {
        $logoSrc = $logoOverride;
    } else {
        $logoSrc = $resolveImage('assets/logo.png', [
            'assets/logo.svg',
            'assets/img/logo.png',
            'assets/images/logo.png',
            'images/logo.png',
            'img/logo.png',
            'public/assets/logo.png',
            'uploads/689636ec11381_finot logo.png',
            'uploads/finot-logo.png',
            'logo.png'
        ]);
    }
    $photoCandidates = [];
    if (!empty($display['photo_path'])) {
        $photoCandidates[] = $display['photo_path'];
        $photoCandidates[] = 'uploads/' . basename($display['photo_path']);
        $photoCandidates[] = 'uploads/photos/' . basename($display['photo_path']);
        $photoCandidates[] = 'photos/' . basename($display['photo_path']);
    }
    $photoSrc = $resolveImage(null, $photoCandidates);

    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Student Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; color: #111827; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.06); overflow: hidden; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; background: linear-gradient(90deg, #0ea5e9, #6366f1); color: #fff; }
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo img { width: 40px; height: 40px; object-fit: contain; border-radius: 8px; background: #fff; }
        .title { font-weight: 700; font-size: 18px; letter-spacing: .3px; }
        .subtitle { opacity: .9; font-size: 12px; }
        .content { padding: 20px 24px; }
        .section { margin-bottom: 16px; }
        .section h3 { font-size: 14px; font-weight: 700; margin: 0 0 8px 0; color: #111827; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
        .row { display: flex; gap: 6px; font-size: 12px; }
        .label { min-width: 120px; color: #6b7280; }
        .value { color: #111827; }
        .chip { display: inline-flex; align-items: center; gap: 6px; background: #f3f4f6; color: #111827; border-radius: 999px; padding: 4px 10px; font-size: 12px; margin-right: 6px; }
        .avatar { width: 72px; height: 72px; border-radius: 999px; object-fit: cover; border: 3px solid #fff; box-shadow: 0 6px 12px rgba(0,0,0,.15); }
        .header-right { text-align: right; }
        @media print {
            body { background: #fff; }
            .container { box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
            .card { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="logo">
                    <?php if ($logoSrc): ?>
                        <img src="<?= $e($logoSrc) ?>" alt="Logo" />
                    <?php endif; ?>
                    <div>
                        <div class="title">የተማሪ መግለጫ ሪፖርት</div>
                        <div class="subtitle">ተፈጥሯል በ <?= $e(date('M j, Y')) ?></div>
                    </div>
                </div>
                <div class="header-right">
                    <?php if ($photoSrc): ?>
                        <img class="avatar" src="<?= $e($photoSrc) ?>" alt="Photo" />
                    <?php endif; ?>
                    <div style="margin-top:6px;">
                        <?php if (!empty($display['instrument'])): ?><span class="chip"><i class="fa fa-music"></i><?= $e($display['instrument']) ?></span><?php endif; ?>
                        <?php if (!empty($record['flagged'])): ?><span class="chip" style="background:#fee2e2;color:#991b1b"><i class="fa fa-flag"></i>Flagged</span><?php endif; ?>
                        <?php if ($age !== null): ?><span class="chip"><i class="fa fa-user"></i>Age: <?= $e($age) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="content">
                <div class="section">
                    <h3>መሠረታዊ መረጃ</h3>
                    <div class="grid">
                        <div class="row"><div class="label">ሙሉ ስም</div><div class="value"><?= $e($display['full_name'] ?? 'N/A') ?></div></div>
                        <div class="row"><div class="label">የክርስትና ስም</div><div class="value"><?= $e($display['christian_name'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">ጾታ</div><div class="value"><?= $e($display['gender'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">የትውልድ ቀን (ዓ.ም)</div><div class="value"><?= $formatDob($display) ?: '-' ?></div></div>
                        <div class="row"><div class="label">ክፍል</div><div class="value"><?= $e($display['current_grade'] ?? '-') ?></div></div>
                    </div>
                </div>

                <div class="section">
                    <h3>የእውቂያ መረጃ</h3>
                    <div class="grid">
                        <div class="row"><div class="label">ስልክ</div><div class="value"><?= $e($display['phone_number'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">አድራሻ</div><div class="value"><?= $e(($display['sub_city'] ?? '').' '.($display['district'] ?? '').' '.($display['kebele'] ?? '').' '.($display['specific_area'] ?? '').' '.($display['house_number'] ?? '')) ?></div></div>
                    </div>
                </div>

                <div class="section">
                    <h3>ትምህርት</h3>
                    <div class="grid">
                        <div class="row"><div class="label">የትምህርት ቤት</div><div class="value"><?= $e($display['regular_school_name'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">የትምህርት ደረጃ</div><div class="value"><?= $e($display['education_level'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">የጥናት መረጃ</div><div class="value"><?= $e($display['field_of_study'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">የት/ቤት አጀና</div><div class="value"><?= $e($display['school_year_start'] ?? '-') ?></div></div>
                    </div>
                </div>

                <?php if ($table === 'students'): ?>
                <div class="section">
                    <h3>ቤተሰብ</h3>
                    <div class="grid">
                        <div class="row"><div class="label">አባት</div><div class="value"><?= $e($display['father_full_name'] ?? '-') ?> <?= $e($display['father_phone'] ?? '') ?> <?= $e($display['father_occupation'] ?? '') ?></div></div>
                        <div class="row"><div class="label">እናት</div><div class="value"><?= $e($display['mother_full_name'] ?? '-') ?> <?= $e($display['mother_phone'] ?? '') ?> <?= $e($display['mother_occupation'] ?? '') ?></div></div>
                        <div class="row"><div class="label">እገዛ አባል</div><div class="value"><?= $e($display['guardian_full_name'] ?? '-') ?> <?= $e($display['guardian_phone'] ?? '') ?> <?= $e($display['guardian_occupation'] ?? '') ?></div></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                    $isAdult = false;
                    if (!empty($display['birth_date']) && $display['birth_date'] !== '0000-00-00') $isAdult = ethiopian_age($display['birth_date']) >= 18;
                    elseif (!empty($display['age'])) $isAdult = ((int)$display['age']) >= 18;
                ?>
                <?php if ($table === 'students' && $isAdult): ?>
                <div class="section">
                    <h3>የአደጋ ጊዜ እውቂያ</h3>
                    <div class="grid">
                        <div class="row"><div class="label">ስም</div><div class="value"><?= $e($display['emergency_name'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">ስልክ</div><div class="value"><?= $e($display['emergency_phone'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">ተጨማሪ ስልክ</div><div class="value"><?= $e($display['emergency_alt_phone'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">አድራሻ</div><div class="value"><?= $e($display['emergency_address'] ?? '-') ?></div></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($table === 'students'): ?>
                <?php
                    $spiritualPresent = !empty($display['has_spiritual_father']) || !empty($display['spiritual_father_name']) || !empty($display['spiritual_father_phone']) || !empty($display['spiritual_father_church']);
                    $additionalPresent = !empty($display['special_interests']) || !empty($display['siblings_in_school']) || !empty($display['physical_disability']) || !empty($display['weak_side']) || !empty($display['transferred_from_other_school']) || !empty($display['came_from_other_religion']) || !empty($display['medical_notes']) || !empty($display['internal_notes']) || !empty($display['talent']) || !empty($display['talents']) || !empty($display['hobby']) || !empty($display['hobbies']);
                ?>
                <?php if ($spiritualPresent): ?>
                <div class="section">
                    <h3>መንፈሳዊ መረጃ</h3>
                    <div class="grid">
                        <div class="row"><div class="label">መንፈሳዊ አባት አለው?</div><div class="value"><?= $e($display['has_spiritual_father'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">ስም</div><div class="value"><?= $e($display['spiritual_father_name'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">ስልክ</div><div class="value"><?= $e($display['spiritual_father_phone'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">ቤተ ክርስቲያን</div><div class="value"><?= $e($display['spiritual_father_church'] ?? '-') ?></div></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($additionalPresent): ?>
                <div class="section">
                    <h3>ተጨማሪ መረጃ</h3>
                    <div class="grid">
                        <div class="row"><div class="label">ልዩ ችሎታ(ዎች)</div><div class="value"><?= $e($display['talents'] ?? ($display['talent'] ?? '-')) ?></div></div>
                        <div class="row"><div class="label">መዝናኛ(ዎች)</div><div class="value"><?= $e($display['hobbies'] ?? ($display['hobby'] ?? '-')) ?></div></div>
                        <div class="row"><div class="label">በተለይ የሚፈልጉ ነገሮች</div><div class="value"><?= $e($display['special_interests'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">በት/ቤት የሚገኙ ወንድሞች/እህቶች</div><div class="value"><?= $e($display['siblings_in_school'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">አካል ጉዳት</div><div class="value"><?= $e($display['physical_disability'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">ድክመት ወገን</div><div class="value"><?= $e($display['weak_side'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">ከሌላ ት/ቤት መተላለፍ</div><div class="value"><?= $e($display['transferred_from_other_school'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">ከሌላ ሃይማኖት መጣ</div><div class="value"><?= $e($display['came_from_other_religion'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">የጤና ማስታወሻ</div><div class="value"><?= $e($display['medical_notes'] ?? '-') ?></div></div>
                        <div class="row"><div class="label">የውስጥ ማስታወሻ</div><div class="value"><?= $e($display['internal_notes'] ?? '-') ?></div></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($table === 'students' && !empty($instrumentRegs)): ?>
                <div class="section">
                    <h3>የመሳሪያ ምዝገባዎች</h3>
                    <?php foreach ($instrumentRegs as $ir): ?>
                        <div class="row"><div class="label">መሳሪያ</div><div class="value"><?= $e($ir['instrument'] ?? '-') ?> (<?= !empty($ir['created_at']) ? $e(date('M j, Y', strtotime($ir['created_at']))) : '-' ?>)</div></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="section no-print" style="text-align:right; margin-top:20px;">
                    <button onclick="window.print()" style="background:#111827;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:12px;cursor:pointer;">
                        <i class="fa fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
} catch (Exception $e) {
    http_response_code(500);
    echo 'Server error';
}
?>


