<?php
session_start();
require 'config.php';
requireAdminLogin();

require_once __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->addScope([Google_Service_Sheets::SPREADSHEETS, Google_Service_Drive::DRIVE_FILE]);
$client->setAccessType('offline');

$service = new Google_Service_Sheets($client);

$columns = [
    'id','full_name','christian_name','gender','birth_date','current_grade','school_year_start',
    'regular_school_name','phone_number','has_spiritual_father','spiritual_father_name','spiritual_father_phone','spiritual_father_church',
    'sub_city','district','specific_area','house_number','living_with','special_interests','siblings_in_school','physical_disability','weak_side',
    'transferred_from_other_school','came_from_other_religion','created_at',
    'father_full_name','father_phone','father_occupation',
    'mother_full_name','mother_phone','mother_occupation'
];

// Create spreadsheet
$spreadsheet = new Google_Service_Sheets_Spreadsheet([
    'properties' => ['title' => 'Students Export ' . date('Y-m-d H:i')]
]);
$spreadsheet = $service->spreadsheets->create($spreadsheet, ['fields' => 'spreadsheetId']);
$spreadsheetId = $spreadsheet->spreadsheetId;

// Prepare values
$values = [];
$values[] = $columns;

$sql = "SELECT s.*, 
    f.full_name AS father_full_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
    m.full_name AS mother_full_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation
FROM students s
LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $record = [];
    foreach ($columns as $col) {
        $record[] = isset($row[$col]) ? (string)$row[$col] : '';
    }
    $values[] = $record;
}

$body = new Google_Service_Sheets_ValueRange([
    'values' => $values
]);
$params = ['valueInputOption' => 'RAW'];
$range = 'Sheet1!A1';
$service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);

// Make it accessible via link
$drive = new Google_Service_Drive(new Google_Client());
$drive->setClient($client);
$permission = new Google_Service_Drive_Permission([
    'type' => 'anyone',
    'role' => 'reader'
]);
$driveService = new Google_Service_Drive($client);
$driveService->permissions->create($spreadsheetId, $permission);

$link = 'https://docs.google.com/spreadsheets/d/' . $spreadsheetId . '/edit#gid=0';
header('Content-Type: application/json');
echo json_encode(['success' => true, 'link' => $link]);
exit;
