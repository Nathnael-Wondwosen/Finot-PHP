<?php
if (!isset($_COOKIE['seen_welcome']) && empty($_GET['skip_welcome'])) {
    header('Location: welcome.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የፍኖተ ሰላም ሰ/ት/ቤት ምዝገባ ፎርም</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
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
    <!-- Error Toast Notification -->
    <?php if (isset($_GET['error']) && $_GET['error']): ?>
        <div id="error-toast" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 bg-red-100 border border-red-400 text-red-800 px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 animate-fade-in" style="min-width:300px;max-width:90vw;">
            <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="flex-1 text-base font-semibold">
                <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
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
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="flex flex-col items-center mb-6">
            <a href="welcome.php">
                <img src="uploads/689636ec11381_finot logo.png" alt="Finot Logo" class="w-24 h-24 md:w-32 md:h-32 rounded-full shadow-lg object-contain border-4 border-blue-200 dark:border-blue-800 bg-white mb-2 transition-all duration-300 hover:scale-105">
            </a>
            <h1 class="text-3xl font-bold text-center text-blue-800 mt-2">የተማሪ ምዝገባ ፎርም</h1>
        </div>
        
        <!-- Progress indicator -->
        <div class="flex justify-between items-center mb-8 relative">
            <div class="absolute top-1/2 left-0 right-0 h-1 bg-gray-200 -z-10"></div>
            <div id="progress-bar" class="absolute top-1/2 left-0 h-1 bg-blue-500 -z-10" style="width: 0%"></div>
            
            <?php for ($i = 1; $i <= 9; $i++): ?>
                <div class="progress-step" id="step-<?= $i ?>"><?= $i ?></div>
            <?php endfor; ?>
        </div>
        
        <form id="student-form" action="process.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg p-6">
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
                        <input type="file" id="student_photo" name="student_photo" accept="image/*" class="ml-4 hidden" onchange="previewPhoto(this)">
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
                    <label class="block text-gray-700 mb-2 flex items-center" for="full_name">ሙሉ ስም እስከ አያት እስከ አያት <span class="text-red-500 ml-1 text-sm">*</span></label>
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
                    <label class="block text-gray-700 mb-2 flex items-center" for="current_grade">በ2017 ዓ.ም ሰንበት ት/ቤት ስንተኛ ክፍል ነህ/ነሽ? <span class="text-red-500 ml-1 text-sm">*</span></label>
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
                    <label class="block text-gray-700 mb-2 flex items-center" for="school_year_start">በ2017 ዓ.ም ሰንበት ት/ቤት የነበረህ/ሽ ቆይታ? <span class="text-red-500 ml-1 text-sm">*</span></label>
                    <select id="school_year_start" name="school_year_start" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">-- ይምረጡ --</option>
                        <option value="2018">አዲስ</option>
                        <?php for ($year = 1995; $year <= 2017; $year++): ?>
                            <option value="<?= $year ?>"><?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 flex items-center" for="regular_school_name">ዓላማዊ የት/ቤት ስም እና የክፍል ደረጃ(በ2017 ዓ.ም) <span class="text-red-500 ml-1 text-sm">*</span></label>
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
                    <label class="block text-gray-700 mb-2 flex items-center" for="father_full_name">የወላጅ አባት ሙሉ ስም እስከ አያት <span class="text-red-500 ml-1 text-sm">*</span></label>
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
                    <label class="block text-gray-700 mb-2 flex items-center" for="mother_full_name">የወላጅ እናት ሙሉ ስም እስከ አያት <span class="text-red-500 ml-1 text-sm">*</span></label>
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
                    <label class="block text-gray-700 mb-2" for="father_only_full_name">የወላጅ አባት ሙሉ ስም እስከ አያት</label>
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
                    <label class="block text-gray-700 mb-2" for="mother_only_full_name">የወላጅ እናት ሙሉ ስም እስከ አያት</label>
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
                    <label class="block text-gray-700 mb-2" for="guardian_father_full_name">የዘመድ(አሳዳጊ) አባት ሙሉ ስም እስከ አያት</label>
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
                    <label class="block text-gray-700 mb-2" for="guardian_mother_full_name">የዘመድ(አሳዳጊ) እናት ሙሉ ስም እስከ አያት</label>
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
        }

        document.addEventListener('DOMContentLoaded', function() {
            restoreFormFromLocalStorage();
            document.getElementById('student-form').addEventListener('input', function(e) {
                saveFormToLocalStorage();
            });

            document.getElementById('student-form').addEventListener('submit', function(e) {
                localStorage.removeItem(FORM_STORAGE_KEY);
            });
        });

        let currentCard = 1;
        const totalCards = 9;
        
        // Initialize form by showing only the first card
        document.addEventListener('DOMContentLoaded', function() {
            updateProgressBar();
        });
        
        function nextCard(cardNumber) {
            if (cardNumber) {
                // Validate current card before proceeding
                if (!validateCard(currentCard)) return;
                
                document.getElementById(`card-${currentCard}`).classList.remove('active');
                document.getElementById(`card-${cardNumber}`).classList.add('active');
                document.getElementById(`step-${currentCard}`).classList.remove('active');
                document.getElementById(`step-${cardNumber}`).classList.add('active');
                currentCard = cardNumber;
                updateProgressBar();
                window.scrollTo(0, 0);
            }
        }
        
        function prevCard(cardNumber) {
            if (cardNumber) {
                document.getElementById(`card-${currentCard}`).classList.remove('active');
                document.getElementById(`card-${cardNumber}`).classList.add('active');
                document.getElementById(`step-${currentCard}`).classList.remove('active');
                document.getElementById(`step-${cardNumber}`).classList.add('active');
                currentCard = cardNumber;
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
        
        function previewPhoto(input) {
            // If photo is set by camera, update preview
            if (window.capturedPhotoDataUrl) {
                preview.src = window.capturedPhotoDataUrl;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            }
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
            function capturePhoto() {
                const video = document.getElementById('camera-video');
                const canvas = document.getElementById('camera-canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
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

            requiredInputs.forEach(input => {
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
                    errorMessages.push('ሙሉ ስም እስከ አያት ላይ ቁጥሮችና ምልክቶችን አይጠቀሙ።');
                }
                // Photo required
                const studentPhotoInput = document.getElementById('student_photo');
                if (!studentPhotoInput.files || studentPhotoInput.files.length === 0) {
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