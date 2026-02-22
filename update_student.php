<?php
session_start();
require 'config.php';
requireAdminLogin();
header('Content-Type: application/json');

function json_fail($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$student_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($student_id <= 0) json_fail('Invalid student ID');

try {
    $pdo->beginTransaction();

    // Get current photo_path for potential delete/replace
    $stmt = $pdo->prepare('SELECT photo_path FROM students WHERE id = ? LIMIT 1');
    $stmt->execute([$student_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $currentPhoto = $current['photo_path'] ?? '';

    // Update students table
    $fields = [
        'full_name','christian_name','gender','birth_date','current_grade','phone_number',
        'sub_city','district','specific_area','house_number',
        'spiritual_father_name','spiritual_father_phone','spiritual_father_church','has_spiritual_father','living_with',
        'regular_school_name','regular_school_grade','school_year_start',
        'education_level','field_of_study',
        'emergency_name','emergency_phone','emergency_alt_phone','emergency_address',
        'special_interests','siblings_in_school','physical_disability','weak_side',
        'transferred_from_other_school','came_from_other_religion'
    ];
    $sets = [];
    $params = [];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $sets[] = "$f = ?";
            $params[] = $_POST[$f];
        }
    }
    // Handle photo update/remove
    $newPhotoPath = $currentPhoto;
    $removePhoto = isset($_POST['remove_photo']) && $_POST['remove_photo'] === 'on';
    $hasUpload = isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK;
    if ($removePhoto) {
        $newPhotoPath = '';
        $sets[] = 'photo_path = ?';
        $params[] = $newPhotoPath;
    } elseif ($hasUpload) {
        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['student_photo']['name']));
        $photoName = uniqid() . '_' . $safeName;
        $absPath = $uploadDir . DIRECTORY_SEPARATOR . $photoName;
        if (!move_uploaded_file($_FILES['student_photo']['tmp_name'], $absPath)) {
            throw new Exception('Failed to upload photo');
        }
        $newPhotoPath = 'uploads/' . $photoName;
        $sets[] = 'photo_path = ?';
        $params[] = $newPhotoPath;
    }

    if (!empty($sets)) {
        $params[] = $student_id;
        $sql = 'UPDATE students SET ' . implode(',', $sets) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // If photo changed, delete old file (only for local files under uploads)
    if ($newPhotoPath !== $currentPhoto && !empty($currentPhoto)) {
        $isRemote = preg_match('/^https?:\\/\\//i', $currentPhoto) === 1 || str_starts_with($currentPhoto, '//');
        if (!$isRemote) {
            $baseDir = __DIR__ . DIRECTORY_SEPARATOR;
            $relative = ltrim(str_replace(['\\\\', '/'], DIRECTORY_SEPARATOR, $currentPhoto), DIRECTORY_SEPARATOR);
            $absolutePath = $baseDir . $relative;
            $uploadsDir = $baseDir . 'uploads' . DIRECTORY_SEPARATOR;
            $absNorm = realpath($absolutePath);
            $uploadsNorm = realpath($uploadsDir) ?: $uploadsDir;
            if ($absNorm && $uploadsNorm && str_starts_with($absNorm, rtrim($uploadsNorm, DIRECTORY_SEPARATOR))) {
                if (is_file($absNorm)) {
                    @unlink($absNorm);
                }
            }
        }
    }

    // Helper to upsert parent
    $upsertParent = function($type, $nameField, $phoneField, $occField) use ($pdo, $student_id) {
        $name = isset($_POST[$nameField]) ? trim($_POST[$nameField]) : '';
        $phone = isset($_POST[$phoneField]) ? trim($_POST[$phoneField]) : '';
        $occupation = isset($_POST[$occField]) ? trim($_POST[$occField]) : '';
        // Check if exists
        $check = $pdo->prepare("SELECT id FROM parents WHERE student_id = ? AND parent_type = ? LIMIT 1");
        $check->execute([$student_id, $type]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $stmt = $pdo->prepare("UPDATE parents SET full_name = ?, phone_number = ?, occupation = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $occupation, $row['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO parents (student_id, parent_type, full_name, phone_number, occupation) VALUES (?,?,?,?,?)");
            $stmt->execute([$student_id, $type, $name, $phone, $occupation]);
        }
    };

    $upsertParent('father', 'father_full_name', 'father_phone', 'father_occupation');
    $upsertParent('mother', 'mother_full_name', 'mother_phone', 'mother_occupation');
    $upsertParent('guardian', 'guardian_full_name', 'guardian_phone', 'guardian_occupation');

    $pdo->commit();
    echo json_encode(['success' => true, 'photo_path' => $newPhotoPath]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail('DB error: ' . $e->getMessage());
}


