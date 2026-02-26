<?php
session_start();
require '../config.php';
requireAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$section = isset($_POST['section']) ? trim($_POST['section']) : '';

if ($student_id <= 0 || empty($section)) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID or section']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $updatedData = [];
    
    switch($section) {
        case 'personal':
            $sql = "UPDATE students SET 
                    full_name = ?, 
                    christian_name = ?, 
                    gender = ?, 
                    current_grade = ?, 
                    birth_date = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['full_name'] ?? '',
                $_POST['christian_name'] ?? '',
                $_POST['gender'] ?? '',
                $_POST['current_grade'] ?? '',
                $_POST['birth_date'] ?? '',
                $student_id
            ]);
            $updatedData = [
                'full_name' => $_POST['full_name'] ?? '',
                'christian_name' => $_POST['christian_name'] ?? '',
                'gender' => $_POST['gender'] ?? '',
                'current_grade' => $_POST['current_grade'] ?? '',
                'birth_date' => $_POST['birth_date'] ?? ''
            ];
            break;
            
        case 'contact':
            $sql = "UPDATE students SET 
                    phone_number = ?, 
                    sub_city = ?, 
                    district = ?, 
                    specific_area = ?, 
                    house_number = ?, 
                    living_with = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['phone_number'] ?? '',
                $_POST['sub_city'] ?? '',
                $_POST['district'] ?? '',
                $_POST['specific_area'] ?? '',
                $_POST['house_number'] ?? '',
                $_POST['living_with'] ?? '',
                $student_id
            ]);
            $updatedData = [
                'phone_number' => $_POST['phone_number'] ?? '',
                'sub_city' => $_POST['sub_city'] ?? '',
                'district' => $_POST['district'] ?? '',
                'specific_area' => $_POST['specific_area'] ?? '',
                'house_number' => $_POST['house_number'] ?? '',
                'living_with' => $_POST['living_with'] ?? ''
            ];
            break;
            
        case 'family':
            // Handle family information - check if we need to update parents table
            $age = null;
            
            // Get student age to determine if we need parents table
            $ageStmt = $pdo->prepare("SELECT birth_date, birth_year_et, birth_month_et, birth_day_et FROM students WHERE id = ?");
            $ageStmt->execute([$student_id]);
            $studentData = $ageStmt->fetch();
            
            if ($studentData) {
                if (!empty($studentData['birth_year_et'])) {
                    $age = ethiopian_age_from_ymd($studentData['birth_year_et'], $studentData['birth_month_et'], $studentData['birth_day_et']);
                } elseif (!empty($studentData['birth_date'])) {
                    $parts = explode('-', $studentData['birth_date']);
                    if (count($parts) === 3) {
                        $age = ethiopian_age_from_ymd(intval($parts[0]), intval($parts[1]), intval($parts[2]));
                    }
                }
            }
            
            if ($age !== null && $age < 18) {
                // Update or insert parent records
                $parentTypes = ['father', 'mother', 'guardian'];
                
                foreach ($parentTypes as $type) {
                    $fullName = $_POST[$type . '_full_name'] ?? '';
                    $phone = $_POST[$type . '_phone'] ?? '';
                    $occupation = $_POST[$type . '_occupation'] ?? '';
                    
                    if (!empty($fullName)) {
                        // Check if parent record exists
                        $checkStmt = $pdo->prepare("SELECT id FROM parents WHERE student_id = ? AND parent_type = ?");
                        $checkStmt->execute([$student_id, $type]);
                        $existingParent = $checkStmt->fetch();
                        
                        if ($existingParent) {
                            // Update existing record
                            $updateStmt = $pdo->prepare("UPDATE parents SET full_name = ?, phone_number = ?, occupation = ? WHERE id = ?");
                            $updateStmt->execute([$fullName, $phone, $occupation, $existingParent['id']]);
                        } else {
                            // Insert new record
                            $insertStmt = $pdo->prepare("INSERT INTO parents (student_id, parent_type, full_name, phone_number, occupation) VALUES (?, ?, ?, ?, ?)");
                            $insertStmt->execute([$student_id, $type, $fullName, $phone, $occupation]);
                        }
                    }
                }
            }
            
            $updatedData = [
                'father_full_name' => $_POST['father_full_name'] ?? '',
                'father_phone' => $_POST['father_phone'] ?? '',
                'father_occupation' => $_POST['father_occupation'] ?? '',
                'mother_full_name' => $_POST['mother_full_name'] ?? '',
                'mother_phone' => $_POST['mother_phone'] ?? '',
                'mother_occupation' => $_POST['mother_occupation'] ?? '',
                'guardian_full_name' => $_POST['guardian_full_name'] ?? '',
                'guardian_phone' => $_POST['guardian_phone'] ?? '',
                'guardian_occupation' => $_POST['guardian_occupation'] ?? ''
            ];
            break;
            
        case 'emergency':
            $sql = "UPDATE students SET 
                    emergency_name = ?, 
                    emergency_phone = ?, 
                    emergency_alt_phone = ?, 
                    emergency_address = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['emergency_name'] ?? '',
                $_POST['emergency_phone'] ?? '',
                $_POST['emergency_alt_phone'] ?? '',
                $_POST['emergency_address'] ?? '',
                $student_id
            ]);
            $updatedData = [
                'emergency_name' => $_POST['emergency_name'] ?? '',
                'emergency_phone' => $_POST['emergency_phone'] ?? '',
                'emergency_alt_phone' => $_POST['emergency_alt_phone'] ?? '',
                'emergency_address' => $_POST['emergency_address'] ?? ''
            ];
            break;
            
        case 'education':
            $sql = "UPDATE students SET 
                    current_grade = ?, 
                    status = ?, 
                    field_of_study = ?, 
                    transferred_from_other_school = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['current_grade'] ?? '',
                $_POST['status'] ?? 'active',
                $_POST['field_of_study'] ?? '',
                $_POST['previous_school'] ?? '',
                $student_id
            ]);
            $updatedData = [
                'current_grade' => $_POST['current_grade'] ?? '',
                'status' => $_POST['status'] ?? 'active',
                'field_of_study' => $_POST['field_of_study'] ?? '',
                'transferred_from_other_school' => $_POST['previous_school'] ?? ''
            ];
            break;
            
        case 'spiritual':
            $sql = "UPDATE students SET 
                    has_spiritual_father = ?, 
                    spiritual_father_name = ?, 
                    spiritual_father_phone = ?, 
                    spiritual_father_church = ?, 
                    came_from_other_religion = ? 
                    WHERE id = ?";
            $hasSpiritual = isset($_POST['has_spiritual_father']) ? 1 : 0;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $hasSpiritual,
                $hasSpiritual ? ($_POST['spiritual_father_name'] ?? '') : '',
                $hasSpiritual ? ($_POST['spiritual_father_phone'] ?? '') : '',
                $hasSpiritual ? ($_POST['spiritual_father_church'] ?? '') : '',
                $_POST['came_from_other_religion'] ?? '',
                $student_id
            ]);
            $updatedData = [
                'has_spiritual_father' => $hasSpiritual,
                'spiritual_father_name' => $hasSpiritual ? ($_POST['spiritual_father_name'] ?? '') : '',
                'spiritual_father_phone' => $hasSpiritual ? ($_POST['spiritual_father_phone'] ?? '') : '',
                'spiritual_father_church' => $hasSpiritual ? ($_POST['spiritual_father_church'] ?? '') : '',
                'came_from_other_religion' => $_POST['came_from_other_religion'] ?? ''
            ];
            break;
            
        case 'additional':
            $sql = "UPDATE students SET 
                    special_interests = ?, 
                    siblings_in_school = ?, 
                    instrument = ?, 
                    physical_disability = ?, 
                    weak_side = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['special_interests'] ?? '',
                $_POST['siblings_in_school'] ?? '',
                $_POST['instrument'] ?? '',
                $_POST['physical_disability'] ?? '',
                $_POST['weak_side'] ?? '',
                $student_id
            ]);
            $updatedData = [
                'special_interests' => $_POST['special_interests'] ?? '',
                'siblings_in_school' => $_POST['siblings_in_school'] ?? '',
                'instrument' => $_POST['instrument'] ?? '',
                'physical_disability' => $_POST['physical_disability'] ?? '',
                'weak_side' => $_POST['weak_side'] ?? ''
            ];
            break;
            
        default:
            throw new Exception('Invalid section specified');
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => ucfirst($section) . ' information updated successfully',
        'updatedData' => $updatedData
    ]);
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("Update student section error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating ' . $section . ' information: ' . $e->getMessage()
    ]);
}

// Helper function for Ethiopian age calculation
function ethiopian_age_from_ymd($ey, $em, $ed) {
    $today = new DateTime();
    $currentYear = intval($today->format('Y')) - 7; // Approximate Ethiopian year
    $currentMonth = intval($today->format('m'));
    $currentDay = intval($today->format('d'));
    
    $age = $currentYear - $ey;
    if ($currentMonth < $em || ($currentMonth === $em && $currentDay < $ed)) {
        $age--;
    }
    return max(0, $age);
}
?>