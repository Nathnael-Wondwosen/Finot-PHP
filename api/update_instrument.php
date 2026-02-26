<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['registration_id']) || !isset($input['instrument'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$registration_id = (int)$input['registration_id'];
$instrument = trim($input['instrument']);

// Validate instrument types (can be comma-separated for multiple instruments)
$valid_instruments = ['begena', 'masenqo', 'kebero', 'krar'];
$selected_instruments = [];

if (!empty($instrument)) {
    $instruments_array = array_map('trim', explode(',', $instrument));
    
    foreach ($instruments_array as $inst) {
        if (!in_array($inst, $valid_instruments)) {
            echo json_encode(['success' => false, 'message' => 'Invalid instrument type: ' . $inst]);
            exit;
        }
        $selected_instruments[] = $inst;
    }
    
    // Remove duplicates and rejoin
    $selected_instruments = array_unique($selected_instruments);
    $instrument = implode(',', $selected_instruments);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if registration exists
    $stmt = $pdo->prepare("SELECT id, full_name FROM instrument_registrations WHERE id = ?");
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        echo json_encode(['success' => false, 'message' => 'Registration not found']);
        exit;
    }
    
    // Update the instrument(s)
    $stmt = $pdo->prepare("UPDATE instrument_registrations SET instrument = ? WHERE id = ?");
    $result = $stmt->execute([$instrument, $registration_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Instrument selection updated successfully',
            'new_instrument' => $instrument,
            'registration_id' => $registration_id,
            'student_name' => $registration['full_name'],
            'selected_count' => count($selected_instruments)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update instrument selection']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>