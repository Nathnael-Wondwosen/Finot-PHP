<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['student_name']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$student_name = trim($input['student_name']);
$action = $input['action'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'consolidate':
            // Keep the most recent registration and move other instruments to it
            $stmt = $pdo->prepare("
                SELECT * FROM instrument_registrations 
                WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?))
                ORDER BY created_at DESC
            ");
            $stmt->execute([$student_name]);
            $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($registrations) <= 1) {
                echo json_encode(['success' => false, 'message' => 'No duplicate registrations to consolidate']);
                exit;
            }
            
            $primary_registration = $registrations[0];
            $instruments = [];
            $registration_ids_to_delete = [];
            
            // Collect all instruments
            foreach ($registrations as $reg) {
                $instruments[] = $reg['instrument'];
                if ($reg['id'] != $primary_registration['id']) {
                    $registration_ids_to_delete[] = $reg['id'];
                }
            }
            
            // Update primary registration with all instruments (comma-separated)
            $all_instruments = implode(', ', array_unique($instruments));
            $stmt = $pdo->prepare("UPDATE instrument_registrations SET instrument = ? WHERE id = ?");
            $stmt->execute([$all_instruments, $primary_registration['id']]);
            
            // Delete duplicate registrations
            if (!empty($registration_ids_to_delete)) {
                $placeholders = str_repeat('?,', count($registration_ids_to_delete) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM instrument_registrations WHERE id IN ($placeholders)");
                $stmt->execute($registration_ids_to_delete);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Successfully consolidated ' . count($registrations) . ' registrations into one',
                'consolidated_instruments' => $all_instruments
            ]);
            break;
            
        case 'merge_with_student':
            if (!isset($input['target_student_id'])) {
                echo json_encode(['success' => false, 'message' => 'Target student ID required']);
                exit;
            }
            
            $target_student_id = $input['target_student_id'];
            
            // Update all registrations to link with the target student
            $stmt = $pdo->prepare("
                UPDATE instrument_registrations 
                SET student_id = ? 
                WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?))
            ");
            $stmt->execute([$target_student_id, $student_name]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Successfully linked all registrations to student #' . $target_student_id
            ]);
            break;
            
        case 'flag_all':
            $flag_status = isset($input['flag_status']) ? $input['flag_status'] : 1;
            
            $stmt = $pdo->prepare("
                UPDATE instrument_registrations 
                SET flagged = ? 
                WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?))
            ");
            $stmt->execute([$flag_status, $student_name]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Successfully ' . ($flag_status ? 'flagged' : 'unflagged') . ' all registrations'
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>