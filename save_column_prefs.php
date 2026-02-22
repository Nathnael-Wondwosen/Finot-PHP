<?php
session_start();
header('Content-Type: application/json');
require 'config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Get admin_id from session
$admin_id = $_SESSION['admin_id'] ?? 1;

// Get view parameter to construct table name
$view = $_POST['view'] ?? 'all';
if (!in_array($view, ['all', 'youth', 'under', 'instrument'], true)) {
    $view = 'all';
}
$table_name = 'students_' . $view;

// Get selected columns from POST (supports 'columns' or 'columns[]')
$columns = [];
if (isset($_POST['columns']) && is_array($_POST['columns'])) {
    $columns = $_POST['columns'];
} elseif (isset($_POST['columns']) && is_string($_POST['columns'])) {
    // Comma-separated fallback
    $columns = array_filter(array_map('trim', explode(',', $_POST['columns'])));
} elseif (isset($_POST['columns']) && isset($_POST['columns'][0])) {
    $columns = (array)$_POST['columns'];
} elseif (isset($_POST['columns']) || isset($_POST['columns'][0])) {
    // no-op; will be empty
}
if (empty($columns)) {
    echo json_encode(['success' => false, 'error' => 'No columns selected']);
    exit;
}
$column_list = implode(',', $columns);

// Check if preference exists
$stmt = $pdo->prepare("SELECT id FROM admin_preferences WHERE admin_id = ? AND table_name = ? LIMIT 1");
$stmt->execute([$admin_id, $table_name]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Update
    $update = $pdo->prepare("UPDATE admin_preferences SET column_list = ?, updated_at = NOW() WHERE id = ?");
    $update->execute([$column_list, $row['id']]);
} else {
    // Insert
    $insert = $pdo->prepare("INSERT INTO admin_preferences (admin_id, table_name, column_list) VALUES (?, ?, ?)");
    $insert->execute([$admin_id, $table_name, $column_list]);
}

echo json_encode(['success' => true]);
