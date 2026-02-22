<?php
// Clean output buffer and suppress any previous output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1); // Log errors instead

// Define API context to prevent header conflicts
define('API_CONTEXT', true);

require_once __DIR__ . '/../config.php';

// Clear any output from config.php
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Check if PDO connection exists
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $filters = $input ?? [];
    
    // Debug logging
    error_log("Filter API Debug - Received filters: " . json_encode($filters));
    
    // Build the base query
    $query = "
        SELECT DISTINCT
            s.id,
            s.full_name,
            s.christian_name,
            s.gender,
            s.birth_date,
            s.current_grade,
            s.sub_city,
            s.living_with,
            s.physical_disability,
            s.has_spiritual_father,
            s.phone_number,
            s.emergency_phone,
            GROUP_CONCAT(DISTINCT ir.instrument) as instruments
        FROM students s
        LEFT JOIN instrument_registrations ir ON s.id = ir.student_id
        WHERE 1=1
    ";
    
    $params = [];
    $conditions = [];
    
    // Apply filters - be more flexible with string comparisons
    if (!empty($filters['grade']) && trim($filters['grade']) !== '') {
        $conditions[] = "s.current_grade = ?";
        $params[] = trim($filters['grade']);
    }
    
    if (!empty($filters['gender']) && trim($filters['gender']) !== '') {
        $conditions[] = "LOWER(s.gender) = LOWER(?)";
        $params[] = trim($filters['gender']);
    }
    
    if (!empty($filters['birthMonth']) && trim($filters['birthMonth']) !== '') {
        $conditions[] = "MONTH(s.birth_date) = ?";
        $params[] = (int)$filters['birthMonth'];
    }
    
    if (!empty($filters['instrument']) && trim($filters['instrument']) !== '') {
        $conditions[] = "ir.instrument LIKE ?";
        $params[] = '%' . trim($filters['instrument']) . '%';
    }
    
    if (!empty($filters['spiritualFather']) && trim($filters['spiritualFather']) !== '') {
        if ($filters['spiritualFather'] === 'yes') {
            $conditions[] = "s.has_spiritual_father IN ('own', 'family')";
        } elseif ($filters['spiritualFather'] === 'no') {
            $conditions[] = "(s.has_spiritual_father NOT IN ('own', 'family') OR s.has_spiritual_father IS NULL OR s.has_spiritual_father = '')";
        }
    }
    
    if (!empty($filters['subCity']) && trim($filters['subCity']) !== '') {
        $conditions[] = "s.sub_city LIKE ?";
        $params[] = '%' . trim($filters['subCity']) . '%';
    }
    
    if (!empty($filters['livingWith']) && trim($filters['livingWith']) !== '') {
        $conditions[] = "s.living_with = ?";
        $params[] = trim($filters['livingWith']);
    }
    
    if (!empty($filters['disability']) && trim($filters['disability']) !== '') {
        if ($filters['disability'] === 'yes') {
            $conditions[] = "s.physical_disability IS NOT NULL AND s.physical_disability != '' AND s.physical_disability != 'የለም' AND s.physical_disability != 'None'";
        } elseif ($filters['disability'] === 'no') {
            $conditions[] = "(s.physical_disability IS NULL OR s.physical_disability = '' OR s.physical_disability = 'የለም' OR s.physical_disability = 'None')";
        }
    }
    
    // Add conditions to query
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    $query .= " GROUP BY s.id ORDER BY s.full_name";
    
    // Debug the final query
    error_log("Filter API Debug - Final query: " . $query);
    error_log("Filter API Debug - Parameters: " . json_encode($params));
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Filter API Debug - Found " . count($students) . " students");
    
    // Format the data
    $formattedStudents = [];
    foreach ($students as $student) {
        // Format birth date
        $birthDateFormatted = 'N/A';
        if ($student['birth_date']) {
            $date = new DateTime($student['birth_date']);
            $ethiopianMonths = [
                1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሳስ',
                5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
                9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
            ];
            $month = (int)$date->format('m');
            $monthName = $ethiopianMonths[$month] ?? $month;
            $birthDateFormatted = $date->format('j') . ' ' . $monthName . ' ' . $date->format('Y');
        }
        
        // Parse instruments
        $instruments = [];
        if ($student['instruments']) {
            $instruments = array_filter(explode(',', $student['instruments']));
        }
        
        // Check spiritual father status
        $hasSpiritual = in_array($student['has_spiritual_father'], ['own', 'family']);
        
        // Check disability status
        $hasDisability = !empty($student['physical_disability']) && 
                        $student['physical_disability'] !== 'የለም' && 
                        $student['physical_disability'] !== 'None';
        
        $formattedStudents[] = [
            'id' => $student['id'],
            'full_name' => $student['full_name'],
            'christian_name' => $student['christian_name'],
            'gender' => $student['gender'],
            'birth_date' => $student['birth_date'],
            'birth_date_formatted' => $birthDateFormatted,
            'current_grade' => $student['current_grade'],
            'sub_city' => $student['sub_city'],
            'living_with' => $student['living_with'],
            'physical_disability' => $student['physical_disability'],
            'phone_number' => $student['phone_number'],
            'emergency_phone' => $student['emergency_phone'],
            'has_spiritual_father' => $hasSpiritual,
            'has_disability' => $hasDisability,
            'instruments' => $instruments
        ];
    }
    
    echo json_encode([
        'success' => true,
        'students' => $formattedStudents,
        'total' => count($formattedStudents),
        'filters_applied' => array_filter($filters),
        'query_info' => [
            'total_conditions' => count($conditions),
            'has_filters' => !empty($conditions),
            'raw_count' => count($students)
        ]
    ]);
    
} catch (PDOException $e) {
    // Clear any output buffer
    ob_clean();
    $error_msg = 'Database error: ' . $e->getMessage();
    error_log("Filter API PDO Error: " . $error_msg);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $error_msg
    ]);
} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    $error_msg = 'Server error: ' . $e->getMessage();
    error_log("Filter API General Error: " . $error_msg);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $error_msg
    ]);
}

// Ensure clean output
ob_end_flush();
?>