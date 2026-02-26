<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security_helpers.php';
require_once __DIR__ . '/../includes/students_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Ensure classification table exists (lightweight)
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

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page_raw = $_GET['per_page'] ?? 25;
$per_page = (string)$per_page_raw === 'all' ? 'all' : min(100, max(1, (int)$per_page_raw));
$search = trim((string)($_GET['search'] ?? ''));
$edu = trim((string)($_GET['education_level'] ?? ''));
$field = trim((string)($_GET['field_of_study'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$profCat = trim((string)($_GET['profession_category'] ?? ''));
$studyCat = trim((string)($_GET['study_field_category'] ?? ''));

// Build query: only required columns
$where = [];
$params = [];

// Age >= 17 using Ethiopian year logic as in helpers
$currentEY = current_ethiopian_year();
$where[] = "(s.birth_date IS NOT NULL AND s.birth_date <> '0000-00-00' AND (? - CAST(SUBSTRING(s.birth_date,1,4) AS UNSIGNED)) >= 17)";
$params[] = $currentEY;

if ($search !== '') {
    $where[] = "(s.full_name LIKE ? OR s.christian_name LIKE ? OR s.phone_number LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($edu !== '') { $where[] = "s.education_level LIKE ?"; $params[] = "%$edu%"; }
if ($field !== '') { $where[] = "s.field_of_study LIKE ?"; $params[] = "%$field%"; }
if ($status !== '') { $where[] = "sc.status = ?"; $params[] = $status; }
if ($profCat !== '') { $where[] = "sc.profession_category LIKE ?"; $params[] = "%$profCat%"; }
if ($studyCat !== '') { $where[] = "sc.study_field_category LIKE ?"; $params[] = "%$studyCat%"; }

$sql = "SELECT s.id, s.full_name, s.christian_name, s.phone_number, s.birth_date, s.current_grade, s.education_level, s.field_of_study,
               sc.status, sc.profession_category, sc.study_field_category
        FROM students s
        LEFT JOIN student_classifications sc ON sc.student_id = s.id";
if ($where) { $sql .= " WHERE " . implode(' AND ', $where); }
$sql .= " ORDER BY s.created_at DESC";
if ($per_page !== 'all') { $sql .= " LIMIT ? OFFSET ?"; }

$stmt = $pdo->prepare($sql);
$bindIndex = 1;
foreach ($params as $v) { $stmt->bindValue($bindIndex++, $v); }
if ($per_page !== 'all') {
    $stmt->bindValue($bindIndex++, (int)$per_page, PDO::PARAM_INT);
    $stmt->bindValue($bindIndex++, (int)(($page-1)*$per_page), PDO::PARAM_INT);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Count for pagination
$countSql = "SELECT COUNT(*) AS cnt FROM students s LEFT JOIN student_classifications sc ON sc.student_id = s.id";
if ($where) { $countSql .= " WHERE " . implode(' AND ', $where); }
$cstmt = $pdo->prepare($countSql);
$bindIndex = 1; foreach ($params as $v) { $cstmt->bindValue($bindIndex++, $v); }
$cstmt->execute();
$total = (int)($cstmt->fetchColumn() ?: 0);

// Also return distinct lists for filters (edu/field), lightweight
$filters = [
  'education_levels' => [],
  'fields_of_study' => [],
  'profession_categories' => [],
  'study_field_categories' => []
];
$lvls = $pdo->query("SELECT DISTINCT education_level FROM students WHERE education_level IS NOT NULL AND education_level <> '' ORDER BY education_level ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$flds = $pdo->query("SELECT DISTINCT field_of_study FROM students WHERE field_of_study IS NOT NULL AND field_of_study <> '' ORDER BY field_of_study ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$filters['education_levels'] = $lvls;
$filters['fields_of_study'] = $flds;

// Ensure admin_categories exists and also provide distinct categories for filters
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('profession','study_field') NOT NULL,
  name VARCHAR(100) NOT NULL,
  UNIQUE KEY uniq_type_name (type, name),
  INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$profRows = $pdo->query("SELECT name FROM admin_categories WHERE type='profession' ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$studyRows = $pdo->query("SELECT name FROM admin_categories WHERE type='study_field' ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Fallback to existing assignments if admin list empty
if (!$profRows) {
    $profRows = $pdo->query("SELECT DISTINCT profession_category FROM student_classifications WHERE profession_category IS NOT NULL AND profession_category<>'' ORDER BY profession_category ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
if (!$studyRows) {
    $studyRows = $pdo->query("SELECT DISTINCT study_field_category FROM student_classifications WHERE study_field_category IS NOT NULL AND study_field_category<>'' ORDER BY study_field_category ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
$filters['profession_categories'] = $profRows;
$filters['study_field_categories'] = $studyRows;

echo json_encode([
  'success' => true,
  'data' => $rows,
  'pagination' => ['page'=>$page,'per_page'=>$per_page,'total'=>$total],
  'filters' => $filters
]);
