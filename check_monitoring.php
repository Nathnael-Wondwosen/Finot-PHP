<?php
require 'config.php';

try {
    $stmt = $pdo->query('SHOW TABLES LIKE "system_metrics"');
    if ($stmt->rowCount() > 0) {
        echo "✅ Monitoring tables exist\n";

        $stmt = $pdo->query('SHOW TABLES LIKE "error_logs"');
        if ($stmt->rowCount() > 0) {
            echo "✅ Error logs table exists\n";
        } else {
            echo "❌ Error logs table not created\n";
        }
    } else {
        echo "❌ Monitoring tables not created\n";
    }
} catch (Exception $e) {
    echo "❌ Database check failed: " . $e->getMessage() . "\n";
}
?>