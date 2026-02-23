<?php
// Initialize monitoring system
session_start();
$_SESSION['admin_id'] = 1; // Simulate admin login

require 'system_monitor.php';
?>