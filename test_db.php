<?php
// Database connection test
try {
    $pdo = new PDO('mysql:host=localhost;dbname=finotdb;charset=utf8mb4', 'root', '');
    echo "✅ Database connection successful!\n";

    // Test a simple query
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM students');
    $result = $stmt->fetch();
    echo "✅ Students table accessible. Total records: " . $result['count'] . "\n";

} catch(Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";

    // Try to provide helpful troubleshooting
    if (strpos($e->getMessage(), 'could not find driver') !== false) {
        echo "💡 Solution: Enable PDO MySQL extension in php.ini\n";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "💡 Solution: Start MySQL service\n";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "💡 Solution: Create the 'finotdb' database\n";
    }
}
?>