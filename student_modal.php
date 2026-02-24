<?php
session_start();
require 'config.php';
requireAdminLogin();
require_once 'ethiopian_age.php';
header('Content-Type: application/json');

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id <= 0) {
    echo json_encode(['error' => 'Invalid student ID']);
    exit;
}
$sql = "SELECT s.*, 
    f.full_name AS father_full_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
    m.full_name AS mother_full_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation,
    g.full_name AS guardian_full_name, g.phone_number AS guardian_phone, g.occupation AS guardian_occupation
FROM students s
LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
WHERE s.id = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$student_id]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    echo json_encode(['error' => 'Student not found']);
    exit;
}

// Ethiopian calendar helpers
function gregorian_to_jdn($y, $m, $d) {
    $a = intdiv(14 - $m, 12);
    $yy = $y + 4800 - $a;
    $mm = $m + 12 * $a - 3;
    return $d + intdiv(153 * $mm + 2, 5) + 365 * $yy + intdiv($yy, 4) - intdiv($yy, 100) + intdiv($yy, 400) - 32045;
}
function jdn_to_ethiopian($jdn) {
    $r = ($jdn - 1723856) % 1461;
    if ($r < 0) $r += 1461;
    $n = ($r % 365) + 365 * intdiv($r, 1460);
    $year = 4 * intdiv(($jdn - 1723856), 1461) + intdiv($r, 365) - intdiv($r, 1460);
    $month = intdiv($n, 30) + 1;
    $day = ($n % 30) + 1;
    return [$year, $month, $day];
}
function ethiopian_today() {
    $t = new DateTime();
    [$ey, $em, $ed] = jdn_to_ethiopian(gregorian_to_jdn((int)$t->format('Y'), (int)$t->format('m'), (int)$t->format('d')));
    return [$ey, $em, $ed];
}
function ethiopian_age_from_ymd($ey, $em, $ed) {
    [$cy, $cm, $cd] = ethiopian_today();
    $age = $cy - $ey;
    if ($cm < $em || ($cm === $em && $cd < $ed)) $age--;
    return $age;
}

// Compose Ethiopian birth date display and age
$birthDateDisplay = null;
$ageEt = null;
if (isset($student['birth_year_et']) && $student['birth_year_et']) {
    $ey = (int)$student['birth_year_et'];
    $em = (int)$student['birth_month_et'];
    $ed = (int)$student['birth_day_et'];
    $birthDateDisplay = sprintf('%04d-%02d-%02d', $ey, $em, $ed);
    $ageEt = ethiopian_age_from_ymd($ey, $em, $ed);
} elseif (!empty($student['birth_date'])) {
    // birth_date stored as Ethiopian YYYY-MM-DD
    $birthDateDisplay = $student['birth_date'];
    [$ey, $em, $ed] = array_map('intval', explode('-', $student['birth_date']));
    if ($ey && $em && $ed) $ageEt = ethiopian_age_from_ymd($ey, $em, $ed);
}
$student['birth_date_display'] = $birthDateDisplay;
$student['age'] = $ageEt;
$student['ethiopian_age'] = $ageEt;

echo json_encode($student);
