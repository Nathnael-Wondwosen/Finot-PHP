<?php
// Run testing framework
session_start();
$_SESSION['admin_id'] = 1; // Simulate admin login

require 'testing_framework.php';
?>