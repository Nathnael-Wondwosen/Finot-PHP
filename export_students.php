<?php
session_start();
require 'config.php';
require 'includes/students_helpers.php';
requireAdminLogin();

// Get parameters from URL
$view = $_GET['view'] ?? 'all';
$search = $_GET['search'] ?? '';
$instrument_type = $_GET['instrument_type'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$show_all = $_GET['show_all'] ?? false;
$custom_columns = $_GET['columns'] ?? '';

// Set appropriate filename based on view and filters
$filename_parts = ['students_export'];
if ($view !== 'all') $filename_parts[] = $view;
if ($search) $filename_parts[] = 'search';
$filename = implode('_', $filename_parts) . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel compatibility
fwrite($out, "\xEF\xBB\xBF");

// Get all students with the same logic as students.php
$all = fetch_all_students_with_parents($pdo, 1, 10000);

if ($view === 'instrument') {
    // Handle instrument view export
    $params = [];
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
    
    if ($status) {
        switch ($status) {
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
    
    // Define columns for instrument export
    $columns = [
        'id', 'full_name', 'christian_name', 'gender', 'instrument', 'birth_date', 'current_grade', 
        'phone_number', 'created_at', 'flagged', 'linked_status'
    ];
} else {
    // Handle regular student views
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
    
    // Define default columns for student export
    $columns = [
        'id','full_name','christian_name','gender','birth_date','current_grade','school_year_start',
        'regular_school_name','phone_number','has_spiritual_father','spiritual_father_name','spiritual_father_phone','spiritual_father_church',
        'sub_city','district','specific_area','house_number','living_with','special_interests','siblings_in_school','physical_disability','weak_side',
        'transferred_from_other_school','came_from_other_religion','created_at',
        'father_full_name','father_phone','father_occupation',
        'mother_full_name','mother_phone','mother_occupation'
    ];
}

// Use custom columns if provided
if ($custom_columns) {
    $columns = explode(',', $custom_columns);
}

// Write CSV header
fputcsv($out, $columns);

// Write data rows
foreach ($students as $student) {
    $record = [];
    
    // Handle instrument view data transformation
    if ($view === 'instrument') {
        // Transform instrument data similar to students.php logic
        $original_full_name = $student['full_name'];
        $original_christian_name = $student['christian_name'];
        $original_gender = $student['gender'];
        $original_phone = $student['phone_number'];
        
        // Use instrument registration data first, fallback to student data
        $student['full_name'] = !empty($original_full_name) ? $original_full_name : ($student['s_full_name'] ?? '-');
        $student['christian_name'] = !empty($original_christian_name) ? $original_christian_name : ($student['s_christian_name'] ?? '-');
        $student['gender'] = !empty($original_gender) ? $original_gender : ($student['s_gender'] ?? '-');
        $student['phone_number'] = !empty($original_phone) ? $original_phone : ($student['s_phone_number'] ?? '-');
        
        // Format birth date from Ethiopian calendar
        if ($student['birth_year_et'] && $student['birth_month_et'] && $student['birth_day_et']) {
            $student['birth_date'] = $student['birth_year_et'] . '-' . 
                                     str_pad($student['birth_month_et'], 2, '0', STR_PAD_LEFT) . '-' . 
                                     str_pad($student['birth_day_et'], 2, '0', STR_PAD_LEFT);
        } else {
            $student['birth_date'] = $student['s_birth_date'] ?? 'N/A';
        }
        
        // Add linked status
        $student['linked_status'] = isset($student['student_id']) && !empty($student['student_id']) ? 'Linked' : 'Unlinked';
    }
    
    // Build record based on selected columns
    foreach ($columns as $col) {
        $val = isset($student[$col]) ? $student[$col] : '';
        
        // Handle special formatting
        if ($col === 'gender' && !empty($val)) {
            $val = strtolower($val) === 'male' ? 'Male' : 'Female';
        } elseif ($col === 'flagged') {
            $val = !empty($val) ? 'Yes' : 'No';
        } elseif ($col === 'has_spiritual_father') {
            $val = !empty($val) ? ucfirst($val) : 'No';
        } elseif (strpos($col, '_date') !== false || $col === 'created_at') {
            if (!empty($val) && $val !== '0000-00-00') {
                $val = date('Y-m-d', strtotime($val));
            } else {
                $val = '';
            }
        }
        
        // Normalize newlines and trim
        if (is_string($val)) {
            $val = str_replace(["\r\n","\r","\n"], ' ', trim($val));
        }
        
        $record[] = $val;
    }
    
    fputcsv($out, $record);
}

fclose($out);
exit;
