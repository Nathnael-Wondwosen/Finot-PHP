<?php
// Add debugging to see what's being received
error_log("=== process_youth.php STARTED === " . date('Y-m-d H:i:s'));
error_log("Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
error_log("Is AJAX request: " . ($isAjax ? "YES" : "NO"));

// Production settings - errors logged, not displayed
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
session_start();

// Add debugging for session and login
error_log("Session ID: " . session_id());
error_log("Admin logged in: " . (isset($_SESSION['admin_id']) ? 'YES' : 'NO'));

require 'config.php';

// Add debugging after login check
error_log("Admin login check passed");

// Check if form was submitted
if (!isset($_POST['form_submitted']) && empty($_POST)) {
    error_log("Form was not submitted properly");
    throw new Exception('Form not submitted properly');
}
error_log("Form submission detected");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
        throw new Exception('Invalid request');
    }

    error_log("Processing POST request");
    
    // Log all POST data
    foreach ($_POST as $key => $value) {
        error_log("POST[$key]: " . (is_string($value) ? $value : print_r($value, true)));
    }
    
    $full_name = trim($_POST['full_name'] ?? '');
    $christian_name = trim($_POST['christian_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $by = (int)($_POST['birth_year_et'] ?? 0);
    $bm = (int)($_POST['birth_month_et'] ?? 0);
    $bd = (int)($_POST['birth_day_et'] ?? 0);
    
    error_log("Name: $full_name, Christian name: $christian_name, Gender: $gender, DOB: $by-$bm-$bd");
    
    if ($full_name === '' || $christian_name === '' || !$gender || !$by || !$bm || !$bd) {
        error_log("Missing required fields");
        throw new Exception('Please fill required fields');
    }

    // phone validations (basic ET mobile)
    $phone = trim($_POST['phone_number'] ?? '');
    error_log("Phone: $phone");
    if ($phone === '' || !preg_match('/^(\\+?251|0)9\\d{8}$/', $phone)) {
        error_log("Invalid phone format");
        throw new Exception('Invalid phone');
    }
    
    $em_phone = trim($_POST['emergency_phone'] ?? '');
    $em_alt = trim($_POST['emergency_alt_phone'] ?? '');
    error_log("Emergency phone: $em_phone, Alt phone: $em_alt");
    if (!preg_match('/^(\\+?251|0)9\\d{8}$/', $em_phone) || !preg_match('/^(\\+?251|0)9\\d{8}$/', $em_alt)) {
        error_log("Invalid emergency phone format");
        throw new Exception('Invalid emergency phone');
    }

    // spiritual father required option
    $has_sf = $_POST['has_spiritual_father'] ?? '';
    error_log("Has spiritual father: $has_sf");
    if ($has_sf !== 'own' && $has_sf !== 'family' && $has_sf !== 'none') {
        error_log("Invalid spiritual father option");
        throw new Exception('Choose spiritual father option');
    }

    // emergency required
    $emergency_name = trim($_POST['emergency_name'] ?? '');
    $emergency_address = trim($_POST['emergency_address'] ?? '');
    error_log("Emergency name: $emergency_name, Address: $emergency_address");
    if ($emergency_name === '' || $emergency_address === '') {
        error_log("Missing emergency contact info");
        throw new Exception('Fill emergency contact');
    }

    // Additional information fields are optional; log values if provided
    foreach (['special_interests','physical_disability','transferred_from_other_school','came_from_other_religion'] as $fld) {
        $value = trim($_POST[$fld] ?? '');
        error_log("$fld: $value");
    }

    // Handle photo upload or temp photo key
    error_log("Checking photo upload or temp key");
    $photo_path = null;
    $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png'];
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    $tmpDir = $uploadDir . DIRECTORY_SEPARATOR . 'tmp';
    
    if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['student_photo'];
        error_log("Photo details - name: " . $f['name'] . ", type: " . $f['type'] . ", size: " . $f['size'] . ", tmp_name: " . $f['tmp_name']);
        if (!isset($allowed[$f['type']]) || $f['size'] > 5*1024*1024) {
            error_log("Invalid photo type or size");
            throw new Exception('Invalid photo');
        }
        // Ensure uploads directory exists
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
        $basename = uniqid() . ($allowed[$f['type']] ?? '.jpg');
        $dest = $uploadDir . DIRECTORY_SEPARATOR . $basename;
        error_log("Destination file: $dest");
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            error_log("Failed to move uploaded file");
            error_log("Tmp file exists: " . (file_exists($f['tmp_name']) ? 'YES' : 'NO'));
            error_log("Tmp file readable: " . (is_readable($f['tmp_name']) ? 'YES' : 'NO'));
            throw new Exception('Failed saving photo');
        }
        $photo_path = 'uploads/' . $basename;
        error_log("Photo saved successfully: $photo_path");
    } else {
        $tempKey = trim($_POST['temp_photo_key'] ?? '');
        error_log("No direct file uploaded. temp_photo_key: " . ($tempKey !== '' ? $tempKey : 'NONE'));
        if ($tempKey !== '') {
            // Expect tempKey to be filename like ytmp_xxx.ext in uploads/tmp
            $tempPath = $tmpDir . DIRECTORY_SEPARATOR . basename($tempKey);
            if (!file_exists($tempPath)) {
                error_log("Temp file not found: $tempPath");
                throw new Exception('Temporary photo not found');
            }
            // Determine extension by filename
            $ext = strtolower(pathinfo($tempPath, PATHINFO_EXTENSION));
            if (!in_array('.' . $ext, $allowed)) {
                error_log("Temp photo invalid extension: .$ext");
                throw new Exception('Invalid temp photo');
            }
            // Size check (best-effort)
            if (filesize($tempPath) > 5*1024*1024) {
                error_log("Temp photo too large");
                throw new Exception('Photo too large');
            }
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            $finalName = uniqid() . '.' . $ext;
            $finalPath = $uploadDir . DIRECTORY_SEPARATOR . $finalName;
            if (!rename($tempPath, $finalPath)) {
                error_log("Failed to move temp photo to final");
                throw new Exception('Failed saving temp photo');
            }
            $photo_path = 'uploads/' . $finalName;
            error_log("Temp photo promoted to final: $photo_path");
        } else {
            error_log("Neither uploaded file nor temp key provided");
            throw new Exception('Photo upload required');
        }
    }

    $birth_date_et = sprintf('%04d-%02d-%02d', $by, $bm, $bd);
    error_log("Birth date ET: $birth_date_et");

    // Ethiopian/Gregorian conversion helpers (JDN-based)
    $gregorianToJDN = function(int $gy, int $gm, int $gd): int {
        $a = intdiv(14 - $gm, 12);
        $y = $gy + 4800 - $a;
        $m = $gm + 12 * $a - 3;
        return $gd + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    };
    $ethiopianToJDN = function(int $ey, int $em, int $ed): int {
        // Ethiopian epoch JDN (Julian Day Number for 1-1-1 E.C.)
        $ETHIOPIAN_EPOCH = 1723856;
        return $ETHIOPIAN_EPOCH + 365 * ($ey - 1) + intdiv($ey, 4) + 30 * ($em - 1) + ($ed - 1);
    };
    $jdnToEthiopian = function(int $jdn): array {
        $ETHIOPIAN_EPOCH = 1723856;
        $r = $jdn - $ETHIOPIAN_EPOCH;
        $ey = intdiv($r, 1461) * 4 + intdiv($r % 1461, 365) + 1;
        $rd = ($r % 1461) % 365;
        if ($r % 1461 == 1460) { // last day of 4-year cycle
            $ey = intdiv($r, 1461) * 4 + 4;
            $em = 13;
            $ed = 6;
        } else {
            $em = intdiv($rd, 30) + 1;
            $ed = $rd % 30 + 1;
        }
        return [$ey, $em, $ed];
    };
    $today = new DateTime('now');
    $gyT = (int)$today->format('Y');
    $gmT = (int)$today->format('m');
    $gdT = (int)$today->format('d');
    $todayJdn = $gregorianToJDN($gyT, $gmT, $gdT);
    [$curEy, $curEm, $curEd] = $jdnToEthiopian($todayJdn);

    // Compute Ethiopian age from Ethiopian birth date (year-month-day)
    $ageEt = $curEy - $by;
    if ($curEm < $bm || ($curEm === $bm && $curEd < $bd)) {
        $ageEt -= 1;
    }
    error_log("Age: $ageEt");
    
    if ($ageEt < 17) {
        error_log("Age less than 17");
        throw new Exception('ወጣት ምድብ ለመመዝገብ ከ17 ዓመት በላይ መሆን ይኖርቦታል።');
    }

    // Prevent exact duplicate by full_name (case/space-insensitive) + birth_date
    error_log("Checking for duplicates");
    $dupStmt = $pdo->prepare("SELECT id FROM students WHERE birth_date = :bd AND LOWER(TRIM(full_name)) = LOWER(TRIM(:fn)) LIMIT 1");
    $dupStmt->execute([':bd' => $birth_date_et, ':fn' => $full_name]);
    if ($dupStmt->fetchColumn()) {
        error_log("Duplicate found");
        throw new Exception('ይህ ተማሪ በተመሳሳይ ሙሉ ስም እስከ አያት እና ቀን ትውልድ አስቀድሞ ተመዝግቧል።');
    }

    error_log("Inserting student record");
    $sql = "INSERT INTO students (
            full_name, christian_name, gender, birth_date, phone_number,
            sub_city, district, specific_area, house_number,
            current_grade, school_year_start, regular_school_grade,
            education_level, field_of_study,
            has_spiritual_father, living_with, emergency_name, emergency_phone, emergency_alt_phone, emergency_address,
            special_interests, physical_disability, transferred_from_other_school, came_from_other_religion
        ) VALUES (
            :full_name, :christian_name, :gender, :birth_date, :phone_number,
            :sub_city, :district, :specific_area, :house_number,
            :current_grade, :school_year_start, :regular_school_grade,
            :education_level, :field_of_study,
            :has_spiritual_father, :living_with, :emergency_name, :emergency_phone, :emergency_alt_phone, :emergency_address,
            :special_interests, :physical_disability, :transferred_from_other_school, :came_from_other_religion
        )";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':full_name' => $full_name,
        ':christian_name' => $christian_name,
        ':gender' => $gender,
        ':birth_date' => $birth_date_et,
        ':phone_number' => $phone,
        ':sub_city' => $_POST['sub_city'] ?? null,
        ':district' => $_POST['district'] ?? null,
        ':specific_area' => $_POST['specific_area'] ?? null,
        ':house_number' => $_POST['house_number'] ?? null,
        ':current_grade' => $_POST['current_grade'] ?? 'new',
        ':school_year_start' => isset($_POST['school_year_start']) ? (int)$_POST['school_year_start'] : null,
        ':regular_school_grade' => null,
        ':education_level' => trim($_POST['education_level'] ?? ''),
        ':field_of_study' => trim($_POST['field_of_study'] ?? ''),
        ':has_spiritual_father' => ($has_sf ?? 'none'),
        ':living_with' => 'both_parents',
        ':emergency_name' => $emergency_name,
        ':emergency_phone' => $em_phone,
        ':emergency_alt_phone' => $em_alt,
        ':emergency_address' => $emergency_address,
        ':special_interests' => $_POST['special_interests'] ?? null,
        ':physical_disability' => $_POST['physical_disability'] ?? null,
        ':transferred_from_other_school' => $_POST['transferred_from_other_school'] ?? null,
        ':came_from_other_religion' => $_POST['came_from_other_religion'] ?? null
    ]);
    
    error_log("Insert result: " . ($result ? "SUCCESS" : "FAILED"));
    
    if (!$result) {
        error_log("PDO Error: " . print_r($stmt->errorInfo(), true));
        throw new Exception('Failed to insert student record');
    }

    // Also update photo path after insert
    $studentId = $pdo->lastInsertId();
    error_log("Student ID: $studentId");
    
    $updateStmt = $pdo->prepare("UPDATE students SET photo_path=?, spiritual_father_name=?, spiritual_father_phone=?, spiritual_father_church=? WHERE id=?");
    $updateResult = $updateStmt->execute([
        $photo_path,
        trim($_POST['spiritual_father_name'] ?? ''),
        trim($_POST['spiritual_father_phone'] ?? ''),
        trim($_POST['spiritual_father_church'] ?? ''),
        $studentId
    ]);
    
    error_log("Update result: " . ($updateResult ? "SUCCESS" : "FAILED"));
    
    if (!$updateResult) {
        error_log("PDO Error: " . print_r($updateStmt->errorInfo(), true));
        throw new Exception('Failed to update student photo');
    }

    error_log("Registration successful, redirecting to success page");
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'id' => $studentId]);
    } else {
        header('Location: success.php?type=youth&id=' . urlencode((string)$studentId));
    }
} catch (Throwable $e) {
    error_log("Exception caught: " . $e->getMessage());
    error_log("Exception trace: " . $e->getTraceAsString());
    
    // Log error to file for debugging
    $logMsg = date('Y-m-d H:i:s') . " | " . $e->getMessage() . " | " . $_SERVER['REMOTE_ADDR'] . "\n";
    file_put_contents(__DIR__ . '/youth_registration_error.log', $logMsg, FILE_APPEND);
    
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        $msg = urlencode($e->getMessage());
        header('Location: youth_registration.php?error=' . $msg);
    }
}
?>