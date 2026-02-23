<?php
require 'config.php';

try {
    $stmt = $pdo->query('SHOW TABLES LIKE "security_audit"');
    if ($stmt->rowCount() > 0) {
        echo "✅ Security audit table exists\n";

        $stmt = $pdo->query('SHOW TABLES LIKE "failed_login_attempts"');
        if ($stmt->rowCount() > 0) {
            echo "✅ Failed login attempts table exists\n";
        } else {
            echo "❌ Failed login attempts table not created\n";
        }
    } else {
        echo "❌ Security audit table not created\n";
    }
} catch (Exception $e) {
    echo "❌ Database check failed: " . $e->getMessage() . "\n";
}
?>