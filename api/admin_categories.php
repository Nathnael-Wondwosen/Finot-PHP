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

// Ensure categories table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('profession','study_field') NOT NULL,
  name VARCHAR(100) NOT NULL,
  UNIQUE KEY uniq_type_name (type, name),
  INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $type = $_GET['type'] ?? '';
        if ($type && !in_array($type, ['profession','study_field'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid type']);
            exit;
        }
        if ($type) {
            $stmt = $pdo->prepare('SELECT id, name FROM admin_categories WHERE type = ? ORDER BY name ASC');
            $stmt->execute([$type]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'data' => $rows]);
        } else {
            $rows = $pdo->query("SELECT type, name FROM admin_categories ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'data' => $rows]);
        }
        exit;
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $type = $body['type'] ?? '';
        $name = trim((string)($body['name'] ?? ''));
        if (!in_array($type, ['profession','study_field'], true) || $name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payload']);
            exit;
        }
        $stmt = $pdo->prepare('INSERT IGNORE INTO admin_categories(type, name) VALUES(?, ?)');
        $stmt->execute([$type, $name]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($method === 'DELETE') {
        // Support /admin_categories.php?id=123
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid id']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM admin_categories WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
