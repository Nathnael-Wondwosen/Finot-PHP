/**
 * Column Customizer JavaScript Functions
 * Clean implementation without PHP/JavaScript mixing
 */

// Column customizer data - will be populated by PHP
let availableColumns = {};
let selectedFields = [];

// Initialize column customizer data from PHP
function initializeColumnData(columns, selected) {
    availableColumns = columns;
    selectedFields = selected;
}

function showColumnCustomizer() {
    document.getElementById("column-customizer-modal").classList.remove("hidden");
    populateColumnOptions();
    updateSelectedCount();
}

function closeColumnCustomizer() {
    document.getElementById("column-customizer-modal").classList.add("hidden");
}

// Add event listeners for real-time updates
function setupColumnEventListeners() {
    document.addEventListener("change", function(e) {
        if (e.target.classList.contains("column-checkbox")) {
            updateSelectedCount();
        }
    });
}

function updateSelectedCount() {
    const selected = document.querySelectorAll(".column-checkbox:checked").length;
    const total = document.querySelectorAll(".column-checkbox").length;
    const visible = document.querySelectorAll(".column-checkbox:not([style*=\"none\"])").length;
    
    // Update main counter
    const countElement = document.getElementById("selected-count");
    if (countElement) {
        countElement.textContent = selected;
    }
    
    // Update statistics
    const totalElement = document.getElementById("total-columns-count");
    const selectedElement = document.getElementById("selected-columns-count");
    const visibleElement = document.getElementById("visible-columns-count");
    
    if (totalElement) totalElement.textContent = total;
    if (selectedElement) selectedElement.textContent = selected;
    if (visibleElement) visibleElement.textContent = visible;
}

function filterColumns() {
    const searchTerm = document.getElementById("column-search").value.toLowerCase();
    const labels = document.querySelectorAll("#column-options label");
    
    labels.forEach(label => {
        const text = label.textContent.toLowerCase();
        const shouldShow = text.includes(searchTerm);
        label.style.display = shouldShow ? "flex" : "none";
    });
    
    // Show/hide category headers based on visible columns
    const categories = document.querySelectorAll("[data-category]");
    categories.forEach(category => {
        const visibleColumns = category.querySelectorAll("label[style*=\"flex\"], label:not([style*=\"none\"])").length;
        const categoryHeader = category.previousElementSibling;
        if (categoryHeader && categoryHeader.querySelector("h4")) {
            categoryHeader.style.display = visibleColumns > 0 ? "block" : "none";
        }
        category.style.display = visibleColumns > 0 ? "grid" : "none";
    });
    
    // Update statistics
    updateSelectedCount();
}

function saveCurrentAsPreset() {
    const selectedColumns = [];
    document.querySelectorAll(".column-checkbox:checked").forEach(checkbox => {
        selectedColumns.push(checkbox.value);
    });
    
    if (selectedColumns.length === 0) {
        showToast("Please select at least one column", "warning");
        return;
    }
    
    const presetName = prompt("Enter a name for this column preset:");
    if (!presetName) return;
    
    // Save preset to localStorage
    const presets = JSON.parse(localStorage.getItem("columnPresets") || "{}");
    presets[currentView + "_" + presetName] = {
        columns: selectedColumns,
        created: new Date().toISOString(),
        view: currentView
    };
    localStorage.setItem("columnPresets", JSON.stringify(presets));
    
    showToast("Preset \"" + presetName + "\" saved successfully", "success");
}

function populateColumnOptions() {
    const container = document.getElementById("column-options");
    
    // Get current selected columns from URL or defaults
    const urlParams = new URLSearchParams(window.location.search);
    const currentColumns = urlParams.get('columns') ? urlParams.get('columns').split(',') : selectedFields;
    
    // Group columns by category for better organization
    const columnCategories = {
        'basic': {
            label: 'Basic Information',
            columns: ['photo_path', 'full_name', 'christian_name', 'gender', 'birth_date', 'current_grade', 'phone_number', 'created_at']
        },
        'education': {
            label: 'Education Details',
            columns: ['school_year_start', 'regular_school_name', 'regular_school_grade', 'education_level', 'field_of_study']
        },
        'location': {
            label: 'Location & Address',
            columns: ['sub_city', 'district', 'specific_area', 'house_number', 'living_with']
        },
        'emergency': {
            label: 'Emergency Contacts',
            columns: ['emergency_name', 'emergency_phone', 'emergency_alt_phone', 'emergency_address']
        },
        'spiritual': {
            label: 'Spiritual Information',
            columns: ['has_spiritual_father', 'spiritual_father_name', 'spiritual_father_phone', 'spiritual_father_church']
        },
        'family': {
            label: 'Family Information',
            columns: ['father_full_name', 'father_phone', 'father_occupation', 'mother_full_name', 'mother_phone', 'mother_occupation', 'guardian_full_name', 'guardian_phone', 'guardian_occupation']
        },
        'additional': {
            label: 'Additional Information',
            columns: ['special_interests', 'siblings_in_school', 'physical_disability', 'weak_side', 'transferred_from_other_school', 'came_from_other_religion']
        }
    };
    
    let html = `
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-600">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Column Categories</span>
                <div class="flex space-x-2">
                    <button type="button" onclick="selectAllColumns()" class="px-3 py-1 text-xs bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded hover:bg-primary-200 dark:hover:bg-primary-800 transition-colors">
                        Select All
                    </button>
                    <button type="button" onclick="deselectAllColumns()" class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        Deselect All
                    </button>
                </div>
            </div>
    `;
    
    Object.entries(columnCategories).forEach(([categoryKey, category]) => {
        const categoryColumns = category.columns.filter(col => availableColumns.hasOwnProperty(col));
        if (categoryColumns.length === 0) return;
        
        html += `
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">${category.label}</h4>
                    <button type="button" onclick="toggleCategoryColumns('${categoryKey}')" class="text-xs text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-200">
                        Toggle All
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-2" data-category="${categoryKey}">
        `;
        
        categoryColumns.forEach(columnKey => {
            const checked = currentColumns.includes(columnKey) ? 'checked' : '';
            const label = availableColumns[columnKey] || columnKey;
            html += `
                <label class="flex items-center space-x-3 p-2 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors text-sm">
                    <input type="checkbox" value="${columnKey}" ${checked} class="column-checkbox rounded border-gray-300 text-primary-600 focus:ring-primary-500 text-sm">
                    <span class="text-gray-700 dark:text-gray-300">${label}</span>
                </label>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    });
    
    // Add any remaining columns that don't fit into categories
    const categorizedColumns = Object.values(columnCategories).flatMap(cat => cat.columns);
    const remainingColumns = Object.keys(availableColumns).filter(col => !categorizedColumns.includes(col));
    
    if (remainingColumns.length > 0) {
        html += `
            <div class="space-y-2">
                <h4 class="text-xs font-semibold text-gray-800 dark:text-gray-200">Other Fields</h4>
                <div class="grid grid-cols-1 gap-1">
        `;
        
        remainingColumns.forEach(columnKey => {
            const checked = currentColumns.includes(columnKey) ? 'checked' : '';
            const label = availableColumns[columnKey] || columnKey;
            html += `
                <label class="flex items-center space-x-3 p-2 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors text-sm">
                    <input type="checkbox" value="${columnKey}" ${checked} class="column-checkbox rounded border-gray-300 text-primary-600 focus:ring-primary-500 text-sm">
                    <span class="text-gray-700 dark:text-gray-300">${label}</span>
                </label>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    html += `
        </div>
    `;
    
    container.innerHTML = html;
    
    // Update statistics after populating
    setTimeout(() => {
        updateSelectedCount();
    }, 100);
}

function applyColumnSettings() {
    const selectedColumns = [];
    document.querySelectorAll('.column-checkbox:checked').forEach(checkbox => {
        selectedColumns.push(checkbox.value);
    });
    
    if (selectedColumns.length === 0) {
        showToast('Please select at least one column', 'warning');
        return;
    }
    
    // Save preferences to server
    const formData = new FormData();
    formData.append('view', currentView);
    formData.append('columns', selectedColumns.join(','));
    
    fetch('save_column_prefs.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect with new column selection
            const url = new URL(window.location);
            url.searchParams.set('columns', selectedColumns.join(','));
            window.location.href = url.toString();
        } else {
            showToast('Error saving column preferences', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error saving column preferences', 'error');
    });
}

// Additional column management functions
function selectAllColumns() {
    document.querySelectorAll('.column-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSelectedCount();
}

function deselectAllColumns() {
    document.querySelectorAll('.column-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelectedCount();
}

function toggleCategoryColumns(category) {
    const categoryContainer = document.querySelector(`[data-category="${category}"]`);
    if (!categoryContainer) return;
    
    const checkboxes = categoryContainer.querySelectorAll('.column-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });
    updateSelectedCount();
}

function resetToDefaults() {
    // Reset to default columns based on current view
    const defaultColumns = {
        'all': ['photo_path', 'full_name', 'gender', 'birth_date', 'current_grade', 'phone_number'],
        'youth': ['photo_path', 'full_name', 'gender', 'birth_date', 'current_grade', 'phone_number', 'field_of_study'],
        'under': ['photo_path', 'full_name', 'gender', 'birth_date', 'current_grade', 'sub_city', 'district', 'phone_number'],
        'instrument': ['photo_path', 'full_name', 'gender', 'instrument', 'phone_number', 'status']
    };
    
    const currentDefaults = defaultColumns[currentView] || defaultColumns['all'];
    
    document.querySelectorAll('.column-checkbox').forEach(checkbox => {
        checkbox.checked = currentDefaults.includes(checkbox.value);
    });
    updateSelectedCount();
}

function saveAsPreset() {
    const selectedColumns = [];
    document.querySelectorAll('.column-checkbox:checked').forEach(checkbox => {
        selectedColumns.push(checkbox.value);
    });
    
    if (selectedColumns.length === 0) {
        showToast('Please select at least one column', 'warning');
        return;
    }
    
    const presetName = prompt('Enter a name for this column preset:');
    if (!presetName) return;
    
    // Save preset to localStorage for now (can be enhanced to save to database)
    const presets = JSON.parse(localStorage.getItem('columnPresets') || '{}');
    presets[currentView + '_' + presetName] = selectedColumns;
    localStorage.setItem('columnPresets', JSON.stringify(presets));
    
    showToast(`Preset "${presetName}" saved successfully`, 'success');
}

function loadPreset() {
    const presets = JSON.parse(localStorage.getItem('columnPresets') || '{}');
    const currentViewPresets = Object.keys(presets).filter(key => key.startsWith(currentView + '_'));
    
    if (currentViewPresets.length === 0) {
        showToast('No saved presets found', 'info');
        return;
    }
    
    const presetNames = currentViewPresets.map(key => key.replace(currentView + '_', ''));
    const selectedPreset = prompt('Available presets:\n' + presetNames.join('\n') + '\n\nEnter preset name to load:');
    
    if (!selectedPreset) return;
    
    const presetKey = currentView + '_' + selectedPreset;
    const presetColumns = presets[presetKey];
    
    if (!presetColumns) {
        showToast('Preset not found', 'error');
        return;
    }
    
    document.querySelectorAll('.column-checkbox').forEach(checkbox => {
        checkbox.checked = presetColumns.includes(checkbox.value);
    });
    
    showToast(`Preset "${selectedPreset}" loaded successfully`, 'success');
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", function() {
    setupColumnEventListeners();
});