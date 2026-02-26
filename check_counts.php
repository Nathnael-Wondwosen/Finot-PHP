<?php
require 'config.php';

try {
    // Count students
    $stmt = $pdo->query('SELECT COUNT(*) FROM students');
    $students_count = $stmt->fetchColumn();
    
    // Count instrument registrations
    $stmt = $pdo->query('SELECT COUNT(*) FROM instrument_registrations');
    $instruments_count = $stmt->fetchColumn();
    
    echo "Students count: " . $students_count . "\n";
    echo "Instrument registrations count: " . $instruments_count . "\n";
    
    // Check for duplicates in students table
    $stmt = $pdo->query("SELECT full_name, COUNT(*) as count FROM students GROUP BY full_name HAVING COUNT(*) > 1 ORDER BY count DESC LIMIT 10");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nTop 10 duplicate names in students table:\n";
    foreach ($duplicates as $dup) {
        echo $dup['full_name'] . " (" . $dup['count'] . " times)\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>