<?php
session_start();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>መነሻ ገጽ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Ethiopic', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="w-full max-w-3xl bg-white rounded-2xl shadow-xl p-6 md:p-8">
        <div class="text-center mb-6">
            <img src="uploads/689636ec11381_finot logo.png" alt="Finot Logo" class="w-20 h-20 md:w-24 md:h-24 mx-auto rounded-full shadow-lg object-contain border-4 border-blue-200 dark:border-blue-800 bg-white mb-4">
            <h1 class="text-2xl md:text-3xl font-bold text-blue-800">እንኳን በደህና መጡ</h1>
            <p class="text-gray-600 mt-2">እባክዎ መመሪያዎችን ያንብቡ እና የምዝገባ ምክንያትዎን ይምረጡ።</p>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800 mb-6">
            <ul class="list-disc list-inside space-y-1">
                <li>ፎርሙን ሙሉ በሙሉ በትክክል ይሙሉ።</li>
                <li>የትክክለኛ ኢትዮጵያዊ የትውልድ ቀን ይምረጡ።</li>
                <li>የወላጅ መረጃዎች እና ስልክ ቁጥሮች ትክክለኛ መሆናቸውን ያረጋግጡ።</li>
                <li>ወደ መነሻ ገጽ ለመመለስ ሲፈልጉ አርማውን ይጫኑ።</li>
            </ul>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="go_registration.php?type=instrument" class="block p-5 bg-white border rounded-lg hover:shadow-md transition">
                <div class="text-lg font-semibold">የዜማ መሳሪያ</div>
                <div class="text-sm text-gray-600 mt-1">የዜማ መሣሪያ ምዝገባ</div>
            </a>
            <a href="go_registration.php?type=youth" class="block p-5 bg-white border rounded-lg hover:shadow-md transition">
                <div class="text-lg font-semibold">ወጣቶች</div>
                <div class="text-sm text-gray-600 mt-1">17 አመት እና ከዚያ በላይ</div>
            </a>
            <a href="go_registration.php?type=children" class="block p-5 bg-white border rounded-lg hover:shadow-md transition col-span-1 sm:col-span-2">
                <div class="text-lg font-semibold">ህፃናት</div>
                <div class="text-sm text-gray-600 mt-1">ከ18 አመት በታች ለሆኑ ተመዝጋቢዎች</div>
            </a>
        </div>

        <div class="flex items-center justify-between mt-6">
            <label class="inline-flex items-center gap-2 text-gray-600 text-sm">
                <input type="checkbox" id="dontShowAgain" class="h-4 w-4">
                ይህን ገጽ ከዚህ በኋላ አታሳይልኝ
            </label>
            <a href="index.php" id="continueBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">ቀጥል</a>
        </div>
    </div>

    <script>
    document.getElementById('continueBtn').addEventListener('click', function(e){
        if (document.getElementById('dontShowAgain').checked) {
            document.cookie = 'seen_welcome=1; path=/; max-age=' + (60*60*24*365);
        }
    });
    </script>
</body>
</html>