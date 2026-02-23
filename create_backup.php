<?php
// Create initial backup
session_start();
$_SESSION['admin_id'] = 1; // Simulate admin login

require 'backup_manager.php';
?>