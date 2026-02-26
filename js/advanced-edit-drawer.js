/**
 * Advanced Edit Drawer JavaScript Functions
 * Complete drawer functionality extracted from students.php
 */

// Global drawer variables
let currentEditingStudent = null;
let currentEditingTable = 'students';
let originalStudentData = null;
let hasUnsavedChanges = false;

// Advanced Edit Drawer Implementation
window.openEditDrawer = function(studentData, table) {
    // Remove existing drawer if present
    const existingDrawer = document.getElementById("editDrawer");
    if (existingDrawer) {
        existingDrawer.remove();
    }
    
    // Store current editing data
    window.currentEditingStudent = { ...studentData };
    window.currentEditingTable = table || "students";
    window.originalStudentData = { ...studentData };
    
    // Create and initialize drawer
    createAdvancedEditDrawer(studentData);
    
    // Show drawer with animation
    requestAnimationFrame(() => {
        const drawer = document.getElementById("editDrawer");
        if (drawer) {
            drawer.classList.remove("translate-x-full");
            drawer.querySelector(".drawer-panel").classList.remove("translate-x-full");
        }
    });
    
    // Prevent body scroll
    document.body.style.overflow = "hidden";
    
    // Initialize advanced features
    initializeAdvancedDrawerFeatures();
    
    // Set existing birth date in Ethiopian calendar after initialization
    setTimeout(() => {
        if (window.setAdvancedEthiopianBirthDate && studentData) {
            window.setAdvancedEthiopianBirthDate(studentData);
        }
    }, 100); // Small delay to ensure calendar is initialized
    
    showToast("Edit drawer opened - Ready for advanced editing!", "success");
};

// Create advanced edit drawer with tabbed interface
function createAdvancedEditDrawer(studentData) {
    const drawerHTML = `
        <div id="editDrawer" class="fixed inset-0 z-50 flex justify-end bg-black bg-opacity-40 backdrop-blur-sm translate-x-full transition-transform duration-300 ease-in-out">
            <div class="drawer-panel w-full max-w-xl bg-white dark:bg-gray-800 shadow-xl transform translate-x-full transition-transform duration-300 ease-in-out overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-9 h-9 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <i class="fas fa-user-edit text-lg"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold">Edit Student Profile</h2>
                                <p class="text-blue-100 text-xs">${studentData.full_name || "Student Details"}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div id="saveStatus" class="flex items-center space-x-2 text-xs">
                                <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                <span>Ready</span>
                            </div>
                            <button onclick="closeAdvancedEditDrawer()" class="p-1.5 hover:bg-white hover:bg-opacity-20 rounded-full transition-colors">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Navigation -->
                <div class="bg-gray-50 dark:bg-gray-700 border-b px-4">
                    <nav class="flex space-x-6 overflow-x-auto">
                        <button onclick="switchAdvancedTab('profile')" class="tab-btn tab-btn-active py-2 px-2 text-sm font-medium border-b-2 border-blue-500 text-blue-600 whitespace-nowrap">
                            <i class="fas fa-id-card mr-2"></i>Profile
                        </button>
                        <button onclick="switchAdvancedTab('contacts')" class="tab-btn py-2 px-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap">
                            <i class="fas fa-address-book mr-2"></i>Contacts & Family
                        </button>
                    </nav>
                </div>
                
                <!-- Content Area -->
                <div class="flex-1 overflow-y-auto p-4">
                    <form id="advancedStudentEditForm" class="space-y-4">
                        <!-- Profile Tab (composed) -->
                        <div id="profileTab" class="tab-content">
                            ${createProfileTab(studentData)}
                        </div>
                        <!-- Contacts & Family Tab (composed) -->
                        <div id="contactsTab" class="tab-content hidden">
                            ${createContactsTab(studentData)}
                        </div>
                    </form>
                </div>
                
                <!-- Footer Actions -->
                <div class="bg-gray-50 dark:bg-gray-700 p-3 border-t">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <button onclick="resetAdvancedForm()" class="px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md font-medium">
                                <i class="fas fa-undo mr-1"></i>Reset
                            </button>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="saveAdvancedStudentChanges(false)" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-md text-sm font-medium transition-colors">
                                <i class="fas fa-save mr-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Camera Modal -->
                <div id="drawer-camera-modal" class="hidden absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 w-80 shadow-2xl relative">
                        <button type="button" onclick="closeDrawerCameraModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800"><i class="fas fa-times"></i></button>
                        <video id="drawer-camera-video" autoplay playsinline class="w-full h-48 bg-gray-200 rounded"></video>
                        <canvas id="drawer-camera-canvas" class="hidden"></canvas>
                        <div class="flex justify-between mt-4">
                            <button type="button" onclick="switchDrawerCamera()" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded text-gray-800"><i class="fas fa-sync-alt mr-1"></i>Switch</button>
                            <button type="button" onclick="captureDrawerPhoto()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded"><i class="fas fa-camera mr-1"></i>Capture</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', drawerHTML);
}

// Create Basic Info Tab
function createBasicInfoSection(student) {
    return `<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" value="${student.full_name || ''}" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Christian Name</label>
            <input type="text" name="christian_name" value="${student.christian_name || ''}" class="form-input">
        </div>
        <div>
            <label class="form-label">Gender</label>
            <select name="gender" class="form-input">
                <option value="">Select Gender</option>
                <option value="male"${String(student.gender).toLowerCase() === 'male' ? ' selected' : ''}>Male</option>
                <option value="female"${String(student.gender).toLowerCase() === 'female' ? ' selected' : ''}>Female</option>
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">የተወለዱበት ቀን (ዓ.ም) <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <select id="birth_year_et" name="birth_year_et" class="px-3 py-2 border rounded" required>
                    <option value="">ዓመት</option>
                </select>
                <select id="birth_month_et" name="birth_month_et" class="px-3 py-2 border rounded" required>
                    <option value="">ወር</option>
                </select>
                <select id="birth_day_et" name="birth_day_et" class="px-3 py-2 border rounded" required>
                    <option value="">ቀን</option>
                </select>
            </div>
            <!-- Hidden field to maintain compatibility with existing backend -->
            <input type="hidden" name="birth_date" id="birth_date_hidden">
        </div>
        <div>
            <label class="form-label">Current Grade</label>
            <select name="current_grade" class="form-input" data-selected-grade="${String(student.current_grade||'')}">
                <option value="">Select Grade</option>
            </select>
        </div>
        <div>
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone_number" value="${student.phone_number || ''}" class="form-input">
        </div>
    </div>`;
}

function createPhotoSection(student) {
    const src = student.photo_path || '';
    const preview = src ? `<img id="adv-photo-preview" src="${src}" class="w-28 h-28 rounded-full object-cover ring-2 ring-blue-200 shadow" />` : `<div id="adv-photo-preview-ph" class="w-28 h-28 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-600 font-semibold">IMG</div>`;
    return `
        <div class="p-4 rounded-xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 border border-gray-200 dark:border-gray-600 shadow-sm">
            <div class="flex items-center gap-4">
                ${preview}
                <div class="space-y-2">
                    <label class="form-label">Photo</label>
                    <input type="file" name="student_photo" accept="image/*" class="form-input" onchange="window.__onPhotoChange && __onPhotoChange(event)">
                    <label class="inline-flex items-center gap-2 text-xs"><input type="checkbox" name="remove_photo"> Remove photo</label>
                    <div class="flex gap-2">
                        <button type="button" class="px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded" onclick="document.querySelector('#editDrawer input[name=\\'student_photo\\']').click()"><i class="fas fa-upload mr-1"></i>Upload</button>
                        <button type="button" class="px-3 py-1.5 text-xs bg-green-600 hover:bg-green-700 text-white rounded" onclick="openDrawerCameraModal()"><i class="fas fa-camera mr-1"></i>Camera</button>
                        <button type="button" class="px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 rounded" onclick="window.__clearPhoto && __clearPhoto()"><i class="fas fa-times mr-1"></i>Clear</button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Create School Tab
function createSchoolSection(student) {
    return `<div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">School Year Start</label>
                <input type="number" name="school_year_start" value="${student.school_year_start || ''}" class="form-input">
            </div>
            <div>
                <label class="form-label">Regular School Grade</label>
                <input type="text" name="regular_school_grade" value="${student.regular_school_grade || ''}" class="form-input">
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Regular School Name</label>
                <input type="text" name="regular_school_name" value="${student.regular_school_name || ''}" class="form-input">
            </div>
            <div>
                <label class="form-label">Education Level</label>
                <input type="text" name="education_level" value="${student.education_level || ''}" class="form-input">
            </div>
            <div>
                <label class="form-label">Field of Study</label>
                <input type="text" name="field_of_study" value="${student.field_of_study || ''}" class="form-input">
            </div>
        </div>
    </div>`;
}

// Create Address Tab
function createAddressSection(student) {
    return `<div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Sub City</label>
                    <input type="text" name="sub_city" value="${student.sub_city || ''}" class="form-input">
                </div>
                <div>
                    <label class="form-label">District</label>
                    <input type="text" name="district" value="${student.district || ''}" class="form-input">
                </div>
            <div>
                <label class="form-label">Specific Area</label>
                <input type="text" name="specific_area" value="${student.specific_area || ''}" class="form-input">
            </div>
            <div>
                <label class="form-label">House Number</label>
                <input type="text" name="house_number" value="${student.house_number || ''}" class="form-input">
            </div>
            <div>
                <label class="form-label">Living With</label>
                <select name="living_with" class="form-input">
                    ${['both_parents','father_only','mother_only','relative_or_guardian']
                        .map(v => `<option value="${v}"${String(student.living_with||'')===v?' selected':''}>${v.replace(/_/g,' ')}</option>`).join('')}
                </select>
            </div>
        </div>
    </div>`;
}

// Create Spiritual Tab
function createSpiritualSection(student) {
    return `<div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Has Spiritual Father</label>
                <select name="has_spiritual_father" class="form-input">
                    ${['own','family','none']
                        .map(v => `<option value="${v}"${String(student.has_spiritual_father||'')===v?' selected':''}>${v}</option>`).join('')}
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Spiritual Father Name</label>
                <input type="text" name="spiritual_father_name" value="${student.spiritual_father_name || ''}" class="form-input">
            </div>
            <div>
                <label class="form-label">Spiritual Father Phone</label>
                <input type="tel" name="spiritual_father_phone" value="${student.spiritual_father_phone || ''}" class="form-input">
            </div>
            <div>
                <label class="form-label">Spiritual Father Church</label>
                <input type="text" name="spiritual_father_church" value="${student.spiritual_father_church || ''}" class="form-input">
            </div>
        </div>
    </div>`;
}

// Create Emergency Tab
function createEmergencySection(student) {
    return `<div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                <label class="form-label">Emergency Name</label>
                    <input type="text" name="emergency_name" value="${student.emergency_name || ''}" class="form-input">
                </div>
                <div>
                <label class="form-label">Emergency Phone</label>
                    <input type="tel" name="emergency_phone" value="${student.emergency_phone || ''}" class="form-input">
                </div>
            <div>
                <label class="form-label">Emergency Alt Phone</label>
                <input type="tel" name="emergency_alt_phone" value="${student.emergency_alt_phone || ''}" class="form-input">
            </div>
            <div>
                <label class="form-label">Emergency Address</label>
                <input type="text" name="emergency_address" value="${student.emergency_address || ''}" class="form-input">
            </div>
        </div>
    </div>`;
}

// Create Health & Misc Tab
function createHealthSection(student) {
    return `<div class="space-y-6">
        <div>
            <label class="form-label">Special Interests</label>
            <textarea name="special_interests" class="form-input">${student.special_interests || ''}</textarea>
        </div>
        <div>
            <label class="form-label">Siblings In School</label>
            <textarea name="siblings_in_school" class="form-input">${student.siblings_in_school || ''}</textarea>
        </div>
        <div>
            <label class="form-label">Physical Disability</label>
            <textarea name="physical_disability" class="form-input">${student.physical_disability || ''}</textarea>
        </div>
        <div>
            <label class="form-label">Weak Side</label>
            <textarea name="weak_side" class="form-input">${student.weak_side || ''}</textarea>
        </div>
        <div>
            <label class="form-label">Transferred From Other School</label>
            <textarea name="transferred_from_other_school" class="form-input">${student.transferred_from_other_school || ''}</textarea>
        </div>
        <div>
            <label class="form-label">Came From Other Religion</label>
            <textarea name="came_from_other_religion" class="form-input">${student.came_from_other_religion || ''}</textarea>
        </div>
    </div>`;
}

function createProfileTab(student) {
    return `
        <div class="space-y-8">
            <section>
                <h3 class="text-base font-semibold mb-3">Profile & Basic</h3>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div>
                        ${createPhotoSection(student)}
                    </div>
                    <div class="lg:col-span-2">
                        ${createBasicInfoSection(student)}
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-base font-semibold mb-3">School</h3>
                ${createSchoolSection(student)}
            </section>
            <section>
                <h3 class="text-base font-semibold mb-3">Address</h3>
                ${createAddressSection(student)}
            </section>
            <section>
                <h3 class="text-base font-semibold mb-3">Spiritual</h3>
                ${createSpiritualSection(student)}
            </section>
            <section>
                <h3 class="text-base font-semibold mb-3">Health & Misc</h3>
                ${createHealthSection(student)}
            </section>
        </div>
    `;
}

function createContactsTab(student) {
    return `
        <div class="space-y-8">
            <section class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
                <h3 class="text-base font-semibold mb-3">Emergency</h3>
                ${createEmergencySection(student)}
            </section>
            <section>
                <h3 class="text-base font-semibold mb-3">Family</h3>
                ${createFamilyTab(student)}
            </section>
        </div>
    `;
}

// Create Family Tab
function createFamilyTab(student) {
    return `<div class="space-y-8">
        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <h3 class="text-lg font-medium mb-4 text-blue-800 dark:text-blue-200">
                <i class="fas fa-male mr-2"></i>Father Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Full Name</label>
                    <input type="text" name="father_full_name" value="${student.father_full_name || ''}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input type="tel" name="father_phone" value="${student.father_phone || ''}" class="form-input">
                </div>
            </div>
        </div>
        <div class="bg-pink-50 dark:bg-pink-900/20 p-4 rounded-lg">
            <h3 class="text-lg font-medium mb-4 text-pink-800 dark:text-pink-200">
                <i class="fas fa-female mr-2"></i>Mother Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Full Name</label>
                    <input type="text" name="mother_full_name" value="${student.mother_full_name || ''}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input type="tel" name="mother_phone" value="${student.mother_phone || ''}" class="form-input">
                </div>
            </div>
        </div>
    </div>`;
}

// Initialize drawer features
function initializeAdvancedDrawerFeatures() {
    initializeFormWatchers();
    
    // Initialize Ethiopian calendar dropdowns for advanced edit mode
    initializeAdvancedEthiopianCalendar();
    
    // Load grade options from backend and populate current_grade
    try {
        const form = document.getElementById('advancedStudentEditForm');
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
                .catch(() => { /* keep placeholder on error */ });
        }
    } catch (e) { /* ignore */ }
    
    // Photo preview handlers
    window.__onPhotoChange = function(e){
        const input = e.target;
        const file = input && input.files && input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev){
            const img = document.getElementById('adv-photo-preview');
            const ph = document.getElementById('adv-photo-preview-ph');
            if (img) {
                img.src = ev.target.result;
            } else if (ph) {
                const parent = ph.parentElement;
                ph.remove();
                const newImg = document.createElement('img');
                newImg.id = 'adv-photo-preview';
                newImg.className = 'w-28 h-28 rounded-full object-cover ring-2 ring-blue-200 shadow';
                newImg.src = ev.target.result;
                parent.prepend(newImg);
            }
        };
        reader.readAsDataURL(file);
    };
    window.__clearPhoto = function(){
        const input = document.querySelector('#editDrawer input[name="student_photo"]');
        if (input) input.value = '';
        const img = document.getElementById('adv-photo-preview');
        if (img) img.src = '';
    };
    // Camera helpers
    let __drawerCameraStream = null;
    let __drawerFacingMode = 'user';
    window.openDrawerCameraModal = function(){
        const modal = document.getElementById('drawer-camera-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        startDrawerCamera(__drawerFacingMode);
    };
    window.closeDrawerCameraModal = function(){
        const modal = document.getElementById('drawer-camera-modal');
        if (modal) modal.classList.add('hidden');
        stopDrawerCamera();
    };
    function startDrawerCamera(facing){
        const video = document.getElementById('drawer-camera-video');
        if (!video) return;
        stopDrawerCamera();
        navigator.mediaDevices.getUserMedia({ video: { facingMode: facing } })
            .then(stream => {
                __drawerCameraStream = stream;
                video.srcObject = stream;
            })
            .catch(() => { alert('Camera not available.'); closeDrawerCameraModal(); });
    }
    function stopDrawerCamera(){
        const video = document.getElementById('drawer-camera-video');
        if (video) video.srcObject = null;
        if (__drawerCameraStream) {
            __drawerCameraStream.getTracks().forEach(t => t.stop());
            __drawerCameraStream = null;
        }
    }
    window.switchDrawerCamera = function(){
        __drawerFacingMode = __drawerFacingMode === 'user' ? 'environment' : 'user';
        startDrawerCamera(__drawerFacingMode);
    };
    window.captureDrawerPhoto = function(){
        const video = document.getElementById('drawer-camera-video');
        const canvas = document.getElementById('drawer-camera-canvas');
        if (!video || !canvas) return;
        canvas.width = video.videoWidth || 320;
        canvas.height = video.videoHeight || 240;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(blob => {
            const file = new File([blob], 'captured_photo.jpg', { type: 'image/jpeg' });
            const dt = new DataTransfer();
            dt.items.add(file);
            const input = document.querySelector('#editDrawer input[name="student_photo"]');
            if (input) input.files = dt.files;
            // Preview
            const reader = new FileReader();
            reader.onload = function(ev){
                const img = document.getElementById('adv-photo-preview');
                const ph = document.getElementById('adv-photo-preview-ph');
                if (img) { img.src = ev.target.result; }
                else if (ph) {
                    const parent = ph.parentElement;
                    ph.remove();
                    const newImg = document.createElement('img');
                    newImg.id = 'adv-photo-preview';
                    newImg.className = 'w-28 h-28 rounded-full object-cover ring-2 ring-blue-200 shadow';
                    newImg.src = ev.target.result;
                    parent.prepend(newImg);
                }
            };
            reader.readAsDataURL(file);
            closeDrawerCameraModal();
        }, 'image/jpeg');
    };
}

// Initialize form watchers for changes
function initializeFormWatchers() {
    window.hasUnsavedChanges = false;
    
    const form = document.getElementById('advancedStudentEditForm');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            window.hasUnsavedChanges = true;
        });
    });
}

// Switch between tabs
function switchAdvancedTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    const selectedTab = document.getElementById(tabName + 'Tab');
    if (selectedTab) {
        selectedTab.classList.remove('hidden');
    }
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('tab-btn-active', 'border-blue-500', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    const activeBtn = document.querySelector(`[onclick*="switchAdvancedTab('${tabName}')"]`);
    if (activeBtn) {
        activeBtn.classList.add('tab-btn-active', 'border-blue-500', 'text-blue-600');
        activeBtn.classList.remove('border-transparent', 'text-gray-500');
    }
}

// Close advanced edit drawer
function closeAdvancedEditDrawer() {
    if (window.hasUnsavedChanges) {
        if (!confirm('You have unsaved changes. Are you sure you want to close without saving?')) {
            return;
        }
    }
    
    const drawer = document.getElementById('editDrawer');
    if (drawer) {
        drawer.classList.add('translate-x-full');
        setTimeout(() => {
            drawer.remove();
            document.body.style.overflow = 'auto';
            window.hasUnsavedChanges = false;
        }, 300);
    }
}

// Save advanced student changes
function saveAdvancedStudentChanges(silent = false) {
    const form = document.getElementById('advancedStudentEditForm');
    if (!form) {
        if (!silent) showToast('Form not found', 'error');
        return;
    }
    
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
            
            Object.assign(window.currentEditingStudent, data.student);
            
            if (!silent) {
                showToast('Student information updated successfully!', 'success');
            }
        } else {
            if (!silent) {
                showToast(data.message || 'Failed to update student information', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (!silent) {
            showToast('An error occurred while saving', 'error');
        }
    });
}

// Reset advanced form
function resetAdvancedForm() {
    if (!window.originalStudentData) {
        showToast('No original data available', 'warning');
        return;
    }
    
    if (window.hasUnsavedChanges) {
        if (!confirm('This will discard all unsaved changes. Are you sure?')) {
            return;
        }
    }
    
    const form = document.getElementById('advancedStudentEditForm');
    if (!form) return;
    
    Object.keys(window.originalStudentData).forEach(key => {
        const field = form.querySelector(`[name="${key}"]`);
        if (field) {
            field.value = window.originalStudentData[key] || '';
        }
    });
    
    window.hasUnsavedChanges = false;
    showToast('Form reset to original values', 'info');
}

// Make functions globally available
window.switchAdvancedTab = switchAdvancedTab;
window.closeAdvancedEditDrawer = closeAdvancedEditDrawer;
window.saveAdvancedStudentChanges = saveAdvancedStudentChanges;
window.resetAdvancedForm = resetAdvancedForm;

// Ethiopian Calendar Functions for Advanced Edit Mode
function initializeAdvancedEthiopianCalendar() {
    const ySel = document.getElementById('birth_year_et');
    const mSel = document.getElementById('birth_month_et');
    const dSel = document.getElementById('birth_day_et');
    const hiddenField = document.getElementById('birth_date_hidden');
    
    if (!ySel || !mSel || !dSel) {
        console.log('Ethiopian calendar elements not found in advanced drawer, skipping initialization');
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
    function setAdvancedExistingBirthDate(student) {
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
    window.setAdvancedEthiopianBirthDate = setAdvancedExistingBirthDate;
    
    console.log('Ethiopian calendar for advanced edit mode initialized');
}