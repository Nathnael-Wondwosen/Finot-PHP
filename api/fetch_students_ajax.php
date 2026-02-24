<?php
/**
 * AJAX endpoint for fetching students data
 * Improves responsiveness by loading data asynchronously
 */

session_start();
require_once '../config.php';
require_once '../includes/students_helpers.php';
require_once '../includes/cache.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$view = isset($_GET['view']) ? $_GET['view'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;

// Validate view parameter
if (!in_array($view, ['all', 'youth', 'under', 'instrument'])) {
    $view = 'all';
}

// Create cache key
$cacheKey = "students_data_{$view}_{$page}_{$per_page}_" . md5($search);
$cacheTTL = 60; // Cache for 1 minute

// Try to get from cache first
$cachedData = cache_get($cacheKey, $cacheTTL);
if ($cachedData !== null) {
    echo json_encode([
        'success' => true,
        'data' => $cachedData['data'],
        'pagination' => $cachedData['pagination'],
        'from_cache' => true
    ]);
    exit;
}

try {
    if ($view === 'instrument') {
        // Handle instrument view
        $offset = ($page - 1) * $per_page;
        
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
                LEFT JOIN students s ON LOWER(TRIM(ir.full_name)) = LOWER(TRIM(s.full_name))
                ORDER BY ir.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $countStmt = $pdo->query("SELECT COUNT(*) FROM instrument_registrations");
        $totalRecords = $countStmt->fetchColumn();
    } else {
        // Handle student views
        $students = fetch_all_students_with_parents($pdo, $page, $per_page);
        
        // Get total count for pagination
        $totalRecords = get_total_students_count($pdo);
    }
    
    $totalPages = ceil($totalRecords / $per_page);
    
    $pagination = [
        'current_page' => $page,
        'per_page' => $per_page,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1,
        'next_page' => $page < $totalPages ? $page + 1 : null,
        'prev_page' => $page > 1 ? $page - 1 : null
    ];
    
    $responseData = [
        'data' => $students,
        'pagination' => $pagination
    ];
    
    // Cache the response
    cache_set($cacheKey, $responseData);
    
    echo json_encode([
        'success' => true,
        'data' => $responseData['data'],
        'pagination' => $responseData['pagination'],
        'from_cache' => false
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching data: ' . $e->getMessage()
    ]);
}
?>