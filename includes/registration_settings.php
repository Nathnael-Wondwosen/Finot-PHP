<?php
// includes/registration_settings.php
require_once __DIR__ . '/../config.php';

if (!function_exists('registration_settings_init')) {
    function registration_settings_init(PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS registration_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(32) NOT NULL UNIQUE,
            active TINYINT(1) NOT NULL DEFAULT 1,
            title VARCHAR(255) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Migrate existing table to add columns if they don't exist
        try { $pdo->exec("ALTER TABLE registration_settings ADD COLUMN title VARCHAR(255) NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE registration_settings ADD COLUMN message TEXT NULL"); } catch (Throwable $e) {}
    }
}

if (!function_exists('get_registration_status')) {
    function get_registration_status(string $type, PDO $pdo): bool {
        registration_settings_init($pdo);
        $stmt = $pdo->prepare('SELECT active FROM registration_settings WHERE type = ? LIMIT 1');
        $stmt->execute([$type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { return (int)$row['active'] === 1; }
        // Default to active if not set
        $stmt = $pdo->prepare('INSERT IGNORE INTO registration_settings(type, active) VALUES(?, 1)');
        $stmt->execute([$type]);
        return true;
    }
}

if (!function_exists('set_registration_status')) {
    function set_registration_status(string $type, bool $active, PDO $pdo): bool {
        registration_settings_init($pdo);
        $stmt = $pdo->prepare('INSERT INTO registration_settings(type, active) VALUES(?, ?) ON DUPLICATE KEY UPDATE active = VALUES(active)');
        return $stmt->execute([$type, $active ? 1 : 0]);
    }
}

if (!function_exists('get_registration_config')) {
    function get_registration_config(string $type, PDO $pdo): array {
        registration_settings_init($pdo);
        $stmt = $pdo->prepare('SELECT type, active, title, message FROM registration_settings WHERE type = ? LIMIT 1');
        $stmt->execute([$type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            // Seed defaults
            $stmt = $pdo->prepare('INSERT IGNORE INTO registration_settings(type, active) VALUES(?, 1)');
            $stmt->execute([$type]);
            return ['type'=>$type, 'active'=>1, 'title'=>null, 'message'=>null];
        }
        return [
            'type' => $row['type'],
            'active' => (int)$row['active'],
            'title' => $row['title'],
            'message' => $row['message']
        ];
    }
}

if (!function_exists('set_registration_config')) {
    function set_registration_config(string $type, ?string $title, ?string $message, PDO $pdo): bool {
        registration_settings_init($pdo);
        $stmt = $pdo->prepare('INSERT INTO registration_settings(type, title, message) VALUES(?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message)');
        return $stmt->execute([$type, $title, $message]);
    }
}
