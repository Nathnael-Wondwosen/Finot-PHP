<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ምዝገባ ተጠናቀቀ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Ethiopic', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-md w-full text-center">
            <div class="flex flex-col items-center mb-4">
                <img src="uploads/689636ec11381_finot logo.png" alt="Finot Logo" class="w-20 h-20 md:w-28 md:h-28 rounded-full shadow-lg object-contain border-4 border-blue-200 dark:border-blue-800 bg-white mb-2 transition-all duration-300 hover:scale-105">
            </div>
            <div class="text-green-500 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold mb-4 text-gray-800">ምዝገባው በተሳካ ሁኔታ ተጠናቀቀ!</h1>
            <p class="text-gray-600 mb-6">የተማሪው መረጃ በተሳካ ሁኔታ ተመዝግቧል። ለተጨማሪ መረጃ እባክዎ የት/ቤቱን አስተዳደር ያነጋግሩ።</p>
            <a href="<?php echo isset($_COOKIE['seen_welcome']) ? 'index.php' : 'welcome.php'; ?>" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 inline-block">
                <?php echo isset($_COOKIE['seen_welcome']) ? 'ወደ መነሻ ገጽ ተመለስ' : 'ቀጥል'; ?>
            </a>
        </div>
    </div>
</body>
</html>
<?php /* Append a small script to clear autosave on confirmed success */ ?>
<script>
  (function(){
    try {
      var params = new URLSearchParams(window.location.search);
      var t = params.get('type');
      if (t === 'instrument') {
        localStorage.removeItem('INSTRUMENT_FORM_AUTOSAVE');
      } else if (t === 'youth') {
        localStorage.removeItem('YOUTH_FORM_AUTOSAVE');
        localStorage.removeItem('YOUTH_FORM_CARD');
        localStorage.removeItem('YOUTH_TEMP_PHOTO_KEY');
        localStorage.removeItem('YOUTH_TEMP_PHOTO_URL');
      }
    } catch (e) {}
  })();
</script>