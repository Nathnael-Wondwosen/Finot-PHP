<?php
// Set content type for JSON output first
header("Content-Type: application/json");

// Temporary script to run security hardener with admin access
session_start();
$_SESSION['admin_id'] = 1; // Simulate admin login

try {
    require 'security_hardener.php';
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Security hardening failed: " . $e->getMessage()
    ]);
}
?>