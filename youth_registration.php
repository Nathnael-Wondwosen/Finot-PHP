<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የወጣቶች ምዝገባ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Ethiopic', sans-serif; }
        .form-card { position: absolute; left: -9999px; }
        .form-card.active { position: static; left: auto; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px);} to { opacity: 1; transform: translateY(0);} }
        .progress-step { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background-color: #e5e7eb; color: #6b7280; font-weight: 700; font-size: 0.9rem; }
        .progress-step.active { background-color: #3b82f6; color: #fff; }
        .progress-step.completed { background-color: #10b981; color: #fff; }
    </style>
</head>
<body class="bg-gray-50">
    <?php
    // Inject youth form configuration for zero extra HTTP calls
    try {
        require_once __DIR__ . '/includes/form_config.php';
        $cfgRows = get_form_config('youth', $pdo);
        $cfgAssoc = [];
        foreach ($cfgRows as $r) {
            $cfgAssoc[$r['field_key']] = [
                'label' => $r['label'],
                'placeholder' => $r['placeholder'],
                'required' => (int)$r['required'],
                'sort_order' => (int)$r['sort_order']
            ];
        }
        echo '<script>window.FORM_CFG_YOUTH=' . json_encode($cfgAssoc, JSON_UNESCAPED_UNICODE) . ';</script>';
    } catch (Throwable $e) { /* ignore */ }
    ?>
    <!-- Error Toast Notification -->
    <?php
    if (isset($_GET['success']) && $_GET['success']) {
        echo '<div id=\'success-toast\' class=\'fixed top-6 left-1/2 transform -translate-x-1/2 z-50 bg-green-100 border border-green-400 text-green-800 px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 animate-fade-in\' style=\'min-width:300px;max-width:90vw;\'>
            <svg class=\'w-6 h-6 text-green-500 flex-shrink-0\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M5 13l4 4L19 7\'/></svg>
            <span class=\'flex-1 text-base font-semibold\'>ምዝገባው ተሳክቷል!</span>
            <button onclick=\'document.getElementById("success-toast").remove()\' class=\'ml-4 text-green-600 hover:text-green-900 focus:outline-none\'>
                <svg class=\'w-5 h-5\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M6 18L18 6M6 6l12 12\'/></svg>
            </button>
        </div>';
        echo '<script>setTimeout(function(){ var toast = document.getElementById("success-toast"); if (toast) toast.remove(); }, 7000);</script>';
    }
    ?>
    <?php
    $displayError = '';
    if (isset($_GET['error']) && $_GET['error']) {
        $displayError = htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8');
    } else {
        // Only show the last error from the log if it's recent (within last 5 minutes)
        $logFile = __DIR__ . '/youth_registration_error.log';
        if (file_exists($logFile)) {
            $lines = file($logFile);
            if ($lines && count($lines) > 0) {
                $lastLine = trim($lines[count($lines)-1]);
                // Extract timestamp from the log entry
                if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $lastLine, $matches)) {
                    $logTime = strtotime($matches[1]);
                    $currentTime = time();
                    // Only show error if it's from the last 5 minutes
                    if (($currentTime - $logTime) < 300) {
                        $displayError = htmlspecialchars($lastLine, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        }
    }
    if ($displayError): ?>
        <div id="error-toast" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 bg-red-100 border border-red-400 text-red-800 px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 animate-fade-in" style="min-width:300px;max-width:90vw;">
            <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="flex-1 text-base font-semibold">
                <?php echo $displayError; ?>
            </span>
            <button onclick="document.getElementById('error-toast').remove()" class="ml-4 text-red-600 hover:text-red-900 focus:outline-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .animate-fade-in { animation: fade-in 0.5s cubic-bezier(.4,0,.2,1); }
        </style>
        <script>
        setTimeout(function(){
            var toast = document.getElementById('error-toast');
            if (toast) toast.remove();
        }, 7000);
        </script>
    <?php endif; ?>
    <div class="container mx-auto px-4 py-8 max-w-3xl">
        <div class="flex flex-col items-center mb-6">
            <a href="welcome.php">
                <img src="uploads/689636ec11381_finot logo.png" alt="Finot Logo" class="w-24 h-24 md:w-32 md:h-32 rounded-full shadow-lg object-contain border-4 border-blue-200 dark:border-blue-800 bg-white mb-2 transition-all duration-300 hover:scale-105">
            </a>
            <h1 class="text-2xl md:text-3xl font-bold text-blue-800">የወጣቶች ምዝገባ</h1>
            <p class="text-gray-600 mt-1">እባክዎ አስፈላጊ መረጃዎችን ይሙሉ።</p>
            <div class="mt-2">
                <button type="button" onclick="clearYouthForm()" class="px-3 py-1 text-xs md:text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 rounded">ፎርም አጥራ</button>
            </div>
            <!-- Add helpful note about age requirement -->
            <div class="mt-2 p-2 bg-blue-50 rounded-lg text-center">
                <p class="text-sm text-blue-700">ማስታወሻ: ወጣት ምድብ ለመመዝገብ 17 ዓመት እና ከዛ በላይ መሆን ይኖርቦታል</p>
            </div>
        </div>

        <!-- Progress indicator -->
        <div class="flex justify-between items-center mb-6 relative">
            <div class="absolute top-1/2 left-0 right-0 h-1 bg-gray-200 -z-10"></div>
            <div id="y-progress-bar" class="absolute top-1/2 left-0 h-1 bg-blue-500 -z-10" style="width: 0%"></div>
            <div class="progress-step" id="y-step-1">1</div>
            <div class="progress-step" id="y-step-2">2</div>
            <div class="progress-step" id="y-step-3">3</div>
            <div class="progress-step" id="y-step-4">4</div>
            <div class="progress-step" id="y-step-5">5</div>
        </div>

        <form id="youth-form" action="process_youth.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6" novalidate>
        <input type="hidden" name="form_submitted" value="1">
        <input type="hidden" name="temp_photo_key" id="y_temp_photo_key" value="">
        <!-- Submission indicator -->
        <div id="youth-submitting-indicator" class="hidden mb-4 px-3 py-2 rounded bg-blue-50 text-blue-700 text-center text-base font-semibold flex items-center justify-center gap-2">
            <svg class="w-5 h-5 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
            <span>መረጃው በመላክ ላይ ነው...</span>
        </div>
            <div id="y-error" class="hidden mb-4 px-3 py-2 rounded bg-red-50 text-red-700 text-sm"></div>
        <div id="y-review-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl p-0 overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center justify-between bg-blue-50">
                    <h3 class="text-lg md:text-xl font-bold text-blue-800">መመሪያ ማረጋገጫ</h3>
                    <button type="button" id="y-review-close" class="text-blue-700 hover:text-blue-900" aria-label="Close">✕</button>
                </div>
                <div id="y-review-content" class="max-h-[70vh] overflow-auto px-5 py-4 space-y-6 text-sm text-gray-800"></div>
                <div class="px-5 py-4 border-t bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p class="text-xs text-gray-600">እባክዎ መረጃዎትን ያረጋግጡ። ትክክለኛ ከሆነ በ"ሙሉ በሙሉ አስገባ" ይጨርሱ።</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="y-review-cancel" class="px-4 py-2 rounded-md bg-white border border-gray-300 text-gray-800 hover:bg-gray-100">ተመለስ እና አርም</button>
                        <button type="button" id="y-review-confirm" class="px-4 py-2 rounded-md bg-green-600 hover:bg-green-700 text-white font-semibold shadow-sm">ሙሉ በሙሉ አስገባ</button>
                    </div>
                </div>
            </div>
        </div>
            <!-- Card 1: Student Information -->
            <div class="form-card active" id="y-card-1">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">1. የተማሪ መረጃ</h2>
                <div>
                    <label class="block text-gray-700 mb-1" for="y_student_photo">የተማሪ ፎቶ <span class="text-red-500">*</span></label>
                    <div class="flex items-center">
                        <div class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                            <img id="y-photo-preview" src="" alt="" class="hidden w-full h-full object-cover">
                            <span id="y-photo-placeholder" class="text-gray-500">ፎቶ</span>
                        </div>
                        <input type="file" id="y_student_photo" name="student_photo" accept="image/*" class="ml-4 hidden">
                        <button type="button" onclick="document.getElementById('y_student_photo').click()" class="ml-4 px-3 py-1 text-xs md:text-sm bg-blue-500 text-white rounded hover:bg-blue-600">ፎቶ ይምረጡ</button>
                        <button type="button" onclick="openYCameraModal()" class="ml-2 px-3 py-1 text-xs md:text-sm bg-green-500 text-white rounded hover:bg-green-600">ፎቶ አንሳ</button>
                    </div>
                    <!-- Photo Required Modal -->
                    <div id="y-photo-required" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                        <div class="bg-white rounded-lg p-6 shadow-lg w-80">
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">የተማሪ ፎቶ ያስፈልጋል</h3>
                            <p class="text-sm text-gray-600 mb-4">ምዝገባ ለመቀጠል ፎቶ ይመርጡ ወይም በስልክዎ ይነሱ።</p>
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="closeYPhotoRequired()" class="px-3 py-2 bg-gray-200 rounded">ዝጋ</button>
                                <button type="button" onclick="closeYPhotoRequired();document.getElementById('y_student_photo').click();" class="px-3 py-2 bg-blue-600 text-white rounded">ፎቶ ይምረጡ</button>
                                <button type="button" onclick="closeYPhotoRequired();openYCameraModal();" class="px-3 py-2 bg-green-600 text-white rounded">ፎቶ አንሳ</button>
                            </div>
                        </div>
                    </div>
                    <!-- Camera Modal -->
                    <div id="y-camera-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                        <div class="bg-white rounded-lg p-6 shadow-lg relative w-80">
                            <button type="button" onclick="closeYCameraModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">&times;</button>
                            <video id="y-camera-video" autoplay playsinline class="w-full h-48 bg-gray-200 rounded"></video>
                            <canvas id="y-camera-canvas" class="hidden"></canvas>
                            <div class="flex justify-between mt-4">
                                <button type="button" onclick="switchYCamera()" class="px-3 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Switch Camera</button>
                                <button type="button" onclick="captureYPhoto()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">ፎቶ አንሳ</button>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">JPEG/PNG, 5MB እስከ.</p>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">ሙሉ ስም እስከ አያት እስከ አያት <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" class="w-full px-3 py-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">የክርስትና ስም <span class="text-red-500">*</span></label>
                    <input type="text" name="christian_name" class="w-full px-3 py-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">ጾታ <span class="text-red-500">*</span></label>
                    <div class="flex gap-6">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="gender" value="male" required> ወንድ</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="gender" value="female"> ሴት</label>
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">የተወለዱበት ቀን (ዓ.ም) <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <select id="y_birth_year" name="birth_year_et" class="px-3 py-2 border rounded" required></select>
                        <select id="y_birth_month" name="birth_month_et" class="px-3 py-2 border rounded" required></select>
                        <select id="y_birth_day" name="birth_day_et" class="px-3 py-2 border rounded" required></select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">በ2018 ዓ.ም በሰንበት ት/ቤት ስንተኛ ክፍል ነህ/ሽ?<span class="text-red-500">*</span></label>
                        <select name="current_grade" class="w-full px-3 py-2 border rounded" required>
                            <option value="">-- ይምረጡ --</option>
                            <option value="new">አዲስ</option>
                            <option value="1st">1ኛ</option>
                            <option value="2nd">2ኛ</option>
                            <option value="3rd">3ኛ</option>
                            <option value="4th">4ኛ</option>
                            <option value="5th">5ኛ</option>
                            <option value="6th">6ኛ</option>
                            <option value="7th">7ኛ</option>
                            <option value="8th">8ኛ</option>
                            <option value="9th">9ኛ</option>
                            <option value="10th">10ኛ</option>
                            <option value="11th">11ኛ</option>
                            <option value="12th">12ኛ</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">በዚህ ሰንበት ት/ቤት የነበረህ/ሽ ቆይታ? <span class="text-red-500">*</span></label>
                        <select name="school_year_start" class="w-full px-3 py-2 border rounded" required>
                            <option value="">-- ይምረጡ --</option>
                            <option value="2018">አዲስ</option>
                            <?php for ($year = 1995; $year <= 2017; $year++): ?>
                                <option value="<?= $year ?>"><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">ዓላማዊ የትምህርት ደረጃ</label>
                        <input type="text" name="education_level" class="w-full px-3 py-2 border rounded" placeholder="ም CSA. ዩኒቨርሳቲ/ደረጃ ...">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">የሞያ ዘርፍ / ለተማሪዎች የትምህርት ዘርፍ</label>
                        <input type="text" name="field_of_study" class="w-full px-3 py-2 border rounded" placeholder="ም CSA. ኢንጂነሪንግ ...">
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">የተማሪው ስልክ ቁጥር <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone_number" class="w-full px-3 py-2 border rounded" placeholder="09XXXXXXXX" pattern="^(\+?251|0)9\d{8}$" required>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" class="px-5 py-2 bg-gray-200 text-gray-700 rounded" disabled>ቀድሞ</button>
                    <button type="button" data-y-next="2" onclick="try{yNext(2)}catch(_){ }" class="px-5 py-2 bg-blue-600 text-white rounded">ቀጣይ</button>
                </div>
            </div>

            <!-- Card 2: Spiritual Father -->
            <div class="form-card" id="y-card-2">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2">2. የንስሐ አባት</h2>
                <div class="space-y-2">
                    <label class="inline-flex items-center gap-2"><input type="radio" name="has_spiritual_father" value="own" required onchange="toggleYSpiritualFather(true)"> የራሴ አለኝ</label>
                    <label class="inline-flex items-center gap-2"><input type="radio" name="has_spiritual_father" value="family" onchange="toggleYSpiritualFather(true)"> የቤተሰብ (የጋራ)</label>
                    <label class="inline-flex items-center gap-2"><input type="radio" name="has_spiritual_father" value="none" onchange="toggleYSpiritualFather(false)"> የለኝም</label>
                </div>
                <div id="y-spiritual-father-info" class="hidden space-y-3">
                    <h3 class="text-md font-semibold text-gray-700">የንስሐ አባት መረጃ</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-gray-700 mb-1">የካህኑ ስም</label>
                            <input type="text" name="spiritual_father_name" class="w-full px-3 py-2 border rounded">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-1">የካህኑ ስልክ ቁጥር</label>
                            <input type="tel" name="spiritual_father_phone" class="w-full px-3 py-2 border rounded" placeholder="09XXXXXXXX">
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">ካህኑ የሚያገለግሉበት ደብር</label>
                        <input type="text" name="spiritual_father_church" class="w-full px-3 py-2 border rounded">
                    </div>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" data-y-prev="1" onclick="try{yPrev(1)}catch(_){ }" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">ቀድሞ</button>
                    <button type="button" data-y-next="3" onclick="try{yNext(3)}catch(_){ }" class="px-5 py-2 bg-blue-600 text-white rounded">ቀጣይ</button>
                </div>
            </div>

            <!-- Card 3: Address -->
            <div class="form-card" id="y-card-3">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2">3. የመኖሪያ አድራሻ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">ክ/ከተማ</label>
                        <input type="text" name="sub_city" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">ወረዳ</label>
                        <input type="text" name="district" class="w-full px-3 py-2 border rounded">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">የሰፈሩ ልዩ ስም</label>
                        <input type="text" name="specific_area" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">የቤት ቁጥር</label>
                        <input type="text" name="house_number" class="w-full px-3 py-2 border rounded">
                    </div>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" data-y-prev="2" onclick="try{yPrev(2)}catch(_){ }" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">ቀድሞ</button>
                    <button type="button" data-y-next="4" onclick="try{yNext(4)}catch(_){ }" class="px-5 py-2 bg-blue-600 text-white rounded">ቀጣይ</button>
                </div>
            </div>

            <!-- Card 4: Emergency Contact -->
            <div class="form-card" id="y-card-4">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2">4. የአደጋ ጊዜ ተጠሪ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">ሙሉ ስም እስከ አያት <span class="text-red-500">*</span></label>
                        <input type="text" name="emergency_name" class="w-full px-3 py-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">ስልክ ቁጥር</label>
                        <input type="tel" name="emergency_phone" class="w-full px-3 py-2 border rounded" placeholder="09XXXXXXXX" pattern="^(\+?251|0)9\d{8}$" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">ተዋጭ ስልክ ቁጥር <span class="text-red-500">*</span></label>
                        <input type="tel" name="emergency_alt_phone" class="w-full px-3 py-2 border rounded" placeholder="09XXXXXXXX" pattern="^(\+?251|0)9\d{8}$" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">አድራሻ <span class="text-red-500">*</span></label>
                        <input type="text" name="emergency_address" class="w-full px-3 py-2 border rounded" required>
                    </div>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" data-y-prev="3" onclick="try{yPrev(3)}catch(_){ }" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">ቀድሞ</button>
                    <button type="button" data-y-next="5" onclick="try{yNext(5)}catch(_){ }" class="px-5 py-2 bg-blue-600 text-white rounded">ቀጣይ</button>
                </div>
            </div>

            <!-- Card 5: Additional Information -->
            <div class="form-card" id="y-card-5">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2">5. የተማሪው ተጨማሪ መረጃ</h2>
                <div>
                    <label class="block text-gray-700 mb-1">ልዩ ፍላጎትና ተሰጥዖ/ሞያ</label>
                    <textarea name="special_interests" rows="2" class="w-full px-3 py-2 border rounded"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">የአካል ጉዳት</label>
                        <textarea name="physical_disability" rows="2" class="w-full px-3 py-2 border rounded"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">ከሌላ ሰንበት ት/ቤት የተዘዋወረ ካለ</label>
                        <textarea name="transferred_from_other_school" rows="2" class="w-full px-3 py-2 border rounded"></textarea>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">ከሌላ እምነት የመጡ ካለ</label>
                        <textarea name="came_from_other_religion" rows="2" class="w-full px-3 py-2 border rounded"></textarea>
                    </div>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" data-y-prev="4" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">ቀድሞ</button>
                    <button type="submit" id="youth-register-button" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded">ምዝገባ አስገባ</button>
                </div>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        // Apply youth form configuration
        (function(){
            try {
                const CFG = window.FORM_CFG_YOUTH || {};
                function setLabelFor(el, text){
                    if (!el) return;
                    let label = null;
                    if (el.id) label = document.querySelector('label[for="'+el.id+'"]');
                    if (!label) label = (el.previousElementSibling && el.previousElementSibling.tagName==='LABEL') ? el.previousElementSibling : null;
                    if (label && text) label.textContent = text + (label.innerHTML.indexOf('*')!==-1 ? ' *' : '');
                }
                function setPh(el, ph){ if (el && ph) el.placeholder = ph; }
                function setReq(el, req){ if (el) el.required = !!req; }
                const map = {
                    student_photo: document.getElementById('y_student_photo'),
                    full_name: document.querySelector('input[name="full_name"]'),
                    christian_name: document.querySelector('input[name="christian_name"]'),
                    gender: document.querySelector('input[name="gender"]'),
                    birth_date_et: document.getElementById('y_birth_year'),
                    phone_number: document.querySelector('input[name="phone_number"]'),
                    sub_city: document.querySelector('input[name="sub_city"]'),
                    district: document.querySelector('input[name="district"]'),
                    specific_area: document.querySelector('input[name="specific_area"]'),
                    house_number: document.querySelector('input[name="house_number"]'),
                    emergency_name: document.querySelector('input[name="emergency_name"]'),
                    emergency_phone: document.querySelector('input[name="emergency_phone"]'),
                    emergency_alt_phone: document.querySelector('input[name="emergency_alt_phone"]'),
                    emergency_address: document.querySelector('input[name="emergency_address"]'),
                    has_spiritual_father: document.querySelector('input[name="has_spiritual_father"]'),
                    spiritual_father_name: document.querySelector('input[name="spiritual_father_name"]'),
                    spiritual_father_phone: document.querySelector('input[name="spiritual_father_phone"]'),
                    spiritual_father_church: document.querySelector('input[name="spiritual_father_church"]'),
                    current_grade: document.querySelector('input[name="current_grade"]'),
                    school_year_start: document.querySelector('input[name="school_year_start"]'),
                    education_level: document.querySelector('input[name="education_level"]'),
                    field_of_study: document.querySelector('input[name="field_of_study"]'),
                    special_interests: document.querySelector('input[name="special_interests"]'),
                    physical_disability: document.querySelector('input[name="physical_disability"]'),
                    transferred_from_other_school: document.querySelector('input[name="transferred_from_other_school"]'),
                    came_from_other_religion: document.querySelector('input[name="came_from_other_religion"]')
                };
                Object.keys(CFG).forEach(k=>{
                    const c = CFG[k]||{}; const el = map[k]; if (!el) return;
                    if (c.label) setLabelFor(el, c.label);
                    setPh(el, c.placeholder||'');
                    if (k==='gender') { document.querySelectorAll('input[name="gender"]').forEach(r=>setReq(r,c.required)); }
                    else if (k==='birth_date_et') {
                        setReq(document.getElementById('y_birth_year'), c.required);
                        setReq(document.getElementById('y_birth_month'), c.required);
                        setReq(document.getElementById('y_birth_day'), c.required);
                    } else if (k==='has_spiritual_father') {
                        document.querySelectorAll('input[name="has_spiritual_father"]').forEach(r=>setReq(r, c.required));
                    } else {
                        setReq(el, c.required);
                    }
                });
            } catch(_) {}
        })();
        const ySel = document.getElementById('y_birth_year');
        const mSel = document.getElementById('y_birth_month');
        const dSel = document.getElementById('y_birth_day');
        const months = ['መስከረም','ጥቅምት','ሕዳር','ታህሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ','ጳጉሜ'];
        function getEY(){ const t=new Date(); const Y=t.getFullYear(), M=t.getMonth()+1, D=t.getDate(); let e=Y-8; if(M>9||(M===9&&D>=11)) e=Y-7; return e; }
        function isLeap(ey){ return ey%4===3; }
        function dim(ey,em){ return em>=1&&em<=12?30:(isLeap(ey)?6:5); }
        function fillYears(){ const cur=getEY(); ySel.innerHTML=''; const ph=document.createElement('option'); ph.value=''; ph.textContent='ዓመት'; ySel.appendChild(ph); for(let y=cur;y>=cur-40;y--){ const o=document.createElement('option'); o.value=y; o.textContent=y; ySel.appendChild(o);} }
        function fillMonths(){ mSel.innerHTML=''; const ph=document.createElement('option'); ph.value=''; ph.textContent='ወር'; mSel.appendChild(ph); months.forEach((n,i)=>{ const o=document.createElement('option'); o.value=i+1; o.textContent=n; mSel.appendChild(o);}); }
        function fillDays(){ dSel.innerHTML='';; const ph=document.createElement('option'); ph.value=''; ph.textContent='ቀን'; dSel.appendChild(ph); const ey=parseInt(ySel.value,10); const em=parseInt(mSel.value,10); if(!ey||!em) return; const count=dim(ey,em||1); for(let d=1; d<=count; d++){ const o=document.createElement('option'); o.value=d; o.textContent=d; dSel.appendChild(o);} }
        fillYears(); fillMonths(); ySel.addEventListener('change', fillDays); mSel.addEventListener('change', fillDays); fillDays();

        // Restore saved Y/M/D after population
        try {
            const saved = JSON.parse(localStorage.getItem('YOUTH_FORM_AUTOSAVE')||'{}');
            if (saved.birth_year_et) ySel.value = saved.birth_year_et;
            if (saved.birth_month_et) mSel.value = saved.birth_month_et;
            fillDays();
            if (saved.birth_day_et) dSel.value = saved.birth_day_et;
        } catch(_) {}

        // Client-side form validation hooks
        const form = document.getElementById('youth-form');
        const tempKeyInput = document.getElementById('y_temp_photo_key');
        // Lightweight mode: silence debug logs unless explicitly enabled
        window.Y_DEBUG = false;
        
        form.addEventListener('submit', function(e){
            if (window.__yReviewConfirmed === true) {
                return; 
            }
            // Show submitting indicator
            var indicator = document.getElementById('youth-submitting-indicator');
            if (indicator) indicator.classList.remove('hidden');
            // Disable submit button to prevent duplicate submissions
            try {
                var submitBtn = document.getElementById('youth-register-button');
                if (submitBtn) { submitBtn.disabled = true; submitBtn.classList.add('opacity-60','cursor-not-allowed'); }
            } catch(_) {}

            if (window.Y_DEBUG) {
                console.log('=== Form Submission Debug Started ===');
                // Log all form data
                const formData = new FormData(form);
                console.log('Form Data:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ':', value);
                }
            }
            // Check if file is selected
            const fileInput = form.querySelector('input[name="student_photo"]');
            if (window.Y_DEBUG) console.log('Photo file:', fileInput && fileInput.files && fileInput.files[0] ? 'Selected' : 'NOT SELECTED');
            if (fileInput && fileInput.files && fileInput.files[0]) {
                if (window.Y_DEBUG) {
                    console.log('File details:', {
                        name: fileInput.files[0].name,
                        size: fileInput.files[0].size,
                        type: fileInput.files[0].type
                    });
                }
            }
            // First validate all cards
            let currentCard = window.__yCurrentCard || 1;
            const yTotal = 5;
            // Validate each card and show specific errors
            for (let i = 1; i <= yTotal; i++) {
                const card = document.getElementById('y-card-'+i);
                const required = card.querySelectorAll('[required]');
                let firstInvalid = null;
                let invalidFieldLabel = null;
                for (let j = 0; j < required.length; j++) {
                    var el = required[j];
                    var valid = true;
                    if (el.type === 'radio') {
                        var any = card.querySelector('input[name="'+el.name+'"]:checked');
                        valid = !!any;
                    } else if (el.type === 'file') {
                        // Accept either a selected file or a previously uploaded temp photo key
                        var tkOK = !!(document.getElementById('y_temp_photo_key') && document.getElementById('y_temp_photo_key').value);
                        valid = (el.files && el.files.length > 0) || tkOK;
                    } else if (el.tagName === 'SELECT' || el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                        valid = !!String(el.value || '').trim();
                    }
                    if (!valid) {
                        // Try to find the label for this field
                        const label = card.querySelector('label[for="'+el.name+'"]') || 
                                     card.querySelector('label[for="'+el.id+'"]') || 
                                     (el.previousElementSibling && el.previousElementSibling.tagName === 'LABEL') ? 
                                     el.previousElementSibling : null;
                        if (label) {
                            invalidFieldLabel = label.textContent.replace('*', '').trim();
                        } else {
                            invalidFieldLabel = 'A required field';
                        }
                        firstInvalid = el;
                        break;
                    }
                }
                // Special case for photo on card 1
                if (i === 1) {
                    var f = document.getElementById('y_student_photo');
                    var tk = (tempKeyInput && tempKeyInput.value) ? true : false;
                    if ((!f || !f.files || !f.files[0]) && !tk) {
                        firstInvalid = f;
                        invalidFieldLabel = 'Student photo';
                    }
                    // Also validate birth date fields
                    const yearSelect = document.getElementById('y_birth_year');
                    const monthSelect = document.getElementById('y_birth_month');
                    const daySelect = document.getElementById('y_birth_day');
                    if (yearSelect && monthSelect && daySelect) {
                        if (!yearSelect.value || !monthSelect.value || !daySelect.value) {
                            // Find the label for birth date
                            const birthDateLabel = card.querySelector('label[for="y_birth_year"]') || 
                                                card.querySelector('label');
                            if (birthDateLabel) {
                                invalidFieldLabel = birthDateLabel.textContent.replace('*', '').trim();
                            } else {
                                invalidFieldLabel = 'Birth date';
                            }
                            firstInvalid = yearSelect;
                        }
                    }
                }
                if (firstInvalid) {
                    // Hide submitting indicator if validation fails
                    if (indicator) indicator.classList.add('hidden');
                    // Re-enable submit button
                    try {
                        var submitBtn = document.getElementById('youth-register-button');
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('opacity-60','cursor-not-allowed'); }
                    } catch(_) {}
                    // Switch to the card with the error
                    document.getElementById('y-card-'+currentCard).classList.remove('active');
                    document.getElementById('y-card-'+i).classList.add('active');
                    window.__yCurrentCard = i;
                    if (typeof window.updateYProgress === 'function') {
                        window.updateYProgress();
                    }
                    // Scroll to the invalid field
                    try { firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch(_) {}
                    // Show specific error message
                    if (invalidFieldLabel) {
                        showError('እባክዎ "' + invalidFieldLabel + '" መረጃ ይሙሉ።');
                    } else {
                        showError('እባክዎ ያለበትን መረጃ ይሙሉ።');
                    }
                    console.log('Form validation failed - missing field:', invalidFieldLabel);
                    e.preventDefault();
                    return false;
                }
            }
            // If we get here, all validations passed -> Show review modal instead of immediate submit
            console.log('All validations passed, opening review modal');
            e.preventDefault();
            try {
                var rc = document.getElementById('y-review-content');
                var modal = document.getElementById('y-review-modal');
                rc.innerHTML = '';
                var section = (title)=>{
                    const wrap = document.createElement('div');
                    const h = document.createElement('h4'); h.className='text-sm font-bold text-blue-700 mb-2'; h.textContent=title; wrap.appendChild(h);
                    const grid = document.createElement('div'); grid.className='grid grid-cols-1 md:grid-cols-2 gap-2'; wrap.appendChild(grid);
                    return {wrap, grid};
                };
                const row = (label, value)=>{
                    const item = document.createElement('div');
                    item.className='flex items-start gap-2 p-2 bg-white rounded border border-gray-100';
                    const l = document.createElement('div'); l.className='w-32 flex-shrink-0 text-gray-600 font-semibold'; l.textContent=label;
                    const v = document.createElement('div'); v.className='text-gray-900'; v.textContent = value||'';
                    item.appendChild(l); item.appendChild(v);
                    return item;
                };
                // Photo
                const preview = document.getElementById('y-photo-preview');
                if (preview && preview.src) {
                    const photoSec = section('ፎቶ');
                    const ph = document.createElement('div');
                    ph.className='p-2';
                    const img = document.createElement('img'); img.src = preview.src; img.className='w-24 h-24 rounded-full object-cover ring-2 ring-blue-200';
                    ph.appendChild(img);
                    photoSec.grid.appendChild(ph);
                    rc.appendChild(photoSec.wrap);
                }
                const fd = new FormData(form);
                // Student
                const s1 = section('የተማሪ መረጃ');
                s1.grid.appendChild(row('ሙሉ ስም', fd.get('full_name')));
                s1.grid.appendChild(row('የክርስትና ስም', fd.get('christian_name')));
                s1.grid.appendChild(row('ጾታ', fd.get('gender')));
                s1.grid.appendChild(row('የትውልድ ቀን (ዓ.ም)', [document.getElementById('y_birth_year')?.value, document.getElementById('y_birth_month')?.value, document.getElementById('y_birth_day')?.value].filter(Boolean).join('-')));
                s1.grid.appendChild(row('ክፍል', fd.get('current_grade')));
                s1.grid.appendChild(row('ቆይታ', fd.get('school_year_start')));
                s1.grid.appendChild(row('የትምህርት ደረጃ', fd.get('education_level')));
                s1.grid.appendChild(row('የሞያ ዘርፍ', fd.get('field_of_study')));
                rc.appendChild(s1.wrap);
                // Address
                const s2 = section('አድራሻ');
                s2.grid.appendChild(row('ክ/ከተማ', fd.get('sub_city')));
                s2.grid.appendChild(row('ወረዳ', fd.get('district')));
                s2.grid.appendChild(row('ሰፈር', fd.get('specific_area')));
                s2.grid.appendChild(row('ቤት ቁጥር', fd.get('house_number')));
                rc.appendChild(s2.wrap);
                // Emergency
                const s3 = section('የአደጋ ጊዜ መረጃ');
                s3.grid.appendChild(row('ስም', fd.get('emergency_name')));
                s3.grid.appendChild(row('ስልክ', fd.get('emergency_phone')));
                s3.grid.appendChild(row('ተዋጭ ስልክ', fd.get('emergency_alt_phone')));
                s3.grid.appendChild(row('አድራሻ', fd.get('emergency_address')));
                rc.appendChild(s3.wrap);
                // Optional
                const s4 = section('ተጨማሪ መረጃ');
                s4.grid.appendChild(row('ልዩ ፍላጎት', fd.get('special_interests')));
                s4.grid.appendChild(row('የአካል ጉዳት', fd.get('physical_disability')));
                s4.grid.appendChild(row('የተዘዋወረ', fd.get('transferred_from_other_school')));
                s4.grid.appendChild(row('ከሌላ እምነት', fd.get('came_from_other_religion')));
                rc.appendChild(s4.wrap);

                modal.classList.remove('hidden');
                try { document.getElementById('youth-submitting-indicator').classList.add('hidden'); } catch(_) {}
                try { var sb = document.getElementById('youth-register-button'); if (sb) { sb.disabled=false; sb.classList.remove('opacity-60','cursor-not-allowed'); } } catch(_) {}
            } catch(err) { console.error(err); }
            
        });
// Autosave/restore like main registration
        const STORAGE_KEY = 'YOUTH_FORM_AUTOSAVE';
        const CARD_KEY = 'YOUTH_FORM_CARD';
        function debounce(fn, delay){ let t; return function(){ const ctx=this, args=arguments; clearTimeout(t); t=setTimeout(()=>fn.apply(ctx,args), delay); }; }
        function saveForm(){
            const data = {};
            Array.from(form.elements).forEach(el => {
                if (!el.name) return;
                if (el.type === 'file') return;
                if (el.type === 'radio' || el.type === 'checkbox') {
                    if (el.checked) data[el.name] = el.value;
                } else {
                    data[el.name] = el.value;
                }
            });
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            localStorage.setItem(CARD_KEY, String(window.__yCurrentCard || 1));
        }
        const saveFormDebounced = debounce(saveForm, 400);
        function restoreForm(){
            const savedRaw = localStorage.getItem(STORAGE_KEY);
            if (!savedRaw) return {};
            let saved = {};
            try { saved = JSON.parse(savedRaw) || {}; } catch(_) { saved = {}; }
            Array.from(form.elements).forEach(el => {
                if (!el.name) return;
                if (!(el.name in saved)) return;
                if (el.type === 'radio' || el.type === 'checkbox') {
                    el.checked = (el.value === saved[el.name]);
                } else if (el.type !== 'file') {
                    el.value = saved[el.name];
                }
            });
            // Toggle spiritual father area
            const sf = saved['has_spiritual_father'];
            if (sf === 'own' || sf === 'family') { window.toggleYSpiritualFather(true); }
            else if (sf === 'none') { window.toggleYSpiritualFather(false); }
            return saved;
        }
        // Bind autosave
        form.addEventListener('input', saveFormDebounced);
        form.addEventListener('change', saveFormDebounced);
        try { window.addEventListener('beforeunload', saveForm); } catch(_) {}
        try {
            document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'hidden') { saveForm(); } });
        } catch(_) {}
        // Initial restore
        const restored = restoreForm();
    });

    // Photo preview and camera functions (youth form)
    document.addEventListener('DOMContentLoaded', function(){
        const input = document.getElementById('y_student_photo');
        const preview = document.getElementById('y-photo-preview');
        const placeholder = document.getElementById('y-photo-placeholder');
        const tempKeyInput = document.getElementById('y_temp_photo_key');
        const TEMP_KEY_LS = 'YOUTH_TEMP_PHOTO_KEY';
        const TEMP_URL_LS = 'YOUTH_TEMP_PHOTO_URL';
        // Restore temp photo from previous session (if any)
        try {
            const savedKey = localStorage.getItem(TEMP_KEY_LS);
            const savedUrl = localStorage.getItem(TEMP_URL_LS);
            if (savedKey && savedUrl) {
                if (tempKeyInput && !tempKeyInput.value) tempKeyInput.value = savedKey;
                if (preview && savedUrl) {
                    preview.src = savedUrl;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                }
            }
        } catch(_) {}
        function dataURLToFile(dataUrl, filename){
            const arr = dataUrl.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while(n--){ u8arr[n] = bstr.charCodeAt(n); }
            return new File([u8arr], filename, {type:mime});
        }
        function compressDataUrl(dataUrl, maxDim=800, quality=0.8){
            return new Promise(resolve => {
                const img = new Image();
                img.onload = function(){
                    let { width, height } = img;
                    const scale = Math.min(1, maxDim/Math.max(width, height));
                    const nw = Math.round(width*scale);
                    const nh = Math.round(height*scale);
                    const canvas = document.createElement('canvas');
                    canvas.width = nw; canvas.height = nh;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, nw, nh);
                    const out = canvas.toDataURL('image/jpeg', quality);
                    resolve(out);
                };
                img.src = dataUrl;
            });
        }
        async function compressFileToJPEG(file, maxDim=800, quality=0.8){
            // Read as data URL, draw to canvas, export as JPEG
            const dataUrl = await new Promise((res, rej)=>{ const r=new FileReader(); r.onload=()=>res(r.result); r.onerror=rej; r.readAsDataURL(file); });
            const compressedDataUrl = await compressDataUrl(String(dataUrl), maxDim, quality);
            return dataURLToFile(compressedDataUrl, (file.name||'photo') + '.jpg');
        }
        async function uploadTempFile(file){
            try {
                const fd = new FormData();
                fd.append('photo', file);
                const res = await fetch('api/upload_temp_photo.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data || !data.success || !data.key) throw new Error('Upload failed');
                // Persist key + constructed url
                const url = 'uploads/tmp/' + data.key;
                localStorage.setItem(TEMP_KEY_LS, data.key);
                localStorage.setItem(TEMP_URL_LS, url);
                if (tempKeyInput) tempKeyInput.value = data.key;
                // Update preview to temp URL for persistence
                preview.src = url; preview.classList.remove('hidden'); placeholder.classList.add('hidden');
            } catch(e) {
                console.error('Temp upload error', e);
            }
        }
        if (input) {
            input.addEventListener('change', function(){
                if (input.files && input.files[0]){
                    const original = input.files[0];
                    // Show quick preview from original while we compress
                    const reader = new FileReader();
                    reader.onload = function(ev){ preview.src = ev.target.result; preview.classList.remove('hidden'); placeholder.classList.add('hidden'); }
                    reader.readAsDataURL(original);
                    // Compress then upload
                    (async () => {
                        try {
                            const compressed = await compressFileToJPEG(original, 800, 0.8);
                            const dt = new DataTransfer(); dt.items.add(compressed); input.files = dt.files;
                            await uploadTempFile(compressed);
                        } catch(err){ console.error(err); await uploadTempFile(original); }
                    })();
                }
            });
        }

        let yCameraStream = null;
        let yFacing = 'user';
        window.openYCameraModal = function(){
            document.getElementById('y-camera-modal').classList.remove('hidden');
            startYCamera(yFacing);
        }
        window.closeYCameraModal = function(){
            document.getElementById('y-camera-modal').classList.add('hidden');
            const video = document.getElementById('y-camera-video');
            if (yCameraStream){ yCameraStream.getTracks().forEach(t=>t.stop()); yCameraStream=null; }
            video.srcObject = null;
        }
        function startYCamera(facing){
            const video = document.getElementById('y-camera-video');
            if (yCameraStream){ yCameraStream.getTracks().forEach(t=>t.stop()); yCameraStream=null; }
            navigator.mediaDevices.getUserMedia({ video: { facingMode: facing }})
            .then(stream => { yCameraStream = stream; video.srcObject = stream; })
            .catch(()=>{ alert('Camera access denied or not available.'); closeYCameraModal(); });
        }
        window.switchYCamera = function(){ yFacing = (yFacing==='user'?'environment':'user'); startYCamera(yFacing); }
        window.captureYPhoto = function(){
            const video = document.getElementById('y-camera-video');
            const canvas = document.getElementById('y-camera-canvas');
            canvas.width = video.videoWidth || 320;
            canvas.height = video.videoHeight || 240;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
            // preview
            preview.src = dataUrl; preview.classList.remove('hidden'); placeholder.classList.add('hidden');
            // convert to file and attach to input
            fetch(dataUrl).then(r=>r.arrayBuffer()).then(async buf=>{
                try {
                    const file = new File([buf], 'captured_photo.jpg', { type: 'image/jpeg' });
                    const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
                    // Upload immediately to temp storage
                    await uploadTempFile(file);
                } catch(err){ console.error(err); }
            });
            closeYCameraModal();
        }
        window.openYPhotoRequired = function(){
            var m = document.getElementById('y-photo-required');
            if (m) m.classList.remove('hidden');
        }
        window.closeYPhotoRequired = function(){
            var m = document.getElementById('y-photo-required');
            if (m) m.classList.add('hidden');
        }
        try {
            var reviewCancel = document.getElementById('y-review-cancel');
            var reviewConfirm = document.getElementById('y-review-confirm');
            var reviewModal = document.getElementById('y-review-modal');
            if (reviewCancel) reviewCancel.addEventListener('click', function(){ reviewModal.classList.add('hidden'); });
            if (reviewConfirm) reviewConfirm.addEventListener('click', function(){
                // Do not clear autosave here; clear only on confirmed success page
                window.__yReviewConfirmed = true;
                try { document.getElementById('youth-submitting-indicator').classList.remove('hidden'); } catch(_) {}
                try { var sb = document.getElementById('youth-register-button'); if (sb) { sb.disabled=true; sb.classList.add('opacity-60','cursor-not-allowed'); } } catch(_) {}
                reviewModal.classList.add('hidden');
                try { document.getElementById('youth-form').submit(); } catch(e) { console.error(e); }
            });
        } catch(_) {}
    });

    // Spiritual father toggle (youth)

    // Clear form mid-registration for youth
    function clearYouthForm(){
        if (!confirm('በእርግጥ ፎርሙን ሙሉ በሙሉ ማጥፋት ትፈልጋለህ/ሽ?')) return;
        try {
            localStorage.removeItem('YOUTH_FORM_AUTOSAVE');
            localStorage.removeItem('YOUTH_FORM_CARD');
            localStorage.removeItem('YOUTH_TEMP_PHOTO_KEY');
            localStorage.removeItem('YOUTH_TEMP_PHOTO_URL');
        } catch(_) {}
        try {
            const form = document.getElementById('youth-form');
            if (form) form.reset();
        } catch(_) {}
        try {
            const tpk = document.getElementById('y_temp_photo_key'); if (tpk) tpk.value='';
            const input = document.getElementById('y_student_photo'); if (input) input.value='';
            const preview = document.getElementById('y-photo-preview');
            const placeholder = document.getElementById('y-photo-placeholder');
            if (preview && placeholder) { preview.src=''; preview.classList.add('hidden'); placeholder.classList.remove('hidden'); }
        } catch(_) {}
        try { yPrev(1); } catch(_) {}
    }

    window.toggleYSpiritualFather = function(show){
        const el = document.getElementById('y-spiritual-father-info');
        if (!el) return; if (show) el.classList.remove('hidden'); else el.classList.add('hidden');
    }
    // Card navigation & progress
    document.addEventListener('DOMContentLoaded', function(){
        let yCurrent = 1; const yTotal = 5;
        window.__yCardAPIReady = true;
        function updateProgress(){
            const pct = ((yCurrent-1)/(yTotal-1))*100; document.getElementById('y-progress-bar').style.width = pct+'%';
            for(let i=1;i<=yTotal;i++){
                const step = document.getElementById('y-step-'+i);
                step.classList.remove('active','completed');
                if(i<yCurrent) step.classList.add('completed');
                else if(i===yCurrent) step.classList.add('active');
            }
        }
        function showCard(n){
            document.getElementById('y-card-'+yCurrent).classList.remove('active');
            document.getElementById('y-card-'+n).classList.add('active');
            yCurrent = n; updateProgress(); window.scrollTo(0,0);
            window.__yCurrentCard = yCurrent;
            try { localStorage.setItem('YOUTH_FORM_CARD', String(yCurrent)); } catch(_) {}
        }
        function showError(msg){
            var box = document.getElementById('y-error');
            if(!box) return alert(msg);
            box.textContent = msg;
            box.classList.remove('hidden');
        }
        function clearError(){
            var box = document.getElementById('y-error');
            if(!box) return; box.classList.add('hidden'); box.textContent = '';
        }
        function validateCard(n){
            const card = document.getElementById('y-card-'+n);
            const required = card.querySelectorAll('[required]');
            let ok = true;
            let firstInvalid = null;
            let invalidFieldLabel = null;
            
            for (var i = 0; i < required.length; i++) {
                var el = required[i];
                var valid = true;
                if (el.type === 'radio') {
                    var any = card.querySelector('input[name="'+el.name+'"]:checked');
                    valid = !!any;
                    if (!valid) {
                        // Find the label for this radio group
                        const label = card.querySelector('label[for="'+el.name+'"]') || card.querySelector('label');
                        invalidFieldLabel = label ? label.textContent : 'One of the radio options';
                    }
                } else if (el.type === 'file') {
                    var tkVal = (document.getElementById('y_temp_photo_key') && document.getElementById('y_temp_photo_key').value) ? true : false;
                    valid = (el.files && el.files.length > 0) || tkVal;
                    if (!valid) {
                        invalidFieldLabel = 'Student photo';
                    }
                } else if (el.tagName === 'SELECT' || el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    valid = !!String(el.value || '').trim();
                    if (!valid) {
                        // Try to find the label for this field
                        const label = card.querySelector('label[for="'+el.name+'"]') || 
                                     card.querySelector('label[for="'+el.id+'"]') || 
                                     (el.previousElementSibling && el.previousElementSibling.tagName === 'LABEL') ? 
                                     el.previousElementSibling : null;
                        
                        // If we still don't have a label, try to find it by traversing parents
                        if (!label) {
                            // Look for a label that contains this element
                            const labels = card.querySelectorAll('label');
                            for (let j = 0; j < labels.length; j++) {
                                if (labels[j].contains(el) || labels[j].nextElementSibling === el) {
                                    invalidFieldLabel = labels[j].textContent.replace('*', '').trim();
                                    break;
                                }
                            }
                            if (!invalidFieldLabel) {
                                invalidFieldLabel = 'A required field';
                            }
                        } else {
                            invalidFieldLabel = label.textContent.replace('*', '').trim();
                        }
                    }
                }
                if (!valid) {
                    ok = false;
                    if (!firstInvalid) firstInvalid = el;
                    try { el.classList.add('border-red-500'); } catch(_) {}
                } else {
                    try { el.classList.remove('border-red-500'); } catch(_) {}
                }
            }
            // Ensure photo chosen on Card 1; if not, prompt modal to choose/capture
            if (n === 1) {
                var f = document.getElementById('y_student_photo');
                var tk = (document.getElementById('y_temp_photo_key') && document.getElementById('y_temp_photo_key').value) ? true : false;
                if ((!f || !f.files || !f.files[0]) && !tk) {
                    try { openYPhotoRequired(); } catch(_) {}
                    ok = false;
                    invalidFieldLabel = 'Student photo';
                    firstInvalid = f;
                }
            }
            if (!ok && firstInvalid) {
                try { firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch(_) {}
                // Show specific error message
                if (invalidFieldLabel) {
                    showError('እባክዎ "' + invalidFieldLabel + '" መረጃ ይሙሉ።');
                } else {
                    showError('እባክዎ ያለበትን መረጃ ይሙሉ።');
                }
                return false; // Return false instead of ok
            }
            return true; // Return true if everything is valid
        }
        window.yNext = function(n){
            // Do not validate on step navigation; validate only on final submit
            clearError();
            showCard(n);
        }
        window.yPrev = function(n){ showCard(n); }
        // Fallback event binding for buttons without relying on inline handlers
        // Cross-browser event binding (no IIFE to avoid parser issues on some engines)
        var nextBtns = document.querySelectorAll('[data-y-next]');
        for (var i=0; i<nextBtns.length; i++) {
            nextBtns[i].addEventListener('click', function(ev){
                var n = parseInt(this.getAttribute('data-y-next'), 10);
                try { window.yNext(n); } catch(e) { console.error(e); showError('Navigation error. እባክዎ ገጹን ያድሱ.'); }
            });
        }
        var prevBtns = document.querySelectorAll('[data-y-prev]');
        for (var j=0; j<prevBtns.length; j++) {
            prevBtns[j].addEventListener('click', function(ev){
                var n = parseInt(this.getAttribute('data-y-prev'), 10);
                try { window.yPrev(n); } catch(e) { console.error(e); showError('Navigation error. እባክዎ ገጹን ያድሱ.'); }
            });
        }
        // Delegation fallback
        document.addEventListener('click', function(ev){
            const t = ev.target.closest('[data-y-next],[data-y-prev]');
            if (!t) return;
            const nNext = t.getAttribute('data-y-next');
            const nPrev = t.getAttribute('data-y-prev');
            if (nNext) { window.yNext(parseInt(nNext,10)); }
            if (nPrev) { window.yPrev(parseInt(nPrev,10)); }
        });
        // Restore last open card
        try {
            const savedCard = parseInt(localStorage.getItem('YOUTH_FORM_CARD')||'1',10);
            if (savedCard && savedCard>=1 && savedCard<=yTotal) {
                document.getElementById('y-card-'+1).classList.remove('active');
                document.getElementById('y-card-'+savedCard).classList.add('active');
                yCurrent = savedCard;
            }
        } catch(_) {}
        updateProgress();
    });

    </script>
</body>
</html>


