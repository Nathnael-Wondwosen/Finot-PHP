/**
 * Enhanced Edit Drawer JavaScript Functions
 * Professional and advanced implementation with modern UI/UX
 */

// Global drawer variables
let currentEditingStudent = null;
let currentEditingTable = 'students';
let originalStudentData = null;
let hasUnsavedChanges = false;
let currentActiveTab = 'profile';

// Enhanced Edit Drawer Implementation
window.openEditDrawer = function(studentData, table) {
    // Remove existing drawer if present
    const existingDrawer = document.getElementById("enhancedEditDrawer");
    if (existingDrawer) {
        existingDrawer.remove();
    }
    
    // Store current editing data
    window.currentEditingStudent = { ...studentData };
    window.currentEditingTable = table || "students";
    window.originalStudentData = { ...studentData };
    
    // Create and initialize drawer
    createEnhancedEditDrawer(studentData);
    
    // Show drawer with animation
    requestAnimationFrame(() => {
        const drawer = document.getElementById("enhancedEditDrawer");
        if (drawer) {
            drawer.classList.remove("translate-x-full");
            drawer.querySelector(".drawer-panel").classList.remove("translate-x-full");
        }
    });
    
    // Prevent body scroll
    document.body.style.overflow = "hidden";
    
    // Initialize enhanced features
    initializeEnhancedDrawerFeatures();
    
    // Set existing birth date in Ethiopian calendar after initialization
    setTimeout(() => {
        if (window.setEditEthiopianBirthDate && studentData) {
            window.setEditEthiopianBirthDate(studentData);
        }
    }, 100); // Small delay to ensure calendar is initialized
    
    // Add keyboard shortcuts
    initializeKeyboardShortcuts();
    
    showToast("Enhanced edit drawer opened - Ready for professional editing!", "success");
};

// Create enhanced edit drawer with modern tabbed interface
function createEnhancedEditDrawer(studentData) {
    const drawerHTML = `
        <div id="enhancedEditDrawer" class="fixed inset-0 z-50 flex justify-end bg-black bg-opacity-50 backdrop-blur-sm translate-x-full transition-all duration-300 ease-in-out">
            <div class="drawer-panel w-full max-w-2xl bg-white dark:bg-gray-900 shadow-xl transform translate-x-full transition-all duration-300 ease-in-out overflow-hidden flex flex-col h-screen">
                <!-- Header with gradient and enhanced styling -->
                <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-blue-500 text-white p-3 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <i class="fas fa-user-edit text-base"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Student Profile Editor</h2>
                                <p class="text-indigo-100 text-[11px] flex items-center">
                                    <span class="truncate max-w-xs">${studentData.full_name || "Student Details"}</span>
                                    <span class="ml-2 px-2 py-0.5 bg-white bg-opacity-20 rounded-full text-xs">
                                        ID: ${studentData.id || "N/A"}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div id="saveStatusIndicator" class="flex items-center space-x-2 text-[11px] bg-white bg-opacity-10 px-2 py-0.5 rounded-full">
                                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                <span>Ready</span>
                            </div>
                            <button onclick="closeEnhancedEditDrawer()" class="p-1 hover:bg-white hover:bg-opacity-20 rounded-full transition-all duration-200">
                                <i class="fas fa-times text-base"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced Tab Navigation with Progress Indicator -->
                <div class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-3">
                    <div class="flex items-center justify-between py-1.5">
                        <nav class="flex space-x-1 overflow-x-auto text-[13px]">
                            <button onclick="switchEnhancedTab('profile')" class="tab-btn-enhanced tab-btn-active py-1.5 px-2.5 text-[13px] font-medium rounded-md transition-all duration-200 flex items-center">
                                <i class="fas fa-user-circle mr-2"></i>Profile
                            </button>
                            <button onclick="switchEnhancedTab('academic')" class="tab-btn-enhanced py-1.5 px-2.5 text-[13px] font-medium rounded-md transition-all duration-200 flex items-center">
                                <i class="fas fa-graduation-cap mr-2"></i>Academic
                            </button>
                            <button onclick="switchEnhancedTab('family')" class="tab-btn-enhanced py-1.5 px-2.5 text-[13px] font-medium rounded-md transition-all duration-200 flex items-center">
                                <i class="fas fa-users mr-2"></i>Family
                            </button>
                            <button onclick="switchEnhancedTab('contact')" class="tab-btn-enhanced py-1.5 px-2.5 text-[13px] font-medium rounded-md transition-all duration-200 flex items-center">
                                <i class="fas fa-address-book mr-2"></i>Contact
                            </button>
                            <button onclick="switchEnhancedTab('additional')" class="tab-btn-enhanced py-1.5 px-2.5 text-[13px] font-medium rounded-md transition-all duration-200 flex items-center">
                                <i class="fas fa-plus-circle mr-2"></i>Additional
                            </button>
                        </nav>
                        <div class="hidden md:flex items-center space-x-2">
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">Progress:</span>
                            <div class="w-16 h-1 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div id="completionProgress" class="h-full bg-gradient-to-r from-green-400 to-blue-500 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Content Area with Scrollable Sections -->
                <div class="flex-1 overflow-y-auto p-3 text-[13px]">
                    <form id="enhancedStudentEditForm" class="space-y-3">
                        <!-- Profile Tab -->
                        <div id="profileTab" class="tab-content-enhanced">
                            ${createEnhancedProfileTab(studentData)}
                        </div>
                        
                        <!-- Academic Tab -->
                        <div id="academicTab" class="tab-content-enhanced hidden">
                            ${createEnhancedAcademicTab(studentData)}
                        </div>
                        
                        <!-- Family Tab -->
                        <div id="familyTab" class="tab-content-enhanced hidden">
                            ${createEnhancedFamilyTab(studentData)}
                        </div>
                        
                        <!-- Contact Tab -->
                        <div id="contactTab" class="tab-content-enhanced hidden">
                            ${createEnhancedContactTab(studentData)}
                        </div>
                        
                        <!-- Additional Tab -->
                        <div id="additionalTab" class="tab-content-enhanced hidden">
                            ${createEnhancedAdditionalTab(studentData)}
                        </div>
                    </form>
                </div>
                
                <!-- Enhanced Footer Actions -->
                <div class="bg-gray-50 dark:bg-gray-800 p-3 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <button onclick="resetEnhancedForm()" class="px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-md font-medium transition-all duration-150 flex items-center">
                                <i class="fas fa-undo mr-1"></i>Reset
                            </button>
                            <button onclick="previewChanges()" class="px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-md font-medium transition-all duration-150 flex items-center">
                                <i class="fas fa-eye mr-1"></i>Preview
                            </button>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="saveEnhancedStudentChanges(false)" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-4 py-1.5 rounded-md text-sm font-medium transition-all duration-150 shadow-md hover:shadow-lg flex items-center">
                                <i class="fas fa-save mr-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced Camera Modal -->
                <div id="enhanced-camera-modal" class="hidden absolute inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-96 shadow-2xl relative">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Capture Photo</h3>
                            <button type="button" onclick="closeEnhancedCameraModal()" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <video id="enhanced-camera-video" autoplay playsinline class="w-full h-64 bg-gray-200 dark:bg-gray-700 rounded-lg"></video>
                        <canvas id="enhanced-camera-canvas" class="hidden"></canvas>
                        <div class="flex justify-between mt-4">
                            <button type="button" onclick="switchEnhancedCamera()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-sync-alt mr-2"></i>Switch Camera
                            </button>
                            <button type="button" onclick="captureEnhancedPhoto()" class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-camera mr-2"></i>Capture
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Changes Preview Modal -->
                <div id="changes-preview-modal" class="hidden absolute inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-2xl shadow-2xl relative max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Changes Preview</h3>
                            <button type="button" onclick="closeChangesPreview()" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="changes-preview-content" class="space-y-4">
                            <!-- Changes will be populated here -->
                        </div>
                        <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" onclick="closeChangesPreview()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg transition-all duration-200 mr-2">
                                Close
                            </button>
                            <button type="button" onclick="saveEnhancedStudentChanges()" class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg transition-all duration-200">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', drawerHTML);
}

// Create Enhanced Profile Tab
function createEnhancedProfileTab(student) {
    return `
        <div class="space-y-3">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                <div>
                    ${createEnhancedPhotoSection(student)}
                </div>
                <div class="lg:col-span-2">
                    ${createEnhancedBasicInfoSection(student)}
                </div>
            </div>
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-800 p-4 rounded-2xl border border-blue-100 dark:border-gray-700">
                <h3 class="text-base font-semibold mb-3 text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>Personal Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Gender</label>
                        <select name="gender" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                            <option value="">Select Gender</option>
                            <option value="male"${String(student.gender).toLowerCase() === 'male' ? ' selected' : ''}>Male</option>
                            <option value="female"${String(student.gender).toLowerCase() === 'female' ? ' selected' : ''}>Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-1">የተወለዱበት ቀን (ዓ.ም) <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <select id="edit_birth_year_et" name="birth_year_et" class="px-2.5 py-2 border rounded dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" required>
                                <option value="">ዓመት</option>
                            </select>
                            <select id="edit_birth_month_et" name="birth_month_et" class="px-2.5 py-2 border rounded dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" required>
                                <option value="">ወር</option>
                            </select>
                            <select id="edit_birth_day_et" name="birth_day_et" class="px-2.5 py-2 border rounded dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" required>
                                <option value="">ቀን</option>
                            </select>
                        </div>
                        <!-- Hidden field to maintain compatibility with existing backend -->
                        <input type="hidden" name="birth_date" id="edit_birth_date_hidden">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Grade</label>
                        <select name="current_grade" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" data-selected-grade="${String(student.current_grade||'')}">
                            <option value="">Select Grade</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                        <input type="tel" name="phone_number" value="${student.phone_number || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Create Enhanced Photo Section
function createEnhancedPhotoSection(student) {
    const src = student.photo_path || student.person_photo_path || '';
    const preview = src ? `<img id="enhanced-photo-preview" src="${src}" class="w-24 h-24 rounded-xl object-cover ring-2 ring-indigo-200 dark:ring-indigo-900 shadow" />` : `<div id="enhanced-photo-preview-ph" class="w-24 h-24 rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-semibold text-sm">IMG</div>`;
    return `
        <div class="p-3 rounded-xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex flex-col items-center gap-4">
                ${preview}
                <div class="w-full space-y-2">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Profile Photo</label>
                    <input type="file" name="student_photo" accept="image/*" class="hidden" onchange="window.__onEnhancedPhotoChange && __onEnhancedPhotoChange(event)">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="flex-1 px-2.5 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition-all duration-200 flex items-center justify-center" onclick="document.querySelector('#enhancedEditDrawer input[name=\\'student_photo\\']').click()">
                            <i class="fas fa-upload mr-1"></i>Upload
                        </button>
                        <button type="button" class="flex-1 px-2.5 py-1.5 text-xs bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-md transition-all duration-200 flex items-center justify-center" onclick="openEnhancedCameraModal()">
                            <i class="fas fa-camera mr-1"></i>Camera
                        </button>
                        <button type="button" class="flex-1 px-2.5 py-1.5 text-xs bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md transition-all duration-200 flex items-center justify-center" onclick="window.__clearEnhancedPhoto && __clearEnhancedPhoto()">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                    <label class="inline-flex items-center text-xs text-gray-600 dark:text-gray-400">
                        <input type="checkbox" name="remove_photo" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 mr-2">
                        Remove current photo
                    </label>
                </div>
            </div>
        </div>
    `;
}

// Create Enhanced Basic Info Section
function createEnhancedBasicInfoSection(student) {
    // Check if this is an instrument registration
    const isInstrument = window.currentEditingTable === 'instruments';
    
    return `
        <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm h-full">
            <h3 class="text-sm font-semibold mb-3 text-gray-800 dark:text-white flex items-center">
                <i class="fas fa-user mr-2 text-indigo-500"></i>Basic Information ${isInstrument ? '(Instrument Registration)' : ''}
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" value="${student.full_name || ''}" class="w-full px-2.5 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Christian Name</label>
                    <input type="text" name="christian_name" value="${student.christian_name || ''}" class="w-full px-2.5 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                </div>
                ${isInstrument ? `
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Instrument <span class="text-red-500">*</span></label>
                    <select name="instrument" required class="w-full px-2.5 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                        <option value="">Select Instrument</option>
                        <option value="begena"${(student.instrument||'') === 'begena' ? ' selected' : ''}>በገና (Begena)</option>
                        <option value="masenqo"${(student.instrument||'') === 'masenqo' ? ' selected' : ''}>መሰንቆ (Masenqo)</option>
                        <option value="kebero"${(student.instrument||'') === 'kebero' ? ' selected' : ''}>ከበሮ (Kebero)</option>
                        <option value="krar"${(student.instrument||'') === 'krar' ? ' selected' : ''}>ክራር (Krar)</option>
                    </select>
                </div>
                ` : ''}
            </div>
        </div>
    `;
}

// Create Enhanced Academic Tab
function createEnhancedAcademicTab(student) {
    return `
        <div class="space-y-4">
            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <h3 class="text-sm font-semibold mb-3 text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-school mr-2 text-indigo-500"></i>Academic Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">School Year Start</label>
                        <input type="number" name="school_year_start" value="${student.school_year_start || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Regular School Grade</label>
                        <input type="text" name="regular_school_grade" value="${student.regular_school_grade || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Regular School Name</label>
                        <input type="text" name="regular_school_name" value="${student.regular_school_name || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Education Level</label>
                        <input type="text" name="education_level" value="${student.education_level || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Field of Study</label>
                        <input type="text" name="field_of_study" value="${student.field_of_study || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Create Enhanced Family Tab
function createEnhancedFamilyTab(student) {
    return `
        <div class="space-y-4">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-800 p-3 rounded-xl border border-blue-100 dark:border-gray-700 shadow-sm">
                <h3 class="text-sm font-semibold mb-3 text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-male mr-2 text-blue-500"></i>Father Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                        <input type="text" name="father_full_name" value="${student.father_full_name || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                        <input type="tel" name="father_phone" value="${student.father_phone || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Occupation</label>
                        <input type="text" name="father_occupation" value="${student.father_occupation || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-pink-50 to-rose-50 dark:from-gray-800 dark:to-gray-800 p-3 rounded-xl border border-pink-100 dark:border-gray-700 shadow-sm">
                <h3 class="text-sm font-semibold mb-3 text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-female mr-2 text-pink-500"></i>Mother Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                        <input type="text" name="mother_full_name" value="${student.mother_full_name || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                        <input type="tel" name="mother_phone" value="${student.mother_phone || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Occupation</label>
                        <input type="text" name="mother_occupation" value="${student.mother_occupation || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-gray-50 to-neutral-50 dark:from-gray-800 dark:to-gray-800 p-3 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <h3 class="text-sm font-semibold mb-3 text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-user-friends mr-2 text-gray-500"></i>Guardian Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                        <input type="text" name="guardian_full_name" value="${student.guardian_full_name || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                        <input type="tel" name="guardian_phone" value="${student.guardian_phone || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Occupation</label>
                        <input type="text" name="guardian_occupation" value="${student.guardian_occupation || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Create Enhanced Contact Tab
function createEnhancedContactTab(student) {
    return `
        <div class="space-y-4">
            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <h3 class="text-sm font-semibold mb-3 text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-home mr-2 text-indigo-500"></i>Address Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sub City</label>
                        <input type="text" name="sub_city" value="${student.sub_city || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">District</label>
                        <input type="text" name="district" value="${student.district || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Specific Area</label>
                        <input type="text" name="specific_area" value="${student.specific_area || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">House Number</label>
                        <input type="text" name="house_number" value="${student.house_number || ''}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-rose-50 to-red-50 dark:from-gray-800 dark:to-gray-800 p-2 rounded-xl border border-rose-100 dark:border-gray-700 shadow-sm">
                <h3 class="text-xs font-semibold mb-2 text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-ambulance mr-2 text-rose-500"></i>Emergency Contact
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xxs font-medium text-gray-700 dark:text-gray-300 mb-1">Emergency Name</label>
                        <input type="text" name="emergency_name" value="${student.emergency_name || ''}" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xxs font-medium text-gray-700 dark:text-gray-300 mb-1">Emergency Phone</label>
                        <input type="tel" name="emergency_phone" value="${student.emergency_phone || ''}" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xxs font-medium text-gray-700 dark:text-gray-300 mb-1">Emergency Alt Phone</label>
                        <input type="tel" name="emergency_alt_phone" value="${student.emergency_alt_phone || ''}" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-xxs font-medium text-gray-700 dark:text-gray-300 mb-1">Emergency Address</label>
                        <input type="text" name="emergency_address" value="${student.emergency_address || ''}" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Create Enhanced Additional Tab
function createEnhancedAdditionalTab(student) {
    return `
        <div class="space-y-8">
            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <h3 class="text-lg font-semibold mb-5 text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-heart mr-2 text-indigo-500"></i>Spiritual Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Has Spiritual Father</label>
                        <select name="has_spiritual_father" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                            <option value="">Select Option</option>
                            ${['own','family','none']
                                .map(v => `<option value="${v}"${String(student.has_spiritual_father||'')===v?' selected':''}>${v.replace(/\b\w/g, l => l.toUpperCase())}</option>`).join('')}
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Spiritual Father Name</label>
                        <input type="text" name="spiritual_father_name" value="${student.spiritual_father_name || ''}" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Spiritual Father Phone</label>
                        <input type="tel" name="spiritual_father_phone" value="${student.spiritual_father_phone || ''}" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Spiritual Father Church</label>
                        <input type="text" name="spiritual_father_church" value="${student.spiritual_father_church || ''}" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-gray-800 dark:to-gray-800 p-6 rounded-2xl border border-emerald-100 dark:border-gray-700 shadow-sm">
                <h3 class="text-lg font-semibold mb-5 text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-plus-circle mr-2 text-emerald-500"></i>Additional Information
                </h3>
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Special Interests</label>
                        <textarea name="special_interests" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" rows="3">${student.special_interests || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Siblings In School</label>
                        <textarea name="siblings_in_school" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" rows="3">${student.siblings_in_school || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Physical Disability</label>
                        <textarea name="physical_disability" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" rows="3">${student.physical_disability || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Weak Side</label>
                        <textarea name="weak_side" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" rows="3">${student.weak_side || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Transferred From Other School</label>
                        <textarea name="transferred_from_other_school" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" rows="3">${student.transferred_from_other_school || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Came From Other Religion</label>
                        <textarea name="came_from_other_religion" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200" rows="3">${student.came_from_other_religion || ''}</textarea>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Initialize enhanced drawer features
function initializeEnhancedDrawerFeatures() {
    initializeEnhancedFormWatchers();
    updateCompletionProgress();
    
    // Initialize Ethiopian calendar dropdowns for edit mode
    initializeEditEthiopianCalendar();
    
    // Load grade options from backend and populate current_grade
    try {
        const form = document.getElementById('enhancedStudentEditForm');
        const gradeSelect = form ? form.querySelector('select[name="current_grade"]') : null;
        if (gradeSelect) {
            const previouslySelected = gradeSelect.getAttribute('data-selected-grade') || '';
            const fd = new FormData();
            fd.append('action', 'get_grade_options');
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (!data || !data.success || !Array.isArray(data.grades)) return;
                    const uniqueGrades = Array.from(new Set(data.grades.filter(Boolean)));
                    const options = ['<option value="">Select Grade</option>']
                        .concat(uniqueGrades.map(g => {
                            const val = String(g);
                            const sel = previouslySelected && String(previouslySelected) === val ? ' selected' : '';
                            return `<option value="${val}"${sel}>${val.toUpperCase()}</option>`;
                        }));
                    gradeSelect.innerHTML = options.join('');
                })
                .catch(() => { /* silent fail, keep placeholder */ });
        }
    } catch (e) { /* ignore */ }
    
    // Photo preview handlers
    window.__onEnhancedPhotoChange = function(e){
        const input = e.target;
        const file = input && input.files && input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev){
            const img = document.getElementById('enhanced-photo-preview');
            const ph = document.getElementById('enhanced-photo-preview-ph');
            if (img) {
                img.src = ev.target.result;
            } else if (ph) {
                const parent = ph.parentElement;
                ph.remove();
                const newImg = document.createElement('img');
                newImg.id = 'enhanced-photo-preview';
                newImg.className = 'w-32 h-32 rounded-2xl object-cover ring-4 ring-indigo-200 dark:ring-indigo-900 shadow-lg';
                newImg.src = ev.target.result;
                parent.prepend(newImg);
            }
        };
        reader.readAsDataURL(file);
    };
    window.__clearEnhancedPhoto = function(){
        const input = document.querySelector('#enhancedEditDrawer input[name="student_photo"]');
        if (input) input.value = '';
        const img = document.getElementById('enhanced-photo-preview');
        if (img) img.src = '';
    };
    
    // Camera helpers
    let __enhancedCameraStream = null;
    let __enhancedFacingMode = 'user';
    window.openEnhancedCameraModal = function(){
        const modal = document.getElementById('enhanced-camera-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        startEnhancedCamera(__enhancedFacingMode);
    };
    window.closeEnhancedCameraModal = function(){
        const modal = document.getElementById('enhanced-camera-modal');
        if (modal) modal.classList.add('hidden');
        stopEnhancedCamera();
    };
    function startEnhancedCamera(facing){
        const video = document.getElementById('enhanced-camera-video');
        if (!video) return;
        stopEnhancedCamera();
        navigator.mediaDevices.getUserMedia({ video: { facingMode: facing } })
            .then(stream => {
                __enhancedCameraStream = stream;
                video.srcObject = stream;
            })
            .catch(() => { alert('Camera not available.'); closeEnhancedCameraModal(); });
    }
    function stopEnhancedCamera(){
        const video = document.getElementById('enhanced-camera-video');
        if (video) video.srcObject = null;
        if (__enhancedCameraStream) {
            __enhancedCameraStream.getTracks().forEach(t => t.stop());
            __enhancedCameraStream = null;
        }
    }
    window.switchEnhancedCamera = function(){
        __enhancedFacingMode = __enhancedFacingMode === 'user' ? 'environment' : 'user';
        startEnhancedCamera(__enhancedFacingMode);
    };
    window.captureEnhancedPhoto = function(){
        const video = document.getElementById('enhanced-camera-video');
        const canvas = document.getElementById('enhanced-camera-canvas');
        if (!video || !canvas) return;
        canvas.width = video.videoWidth || 320;
        canvas.height = video.videoHeight || 240;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(blob => {
            const file = new File([blob], 'captured_photo.jpg', { type: 'image/jpeg' });
            const dt = new DataTransfer();
            dt.items.add(file);
            const input = document.querySelector('#enhancedEditDrawer input[name="student_photo"]');
            if (input) input.files = dt.files;
            // Preview
            const reader = new FileReader();
            reader.onload = function(ev){
                const img = document.getElementById('enhanced-photo-preview');
                const ph = document.getElementById('enhanced-photo-preview-ph');
                if (img) { img.src = ev.target.result; }
                else if (ph) {
                    const parent = ph.parentElement;
                    ph.remove();
                    const newImg = document.createElement('img');
                    newImg.id = 'enhanced-photo-preview';
                    newImg.className = 'w-32 h-32 rounded-2xl object-cover ring-4 ring-indigo-200 dark:ring-indigo-900 shadow-lg';
                    newImg.src = ev.target.result;
                    parent.prepend(newImg);
                }
            };
            reader.readAsDataURL(file);
            closeEnhancedCameraModal();
        }, 'image/jpeg');
    };
}

// Initialize enhanced form watchers for changes
function initializeEnhancedFormWatchers() {
    window.hasUnsavedChanges = false;
    
    const form = document.getElementById('enhancedStudentEditForm');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            window.hasUnsavedChanges = true;
            updateCompletionProgress();
            updateSaveStatusIndicator('Unsaved changes');
        });
    });
}

// Update completion progress indicator
function updateCompletionProgress() {
    const form = document.getElementById('enhancedStudentEditForm');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, select, textarea');
    const totalFields = inputs.length;
    let filledFields = 0;
    
    inputs.forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio') {
            if (input.checked) filledFields++;
        } else if (input.value.trim() !== '') {
            filledFields++;
        }
    });
    
    const completionPercentage = totalFields > 0 ? Math.round((filledFields / totalFields) * 100) : 0;
    const progressBar = document.getElementById('completionProgress');
    if (progressBar) {
        progressBar.style.width = completionPercentage + '%';
    }
}

// Update save status indicator
function updateSaveStatusIndicator(status) {
    const indicator = document.getElementById('saveStatusIndicator');
    if (!indicator) return;
    
    let statusClass = '';
    let statusIcon = '';
    
    switch (status) {
        case 'Unsaved changes':
            statusClass = 'bg-yellow-100 text-yellow-800';
            statusIcon = '<div class="w-2 h-2 bg-yellow-400 rounded-full"></div>';
            break;
        case 'Saving...':
            statusClass = 'bg-blue-100 text-blue-800';
            statusIcon = '<div class="w-2 h-2 bg-blue-400 rounded-full animate-pulse"></div>';
            break;
        case 'Saved':
            statusClass = 'bg-green-100 text-green-800';
            statusIcon = '<div class="w-2 h-2 bg-green-400 rounded-full"></div>';
            break;
        default:
            statusClass = 'bg-green-100 text-green-800';
            statusIcon = '<div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>';
    }
    
    indicator.className = `flex items-center space-x-2 text-sm px-3 py-1 rounded-full ${statusClass}`;
    indicator.innerHTML = `${statusIcon}<span>${status}</span>`;
}

// Switch between enhanced tabs
function switchEnhancedTab(tabName) {
    // Update current active tab
    window.currentActiveTab = tabName;
    
    // Hide all tab contents
    document.querySelectorAll('.tab-content-enhanced').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabName + 'Tab');
    if (selectedTab) {
        selectedTab.classList.remove('hidden');
    }
    
    // Update tab button styles
    document.querySelectorAll('.tab-btn-enhanced').forEach(btn => {
        btn.classList.remove('tab-btn-active', 'bg-indigo-100', 'dark:bg-indigo-900', 'text-indigo-700', 'dark:text-indigo-200');
        btn.classList.add('text-gray-600', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
    });
    
    // Highlight active tab button
    const activeBtn = document.querySelector(`[onclick*="switchEnhancedTab('${tabName}')"]`);
    if (activeBtn) {
        activeBtn.classList.remove('text-gray-600', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
        activeBtn.classList.add('tab-btn-active', 'bg-indigo-100', 'dark:bg-indigo-900', 'text-indigo-700', 'dark:text-indigo-200');
    }
}

// Close enhanced edit drawer
function closeEnhancedEditDrawer() {
    if (window.hasUnsavedChanges) {
        if (!confirm('You have unsaved changes. Are you sure you want to close without saving?')) {
            return;
        }
    }
    
    const drawer = document.getElementById('enhancedEditDrawer');
    if (drawer) {
        drawer.classList.add('translate-x-full');
        setTimeout(() => {
            drawer.remove();
            document.body.style.overflow = 'auto';
            window.hasUnsavedChanges = false;
        }, 300);
    }
}

// Save enhanced student changes
function saveEnhancedStudentChanges(silent = false) {
    const form = document.getElementById('enhancedStudentEditForm');
    if (!form) {
        if (!silent) showToast('Form not found', 'error');
        return;
    }
    
    // Update status indicator
    updateSaveStatusIndicator('Saving...');
    
    const formData = new FormData(form);
    formData.append('action', 'update_student');
    formData.append('student_id', window.currentEditingStudent.id);
    formData.append('table', window.currentEditingTable || 'students');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.hasUnsavedChanges = false;
            updateSaveStatusIndicator('Saved');
            
            Object.assign(window.currentEditingStudent, data.student);
            
            if (!silent) {
                showToast('Student information updated successfully!', 'success');
            }
        } else {
            updateSaveStatusIndicator('Unsaved changes');
            if (!silent) {
                showToast(data.message || 'Failed to update student information', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        updateSaveStatusIndicator('Unsaved changes');
        if (!silent) {
            showToast('An error occurred while saving', 'error');
        }
    });
}

// Reset enhanced form
function resetEnhancedForm() {
    if (!window.originalStudentData) {
        showToast('No original data available', 'warning');
        return;
    }
    
    if (window.hasUnsavedChanges) {
        if (!confirm('This will discard all unsaved changes. Are you sure?')) {
            return;
        }
    }
    
    const form = document.getElementById('enhancedStudentEditForm');
    if (!form) return;
    
    Object.keys(window.originalStudentData).forEach(key => {
        const field = form.querySelector(`[name="${key}"]`);
        if (field) {
            field.value = window.originalStudentData[key] || '';
        }
    });
    
    window.hasUnsavedChanges = false;
    updateCompletionProgress();
    updateSaveStatusIndicator('Ready');
    showToast('Form reset to original values', 'info');
}

// Preview changes
function previewChanges() {
    const form = document.getElementById('enhancedStudentEditForm');
    if (!form) return;
    
    const changes = [];
    const formData = new FormData(form);
    
    // Compare form data with original data
    for (const [key, value] of formData.entries()) {
        if (key === 'student_photo') continue; // Skip file inputs
        if (key === 'remove_photo') continue; // Skip checkbox
        
        const originalValue = window.originalStudentData[key] || '';
        if (value !== originalValue) {
            changes.push({
                field: key,
                original: originalValue,
                new: value
            });
        }
    }
    
    // Display changes in modal
    const previewContent = document.getElementById('changes-preview-content');
    if (!previewContent) return;
    
    if (changes.length === 0) {
        previewContent.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-2">No Changes Detected</h3>
                <p class="text-gray-600 dark:text-gray-400">All fields match the original values.</p>
            </div>
        `;
    } else {
        let changesHTML = `
            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-6">
                <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-2">
                    <i class="fas fa-exclamation-circle mr-2"></i>Changes Summary
                </h3>
                <p class="text-blue-700 dark:text-blue-300">
                    ${changes.length} field${changes.length !== 1 ? 's' : ''} will be updated.
                </p>
            </div>
            <div class="space-y-4">
        `;
        
        changes.forEach(change => {
            changesHTML += `
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">${formatFieldName(change.field)}</h4>
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Original Value</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 p-2 bg-gray-50 dark:bg-gray-800 rounded">${change.original || '<span class="text-gray-400">Empty</span>'}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">New Value</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 p-2 bg-green-50 dark:bg-green-900/20 rounded">${change.new || '<span class="text-gray-400">Empty</span>'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        changesHTML += `</div>`;
        previewContent.innerHTML = changesHTML;
    }
    
    // Show preview modal
    const modal = document.getElementById('changes-preview-modal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

// Close changes preview modal
function closeChangesPreview() {
    const modal = document.getElementById('changes-preview-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Format field names for display
function formatFieldName(fieldName) {
    // Convert snake_case to Title Case with spaces
    return fieldName
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase());
}

// Initialize keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Only handle shortcuts when drawer is open
        if (!document.getElementById('enhancedEditDrawer')) return;
        
        // Ctrl+S - Save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            saveEnhancedStudentChanges();
        }
        
        // Escape - Close
        if (e.key === 'Escape') {
            closeEnhancedEditDrawer();
        }
        
        // Ctrl+R - Reset
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            resetEnhancedForm();
        }
        
        // Ctrl+1-5 - Switch tabs
        if (e.ctrlKey && e.key >= '1' && e.key <= '5') {
            e.preventDefault();
            const tabs = ['profile', 'academic', 'family', 'contact', 'additional'];
            const tabIndex = parseInt(e.key) - 1;
            if (tabs[tabIndex]) {
                switchEnhancedTab(tabs[tabIndex]);
            }
        }
    });
}

// Make functions globally available
window.switchEnhancedTab = switchEnhancedTab;
window.closeEnhancedEditDrawer = closeEnhancedEditDrawer;
window.saveEnhancedStudentChanges = saveEnhancedStudentChanges;
window.resetEnhancedForm = resetEnhancedForm;
window.previewChanges = previewChanges;
window.closeChangesPreview = closeChangesPreview;

// Ethiopian Calendar Functions for Edit Mode
function initializeEditEthiopianCalendar() {
    const ySel = document.getElementById('edit_birth_year_et');
    const mSel = document.getElementById('edit_birth_month_et');
    const dSel = document.getElementById('edit_birth_day_et');
    const hiddenField = document.getElementById('edit_birth_date_hidden');
    
    if (!ySel || !mSel || !dSel) {
        console.log('Ethiopian calendar elements not found, skipping initialization');
        return;
    }
    
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
        const min = current - 40; // Extended range for editing
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
        updateHiddenBirthDate();
    }
    
    function updateHiddenBirthDate() {
        const y = ySel.value;
        const m = mSel.value;
        const d = dSel.value;
        
        if (y && m && d && hiddenField) {
            // Format as YYYY-MM-DD for backend compatibility
            const formattedDate = y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            hiddenField.value = formattedDate;
        } else if (hiddenField) {
            hiddenField.value = '';
        }
    }
    
    // Set up existing birth date from student data
    function setExistingBirthDate(student) {
        if (student.birth_year_et && student.birth_month_et && student.birth_day_et) {
            // Use existing Ethiopian calendar data
            ySel.value = student.birth_year_et;
            mSel.value = student.birth_month_et;
            populateDays();
            dSel.value = student.birth_day_et;
            updateHiddenBirthDate();
        } else if (student.birth_date) {
            // Parse from birth_date string (YYYY-MM-DD format)
            const parts = student.birth_date.split('-');
            if (parts.length === 3) {
                ySel.value = parts[0];
                mSel.value = parseInt(parts[1], 10).toString();
                populateDays();
                dSel.value = parseInt(parts[2], 10).toString();
                updateHiddenBirthDate();
            }
        }
    }
    
    // Event listeners
    ySel.addEventListener('change', function() { 
        populateDays(); 
        updateHiddenBirthDate();
    });
    mSel.addEventListener('change', function() { 
        populateDays(); 
        updateHiddenBirthDate();
    });
    dSel.addEventListener('change', function() {
        updateHiddenBirthDate();
    });
    
    // Initialize
    populateYears();
    populateMonths();
    
    // Store the setter function globally so it can be called when student data is loaded
    window.setEditEthiopianBirthDate = setExistingBirthDate;
    
    console.log('Ethiopian calendar for edit mode initialized');
}