<?php
session_start();
require 'config.php';
require 'includes/students_helpers.php';
require 'includes/admin_layout.php';
require 'includes/mobile_table.php';
require 'includes/security_helpers.php';
require 'includes/cache_manager.php';

// Performance monitoring
if (isset($_GET['perf_monitor']) && $_SESSION['admin_id'] == 1) {
    require 'performance_monitor.php';
    $perfMonitor = new PerformanceMonitor();
} else {
    $perfMonitor = null;
}

// Require admin authentication
requireAdminLogin();

$admin_id = $_SESSION['admin_id'] ?? 1;

// Rate limiting for AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !checkRateLimit('ajax_request', 30, 60)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_grade_options':
            try {
                $stmt = $pdo->query("SELECT DISTINCT current_grade FROM students WHERE current_grade IS NOT NULL AND current_grade <> '' ORDER BY current_grade ASC");
                $grades = array_values(array_filter(array_map(function($row){ return trim((string)$row['current_grade']); }, $stmt->fetchAll(PDO::FETCH_ASSOC))));
                // Ensure "new" exists if used in system
                if (!in_array('new', $grades, true)) { $grades = array_merge(['new'], $grades); }
                echo json_encode(['success' => true, 'grades' => $grades]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching grades: ' . $e->getMessage()]);
            }
            exit;
        case 'delete_student':
            $student_id = (int)$_POST['student_id'];
            $table = $_POST['table'] ?? 'students';
            
            try {
                if ($table === 'instruments') {
                    $stmt = $pdo->prepare("DELETE FROM instrument_registrations WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                }
                $result = $stmt->execute([$student_id]);
                echo json_encode(['success' => $result, 'message' => $result ? 'Record deleted successfully' : 'Failed to delete record']);
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
                    'message' => $flagged ? 'Record flagged' : 'Record unflagged'
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
                    // For instrument registrations, get the registration record with optional linked student data
                    $stmt = $pdo->prepare("SELECT ir.*, s.id AS student_id, s.full_name AS s_full_name, s.christian_name AS s_christian_name, s.gender AS s_gender, s.current_grade AS s_current_grade, s.photo_path AS s_photo_path, s.phone_number AS s_phone_number, s.birth_date AS s_birth_date FROM instrument_registrations ir LEFT JOIN students s ON LOWER(TRIM(ir.full_name)) = LOWER(TRIM(s.full_name)) WHERE ir.id = ?");
                    $stmt->execute([$student_id]);
                    $record = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($record) {
                        // Format the record for compatibility with student detail view
                        $student = [
                            'id' => $record['id'],
                            'registration_id' => $record['id'], // For instrument actions
                            'full_name' => $record['full_name'],
                            'christian_name' => $record['christian_name'],
                            'gender' => $record['gender'],
                            'phone_number' => $record['phone_number'],
                            'instrument' => $record['instrument'],
                            'person_photo_path' => $record['person_photo_path'],
                            'photo_path' => $record['person_photo_path'], // Alias for compatibility
                            'birth_year_et' => $record['birth_year_et'],
                            'birth_month_et' => $record['birth_month_et'],
                            'birth_day_et' => $record['birth_day_et'],
                            'created_at' => $record['created_at'],
                            'flagged' => $record['flagged'],
                            'student_id' => $record['student_id'], // Linked student ID
                            'sub_city' => $record['sub_city'],
                            'district' => $record['district'],
                            'specific_area' => $record['specific_area'],
                            'house_number' => $record['house_number'],
                            'emergency_name' => $record['emergency_name'],
                            'emergency_phone' => $record['emergency_phone'],
                            'emergency_alt_phone' => $record['emergency_alt_phone'],
                            'emergency_address' => $record['emergency_address'],
                            'has_spiritual_father' => $record['has_spiritual_father'],
                            'spiritual_father_name' => $record['spiritual_father_name'],
                            'spiritual_father_phone' => $record['spiritual_father_phone'],
                            'spiritual_father_church' => $record['spiritual_father_church'],
                            // Linked student data (if available)
                            's_full_name' => $record['s_full_name'],
                            's_christian_name' => $record['s_christian_name'],
                            's_gender' => $record['s_gender'],
                            's_current_grade' => $record['s_current_grade'],
                            's_photo_path' => $record['s_photo_path'],
                            's_phone_number' => $record['s_phone_number'],
                            's_birth_date' => $record['s_birth_date']
                        ];
                        echo json_encode(['success' => true, 'student' => $student]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Instrument registration not found']);
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                    $stmt->execute([$student_id]);
                    
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($student) {
                        echo json_encode(['success' => true, 'student' => $student]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Student not found']);
                    }
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_student':
            $student_id = (int)$_POST['student_id'];
            $table = $_POST['table'] ?? 'students';
            
            try {
                if ($table === 'instruments') {
                    // Handle instrument registration updates
                    $allowed_instrument_fields = [
                        'full_name', 'christian_name', 'gender', 'birth_year_et', 'birth_month_et', 'birth_day_et',
                        'phone_number', 'instrument', 'has_spiritual_father', 'spiritual_father_name', 
                        'spiritual_father_phone', 'spiritual_father_church', 'sub_city', 'district', 
                        'specific_area', 'house_number', 'emergency_name', 'emergency_phone', 
                        'emergency_alt_phone', 'emergency_address'
                    ];
                    
                    $update_fields = [];
                    $update_values = [];
                    
                    foreach ($allowed_instrument_fields as $field) {
                        if (isset($_POST[$field])) {
                            $update_fields[] = "$field = ?";
                            $update_values[] = $_POST[$field];
                        }
                    }
                    
                    if (empty($update_fields)) {
                        echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
                        exit;
                    }
                    
                    // Check for duplicate instrument registration (excluding current record)
                    if (isset($_POST['full_name']) && isset($_POST['instrument'])) {
                        $stmt = $pdo->prepare("SELECT id FROM instrument_registrations WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?)) AND instrument = ? AND id != ?");
                        $stmt->execute([$_POST['full_name'], $_POST['instrument'], $student_id]);
                        if ($stmt->fetch()) {
                            echo json_encode(['success' => false, 'message' => 'A registration with this name and instrument already exists']);
                            exit;
                        }
                    }
                    
                    // Perform update on instrument_registrations table
                    $update_values[] = $student_id;
                    $sql = "UPDATE instrument_registrations SET " . implode(', ', $update_fields) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute($update_values);
                    
                    if ($result) {
                        // Fetch updated record
                        $stmt = $pdo->prepare("SELECT * FROM instrument_registrations WHERE id = ?");
                        $stmt->execute([$student_id]);
                        $updated_record = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Instrument registration updated successfully',
                            'student' => $updated_record
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update instrument registration']);
                    }
                } else {
                    // Define allowed fields for updating students table
                    $allowed_student_fields = [
                        'full_name', 'christian_name', 'gender', 'birth_date', 'phone_number', 'current_grade',
                        'sub_city', 'district', 'specific_area', 'house_number', 'living_with',
                        'regular_school_name', 'regular_school_grade', 'education_level', 'field_of_study', 'school_year_start',
                        'emergency_name', 'emergency_phone', 'emergency_alt_phone', 'emergency_address',
                        'has_spiritual_father', 'spiritual_father_name', 'spiritual_father_phone', 'spiritual_father_church',
                        'special_interests', 'siblings_in_school', 'physical_disability', 'weak_side',
                        'transferred_from_other_school', 'came_from_other_religion'
                    ];
                    
                    // Define parent fields that need special handling
                    $parent_fields = [
                        'father_full_name', 'father_phone', 'father_occupation',
                        'mother_full_name', 'mother_phone', 'mother_occupation',
                        'guardian_full_name', 'guardian_phone', 'guardian_occupation'
                    ];
                    
                    // Build update query for students table
                    $update_fields = [];
                    $update_values = [];
                    
                    foreach ($allowed_student_fields as $field) {
                        if (isset($_POST[$field])) {
                            $update_fields[] = "$field = ?";
                            $update_values[] = $_POST[$field];
                        }
                    }
                    
                    if (empty($update_fields) && empty(array_intersect(array_keys($_POST), $parent_fields))) {
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
                    
                    // Begin transaction for consistency
                    $pdo->beginTransaction();
                    
                    // Perform update on students table
                    if (!empty($update_fields)) {
                        $update_values[] = $student_id;
                        $sql = "UPDATE students SET " . implode(', ', $update_fields) . " WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($update_values);
                    }
                    
                    // Handle parent information updates
                    $parent_types = ['father', 'mother', 'guardian'];
                    foreach ($parent_types as $parent_type) {
                        $full_name_key = $parent_type . '_full_name';
                        $phone_key = $parent_type . '_phone';
                        $occupation_key = $parent_type . '_occupation';
                        
                        // Check if any parent fields are being updated
                        if (isset($_POST[$full_name_key]) || isset($_POST[$phone_key]) || isset($_POST[$occupation_key])) {
                            // Get current parent record
                            $stmt = $pdo->prepare("SELECT id FROM parents WHERE student_id = ? AND parent_type = ?");
                            $stmt->execute([$student_id, $parent_type]);
                            $parent_record = $stmt->fetch();
                            
                            if ($parent_record) {
                                // Update existing parent record
                                $parent_update_fields = [];
                                $parent_update_values = [];
                                
                                if (isset($_POST[$full_name_key])) {
                                    $parent_update_fields[] = "full_name = ?";
                                    $parent_update_values[] = $_POST[$full_name_key];
                                }
                                
                                if (isset($_POST[$phone_key])) {
                                    $parent_update_fields[] = "phone_number = ?";
                                    $parent_update_values[] = $_POST[$phone_key];
                                }
                                
                                if (isset($_POST[$occupation_key])) {
                                    $parent_update_fields[] = "occupation = ?";
                                    $parent_update_values[] = $_POST[$occupation_key];
                                }
                                
                                if (!empty($parent_update_fields)) {
                                    $parent_update_values[] = $parent_record['id'];
                                    $sql = "UPDATE parents SET " . implode(', ', $parent_update_fields) . " WHERE id = ?";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute($parent_update_values);
                                }
                            } else {
                                // Insert new parent record if it doesn't exist
                                $full_name = $_POST[$full_name_key] ?? '';
                                $phone = $_POST[$phone_key] ?? '';
                                $occupation = $_POST[$occupation_key] ?? '';
                                
                                // Only insert if at least one field has a value
                                if (!empty($full_name) || !empty($phone) || !empty($occupation)) {
                                    $sql = "INSERT INTO parents (student_id, parent_type, full_name, phone_number, occupation) VALUES (?, ?, ?, ?, ?)";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute([$student_id, $parent_type, $full_name, $phone, $occupation]);
                                }
                            }
                        }
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    // Fetch updated student data
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $updated_student = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Student information updated successfully',
                        'student' => $updated_student
                    ]);
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                if ($table !== 'instruments') {
                    $pdo->rollback();
                }
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Main page logic
// Implement proper pagination
if ($perfMonitor) $perfMonitor->checkpoint('start_processing');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
// Determine Show All and page size BEFORE initial fetch
$show_all_init = false;
if (isset($_GET['show_all'])) {
    $val = strtolower((string)$_GET['show_all']);
    $show_all_init = ($val === '' || $val === 'true' || $val === '1' || $val === 'yes' || $val === 'on');
}

// Optional per_page param for paged mode
$allowed_page_sizes = [10, 25, 50, 100];
$per_page_param = (int)($_GET['per_page'] ?? 25);
if (!in_array($per_page_param, $allowed_page_sizes, true)) { $per_page_param = 25; }

if ($show_all_init) {
    // Fetch all rows for initial dataset
    $page = 1;
    $total_all = (int)get_total_students_count($pdo);
    $per_page = $total_all;
} else {
    $per_page = $per_page_param; // Paged mode
}

$all = fetch_all_students_with_parents($pdo, $page, $per_page);
if ($perfMonitor) $perfMonitor->checkpoint('data_fetched');

$view = $_GET['view'] ?? 'all'; // all | youth | under | instrument
if (!in_array($view, ['all','youth','under','instrument'], true)) $view = 'all';

// Search functionality
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Compute total filtered count early for header badge (respects view, search, date filters)
$total_records_count = get_filtered_students_count($pdo, $view, $search, $date_from, $date_to);

$show_all_init = false;
if (isset($_GET['show_all'])) {
    $val = strtolower((string)$_GET['show_all']);
    $show_all_init = ($val === 'true' || $val === '1' || $val === 'yes');
}

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
            // Use the student's birth_date directly (already in Ethiopian format)
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
                    <?= number_format($total_records_count) ?> <?= ($total_records_count == 1) ? 'record' : 'records' ?>
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
                <a href="registration.php" 
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
    
    <!-- Real-time Search Bar -->
    <div class="mt-4">
        <div class="relative max-w-md">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400 text-sm"></i>
            </div>
            <input type="text" id="studentSearch" 
                   class="block w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm" 
                   placeholder="Search students by name, phone, grade, location...">
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                <span id="searchCount" class="text-xs text-gray-400 hidden"></span>
                <button type="button" id="clearSearch" class="ml-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hidden" onclick="clearStudentSearch()">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
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
            return '<img src="' . htmlspecialchars($photo_path) . '" alt="Photo" loading="lazy" class="w-8 h-8 rounded-full object-cover ring-1 ring-gray-200 dark:ring-gray-700">';
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
        if (!empty($row['is_duplicate']) && !empty($row['total_registrations']) && $row['total_registrations'] > 1) {
            $badges[] = '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-400 rounded"><i class="fas fa-copy text-xs mr-0.5"></i>' . $row['total_registrations'] . ' Inst</span>';
        } elseif (!empty($row['is_duplicate'])) {
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
        // Handle multiple instruments for duplicate students
        if (!empty($row['instrument_group']) && is_array($row['instrument_group']) && count($row['instrument_group']) > 1) {
            $instruments = [
                'begena' => ['name' => 'በገና', 'icon' => 'fa-guitar', 'color' => 'text-blue-600 dark:text-blue-400'],
                'masenqo' => ['name' => 'መሰንቆ', 'icon' => 'fa-violin', 'color' => 'text-green-600 dark:text-green-400'],
                'kebero' => ['name' => 'ከበሮ', 'icon' => 'fa-drum', 'color' => 'text-orange-600 dark:text-orange-400'],
                'krar' => ['name' => 'ክራር', 'icon' => 'fa-guitar', 'color' => 'text-purple-600 dark:text-purple-400']
            ];
            
            $instrumentBadges = [];
            foreach ($row['instrument_group'] as $inst) {
                $instrument = $instruments[$inst] ?? ['name' => htmlspecialchars($inst), 'icon' => 'fa-music', 'color' => 'text-gray-600 dark:text-gray-400'];
                $instrumentBadges[] = '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded mr-1"><i class="fas ' . $instrument['icon'] . ' ' . $instrument['color'] . ' text-xs mr-1"></i>' . $instrument['name'] . '</span>';
            }
            
            return '<div class="flex flex-wrap gap-0.5">' . implode('', $instrumentBadges) . '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 rounded ml-1">' . count($row['instrument_group']) . ' inst</span></div>';
        }
        
        // Handle comma-separated multiple instruments (new functionality)
        if (!empty($value) && strpos($value, ',') !== false) {
            $instruments = [
                'begena' => ['name' => 'በገና', 'icon' => 'fa-guitar', 'color' => 'text-blue-600 dark:text-blue-400'],
                'masenqo' => ['name' => 'መሰንቆ', 'icon' => 'fa-violin', 'color' => 'text-green-600 dark:text-green-400'],
                'kebero' => ['name' => 'ከበሮ', 'icon' => 'fa-drum', 'color' => 'text-orange-600 dark:text-orange-400'],
                'krar' => ['name' => 'ክራር', 'icon' => 'fa-guitar', 'color' => 'text-purple-600 dark:text-purple-400']
            ];
            
            $instrumentList = array_map('trim', explode(',', $value));
            $instrumentBadges = [];
            
            foreach ($instrumentList as $inst) {
                if (!empty($inst)) {
                    $instrument = $instruments[$inst] ?? ['name' => htmlspecialchars($inst), 'icon' => 'fa-music', 'color' => 'text-gray-600 dark:text-gray-400'];
                    $instrumentBadges[] = '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded mr-1"><i class="fas ' . $instrument['icon'] . ' ' . $instrument['color'] . ' text-xs mr-1"></i>' . $instrument['name'] . '</span>';
                }
            }
            
            if (count($instrumentBadges) > 0) {
                return '<div class="flex flex-wrap gap-0.5">' . implode('', $instrumentBadges) . '<span class="inline-flex items-center px-1 py-0.5 text-xs font-medium bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 rounded ml-1">' . count($instrumentBadges) . ' inst</span></div>';
            }
        }
        
        // Handle single instrument
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
        // Ethiopic month names
        $amharicEthiopicMonths = [
            1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ህዳር', 4 => 'ታህሳስ', 5 => 'ጥር', 6 => 'የካቲት',
            7 => 'መጋቢት', 8 => 'ሚያዝያ', 9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
        ];
        $escape = function($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); };
        
        // 1) Prefer explicit Ethiopic components if present in row (instrument registration)
        if (!empty($row['birth_year_et']) && !empty($row['birth_month_et']) && !empty($row['birth_day_et'])) {
            $ey = (int)$row['birth_year_et'];
            $em = (int)$row['birth_month_et'];
            $ed = (int)$row['birth_day_et'];
            $monthName = $amharicEthiopicMonths[$em] ?? '';
            $out = trim($escape($ed) . ' ' . $monthName . ' ' . $escape($ey));
            return '<span class="text-xs text-gray-900 dark:text-white">' . $out . '</span>';
        }
        
        // 2) For student records, use the birth_date directly (already in Ethiopian format)
        if (!empty($value) && $value !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            // Parse the Ethiopian date format (YYYY-MM-DD)
            [$ey, $em, $ed] = array_map('intval', explode('-', $value));
            if ($ey && $em && $ed) {
                $monthName = $amharicEthiopicMonths[$em] ?? '';
                $out = trim($escape($ed) . ' ' . $monthName . ' ' . $escape($ey));
                return '<span class="text-xs text-gray-900 dark:text-white">' . $out . '</span>';
            }
        }
        
        // 3) Fallback: show raw or dash
        if (empty($value) || $value === '0000-00-00') return '-';
        return '<span class="text-xs text-gray-900 dark:text-white">' . $escape($value) . '</span>';
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

// Pagination - Improved with database-level pagination
$total_records = get_filtered_students_count($pdo, $view, $search, $date_from, $date_to);
$show_all = false;
if (isset($_GET['show_all'])) {
    $val = strtolower((string)$_GET['show_all']);
    $show_all = ($val === '' || $val === 'true' || $val === '1' || $val === 'yes' || $val === 'on');
}
$page = (int)($_GET['page'] ?? 1);
if ($show_all) {
    $page = 1;
}
$per_page = $show_all ? $total_records : $table_config['page_size'];
$total_pages = $show_all ? 1 : ceil($total_records / $table_config['page_size']);
// Fallback: if per_page equals total, consider show_all true for UI
if (!$show_all && $total_records > 0 && $per_page >= $total_records) {
    $show_all = true;
    $page = 1;
    $total_pages = 1;
}

// For show_all, we still need to limit to prevent memory issues
if ($show_all && $total_records > 1000) {
    $per_page_init = get_total_students_count($pdo);
    $per_page = (int)$per_page_init;
    $total_pages = 1;
    $page = 1;
}

$offset = ($page - 1) * $per_page;
// For non-instrument views, fetch the current page from DB with filters (search, dates, age group)
if ($view === 'instrument') {
    $paginated_students = $filtered_students;
} else {
    $paginated_students = fetch_students_with_parents_filtered($pdo, $view, $search, $date_from, $date_to, $page, $per_page);
}

// Map options for renderMobileTable
$table_options = $table_config;
$table_options['show_pagination'] = !$show_all;
$table_options['per_page'] = $per_page;
$table_options['current_page'] = $page;
$table_options['total_records'] = $total_records;

echo renderMobileTable(
    $paginated_students,
    $filtered_headers,
    $table_options,
    $render_functions
);
?>

<!-- Pagination / Controls -->
<?php if ($show_all || $total_pages > 1): ?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-4 space-y-2 sm:space-y-0">
    <div class="flex flex-col sm:flex-row sm:items-center gap-2">
        <div class="text-xs text-gray-700 dark:text-gray-300">
            <?php if ($show_all): ?>
                Showing all <?= number_format($total_records) ?> results
            <?php else: ?>
                Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $per_page, $total_records)) ?> of <?= number_format($total_records) ?> results
            <?php endif; ?>
        </div>
        
        <!-- Show All / Show Paginated Toggle -->
        <div class="flex items-center gap-2">
            <?php if ($show_all): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['show_all' => null, 'page' => 1])) ?>" 
                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                    <i class="fas fa-list mr-1 text-xs"></i> Show Pages
                </a>
            <?php else: ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['show_all' => 'true'])) ?>" 
                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                    <i class="fas fa-expand mr-1 text-xs"></i> Show All (<?= number_format($total_records) ?>)
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!$show_all && $total_pages > 1): ?>
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
    <?php endif; ?>
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
<script src="js/enhanced-edit-drawer.js?v=2"></script>
<script src="js/instrument-edit-drawer.js?v=1"></script>
<script src="js/column-customizer.js"></script>
<script src="js/ethiopian-calendar-filter.js"></script>
<script>
// Prevent duplicate main script logic and set globals
window.__STUDENTS_BOOTSTRAP__ = true;
// Initialize column customizer data from PHP
initializeColumnData(' . json_encode($all_fields) . ', ' . json_encode($selected_fields) . ');

// Set current view for JavaScript
window.currentView = "' . htmlspecialchars($view) . '";
window.currentTable = "' . ($view === 'instrument' ? 'instruments' : 'students') . '";

// Global helpers for legacy inline handlers
window.viewStudentDetails = function(studentId, table) {
    table = table || (window.currentTable ? window.currentTable : "students");
    // Store context for print and edit actions
    window.currentStudentId = studentId;
    window.currentTable = table;
    const modal = document.getElementById("student-details-modal");
    const content = document.getElementById("modal-content");
    if (!modal || !content) {
        console.error("Modal elements not found");
        return;
    }
    content.innerHTML = "<div class=\"flex items-center justify-center py-12\"><div class=\"animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600\"></div></div>";
    modal.classList.remove("hidden");
    showLoadingOverlay();
    
    // Try to get from cache first
    const cacheKey = `student_${studentId}_${table}`;
    const cachedData = sessionStorage.getItem(cacheKey);
    if (cachedData) {
        try {
            const parsedData = JSON.parse(cachedData);
            if (Date.now() - parsedData.timestamp < 300000) { // 5 minutes cache
                content.innerHTML = parsedData.html;
                hideLoadingOverlay();
                return;
            }
        } catch (e) {
            // If parsing fails, continue with fetch
        }
    }
    
    const url = new URL(window.location.origin + window.location.pathname.replace("students.php","") + "api/student_details_view.php");
    url.searchParams.set("id", studentId);
    url.searchParams.set("table", table === "instruments" ? "instruments" : "students");
    fetch(url.toString(), { 
        method: "GET", 
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin" // Ensure cookies are sent
    })
    .then(r => r.text())
    .then(html => { 
        content.innerHTML = html;
        // Cache the result
        try {
            sessionStorage.setItem(cacheKey, JSON.stringify({
                html: html,
                timestamp: Date.now()
            }));
        } catch (e) {
            // Ignore cache errors
        }
    })
    .catch(() => { content.innerHTML = "<div class=\"text-center py-8\"><i class=\"fas fa-exclamation-triangle text-red-500 text-3xl mb-4\"></i><p class=\"text-gray-600 dark:text-gray-400\">Unable to load student details</p></div>"; })
    .finally(() => hideLoadingOverlay());
};

// Comprehensive student details view for non-instrument tables
window.viewComprehensiveStudentDetails = function(studentId, table) {
    table = table || (window.currentTable ? window.currentTable : "students");
    // Store context for print and edit actions
    window.currentStudentId = studentId;
    window.currentTable = table;
    const modal = document.getElementById("student-details-modal");
    const content = document.getElementById("modal-content");
    if (!modal || !content) {
        console.error("Modal elements not found");
        return;
    }
    content.innerHTML = "<div class=\"flex items-center justify-center py-12\"><div class=\"animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600\"></div></div>";
    modal.classList.remove("hidden");
    showLoadingOverlay();
    const url = new URL(window.location.origin + window.location.pathname.replace("students.php","") + "api/comprehensive_student_details.php");
    url.searchParams.set("id", studentId);
    fetch(url.toString(), { 
        method: "GET", 
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin" // Ensure cookies are sent
    })
    .then(r => r.text())
    .then(html => { content.innerHTML = html; })
    .catch(() => { content.innerHTML = "<div class=\"text-center py-8\"><i class=\"fas fa-exclamation-triangle text-red-500 text-3xl mb-4\"></i><p class=\"text-gray-600 dark:text-gray-400\">Unable to load comprehensive student details</p></div>"; })
    .finally(() => hideLoadingOverlay());
};

window.editStudent = function(studentId, table){
    table = table || (window.currentTable||"students");
    fetch("student_modal.php?id=" + encodeURIComponent(studentId))
        .then(r => r.json())
        .then(sdata => { 
            if (sdata && sdata.id && window.openEditDrawer) { 
                window.openEditDrawer(sdata, table); 
            } else { 
                alert("Unable to load editor"); 
            } 
        })
        .catch(() => alert("Network error"));
};

// Specialized function for editing instrument registrations
window.editInstrumentRegistration = function(studentId, table){
    table = table || "instruments";
    // First fetch the instrument registration data
    const url = new URL(window.location.origin + window.location.pathname.replace("students.php","") + "api/student_details_view.php");
    url.searchParams.set("id", studentId);
    url.searchParams.set("table", "instruments");
    
    showLoadingOverlay();
    
    fetch(url.toString(), { 
        method: "GET", 
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin"
    })
    .then(r => {
        if (!r.ok) throw new Error("Failed to fetch instrument data");
        return r.text();
    })
    .then(html => {
        // Parse the HTML response to extract instrument data
        // For now, we\'ll fetch the raw data via a separate endpoint
        return fetch("student_modal.php?id=" + encodeURIComponent(studentId) + "&table=instruments");
    })
    .then(r => r.json())
    .then(instrumentData => { 
        if (instrumentData && instrumentData.id && window.openInstrumentEditDrawer) { 
            window.openInstrumentEditDrawer(instrumentData, table); 
        } else { 
            alert("Unable to load instrument editor"); 
        } 
    })
    .catch(error => {
        console.error("Error loading instrument data:", error);
        alert("Network error loading instrument editor");
    })
    .finally(() => hideLoadingOverlay());
};

window.deleteStudent = function(studentId, table){
    table = table || (window.currentTable||"students");
    if (!confirm("Are you sure you want to delete this student?")) return;
    showLoadingOverlay();
    fetch(window.location.href, {
        method: "POST", 
        headers: {"Content-Type": "application/x-www-form-urlencoded"}, 
        body: "action=delete_student&student_id=" + encodeURIComponent(studentId) + "&table=" + encodeURIComponent(table)
    })
    .then(r => r.json())
    .then(data => { 
        if (data && data.success) { 
            location.reload(); 
        } else { 
            alert((data&&data.message)||"Delete failed"); 
        } 
    })
    .catch(() => alert("Network error"))
    .finally(() => hideLoadingOverlay());
};

// UX helpers: loading overlay
(function(){
    function ensureOverlay(){
        if (!document.getElementById("global-loading-overlay")) {
            const overlay = document.createElement("div");
            overlay.id = "global-loading-overlay";
            overlay.className = "hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30";
            overlay.innerHTML = "<div class=\"bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 px-3 py-2 rounded-lg shadow flex items-center space-x-2\"><span class=\"animate-spin inline-block w-4 h-4 border-2 border-b-transparent border-primary-600 rounded-full\"></span><span class=\"text-xs\">Loading...</span></div>";
            document.body.appendChild(overlay);
        }
    }
    document.addEventListener("DOMContentLoaded", ensureOverlay);
})();

window.showLoadingOverlay = function(){
    const el = document.getElementById("global-loading-overlay");
    if (el) el.classList.remove("hidden");
};
window.hideLoadingOverlay = function(){
    const el = document.getElementById("global-loading-overlay");
    if (el) el.classList.add("hidden");
};

// Modal helpers
window.closeStudentDetailsModal = function(){
    const modal = document.getElementById("student-details-modal");
    if (modal) modal.classList.add("hidden");
};

window.closeModal = function(event){
    if (event && event.target === event.currentTarget) {
        event.currentTarget.classList.add("hidden");
    }
};

// Debounced search submit
document.addEventListener("DOMContentLoaded", function(){
    const searchInput = document.getElementById("search");
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener("input", function(){
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function(){
                const form = document.getElementById("filter-form");
                if (form) form.submit();
            }, 400);
        });
    }
    
    // Real-time student search functionality
    const studentSearchInput = document.getElementById("studentSearch");
    const searchCount = document.getElementById("searchCount");
    const clearSearchBtn = document.getElementById("clearSearch");
    
    if (studentSearchInput) {
        studentSearchInput.addEventListener("input", function() {
            const query = studentSearchInput.value.trim().toLowerCase();
            const tableBody = document.querySelector("#students-table tbody") || document.querySelector("table tbody");
            
            if (!tableBody) return;
            
            const rows = tableBody.querySelectorAll("tr");
            let visibleCount = 0;
            let totalRows = 0;
            
            rows.forEach(row => {
                // Skip empty or non-data rows
                const cells = row.querySelectorAll("td");
                if (cells.length === 0 || row.querySelector("td[colspan]")) {
                    return;
                }
                
                totalRows++;
                let match = false;
                
                if (query === "") {
                    match = true;
                } else {
                    // Search through all text content in the row
                    const rowText = row.textContent.toLowerCase();
                    if (rowText.includes(query)) {
                        match = true;
                    } else {
                        // Also search through data attributes and titles
                        cells.forEach(cell => {
                            const cellText = cell.textContent.toLowerCase();
                            const title = cell.getAttribute("title");
                            if (cellText.includes(query) || (title && title.toLowerCase().includes(query))) {
                                match = true;
                            }
                        });
                    }
                }
                
                // Show/hide row
                if (match) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });
            
            // Update search count and clear button visibility
            if (query === "") {
                searchCount.classList.add("hidden");
                clearSearchBtn.classList.add("hidden");
            } else {
                searchCount.textContent = `${visibleCount}/${totalRows}`;
                searchCount.classList.remove("hidden");
                clearSearchBtn.classList.remove("hidden");
            }
            
            // Show "No results found" message if needed
            if (totalRows > 0 && visibleCount === 0 && query !== "") {
                let noResultsRow = tableBody.querySelector(".no-results-row");
                if (!noResultsRow) {
                    noResultsRow = document.createElement("tr");
                    noResultsRow.className = "no-results-row";
                    const colCount = tableBody.querySelector("tr")?.querySelectorAll("td").length || 1;
                    noResultsRow.innerHTML = `<td colspan="${colCount}" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400"><div class="flex flex-col items-center"><i class="fas fa-search text-2xl mb-2 opacity-50"></i><p class="font-medium">No students found</p><p class="text-sm">Try adjusting your search terms</p></div></td>`;
                    tableBody.appendChild(noResultsRow);
                }
                noResultsRow.style.display = "";
            } else {
                const noResultsRow = tableBody.querySelector(".no-results-row");
                if (noResultsRow) {
                    noResultsRow.style.display = "none";
                }
            }
        });
        
        // Focus search on Ctrl+F or Cmd+F
        document.addEventListener("keydown", function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === "f") {
                e.preventDefault();
                studentSearchInput.focus();
                studentSearchInput.select();
            }
        });
    }
});

// Open full editor page for comprehensive editing
window.openFullEditor = function(){
    const id = window.currentStudentId;
    if (!id) { if (window.showToast) showToast("No student selected", "warning"); return; }
    window.location.href = "student_edit.php?id=" + encodeURIComponent(id);
};

// Clear search function
window.clearStudentSearch = function(){
    const searchInput = document.getElementById("studentSearch");
    const searchCount = document.getElementById("searchCount");
    const clearSearchBtn = document.getElementById("clearSearch");
    
    if (searchInput) {
        searchInput.value = "";
        // Trigger input event to reset the search
        searchInput.dispatchEvent(new Event("input"));
        searchInput.focus();
    }
};

// Export function
window.exportData = function(){
    const selectedColumns = [];
    document.querySelectorAll(".column-checkbox:checked").forEach(function(checkbox) {
        selectedColumns.push(checkbox.value);
    });
    
    // Construct export URL with current filters and selected columns
    const url = new URL(window.location);
    url.pathname = url.pathname.replace("students.php", "export_students.php");
    
    // Preserve current view and search parameters
    const currentParams = new URLSearchParams(window.location.search);
    ["view", "search", "instrument_type", "status", "date_from", "date_to", "show_all"].forEach(function(param) {
        if (currentParams.get(param)) {
            url.searchParams.set(param, currentParams.get(param));
        }
    });
    
    // Add selected columns if any
    if (selectedColumns.length > 0) {
        url.searchParams.set("columns", selectedColumns.join(","));
    }
    
    // Open export in new tab
    window.open(url.toString(), "_blank");
    
    // Show toast notification
    if (typeof showToast === "function") {
        showToast("Export started. Check your downloads folder.", "info");
    } else {
        console.log("Export started for", selectedColumns.length, "columns");
        
        // Create simple toast notification
        const toast = document.createElement("div");
        toast.className = "fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-md shadow-lg z-50 transition-opacity duration-300";
        toast.textContent = "Export started. Check your downloads folder.";
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(function() {
            toast.style.opacity = "0";
            setTimeout(function() {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
};

// Simple toast notification function
window.showToast = function(message, type) {
    type = type || "info";
    const colors = {
        "info": "bg-blue-500",
        "success": "bg-green-500", 
        "warning": "bg-yellow-500",
        "error": "bg-red-500"
    };
    
    const toast = document.createElement("div");
    toast.className = "fixed top-4 right-4 " + (colors[type] || colors.info) + " text-white px-4 py-2 rounded-md shadow-lg z-50 transition-opacity duration-300";
    toast.textContent = message;
    document.body.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(function() {
        toast.style.opacity = "0";
        setTimeout(function() {
            if (toast.parentNode) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 3000);
};

// Column customizer function placeholder
window.showColumnCustomizer = window.showColumnCustomizer || function() {
    alert("Column customizer functionality is being loaded. Please try again in a moment.");
};

</script>
<script>
function printStudentDetails(){
    const table = window.currentTable || "students";
    const id = window.currentStudentId;
    if (!id) { if (window.showToast) showToast("No student selected", "warning"); return; }
    const url = new URL(window.location.origin + window.location.pathname.replace("students.php", "") + "api/student_details_print.php");
    url.searchParams.set("id", id);
    url.searchParams.set("table", table === "instruments" ? "instruments" : "students");
    window.open(url.toString(), "_blank");
}
</script>';



// Render the complete page using the admin layout
echo renderAdminLayout($title . ' - Student Management System', $content, $page_script);
?>