<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/registration_settings.php';
$type = isset($_GET['type']) ? htmlspecialchars($_GET['type'], ENT_QUOTES, 'UTF-8') : '';
$names = [
    'youth' => 'የወጣቶች ምዝገባ',
    'instrument' => 'የዜማ መሳሪያ ምዝገባ',
    'children' => 'የህፃናት ምዝገባ'
];
$label = isset($names[$type]) ? $names[$type] : 'ምዝገባ';
// Load custom config (title/message)
$cfg = [];
try { $cfg = get_registration_config($type, $pdo); } catch (Throwable $e) { $cfg = []; }
$customTitle = isset($cfg['title']) && $cfg['title'] !== null && $cfg['title'] !== '' ? $cfg['title'] : ($label . ' ተዘግቷል');
$customMessage = isset($cfg['message']) && $cfg['message'] !== null && $cfg['message'] !== '' ? $cfg['message'] : 'እባክዎ በኋላ ይመለሱ፣ ወይም ከአስተዳዳሪው ጋር ይነጋግሩ።';
?><!DOCTYPE html>
<html lang="am">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($customTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap" rel="stylesheet">
  <style> body { font-family: 'Noto Sans Ethiopic', sans-serif; } </style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 text-center">
    <img src="uploads/689636ec11381_finot logo.png" alt="Finot Logo" class="w-20 h-20 mx-auto rounded-full shadow mb-3 object-contain border-4 border-blue-200 bg-white">
    <h1 class="text-xl md:text-2xl font-bold text-red-600 mb-2"><?php echo htmlspecialchars($customTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8')); ?></p>
    <div class="flex items-center justify-center gap-3">
      <a href="welcome.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">ወደ መነሻ ገጽ</a>
      <a href="index.php?skip_welcome=1" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded">መነሻ ይቀርቡ</a>
    </div>
  </div>
</body>
</html>
