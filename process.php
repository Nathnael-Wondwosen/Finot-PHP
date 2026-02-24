<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: log the entire $_POST array for troubleshooting
    file_put_contents(__DIR__ . '/parent_info_debug.log', "==== NEW SUBMISSION ====\n" . date('Y-m-d H:i:s') . "\n" . print_r($_POST, true) . "\n", FILE_APPEND);
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Handle file upload
        $photoPath = '';
        if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $photoName = uniqid() . '_' . basename($_FILES['student_photo']['name']);
            $photoPath = $uploadDir . $photoName;
            move_uploaded_file($_FILES['student_photo']['tmp_name'], $photoPath);
        } else {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $tempKey = isset($_POST['temp_photo_key']) ? trim($_POST['temp_photo_key']) : '';
            $tempDataUrl = isset($_POST['temp_photo_dataurl']) ? trim($_POST['temp_photo_dataurl']) : '';
            if ($tempKey !== '') {
                $tmpSrc = __DIR__ . '/uploads/tmp/' . basename($tempKey);
                if (is_file($tmpSrc)) {
                    $ext = pathinfo($tmpSrc, PATHINFO_EXTENSION);
                    if ($ext === '') { $ext = 'jpg'; }
                    $photoName = uniqid() . '.' . $ext;
                    $dest = __DIR__ . '/uploads/' . $photoName;
                    if (@rename($tmpSrc, $dest) || @copy($tmpSrc, $dest)) {
                        $photoPath = 'uploads/' . $photoName;
                        @unlink($tmpSrc);
                    }
                }
            } elseif ($tempDataUrl !== '' && strpos($tempDataUrl, 'data:image') === 0) {
                $commaPos = strpos($tempDataUrl, ',');
                if ($commaPos !== false) {
                    $meta = substr($tempDataUrl, 0, $commaPos);
                    $b64 = substr($tempDataUrl, $commaPos + 1);
                    $bin = base64_decode($b64);
                    if ($bin !== false) {
                        $ext = 'jpg';
                        if (stripos($meta, 'png') !== false) { $ext = 'png'; }
                        elseif (stripos($meta, 'jpeg') !== false) { $ext = 'jpg'; }
                        elseif (stripos($meta, 'webp') !== false) { $ext = 'webp'; }
                        $photoName = uniqid() . '.' . $ext;
                        $dest = __DIR__ . '/uploads/' . $photoName;
                        if (file_put_contents($dest, $bin) !== false) {
                            $photoPath = 'uploads/' . $photoName;
                        }
                    }
                }
            }
        }

        // Insert student basic info
        // Prepare Ethiopian date fields if table supports them; fallback to existing birth_date
        $hasEtColumns = false;
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'birth_year_et'");
            $hasEtColumns = (bool)$colStmt->fetch();
        } catch (Exception $ignore) {}

        if ($hasEtColumns) {
            // Duplicate guard: exact normalized full_name only (phone duplicates allowed)
            $dup = $pdo->prepare("SELECT id FROM students WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(:fn)) LIMIT 1");
            $dup->execute([':fn' => trim($_POST['full_name'] ?? '')]);
            if ($dup->fetchColumn()) {
                throw new Exception('ይህ ተማሪ በተመሳሳይ ሙሉ ስም እስከ አያት አስቀድሞ ተመዝግቧል።');
            }
            $stmt = $pdo->prepare("
                INSERT INTO students (
                    photo_path, full_name, christian_name, gender,
                    birth_year_et, birth_month_et, birth_day_et,
                    current_grade, school_year_start, regular_school_name, regular_school_grade, phone_number,
                    has_spiritual_father, spiritual_father_name, spiritual_father_phone, spiritual_father_church,
                    sub_city, district, specific_area, house_number, living_with,
                    special_interests, siblings_in_school, physical_disability, weak_side,
                    transferred_from_other_school, came_from_other_religion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $photoPath,
                $_POST['full_name'],
                $_POST['christian_name'],
                $_POST['gender'],
                $_POST['birth_year_et'] ?? null,
                $_POST['birth_month_et'] ?? null,
                $_POST['birth_day_et'] ?? null,
                $_POST['current_grade'],
                $_POST['school_year_start'],
                $_POST['regular_school_name'],
                null,
                $_POST['student_phone'],
                $_POST['has_spiritual_father'],
                $_POST['spiritual_father_name'] ?? null,
                $_POST['spiritual_father_phone'] ?? null,
                $_POST['spiritual_father_church'] ?? null,
                $_POST['sub_city'],
                $_POST['district'],
                $_POST['specific_area'],
                $_POST['house_number'],
                $_POST['living_with'],
                $_POST['special_interests'],
                $_POST['siblings_in_school'],
                $_POST['physical_disability'],
                $_POST['weak_side'],
                $_POST['transferred_from_other_school'],
                $_POST['came_from_other_religion']
            ]);
        } else {
            // Fallback: store Ethiopian Y/M/D as YYYY-MM-DD string into legacy birth_date column
            $ey = isset($_POST['birth_year_et']) ? (int)$_POST['birth_year_et'] : null;
            $em = isset($_POST['birth_month_et']) ? (int)$_POST['birth_month_et'] : null;
            $ed = isset($_POST['birth_day_et']) ? (int)$_POST['birth_day_et'] : null;
            $birthGregorian = null;
            if ($ey && $em && $ed) {
                // Store as Ethiopian YYYY-MM-DD (MySQL DATE accepts this format)
                $birthGregorian = sprintf('%04d-%02d-%02d', $ey, $em, $ed);
            } else {
                // As ultimate fallback, use posted birth_date if provided
                $birthGregorian = $_POST['birth_date'] ?? null;
            }

            // Duplicate guard: exact normalized full_name only (phone duplicates allowed)
            $dup = $pdo->prepare("SELECT id FROM students WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(:fn)) LIMIT 1");
            $dup->execute([':fn' => trim($_POST['full_name'] ?? '')]);
            if ($dup->fetchColumn()) {
                throw new Exception('ይህ ተማሪ በተመሳሳይ ሙሉ ስም እስከ አያት አስቀድሞ ተመዝግቧል።');
            }
            $stmt = $pdo->prepare("
                INSERT INTO students (
                    photo_path, full_name, christian_name, gender, birth_date, current_grade,
                    school_year_start, regular_school_name, regular_school_grade, phone_number,
                    has_spiritual_father, spiritual_father_name, spiritual_father_phone, spiritual_father_church,
                    sub_city, district, specific_area, house_number, living_with,
                    special_interests, siblings_in_school, physical_disability, weak_side,
                    transferred_from_other_school, came_from_other_religion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $photoPath,
                $_POST['full_name'],
                $_POST['christian_name'],
                $_POST['gender'],
                $birthGregorian,
                $_POST['current_grade'],
                $_POST['school_year_start'],
                $_POST['regular_school_name'],
                null, // regular_school_grade ignored
                $_POST['student_phone'],
                $_POST['has_spiritual_father'],
                $_POST['spiritual_father_name'] ?? null,
                $_POST['spiritual_father_phone'] ?? null,
                $_POST['spiritual_father_church'] ?? null,
                $_POST['sub_city'],
                $_POST['district'],
                $_POST['specific_area'],
                $_POST['house_number'],
                $_POST['living_with'],
                $_POST['special_interests'],
                $_POST['siblings_in_school'],
                $_POST['physical_disability'],
                $_POST['weak_side'],
                $_POST['transferred_from_other_school'],
                $_POST['came_from_other_religion']
            ]);
        }
        
        $studentId = $pdo->lastInsertId();
        
        // Handle parent/guardian information based on living_with, with validation and error logging
        $parentLogFile = __DIR__ . '/parent_info_debug.log';
        $logParentError = function($msg) use ($parentLogFile) {
            file_put_contents($parentLogFile, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
        };
        // Helper: return first non-empty posted value from a list of keys
        $getVal = function($keys) {
            foreach ((array)$keys as $key) {
                if (isset($_POST[$key]) && $_POST[$key] !== '') {
                    return $_POST[$key];
                }
            }
            return null;
        };

        switch ($_POST['living_with']) {
            case 'both_parents':
                // Map father fields (support legacy names)
                $father_full_name = $getVal(['father_full_name_both', 'father_full_name']);
                $father_christian_name = $getVal(['father_christian_name_both', 'father_christian_name']);
                $father_occupation = $getVal(['father_occupation_both', 'father_occupation']);
                $father_phone = $getVal(['father_phone_both', 'father_phone']);
                if (empty($father_full_name) || empty($father_christian_name) || empty($father_occupation) || empty($father_phone)) {
                    $logParentError('Missing father info for both_parents: ' . json_encode($_POST));
                    throw new Exception('Father information is required.');
                }
                // Map mother fields (support legacy names)
                $mother_full_name = $getVal(['mother_full_name_both', 'mother_full_name']);
                $mother_christian_name = $getVal(['mother_christian_name_both', 'mother_christian_name']);
                $mother_occupation = $getVal(['mother_occupation_both', 'mother_occupation']);
                $mother_phone = $getVal(['mother_phone_both', 'mother_phone']);
                if (empty($mother_full_name) || empty($mother_christian_name) || empty($mother_occupation) || empty($mother_phone)) {
                    $logParentError('Missing mother info for both_parents: ' . json_encode($_POST));
                    throw new Exception('Mother information is required.');
                }
                // Insert father
                $stmt = $pdo->prepare("
                    INSERT INTO parents (student_id, parent_type, full_name, christian_name, occupation, phone_number)
                    VALUES (?, 'father', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentId,
                    $father_full_name,
                    $father_christian_name,
                    $father_occupation,
                    $father_phone
                ]);
                $logParentError('Inserted father for both_parents: ' . $father_full_name);
                // Insert mother
                $stmt = $pdo->prepare("
                    INSERT INTO parents (student_id, parent_type, full_name, christian_name, occupation, phone_number)
                    VALUES (?, 'mother', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentId,
                    $mother_full_name,
                    $mother_christian_name,
                    $mother_occupation,
                    $mother_phone
                ]);
                $logParentError('Inserted mother for both_parents: ' . $mother_full_name);
                break;
            case 'father_only':
                $father_full_name = $getVal(['father_full_name_only', 'father_full_name']);
                $father_christian_name = $getVal(['father_christian_name_only', 'father_christian_name']);
                $father_occupation = $getVal(['father_occupation_only', 'father_occupation']);
                $father_phone = $getVal(['father_phone_only', 'father_phone']);
                if (empty($father_full_name) || empty($father_christian_name) || empty($father_occupation) || empty($father_phone)) {
                    $logParentError('Missing father info for father_only: ' . json_encode($_POST));
                    throw new Exception('Father information is required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO parents (student_id, parent_type, full_name, christian_name, occupation, phone_number)
                    VALUES (?, 'father', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentId,
                    $father_full_name,
                    $father_christian_name,
                    $father_occupation,
                    $father_phone
                ]);
                $logParentError('Inserted father for father_only: ' . $father_full_name);
                break;
            case 'mother_only':
                $mother_full_name = $getVal(['mother_full_name_only', 'mother_full_name']);
                $mother_christian_name = $getVal(['mother_christian_name_only', 'mother_christian_name']);
                $mother_occupation = $getVal(['mother_occupation_only', 'mother_occupation']);
                $mother_phone = $getVal(['mother_phone_only', 'mother_phone']);
                if (empty($mother_full_name) || empty($mother_christian_name) || empty($mother_occupation) || empty($mother_phone)) {
                    $logParentError('Missing mother info for mother_only: ' . json_encode($_POST));
                    throw new Exception('Mother information is required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO parents (student_id, parent_type, full_name, christian_name, occupation, phone_number)
                    VALUES (?, 'mother', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentId,
                    $mother_full_name,
                    $mother_christian_name,
                    $mother_occupation,
                    $mother_phone
                ]);
                $logParentError('Inserted mother for mother_only: ' . $mother_full_name);
                break;
            case 'relative_or_guardian':
                // Guardian father -> save as parent_type 'father'
                if (empty($_POST['guardian_father_full_name']) || empty($_POST['guardian_father_christian_name']) || empty($_POST['guardian_father_occupation']) || empty($_POST['guardian_father_phone'])) {
                    $logParentError('Missing guardian father info: ' . json_encode($_POST));
                    throw new Exception('Guardian father information is required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO parents (student_id, parent_type, full_name, christian_name, occupation, phone_number)
                    VALUES (?, 'father', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentId,
                    $_POST['guardian_father_full_name'],
                    $_POST['guardian_father_christian_name'],
                    $_POST['guardian_father_occupation'],
                    $_POST['guardian_father_phone']
                ]);
                $logParentError('Inserted guardian father as father: ' . $_POST['guardian_father_full_name']);
                // Guardian mother (optional) -> save as parent_type 'mother'
                if (!empty($_POST['guardian_mother_full_name'])) {
                    if (empty($_POST['guardian_mother_christian_name']) || empty($_POST['guardian_mother_occupation']) || empty($_POST['guardian_mother_phone'])) {
                        $logParentError('Missing guardian mother info: ' . json_encode($_POST));
                        throw new Exception('Guardian mother information is incomplete.');
                    }
                    $stmt = $pdo->prepare("
                        INSERT INTO parents (student_id, parent_type, full_name, christian_name, occupation, phone_number)
                        VALUES (?, 'mother', ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $studentId,
                        $_POST['guardian_mother_full_name'],
                        $_POST['guardian_mother_christian_name'],
                        $_POST['guardian_mother_occupation'],
                        $_POST['guardian_mother_phone']
                    ]);
                    $logParentError('Inserted guardian mother as mother: ' . $_POST['guardian_mother_full_name']);
                }
                break;
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect to success page
        header('Location: success.php');
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
?>