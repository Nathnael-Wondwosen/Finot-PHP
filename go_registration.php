<?php
// go_registration.php - smart router for registration availability
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/registration_settings.php';

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$map = [
    'youth' => 'youth_registration.php',
    'instrument' => 'instrument_registration.php',
    'children' => 'registration.php'
];

if (!isset($map[$type])) {
    header('Location: welcome.php');
    exit;
}

$active = get_registration_status($type, $pdo);
if ($active) {
    header('Location: ' . $map[$type]);
    exit;
}

// Closed
header('Location: registration_closed.php?type=' . urlencode($type));
exit;
