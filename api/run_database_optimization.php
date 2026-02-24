<?php
/**
 * AJAX handler for database optimization
 */

require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin login required.']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Optimization queries
$optimizationQueries = [
    // Add indexes to students table
    "ALTER TABLE students ADD INDEX idx_full_name (full_name)",
    "ALTER TABLE students ADD INDEX idx_christian_name (christian_name)",
    "ALTER TABLE students ADD INDEX idx_birth_date (birth_date)",
    "ALTER TABLE students ADD INDEX idx_current_grade (current_grade)",
    "ALTER TABLE students ADD INDEX idx_phone_number (phone_number)",
    "ALTER TABLE students ADD INDEX idx_created_at (created_at)",
    "ALTER TABLE students ADD INDEX idx_flagged (flagged)",
    "ALTER TABLE students ADD INDEX idx_sub_city (sub_city)",
    "ALTER TABLE students ADD INDEX idx_district (district)",

    // New: indexes supporting allocation and data quality flows
    "ALTER TABLE students ADD INDEX idx_is_new_registration (is_new_registration)",
    "ALTER TABLE students ADD INDEX idx_school_year_start (school_year_start)",
    "ALTER TABLE students ADD INDEX idx_new_created (is_new_registration, created_at)",

    // Optional: normalized name for faster duplicate detection (may fail on older MySQL)
    "ALTER TABLE students ADD COLUMN normalized_name VARCHAR(255) GENERATED ALWAYS AS (LOWER(TRIM(full_name))) STORED",
    "ALTER TABLE students ADD INDEX idx_students_normalized_name (normalized_name)",

    // Add indexes to instrument_registrations table
    "ALTER TABLE instrument_registrations ADD INDEX idx_full_name (full_name)",
    "ALTER TABLE instrument_registrations ADD INDEX idx_instrument (instrument)",
    "ALTER TABLE instrument_registrations ADD INDEX idx_created_at (created_at)",
    "ALTER TABLE instrument_registrations ADD INDEX idx_flagged (flagged)",
    "ALTER TABLE instrument_registrations ADD INDEX idx_birth_year_et (birth_year_et)",
    "ALTER TABLE instrument_registrations ADD INDEX idx_phone_number (phone_number)",

    // Add indexes to parents table
    "ALTER TABLE parents ADD INDEX idx_student_id (student_id)",
    "ALTER TABLE parents ADD INDEX idx_parent_type (parent_type)",

    // Add indexes to admin_preferences table
    "ALTER TABLE admin_preferences ADD INDEX idx_admin_id (admin_id)",
    "ALTER TABLE admin_preferences ADD INDEX idx_table_name (table_name)",

    // Optimize tables
    "OPTIMIZE TABLE students",
    "OPTIMIZE TABLE instrument_registrations",
    "OPTIMIZE TABLE parents",
    "OPTIMIZE TABLE admin_preferences",
    "OPTIMIZE TABLE admins",
    // Refresh statistics
    "ANALYZE TABLE students"
];

$results = [];
$successCount = 0;
$errorCount = 0;

foreach ($optimizationQueries as $query) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $results[] = [
            'query' => $query,
            'status' => 'success',
            'message' => 'Index added successfully'
        ];
        $successCount++;
    } catch (PDOException $e) {
        // Treat duplicate index/column as non-fatal 'already exists'
        if (strpos($e->getMessage(), 'Duplicate key name') !== false
            || strpos($e->getMessage(), 'Duplicate column name') !== false
            || $e->getCode() === '42S21') {
            $results[] = [
                'query' => $query,
                'status' => 'duplicate',
                'message' => 'Already exists'
            ];
            $successCount++;
        } else {
            $results[] = [
                'query' => $query,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            $errorCount++;
        }
    }
}

// Get updated table information
$tableInfo = [];
try {
    $stmt = $pdo->query("
        SELECT 
            table_name,
            table_rows,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        ORDER BY table_name
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tableInfo[] = $row;
    }
} catch (Exception $e) {
    // If we can't get table info, that's okay
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'results' => $results,
    'successCount' => $successCount,
    'errorCount' => $errorCount,
    'tableInfo' => $tableInfo
]);
?>