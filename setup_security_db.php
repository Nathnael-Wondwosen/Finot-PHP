<?php
require 'config.php';

try {
    echo "🔒 Creating security database tables...\n";

    // Create security audit table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS security_audit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            user_id INT DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            event_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_created_at (created_at),
            INDEX idx_ip_address (ip_address)
        )
    ");
    echo "✅ Security audit table created\n";

    // Create failed login attempts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS failed_login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username_ip (username, ip_address),
            INDEX idx_attempt_time (attempt_time)
        )
    ");
    echo "✅ Failed login attempts table created\n";

    // Add security columns to existing tables
    try {
        $pdo->exec("ALTER TABLE admins ADD COLUMN last_password_change TIMESTAMP NULL DEFAULT NULL");
        echo "✅ Added last_password_change to admins table\n";
    } catch (Exception $e) {
        echo "⚠️ last_password_change column might already exist\n";
    }

    try {
        $pdo->exec("ALTER TABLE admins ADD COLUMN password_reset_token VARCHAR(255) NULL DEFAULT NULL");
        echo "✅ Added password_reset_token to admins table\n";
    } catch (Exception $e) {
        echo "⚠️ password_reset_token column might already exist\n";
    }

    try {
        $pdo->exec("ALTER TABLE admins ADD COLUMN password_reset_expires TIMESTAMP NULL DEFAULT NULL");
        echo "✅ Added password_reset_expires to admins table\n";
    } catch (Exception $e) {
        echo "⚠️ password_reset_expires column might already exist\n";
    }

    try {
        $pdo->exec("ALTER TABLE admins ADD COLUMN account_locked_until TIMESTAMP NULL DEFAULT NULL");
        echo "✅ Added account_locked_until to admins table\n";
    } catch (Exception $e) {
        echo "⚠️ account_locked_until column might already exist\n";
    }

    try {
        $pdo->exec("ALTER TABLE admins ADD COLUMN failed_login_count INT DEFAULT 0");
        echo "✅ Added failed_login_count to admins table\n";
    } catch (Exception $e) {
        echo "⚠️ failed_login_count column might already exist\n";
    }

    echo "\n🎉 Security database setup completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Security database setup failed: " . $e->getMessage() . "\n";
}
?>