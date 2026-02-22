<?php
session_start();
require 'config.php';
require 'includes/students_helpers.php';
require 'includes/admin_layout.php';
require 'includes/mobile_table.php';

$admin_id = $_SESSION['admin_id'] ?? 1;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'delete_student':
            $student_id = (int)$_POST['student_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $result = $stmt->execute([$student_id]);
                echo json_encode(['success' => $result, 'message' => $result ? 'Student deleted successfully' : 'Failed to delete student']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'toggle_flag':
            $student_id = (int)$_POST['student_id'];
            $table = $_POST['table'] ?? 'students';
            try {
                if ($table === 'instruments') {
                    $stmt = $pdo->prepare("UPDATE instrument_registrations SET flagged = 1 - COALESCE(flagged, 0) WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE students SET flagged = 1 - COALESCE(flagged, 0) WHERE id = ?");
                }
                $result = $stmt->execute([$student_id]);
                
                // Get new flag status
                if ($table === 'instruments') {
                    $stmt = $pdo->prepare("SELECT flagged FROM instrument_registrations WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("SELECT flagged FROM students WHERE id = ?");
                }
                $stmt->execute([$student_id]);
                $flagged = $stmt->fetchColumn();
                
                echo json_encode([
                    'success' => $result, 
                    'flagged' => (bool)$flagged,
                    'message' => $flagged ? 'Student flagged' : 'Student unflagged'
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'bulk_action':
            $ids = $_POST['ids'] ?? [];
            $bulk_action = $_POST['bulk_action'] ?? '';
            $table = $_POST['table'] ?? 'students';
            
            if (empty($ids) || empty($bulk_action)) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }
            
            try {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                
                switch ($bulk_action) {
                    case 'delete':
                        if ($table === 'instruments') {
                            $stmt = $pdo->prepare("DELETE FROM instrument_registrations WHERE id IN ($placeholders)");
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM students WHERE id IN ($placeholders)");
                        }
                        break;
                    case 'flag':
                        if ($table === 'instruments') {
                            $stmt = $pdo->prepare("UPDATE instrument_registrations SET flagged = 1 WHERE id IN ($placeholders)");
                        } else {
                            $stmt = $pdo->prepare("UPDATE students SET flagged = 1 WHERE id IN ($placeholders)");
                        }
                        break;
                    case 'unflag':
                        if ($table === 'instruments') {
                            $stmt = $pdo->prepare("UPDATE instrument_registrations SET flagged = 0 WHERE id IN ($placeholders)");
                        } else {
                            $stmt = $pdo->prepare("UPDATE students SET flagged = 0 WHERE id IN ($placeholders)");
                        }
                        break;
                    default:
                        echo json_encode(['success' => false, 'message' => 'Invalid action']);
                        exit;
                }
                
                $result = $stmt->execute($ids);
                echo json_encode([
                    'success' => $result, 
                    'message' => $result ? 'Bulk action completed successfully' : 'Failed to complete bulk action'
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_student_details':
            $student_id = (int)$_POST['student_id'];
            $table = $_POST['table'] ?? 'students';
            
            try {
                if ($table === 'instruments') {
                    $stmt = $pdo->prepare("SELECT ir.*, s.* FROM instrument_registrations ir LEFT JOIN students s ON ir.id = ? WHERE ir.id = ?");
                    $stmt->execute([$student_id, $student_id]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                    $stmt->execute([$student_id]);
                }
                
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($student) {
                    echo json_encode(['success' => true, 'student' => $student]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'edit_student':
            $student_id = (int)$_POST['student_id'];
            $table = $_POST['table'] ?? 'students';
            
            // Check if this is a data update request
            if (isset($_POST['update_data'])) {
                // Future: Handle actual student data updates here
                echo json_encode(['success' => false, 'message' => 'Edit functionality will be implemented in future update']);
                exit;
            }
            
            // For now, return the student data for editing
            try {
                if ($table === 'instruments') {
                    $stmt = $pdo->prepare("SELECT ir.*, s.* FROM instrument_registrations ir LEFT JOIN students s ON ir.id = ? WHERE ir.id = ?");
                    $stmt->execute([$student_id, $student_id]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                    $stmt->execute([$student_id]);
                }
                
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($student) {
                    echo json_encode(['success' => true, 'student' => $student, 'message' => 'Student data retrieved for editing']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_student':
            $student_id = (int)$_POST['student_id'];
            $table = $_POST['table'] ?? 'students';
            
            try {
                // Define allowed fields for updating
                $allowed_fields = [
                    'full_name', 'christian_name', 'gender', 'birth_date', 'phone_number', 'current_grade',
                    'sub_city', 'district', 'specific_area', 'house_number', 'living_with',
                    'regular_school_name', 'regular_school_grade', 'education_level', 'field_of_study', 'school_year_start',
                    'father_full_name', 'father_phone', 'father_occupation',
                    'mother_full_name', 'mother_phone', 'mother_occupation',
                    'guardian_full_name', 'guardian_phone', 'guardian_occupation',
                    'emergency_name', 'emergency_phone', 'emergency_alt_phone', 'emergency_address',
                    'has_spiritual_father', 'spiritual_father_name', 'spiritual_father_phone', 'spiritual_father_church',
                    'special_interests', 'siblings_in_school', 'physical_disability', 'weak_side',
                    'transferred_from_other_school', 'came_from_other_religion'
                ];
                
                // Build update query
                $update_fields = [];
                $update_values = [];
                
                foreach ($allowed_fields as $field) {
                    if (isset($_POST[$field])) {
                        $update_fields[] = "`$field` = ?";
                        $update_values[] = $_POST[$field];
                    }
                }
                
                if (empty($update_fields)) {
                    echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
                    exit;
                }
                
                // Check for duplicate full names (excluding current student)
                if (isset($_POST['full_name']) && !empty($_POST['full_name'])) {
                    $stmt = $pdo->prepare("SELECT id FROM students WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?)) AND id != ?");
                    $stmt->execute([$_POST['full_name'], $student_id]);
                    if ($stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'A student with this full name already exists']);
                        exit;
                    }
                }
                
                // Perform update
                $update_values[] = $student_id;
                $sql = "UPDATE students SET " . implode(', ', $update_fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute($update_values);
                
                if ($result) {
                    // Fetch updated student data
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $updated_student = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Student information updated successfully',
                        'student' => $updated_student
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update student information']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_student_advanced':
            $student_id = (int)$_POST['student_id'];
            $table = $_POST['table'] ?? 'students';
            
            try {
                // Enhanced allowed fields for advanced editing
                $allowed_fields = [
                    // Basic Info
                    'full_name', 'christian_name', 'gender', 'birth_date', 'current_grade', 'instrument',
                    
                    // Academic Info
                    'regular_school_name', 'education_level', 'field_of_study', 'gpa', 'academic_notes',
                    
                    // Family Info
                    'father_full_name', 'father_phone', 'father_occupation', 'father_education',
                    'mother_full_name', 'mother_phone', 'mother_occupation', 'mother_education',
                    'guardian_full_name', 'guardian_phone', 'guardian_occupation', 'guardian_relationship',
                    
                    // Contact Info
                    'phone_number', 'email', 'sub_city', 'district', 'kebele', 'house_number', 'full_address',
                    'emergency_name', 'emergency_phone', 'emergency_alt_phone', 'emergency_relationship', 'emergency_address',
                    
                    // Additional Info
                    'special_interests', 'siblings_in_school', 'physical_disability', 'weak_side', 'medical_notes',
                    'internal_notes', 'status', 'priority_level'
                ];
                
                // Build update query
                $update_fields = [];
                $update_values = [];
                
                foreach ($allowed_fields as $field) {
                    if (isset($_POST[$field])) {
                        $update_fields[] = "`$field` = ?";
                        $update_values[] = $_POST[$field];
                    }
                }
                
                // Handle photo upload if present
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $photo_result = handlePhotoUpload($_FILES['photo'], $student_id);
                    if ($photo_result['success']) {
                        $update_fields[] = "`photo_path` = ?";
                        $update_values[] = $photo_result['path'];
                    }
                }
                
                if (empty($update_fields)) {
                    echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
                    exit;
                }
                
                // Check for duplicate full names (excluding current student)
                if (isset($_POST['full_name']) && !empty($_POST['full_name'])) {
                    $stmt = $pdo->prepare("SELECT id FROM students WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?)) AND id != ?");
                    $stmt->execute([$_POST['full_name'], $student_id]);
                    if ($stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'A student with this full name already exists']);
                        exit;
                    }
                }
                
                // Add updated timestamp
                $update_fields[] = "`updated_at` = NOW()";
                
                // Perform update
                $update_values[] = $student_id;
                $sql = "UPDATE students SET " . implode(', ', $update_fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute($update_values);
                
                if ($result) {
                    // Fetch updated student data
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $updated_student = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Log the update activity
                    try {
                        $activity_stmt = $pdo->prepare(
                            "INSERT INTO activity_log (user_id, action, target_type, target_id, description, created_at) 
                             VALUES (?, 'update', 'student', ?, 'Advanced student profile update', NOW())"
                        );
                        $activity_stmt->execute([1, $student_id]); // Using user_id = 1 as placeholder
                    } catch (Exception $log_error) {
                        // Continue even if logging fails
                        error_log('Activity log error: ' . $log_error->getMessage());
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Student information updated successfully with advanced features',
                        'student' => $updated_student
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update student information']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Main page logic
$all = fetch_all_students_with_parents($pdo);

$view = $_GET['view'] ?? 'all'; // all | youth | under | instrument
if (!in_array($view, ['all','youth','under','instrument'], true)) $view = 'all';

// Search functionality
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if ($view === 'instrument') {
    // Instrument type filter
    $instrument_type = isset($_GET['instrument_type']) ? $_GET['instrument_type'] : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $params = [];
    
    // Updated query to link based on exact full name matching
    $sql = "SELECT ir.*, 
                   s.full_name as s_full_name, 
                   s.christian_name as s_christian_name, 
                   s.gender as s_gender, 
                   s.birth_date as s_birth_date, 
                   s.current_grade as s_current_grade, 
                   s.phone_number as s_phone_number, 
                   s.photo_path as s_photo_path, 
                   s.id as s_id,
                   ir.id as registration_id,
                   CASE WHEN s.id IS NOT NULL THEN s.id ELSE NULL END as student_id
            FROM instrument_registrations ir 
            LEFT JOIN students s ON LOWER(TRIM(ir.full_name)) = LOWER(TRIM(s.full_name))";
    
    $where_conditions = [];
    
    if ($instrument_type) {
        $where_conditions[] = "ir.instrument = ?";
        $params[] = $instrument_type;
    }
    
    if ($search) {
        $where_conditions[] = "(ir.full_name LIKE ? OR ir.christian_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($date_from) {
        $where_conditions[] = "ir.created_at >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "ir.created_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    // Add status filtering
    if ($status_filter) {
        switch ($status_filter) {
            case 'linked':
                $where_conditions[] = "s.id IS NOT NULL";
                break;
            case 'unlinked':
                $where_conditions[] = "s.id IS NULL";
                break;
            case 'flagged':
                $where_conditions[] = "ir.flagged = 1";
                break;
        }
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(' AND ', $where_conditions);
    }
    
    $sql .= " ORDER BY ir.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Transform the data to match expected format and detect duplicates
    $student_groups = [];
    $duplicate_names = [];
    
    foreach ($students as &$student) {
        // Always use instrument registration data as primary source
        // Only use student table data as supplementary info where instrument data is missing
        
        // Set registration ID for actions
        $student['registration_id'] = $student['id'];
        
        // Keep original instrument registration data
        $original_full_name = $student['full_name'];
        $original_christian_name = $student['christian_name'];
        $original_gender = $student['gender'];
        $original_phone = $student['phone_number'];
        $original_photo = $student['person_photo_path'];
        
        // Use instrument registration data first, fallback to student data only if missing
        $student['full_name'] = !empty($original_full_name) ? $original_full_name : ($student['s_full_name'] ?? '-');
        $student['christian_name'] = !empty($original_christian_name) ? $original_christian_name : ($student['s_christian_name'] ?? '-');
        $student['gender'] = !empty($original_gender) ? $original_gender : ($student['s_gender'] ?? '-');
        $student['phone_number'] = !empty($original_phone) ? $original_phone : ($student['s_phone_number'] ?? '-');
        $student['photo_path'] = !empty($original_photo) ? $original_photo : ($student['s_photo_path'] ?? '');
        
        // Additional info from student table (if linked)
        $student['current_grade'] = $student['s_current_grade'] ?? null;
        
        // Set student_id (null if no exact name match found)
        $student['id'] = $student['student_id'] ?: null;
        
        // Format birth date from Ethiopian calendar (always from instrument registration)
        if ($student['birth_year_et'] && $student['birth_month_et'] && $student['birth_day_et']) {
            $student['birth_date'] = $student['birth_year_et'] . '-' . 
                                     str_pad($student['birth_month_et'], 2, '0', STR_PAD_LEFT) . '-' . 
                                     str_pad($student['birth_day_et'], 2, '0', STR_PAD_LEFT);
        } else {
            $student['birth_date'] = $student['s_birth_date'] ?? 'N/A';
        }
        
        // Group by name for duplicate detection and grouping
        $name_key = strtolower(trim($student['full_name']));
        if (!isset($student_groups[$name_key])) {
            $student_groups[$name_key] = [];
        }
        $student_groups[$name_key][] = &$student;
        
        // Mark as duplicate if more than one registration
        if (count($student_groups[$name_key]) > 1) {
            $duplicate_names[$name_key] = true;
            foreach ($student_groups[$name_key] as &$dup_student) {
                $dup_student['is_duplicate'] = true;
                $dup_student['duplicate_count'] = count($student_groups[$name_key]);
            }
        }
    }
    
    // Create grouped students array for display
    $grouped_students = [];
    foreach ($student_groups as $name_key => $group) {
        $primary_student = $group[0]; // Use first registration as primary
        $primary_student['instrument_group'] = array_column($group, 'instrument');
        $primary_student['registration_group'] = $group;
        $primary_student['total_registrations'] = count($group);
        $grouped_students[] = $primary_student;
    }
} else {
    // Regular student view with search
    $students = ($view === 'all') ? $all : filter_students_by_age_group($all, $view === 'youth' ? 'youth' : 'under');
    
    // Apply search filter
    if ($search) {
        $students = array_filter($students, function($student) use ($search) {
            return stripos($student['full_name'], $search) !== false || 
                   stripos($student['christian_name'], $search) !== false ||
                   stripos($student['phone_number'], $search) !== false;
        });
    }
    
    // Apply date filter
    if ($date_from || $date_to) {
        $students = array_filter($students, function($student) use ($date_from, $date_to) {
            $created_date = $student['created_at'] ?? '';
            if (!$created_date) return true;
            
            if ($date_from && $created_date < $date_from) return false;
            if ($date_to && $created_date > ($date_to . ' 23:59:59')) return false;
            return true;
        });
    }
    
    $grouped_students = $students; // For consistency with instrument view
}

$title = $view === 'youth' ? '18+ Students' : ($view === 'under' ? 'Under 18 Students' : ($view === 'instrument' ? 'Instrument Students' : 'All Students'));
$all_fields = get_all_student_fields();

// Load per-view column prefs from DB or URL: admin_preferences table, table_name = students_[view]
$url_columns = $_GET['columns'] ?? null;
if ($url_columns) {
    $selected_fields = explode(',', $url_columns);
} else {
    $stmt = $pdo->prepare("SELECT column_list FROM admin_preferences WHERE admin_id = ? AND table_name = ? LIMIT 1");
    $stmt->execute([$admin_id, 'students_'.$view]);
    $pref = $stmt->fetch(PDO::FETCH_ASSOC);
    $selected_fields = ($pref && !empty($pref['column_list'])) ? explode(',', $pref['column_list']) : (
        $view === 'youth' ? ['photo_path','full_name','gender','birth_date','current_grade','phone_number','field_of_study'] :
        ($view === 'under' ? ['photo_path','full_name','gender','birth_date','current_grade','sub_city','district','phone_number'] : ['photo_path','full_name','gender','birth_date','current_grade','phone_number'])
    );
}

// Define enhanced table headers - dynamically support all database fields
if ($view === 'instrument') {
    // Base headers for instrument view
    $base_headers = [
        'checkbox' => ['label' => '', 'width' => '30px', 'sortable' => false, 'mobile_priority' => 0],
        'photo_path' => ['label' => 'Photo', 'width' => '50px', 'sortable' => false, 'mobile_priority' => 1],
        'full_name' => ['label' => 'Full Name', 'width' => '120px', 'sortable' => true, 'primary' => true, 'mobile_priority' => 1],
        'instrument' => ['label' => 'Instrument', 'width' => '80px', 'sortable' => true, 'mobile_priority' => 2],
        'gender' => ['label' => 'Gender', 'width' => '50px', 'sortable' => true, 'mobile_priority' => 3],
        'phone_number' => ['label' => 'Phone', 'width' => '90px', 'sortable' => true, 'mobile_priority' => 2],
        'registration_date' => ['label' => 'Registered', 'width' => '80px', 'sortable' => true, 'mobile_priority' => 3],
        'status' => ['label' => 'Status', 'width' => '60px', 'sortable' => true, 'mobile_priority' => 2]
    ];
} else {
    // Base headers for student views
    $base_headers = [
        'checkbox' => ['label' => '', 'width' => '30px', 'sortable' => false, 'mobile_priority' => 0],
        'photo_path' => ['label' => 'Photo', 'width' => '50px', 'sortable' => false, 'mobile_priority' => 1],
        'full_name' => ['label' => 'Full Name', 'width' => '120px', 'sortable' => true, 'primary' => true, 'mobile_priority' => 1],
        'christian_name' => ['label' => 'Christian Name', 'width' => '100px', 'sortable' => true, 'mobile_priority' => 3],
        'gender' => ['label' => 'Gender', 'width' => '50px', 'sortable' => true, 'mobile_priority' => 3],
        'birth_date' => ['label' => 'Birth Date', 'width' => '80px', 'sortable' => true, 'mobile_priority' => 2],
        'current_grade' => ['label' => 'Grade', 'width' => '60px', 'sortable' => true, 'mobile_priority' => 2],
        'phone_number' => ['label' => 'Phone', 'width' => '90px', 'sortable' => true, 'mobile_priority' => 2]
    ];
}

// Create comprehensive table headers that support ALL database fields
$table_headers = $base_headers;

// Add any additional fields from the all_fields list that aren't already defined
foreach ($all_fields as $field_key => $field_label) {
    if (!isset($table_headers[$field_key])) {
        // Determine appropriate mobile priority and sortability
        $mobile_priority = 3; // Default to low priority for additional fields
        $sortable = true;
        $width = '80px'; // Default compact width
        
        // Special handling for certain field types
        if (strpos($field_key, 'photo') !== false) {
            $sortable = false;
            $mobile_priority = 1;
            $width = '50px';
        } elseif (in_array($field_key, ['full_name', 'christian_name'])) {
            $mobile_priority = 1;
            $width = '120px';
        } elseif (in_array($field_key, ['phone_number', 'birth_date', 'current_grade'])) {
            $mobile_priority = 2;
            $width = '90px';
        } elseif (strpos($field_key, '_phone') !== false) {
            $width = '90px';
        } elseif (strpos($field_key, '_date') !== false || strpos($field_key, 'created_at') !== false) {
            $width = '80px';
        } elseif (in_array($field_key, ['gender', 'grade', 'status'])) {
            $width = '60px';
        } elseif (strpos($field_key, '_name') !== false) {
            $width = '100px';
        }
        
        $table_headers[$field_key] = [
            'label' => $field_label,
            'width' => $width,
            'sortable' => $sortable,
            'mobile_priority' => $mobile_priority
        ];
    }
}

// Filter table headers based on selected fields
$filtered_headers = [];
foreach ($table_headers as $key => $header) {
    if ($key === 'checkbox' || in_array($key, $selected_fields)) {
        $filtered_headers[$key] = $header;
    }
}

// Start building the content
ob_start();
?>

<!-- Page Header with Advanced Controls -->
<div class="mb-4 sm:mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-3 lg:space-y-0">
        <div>
            <h2 class="text-md sm:text-lg font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-<?= $view === 'instrument' ? 'music' : 'users' ?> mr-1.5 text-primary-600 dark:text-primary-400 text-xs"></i>
                <span class="truncate max-w-xs sm:max-w-sm"><?= htmlspecialchars($title) ?></span>
                <span class="ml-1.5 px-1.5 py-0.5 text-xs font-medium bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400 rounded-full flex-shrink-0">
                    <?= count($grouped_students) ?> <?= count($grouped_students) === 1 ? 'record' : 'records' ?>
                </span>
            </h2>
            <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400 max-w-md truncate">
                <?php if ($view === 'instrument'): ?>
                    Manage instrument registrations and track status
                <?php else: ?>
                    Student management with filtering and bulk operations
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-2">
            <?php if ($view === 'instrument'): ?>
                <a href="instrument_registration.php" 
                   class="inline-flex items-center justify-center px-2.5 py-1.5 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white rounded-md text-xs font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 touch-target">
                    <i class="fas fa-plus mr-1 text-xs"></i> New Registration
                </a>
            <?php else: ?>
                <a href="student_registration.php" 
                   class="inline-flex items-center justify-center px-2.5 py-1.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-md text-xs font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 touch-target">
                    <i class="fas fa-user-plus mr-1 text-xs"></i> Add Student
                </a>
            <?php endif; ?>
            
            <button type="button" onclick="showColumnCustomizer()" 
                    class="inline-flex items-center justify-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md text-xs font-medium transition-colors touch-target">
                <i class="fas fa-columns mr-1 text-xs"></i> Columns
            </button>
            
            <button type="button" onclick="exportData()" 
                    class="inline-flex items-center justify-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md text-xs font-medium transition-colors touch-target">
                <i class="fas fa-download mr-1 text-xs"></i> Export
            </button>
        </div>
    </div>
    
    <!-- View Tabs - Enhanced Design -->
    <div class="mt-4">
        <div class="flex flex-wrap gap-0.5 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 p-0.5 rounded-lg shadow-inner">
            <a href="students.php?view=all" 
               class="px-2.5 py-1 text-xs font-medium rounded-md transition-all duration-200 touch-target <?= $view==='all' ? 'bg-white dark:bg-gray-600 text-primary-600 dark:text-primary-400 shadow-md transform scale-105' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-gray-600/50' ?>">
                <i class="fas fa-users mr-1 text-xs"></i> All
                <?php if ($view !== 'all'): ?>
                    <span class="ml-1 text-xs opacity-70">(<?= count(fetch_all_students_with_parents($pdo, 1, 10000)) ?>)</span>
                <?php endif; ?>
            </a>
            <a href="students.php?view=youth" 
               class="px-2.5 py-1 text-xs font-medium rounded-md transition-all duration-200 touch-target <?= $view==='youth' ? 'bg-white dark:bg-gray-600 text-primary-600 dark:text-primary-400 shadow-md transform scale-105' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-gray-600/50' ?>">
                <i class="fas fa-user-graduate mr-1 text-xs"></i> 18+
            </a>
            <a href="students.php?view=under" 
               class="px-2.5 py-1 text-xs font-medium rounded-md transition-all duration-200 touch-target <?= $view==='under' ? 'bg-white dark:bg-gray-600 text-primary-600 dark:text-primary-400 shadow-md transform scale-105' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-gray-600/50' ?>">
                <i class="fas fa-child mr-1 text-xs"></i> Under 18
            </a>
            <a href="students.php?view=instrument" 
               class="px-2.5 py-1 text-xs font-medium rounded-md transition-all duration-200 touch-target <?= $view==='instrument' ? 'bg-white dark:bg-gray-600 text-primary-600 dark:text-primary-400 shadow-md transform scale-105' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-gray-600/50' ?>">
                <i class="fas fa-music mr-1 text-xs"></i> Instruments
            </a>
        </div>
    </div>
</div>

<!-- Advanced Search and Filters -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-4" 
     x-data="{ filtersOpen: false, activeFilters: 0 }" 
     x-init="activeFilters = <?= (!empty($_GET['search']) || !empty($_GET['date_from']) || !empty($_GET['date_to']) || !empty($_GET['instrument_type']) || !empty($_GET['status'])) ? 'true' : 'false' ?>">
    
    <!-- Filter Header -->
    <div class="flex items-center justify-between p-3 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center space-x-1.5">
            <i class="fas fa-filter text-primary-600 dark:text-primary-400 text-xs"></i>
            <h3 class="text-xs font-semibold text-gray-900 dark:text-white">Search & Filters</h3>
            <span x-show="activeFilters > 0" 
                  class="px-1.5 py-0.5 text-xs font-medium bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400 rounded-full"
                  x-text="activeFilters + ' active'"></span>
        </div>
        <button @click="filtersOpen = !filtersOpen" 
                class="flex items-center space-x-1 px-1.5 py-0.5 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors touch-target">
            <span x-text="filtersOpen ? 'Hide' : 'Show'"></span>
            <i class="fas fa-chevron-down transition-transform duration-200 text-xs" :class="filtersOpen ? 'rotate-180' : ''"></i>
        </button>
    </div>
    
    <!-- Filter Content -->
    <div x-show="filtersOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         class="p-2">
        
        <form method="get" class="space-y-2" id="filter-form">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            
            <!-- Search Bar -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <i class="fas fa-search mr-1 text-xs"></i> Search Students
                    </label>
                    <div class="relative">
                        <input type="text" name="search" id="search" 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                               placeholder="Search by name, phone, details..."
                               class="w-full pl-6 pr-8 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-colors text-xs">
                        <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400 text-xs"></i>
                        </div>
                        <?php if (!empty($_GET['search'])): ?>
                            <button type="button" onclick="clearSearch()" 
                                    class="absolute inset-y-0 right-0 pr-1.5 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <i class="fas fa-bolt mr-1 text-xs"></i> Quick Actions
                    </label>
                    <button type="button" onclick="applyQuickFilter('flagged')" 
                            class="w-full px-2.5 py-1.5 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400 border border-orange-200 dark:border-orange-800 rounded-md hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors text-xs font-medium">
                        <i class="fas fa-flag mr-1 text-xs"></i> Show Flagged Only
                    </button>
                </div>
            </div>
            
            <!-- Advanced Filters -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <?php if ($view === 'instrument'): ?>
                    <div>
                        <label for="instrument_type" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-music mr-1 text-xs"></i> Instrument Type
                        </label>
                        <select name="instrument_type" id="instrument_type" 
                                class="w-full px-1.5 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-xs">
                            <option value="">All Instruments</option>
                            <option value="begena" <?= ($instrument_type ?? '') === 'begena' ? 'selected' : '' ?>>በገና (Begena)</option>
                            <option value="masenqo" <?= ($instrument_type ?? '') === 'masenqo' ? 'selected' : '' ?>>መሰንቆ (Masenqo)</option>
                            <option value="kebero" <?= ($instrument_type ?? '') === 'kebero' ? 'selected' : '' ?>>ከበሮ (Kebero)</option>
                            <option value="krar" <?= ($instrument_type ?? '') === 'krar' ? 'selected' : '' ?>>ክራር (Krar)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status_filter" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-filter mr-1 text-xs"></i> Registration Status
                        </label>
                        <select name="status" id="status_filter" 
                                class="w-full px-1.5 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-xs">
                            <option value="">All Records</option>
                            <option value="linked" <?= ($_GET['status'] ?? '') === 'linked' ? 'selected' : '' ?>>Linked to Student</option>
                            <option value="unlinked" <?= ($_GET['status'] ?? '') === 'unlinked' ? 'selected' : '' ?>>Instrument Data Only</option>
                            <option value="flagged" <?= ($_GET['status'] ?? '') === 'flagged' ? 'selected' : '' ?>>Flagged Records</option>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div>
                    <label for="date_from" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <i class="fas fa-calendar-alt mr-1 text-xs"></i> Date From
                    </label>
                    <input type="date" name="date_from" id="date_from" 
                           value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                           class="w-full px-1.5 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-xs">
                </div>
                
                <div>
                    <label for="date_to" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <i class="fas fa-calendar-alt mr-1 text-xs"></i> Date To
                    </label>
                    <input type="date" name="date_to" id="date_to" 
                           value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                           class="w-full px-1.5 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-xs">
                </div>
            </div>
            
            <!-- Filter Actions -->
            <div class="flex flex-col sm:flex-row gap-1.5 pt-2 border-t border-gray-200 dark:border-gray-700">
                <button type="submit" 
                        class="inline-flex items-center justify-center px-3 py-1.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white rounded-md font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 touch-target text-xs">
                    <i class="fas fa-search mr-1 text-xs"></i> Apply Filters
                </button>
                
                <a href="students.php?view=<?= htmlspecialchars($view) ?>" 
                   class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md font-medium transition-colors touch-target text-xs">
                    <i class="fas fa-times mr-1 text-xs"></i> Clear All
                </a>
                
                <button type="button" onclick="saveAsPreset()" 
                        class="inline-flex items-center justify-center px-3 py-1.5 border border-primary-300 dark:border-primary-600 text-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-md font-medium transition-colors touch-target text-xs">
                    <i class="fas fa-bookmark mr-1 text-xs"></i> Save Preset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Advanced Data Table -->
<?php
// Enhanced table configuration with actions - optimized for density
$table_config = [
    'table_id' => 'students-table',
    'show_checkboxes' => false,
    'show_row_numbers' => false,
    'striped_rows' => true,
    'hover_effects' => true,
    'compact_mode' => true,
    'mobile_breakpoint' => 'lg',
    'show_actions' => true,
    'sortable' => true,
    'pagination' => true,
    'page_size' => 25,
    'responsive' => true,
    'overflow_x' => 'auto',
    'min_column_width' => '90px',
    'dense_layout' => true,
    'small_fonts' => true
];

// Custom render functions for specific columns
$render_functions = [
    'photo_path' => function($value, $row) {
        // Try multiple photo path sources
        $photo_candidates = [
            $row['photo_path'] ?? '',           // Student table photo
            $row['person_photo_path'] ?? '',    // Instrument registration photo
            $row['s_photo_path'] ?? ''          // Linked student photo
        ];
        
        $photo_path = '';
        foreach ($photo_candidates as $candidate) {
            if (!empty($candidate)) {
                // Check if file exists with different possible paths
                $possible_paths = [
                    $candidate,
                    'uploads/' . basename($candidate),
                    'uploads/photos/' . basename($candidate),
                    'photos/' . basename($candidate)
                ];
                
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $photo_path = $path;
                        break 2; // Break out of both loops
                    }
                }
            }
        }
        
        if (!empty($photo_path)) {
            return '<img src="' . htmlspecialchars($photo_path) . '" alt="Photo" class="w-8 h-8 rounded-full object-cover ring-1 ring-gray-200 dark:ring-gray-700">';
        }
        
        // Generate avatar with initials if no photo found
        $name = $row['full_name'] ?? $row['christian_name'] ?? 'Unknown';
        $initials = '';
        $name_parts = explode(' ', trim($name));
        foreach ($name_parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }
        if (empty($initials)) $initials = 'U';
        
        return '<div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-medium text-xs ring-1 ring-primary-200 dark:ring-primary-700">' . 
               htmlspecialchars($initials) . '</div>';
    },
    
    'full_name' => function($value, $row) {
        $html = '<div class="flex flex-col">';
        $html .= '<span class="font-medium text-gray-900 dark:text-white text-xs">' . htmlspecialchars($value) . '</span>';
        
        // Add badges for special statuses
        $badges = [];
        if (!empty($row['is_duplicate'])) {
            $badges[] = '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-400 rounded"><i class="fas fa-copy text-xs mr-0.5"></i>Dup</span>';
        }
        if (!empty($row['flagged'])) {
            $badges[] = '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-400 rounded"><i class="fas fa-flag text-xs mr-0.5"></i>Flag</span>';
        }
        if (isset($row['student_id']) && !empty($row['student_id'])) {
            $badges[] = '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400 rounded"><i class="fas fa-link text-xs mr-0.5"></i>Link</span>';
        }
        
        if (!empty($badges)) {
            $html .= '<div class="flex flex-wrap gap-0.5 mt-0.5">' . implode('', $badges) . '</div>';
        }
        
        $html .= '</div>';
        return $html;
    },
    
    'instrument' => function($value, $row) {
        if (empty($value)) return '-';
        
        $instruments = [
            'begena' => ['name' => 'በገና', 'icon' => 'fa-guitar', 'color' => 'text-blue-600 dark:text-blue-400'],
            'masenqo' => ['name' => 'መሰንቆ', 'icon' => 'fa-violin', 'color' => 'text-green-600 dark:text-green-400'],
            'kebero' => ['name' => 'ከበሮ', 'icon' => 'fa-drum', 'color' => 'text-orange-600 dark:text-orange-400'],
            'krar' => ['name' => 'ክራር', 'icon' => 'fa-guitar', 'color' => 'text-purple-600 dark:text-purple-400']
        ];
        
        $instrument = $instruments[$value] ?? ['name' => htmlspecialchars($value), 'icon' => 'fa-music', 'color' => 'text-gray-600 dark:text-gray-400'];
        
        return '<div class="flex items-center space-x-1"><i class="fas ' . $instrument['icon'] . ' ' . $instrument['color'] . ' text-xs"></i><span class="text-xs">' . $instrument['name'] . '</span></div>';
    },
    
    'gender' => function($value, $row) {
        if (empty($value)) return '-';
        $icon = strtolower($value) === 'male' ? 'fa-mars text-blue-600 dark:text-blue-400' : 'fa-venus text-pink-600 dark:text-pink-400';
        $display = strtolower($value) === 'male' ? 'M' : 'F';
        return '<div class="flex items-center space-x-1"><i class="fas ' . $icon . ' text-xs"></i><span class="text-xs">' . htmlspecialchars($display) . '</span></div>';
    },
    
    'registration_date' => function($value, $row) {
        $date = $row['created_at'] ?? '';
        if (empty($date)) return '-';
        
        $timestamp = strtotime($date);
        $formatted = date('M j', $timestamp); // Shorter format
        $relative = time() - $timestamp;
        
        if ($relative < 86400) { // Less than 24 hours
            $relative_text = 'Today';
            $color = 'text-green-600 dark:text-green-400';
        } elseif ($relative < 604800) { // Less than 7 days
            $relative_text = 'Week';
            $color = 'text-blue-600 dark:text-blue-400';
        } else {
            $relative_text = $formatted;
            $color = 'text-gray-600 dark:text-gray-400';
        }
        
        return '<div class="text-xs ' . $color . '" title="' . date('M j, Y', $timestamp) . '">' . $relative_text . '</div>';
    },
    
    'status' => function($value, $row) {
        $statuses = [];
        
        if (isset($row['student_id']) && !empty($row['student_id'])) {
            $statuses[] = '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400 rounded">Link</span>';
        } else {
            $statuses[] = '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded">Data</span>';
        }
        
        if (!empty($row['flagged'])) {
            $statuses[] = '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-400 rounded">Flag</span>';
        }
        
        return '<div class="flex flex-col space-y-0.5">' . implode('', $statuses) . '</div>';
    },
    
    'birth_date' => function($value, $row) {
        if (empty($value) || $value === '0000-00-00') return '-';
        $date = date('M j, Y', strtotime($value));
        return '<span class="text-xs text-gray-900 dark:text-white">' . $date . '</span>';
    },
    
    'current_grade' => function($value, $row) {
        if (empty($value)) return '-';
        return '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-400 rounded">' . htmlspecialchars($value) . '</span>';
    },
    
    'phone_number' => function($value, $row) {
        if (empty($value)) return '-';
        return '<div class="flex items-center space-x-1"><i class="fas fa-phone text-gray-400 text-xs"></i><span class="text-xs">' . htmlspecialchars($value) . '</span></div>';
    },
    
    'christian_name' => function($value, $row) {
        if (empty($value)) return '-';
        return '<span class="text-xs text-gray-900 dark:text-white">' . htmlspecialchars($value) . '</span>';
    },
    
    'sub_city' => function($value, $row) {
        if (empty($value)) return '-';
        return '<span class="text-xs text-gray-600 dark:text-gray-400">' . htmlspecialchars($value) . '</span>';
    },
    
    'district' => function($value, $row) {
        if (empty($value)) return '-';
        return '<span class="text-xs text-gray-600 dark:text-gray-400">' . htmlspecialchars($value) . '</span>';
    },
    
    'field_of_study' => function($value, $row) {
        if (empty($value)) return '-';
        return '<span class="text-xs text-gray-600 dark:text-gray-400">' . htmlspecialchars($value) . '</span>';
    }
];

// Add comprehensive renderers for ALL database fields
$additional_renderers = [
    // Phone fields
    'father_phone' => function($v, $r) { return empty($v) ? '-' : '<div class="flex items-center space-x-1"><i class="fas fa-phone text-gray-400 text-xs"></i><span class="text-xs">' . htmlspecialchars($v) . '</span></div>'; },
    'mother_phone' => function($v, $r) { return empty($v) ? '-' : '<div class="flex items-center space-x-1"><i class="fas fa-phone text-gray-400 text-xs"></i><span class="text-xs">' . htmlspecialchars($v) . '</span></div>'; },
    'guardian_phone' => function($v, $r) { return empty($v) ? '-' : '<div class="flex items-center space-x-1"><i class="fas fa-phone text-gray-400 text-xs"></i><span class="text-xs">' . htmlspecialchars($v) . '</span></div>'; },
    'emergency_phone' => function($v, $r) { return empty($v) ? '-' : '<div class="flex items-center space-x-1"><i class="fas fa-phone-alt text-red-400 text-xs"></i><span class="text-xs">' . htmlspecialchars($v) . '</span></div>'; },
    'spiritual_father_phone' => function($v, $r) { return empty($v) ? '-' : '<div class="flex items-center space-x-1"><i class="fas fa-phone text-gray-400 text-xs"></i><span class="text-xs">' . htmlspecialchars($v) . '</span></div>'; },
    
    // Date fields
    'created_at' => function($v, $r) { return empty($v) ? '-' : '<span class="text-xs text-gray-600 dark:text-gray-400">' . date('M j, Y', strtotime($v)) . '</span>'; },
    
    // Boolean/enum fields
    'has_spiritual_father' => function($v, $r) {
        $labels = ['own' => 'Own', 'family' => 'Family', 'none' => 'None'];
        return empty($v) ? '-' : '<span class="text-xs">' . ($labels[$v] ?? htmlspecialchars($v)) . '</span>';
    },
    'living_with' => function($v, $r) {
        $labels = ['both_parents' => 'Both', 'father_only' => 'Father', 'mother_only' => 'Mother', 'relative_or_guardian' => 'Guardian'];
        return empty($v) ? '-' : '<span class="text-xs">' . ($labels[$v] ?? htmlspecialchars($v)) . '</span>';
    },
    
    // Address fields
    'specific_area' => function($v, $r) { return empty($v) ? '-' : '<span class="text-xs text-gray-600 dark:text-gray-400">' . htmlspecialchars($v) . '</span>'; },
    'house_number' => function($v, $r) { return empty($v) ? '-' : '<span class="text-xs text-gray-600 dark:text-gray-400">' . htmlspecialchars($v) . '</span>'; }
];

// Merge additional renderers
$render_functions = array_merge($render_functions, $additional_renderers);

// Add default renderer for any remaining fields
$default_renderer = function($value, $row) {
    if (empty($value) || $value === '0000-00-00' || trim($value) === 'None' || trim($value) === 'የለም') {
        return '<span class="text-gray-400 dark:text-gray-500 text-xs">-</span>';
    }
    
    // Handle dates
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && $value !== '0000-00-00') {
        return '<span class="text-xs text-gray-600 dark:text-gray-400">' . date('M j, Y', strtotime($value)) . '</span>';
    }
    
    // Handle long text
    if (strlen($value) > 30) { // Reduced from 50 to 30
        $truncated = substr($value, 0, 30) . '...';
        return '<span class="text-xs text-gray-700 dark:text-gray-300" title="' . htmlspecialchars($value) . '">' . htmlspecialchars($truncated) . '</span>';
    }
    
    return '<span class="text-xs text-gray-700 dark:text-gray-300">' . htmlspecialchars($value) . '</span>';
};

// Apply default renderer to any column without a specific one
foreach ($all_fields as $field_key => $field_label) {
    if (!isset($render_functions[$field_key])) {
        $render_functions[$field_key] = $default_renderer;
    }
}

// Apply filters and sorting
$filtered_students = $grouped_students;

// Pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = $table_config['page_size'];
$total_records = count($filtered_students);
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;
$paginated_students = array_slice($filtered_students, $offset, $per_page);

echo renderMobileTable(
    $paginated_students,
    $filtered_headers,
    $table_config,
    $render_functions
);
?>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-4 space-y-2 sm:space-y-0">
    <div class="text-xs text-gray-700 dark:text-gray-300">
        Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $per_page, $total_records)) ?> of <?= number_format($total_records) ?> results
    </div>
    
    <div class="flex items-center space-x-0.5">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
               class="px-2 py-1 text-xs font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <i class="fas fa-chevron-left mr-1 text-xs"></i> Prev
            </a>
        <?php endif; ?>
        
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
               class="px-2 py-1 text-xs font-medium <?= $i === $page ? 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/50 border-primary-300 dark:border-primary-600' : 'text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' ?> border rounded-lg transition-colors">
                <?= $i ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
               class="px-2 py-1 text-xs font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                Next <i class="fas fa-chevron-right ml-1 text-xs"></i>
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Student Details Modal -->
<div id="student-details-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" onclick="closeModal(event)">
    <div class="relative top-20 mx-auto p-3 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-xl bg-white dark:bg-gray-800" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-md font-semibold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-user-circle mr-2 text-primary-600 dark:text-primary-400 text-sm"></i>
                Student Details
            </h3>
            <button type="button" onclick="closeStudentDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div id="modal-content" class="py-3">
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600"></div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="flex space-x-2">
                <button type="button" onclick="printStudentDetails()" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-600 hover:bg-gray-700 rounded-lg transition-colors print:hidden">
                    <i class="fas fa-print mr-1 text-xs"></i> Print Profile
                </button>
                <button type="button" onclick="viewFullProfile()" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors print:hidden">
                    <i class="fas fa-external-link-alt mr-1 text-xs"></i> Full Profile
                </button>
            </div>
            
            <div class="flex space-x-2">
                <button type="button" onclick="closeStudentDetailsModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors print:hidden">
                    Close
                </button>
                <button type="button" id="edit-student-btn" onclick="editCurrentStudent()" class="px-3 py-1.5 text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors print:hidden">
                    <i class="fas fa-edit mr-1 text-xs"></i> Edit Student
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Column Customizer Modal -->
<div id="column-customizer-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" onclick="closeModal(event)">
    <div class="relative top-4 mx-auto p-3 border w-11/12 md:w-4/5 lg:w-3/4 xl:w-2/3 shadow-lg rounded-xl bg-white dark:bg-gray-800" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-columns mr-2 text-primary-600 dark:text-primary-400 text-sm"></i>
                Customize Table Columns
                <span class="ml-2 px-1.5 py-0.5 text-xs bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400 rounded-full">
                    <?= ucfirst($view) ?> View
                </span>
            </h3>
            <button type="button" onclick="closeColumnCustomizer()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <div class="py-3">
            <div class="flex flex-wrap items-center justify-between mb-3 gap-2">
                <p class="text-xs text-gray-600 dark:text-gray-400">Select which columns to display in the table. Organize by categories or search for specific fields.</p>
                
                <!-- Quick Actions -->
                <div class="flex flex-wrap gap-1">
                    <button type="button" onclick="resetToDefaults()" class="px-2 py-1 text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 rounded-md hover:bg-orange-200 dark:hover:bg-orange-900/50 transition-colors">
                        <i class="fas fa-undo mr-1 text-xs"></i> Reset to Defaults
                    </button>
                    <button type="button" onclick="loadPreset()" class="px-2 py-1 text-xs bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-md hover:bg-purple-200 dark:hover:bg-purple-900/50 transition-colors">
                        <i class="fas fa-download mr-1 text-xs"></i> Load Preset
                    </button>
                    <button type="button" onclick="saveCurrentAsPreset()" class="px-2 py-1 text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-md hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors">
                        <i class="fas fa-save mr-1 text-xs"></i> Save as Preset
                    </button>
                </div>
            </div>
            
            <!-- Search Box -->
            <div class="mb-3">
                <div class="relative">
                    <input type="text" id="column-search" placeholder="Search columns..." class="w-full pl-8 pr-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-xs" oninput="filterColumns()">
                    <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-xs"></i>
                    </div>
                </div>
            </div>
            
            <!-- Column Selection Area -->
            <div class="max-h-80 overflow-y-auto" id="column-options">
                <!-- Column options will be populated by JavaScript -->
            </div>
            
            <!-- Column Statistics -->
            <div class="mt-3 p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="text-center">
                        <div class="font-semibold text-gray-700 dark:text-gray-300" id="total-columns-count">0</div>
                        <div class="text-gray-500 dark:text-gray-400">Total Columns</div>
                    </div>
                    <div class="text-center">
                        <div class="font-semibold text-primary-600 dark:text-primary-400" id="selected-columns-count">0</div>
                        <div class="text-gray-500 dark:text-gray-400">Selected</div>
                    </div>
                    <div class="text-center">
                        <div class="font-semibold text-gray-700 dark:text-gray-300" id="visible-columns-count">0</div>
                        <div class="text-gray-500 dark:text-gray-400">Visible</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <span id="selected-count">0</span> columns selected
            </div>
            
            <div class="flex space-x-2">
                <button type="button" onclick="closeColumnCustomizer()" class="px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                    <i class="fas fa-times mr-1 text-xs"></i> Cancel
                </button>
                <button type="button" onclick="applyColumnSettings()" class="px-3 py-1.5 text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                    <i class="fas fa-check mr-1 text-xs"></i> Apply Changes
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Define page script - much cleaner without embedded PHP in JS
$page_script = '
<script src="js/students.js"></script>
<script src="js/column-customizer.js"></script>
<script src="js/enhanced-edit-drawer.js"></script>
<script>
// Initialize column customizer data from PHP
initializeColumnData(' . json_encode($all_fields) . ', ' . json_encode($selected_fields) . ');

// Set current view for JavaScript
currentView = "' . htmlspecialchars($view) . '";
currentTable = "' . ($view === 'instrument' ? 'instruments' : 'students') . '";
</script>';



// Render the complete page using the admin layout
echo renderAdminLayout($title . ' - Student Management System', $content, $page_script);
?>

// Global function declarations to ensure availability to included components
window.viewStudentDetails = function(studentId, table = "students") {
    // Store for edit functionality
    window.currentStudentId = studentId;
    window.currentTable = table;
    
    const modal = document.getElementById("student-details-modal");
    const content = document.getElementById("modal-content");
    
    if (!modal || !content) {
        console.error("Modal elements not found");
        return;
    }
    
    content.innerHTML = '<div class=\"flex items-center justify-center py-12\"><div class=\"animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600\"></div></div>';
    modal.classList.remove(\"hidden\");
    
    fetch(window.location.href, {
        method: "POST",
        headers: { \"Content-Type\": \"application/x-www-form-urlencoded\" },
        body: 'action=get_student_details&student_id=' + studentId + '&table=' + table
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.displayStudentDetails(data.student);
        } else {
            content.innerHTML = '<div class=\"text-center py-8\"><i class=\"fas fa-exclamation-triangle text-red-500 text-3xl mb-4\"></i><p class=\"text-gray-600 dark:text-gray-400\">' + data.message + '</p></div>';
        }
    })
    .catch(error => {
        console.error(\"Error:\", error);
        content.innerHTML = '<div class=\"text-center py-8\"><i class=\"fas fa-exclamation-triangle text-red-500 text-3xl mb-4\"></i><p class=\"text-gray-600 dark:text-gray-400\">Error loading student details</p></div>';
    });
};

window.editStudent = function(studentId, table = "students") {
    fetch(window.location.href, {
        method: "POST",
        headers: { \"Content-Type\": \"application/x-www-form-urlencoded\" },
        body: 'action=get_student_details&student_id=' + studentId + '&table=' + table
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.currentEditingStudent = data.student;
            window.currentEditingTable = table;
            window.openEditDrawer(data.student, table);
        } else {
            window.showToast(data.message || "Error loading student data", "error");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        window.showToast("Error loading student data", "error");
    });
};

window.deleteStudent = function(studentId, table = "students") {
    if (!confirm("Are you sure you want to delete this student? This action cannot be undone.")) {
        return;
    }
    
    fetch(window.location.href, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: 'action=delete_student&student_id=' + studentId + '&table=' + table
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.querySelector('[data-student-id=\"' + studentId + '\"]')?.closest("tr");
            if (row) { row.remove(); }
            window.showToast(data.message, "success");
            
            // Reload page to refresh data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            window.showToast(data.message, "error");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        window.showToast("Error deleting student", "error");
    });
};

// Add supporting functions to global scope
window.showToast = function(message, type) {
    type = type || 'info';
    // Remove existing toasts
    document.querySelectorAll('.toast').forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = 'toast fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300';
    
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
    
    toast.className += ' ' + (colors[type] || colors.info);
    toast.innerHTML = '<div class="flex items-center space-x-2"><i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + message + '</span></div>';
    
    document.body.appendChild(toast);
    
    setTimeout(function() {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    setTimeout(function() {
        toast.classList.add('translate-x-full');
        setTimeout(function() { toast.remove(); }, 300);
    }, 3000);
};

// Ensure critical functions are available immediately
document.addEventListener('DOMContentLoaded', function() {
    // Make functions globally available for included components
    if (typeof window.viewStudentDetails === 'function') {
        console.log('Global functions loaded successfully');
    }
});

// Add displayStudentDetails to global scope
window.displayStudentDetails = function(student) {
    const content = document.getElementById("modal-content");
    if (!content) return;
    
    // Print header for printing
    let html = '<div class="hidden print:block mb-4">' +
        '<div class="text-center">' +
            '<h1 class="text-xl font-bold">Student Profile Report</h1>' +
            '<h2 class="text-lg">' + (student.full_name || "N/A") + '</h2>' +
            '<p class="text-sm">Generated: ' + new Date().toLocaleDateString() + '</p>' +
        '</div><hr class="my-4">' +
    '</div>';
    
    // Student profile content
    html += '<div class="space-y-6">';
    
    // Basic Info Header
    html += '<div class="flex items-start space-x-4 p-4 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-gray-800 dark:to-gray-700 rounded-lg">';
    
    // Photo
    if (student.photo_path) {
        html += '<img src="' + student.photo_path + '" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg">';
    } else {
        html += '<div class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center border-4 border-white shadow-lg">' +
            '<i class="fas fa-user text-white text-2xl"></i>' +
        '</div>';
    }
    
    // Basic details
    html += '<div class="flex-1">' +
        '<h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1">' + (student.full_name || "N/A") + '</h3>';
    
    if (student.christian_name) {
        html += '<p class="text-sm text-gray-600 dark:text-gray-300 mb-1">Christian Name: ' + student.christian_name + '</p>';
    }
    
    html += '<div class="flex flex-wrap gap-2 mt-2">' +
        '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Grade: ' + (student.current_grade || "N/A") + '</span>';
    
    if (student.gender) {
        html += '<span class="px-2 py-1 bg-pink-100 text-pink-800 text-xs rounded-full">' + student.gender + '</span>';
    }
    
    if (student.instrument) {
        html += '<span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">' + student.instrument + '</span>';
    }
    
    html += '</div></div></div>';
    
    // Information sections grid
    html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
    
    // Personal Information
    html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
        '<h4 class="text-lg font-semibold mb-4">Personal Information</h4>' +
        '<div class="space-y-2">';
    
    if (student.full_name) html += '<p><strong>Full Name:</strong> ' + student.full_name + '</p>';
    if (student.phone_number) html += '<p><strong>Phone:</strong> ' + student.phone_number + '</p>';
    if (student.birth_date) html += '<p><strong>Birth Date:</strong> ' + student.birth_date + '</p>';
    if (student.sub_city) html += '<p><strong>Sub City:</strong> ' + student.sub_city + '</p>';
    if (student.district) html += '<p><strong>District:</strong> ' + student.district + '</p>';
    
    html += '</div></div>';
    
    // Academic Information
    html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
        '<h4 class="text-lg font-semibold mb-4">Academic Information</h4>' +
        '<div class="space-y-2">';
    
    if (student.regular_school_name) html += '<p><strong>School:</strong> ' + student.regular_school_name + '</p>';
    if (student.education_level) html += '<p><strong>Education Level:</strong> ' + student.education_level + '</p>';
    if (student.field_of_study) html += '<p><strong>Field of Study:</strong> ' + student.field_of_study + '</p>';
    
    html += '</div></div></div>';
    
    // Family Information
    if (student.father_full_name || student.mother_full_name || student.guardian_full_name) {
        html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
            '<h4 class="text-lg font-semibold mb-4">Family Information</h4>' +
            '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
        
        if (student.father_full_name) {
            html += '<div><h5 class="font-medium">Father</h5>' +
                '<p class="text-sm">Name: ' + student.father_full_name + '</p>';
            if (student.father_phone) html += '<p class="text-sm">Phone: ' + student.father_phone + '</p>';
            if (student.father_occupation) html += '<p class="text-sm">Occupation: ' + student.father_occupation + '</p>';
            html += '</div>';
        }
        
        if (student.mother_full_name) {
            html += '<div><h5 class="font-medium">Mother</h5>' +
                '<p class="text-sm">Name: ' + student.mother_full_name + '</p>';
            if (student.mother_phone) html += '<p class="text-sm">Phone: ' + student.mother_phone + '</p>';
            if (student.mother_occupation) html += '<p class="text-sm">Occupation: ' + student.mother_occupation + '</p>';
            html += '</div>';
        }
        
        if (student.guardian_full_name) {
            html += '<div><h5 class="font-medium">Guardian</h5>' +
                '<p class="text-sm">Name: ' + student.guardian_full_name + '</p>';
            if (student.guardian_phone) html += '<p class="text-sm">Phone: ' + student.guardian_phone + '</p>';
            if (student.guardian_occupation) html += '<p class="text-sm">Occupation: ' + student.guardian_occupation + '</p>';
            html += '</div>';
        }
        
        html += '</div></div>';
    }
    
    // Emergency Contact
    if (student.emergency_name || student.emergency_phone) {
        html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
            '<h4 class="text-lg font-semibold mb-4">Emergency Contact</h4>' +
            '<div class="space-y-2">';
        
        if (student.emergency_name) html += '<p><strong>Contact Name:</strong> ' + student.emergency_name + '</p>';
        if (student.emergency_phone) html += '<p><strong>Phone:</strong> ' + student.emergency_phone + '</p>';
        if (student.emergency_alt_phone) html += '<p><strong>Alt Phone:</strong> ' + student.emergency_alt_phone + '</p>';
        if (student.emergency_address) html += '<p><strong>Address:</strong> ' + student.emergency_address + '</p>';
        
        html += '</div></div>';
    }
    
    // Additional Information
    let hasAdditionalInfo = student.special_interests || student.siblings_in_school || student.physical_disability || student.weak_side;
    
    if (hasAdditionalInfo) {
        html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
            '<h4 class="text-lg font-semibold mb-4">Additional Information</h4>' +
            '<div class="space-y-2">';
        
        if (student.special_interests) html += '<p><strong>Special Interests:</strong> ' + student.special_interests + '</p>';
        if (student.siblings_in_school) html += '<p><strong>Siblings in School:</strong> ' + student.siblings_in_school + '</p>';
        if (student.physical_disability) html += '<p><strong>Physical Disability:</strong> ' + student.physical_disability + '</p>';
        if (student.weak_side) html += '<p><strong>Weak Side:</strong> ' + student.weak_side + '</p>';
        
        html += '</div></div>';
    }
    
    // Registration Information
    html += '<div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">' +
        '<h4 class="text-lg font-semibold mb-4">Registration Information</h4>' +
        '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
    
    if (student.created_at) {
        html += '<p><strong>Registered:</strong> ' + new Date(student.created_at).toLocaleDateString() + '</p>';
    }
    if (student.id) {
        html += '<p><strong>Student ID:</strong> ' + student.id + '</p>';
    }
    
    html += '</div></div></div>';
    
    content.innerHTML = html;
};































</script>



<!-- Advanced Edit Drawer Styles -->
<style>
.form-label {
    @apply block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2;
}

.form-input {
    @apply w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white transition-colors duration-200;
}

.form-input:focus {
    @apply outline-none ring-2 ring-blue-500 border-blue-500;
}

.tab-btn {
    @apply transition-all duration-200 ease-in-out;
}

.tab-btn:hover {
    @apply text-gray-700 dark:text-gray-300;
}

.tab-btn-active {
    @apply border-blue-500 text-blue-600 font-semibold;
}

.tab-content {
    @apply transition-opacity duration-300 ease-in-out;
}

.drawer-panel {
    @apply transition-transform duration-300 ease-in-out;
}

/* Custom scrollbar for drawer */
.drawer-panel {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f7fafc;
}

.drawer-panel::-webkit-scrollbar {
    width: 6px;
}

.drawer-panel::-webkit-scrollbar-track {
    background: #f7fafc;
}

.drawer-panel::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

.drawer-panel::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

/* Advanced form field animations */
.form-input {
    @apply transition-all duration-200;
}

.form-input:focus {
    @apply transform scale-105;
}

/* Field validation styles */
.field-error {
    @apply text-red-500 text-xs font-medium;
}

/* Success animations */
@keyframes saveSuccess {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.save-success {
    animation: saveSuccess 0.3s ease-in-out;
}

/* Loading spinner for photo upload */
.photo-loading {
    @apply absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center rounded-full;
}

/* Tab indicator */
.tab-indicator {
    @apply absolute bottom-0 left-0 h-0.5 bg-blue-500 transition-all duration-300 ease-in-out;
}

/* Advanced hover effects */
.hover-lift {
    @apply transition-transform duration-200;
}

.hover-lift:hover {
    @apply transform -translate-y-1 shadow-lg;
}

/* Status indicator styles */
.status-indicator {
    @apply flex items-center space-x-2 text-sm;
}

.status-dot {
    @apply w-2 h-2 rounded-full;
}

/* Mobile responsiveness for drawer */
@media (max-width: 768px) {
    .drawer-panel {
        @apply max-w-full;
    }
    
    .tab-btn {
        @apply px-3 py-2 text-xs;
    }
    
    .form-input {
        @apply text-base; /* Prevent zoom on iOS */
    }
}

/* Print styles for drawer content */
@media print {
    #editDrawer {
        display: none !important;
    }
}

/* Dark mode enhancements */
@media (prefers-color-scheme: dark) {
    .form-input {
        @apply bg-gray-800 border-gray-600 text-white;
    }
    
    .form-input:focus {
        @apply border-blue-400 ring-blue-400;
    }
}

/* Advanced animations */
.fade-in {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.slide-in-right {
    animation: slideInRight 0.3s ease-in-out;
}

@keyframes slideInRight {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

/* Accessibility improvements */
.form-input:focus {
    @apply ring-offset-2 ring-offset-white dark:ring-offset-gray-800;
}

/* Better focus indicators */
.tab-btn:focus {
    @apply outline-none ring-2 ring-blue-500 ring-offset-2;
}

button:focus {
    @apply outline-none ring-2 ring-blue-500 ring-offset-2;
}

/* Loading states */
.loading {
    @apply opacity-50 pointer-events-none;
}

.loading::after {
    content: '';
    @apply absolute inset-0 bg-white bg-opacity-50;
}
</style>

<script>
// Global variables
let currentView = "<?php echo $view; ?>";
let currentTable = "<?php echo ($view === 'instrument' ? 'instruments' : 'students'); ?>";
let currentStudentId = null;

// Initialize page functionality
document.addEventListener("DOMContentLoaded", function() {
    initializeAdvancedFeatures();
    setupEventListeners();
});

function initializeAdvancedFeatures() {
    setupSearchAutocomplete();
    initializeTooltips();
    setupKeyboardShortcuts();
    initializeTableSorting();
}

function setupEventListeners() {
    const filterForm = document.getElementById("filter-form");
    if (filterForm) {
        filterForm.addEventListener("change", function(e) {
            if (e.target.type === "select-one" || e.target.type === "date") {
                setTimeout(() => filterForm.submit(), 300);
            }
        });
    }
}

function setupSearchAutocomplete() {
    const searchInput = document.getElementById("search");
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener("input", function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (e.target.value.length >= 2) {
                    console.log("Search suggestions for:", e.target.value);
                }
            }, 300);
        });
    }
}

function setupKeyboardShortcuts() {
    document.addEventListener("keydown", function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === "a" && !e.target.matches("input, textarea")) {
            e.preventDefault();
            selectAll();
        }
        if (e.key === "Escape") {
            selectNone();
            closeAllModals();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === "f") {
            e.preventDefault();
            document.getElementById("search")?.focus();
        }
    });
}

function initializeTableSorting() {
    document.querySelectorAll("[data-sort]").forEach(header => {
        header.addEventListener("click", function() {
            const sortBy = this.dataset.sort;
            const currentSort = new URLSearchParams(window.location.search).get("sort");
            const currentOrder = new URLSearchParams(window.location.search).get("order") || "asc";
            
            let newOrder = "asc";
            if (currentSort === sortBy && currentOrder === "asc") {
                newOrder = "desc";
            }
            
            const url = new URL(window.location);
            url.searchParams.set("sort", sortBy);
            url.searchParams.set("order", newOrder);
            window.location.href = url.toString();
        });
    });
}

// Individual Actions
function viewStudentDetails(studentId, table = "students") {
    // Use the global function
    return window.viewStudentDetails(studentId, table);
}

function displayStudentDetails(student) {
    // Use the global function
    return window.displayStudentDetails(student);
}

function toggleFlag(studentId, table = "students") {
    const button = document.querySelector(`[data-flag-btn="${studentId}"]`);
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin text-sm"></i>';
    }
    
    fetch(window.location.href, {
        method: "POST",
        headers: { \"Content-Type\": \"application/x-www-form-urlencoded\" },
        body: 'action=toggle_flag&student_id=' + studentId + '&table=' + table
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateFlagButton(studentId, data.flagged);
            showToast(data.message, "success");
        } else {
            showToast(data.message, "error");
        }
    })
    .catch(error => {
        console.error(\"Error:\", error);
        showToast("Error updating flag status", "error");
    })
    .finally(() => {
        if (button) {
            button.innerHTML = '<i class="fas fa-flag text-sm"></i>';
        }
    });
}

function updateFlagButton(studentId, flagged) {
    const button = document.querySelector(`[data-flag-btn="${studentId}"]`);
    if (button) {
        button.title = flagged ? "Unflag" : "Flag";
        
        if (flagged) {
            button.classList.add("text-red-600", "dark:text-red-400", "bg-red-100", "dark:bg-red-900/50");
            button.classList.remove("text-gray-400", "dark:text-gray-500", "hover:bg-gray-100", "dark:hover:bg-gray-700");
        } else {
            button.classList.remove("text-red-600", "dark:text-red-400", "bg-red-100", "dark:bg-red-900/50");
            button.classList.add("text-gray-400", "dark:text-gray-500", "hover:bg-gray-100", "dark:hover:bg-gray-700");
        }
    }
}

function editCurrentStudent() {
    if (currentStudentId) {
        editStudent(currentStudentId, currentTable);
    } else {
        showToast("No student selected for editing", "warning");
    }
}

function editStudent(studentId, table = "students") {
    // Use the global function 
    return window.editStudent(studentId, table);
}

function deleteStudent(studentId, table = "students") {
    // Use the global function
    return window.deleteStudent(studentId, table);
}

// Utility Functions
function clearSearch() {
    document.getElementById("search").value = "";
    document.getElementById("filter-form").submit();
}

function applyQuickFilter(type) {
    const url = new URL(window.location);
    if (type === "flagged") {
        url.searchParams.set("status", "flagged");
    }
    window.location.href = url.toString();
}

function exportData() {
    const selectedColumns = [];
    document.querySelectorAll('.column-checkbox:checked').forEach(checkbox => {
        selectedColumns.push(checkbox.value);
    });
    
    // Construct export URL with current filters and selected columns
    const url = new URL(window.location);
    url.pathname = url.pathname.replace('students.php', 'export_students.php');
    if (selectedColumns.length > 0) {
        url.searchParams.set('columns', selectedColumns.join(','));
    }
    
    // Open export in new tab
    window.open(url.toString(), '_blank');
    showToast('Export started. Check your downloads folder.', 'info');
}


function closeStudentDetailsModal() {
    document.getElementById("student-details-modal").classList.add("hidden");
    currentStudentId = null; // Clear stored student ID
}

function printStudentDetails() {
    // Hide modal background and other UI elements for printing
    const modal = document.getElementById("student-details-modal");
    const originalClasses = modal.className;
    
    // Temporarily modify modal for printing
    modal.className = "fixed inset-0 bg-white overflow-y-auto h-full w-full z-50";
    
    // Print the modal content
    window.print();
    
    // Restore original modal classes
    setTimeout(() => {
        modal.className = originalClasses;
    }, 100);
}

function viewFullProfile() {
    if (currentStudentId) {
        // Determine the correct page based on current table
        // Open the full student profile in a new tab
        window.open("student_view.php?id=" + currentStudentId, "_blank");
    } else {
        showToast("No student selected", "warning");
    }
}

function showColumnCustomizer() {
    document.getElementById("column-customizer-modal").classList.remove("hidden");
    populateColumnOptions();
    updateSelectedCount();
}

function closeColumnCustomizer() {
    document.getElementById("column-customizer-modal").classList.add("hidden");
}

// Add event listeners for real-time updates
function setupColumnEventListeners() {
    document.addEventListener("change", function(e) {
        if (e.target.classList.contains("column-checkbox")) {
            updateSelectedCount();
        }
    });
}

function updateSelectedCount() {
    const selected = document.querySelectorAll(".column-checkbox:checked").length;
    const total = document.querySelectorAll(".column-checkbox").length;
    const visible = document.querySelectorAll(".column-checkbox:not([style*=\"none\"])").length;
    
    // Update main counter
    const countElement = document.getElementById("selected-count");
    if (countElement) {
        countElement.textContent = selected;
    }
    
    // Update statistics
    const totalElement = document.getElementById("total-columns-count");
    const selectedElement = document.getElementById("selected-columns-count");
    const visibleElement = document.getElementById("visible-columns-count");
    
    if (totalElement) totalElement.textContent = total;
    if (selectedElement) selectedElement.textContent = selected;
    if (visibleElement) visibleElement.textContent = visible;
}

function filterColumns() {
    const searchTerm = document.getElementById("column-search").value.toLowerCase();
    const labels = document.querySelectorAll("#column-options label");
    
    labels.forEach(label => {
        const text = label.textContent.toLowerCase();
        const shouldShow = text.includes(searchTerm);
        label.style.display = shouldShow ? "flex" : "none";
    });
    
    // Show/hide category headers based on visible columns
    const categories = document.querySelectorAll("[data-category]");
    categories.forEach(category => {
        const visibleColumns = category.querySelectorAll("label[style*=\"flex\"], label:not([style*=\"none\"])").length;
        const categoryHeader = category.previousElementSibling;
        if (categoryHeader && categoryHeader.querySelector("h4")) {
            categoryHeader.style.display = visibleColumns > 0 ? "block" : "none";
        }
        category.style.display = visibleColumns > 0 ? "grid" : "none";
    });
    
    // Update statistics
    updateSelectedCount();
}

function saveCurrentAsPreset() {
    const selectedColumns = [];
    document.querySelectorAll(".column-checkbox:checked").forEach(checkbox => {
        selectedColumns.push(checkbox.value);
    });
    
    if (selectedColumns.length === 0) {
        showToast("Please select at least one column", "warning");
        return;
    }
    
    const presetName = prompt("Enter a name for this column preset:");
    if (!presetName) return;
    
    // Save preset to localStorage
    const presets = JSON.parse(localStorage.getItem("columnPresets") || "{}");
    presets[currentView + "_" + presetName] = {
        columns: selectedColumns,
        created: new Date().toISOString(),
        view: currentView
    };
    localStorage.setItem("columnPresets", JSON.stringify(presets));
    
    showToast("Preset \"" + presetName + "\" saved successfully", "success");
}

// Initialize event listeners when page loads
document.addEventListener("DOMContentLoaded", function() {
    setupColumnEventListeners();
});

function populateColumnOptions() {
    const container = document.getElementById("column-options");
    
    // Complete list of all available database columns
    const availableColumns = <?= json_encode($all_fields) ?>;
    
    // Get current selected columns from URL or defaults
    const urlParams = new URLSearchParams(window.location.search);
    const currentColumns = urlParams.get('columns') ? urlParams.get('columns').split(',') : <?= json_encode($selected_fields) ?>;
    
    // Group columns by category for better organization
    const columnCategories = {
        'basic': {
            label: 'Basic Information',
            columns: ['photo_path', 'full_name', 'christian_name', 'gender', 'birth_date', 'current_grade', 'phone_number', 'created_at']
        },
        'education': {
            label: 'Education Details',
            columns: ['school_year_start', 'regular_school_name', 'regular_school_grade', 'education_level', 'field_of_study']
        },
        'location': {
            label: 'Location & Address',
            columns: ['sub_city', 'district', 'specific_area', 'house_number', 'living_with']
        },
        'emergency': {
            label: 'Emergency Contacts',
            columns: ['emergency_name', 'emergency_phone', 'emergency_alt_phone', 'emergency_address']
        },
        'spiritual': {
            label: 'Spiritual Information',
            columns: ['has_spiritual_father', 'spiritual_father_name', 'spiritual_father_phone', 'spiritual_father_church']
        },
        'family': {
            label: 'Family Information',
            columns: ['father_full_name', 'father_phone', 'father_occupation', 'mother_full_name', 'mother_phone', 'mother_occupation', 'guardian_full_name', 'guardian_phone', 'guardian_occupation']
        },
        'additional': {
            label: 'Additional Information',
            columns: ['special_interests', 'siblings_in_school', 'physical_disability', 'weak_side', 'transferred_from_other_school', 'came_from_other_religion']
        }
    };
    
    let html = `
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-600">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Column Categories</span>
                <div class="flex space-x-2">
                    <button type="button" onclick="selectAllColumns()" class="px-3 py-1 text-xs bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded hover:bg-primary-200 dark:hover:bg-primary-800 transition-colors">
                        Select All
                    </button>
                    <button type="button" onclick="deselectAllColumns()" class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        Deselect All
                    </button>
                </div>
            </div>
    `;
    
    Object.entries(columnCategories).forEach(([categoryKey, category]) => {
        const categoryColumns = category.columns.filter(col => availableColumns.hasOwnProperty(col));
        if (categoryColumns.length === 0) return;
        
        html += `
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">${category.label}</h4>
                    <button type="button" onclick="toggleCategoryColumns(\'${categoryKey}\')" class="text-xs text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-200">
                        Toggle All
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-2" data-category="${categoryKey}">
        `;
        
        categoryColumns.forEach(columnKey => {
            const checked = currentColumns.includes(columnKey) ? 'checked' : '';
            const label = availableColumns[columnKey] || columnKey;
            html += `
                <label class="flex items-center space-x-3 p-2 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors text-sm">
                    <input type="checkbox" value="${columnKey}" ${checked} class="column-checkbox rounded border-gray-300 text-primary-600 focus:ring-primary-500 text-sm">
                    <span class="text-gray-700 dark:text-gray-300">${label}</span>
                </label>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    });
    
    // Add any remaining columns that don't fit into categories
    const categorizedColumns = Object.values(columnCategories).flatMap(cat => cat.columns);
    const remainingColumns = Object.keys(availableColumns).filter(col => !categorizedColumns.includes(col));
    
    if (remainingColumns.length > 0) {
        html += `
            <div class="space-y-2">
                <h4 class="text-xs font-semibold text-gray-800 dark:text-gray-200">Other Fields</h4>
                <div class="grid grid-cols-1 gap-1">
        `;
        
        remainingColumns.forEach(columnKey => {
            const checked = currentColumns.includes(columnKey) ? 'checked' : '';
            const label = availableColumns[columnKey] || columnKey;
            html += `
                <label class="flex items-center space-x-3 p-2 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors text-sm">
                    <input type="checkbox" value="${columnKey}" ${checked} class="column-checkbox rounded border-gray-300 text-primary-600 focus:ring-primary-500 text-sm">
                    <span class="text-gray-700 dark:text-gray-300">${label}</span>
                </label>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    html += `
        </div>
    `;
    
    container.innerHTML = html;
    
    // Update statistics after populating
    setTimeout(() => {
        updateSelectedCount();
    }, 100);
}

function applyColumnSettings() {
    const selectedColumns = [];
    document.querySelectorAll('.column-checkbox:checked').forEach(checkbox => {
        selectedColumns.push(checkbox.value);
    });
    
    if (selectedColumns.length === 0) {
        showToast('Please select at least one column', 'warning');
        return;
    }
    
    // Save preferences to server
    const formData = new FormData();
    formData.append('view', currentView);
    formData.append('columns', selectedColumns.join(','));
    
    fetch('save_column_prefs.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect with new column selection
            const url = new URL(window.location);
            url.searchParams.set('columns', selectedColumns.join(','));
            window.location.href = url.toString();
        } else {
            showToast('Error saving column preferences', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error saving column preferences', 'error');
    });
}

// Additional column management functions
function selectAllColumns() {
    document.querySelectorAll('.column-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllColumns() {
    document.querySelectorAll('.column-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
}

function toggleCategoryColumns(category) {
    const categoryContainer = document.querySelector(`[data-category="${category}"]`);
    if (!categoryContainer) return;
    
    const checkboxes = categoryContainer.querySelectorAll('.column-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });
}

function resetToDefaults() {
    // Reset to default columns based on current view
    const defaultColumns = {
        'all': ['photo_path', 'full_name', 'gender', 'birth_date', 'current_grade', 'phone_number'],
        'youth': ['photo_path', 'full_name', 'gender', 'birth_date', 'current_grade', 'phone_number', 'field_of_study'],
        'under': ['photo_path', 'full_name', 'gender', 'birth_date', 'current_grade', 'sub_city', 'district', 'phone_number'],
        'instrument': ['photo_path', 'full_name', 'gender', 'instrument', 'phone_number', 'status']
    };
    
    const currentDefaults = defaultColumns[currentView] || defaultColumns['all'];
    
    document.querySelectorAll('.column-checkbox').forEach(checkbox => {
        checkbox.checked = currentDefaults.includes(checkbox.value);
    });
}

function saveAsPreset() {
    const selectedColumns = [];
    document.querySelectorAll(\'.column-checkbox:checked\').forEach(checkbox => {
        selectedColumns.push(checkbox.value);
    });
    
    if (selectedColumns.length === 0) {
        showToast(\'Please select at least one column\', \'warning\');
        return;
    }
    
    const presetName = prompt(\'Enter a name for this column preset:\');
    if (!presetName) return;
    
    // Save preset to localStorage for now (can be enhanced to save to database)
    const presets = JSON.parse(localStorage.getItem(\'columnPresets\') || \'{}\');
    presets[currentView + \'_\' + presetName] = selectedColumns;
    localStorage.setItem(\'columnPresets\', JSON.stringify(presets));
    
    showToast(`Preset "${presetName}" saved successfully`, \'success\');
}

function loadPreset() {
    const presets = JSON.parse(localStorage.getItem(\'columnPresets\') || \'{}\');
    const currentViewPresets = Object.keys(presets).filter(key => key.startsWith(currentView + \'_\'));
    
    if (currentViewPresets.length === 0) {
        showToast(\'No saved presets found\', \'info\');
        return;
    }
    
    const presetNames = currentViewPresets.map(key => key.replace(currentView + \'_\', \'\'));
    const selectedPreset = prompt(\'Available presets:\\n\' + presetNames.join(\'\\n\') + \'\\n\\nEnter preset name to load:\');
    
    if (!selectedPreset) return;
    
    const presetKey = currentView + \'_\' + selectedPreset;
    const presetColumns = presets[presetKey];
    
    if (!presetColumns) {
        showToast(\'Preset not found\', \'error\');
        return;
    }
    
    document.querySelectorAll(\'.column-checkbox\').forEach(checkbox => {
        checkbox.checked = presetColumns.includes(checkbox.value);
    });
    
    showToast(`Preset "${selectedPreset}" loaded successfully`, \'success\');
}

function closeModal(event) {
    if (event.target === event.currentTarget) {
        event.currentTarget.classList.add("hidden");
    }
}

function closeAllModals() {
    document.querySelectorAll(".modal").forEach(modal => {
        modal.classList.add("hidden");
    });
}

// Toast notification system
function showToast(message, type = "info", duration = 3000) {
    document.querySelectorAll(".toast").forEach(toast => toast.remove());
    
    const toast = document.createElement("div");
    toast.className = `toast fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300`;
    
    const colors = {
        success: "bg-green-500 text-white",
        error: "bg-red-500 text-white",
        warning: "bg-yellow-500 text-white",
        info: "bg-blue-500 text-white"
    };
    
    const icons = {
        success: "fa-check-circle",
        error: "fa-exclamation-circle",
        warning: "fa-exclamation-triangle",
        info: "fa-info-circle"
    };
    
    toast.className += " " + colors[type];
    toast.innerHTML = `<div class="flex items-center space-x-2"><i class="fas ${icons[type]}"></i><span>${message}</span></div>`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => { toast.classList.remove("translate-x-full"); }, 100);
    setTimeout(() => {
        toast.classList.add("translate-x-full");
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function initializeTooltips() {
    document.querySelectorAll("[title]").forEach(element => {
        element.addEventListener("mouseenter", showTooltip);
        element.addEventListener("mouseleave", hideTooltip);
    });
}

function showTooltip(event) {
    const tooltip = document.createElement("div");
    tooltip.className = "tooltip fixed z-50 px-2 py-1 text-xs text-white bg-gray-900 dark:bg-gray-700 rounded shadow-lg pointer-events-none";
    tooltip.textContent = event.target.title;
    
    event.target.dataset.originalTitle = event.target.title;
    event.target.removeAttribute("title");
    
    document.body.appendChild(tooltip);
    
    const rect = event.target.getBoundingClientRect();
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + "px";
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + "px";
}

function hideTooltip(event) {
    if (event.target.dataset.originalTitle) {
        event.target.title = event.target.dataset.originalTitle;
        delete event.target.dataset.originalTitle;
    }
    document.querySelectorAll(".tooltip").forEach(tooltip => tooltip.remove());
};

// Edit Drawer Functions for Table Edit Icons
function openEditDrawer(studentData, table) {
    // Create drawer if it doesn\'t exist
    let drawer = document.getElementById(\'editDrawer\');
    if (!drawer) {
        createEditDrawer();
        drawer = document.getElementById(\'editDrawer\');
    }
    
    // Populate drawer with student data
    populateDrawerData(studentData, table);
    
    // Show drawer
    const panel = drawer.querySelector(\'.max-w-md\');
    drawer.classList.remove(\'translate-x-full\');
    setTimeout(() => {
        panel.classList.remove(\'translate-x-full\');
    }, 10);
    
    // Prevent body scroll
    document.body.style.overflow = \'hidden\';
}

function closeEditDrawer() {
    const drawer = document.getElementById(\'editDrawer\');
    if (!drawer) return;
    
    const panel = drawer.querySelector(\'.max-w-md\');
    panel.classList.add(\'translate-x-full\');
    setTimeout(() => {
        drawer.classList.add(\'translate-x-full\');
        document.body.style.overflow = \'auto\';
    }, 300);
}

function createEditDrawer() {
    const drawerHTML = `
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
                                <p class="text-emerald-100 text-sm" id="drawerStudentName">Student Name</p>
                            </div>
                        </div>
                        <button onclick="closeEditDrawer()" class="bg-white/20 hover:bg-white/30 p-2 rounded-lg transition-colors duration-200">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- Drawer Content -->
                <div class="h-full overflow-y-auto pb-20">
                    <!-- Photo Edit Section -->
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-100 p-6 border-b">
                        <div class="text-center">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center justify-center">
                                <i class="fas fa-camera text-blue-600 mr-2"></i>
                                Profile Photo
                            </h3>
                            
                            <!-- Current Photo Display -->
                            <div class="relative inline-block mb-4">
                                <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-white shadow-lg">
                                    <div id="drawerCurrentPhoto" class="w-full h-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 text-2xl"></i>
                                    </div>
                                </div>
                                <div class="absolute -bottom-1 -right-1 bg-blue-600 text-white p-1.5 rounded-full shadow-lg">
                                    <i class="fas fa-camera text-xs"></i>
                                </div>
                            </div>
                            
                            <!-- Photo Actions -->
                            <div class="space-y-3">
                                <button onclick="triggerDrawerPhotoUpload()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload New Photo</span>
                                </button>
                                <div class="flex space-x-2">
                                    <button onclick="captureDrawerPhoto()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-2 px-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                        <i class="fas fa-camera"></i>
                                        <span class="text-sm">Take Photo</span>
                                    </button>
                                    <button onclick="removeDrawerPhoto()" id="removePhotoBtn" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                        <i class="fas fa-trash"></i>
                                        <span class="text-sm">Remove</span>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Hidden File Input -->
                            <input type="file" id="drawerPhotoInput" accept="image/*" class="hidden" onchange="handleDrawerPhotoUpload(event)">
                        </div>
                    </div>

                    <!-- Edit Categories -->
                    <div class="p-6 space-y-4" id="drawerEditSections">
                        <!-- Sections will be populated dynamically -->
                    </div>

                    <!-- Quick Actions -->
                    <div class="p-6 border-t border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <button onclick="openFullEditorFromDrawer()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                <i class="fas fa-edit"></i>
                                <span>Open Full Editor</span>
                            </button>
                            <button onclick="duplicateStudentFromDrawer()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                <i class="fas fa-copy"></i>
                                <span>Duplicate Student</span>
                            </button>
                            <button onclick="exportStudentFromDrawer()" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                <i class="fas fa-download"></i>
                                <span>Export Data</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', drawerHTML);
    
    // Close drawer when clicking backdrop
    document.getElementById(\'editDrawer\').addEventListener(\'click\', function(e) {
        if (e.target === this) {
            closeEditDrawer();
        }
    });
}

function populateDrawerData(studentData, table) {
    // Update student name
    const nameElement = document.getElementById(\'drawerStudentName\');
    if (nameElement) {
        nameElement.textContent = studentData.full_name || \'Unknown Student\';
    }
    
    // Update photo
    const photoElement = document.getElementById(\'drawerCurrentPhoto\');
    if (photoElement && studentData.photo_path) {
        photoElement.innerHTML = \'<img src="\' + studentData.photo_path + \'" alt="Student photo" class="w-full h-full object-cover">\'; 
    }
    
    // Create basic edit sections
    createBasicDrawerSections(studentData, table);
}

function createBasicDrawerSections(studentData, table) {
    const container = document.getElementById(\'drawerEditSections\');
    if (!container) return;
    
    let html = \'<div class="text-center py-8">\' +
               \'<i class="fas fa-edit text-4xl text-gray-400 mb-4"></i>\' +
               \'<p class="text-gray-600 mb-4">Quick edit coming soon!</p>\' +
               \'<p class="text-sm text-gray-500">Use \'Open Full Editor\' below for complete editing functionality.</p>\' +
               \'</div>\';
    
    container.innerHTML = html;
}

// Quick action functions
function openFullEditorFromDrawer() {
    if (window.currentEditingStudent) {
        window.location.href = \'student_edit.php?id=\' + (window.currentEditingStudent.id || window.currentEditingStudent.registration_id);
    }
}

function duplicateStudentFromDrawer() {
    if (window.currentEditingStudent && confirm('Create a new student record based on this student\'s information?')) {
        window.location.href = \'student_add.php?template=\' + (window.currentEditingStudent.id || window.currentEditingStudent.registration_id);
    }
}

function exportStudentFromDrawer() {
    if (window.currentEditingStudent) {
        window.open(\'api/export_student.php?id=\' + (window.currentEditingStudent.id || window.currentEditingStudent.registration_id) + \'&format=pdf\', \'_blank\');
    }
}

// Photo functions for drawer
function triggerDrawerPhotoUpload() {
    document.getElementById(\'drawerPhotoInput\').click();
}

function handleDrawerPhotoUpload(event) {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            showToast(\'File size too large. Please choose a file under 5MB.\', \'error\');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const currentPhoto = document.getElementById(\'drawerCurrentPhoto\');
            currentPhoto.innerHTML = \'<img src="\' + e.target.result + \'" alt="New photo" class="w-full h-full object-cover">\'; 
            showToast(\'Photo updated! Use \'Open Full Editor\' to save changes.\', \'info\');
        };
        reader.readAsDataURL(file);
    }
}

function captureDrawerPhoto() {
    showToast(\'Camera feature coming soon!\', \'info\');
}

function removeDrawerPhoto() {
    if (confirm(\'Remove the current photo? Use \'Open Full Editor\' to save changes.\')) {
        const currentPhoto = document.getElementById(\'drawerCurrentPhoto\');
        currentPhoto.innerHTML = \'<div class="w-full h-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center"><i class="fas fa-user text-gray-600 text-2xl"></i></div>\';
        showToast(\'Photo removed! Use \'Open Full Editor\' to save changes.\', \'info\');
    }
}

// Keyboard shortcuts for drawer
document.addEventListener(\'keydown\', function(e) {
    if (e.key === \'Escape\') {
        closeEditDrawer();
    }
});
</script>
EOD;

// Render the complete page using the admin layout
echo renderAdminLayout($title . ' - Student Management System', $content, $page_script);
?>