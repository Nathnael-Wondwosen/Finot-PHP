<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Filters
$search = trim((string)($_GET['search'] ?? ''));
$edu = trim((string)($_GET['education_level'] ?? ''));
$field = trim((string)($_GET['field_of_study'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$profCat = trim((string)($_GET['profession_category'] ?? ''));
$studyCat = trim((string)($_GET['study_field_category'] ?? ''));

// Ensure classification table exists (for join)
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

$where = [];$params = [];
$currentEY = (function(){
    $today = new DateTime();
    $gy = (int)$today->format('Y'); $gm=(int)$today->format('m'); $gd=(int)$today->format('d');
    $ey = $gy - 8; if ($gm > 9 || ($gm == 9 && $gd >= 11)) { $ey = $gy - 7; }
    return $ey;
})();
$where[] = "(s.birth_date IS NOT NULL AND s.birth_date <> '0000-00-00' AND (? - CAST(SUBSTRING(s.birth_date,1,4) AS UNSIGNED)) >= 17)";
$params[] = $currentEY;
if ($search !== '') { $where[] = "(s.full_name LIKE ? OR s.christian_name LIKE ? OR s.phone_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($edu !== '') { $where[] = "s.education_level LIKE ?"; $params[] = "%$edu%"; }
if ($field !== '') { $where[] = "s.field_of_study LIKE ?"; $params[] = "%$field%"; }
if ($status !== '') { $where[] = "sc.status = ?"; $params[] = $status; }
if ($profCat !== '') { $where[] = "sc.profession_category LIKE ?"; $params[] = "%$profCat%"; }
if ($studyCat !== '') { $where[] = "sc.study_field_category LIKE ?"; $params[] = "%$studyCat%"; }

$sql = "SELECT s.id, s.full_name, s.christian_name, s.gender, s.birth_date, s.current_grade, s.phone_number, s.education_level, s.field_of_study,
               sc.status, sc.profession_category, sc.study_field_category
        FROM students s
        LEFT JOIN student_classifications sc ON sc.student_id = s.id";
if ($where) { $sql .= " WHERE " . implode(' AND ', $where); }
$sql .= " ORDER BY s.created_at DESC LIMIT 5000"; // safety cap

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$filename = 'Youth_Categorization_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Cache-Control: no-cache, must-revalidate');

$out = fopen('php://output', 'w');
// UTF-8 BOM
fputs($out, "\xEF\xBB\xBF");

$headers = [
  'ID','Full Name','Christian Name','Gender','Birth (ET)','Current Grade','Phone','Education Level','Field of Study',
  'Status','Profession Category','Study Field Category'
];
fputcsv($out, $headers);
foreach ($rows as $r) {
    $row = [
        $r['id'] ?? '',
        $r['full_name'] ?? '',
        $r['christian_name'] ?? '',
        $r['gender'] ?? '',
        $r['birth_date'] ?? '',
        $r['current_grade'] ?? '',
        $r['phone_number'] ?? '',
        $r['education_level'] ?? '',
        $r['field_of_study'] ?? '',
        $r['status'] ?? '',
        $r['profession_category'] ?? '',
        $r['study_field_category'] ?? ''
    ];
    fputcsv($out, $row);
}

fclose($out);
exit;
