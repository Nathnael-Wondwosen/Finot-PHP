<?php
session_start();
require 'config.php';
requireAdminLogin();

require_once __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Students');

$columns = [
    'id','full_name','christian_name','gender','birth_date','current_grade','school_year_start',
    'regular_school_name','phone_number','has_spiritual_father','spiritual_father_name','spiritual_father_phone','spiritual_father_church',
    'sub_city','district','specific_area','house_number','living_with','special_interests','siblings_in_school','physical_disability','weak_side',
    'transferred_from_other_school','came_from_other_religion','created_at',
    'father_full_name','father_phone','father_occupation',
    'mother_full_name','mother_phone','mother_occupation'
];

// Header row
$colIndex = 1;
foreach ($columns as $col) {
    $sheet->setCellValueByColumnAndRow($colIndex, 1, $col);
    $colIndex++;
}

$sql = "SELECT s.*, 
    f.full_name AS father_full_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
    m.full_name AS mother_full_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation
FROM students s
LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();

$rowNum = 2;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $colIndex = 1;
    foreach ($columns as $col) {
        $val = isset($row[$col]) ? $row[$col] : '';
        $sheet->setCellValueExplicitByColumnAndRow($colIndex, $rowNum, (string)$val, DataType::TYPE_STRING);
        $colIndex++;
    }
    $rowNum++;
}

foreach (range(1, count($columns)) as $i) {
    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
}

$filename = 'students_export_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
