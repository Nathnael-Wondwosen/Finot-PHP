<?php
require 'config.php';

try {
    echo "📊 Creating monitoring database tables...\n";

    // System performance metrics
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            metric_type VARCHAR(50) NOT NULL,
            metric_value DECIMAL(10,4) NOT NULL,
            metric_unit VARCHAR(20) DEFAULT 'ms',
            context_data JSON,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_metric_type (metric_type),
            INDEX idx_recorded_at (recorded_at)
        )
    ");
    echo "✅ System metrics table created\n";

    // Error logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS error_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            error_level VARCHAR(20) NOT NULL,
            error_message TEXT NOT NULL,
            error_file VARCHAR(255),
            error_line INT,
            error_context JSON,
            user_id INT DEFAULT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            request_uri VARCHAR(500),
            request_method VARCHAR(10),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_error_level (error_level),
            INDEX idx_created_at (created_at),
            INDEX idx_user_id (user_id)
        )
    ");
    echo "✅ Error logs table created\n";

    // API performance logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_performance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(255) NOT NULL,
            method VARCHAR(10) NOT NULL,
            response_time DECIMAL(8,4) NOT NULL,
            response_code INT NOT NULL,
            request_size INT DEFAULT 0,
            response_size INT DEFAULT 0,
            user_id INT DEFAULT NULL,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_endpoint (endpoint),
            INDEX idx_created_at (created_at),
            INDEX idx_response_time (response_time)
        )
    ");
    echo "✅ API performance table created\n";

    // User activity logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            activity_type VARCHAR(50) NOT NULL,
            activity_description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            session_id VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_activity_type (activity_type),
            INDEX idx_created_at (created_at)
        )
    ");
    echo "✅ User activity table created\n";

    echo "\n🎉 Monitoring database setup completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Monitoring database setup failed: " . $e->getMessage() . "\n";
}
?>