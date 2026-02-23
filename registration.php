<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የፍኖተ ሰላም ሰ/ት/ቤት ምዝገባ ፎርም</title>

    <!-- Critical CSS (inline) -->
    <style><?php include 'assets/css/dist/critical.min.css'; ?></style>

    <!-- Preload optimized fonts -->
    <link rel="preload" href="assets/fonts/optimized-fonts.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="assets/fonts/optimized-fonts.css"></noscript>

    <!-- Async CSS loader -->
    <script><?php include 'assets/js/dist/css-loader.js'; ?></script>

    <!-- Optimized JavaScript libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethiopian-date/1.0.0/ethiopian-date.min.js"></script>
    <style>
        body {
            font-family: 'Noto Sans Ethiopic', sans-serif;
            direction: ltr;
        }
        .form-card {
            position: absolute;
            left: -9999px;
        }
        .form-card.active {
            position: static;
            left: auto;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .progress-step {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: #e5e7eb;
            color: #6b7280;
            font-weight: bold;
        }
        .progress-step.active {
            background-color: #3b82f6;
            color: white;
        }
        .progress-step.completed {
            background-color: #10b981;
            color: white;
        }

                .datepicker {
            z-index: 1000 !important; /* Ensure the datepicker appears above other elements */
        }

    </style>
</head>
<body class="bg-gray-50">
<?php
// Inject children form configuration for zero extra HTTP calls
try {
    require_once __DIR__ . '/includes/form_config.php';
    $cfgRows = get_form_config('children', $pdo);
    $cfgAssoc = [];
    foreach ($cfgRows as $r) {
        $cfgAssoc[$r['field_key']] = [
            'label' => $r['label'],
            'placeholder' => $r['placeholder'],
            'required' => (int)$r['required'],
            'sort_order' => (int)$r['sort_order']
        ];
    }
    echo '<script>window.FORM_CFG_CHILDREN=' . json_encode($cfgAssoc, JSON_UNESCAPED_UNICODE) . ';</script>';
} catch (Throwable $e) { /* ignore */ }
?>
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="flex flex-col items-center mb-6">
            <a href="welcome.php">
                <img src="uploads/689636ec11381_finot logo.png" alt="Finot Logo" loading="lazy" class="w-24 h-24 md:w-32 md:h-32 rounded-full shadow-lg object-contain border-4 border-blue-200 dark:border-blue-800 bg-white mb-2 transition-all duration-300 hover:scale-105">
            </a>
            <h1 class="text-3xl font-bold text-center text-blue-800 mt-2">የተማሪ ምዝገባ ፎርም</h1>
            <div class="mt-2">
                <button type="button" onclick="clearChildrenForm()" class="px-3 py-1 text-xs md:text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 rounded">ፎርም አጥራ</button>
            </div>
        </div>
        
        <!-- Progress indicator -->
        <div class="flex justify-between items-center mb-8 relative">
            <div class="absolute top-1/2 left-0 right-0 h-1 bg-gray-200 -z-10"></div>
            <div id="progress-bar" class="absolute top-1/2 left-0 h-1 bg-blue-500 -z-10" style="width: 0%"></div>
            
            <?php for ($i = 1; $i <= 9; $i++): ?>
                <div class="progress-step" id="step-<?= $i ?>"><?= $i ?></div>
            <?php endfor; ?>
        </div>
        
        <form id="student-form" action="process.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg p-6" novalidate>
            <input type="hidden" name="temp_photo_key" id="c_temp_photo_key" value="">
            <input type="hidden" name="temp_photo_dataurl" id="c_temp_photo_dataurl" value="">
            <!-- Card 1: Student Information -->
            <div class="form-card active" id="card-1">
                <h2 class="text-2xl font-bold mb-6 text-blue-700 border-b pb-2">1. የተማሪው መረጃ</h2>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="student_photo">የተማሪ ፎቶ <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <div class="flex items-center">
                        <div class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                            <img id="photo-preview" src="" alt="" class="hidden w-full h-full object-cover">
                            <span id="photo-placeholder" class="text-gray-500">ፎቶ</span>
                        </div>
                        <input type="file" id="student_photo" name="student_photo" accept="image/*" class="ml-4 hidden">
                        <button type="button" onclick="document.getElementById('student_photo').click()" class="ml-4 px-3 py-1 text-xs md:text-sm bg-blue-500 text-white rounded hover:bg-blue-600 transition-all duration-200">ፎቶ ይምረጡ</button>
                        <button type="button" onclick="openCameraModal()" class="ml-2 px-3 py-1 text-xs md:text-sm bg-green-500 text-white rounded hover:bg-green-600 transition-all duration-200">ፎቶ አንሳ</button>
                    </div>
                    <!-- Camera Modal -->
                    <div id="camera-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                        <div class="bg-white rounded-lg p-6 shadow-lg relative w-80">
                            <button type="button" onclick="closeCameraModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">&times;</button>
                            <video id="camera-video" autoplay playsinline class="w-full h-48 bg-gray-200 rounded"></video>
                            <canvas id="camera-canvas" class="hidden"></canvas>
                            <div class="flex justify-between mt-4">
                                <button type="button" onclick="switchCamera()" class="px-3 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Switch Camera</button>
                                <button type="button" onclick="capturePhoto()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">ፎቶ አንሳ</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="full_name">ሙሉ ስም እስከ አያት <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <textarea id="full_name" name="full_name" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="christian_name">የክርስትና ስም <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <textarea id="christian_name" name="christian_name" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                </div>
                
                <div class="mb-4">
                    <div class="flex flex-col md:flex-row md:space-x-4">
                        <div class="md:w-1/2">
                            <label class="block text-gray-700 mb-2 flex items-center">ጾታ <span class="text-red-500 ml-1 text-sm">*</span></label>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="gender" value="male" class="h-5 w-5 text-blue-600" required>
                                    <span class="ml-2">ወንድ</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="gender" value="female" class="h-5 w-5 text-blue-600">
                                    <span class="ml-2">ሴት</span>
                                </label>
                            </div>
                        </div>
<div class="md:w-1/2 mt-4 md:mt-0">
    <label class="block text-gray-700 mb-2 flex items-center" for="birth_year_et">
        የተወለዱበት ቀን (ዓ.ም) <span class="text-red-500 ml-1 text-sm">*</span>
    </label>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <select id="birth_year_et" name="birth_year_et" class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-full" required></select>
        <select id="birth_month_et" name="birth_month_et" class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-full" required></select>
        <select id="birth_day_et" name="birth_day_et" class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-full" required></select>
    </div>
    
</div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="current_grade">በ2018 ዓ.ም ሰንበት ት/ቤት ስንተኛ ክፍል ነህ/ነሽ? <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <select id="current_grade" name="current_grade" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
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
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="school_year_start">በሰንበት ት/ቤት የነበረህ/ሽ ቆይታ? <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <select id="school_year_start" name="school_year_start" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">-- ይምረጡ --</option>
                        <option value="2018">አዲስ</option>
                        <?php for ($year = 1995; $year <= 2017; $year++): ?>
                            <option value="<?= $year ?>"><?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="regular_school_name">ዓላማዊ የት/ቤት ስም እና የክፍል ደረጃ(በ2018 ዓ.ም) <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <input type="text" id="regular_school_name" name="regular_school_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="student_phone">የተማሪው ስልክ ቁጥር</label>
                    <input type="tel" id="student_phone" name="student_phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex justify-between mt-8">
                    <button type="button" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400" disabled>ቀድሞ</button>
                    <button type="button" onclick="nextCard(2)" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 2: Spiritual Father Information -->
            <div class="form-card" id="card-2">
                <h2 class="text-2xl font-bold mb-6 text-blue-700 border-b pb-2">2.ንስሐ አባት መረጃ</h2>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">የንስሐ አባት አለህ/ሽ?</label>
                    <div class="space-y-2">
                        <label class="inline-flex items-center">
                            <input type="radio" name="has_spiritual_father" value="own" class="h-5 w-5 text-blue-600" onchange="toggleSpiritualFatherInfo(true)" required>
                            <span class="ml-2">የራሴ አለኝ</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="has_spiritual_father" value="family" class="h-5 w-5 text-blue-600" onchange="toggleSpiritualFatherInfo(true)">
                            <span class="ml-2">የቤተሰብ (የጋራ)</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="has_spiritual_father" value="none" class="h-5 w-5 text-blue-600" onchange="toggleSpiritualFatherInfo(false)">
                            <span class="ml-2">የለኝም</span>
                        </label>
                    </div>
                </div>
                
                <div id="spiritual-father-info" class="hidden">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">የንስሐ አባት ካሎት እነዚህን መረጃዎች ይሙሉ</h3>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2" for="spiritual_father_name">የካህኑ ስም</label>
                        <input type="text" id="spiritual_father_name" name="spiritual_father_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2" for="spiritual_father_phone">የካህኑ ስልክ ቁጥር</label>
                        <input type="tel" id="spiritual_father_phone" name="spiritual_father_phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2" for="spiritual_father_church">ካህኑ የሚያገለግሉበት ደብር</label>
                        <input type="text" id="spiritual_father_church" name="spiritual_father_church" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="flex justify-between mt-8">
                    <button type="button" onclick="prevCard(1)" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">ቀድሞ</button>
                    <button type="button" onclick="nextCard(3)" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 3: Address Information -->
            <div class="form-card" id="card-3">
                <h2 class="text-2xl font-bold mb-6 text-blue-700 border-b pb-2">3. የመኖሪያ አድራሻ</h2>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="sub_city">ክ/ከተማ <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <input type="text" id="sub_city" name="sub_city" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="district">ወረዳ</label>
                    <input type="text" id="district" name="district" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="specific_area">የሰፈሩ ልዩ ስም <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <input type="text" id="specific_area" name="specific_area" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="house_number">የቤት ቁጥር (ካለ)</label>
                    <input type="text" id="house_number" name="house_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex justify-between mt-8">
                    <button type="button" onclick="prevCard(2)" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">ቀድሞ</button>
                    <button type="button" onclick="nextCard(4)" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 4: Living Situation -->
            <div class="form-card" id="card-4">
                <h2 class="text-2xl font-bold mb-6 text-blue-700 border-b pb-2">4. የወላጅ/ሞግዚት አባት እና የወላጅ/ሞግዚት እናት መጠይቅ</h2>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">ልጅዎ ከማን ጋር ይኖራሉ?</label>
                    <div class="space-y-2">
                        <label class="inline-flex items-center">
                            <input type="radio" name="living_with" value="both_parents" class="h-5 w-5 text-blue-600" onchange="showParentInfo('both')" required>
                            <span class="ml-2">ከወላጅ</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="living_with" value="father_only" class="h-5 w-5 text-blue-600" onchange="showParentInfo('father')">
                            <span class="ml-2">ከአባት ብቻ</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="living_with" value="mother_only" class="h-5 w-5 text-blue-600" onchange="showParentInfo('mother')">
                            <span class="ml-2">ከእናት ብቻ</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="living_with" value="relative_or_guardian" class="h-5 w-5 text-blue-600" onchange="showParentInfo('guardian')">
                            <span class="ml-2">ከዘመድ ወይም አሳዳጊ</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-between mt-8">
                    <button type="button" onclick="prevCard(3)" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">ቀድሞ</button>
                    <button type="button" onclick="nextCardBasedOnLivingWith()" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 5: Both Parents Information -->
            <div class="form-card" id="card-5">
                <h2 class="text-2xl font-bold mb-6 text-blue-700 border-b pb-2">5. ወላጅ/ሞግዚት አባት እና የወላጅ/ሞግዚት እናት መረጃ</h2>
                
                <h3 class="text-xl font-semibold mb-4 text-gray-700">የወላጅ አባት መረጃ</h3>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="father_full_name">የወላጅ አባት ሙሉ ስም <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <input type="text" id="father_full_name" name="father_full_name_both" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="father_christian_name">የወላጅ አባት ክርስትና ስም <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <input type="text" id="father_christian_name" name="father_christian_name_both" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="father_occupation">የወላጅ አባት የስራ ዘርፍ(ሙያ)</label>
                    <input type="text" id="father_occupation" name="father_occupation_both" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="father_phone">የወላጅ አባት ስልክ ቁጥር <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <input type="tel" id="father_phone" name="father_phone_both" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="09XXXXXXXX">
                </div>
                
                <h3 class="text-xl font-semibold mb-4 text-gray-700 mt-8">የወላጅ እናት መረጃ</h3>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="mother_full_name">የወላጅ እናት ሙሉ ስም <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <input type="text" id="mother_full_name" name="mother_full_name_both" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="mother_christian_name">የወላጅ እናት ክርስትና ስም <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <input type="text" id="mother_christian_name" name="mother_christian_name_both" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="mother_occupation">የወላጅ እናት የስራ ዘርፍ(ሙያ)</label>
                    <input type="text" id="mother_occupation" name="mother_occupation_both" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="mother_phone">የወላጅ እናት ስልክ ቁጥር <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <input type="tel" id="mother_phone" name="mother_phone_both" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="09XXXXXXXX">
                </div>
                
                <div class="flex justify-between mt-8">
                    <button type="button" onclick="prevCard(4)" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">ቀድሞ</button>
                    <button type="button" onclick="nextCard(9)" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 6: Father Only Information -->
            <div class="form-card" id="card-6">
                <h2 class="text-2xl font-bold mb-6 text-blue-700 border-b pb-2">6. የወላጅ/ሞግዚት አባት ብቻ መረጃ</h2>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="father_only_full_name">የወላጅ አባት ሙሉ ስም</label>
                    <input type="text" id="father_only_full_name" name="father_full_name_only" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="father_only_christian_name">የወላጅ አባት ክርስትና ስም</label>
                    <input type="text" id="father_only_christian_name" name="father_christian_name_only" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="father_only_occupation">የወላጅ አባት የስራ ዘርፍ(ሙያ)</label>
                    <input type="text" id="father_only_occupation" name="father_occupation_only" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="father_only_phone">የወላጅ አባት ስልክ ቁጥር</label>
                    <input type="tel" id="father_only_phone" name="father_phone_only" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="09XXXXXXXX">
                </div>
                
                <div class="flex justify-between mt-8">
                    <button type="button" onclick="prevCard(4)" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">ቀድሞ</button>
                    <button type="button" onclick="nextCard(9)" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 7: Mother Only Information -->
            <div class="form-card" id="card-7">
                <h2 class="text-2xl font-bold mb-6 text-blue-700 border-b pb-2">7. የወላጅ/ሞግዚት እናት ብቻ መረጃ</h2>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="mother_only_full_name">የወላጅ እናት ሙሉ ስም</label>
                    <input type="text" id="mother_only_full_name" name="mother_full_name_only" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="mother_only_christian_name">የወላጅ እናት ክርስትና ስም</label>
                    <input type="text" id="mother_only_christian_name" name="mother_christian_name_only" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="mother_only_occupation">የወላጅ እናት የስራ ዘርፍ(ሙያ)</label>
                    <input type="text" id="mother_only_occupation" name="mother_occupation_only" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="mother_only_phone">የወላጅ እናት ስልክ ቁጥር</label>
                    <input type="tel" id="mother_only_phone" name="mother_phone_only" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="09XXXXXXXX">
                </div>
                
                <div class="flex justify-between mt-8">
                    <button type="button" onclick="prevCard(4)" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">ቀድሞ</button>
                    <button type="button" onclick="nextCard(9)" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 8: Guardian Information -->
            <div class="form-card" id="card-8">
                <h2 class="text-2xl font-bold mb-6 text-blue-700 border-b pb-2">8. የዘመድ ወይም አሳዳጊ መረጃ</h2>
                
                <h3 class="text-xl font-semibold mb-4 text-gray-700">የዘመድ(አሳዳጊ) አባት መረጃ</h3>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="guardian_father_full_name">የዘመድ(አሳዳጊ) አባት ሙሉ ስም</label>
                    <input type="text" id="guardian_father_full_name" name="guardian_father_full_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="guardian_father_christian_name">የዘመድ(አሳዳጊ) አባት ክርስትና ስም</label>
                    <input type="text" id="guardian_father_christian_name" name="guardian_father_christian_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="guardian_father_occupation">የዘመድ(አሳዳጊ) አባት የስራ ዘርፍ(ሙያ)</label>
                    <input type="text" id="guardian_father_occupation" name="guardian_father_occupation" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="guardian_father_phone">የዘመድ(አሳዳጊ) አባት ስልክ ቁጥር</label>
                    <input type="tel" id="guardian_father_phone" name="guardian_father_phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="09XXXXXXXX">
                </div>
                
                <h3 class="text-xl font-semibold mb-4 text-gray-700 mt-8">የዘመድ(አሳዳጊ) እናት መረጃ</h3>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="guardian_mother_full_name">የዘመድ(አሳዳጊ) እናት ሙሉ ስም</label>
                    <input type="text" id="guardian_mother_full_name" name="guardian_mother_full_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="guardian_mother_christian_name">የዘመድ(አሳዳጊ) እናት ክርስትና ስም</label>
                    <input type="text" id="guardian_mother_christian_name" name="guardian_mother_christian_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="guardian_mother_occupation">የዘመድ(አሳዳጊ) እናት የስራ ዘርፍ(ሙያ)</label>
                    <input type="text" id="guardian_mother_occupation" name="guardian_mother_occupation" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="guardian_mother_phone">የዘመድ(አሳዳጊ) እናት ስልክ ቁጥር</label>
                    <input type="tel" id="guardian_mother_phone" name="guardian_mother_phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="09XXXXXXXX">
                </div>
                
                <div class="flex justify-between mt-8">
                    <button type="button" onclick="prevCard(4)" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">ቀድሞ</button>
                    <button type="button" onclick="nextCard(9)" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 9: Additional Information -->
            <div class="form-card" id="card-9">
                <h2 class="text-2xl font-bold mb-6 text-blue-700 border-b pb-2">9. የተማሪው ተጨማሪ መረጃ</h2>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="special_interests">ልዩ ፍላጎትና ተሰጥዖ/ሞያ </label>
                    <textarea id="special_interests" name="special_interests" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="siblings_in_school">በሰንበት ት/ቤት ውስጥ የማይማሩትን ተማሪ/ዎች(እህት ወይም ወንድም) ካለ</label>
                    <textarea id="siblings_in_school" name="siblings_in_school" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="physical_disability">የአካል ጉዳት</label>
                    <textarea id="physical_disability" name="physical_disability" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="weak_side">ደካማ ጎን ካለ</label>
                    <textarea id="weak_side" name="weak_side" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="transferred_from_other_school">ከሌላ ሰንበት ት/ቤት የተዘዋወረ ካለ</label>
                    <textarea id="transferred_from_other_school" name="transferred_from_other_school" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="came_from_other_religion">ከሌላ እምነት የመጡ ካለ</label>
                    <textarea id="came_from_other_religion" name="came_from_other_religion" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex justify-between mt-8">
                    <button type="button" onclick="prevCard(getPreviousCard(9))" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">ቀድሞ</button>
                    <button type="submit" class="px-6 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">ምዝገባውን አጠናቅቅ</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Autosave and restore form data using localStorage
        const FORM_STORAGE_KEY = 'studentFormAutosave';

        function saveFormToLocalStorage() {
            const form = document.getElementById('student-form');
            const data = {};
            Array.from(form.elements).forEach(el => {
                if (el.name) {
                    if (el.type === 'radio') {
                        if (el.checked) data[el.name] = el.value;
                    } else if (el.type === 'checkbox') {
                        data[el.name] = el.checked;
                    } else if (el.type === 'file') {
                        // skip file inputs
                    } else {
                        data[el.name] = el.value;
                    }
                }
            });
            localStorage.setItem(FORM_STORAGE_KEY, JSON.stringify(data));
            // Persist ET birth date separately for robust restore
            try {
                const y = document.getElementById('birth_year_et')?.value || '';
                const m = document.getElementById('birth_month_et')?.value || '';
                const d = document.getElementById('birth_day_et')?.value || '';
                localStorage.setItem('CHILD_BIRTH_ET', JSON.stringify({y,m,d}));
            } catch(_) {}
        }

        function restoreFormFromLocalStorage() {
            const form = document.getElementById('student-form');
            const saved = localStorage.getItem(FORM_STORAGE_KEY);
            if (!saved) return;
            const data = JSON.parse(saved);
            Array.from(form.elements).forEach(el => {
                if (el.name && data.hasOwnProperty(el.name)) {
                    if (el.type === 'radio' || el.type === 'checkbox') {
                        el.checked = (el.value === data[el.name] || data[el.name] === true);
                    } else if (el.type === 'file') {
                        // skip file inputs
                    } else {
                        el.value = data[el.name];
                    }
                }
            });
            // Ensure Ethiopian date dropdowns restore fully (including days) and survive later population
            function applyEtDate(){
                try {
                    const ySel = document.getElementById('birth_year_et');
                    const mSel = document.getElementById('birth_month_et');
                    const dSel = document.getElementById('birth_day_et');
                    if (!(ySel && mSel && dSel)) return;
                    // Prefer dedicated key if present, else fallback to autosave data
                    let savedY = data.birth_year_et || '';
                    let savedM = data.birth_month_et || '';
                    let savedD = data.birth_day_et || '';
                    try {
                        const et = JSON.parse(localStorage.getItem('CHILD_BIRTH_ET')||'{}');
                        if (et && (et.y || et.m || et.d)) {
                            savedY = et.y || savedY; savedM = et.m || savedM; savedD = et.d || savedD;
                        }
                    } catch(_) {}
                    if (savedY) ySel.value = savedY;
                    if (savedM) mSel.value = savedM;
                    // Trigger month change to repopulate days
                    mSel.dispatchEvent(new Event('change', { bubbles: true }));
                    // Retry setting day until days are populated
                    let tries = 0;
                    const maxTries = 30; // up to ~3s
                    const timer = setInterval(() => {
                        tries++;
                        const hasDays = dSel.options && dSel.options.length > 1;
                        if (hasDays) {
                            if (savedD) dSel.value = savedD;
                            clearInterval(timer);
                        } else if (tries >= maxTries) {
                            clearInterval(timer);
                        }
                    }, 100);
                    // MutationObserver to catch late population
                    if (window.MutationObserver) {
                        const obs = new MutationObserver(()=>{
                            const hasDays = dSel.options && dSel.options.length > 1;
                            if (hasDays) {
                                if (savedD) dSel.value = savedD;
                                try { obs.disconnect(); } catch(_) {}
                            }
                        });
                        try { obs.observe(dSel, { childList: true }); } catch(_) {}
                    }
                } catch(_) {}
            }
            applyEtDate();
            // Schedule extra attempts after initial load
            setTimeout(applyEtDate, 200);
            setTimeout(applyEtDate, 600);
            setTimeout(applyEtDate, 1200);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Apply children form configuration (labels/placeholders/required)
            (function(){
                try {
                    const CFG = window.FORM_CFG_CHILDREN || {};
                    function setLabelFor(el, text){
                        if (!el) return;
                        let label = null;
                        if (el.id) label = document.querySelector('label[for="'+el.id+'"]');
                        if (!label) label = (el.previousElementSibling && el.previousElementSibling.tagName==='LABEL') ? el.previousElementSibling : null;
                        if (label && text) label.textContent = text + (label.innerHTML.indexOf('*')!==-1 ? ' *' : '');
                    }
                    function setPh(el, ph){ if (el && typeof ph==='string' && ph!=='') el.placeholder = ph; }
                    function setReq(el, req){ if (el) el.required = !!req; }
                    const map = {
                        student_photo: document.getElementById('student_photo'),
                        full_name: document.getElementById('full_name'),
                        christian_name: document.getElementById('christian_name'),
                        gender: document.querySelector('input[name="gender"]'),
                        birth_year_et: document.getElementById('birth_year_et'),
                        birth_month_et: document.getElementById('birth_month_et'),
                        birth_day_et: document.getElementById('birth_day_et'),
                        phone_number: document.querySelector('input[name="phone_number"]'), // not present; student_phone used
                        student_phone: document.getElementById('student_phone'),
                        sub_city: document.getElementById('sub_city'),
                        district: document.getElementById('district'),
                        specific_area: document.getElementById('specific_area'),
                        house_number: document.getElementById('house_number'),
                        living_with: document.querySelector('input[name="living_with"]'),
                        // both parents
                        father_full_name_both: document.getElementById('father_full_name'),
                        father_christian_name_both: document.getElementById('father_christian_name'),
                        father_occupation_both: document.getElementById('father_occupation'),
                        father_phone_both: document.getElementById('father_phone'),
                        mother_full_name_both: document.getElementById('mother_full_name'),
                        mother_christian_name_both: document.getElementById('mother_christian_name'),
                        mother_occupation_both: document.getElementById('mother_occupation'),
                        mother_phone_both: document.getElementById('mother_phone'),
                        // father only
                        father_full_name_only: document.getElementById('father_only_full_name'),
                        father_christian_name_only: document.getElementById('father_only_christian_name'),
                        father_occupation_only: document.getElementById('father_only_occupation'),
                        father_phone_only: document.getElementById('father_only_phone'),
                        // mother only
                        mother_full_name_only: document.getElementById('mother_only_full_name'),
                        mother_christian_name_only: document.getElementById('mother_only_christian_name'),
                        mother_occupation_only: document.getElementById('mother_only_occupation'),
                        mother_phone_only: document.getElementById('mother_only_phone'),
                        // guardian
                        guardian_father_full_name: document.getElementById('guardian_father_full_name'),
                        guardian_father_christian_name: document.getElementById('guardian_father_christian_name'),
                        guardian_father_occupation: document.getElementById('guardian_father_occupation'),
                        guardian_father_phone: document.getElementById('guardian_father_phone'),
                        guardian_mother_full_name: document.getElementById('guardian_mother_full_name'),
                        guardian_mother_christian_name: document.getElementById('guardian_mother_christian_name'),
                        guardian_mother_occupation: document.getElementById('guardian_mother_occupation'),
                        guardian_mother_phone: document.getElementById('guardian_mother_phone'),
                        // additional
                        special_interests: document.getElementById('special_interests'),
                        siblings_in_school: document.getElementById('siblings_in_school'),
                        physical_disability: document.getElementById('physical_disability'),
                        weak_side: document.getElementById('weak_side'),
                        transferred_from_other_school: document.getElementById('transferred_from_other_school'),
                        came_from_other_religion: document.getElementById('came_from_other_religion')
                    };
                    Object.keys(CFG).forEach(k=>{
                        const c = CFG[k]||{}; const el = map[k]; if (!el) return;
                        if (c.label) setLabelFor(el, c.label);
                        setPh(el, c.placeholder||'');
                        if (k==='gender') {
                            document.querySelectorAll('input[name="gender"]').forEach(r=>setReq(r, c.required));
                        } else if (k==='birth_year_et' || k==='birth_month_et' || k==='birth_day_et') {
                            setReq(document.getElementById('birth_year_et'), CFG.birth_year_et?CFG.birth_year_et.required:el.required);
                            setReq(document.getElementById('birth_month_et'), CFG.birth_month_et?CFG.birth_month_et.required:el.required);
                            setReq(document.getElementById('birth_day_et'), CFG.birth_day_et?CFG.birth_day_et.required:el.required);
                        } else if (k==='living_with') {
                            document.querySelectorAll('input[name="living_with"]').forEach(r=>setReq(r, c.required));
                        } else {
                            setReq(el, c.required);
                        }
                    });
                } catch(_) {}
            })();
        // Persist current preview src on tab hide/unload as extra safety
        (function(){
            function persistPreview(){
                try {
                    const img = document.getElementById('photo-preview');
                    if (img && img.src) localStorage.setItem('CHILD_TEMP_PHOTO_DATAURL', img.src);
                    const keyEl = document.getElementById('c_temp_photo_key');
                    if (keyEl && keyEl.value) localStorage.setItem('CHILD_TEMP_PHOTO_KEY', keyEl.value);
                } catch(_) {}
            }
            document.addEventListener('visibilitychange', function(){ if (document.visibilityState==='hidden') persistPreview(); });
            window.addEventListener('beforeunload', persistPreview);
        })();
            // Restore temp photo if available (prefer offline dataURL)
            (function(){
                try {
                    const key = localStorage.getItem('CHILD_TEMP_PHOTO_KEY');
                    const url = localStorage.getItem('CHILD_TEMP_PHOTO_URL');
                    const dataUrl = localStorage.getItem('CHILD_TEMP_PHOTO_DATAURL');
                    if (dataUrl || url) {
                        const tempKeyEl = document.getElementById('c_temp_photo_key');
                        if (key && tempKeyEl && !tempKeyEl.value) tempKeyEl.value = key;
                        const tempDataEl = document.getElementById('c_temp_photo_dataurl');
                        if (tempDataEl && dataUrl && !tempDataEl.value) tempDataEl.value = dataUrl;
                        const preview = document.getElementById('photo-preview');
                        const placeholder = document.getElementById('photo-placeholder');
                        if (preview) { preview.src = dataUrl || url; preview.classList.remove('hidden'); }
                        if (placeholder) { placeholder.classList.add('hidden'); }
                    }
                } catch(_) {}
            })();
            restoreFormFromLocalStorage();
            // ...existing code...
        });

        function debounce(fn, wait){ let t; return function(){ const ctx=this, args=arguments; clearTimeout(t); t=setTimeout(()=>fn.apply(ctx,args), wait); }; }
        const debouncedSave = debounce(saveFormToLocalStorage, 200);
        const debouncedUpdate = debounce(updateCardStates, 200);
        document.getElementById('student-form').addEventListener('input', function() { debouncedSave(); debouncedUpdate(); }, { passive: true });
        document.getElementById('student-form').addEventListener('change', function() { debouncedSave(); debouncedUpdate(); }, { passive: true });
        // Also persist birth date on explicit select changes
        ['birth_year_et','birth_month_et','birth_day_et'].forEach(id=>{
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', () => {
                try {
                    const y = document.getElementById('birth_year_et')?.value || '';
                    const m = document.getElementById('birth_month_et')?.value || '';
                    const d = document.getElementById('birth_day_et')?.value || '';
                    localStorage.setItem('CHILD_BIRTH_ET', JSON.stringify({y,m,d}));
                } catch(_) {}
            });
        });
        let currentCard = 1;
        const totalCards = 9;
        function cardHasData(n){
            const card = document.getElementById(`card-${n}`);
            if (!card) return false;
            const els = card.querySelectorAll('input, select, textarea');
            for (const el of els){
                if (el.type === 'radio' || el.type === 'checkbox') { if (el.checked) return true; }
                else if (el.type === 'file') { if (el.files && el.files.length>0) return true; }
                else if ((el.value||'').trim() !== '') { return true; }
            }
            return false;
        }
        function getNextFilledCard(from){
            for (let i=from+1;i<=totalCards;i++){ if (cardHasData(i)) return i; }
            return Math.min(from+1, totalCards);
        }
        function getPrevFilledCard(from){
            for (let i=from-1;i>=1;i--){ if (cardHasData(i)) return i; }
            // Fallback to previous logical card
            return Math.max(1, getPreviousCard(from));
        }
        function updateCardStates(){
            const states = {};
            for (let i=1;i<=totalCards;i++){ states[i] = cardHasData(i); }
            try { localStorage.setItem('CHILD_CARD_STATE', JSON.stringify(states)); } catch(_) {}
        }
        
        // Initialize form by showing the first card explicitly (user reviews from the start)
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // Always reset to first card view on fresh load
                document.getElementById(`card-${currentCard}`).classList.remove('active');
                document.getElementById('card-1').classList.add('active');
                document.getElementById(`step-${currentCard}`).classList.remove('active');
                document.getElementById('step-1').classList.add('active');
                currentCard = 1;
                // Do not clear saved step, so navigation continues saving; just ignore it on load
            } catch(_) {}
            // Restore card completion states (for future use)
            try {
                const s = JSON.parse(localStorage.getItem('CHILD_CARD_STATE')||'{}');
                for (let i=1;i<=totalCards;i++){
                    const step = document.getElementById(`step-${i}`);
                    if (step){
                        if (s[i]) { step.classList.add('completed'); step.title = 'Completed'; }
                        else { step.classList.remove('completed'); step.removeAttribute('title'); }
                    }
                }
            } catch(_) {}
            updateProgressBar();
            updateCardStates();
        });
        
        function nextCard(cardNumber) {
            if (cardNumber) {
                // Validate current card before proceeding
                if (!validateCard(currentCard)) return;
                // Prefer jumping to the next section with data when moving forward
                if (cardNumber > currentCard) cardNumber = getNextFilledCard(currentCard) || cardNumber;
                document.getElementById(`card-${currentCard}`).classList.remove('active');
                document.getElementById(`card-${cardNumber}`).classList.add('active');
                document.getElementById(`step-${currentCard}`).classList.remove('active');
                document.getElementById(`step-${cardNumber}`).classList.add('active');
                currentCard = cardNumber;
                try { localStorage.setItem('CHILD_CURRENT_CARD', String(currentCard)); } catch(_) {}
                updateProgressBar();
                window.scrollTo(0, 0);
            }
        }
        
        function prevCard(cardNumber) {
            if (cardNumber) {
                // Prefer jumping to the previous section with data when moving backward
                if (cardNumber < currentCard) cardNumber = getPrevFilledCard(currentCard) || cardNumber;
                document.getElementById(`card-${currentCard}`).classList.remove('active');
                document.getElementById(`card-${cardNumber}`).classList.add('active');
                document.getElementById(`step-${currentCard}`).classList.remove('active');
                document.getElementById(`step-${cardNumber}`).classList.add('active');
                currentCard = cardNumber;
                try { localStorage.setItem('CHILD_CURRENT_CARD', String(currentCard)); } catch(_) {}
                updateProgressBar();
                window.scrollTo(0, 0);
            }
        }
        
        function updateProgressBar() {
            const progressPercentage = ((currentCard - 1) / (totalCards - 1)) * 100;
            document.getElementById('progress-bar').style.width = `${progressPercentage}%`;
            
            // Update step indicators
            for (let i = 1; i <= totalCards; i++) {
                const step = document.getElementById(`step-${i}`);
                if (i < currentCard) {
                    step.classList.remove('active');
                    step.classList.add('completed');
                } else if (i === currentCard) {
                    step.classList.add('active');
                    step.classList.remove('completed');
                } else {
                    step.classList.remove('active', 'completed');
                }
            }
        }
        
        function toggleSpiritualFatherInfo(show) {
            const spiritualFatherInfo = document.getElementById('spiritual-father-info');
            if (show) {
                spiritualFatherInfo.classList.remove('hidden');
            } else {
                spiritualFatherInfo.classList.add('hidden');
            }
        }
        
        let selectedLivingWith = null;

        function setInputsEnabled(cardId, enabled) {
            const card = document.getElementById(`card-${cardId}`);
            if (!card) return;
            card.querySelectorAll('input, select, textarea').forEach(el => {
                el.disabled = !enabled;
            });
        }

        function applyLivingWithEnablement() {
            // Enable common cards
            [1,2,3,4,9].forEach(id => setInputsEnabled(id, true));
            // Disable all parent cards first
            [5,6,7,8].forEach(id => setInputsEnabled(id, false));
            // Enable only the selected parent card
            switch (selectedLivingWith) {
                case 'both_parents': setInputsEnabled(5, true); break;
                case 'father_only': setInputsEnabled(6, true); break;
                case 'mother_only': setInputsEnabled(7, true); break;
                case 'relative_or_guardian': setInputsEnabled(8, true); break;
            }
        }

        function showParentInfo(type) {
            // Track chosen living_with and update enabled fields
            selectedLivingWith = type === 'both' ? 'both_parents'
                : type === 'father' ? 'father_only'
                : type === 'mother' ? 'mother_only'
                : type === 'guardian' ? 'relative_or_guardian'
                : null;
            applyLivingWithEnablement();
        }
        
        function nextCardBasedOnLivingWith() {
            const checkedRadio = document.querySelector('input[name="living_with"]:checked');
            if (!checkedRadio) {
                alert('እባክዎ ከማን ጋር እንደሚኖሩ ይሙሉ።');
                return;
            }
            const livingWith = checkedRadio.value;
            let nextCardNumber;
            
            selectedLivingWith = livingWith;
            switch (livingWith) {
                case 'both_parents':
                    nextCardNumber = 5;
                    break;
                case 'father_only':
                    nextCardNumber = 6;
                    break;
                case 'mother_only':
                    nextCardNumber = 7;
                    break;
                case 'relative_or_guardian':
                    nextCardNumber = 8;
                    break;
                default:
                    nextCardNumber = 9;
            }
            applyLivingWithEnablement();
            nextCard(nextCardNumber);
        }
        
        function getPreviousCard(current) {
            if (current >= 5 && current <= 8) {
                return 4; // All parent info cards go back to living situation card
            }
            return current - 1;
        }
        // Clear form mid-registration (children)
        function clearChildrenForm(){
            if (!confirm('በእርግጥ ፎርሙን ሙሉ በሙሉ ማጥፋት ትፈልጋለህ/ሽ?')) return;
            try { localStorage.removeItem('studentFormAutosave'); } catch(_) {}
            try { localStorage.removeItem('CHILD_TEMP_PHOTO_KEY'); localStorage.removeItem('CHILD_TEMP_PHOTO_URL'); localStorage.removeItem('CHILD_TEMP_PHOTO_DATAURL'); } catch(_) {}
            const form = document.getElementById('student-form'); if (form) form.reset();
            const tpk = document.getElementById('c_temp_photo_key'); if (tpk) tpk.value='';
            const input = document.getElementById('student_photo'); if (input) input.value='';
            const preview = document.getElementById('photo-preview');
            const placeholder = document.getElementById('photo-placeholder');
            if (preview) { preview.src=''; preview.classList.add('hidden'); }
            if (placeholder) { placeholder.classList.remove('hidden'); }
            // Reset navigation to first card
            try {
                document.getElementById(`card-${currentCard}`).classList.remove('active');
                document.getElementById('card-1').classList.add('active');
                document.getElementById(`step-${currentCard}`).classList.remove('active');
                document.getElementById('step-1').classList.add('active');
                currentCard = 1; updateProgressBar(); window.scrollTo(0,0);
            } catch(_) {}
            // Reset enabled/disabled state
            selectedLivingWith = null; applyLivingWithEnablement();
        }
        
        // Low-bandwidth photo handling: compress and upload temp, autosave key
        (function(){
            const input = document.getElementById('student_photo');
            const preview = document.getElementById('photo-preview');
            const placeholder = document.getElementById('photo-placeholder');
            const tempKeyEl = document.getElementById('c_temp_photo_key');
            const TEMP_KEY = 'CHILD_TEMP_PHOTO_KEY';
            const TEMP_URL = 'CHILD_TEMP_PHOTO_URL';
            const TEMP_DATAURL = 'CHILD_TEMP_PHOTO_DATAURL';
            function dataURLToFile(dataUrl, filename){
                const arr = dataUrl.split(',');
                const mime = arr[0].match(/:(.*?);/)[1];
                const bstr = atob(arr[1]);
                let n = bstr.length; const u8 = new Uint8Array(n);
                while(n--){ u8[n] = bstr.charCodeAt(n); }
                return new File([u8], filename, {type:mime});
            }
            function compressDataUrl(dataUrl, maxDim=600, quality=0.7){
                return new Promise(resolve=>{
                    const img = new Image();
                    img.onload = function(){
                        let {width,height} = img;
                        const scale = Math.min(1, maxDim/Math.max(width,height));
                        const nw = Math.round(width*scale), nh = Math.round(height*scale);
                        const canvas = document.createElement('canvas'); canvas.width=nw; canvas.height=nh;
                        const ctx = canvas.getContext('2d'); ctx.drawImage(img,0,0,nw,nh);
                        resolve(canvas.toDataURL('image/jpeg', quality));
                    };
                    img.src = dataUrl;
                });
            }
            async function uploadTemp(file, previewDataUrl){
                const fd = new FormData(); fd.append('photo', file);
                const res = await fetch('api/upload_temp_photo.php', { method:'POST', body: fd });
                const data = await res.json();
                if (!data || !data.success || !data.key) throw new Error('Upload failed');
                const url = 'uploads/tmp/'+data.key;
                try {
                    localStorage.setItem(TEMP_KEY, data.key);
                    localStorage.setItem(TEMP_URL, url);
                    if (previewDataUrl) localStorage.setItem(TEMP_DATAURL, previewDataUrl);
                } catch(_) {}
                if (tempKeyEl) tempKeyEl.value = data.key;
                if (preview) { preview.src = (previewDataUrl || url); preview.classList.remove('hidden'); }
                if (placeholder) placeholder.classList.add('hidden');
            }
            async function handleFile(file){
                // quick preview
                const fr = new FileReader();
                let previewDataUrl = '';
                fr.onload = e=>{
                    previewDataUrl = String(e.target.result||'');
                    try { localStorage.setItem(TEMP_DATAURL, previewDataUrl); } catch(_) {}
                    const tempDataEl = document.getElementById('c_temp_photo_dataurl');
                    if (tempDataEl) tempDataEl.value = previewDataUrl;
                    if (preview){ preview.src=previewDataUrl; preview.classList.remove('hidden'); }
                    if (placeholder) placeholder.classList.add('hidden');
                };
                fr.readAsDataURL(file);
                // compress then upload
                try {
                    const dataUrl = await new Promise((res,rej)=>{ const r=new FileReader(); r.onload=()=>res(r.result); r.onerror=rej; r.readAsDataURL(file); });
                    const comp = await compressDataUrl(String(dataUrl), 800, 0.8);
                    const compFile = dataURLToFile(comp, (file.name||'photo')+'.jpg');
                    await uploadTemp(compFile, previewDataUrl || String(dataUrl));
                } catch(err){ try { await uploadTemp(file, previewDataUrl||''); } catch(_) {} }
            }
            if (input) {
                input.addEventListener('change', function(){ if (input.files && input.files[0]) handleFile(input.files[0]); });
            }
            // Camera capture integration below overrides input and also uploads
        })();

        function previewPhoto(input) {
            // Camera modal logic
            let cameraStream = null;
            function openCameraModal() {
                document.getElementById('camera-modal').classList.remove('hidden');
                const video = document.getElementById('camera-video');
                navigator.mediaDevices.getUserMedia({ video: true })
                    .then(stream => {
                        cameraStream = stream;
                        video.srcObject = stream;
                    })
                    .catch(() => {
                        alert('Camera access denied or not available.');
                        closeCameraModal();
                    });
            }
            function closeCameraModal() {
                document.getElementById('camera-modal').classList.add('hidden');
                const video = document.getElementById('camera-video');
                if (cameraStream) {
                    cameraStream.getTracks().forEach(track => track.stop());
                    cameraStream = null;
                }
                video.srcObject = null;
            }
            async function capturePhoto() {
                const video = document.getElementById('camera-video');
                const canvas = document.getElementById('camera-canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
                window.capturedPhotoDataUrl = dataUrl;
                try { localStorage.setItem('CHILD_TEMP_PHOTO_DATAURL', dataUrl); } catch(_) {}
                const tempDataEl = document.getElementById('c_temp_photo_dataurl');
                if (tempDataEl) tempDataEl.value = dataUrl;
                // preview
                const preview = document.getElementById('photo-preview');
                const placeholder = document.getElementById('photo-placeholder');
                if (preview){ preview.src = dataUrl; preview.classList.remove('hidden'); }
                if (placeholder){ placeholder.classList.add('hidden'); }
                // Set file input and upload temp
                const file = await (async function(){
                    const resp = await fetch(dataUrl); const buf = await resp.arrayBuffer();
                    return new File([buf], 'captured_photo.jpg', { type: 'image/jpeg' });
                })();
                const dt = new DataTransfer(); dt.items.add(file); document.getElementById('student_photo').files = dt.files;
                try {
                    const fd = new FormData(); fd.append('photo', file);
                    const res = await fetch('api/upload_temp_photo.php', { method:'POST', body: fd });
                    const data = await res.json();
                    if (data && data.success && data.key) {
                        const url = 'uploads/tmp/'+data.key;
                        try { localStorage.setItem('CHILD_TEMP_PHOTO_KEY', data.key); localStorage.setItem('CHILD_TEMP_PHOTO_URL', url); } catch(_) {}
                        const tempKeyEl = document.getElementById('c_temp_photo_key'); if (tempKeyEl) tempKeyEl.value = data.key;
                    }
                } catch(_) {}
                closeCameraModal();
            }
            // Helper to convert dataURL to File (moved into capture)
                const preview = document.getElementById('photo-preview');
                const placeholder = document.getElementById('photo-placeholder');
                
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.classList.remove('hidden');
                        placeholder.classList.add('hidden');
                    }
                    
                    reader.readAsDataURL(input.files[0]);
                }
        }
        
        function validateCard(cardNumber) {
            // Enhanced validation for required fields and formats
            const card = document.getElementById(`card-${cardNumber}`);
            const requiredInputs = card.querySelectorAll('[required]');
            let isValid = true;
            let errorMessages = [];

            // Compute photo presence (file OR temp key OR temp dataurl OR visible preview)
            const studentPhotoInput = document.getElementById('student_photo');
            const tempKeyEl0 = document.getElementById('c_temp_photo_key');
            const tempDataEl0 = document.getElementById('c_temp_photo_dataurl');
            const previewImg0 = document.getElementById('photo-preview');
            const hasTempKey0 = !!(tempKeyEl0 && tempKeyEl0.value);
            const hasTempData0 = !!(tempDataEl0 && tempDataEl0.value);
            const hasPreview0 = !!(previewImg0 && previewImg0.src && !previewImg0.classList.contains('hidden'));
            const hasPhoto0 = !!((studentPhotoInput && studentPhotoInput.files && studentPhotoInput.files.length > 0) || hasTempKey0 || hasTempData0 || hasPreview0);

            requiredInputs.forEach(input => {
                // Skip marking file input invalid if we already have a temp photo
                if (input.type === 'file' && input.id === 'student_photo') {
                    if (!hasPhoto0) {
                        input.classList.add('border-red-500');
                        isValid = false;
                    } else {
                        input.classList.remove('border-red-500');
                    }
                    return;
                }
                if (!input.value.trim()) {
                    input.classList.add('border-red-500');
                    isValid = false;
                } else {
                    input.classList.remove('border-red-500');
                }
            });

            // Full name validation (only letters and spaces)
            if (cardNumber === 1) {
                const fullName = document.getElementById('full_name');
                // Accept only Amharic/English letters and spaces
                if (fullName && !/^[\u1200-\u137F\u1380-\u139F\u2D80-\u2DDFa-zA-Z ]+$/.test(fullName.value.trim())) {
                    fullName.classList.add('border-red-500');
                    isValid = false;
                    errorMessages.push('ሙሉ ስም ላይ ቁጥሮችና ምልክቶችን አይጠቀሙ።');
                }
                // Photo required: accept selected file OR temp key OR temp dataurl OR visible preview
                const tempKeyEl = document.getElementById('c_temp_photo_key');
                const tempDataEl = document.getElementById('c_temp_photo_dataurl');
                const previewImg = document.getElementById('photo-preview');
                const hasTempKey = tempKeyEl && tempKeyEl.value;
                const hasTempData = tempDataEl && tempDataEl.value;
                const hasFile = studentPhotoInput && studentPhotoInput.files && studentPhotoInput.files.length > 0;
                const hasPreview = !!(previewImg && previewImg.src && !previewImg.classList.contains('hidden'));
                if (!(hasFile || hasTempKey || hasTempData || hasPreview)) {
                    isValid = false;
                    errorMessages.push('የተማሪ ፎቶ መግባት አለበት።');
                }

                // Gender required
                const genderRadios = document.querySelectorAll('input[name="gender"]');
                if (![...genderRadios].some(r => r.checked)) {
                    isValid = false;
                    errorMessages.push('የጾታ መረጃውን አስተካክለው ይሙሉ።');
                }
                // Birth date required (from Ethiopian dropdowns converted to hidden Gregorian)
                const birthDate = document.getElementById('birth_date');
                const ySel = document.getElementById('birth_year_et');
                const mSel = document.getElementById('birth_month_et');
                const dSel = document.getElementById('birth_day_et');
                if (!ySel || !mSel || !dSel || !ySel.value || !mSel.value || !dSel.value) {
                    isValid = false;
                    errorMessages.push('የተወለዱበት ቀን/ወር/ዓመት ይምረጡ።');
                }
                if (birthDate && !birthDate.value.trim()) {
                    isValid = false;
                    errorMessages.push('የተወለዱበት ቀን ልክ አይደለም።');
                }
            }

            // Phone number validation (must be exactly 10 digits and start with 09)
            // Card 1: student phone (optional, but if filled must be valid)
            if (cardNumber === 1) {
                const studentPhone = document.getElementById('student_phone');
                if (studentPhone && studentPhone.value.trim()) {
                    if (!/^09\d{8}$/.test(studentPhone.value.trim())) {
                        studentPhone.classList.add('border-red-500');
                        isValid = false;
                        errorMessages.push('የተማሪው ስልክ ቁጥር አስተካክለው ይሙሉ።');
                    } else {
                        studentPhone.classList.remove('border-red-500');
                    }
                }
            }
            // Card 5: father/mother phone (required, must be valid)
            if (cardNumber === 5) {
                const fatherPhone = document.getElementById('father_phone');
                const motherPhone = document.getElementById('mother_phone');
                if (fatherPhone && (!fatherPhone.value.trim() || !/^09\d{8}$/.test(fatherPhone.value.trim()))) {
                    fatherPhone.classList.add('border-red-500');
                    isValid = false;
                    errorMessages.push('የወላጅ አባት ስልክ ቁጥር አስተካክለው ይሙሉ።');
                } else if (fatherPhone) {
                    fatherPhone.classList.remove('border-red-500');
                }
                if (motherPhone && (!motherPhone.value.trim() || !/^09\d{8}$/.test(motherPhone.value.trim()))) {
                    motherPhone.classList.add('border-red-500');
                    isValid = false;
                    errorMessages.push('የወላጅ እናት ስልክ ቁጥር አስተካክለው ይሙሉ።');
                } else if (motherPhone) {
                    motherPhone.classList.remove('border-red-500');
                }
            }
            // Card 6: father only phone
            if (cardNumber === 6) {
                const fatherOnlyPhone = document.getElementById('father_only_phone');
                if (fatherOnlyPhone && (!fatherOnlyPhone.value.trim() || !/^09\d{8}$/.test(fatherOnlyPhone.value.trim()))) {
                    fatherOnlyPhone.classList.add('border-red-500');
                    isValid = false;
                    errorMessages.push('የወላጅ አባት ስልክ ቁጥር አስተካክለው ይሙሉ።');
                } else if (fatherOnlyPhone) {
                    fatherOnlyPhone.classList.remove('border-red-500');
                }
            }
            // Card 7: mother only phone
            if (cardNumber === 7) {
                const motherOnlyPhone = document.getElementById('mother_only_phone');
                if (motherOnlyPhone && (!motherOnlyPhone.value.trim() || !/^09\d{8}$/.test(motherOnlyPhone.value.trim()))) {
                    motherOnlyPhone.classList.add('border-red-500');
                    isValid = false;
                    errorMessages.push('የወላጅ እናት ስልክ ቁጥር አስተካክለው ይሙሉ።');
                } else if (motherOnlyPhone) {
                    motherOnlyPhone.classList.remove('border-red-500');
                }
            }
            // Card 8: guardian father/mother phone
            if (cardNumber === 8) {
                const guardianFatherPhone = document.getElementById('guardian_father_phone');
                if (guardianFatherPhone && (!guardianFatherPhone.value.trim() || !/^09\d{8}$/.test(guardianFatherPhone.value.trim()))) {
                    guardianFatherPhone.classList.add('border-red-500');
                    isValid = false;
                    errorMessages.push('የዘመድ(አሳዳጊ) አባት ስልክ ቁጥር አስተካክለው ይሙሉ።');
                } else if (guardianFatherPhone) {
                    guardianFatherPhone.classList.remove('border-red-500');
                }
                const guardianMotherPhone = document.getElementById('guardian_mother_phone');
                if (guardianMotherPhone && guardianMotherPhone.value.trim()) {
                    if (!/^09\d{8}$/.test(guardianMotherPhone.value.trim())) {
                        guardianMotherPhone.classList.add('border-red-500');
                        isValid = false;
                        errorMessages.push('የዘመድ(አሳዳጊ) እናት ስልክ ቁጥሮች አስተካክለው ይሙሉ።');
                    } else {
                        guardianMotherPhone.classList.remove('border-red-500');
                    }
                }
            }

            if (!isValid) {
                alert(errorMessages.length ? errorMessages.join('\n') : 'እባክዎ ሁሉንም የሚጠይቁ መስኮች ይሙሉ!');
                return false;
            }
            return true;
        }
        
        // Form submission
        document.getElementById('student-form').addEventListener('submit', function(e) {
            // Ensure only relevant parent card fields are enabled
            applyLivingWithEnablement();
            if (!validateCard(currentCard)) {
                e.preventDefault();
                return false;
            }
            return true;
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize enabled/disabled inputs based on default state
            const checked = document.querySelector('input[name="living_with"]:checked');
            selectedLivingWith = checked ? checked.value : null;
            applyLivingWithEnablement();
        });
        </script>

<script>
// Ethiopian calendar dropdowns (store as Ethiopian Y/M/D only)
(function() {
    const ySel = document.getElementById('birth_year_et');
    const mSel = document.getElementById('birth_month_et');
    const dSel = document.getElementById('birth_day_et');
    if (!ySel || !mSel || !dSel) return;

    const monthNames = ['መስከረም', 'ጥቅምት', 'ሕዳር', 'ታህሳስ', 'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ጳጉሜ'];

    function getCurrentEthiopianYear() {
        const today = new Date();
        const gYear = today.getFullYear();
        const gMonth = today.getMonth() + 1;
        const gDay = today.getDate();
        let eYear = gYear - 8;
        if (gMonth > 9 || (gMonth === 9 && gDay >= 11)) eYear = gYear - 7;
        return eYear;
    }

    function isEthiopianLeapYear(eYear) {
        return eYear % 4 === 3;
    }

    function daysInEthiopianMonth(eYear, eMonth) {
        if (eMonth >= 1 && eMonth <= 12) return 30;
        return isEthiopianLeapYear(eYear) ? 6 : 5; // Pagume
    }

    function populateYears() {
        const current = getCurrentEthiopianYear();
        const min = current - 30;
        ySel.innerHTML = '<option value="">ዓመት</option>';
        for (let y = current; y >= min; y--) {
            const opt = document.createElement('option');
            opt.value = String(y);
            opt.textContent = y;
            ySel.appendChild(opt);
        }
    }

    function populateMonths() {
        mSel.innerHTML = '<option value="">ወር</option>';
        monthNames.forEach((name, idx) => {
            const opt = document.createElement('option');
            opt.value = String(idx + 1);
            opt.textContent = name;
            mSel.appendChild(opt);
        });
    }

    function populateDays() {
        const y = parseInt(ySel.value, 10);
        const m = parseInt(mSel.value, 10);
        dSel.innerHTML = '<option value="">ቀን</option>';
        if (!y || !m) return;
        const dim = daysInEthiopianMonth(y, m);
        for (let d = 1; d <= dim; d++) {
            const opt = document.createElement('option');
            opt.value = String(d);
            opt.textContent = String(d);
            dSel.appendChild(opt);
        }
    }

    ySel.addEventListener('change', function() { populateDays(); });
    mSel.addEventListener('change', function() { populateDays(); });

    populateYears();
    populateMonths();
})();
</script>





<script>
// Camera modal logic with camera switching
let cameraStream = null;
let currentFacingMode = 'user'; // 'user' (front) or 'environment' (back)

function startCamera(facingMode = 'user') {
    const video = document.getElementById('camera-video');
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    navigator.mediaDevices.getUserMedia({ video: { facingMode } })
        .then(stream => {
            cameraStream = stream;
            video.srcObject = stream;
        })
        .catch(() => {
            alert('Camera access denied or not available.');
            closeCameraModal();
        });
}

window.openCameraModal = function() {
    document.getElementById('camera-modal').classList.remove('hidden');
    currentFacingMode = 'user';
    startCamera(currentFacingMode);
}

window.closeCameraModal = function() {
    document.getElementById('camera-modal').classList.add('hidden');
    const video = document.getElementById('camera-video');
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    video.srcObject = null;
}

window.switchCamera = function() {
    currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
    startCamera(currentFacingMode);
}

window.capturePhoto = function() {
    const video = document.getElementById('camera-video');
    const canvas = document.getElementById('camera-canvas');
    canvas.width = video.videoWidth || 320;
    canvas.height = video.videoHeight || 240;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/jpeg');
    window.capturedPhotoDataUrl = dataUrl;
    // Set preview
    document.getElementById('photo-preview').src = dataUrl;
    document.getElementById('photo-preview').classList.remove('hidden');
    document.getElementById('photo-placeholder').classList.add('hidden');
    // Set file input value for form submission
    dataURLtoFile(dataUrl, 'captured_photo.jpg').then(file => {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        document.getElementById('student_photo').files = dataTransfer.files;
    });
    closeCameraModal();
}
// Helper to convert dataURL to File
function dataURLtoFile(dataurl, filename) {
    return fetch(dataurl)
        .then(res => res.arrayBuffer())
        .then(buf => new File([buf], filename, { type: 'image/jpeg' }));
}

</script>
</body>
</html>