/**
 * Instrument Edit Drawer JavaScript Functions
 * Specialized for instrument registration editing with professional UI
 * Follows the same design patterns as enhanced-edit-drawer.js
 */

// Global drawer variables for instrument editing
let currentEditingInstrument = null;
let originalInstrumentData = null;
let hasUnsavedInstrumentChanges = false;
let currentInstrumentTab = 'basic';

// Enhanced Instrument Edit Drawer Implementation
window.openInstrumentEditDrawer = function(instrumentData, table = 'instruments') {
    // Remove existing drawer if present
    const existingDrawer = document.getElementById("instrumentEditDrawer");
    if (existingDrawer) {
        existingDrawer.remove();
    }
    
    // Store current editing data
    window.currentEditingInstrument = { ...instrumentData };
    window.originalInstrumentData = { ...instrumentData };
    
    // Create and initialize drawer
    createInstrumentEditDrawer(instrumentData);
    
    // Show drawer with animation
    requestAnimationFrame(() => {
        const drawer = document.getElementById("instrumentEditDrawer");
        if (drawer) {
            drawer.classList.remove("translate-x-full");
            drawer.querySelector(".drawer-panel").classList.remove("translate-x-full");
        }
    });
    
    // Prevent body scroll
    document.body.style.overflow = "hidden";
    
    // Initialize enhanced features
    initializeInstrumentDrawerFeatures();
    
    // Add keyboard shortcuts
    initializeInstrumentKeyboardShortcuts();
    
    showToast("Instrument edit drawer opened - Ready for editing!", "success");
};

// Create instrument edit drawer with modern tabbed interface
function createInstrumentEditDrawer(instrumentData) {
    const drawerHTML = `
        <div id="instrumentEditDrawer" class="fixed inset-0 z-50 flex justify-end bg-black bg-opacity-60 backdrop-blur-sm translate-x-full transition-all duration-300 ease-in-out">
            <div class="drawer-panel w-full max-w-3xl bg-white dark:bg-gray-900 shadow-2xl transform translate-x-full transition-all duration-300 ease-in-out overflow-hidden flex flex-col h-screen">
                <!-- Header with gradient and enhanced styling -->
                <div class="bg-gradient-to-r from-purple-600 via-blue-600 to-indigo-500 text-white p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-14 h-14 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <i class="fas fa-music text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold">Instrument Registration Editor</h2>
                                <p class="text-purple-100 text-sm flex items-center">
                                    <span class="truncate max-w-xs">${instrumentData.full_name || "Registration Details"}</span>
                                    <span class="ml-2 px-2 py-0.5 bg-white bg-opacity-20 rounded-full text-xs">
                                        ${instrumentData.instrument ? getMultipleInstrumentDisplayName(instrumentData.instrument) : "N/A"}
                                    </span>
                                    <span class="ml-2 px-2 py-0.5 bg-white bg-opacity-20 rounded-full text-xs">
                                        ID: ${instrumentData.id || "N/A"}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div id="instrumentSaveStatusIndicator" class="flex items-center space-x-2 text-sm bg-white bg-opacity-10 px-3 py-1 rounded-full">
                                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                <span>Ready</span>
                            </div>
                            <button onclick="closeInstrumentEditDrawer()" class="p-2 hover:bg-white hover:bg-opacity-20 rounded-full transition-all duration-200">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced Tab Navigation -->
                <div class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6">
                    <div class="flex items-center justify-between py-3">
                        <nav class="flex space-x-1 overflow-x-auto">
                            <button onclick="switchInstrumentTab('basic')" class="instrument-tab-btn instrument-tab-active py-3 px-4 text-sm font-medium rounded-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-user-circle mr-2"></i>Basic Info
                            </button>
                            <button onclick="switchInstrumentTab('instrument')" class="instrument-tab-btn py-3 px-4 text-sm font-medium rounded-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-music mr-2"></i>Instrument Details
                            </button>
                            <button onclick="switchInstrumentTab('contact')" class="instrument-tab-btn py-3 px-4 text-sm font-medium rounded-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-address-book mr-2"></i>Contact & Address
                            </button>
                            <button onclick="switchInstrumentTab('spiritual')" class="instrument-tab-btn py-3 px-4 text-sm font-medium rounded-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-cross mr-2"></i>Spiritual Info
                            </button>
                        </nav>
                        <div class="hidden md:flex items-center space-x-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Progress:</span>
                            <div class="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div id="instrumentCompletionProgress" class="h-full bg-gradient-to-r from-purple-400 to-blue-500 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Content Area with Scrollable Sections -->
                <div class="flex-1 overflow-y-auto p-6">
                    <form id="instrumentEditForm" class="space-y-8">
                        <!-- Basic Info Tab -->
                        <div id="basicTab" class="instrument-tab-content">
                            ${createInstrumentBasicTab(instrumentData)}
                        </div>
                        
                        <!-- Instrument Details Tab -->
                        <div id="instrumentTab" class="instrument-tab-content hidden">
                            ${createInstrumentDetailsTab(instrumentData)}
                        </div>
                        
                        <!-- Contact & Address Tab -->
                        <div id="contactTab" class="instrument-tab-content hidden">
                            ${createInstrumentContactTab(instrumentData)}
                        </div>
                        
                        <!-- Spiritual Info Tab -->
                        <div id="spiritualTab" class="instrument-tab-content hidden">
                            ${createInstrumentSpiritualTab(instrumentData)}
                        </div>
                    </form>
                </div>
                
                <!-- Enhanced Footer Actions -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <button onclick="resetInstrumentForm()" class="px-4 py-2 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg font-medium transition-all duration-200 flex items-center">
                                <i class="fas fa-undo mr-2"></i>Reset
                            </button>
                            <button onclick="previewInstrumentChanges()" class="px-4 py-2 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg font-medium transition-all duration-200 flex items-center">
                                <i class="fas fa-eye mr-2"></i>Preview
                            </button>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button onclick="saveInstrumentChanges()" class="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white px-6 py-2.5 rounded-lg font-medium transition-all duration-200 shadow-lg hover:shadow-xl flex items-center">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced Camera Modal -->
                <div id="instrument-camera-modal" class="hidden absolute inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-96 shadow-2xl relative">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Capture Photo</h3>
                            <button type="button" onclick="closeInstrumentCameraModal()" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <video id="instrument-camera-video" autoplay playsinline class="w-full h-64 bg-gray-200 dark:bg-gray-700 rounded-lg"></video>
                        <canvas id="instrument-camera-canvas" class="hidden"></canvas>
                        <div class="flex justify-between mt-4">
                            <button type="button" onclick="switchInstrumentCamera()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-sync-alt mr-2"></i>Switch Camera
                            </button>
                            <button type="button" onclick="captureInstrumentPhoto()" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-camera mr-2"></i>Capture
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Changes Preview Modal -->
                <div id="instrument-changes-preview-modal" class="hidden absolute inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-2xl shadow-2xl relative max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Changes Preview</h3>
                            <button type="button" onclick="closeInstrumentChangesPreview()" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="instrument-changes-preview-content" class="space-y-4">
                            <!-- Changes will be populated here -->
                        </div>
                        <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" onclick="closeInstrumentChangesPreview()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg transition-all duration-200 mr-2">
                                Close
                            </button>
                            <button type="button" onclick="saveInstrumentChanges()" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-lg transition-all duration-200">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', drawerHTML);
    
    // Initialize change detection
    initializeInstrumentChangeDetection();
}

// Create Basic Info Tab
function createInstrumentBasicTab(instrument) {
    return `
        <div class="space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div>
                    ${createInstrumentPhotoSection(instrument)}
                </div>
                <div class="lg:col-span-2">
                    ${createInstrumentBasicInfoSection(instrument)}
                </div>
            </div>
        </div>
    `;
}

// Create Photo Section
function createInstrumentPhotoSection(instrument) {
    const photoPath = instrument.person_photo_path || instrument.photo_path || '';
    return `
        <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <i class="fas fa-camera text-purple-600 mr-2"></i>
                Student Photo
            </h3>
            <div class="flex flex-col items-center space-y-4">
                <div class="w-32 h-32 rounded-2xl overflow-hidden bg-gray-100 dark:bg-gray-700 border-4 border-white dark:border-gray-600 shadow-lg">
                    ${photoPath ? 
                        `<img src="${photoPath}" alt="Student photo" class="w-full h-full object-cover" id="instrumentPhotoPreview">` :
                        `<div class="w-full h-full flex items-center justify-center text-gray-400 dark:text-gray-500" id="instrumentPhotoPreview">
                            <i class="fas fa-user text-4xl"></i>
                        </div>`
                    }
                </div>
                <input type="file" name="person_photo" id="instrumentPhotoInput" accept="image/*" class="hidden">
                <div class="flex space-x-2 w-full">
                    <button type="button" class="flex-1 px-3 py-2 text-sm bg-purple-100 hover:bg-purple-200 dark:bg-purple-900/30 dark:hover:bg-purple-900/50 text-purple-700 dark:text-purple-300 rounded-lg transition-all duration-200 flex items-center justify-center" onclick="document.getElementById('instrumentPhotoInput').click()">
                        <i class="fas fa-upload mr-1"></i>Upload
                    </button>
                    <button type="button" class="flex-1 px-3 py-2 text-sm bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded-lg transition-all duration-200 flex items-center justify-center" onclick="openInstrumentCameraModal()">
                        <i class="fas fa-camera mr-1"></i>Camera
                    </button>
                    <button type="button" class="flex-1 px-3 py-2 text-sm bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg transition-all duration-200 flex items-center justify-center" onclick="clearInstrumentPhoto()">
                        <i class="fas fa-times mr-1"></i>Clear
                    </button>
                </div>
                <label class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" name="remove_photo" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 mr-2">
                    Remove current photo
                </label>
            </div>
        </div>
    `;
}

// Create Basic Info Section  
function createInstrumentBasicInfoSection(instrument) {
    return `
        <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm h-full">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <i class="fas fa-user text-purple-600 mr-2"></i>
                Personal Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fas fa-user text-xs mr-1"></i>Full Name
                    </label>
                    <input type="text" name="full_name" value="${instrument.full_name || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fas fa-cross text-xs mr-1"></i>Christian Name
                    </label>
                    <input type="text" name="christian_name" value="${instrument.christian_name || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fas fa-venus-mars text-xs mr-1"></i>Gender
                    </label>
                    <select name="gender" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Select Gender</option>
                        <option value="male" ${instrument.gender === 'male' ? 'selected' : ''}>Male</option>
                        <option value="female" ${instrument.gender === 'female' ? 'selected' : ''}>Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fas fa-phone text-xs mr-1"></i>Phone Number
                    </label>
                    <input type="tel" name="phone_number" value="${instrument.phone_number || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div class="md:col-span-2">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                        <i class="fas fa-calendar-alt text-purple-600 mr-2"></i>
                        Ethiopian Birth Date
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Day</label>
                            <input type="number" name="birth_day_et" value="${instrument.birth_day_et || ''}" min="1" max="30" 
                                   class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Month</label>
                            <select name="birth_month_et" class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white text-sm">
                                <option value="">Select</option>
                                <option value="1" ${instrument.birth_month_et == '1' ? 'selected' : ''}>መስከረም</option>
                                <option value="2" ${instrument.birth_month_et == '2' ? 'selected' : ''}>ጥቅምት</option>
                                <option value="3" ${instrument.birth_month_et == '3' ? 'selected' : ''}>ህዳር</option>
                                <option value="4" ${instrument.birth_month_et == '4' ? 'selected' : ''}>ታህሳስ</option>
                                <option value="5" ${instrument.birth_month_et == '5' ? 'selected' : ''}>ጥር</option>
                                <option value="6" ${instrument.birth_month_et == '6' ? 'selected' : ''}>የካቲት</option>
                                <option value="7" ${instrument.birth_month_et == '7' ? 'selected' : ''}>መጋቢት</option>
                                <option value="8" ${instrument.birth_month_et == '8' ? 'selected' : ''}>ሚያዝያ</option>
                                <option value="9" ${instrument.birth_month_et == '9' ? 'selected' : ''}>ግንቦት</option>
                                <option value="10" ${instrument.birth_month_et == '10' ? 'selected' : ''}>ሰኔ</option>
                                <option value="11" ${instrument.birth_month_et == '11' ? 'selected' : ''}>ሐምሌ</option>
                                <option value="12" ${instrument.birth_month_et == '12' ? 'selected' : ''}>ነሐሴ</option>
                                <option value="13" ${instrument.birth_month_et == '13' ? 'selected' : ''}>ጳጉሜን</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Year</label>
                            <input type="number" name="birth_year_et" value="${instrument.birth_year_et || ''}" min="1900" max="2020" 
                                   class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white text-sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Helper function to get instrument display names
function getInstrumentDisplayName(instrument) {
    const instruments = {
        'begena': 'በገና (Begena)',
        'masenqo': 'መሰንቆ (Masenqo)', 
        'kebero': 'ከበሮ (Kebero)',
        'krar': 'ክራር (Krar)'
    };
    return instruments[instrument] || instrument;
}

// Helper function to get multiple instrument display names
function getMultipleInstrumentDisplayName(instrument) {
    if (!instrument) return 'N/A';
    
    const instruments = {
        'begena': 'በገና',
        'masenqo': 'መሰንቆ', 
        'kebero': 'ከበሮ',
        'krar': 'ክራር'
    };
    
    // Handle comma-separated multiple instruments
    if (typeof instrument === 'string' && instrument.includes(',')) {
        const instrumentList = instrument.split(',').map(i => i.trim());
        const displayNames = instrumentList.map(inst => instruments[inst] || inst);
        
        if (displayNames.length > 3) {
            return displayNames.slice(0, 2).join(', ') + ` +${displayNames.length - 2} more`;
        } else {
            return displayNames.join(', ');
        }
    }
    
    // Handle single instrument
    return instruments[instrument] || instrument;
}

// Additional tab creation functions would go here...
// (I'll add them in the next part to stay within limits)

// Initialize change detection
function initializeInstrumentChangeDetection() {
    const form = document.getElementById('instrumentEditForm');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            window.hasUnsavedInstrumentChanges = true;
            updateInstrumentSaveStatus();
        });
    });
    
    // Photo upload handler
    const photoInput = document.getElementById('instrumentPhotoInput');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('instrumentPhotoPreview');
                    if (preview) {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Photo preview" class="w-full h-full object-cover">`;
                    }
                };
                reader.readAsDataURL(e.target.files[0]);
                window.hasUnsavedInstrumentChanges = true;
                updateInstrumentSaveStatus();
            }
        });
    }
}

// Update save status indicator
function updateInstrumentSaveStatus() {
    const indicator = document.getElementById('instrumentSaveStatusIndicator');
    if (indicator) {
        if (window.hasUnsavedInstrumentChanges) {
            indicator.innerHTML = '<div class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></div><span>Unsaved</span>';
        } else {
            indicator.innerHTML = '<div class="w-2 h-2 bg-green-400 rounded-full"></div><span>Saved</span>';
        }
    }
}

// Switch between tabs
function switchInstrumentTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.instrument-tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabName + 'Tab');
    if (selectedTab) {
        selectedTab.classList.remove('hidden');
    }
    
    // Update tab button styles
    document.querySelectorAll('.instrument-tab-btn').forEach(btn => {
        btn.classList.remove('instrument-tab-active', 'bg-purple-100', 'dark:bg-purple-900', 'text-purple-700', 'dark:text-purple-200');
        btn.classList.add('text-gray-600', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
    });
    
    // Highlight active tab button
    const activeBtn = document.querySelector(`[onclick*="switchInstrumentTab('${tabName}')"]`);
    if (activeBtn) {
        activeBtn.classList.remove('text-gray-600', 'dark:text-gray-400', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
        activeBtn.classList.add('instrument-tab-active', 'bg-purple-100', 'dark:bg-purple-900', 'text-purple-700', 'dark:text-purple-200');
    }
    
    window.currentInstrumentTab = tabName;
}

// Close instrument edit drawer
function closeInstrumentEditDrawer() {
    if (window.hasUnsavedInstrumentChanges) {
        if (!confirm('You have unsaved changes. Are you sure you want to close without saving?')) {
            return;
        }
    }
    
    const drawer = document.getElementById('instrumentEditDrawer');
    if (drawer) {
        drawer.classList.add('translate-x-full');
        setTimeout(() => {
            drawer.remove();
            document.body.style.overflow = 'auto';
            window.hasUnsavedInstrumentChanges = false;
        }, 300);
    }
}

// Show toast notifications
function showToast(message, type = 'info') {
    // Simple toast implementation - can be enhanced
    console.log(`[${type.toUpperCase()}] ${message}`);
    
    // Create a simple toast element
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg text-white text-sm ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
    }`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Additional functions for camera, saving, etc. will be added in the next part...

// Create Instrument Details Tab
function createInstrumentDetailsTab(instrument) {
    // Parse existing instruments if they exist (could be comma-separated or array)
    let selectedInstruments = [];
    if (instrument.instrument) {
        if (typeof instrument.instrument === 'string') {
            selectedInstruments = instrument.instrument.split(',').map(i => i.trim());
        } else if (Array.isArray(instrument.instrument)) {
            selectedInstruments = instrument.instrument;
        } else {
            selectedInstruments = [instrument.instrument];
        }
    }
    
    return `
        <div class="space-y-6">
            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6 flex items-center">
                    <i class="fas fa-music text-purple-600 mr-2"></i>
                    Instrument Registration Details
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            <i class="fas fa-guitar text-xs mr-1"></i>Select Instruments (Multiple Selection Allowed)
                        </label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">You can select multiple instruments that the student is learning.</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="instrument-option ${selectedInstruments.includes('begena') ? 'selected' : ''}" data-instrument="begena">
                                <input type="checkbox" name="instruments[]" value="begena" id="begena" ${selectedInstruments.includes('begena') ? 'checked' : ''} class="hidden">
                                <label for="begena" class="flex flex-col items-center p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:border-purple-500 transition-all duration-200">
                                    <i class="fas fa-guitar text-2xl text-blue-600 mb-2"></i>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">በገና</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Begena</span>
                                </label>
                            </div>
                            <div class="instrument-option ${selectedInstruments.includes('masenqo') ? 'selected' : ''}" data-instrument="masenqo">
                                <input type="checkbox" name="instruments[]" value="masenqo" id="masenqo" ${selectedInstruments.includes('masenqo') ? 'checked' : ''} class="hidden">
                                <label for="masenqo" class="flex flex-col items-center p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:border-purple-500 transition-all duration-200">
                                    <i class="fas fa-violin text-2xl text-green-600 mb-2"></i>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">መሰንቆ</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Masenqo</span>
                                </label>
                            </div>
                            <div class="instrument-option ${selectedInstruments.includes('kebero') ? 'selected' : ''}" data-instrument="kebero">
                                <input type="checkbox" name="instruments[]" value="kebero" id="kebero" ${selectedInstruments.includes('kebero') ? 'checked' : ''} class="hidden">
                                <label for="kebero" class="flex flex-col items-center p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:border-purple-500 transition-all duration-200">
                                    <i class="fas fa-drum text-2xl text-orange-600 mb-2"></i>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">ከበሮ</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Kebero</span>
                                </label>
                            </div>
                            <div class="instrument-option ${selectedInstruments.includes('krar') ? 'selected' : ''}" data-instrument="krar">
                                <input type="checkbox" name="instruments[]" value="krar" id="krar" ${selectedInstruments.includes('krar') ? 'checked' : ''} class="hidden">
                                <label for="krar" class="flex flex-col items-center p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:border-purple-500 transition-all duration-200">
                                    <i class="fas fa-guitar text-2xl text-purple-600 mb-2"></i>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">ክራር</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Krar</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Selected Instruments Display -->
                        <div id="selectedInstrumentsDisplay" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-check-circle text-xs mr-1"></i>Currently Selected
                            </label>
                            <div id="selectedInstrumentsList" class="flex flex-wrap gap-2 min-h-[2rem] p-2 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600">
                                <!-- Selected instruments will be dynamically populated here -->
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-calendar text-xs mr-1"></i>Registration Date
                        </label>
                        <input type="text" value="${instrument.created_at ? new Date(instrument.created_at).toLocaleDateString() : 'N/A'}" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300" readonly>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-flag text-xs mr-1"></i>Status
                            </label>
                            <div class="flex items-center space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="flagged" value="1" ${instrument.flagged ? 'checked' : ''} 
                                           class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Flag this registration</span>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Flagged registrations require admin attention</p>
                        </div>
                        
                        <!-- Quick Actions for Multiple Instruments -->
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-magic text-xs mr-1"></i>Quick Actions
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="selectAllInstruments()" class="px-3 py-1 text-xs bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded-lg transition-all duration-200">
                                    <i class="fas fa-check-double mr-1"></i>Select All
                                </button>
                                <button type="button" onclick="clearAllInstruments()" class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-all duration-200">
                                    <i class="fas fa-times mr-1"></i>Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Create Contact & Address Tab
function createInstrumentContactTab(instrument) {
    return `
        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Address Information -->
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-map-marker-alt text-purple-600 mr-2"></i>
                        Address Information
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-city text-xs mr-1"></i>Sub City
                            </label>
                            <input type="text" name="sub_city" value="${instrument.sub_city || ''}" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-map text-xs mr-1"></i>District/Woreda
                            </label>
                            <input type="text" name="district" value="${instrument.district || ''}" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-location-arrow text-xs mr-1"></i>Specific Area/Kebele
                            </label>
                            <input type="text" name="specific_area" value="${instrument.specific_area || ''}" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-home text-xs mr-1"></i>House Number
                            </label>
                            <input type="text" name="house_number" value="${instrument.house_number || ''}" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                </div>
                
                <!-- Emergency Contact Information -->
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-phone-alt text-red-600 mr-2"></i>
                        Emergency Contact
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-user text-xs mr-1"></i>Contact Name
                            </label>
                            <input type="text" name="emergency_name" value="${instrument.emergency_name || ''}" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-phone text-xs mr-1"></i>Primary Phone
                            </label>
                            <input type="tel" name="emergency_phone" value="${instrument.emergency_phone || ''}" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-mobile-alt text-xs mr-1"></i>Alternative Phone
                            </label>
                            <input type="tel" name="emergency_alt_phone" value="${instrument.emergency_alt_phone || ''}" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-map-marker-alt text-xs mr-1"></i>Emergency Address
                            </label>
                            <textarea name="emergency_address" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white resize-none">${instrument.emergency_address || ''}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Create Spiritual Info Tab
function createInstrumentSpiritualTab(instrument) {
    return `
        <div class="space-y-6">
            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6 flex items-center">
                    <i class="fas fa-cross text-purple-600 mr-2"></i>
                    Spiritual Father Information
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            <i class="fas fa-question-circle text-xs mr-1"></i>Does the student have a spiritual father?
                        </label>
                        <div class="flex items-center space-x-6">
                            <label class="inline-flex items-center">
                                <input type="radio" name="has_spiritual_father" value="yes" ${instrument.has_spiritual_father === 'yes' ? 'checked' : ''} 
                                       class="text-purple-600 focus:ring-purple-500">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="has_spiritual_father" value="no" ${instrument.has_spiritual_father === 'no' ? 'checked' : ''} 
                                       class="text-purple-600 focus:ring-purple-500">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                            </label>
                        </div>
                    </div>
                    
                    <div id="spiritualFatherDetails" class="${instrument.has_spiritual_father === 'yes' ? '' : 'hidden'} space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-user text-xs mr-1"></i>Spiritual Father Name
                                </label>
                                <input type="text" name="spiritual_father_name" value="${instrument.spiritual_father_name || ''}" 
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-phone text-xs mr-1"></i>Phone Number
                                </label>
                                <input type="tel" name="spiritual_father_phone" value="${instrument.spiritual_father_phone || ''}" 
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-church text-xs mr-1"></i>Church Name
                            </label>
                            <input type="text" name="spiritual_father_church" value="${instrument.spiritual_father_church || ''}" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Save instrument changes
function saveInstrumentChanges() {
    const form = document.getElementById('instrumentEditForm');
    if (!form) {
        showToast('Form not found', 'error');
        return;
    }
    
    // Update save status
    const indicator = document.getElementById('instrumentSaveStatusIndicator');
    if (indicator) {
        indicator.innerHTML = '<div class="w-2 h-2 bg-blue-400 rounded-full animate-spin"></div><span>Saving...</span>';
    }
    
    // Get selected instruments (multiple selection)
    const selectedInstruments = form.querySelectorAll('input[name="instruments[]"]:checked');
    const instrumentsArray = Array.from(selectedInstruments).map(cb => cb.value);
    const instrumentsString = instrumentsArray.join(',');
    
    // Prepare data for the API
    const requestData = {
        registration_id: window.currentEditingInstrument.id,
        instrument: instrumentsString
    };
    
    fetch('api/update_instrument.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(requestData),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.hasUnsavedInstrumentChanges = false;
            updateInstrumentSaveStatus();
            showToast('Instrument selection updated successfully!', 'success');
            
            // Update the original data
            if (window.currentEditingInstrument) {
                window.currentEditingInstrument.instrument = instrumentsString;
            }
            if (window.originalInstrumentData) {
                window.originalInstrumentData.instrument = instrumentsString;
            }
            
            // Optionally refresh the table or update the row
            setTimeout(() => {
                if (confirm('Instrument selection saved successfully! Would you like to reload the page to see updates?')) {
                    location.reload();
                }
            }, 1000);
        } else {
            showToast(data.message || 'Failed to save instrument selection', 'error');
            if (indicator) {
                indicator.innerHTML = '<div class="w-2 h-2 bg-red-400 rounded-full"></div><span>Error</span>';
            }
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        showToast('Network error occurred while saving', 'error');
        if (indicator) {
            indicator.innerHTML = '<div class="w-2 h-2 bg-red-400 rounded-full"></div><span>Error</span>';
        }
    });
}

// Reset form to original values
function resetInstrumentForm() {
    if (!window.originalInstrumentData) return;
    
    if (confirm('This will reset all changes to their original values. Continue?')) {
        // Reset all form fields to original values
        const form = document.getElementById('instrumentEditForm');
        if (form) {
            Object.keys(window.originalInstrumentData).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = !!window.originalInstrumentData[key];
                    } else if (field.type === 'radio') {
                        if (field.value === window.originalInstrumentData[key]) {
                            field.checked = true;
                        }
                    } else {
                        field.value = window.originalInstrumentData[key] || '';
                    }
                }
            });
        }
        
        window.hasUnsavedInstrumentChanges = false;
        updateInstrumentSaveStatus();
        showToast('Form reset to original values', 'info');
    }
}

// Preview changes modal
function previewInstrumentChanges() {
    const modal = document.getElementById('instrument-changes-preview-modal');
    const content = document.getElementById('instrument-changes-preview-content');
    
    if (!modal || !content) return;
    
    // Compare current form values with original data
    const form = document.getElementById('instrumentEditForm');
    const changes = [];
    
    if (form && window.originalInstrumentData) {
        const formData = new FormData(form);
        
        formData.forEach((value, key) => {
            const originalValue = window.originalInstrumentData[key] || '';
            if (value !== originalValue) {
                changes.push({
                    field: key,
                    original: originalValue,
                    new: value
                });
            }
        });
    }
    
    if (changes.length === 0) {
        content.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400 py-8">No changes detected</p>';
    } else {
        let changesHTML = `<div class="space-y-4">`;
        changes.forEach(change => {
            changesHTML += `
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900 dark:text-white">${formatFieldName(change.field)}</h4>
                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded text-xs">Modified</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Original Value</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 p-2 bg-gray-50 dark:bg-gray-800 rounded">${change.original || '<span class="text-gray-400">Empty</span>'}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">New Value</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300 p-2 bg-purple-50 dark:bg-purple-900/20 rounded">${change.new || '<span class="text-gray-400">Empty</span>'}</p>
                        </div>
                    </div>
                </div>
            `;
        });
        changesHTML += `</div>`;
        content.innerHTML = changesHTML;
    }
    
    modal.classList.remove('hidden');
}

// Close changes preview modal
function closeInstrumentChangesPreview() {
    const modal = document.getElementById('instrument-changes-preview-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Format field names for display
function formatFieldName(fieldName) {
    return fieldName
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase());
}

// Initialize keyboard shortcuts
function initializeInstrumentKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Only handle shortcuts when drawer is open
        if (!document.getElementById('instrumentEditDrawer')) return;
        
        // Ctrl+S - Save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            saveInstrumentChanges();
        }
        
        // Escape - Close
        if (e.key === 'Escape') {
            closeInstrumentEditDrawer();
        }
        
        // Ctrl+R - Reset
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            resetInstrumentForm();
        }
        
        // Ctrl+1-4 - Switch tabs
        if (e.ctrlKey && e.key >= '1' && e.key <= '4') {
            e.preventDefault();
            const tabs = ['basic', 'instrument', 'contact', 'spiritual'];
            const tabIndex = parseInt(e.key) - 1;
            if (tabs[tabIndex]) {
                switchInstrumentTab(tabs[tabIndex]);
            }
        }
    });
}

// Clear photo
function clearInstrumentPhoto() {
    const preview = document.getElementById('instrumentPhotoPreview');
    const input = document.getElementById('instrumentPhotoInput');
    
    if (preview) {
        preview.innerHTML = `<div class="w-full h-full flex items-center justify-center text-gray-400 dark:text-gray-500">
            <i class="fas fa-user text-4xl"></i>
        </div>`;
    }
    
    if (input) {
        input.value = '';
    }
    
    window.hasUnsavedInstrumentChanges = true;
    updateInstrumentSaveStatus();
}

// Initialize drawer features
function initializeInstrumentDrawerFeatures() {
    // Handle multiple instrument selection with checkboxes
    document.querySelectorAll('input[name="instruments[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const option = this.closest('.instrument-option');
            const label = option.querySelector('label');
            
            if (this.checked) {
                option.classList.add('selected');
                label.classList.add('border-purple-500', 'bg-purple-50', 'dark:bg-purple-900/20');
            } else {
                option.classList.remove('selected');
                label.classList.remove('border-purple-500', 'bg-purple-50', 'dark:bg-purple-900/20');
            }
            
            updateSelectedInstrumentsDisplay();
            window.hasUnsavedInstrumentChanges = true;
            updateInstrumentSaveStatus();
        });
    });
    
    // Initialize the selected instruments display
    updateSelectedInstrumentsDisplay();
    
    // Initialize spiritual father toggle
    document.querySelectorAll('input[name="has_spiritual_father"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const details = document.getElementById('spiritualFatherDetails');
            if (details) {
                if (this.value === 'yes') {
                    details.classList.remove('hidden');
                } else {
                    details.classList.add('hidden');
                }
            }
        });
    });
}

// Function to update selected instruments display
function updateSelectedInstrumentsDisplay() {
    const selectedCheckboxes = document.querySelectorAll('input[name="instruments[]"]:checked');
    const displayElement = document.getElementById('selectedInstrumentsList');
    
    if (!displayElement) return;
    
    if (selectedCheckboxes.length === 0) {
        displayElement.innerHTML = '<span class="text-gray-500 italic text-sm">No instruments selected</span>';
    } else {
        const selectedInstruments = Array.from(selectedCheckboxes).map(cb => {
            const label = document.querySelector(`label[for="${cb.id}"]`);
            const ethiopianName = label.querySelector('span:first-of-type').textContent;
            const englishName = label.querySelector('span:last-of-type').textContent;
            return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 mr-1 mb-1">
                <i class="fas fa-music mr-1"></i>${ethiopianName} (${englishName})
            </span>`;
        });
        displayElement.innerHTML = selectedInstruments.join('');
    }
}

// Function to select all instruments
window.selectAllInstruments = function() {
    document.querySelectorAll('input[name="instruments[]"]').forEach(checkbox => {
        checkbox.checked = true;
        const option = checkbox.closest('.instrument-option');
        const label = option.querySelector('label');
        option.classList.add('selected');
        label.classList.add('border-purple-500', 'bg-purple-50', 'dark:bg-purple-900/20');
    });
    updateSelectedInstrumentsDisplay();
    window.hasUnsavedInstrumentChanges = true;
    updateInstrumentSaveStatus();
};

// Function to clear all instrument selections
window.clearAllInstruments = function() {
    document.querySelectorAll('input[name="instruments[]"]').forEach(checkbox => {
        checkbox.checked = false;
        const option = checkbox.closest('.instrument-option');
        const label = option.querySelector('label');
        option.classList.remove('selected');
        label.classList.remove('border-purple-500', 'bg-purple-50', 'dark:bg-purple-900/20');
    });
    updateSelectedInstrumentsDisplay();
    window.hasUnsavedInstrumentChanges = true;
    updateInstrumentSaveStatus();
};

// Camera-related functions (simplified implementation)
function openInstrumentCameraModal() {
    showToast('Camera feature coming soon!', 'info');
    // Implementation would go here for camera functionality
}

function closeInstrumentCameraModal() {
    const modal = document.getElementById('instrument-camera-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function switchInstrumentCamera() {
    showToast('Camera switch feature coming soon!', 'info');
}

function captureInstrumentPhoto() {
    showToast('Photo capture feature coming soon!', 'info');
    // Future implementation for photo capture functionality
    // This would capture from the video stream and set it as the photo
}