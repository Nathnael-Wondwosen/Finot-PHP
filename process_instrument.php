<?php
// process_instrument.php
session_start();
require_once 'config.php';

function redirectWithError($msg) {
    header('Location: instrument_registration.php?error=' . urlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithError('Invalid request.');
}

// Validate required fields
$required = ['instrument', 'full_name', 'christian_name', 'gender', 'birth_year_et', 'birth_month_et', 'birth_day_et', 'phone_number'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        redirectWithError('እባክዎ መስኮቹን በትክክል ይሙሉ።');
    }
}

$instrument = $_POST['instrument'];
$full_name = trim($_POST['full_name']);
$christian_name = trim($_POST['christian_name']);
$gender = $_POST['gender'];
$birth_year_et = $_POST['birth_year_et'];
$birth_month_et = $_POST['birth_month_et'];
$birth_day_et = $_POST['birth_day_et'];
$phone_number = trim($_POST['phone_number']);

// Process spiritual father information
$has_spiritual_father = $_POST['has_spiritual_father'] ?? null;
$spiritual_father_name = !empty($_POST['spiritual_father_name']) ? trim($_POST['spiritual_father_name']) : null;
$spiritual_father_phone = !empty($_POST['spiritual_father_phone']) ? trim($_POST['spiritual_father_phone']) : null;
$spiritual_father_church = !empty($_POST['spiritual_father_church']) ? trim($_POST['spiritual_father_church']) : null;

// Process address information
$sub_city = !empty($_POST['sub_city']) ? trim($_POST['sub_city']) : null;
$district = !empty($_POST['district']) ? trim($_POST['district']) : null;
$specific_area = !empty($_POST['specific_area']) ? trim($_POST['specific_area']) : null;
$house_number = !empty($_POST['house_number']) ? trim($_POST['house_number']) : null;

// Process emergency contact information
$emergency_name = !empty($_POST['emergency_name']) ? trim($_POST['emergency_name']) : null;
$emergency_phone = !empty($_POST['emergency_phone']) ? trim($_POST['emergency_phone']) : null;
$emergency_alt_phone = !empty($_POST['emergency_alt_phone']) ? trim($_POST['emergency_alt_phone']) : null;
$emergency_address = !empty($_POST['emergency_address']) ? trim($_POST['emergency_address']) : null;

// Enforce minimum age (>=14) using Ethiopian calendar (year-month-day)
try {
    $by = (int)$birth_year_et; $bm = (int)$birth_month_et; $bd = (int)$birth_day_et;
    $gregorianToJDN = function(int $gy, int $gm, int $gd): int {
        $a = intdiv(14 - $gm, 12);
        $y = $gy + 4800 - $a;
        $m = $gm + 12 * $a - 3;
        return $gd + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    };
    $jdnToEthiopian = function(int $jdn): array {
        $ETHIOPIAN_EPOCH = 1723856;
        $r = $jdn - $ETHIOPIAN_EPOCH;
        $ey = intdiv($r, 1461) * 4 + intdiv($r % 1461, 365) + 1;
        $rd = ($r % 1461) % 365;
        if ($r % 1461 == 1460) { $ey = intdiv($r, 1461) * 4 + 4; $em = 13; $ed = 6; }
        else { $em = intdiv($rd, 30) + 1; $ed = $rd % 30 + 1; }
        return [$ey, $em, $ed];
    };
    $today = new DateTime('now');
    $gyT = (int)$today->format('Y'); $gmT = (int)$today->format('m'); $gdT = (int)$today->format('d');
    $todayJdn = $gregorianToJDN($gyT, $gmT, $gdT);
    [$curEy, $curEm, $curEd] = $jdnToEthiopian($todayJdn);
    $ageEt = $curEy - $by; if ($curEm < $bm || ($curEm === $bm && $curEd < $bd)) { $ageEt -= 1; }
    if ($ageEt < 14) {
        redirectWithError('ለዜማ መሳሪያ ምዝገባ የተፈቀደው እድሜ 14 ዓመት እና ከዛ በላይ ነው።');
    }
} catch (Exception $e) {
    // If age validation fails due to parsing, block for safety
    redirectWithError('የትውልድ ቀን ማረጋገጫ ተሳክቷል አልተቻለም።');
}
// Try to find student by full name or phone number
$matched_student_id = null;
$student_stmt = $pdo->prepare("SELECT id FROM students WHERE full_name = ? OR phone_number = ? LIMIT 1");
$student_stmt->execute([$full_name, $phone_number]);
$student_row = $student_stmt->fetch(PDO::FETCH_ASSOC);
if ($student_row) {
    $matched_student_id = $student_row['id'];
}

// Handle person photo upload (direct or via temporary key)
$photo_path = '';
$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$tmpDir = $uploadDir . DIRECTORY_SEPARATOR . 'tmp';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

if (isset($_FILES['person_photo']) && $_FILES['person_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['person_photo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed)) {
        redirectWithError('ፎቶ: JPEG/PNG ብቻ ይቻላል።');
    }
    if ($file['size'] > 5*1024*1024) {
        redirectWithError('ፎቶ: ከ5MB በታች መሆን አለበት።');
    }
    $newname = uniqid() . '_person.' . $ext;
    $destAbs = $uploadDir . DIRECTORY_SEPARATOR . $newname;
    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        redirectWithError('ፎቶ ማስቀመጥ አልተቻለም።');
    }
    $photo_path = 'uploads/' . $newname;
} else {
    // Try temp key
    $tempKey = isset($_POST['temp_photo_key']) ? trim($_POST['temp_photo_key']) : '';
    if ($tempKey !== '') {
        $tempPath = $tmpDir . DIRECTORY_SEPARATOR . basename($tempKey);
        if (!file_exists($tempPath)) {
            redirectWithError('ጊዜያዊ ፎቶ አልተገኘም።');
        }
        // Validate size and extension by filename
        $ext = strtolower(pathinfo($tempPath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png'])) {
            redirectWithError('ፎቶ አይነት የማይፈቀድ ነው።');
        }
        if (filesize($tempPath) > 5*1024*1024) {
            redirectWithError('ፎቶ: ከ5MB በታች መሆን አለበት።');
        }
        $finalName = uniqid() . '_person.' . $ext;
        $finalAbs = $uploadDir . DIRECTORY_SEPARATOR . $finalName;
        if (!@rename($tempPath, $finalAbs)) {
            redirectWithError('ጊዜያዊ ፎቶ ማስቀመጥ አልተቻለም።');
        }
        $photo_path = 'uploads/' . $finalName;
    } else {
        // Try inline data URL
        $dataUrl = isset($_POST['temp_photo_dataurl']) ? trim($_POST['temp_photo_dataurl']) : '';
        if ($dataUrl !== '' && strpos($dataUrl, 'data:image') === 0) {
            $commaPos = strpos($dataUrl, ',');
            if ($commaPos !== false) {
                $meta = substr($dataUrl, 0, $commaPos);
                $b64 = substr($dataUrl, $commaPos + 1);
                $bin = base64_decode($b64);
                if ($bin !== false) {
                    $ext = 'jpg';
                    if (stripos($meta, 'png') !== false) { $ext = 'png'; }
                    elseif (stripos($meta, 'jpeg') !== false) { $ext = 'jpg'; }
                    $finalName = uniqid() . '_person.' . $ext;
                    $finalAbs = $uploadDir . DIRECTORY_SEPARATOR . $finalName;
                    if (file_put_contents($finalAbs, $bin) === false) {
                        redirectWithError('ፎቶ ማስቀመጥ አልተቻለም።');
                    }
                    $photo_path = 'uploads/' . $finalName;
                } else {
                    redirectWithError('የፎቶ መረጃ መጫን አልተቻለም።');
                }
            } else {
                redirectWithError('የፎቶ መረጃ ውስጥ ስህተት አለ።');
            }
        } else {
            redirectWithError('ፎቶ ያስገቡ።');
        }
    }
}

// Save to DB (create table if not exists)

$pdo->exec("CREATE TABLE IF NOT EXISTS instrument_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrument VARCHAR(32) NOT NULL,
    full_name VARCHAR(128) NOT NULL,
    christian_name VARCHAR(128) NOT NULL,
    gender VARCHAR(16) NOT NULL,
    birth_year_et VARCHAR(8) NOT NULL,
    birth_month_et VARCHAR(8) NOT NULL,
    birth_day_et VARCHAR(8) NOT NULL,
    phone_number VARCHAR(32) NOT NULL,
    person_photo_path VARCHAR(255) NOT NULL,
    student_id INT DEFAULT NULL,
    has_spiritual_father VARCHAR(10) DEFAULT NULL,
    spiritual_father_name VARCHAR(128) DEFAULT NULL,
    spiritual_father_phone VARCHAR(32) DEFAULT NULL,
    spiritual_father_church VARCHAR(128) DEFAULT NULL,
    sub_city VARCHAR(64) DEFAULT NULL,
    district VARCHAR(64) DEFAULT NULL,
    specific_area VARCHAR(128) DEFAULT NULL,
    house_number VARCHAR(32) DEFAULT NULL,
    emergency_name VARCHAR(128) DEFAULT NULL,
    emergency_phone VARCHAR(32) DEFAULT NULL,
    emergency_alt_phone VARCHAR(32) DEFAULT NULL,
    emergency_address TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_instrument (instrument),
    INDEX idx_created_at (created_at)
)");

// ...existing code...

// Prevent duplicate registration - check full name + same instrument type (different instruments allowed)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM instrument_registrations WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?)) AND instrument = ?");
$stmt->execute([$full_name, $instrument]);
if ($stmt->fetchColumn() > 0) {
    redirectWithError('ይህ ተማሪ በዚህ የዜማ መሳሪያ አስቀድሞ ተመዝግቧል።');
}

// Insert with better error handling
try {
    $stmt = $pdo->prepare("INSERT INTO instrument_registrations (instrument, full_name, christian_name, gender, birth_year_et, birth_month_et, birth_day_et, phone_number, person_photo_path, student_id, has_spiritual_father, spiritual_father_name, spiritual_father_phone, spiritual_father_church, sub_city, district, specific_area, house_number, emergency_name, emergency_phone, emergency_alt_phone, emergency_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ok = $stmt->execute([$instrument, $full_name, $christian_name, $gender, $birth_year_et, $birth_month_et, $birth_day_et, $phone_number, $photo_path, $matched_student_id, $has_spiritual_father, $spiritual_father_name, $spiritual_father_phone, $spiritual_father_church, $sub_city, $district, $specific_area, $house_number, $emergency_name, $emergency_phone, $emergency_alt_phone, $emergency_address]);
    
    if ($ok) {
        // Set a session flag to show success only once
        $_SESSION['instrument_success'] = $matched_student_id ? 'matched' : 'new';
        header('Location: success.php?type=instrument');
        exit;
    } else {
        redirectWithError('ምዝገባ አልተሳካም። እባክዎ ያተሞላ መረጃ አለመኖሩን ያረጋግጡ።');
    }
} catch (Exception $e) {
    // Show the actual error for debugging
    die('<pre style="color:red; background:#fff; padding:1em;">Instrument registration error: ' . htmlspecialchars($e->getMessage()) . '</pre>');
}
