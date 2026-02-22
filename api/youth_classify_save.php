<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS student_classifications (
    student_id INT NOT NULL PRIMARY KEY,
    status ENUM('student','worker') DEFAULT NULL,
    profession_category VARCHAR(100) DEFAULT NULL,
    study_field_category VARCHAR(100) DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_profession (profession_category),
    INDEX idx_study_field (study_field_category),
    CONSTRAINT fk_sc_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$ids = $payload['student_ids'] ?? [];
$status = $payload['status'] ?? null; // 'student' | 'worker' | null
$profession = isset($payload['profession_category']) ? trim((string)$payload['profession_category']) : null;
$studyField = isset($payload['study_field_category']) ? trim((string)$payload['study_field_category']) : null;

if (!is_array($ids) || count($ids) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No student_ids provided']);
    exit;
}

$ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
if (count($ids) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid student_ids']);
    exit;
}

if ($status !== null && !in_array($status, ['student','worker'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status value']);
    exit;
}

try {
    $pdo->beginTransaction();
    $up = $pdo->prepare("INSERT INTO student_classifications(student_id, status, profession_category, study_field_category)
                         VALUES(?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE status=VALUES(status), profession_category=VALUES(profession_category), study_field_category=VALUES(study_field_category)");

    foreach ($ids as $sid) {
        $cur = $pdo->prepare('SELECT status, profession_category, study_field_category FROM student_classifications WHERE student_id=?');
        $cur->execute([$sid]);
        $row = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
        $newStatus = $status !== null ? $status : ($row['status'] ?? null);
        $newProf = ($profession !== null && $profession !== '') ? $profession : ($row['profession_category'] ?? null);
        $newField = ($studyField !== null && $studyField !== '') ? $studyField : ($row['study_field_category'] ?? null);
        $up->execute([$sid, $newStatus, $newProf, $newField]);
    }
    $pdo->commit();

    echo json_encode(['success' => true, 'updated' => count($ids)]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Save failed: ' . $e->getMessage()]);
}
