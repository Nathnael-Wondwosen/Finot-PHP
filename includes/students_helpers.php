<?php
// includes/students_helpers.php - Optimized for Production
require_once __DIR__ . '/cache_manager.php';

// Cache TTL constants
const CACHE_TTL_STUDENTS = 300; // 5 minutes for student lists
const CACHE_TTL_COUNTS = 600;   // 10 minutes for counts
const CACHE_TTL_DASHBOARD = 180; // 3 minutes for dashboard stats

if (!function_exists('fetch_all_students_with_parents')) {
    function fetch_all_students_with_parents(PDO $pdo, $page = 1, $per_page = 50) {
        $cacheKey = "students_page_{$page}_{$per_page}";
        
        return cache_remember($cacheKey, function() use ($pdo, $page, $per_page) {
            $offset = ($page - 1) * $per_page;
            $sql = "SELECT s.*, 
                f.full_name AS father_full_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
                m.full_name AS mother_full_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation,
                g.full_name AS guardian_full_name, g.phone_number AS guardian_phone, g.occupation AS guardian_occupation
            FROM students s
            LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
            LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
            LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
            ORDER BY s.created_at DESC
            LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, CACHE_TTL_STUDENTS, 'students');
    }
}

// DB-level filtered fetch with pagination and parent joins for student views - OPTIMIZED
if (!function_exists('fetch_students_with_parents_filtered')) {
    function fetch_students_with_parents_filtered(PDO $pdo, string $view = 'all', string $search = '', string $date_from = '', string $date_to = '', int $page = 1, int $per_page = 25) {
        // Skip cache for search queries
        if (!empty($search)) {
            return fetch_students_with_parents_filtered_query($pdo, $view, $search, $date_from, $date_to, $page, $per_page);
        }
        
        $cacheKey = "students_filtered_{$view}_{$date_from}_{$date_to}_{$page}_{$per_page}";
        
        return cache_remember($cacheKey, function() use ($pdo, $view, $search, $date_from, $date_to, $page, $per_page) {
            return fetch_students_with_parents_filtered_query($pdo, $view, $search, $date_from, $date_to, $page, $per_page);
        }, CACHE_TTL_STUDENTS, 'students');
    }
    
    function fetch_students_with_parents_filtered_query(PDO $pdo, string $view, string $search, string $date_from, string $date_to, int $page, int $per_page) {
        $offset = ($page - 1) * $per_page;
        
        // Optimized query - select only needed columns instead of s.*
        $sql = "SELECT s.id, s.photo_path, s.full_name, s.christian_name, s.gender, s.birth_date, 
                s.current_grade, s.school_year_start, s.phone_number, s.education_level, 
                s.field_of_study, s.sub_city, s.district, s.created_at,
            f.full_name AS father_full_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
            m.full_name AS mother_full_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation,
            g.full_name AS guardian_full_name, g.phone_number AS guardian_phone, g.occupation AS guardian_occupation
        FROM students s
        LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
        LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
        LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'";

        $where_conditions = [];
        $params = [];

        if ($search) {
            $where_conditions[] = "(s.full_name LIKE ? OR s.christian_name LIKE ? OR s.phone_number LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($date_from) {
            $where_conditions[] = "s.created_at >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $where_conditions[] = "s.created_at <= ?";
            $params[] = $date_to . ' 23:59:59';
        }

        // Age group condition - optimized
        $currentEY = current_ethiopian_year();
        if ($view === 'youth') {
            $where_conditions[] = "(s.birth_date IS NOT NULL AND s.birth_date <> '0000-00-00' AND (? - CAST(SUBSTRING(s.birth_date,1,4) AS UNSIGNED)) >= 17)";
            $params[] = $currentEY;
        } elseif ($view === 'under') {
            $where_conditions[] = "(s.birth_date IS NOT NULL AND s.birth_date <> '0000-00-00' AND (? - CAST(SUBSTRING(s.birth_date,1,4) AS UNSIGNED)) < 17)";
            $params[] = $currentEY;
        }

        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }

        $sql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";

        $stmt = $pdo->prepare($sql);
        $bindIndex = 1;
        foreach ($params as $val) {
            $stmt->bindValue($bindIndex++, $val);
        }
        $stmt->bindValue($bindIndex++, (int)$per_page, PDO::PARAM_INT);
        $stmt->bindValue($bindIndex++, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('get_total_students_count')) {
    function get_total_students_count(PDO $pdo) {
        return cache_remember('total_students_count', function() use ($pdo) {
            $sql = "SELECT COUNT(*) FROM students";
            return (int) $pdo->query($sql)->fetchColumn();
        }, CACHE_TTL_COUNTS, 'counts');
    }
}

if (!function_exists('get_filtered_students_count')) {
    function get_filtered_students_count(PDO $pdo, $view = 'all', $search = '', $date_from = '', $date_to = '') {
        if ($view === 'instrument') {
            $sql = "SELECT COUNT(*) FROM instrument_registrations ir 
                    LEFT JOIN students s ON LOWER(TRIM(ir.full_name)) = LOWER(TRIM(s.full_name))";
            $where_conditions = [];
            $params = [];
            
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
            
            if (!empty($where_conditions)) {
                $sql .= " WHERE " . implode(' AND ', $where_conditions);
            }
        } else {
            $sql = "SELECT COUNT(*) FROM students s";
            $where_conditions = [];
            $params = [];
            
            if ($search) {
                $where_conditions[] = "(s.full_name LIKE ? OR s.christian_name LIKE ? OR s.phone_number LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if ($date_from || $date_to) {
                if ($date_from) {
                    $where_conditions[] = "s.created_at >= ?";
                    $params[] = $date_from;
                }
                
                if ($date_to) {
                    $where_conditions[] = "s.created_at <= ?";
                    $params[] = $date_to . ' 23:59:59';
                }
            }

            // Age group filter (youth: >=17, under: <17) using Ethiopian year from birth_date (YYYY-MM-DD)
            $currentEY = current_ethiopian_year();
            if ($view === 'youth') {
                $where_conditions[] = "(s.birth_date IS NOT NULL AND s.birth_date <> '0000-00-00' AND (? - CAST(SUBSTRING(s.birth_date,1,4) AS UNSIGNED)) >= 17)";
                $params[] = $currentEY;
            } elseif ($view === 'under') {
                $where_conditions[] = "(s.birth_date IS NOT NULL AND s.birth_date <> '0000-00-00' AND (? - CAST(SUBSTRING(s.birth_date,1,4) AS UNSIGNED)) < 17)";
                $params[] = $currentEY;
            }
            
            if (!empty($where_conditions)) {
                $sql .= " WHERE " . implode(' AND ', $where_conditions);
            }
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}

if (!function_exists('current_ethiopian_year')) {
    function current_ethiopian_year() {
        $today = new DateTime();
        $gy = (int)$today->format('Y');
        $gm = (int)$today->format('m');
        $gd = (int)$today->format('d');
        $ey = $gy - 8;
        if ($gm > 9 || ($gm == 9 && $gd >= 11)) {
            $ey = $gy - 7;
        }
        return $ey;
    }
}

if (!function_exists('ethiopian_age_from_string')) {
    function ethiopian_age_from_string($eth_yyyy_mm_dd) {
        if (!$eth_yyyy_mm_dd) return null;
        $parts = explode('-', $eth_yyyy_mm_dd);
        if (count($parts) !== 3) return null;
        $by = (int)$parts[0];
        if ($by <= 0) return null;
        $currentEy = current_ethiopian_year();
        return $currentEy - $by;
    }
}

if (!function_exists('filter_students_by_age_group')) {
    function filter_students_by_age_group(array $students, string $group) {
        return array_values(array_filter($students, function($s) use ($group) {
            $age = ethiopian_age_from_string($s['birth_date'] ?? '');
            if ($age === null) return false;
            if ($group === 'youth') return $age >= 17;
            if ($group === 'under') return $age < 17;
            return true;
        }));
    }
}

if (!function_exists('safe')) {
    function safe($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('compute_completeness_status')) {
    /**
     * Determine completeness level and reason list.
     * Returns array: [status: 'red'|'yellow'|'green', missingCore: array, missingRecommended: array]
     */
    function compute_completeness_status(array $student) {
        $coreFields = [
            'photo_path', 'phone_number', 'birth_date',
            'sub_city', 'district', 'specific_area', 'house_number'
        ];
        $recommendedFields = [
            'full_name', 'christian_name', 'gender', 'current_grade',
            'education_level', 'field_of_study',
            'emergency_phone', 'emergency_name',
            'father_phone', 'mother_phone', 'guardian_phone'
        ];
        $missingCore = [];
        foreach ($coreFields as $f) {
            $v = isset($student[$f]) ? trim((string)$student[$f]) : '';
            if ($v === '' || $v === '0') $missingCore[] = $f;
        }
        $missingRecommended = [];
        foreach ($recommendedFields as $f) {
            $v = isset($student[$f]) ? trim((string)$student[$f]) : '';
            if ($v === '' || $v === '0') $missingRecommended[] = $f;
        }
        if (count($missingCore) > 0) return ['red', $missingCore, $missingRecommended];
        if (count($missingRecommended) > 0) return ['yellow', [], $missingRecommended];
        return ['green', [], []];
    }
}

if (!function_exists('get_all_student_fields')) {
    function get_all_student_fields() {
        return [
            'photo_path' => 'Photo',
            'full_name' => 'Full Name',
            'christian_name' => 'Christian Name',
            'gender' => 'Gender',
            'birth_date' => 'Birth Date',
            'current_grade' => 'Current Grade',
            'school_year_start' => 'School Year Start',
            'regular_school_name' => 'Regular School Name',
            'regular_school_grade' => 'Regular School Grade',
            'phone_number' => 'Phone Number',
            'education_level' => 'Education Level',
            'field_of_study' => 'Field of Study',
            'emergency_name' => 'Emergency Name',
            'emergency_phone' => 'Emergency Phone',
            'emergency_alt_phone' => 'Emergency Alt Phone',
            'emergency_address' => 'Emergency Address',
            'has_spiritual_father' => 'Has Spiritual Father',
            'spiritual_father_name' => 'Spiritual Father Name',
            'spiritual_father_phone' => 'Spiritual Father Phone',
            'spiritual_father_church' => 'Spiritual Father Church',
            'sub_city' => 'Sub City',
            'district' => 'District',
            'specific_area' => 'Specific Area',
            'house_number' => 'House Number',
            'living_with' => 'Living With',
            'special_interests' => 'Special Interests',
            'siblings_in_school' => 'Siblings In School',
            'physical_disability' => 'Physical Disability',
            'weak_side' => 'Weak Side',
            'transferred_from_other_school' => 'Transferred From Other School',
            'came_from_other_religion' => 'Came From Other Religion',
            'created_at' => 'Registered',
            'father_full_name' => 'Father Name',
            'father_phone' => 'Father Phone',
            'father_occupation' => 'Father Occupation',
            'mother_full_name' => 'Mother Name',
            'mother_phone' => 'Mother Phone',
            'mother_occupation' => 'Mother Occupation',
            'guardian_full_name' => 'Guardian Name',
            'guardian_phone' => 'Guardian Phone',
            'guardian_occupation' => 'Guardian Occupation',
        ];
    }
}

if (!function_exists('handlePhotoUpload')) {
    /**
     * Handle photo upload for students
     * 
     * @param array $file - The $_FILES array element for the photo
     * @param int $student_id - The student ID for naming
     * @return array - Result array with success status, path, and message
     */
    function handlePhotoUpload($file, $student_id) {
        try {
            // Validate file upload
            if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'File upload error', 'path' => ''];
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $detected_type = finfo_file($file_info, $file['tmp_name']);
            finfo_close($file_info);
            
            if (!in_array($detected_type, $allowed_types)) {
                return ['success' => false, 'message' => 'Invalid file type. Only JPEG and PNG images are allowed.', 'path' => ''];
            }
            
            // Validate file size (max 5MB)
            $max_size = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $max_size) {
                return ['success' => false, 'message' => 'File size too large. Maximum size is 5MB.', 'path' => ''];
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = __DIR__ . '/../uploads/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    return ['success' => false, 'message' => 'Could not create upload directory.', 'path' => ''];
                }
            }
            
            // Generate unique filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (empty($extension)) {
                $extension = ($detected_type === 'image/png') ? 'png' : 'jpg';
            }
            
            $filename = 'student_' . $student_id . '_' . uniqid() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            $relative_path = 'uploads/' . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return ['success' => false, 'message' => 'Failed to save uploaded file.', 'path' => ''];
            }
            
            // Verify the uploaded file
            if (!file_exists($filepath)) {
                return ['success' => false, 'message' => 'Upload verification failed.', 'path' => ''];
            }
            
            return [
                'success' => true, 
                'message' => 'Photo uploaded successfully.', 
                'path' => $relative_path,
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Upload error: ' . $e->getMessage(), 'path' => ''];
        }
    }
}


