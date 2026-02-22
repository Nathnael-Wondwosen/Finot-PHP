/**
 * Students Page JavaScript Functions
 * Fixed and optimized version
 */

// Global variables
let currentView = 'all';
let currentTable = 'students';
let currentStudentId = null;
let hasUnsavedChanges = false;

// Initialize page functionality
document.addEventListener("DOMContentLoaded", function() {
    initializeAdvancedFeatures();
    setupEventListeners();
    
    // Get current view from URL or global PHP variable
    const urlParams = new URLSearchParams(window.location.search);
    currentView = urlParams.get('view') || 'all';
    currentTable = (currentView === 'instrument') ? 'instruments' : 'students';
});

function initializeAdvancedFeatures() {
    setupSearchAutocomplete();
    initializeTooltips();
    setupKeyboardShortcuts();
    initializeTableSorting();
}

function setupEventListeners() {
    const filterForm = document.getElementById("filter-form");
    if (filterForm) {
        filterForm.addEventListener("change", function(e) {
            if (e.target.type === "select-one" || e.target.type === "date") {
                setTimeout(() => filterForm.submit(), 300);
            }
        });
    }
}

function setupSearchAutocomplete() {
    const searchInput = document.getElementById("search");
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener("input", function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (e.target.value.length >= 2) {
                    console.log("Search suggestions for:", e.target.value);
                }
            }, 300);
        });
    }
}

function setupKeyboardShortcuts() {
    document.addEventListener("keydown", function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === "a" && !e.target.matches("input, textarea")) {
            e.preventDefault();
            selectAll();
        }
        if (e.key === "Escape") {
            selectNone();
            closeAllModals();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === "f") {
            e.preventDefault();
            document.getElementById("search")?.focus();
        }
    });
}

function initializeTableSorting() {
    document.querySelectorAll("[data-sort]").forEach(header => {
        header.addEventListener("click", function() {
            const sortBy = this.dataset.sort;
            const currentSort = new URLSearchParams(window.location.search).get("sort");
            const currentOrder = new URLSearchParams(window.location.search).get("order") || "asc";
            
            let newOrder = "asc";
            if (currentSort === sortBy && currentOrder === "asc") {
                newOrder = "desc";
            }
            
            const url = new URL(window.location);
            url.searchParams.set("sort", sortBy);
            url.searchParams.set("order", newOrder);
            window.location.href = url.toString();
        });
    });
}

// Global function declarations to ensure availability to included components
window.viewStudentDetails = function(studentId, table = "students") {
    // Store for edit functionality
    window.currentStudentId = studentId;
    window.currentTable = table;
    
    const modal = document.getElementById("student-details-modal");
    const content = document.getElementById("modal-content");
    
    if (!modal || !content) {
        console.error("Modal elements not found");
        return;
    }
    
    content.innerHTML = '<div class="flex items-center justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div></div>';
    modal.classList.remove("hidden");
    
    fetch(window.location.href, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: 'action=get_student_details&student_id=' + studentId + '&table=' + table
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.displayStudentDetails(data.student);
        } else {
            content.innerHTML = '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">' + data.message + '</p></div>';
        }
    })
    .catch(error => {
        console.error("Error:", error);
        content.innerHTML = '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-gray-600 dark:text-gray-400">Error loading student details</p></div>';
    });
};

window.editStudent = function(studentId, table = "students") {
    fetch(window.location.href, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: 'action=get_student_details&student_id=' + studentId + '&table=' + table
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.currentEditingStudent = data.student;
            window.currentEditingTable = table;
            window.openEditDrawer(data.student, table);
        } else {
            window.showToast(data.message || "Error loading student data", "error");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        window.showToast("Error loading student data", "error");
    });
};

window.deleteStudent = function(studentId, table = "students") {
    if (!confirm("Are you sure you want to delete this student? This action cannot be undone.")) {
        return;
    }
    
    fetch(window.location.href, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: 'action=delete_student&student_id=' + studentId + '&table=' + table
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.querySelector('[data-student-id="' + studentId + '"]')?.closest("tr");
            if (row) { row.remove(); }
            window.showToast(data.message, "success");
            
            // Reload page to refresh data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            window.showToast(data.message, "error");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        window.showToast("Error deleting student", "error");
    });
};

// Add supporting functions to global scope
window.showToast = function(message, type) {
    type = type || 'info';
    // Remove existing toasts
    document.querySelectorAll('.toast').forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = 'toast fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300';
    
    const colors = {
        success: 'bg-green-500 text-white',
        error: 'bg-red-500 text-white',
        warning: 'bg-yellow-500 text-white',
        info: 'bg-blue-500 text-white'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    toast.className += ' ' + (colors[type] || colors.info);
    toast.innerHTML = '<div class="flex items-center space-x-2"><i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + message + '</span></div>';
    
    document.body.appendChild(toast);
    
    setTimeout(function() {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    setTimeout(function() {
        toast.classList.add('translate-x-full');
        setTimeout(function() { toast.remove(); }, 300);
    }, 3000);
};

// Add displayStudentDetails to global scope
window.displayStudentDetails = function(student) {
    const content = document.getElementById("modal-content");
    if (!content) return;
    
    // Print header for printing
    let html = '<div class="hidden print:block mb-4">' +
        '<div class="text-center">' +
            '<h1 class="text-xl font-bold">Student Profile Report</h1>' +
            '<h2 class="text-lg">' + (student.full_name || "N/A") + '</h2>' +
            '<p class="text-sm">Generated: ' + new Date().toLocaleDateString() + '</p>' +
        '</div><hr class="my-4">' +
    '</div>';
    
    // Student profile content
    html += '<div class="space-y-6">';
    
    // Basic Info Header
    html += '<div class="flex items-start space-x-4 p-4 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-gray-800 dark:to-gray-700 rounded-lg">';
    
    // Photo
    if (student.photo_path) {
        html += '<img src="' + student.photo_path + '" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg">';
    } else {
        html += '<div class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center border-4 border-white shadow-lg">' +
            '<i class="fas fa-user text-white text-2xl"></i>' +
        '</div>';
    }
    
    // Basic details
    html += '<div class="flex-1">' +
        '<h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1">' + (student.full_name || "N/A") + '</h3>';
    
    if (student.christian_name) {
        html += '<p class="text-sm text-gray-600 dark:text-gray-300 mb-1">Christian Name: ' + student.christian_name + '</p>';
    }
    
    html += '<div class="flex flex-wrap gap-2 mt-2">' +
        '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Grade: ' + (student.current_grade || "N/A") + '</span>';
    
    if (student.gender) {
        html += '<span class="px-2 py-1 bg-pink-100 text-pink-800 text-xs rounded-full">' + student.gender + '</span>';
    }
    
    if (student.instrument) {
        html += '<span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">' + student.instrument + '</span>';
    }
    
    html += '</div></div></div>';
    
    // Information sections grid
    html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
    
    // Personal Information
    html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
        '<h4 class="text-lg font-semibold mb-4">Personal Information</h4>' +
        '<div class="space-y-2">';
    
    if (student.full_name) html += '<p><strong>Full Name:</strong> ' + student.full_name + '</p>';
    if (student.phone_number) html += '<p><strong>Phone:</strong> ' + student.phone_number + '</p>';
    if (student.birth_date) html += '<p><strong>Birth Date:</strong> ' + student.birth_date + '</p>';
    if (student.sub_city) html += '<p><strong>Sub City:</strong> ' + student.sub_city + '</p>';
    if (student.district) html += '<p><strong>District:</strong> ' + student.district + '</p>';
    
    html += '</div></div>';
    
    // Academic Information
    html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
        '<h4 class="text-lg font-semibold mb-4">Academic Information</h4>' +
        '<div class="space-y-2">';
    
    if (student.regular_school_name) html += '<p><strong>School:</strong> ' + student.regular_school_name + '</p>';
    if (student.education_level) html += '<p><strong>Education Level:</strong> ' + student.education_level + '</p>';
    if (student.field_of_study) html += '<p><strong>Field of Study:</strong> ' + student.field_of_study + '</p>';
    
    html += '</div></div></div>';
    
    // Family Information
    if (student.father_full_name || student.mother_full_name || student.guardian_full_name) {
        html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
            '<h4 class="text-lg font-semibold mb-4">Family Information</h4>' +
            '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
        
        if (student.father_full_name) {
            html += '<div><h5 class="font-medium">Father</h5>' +
                '<p class="text-sm">Name: ' + student.father_full_name + '</p>';
            if (student.father_phone) html += '<p class="text-sm">Phone: ' + student.father_phone + '</p>';
            if (student.father_occupation) html += '<p class="text-sm">Occupation: ' + student.father_occupation + '</p>';
            html += '</div>';
        }
        
        if (student.mother_full_name) {
            html += '<div><h5 class="font-medium">Mother</h5>' +
                '<p class="text-sm">Name: ' + student.mother_full_name + '</p>';
            if (student.mother_phone) html += '<p class="text-sm">Phone: ' + student.mother_phone + '</p>';
            if (student.mother_occupation) html += '<p class="text-sm">Occupation: ' + student.mother_occupation + '</p>';
            html += '</div>';
        }
        
        if (student.guardian_full_name) {
            html += '<div><h5 class="font-medium">Guardian</h5>' +
                '<p class="text-sm">Name: ' + student.guardian_full_name + '</p>';
            if (student.guardian_phone) html += '<p class="text-sm">Phone: ' + student.guardian_phone + '</p>';
            if (student.guardian_occupation) html += '<p class="text-sm">Occupation: ' + student.guardian_occupation + '</p>';
            html += '</div>';
        }
        
        html += '</div></div>';
    }
    
    // Emergency Contact
    if (student.emergency_name || student.emergency_phone) {
        html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
            '<h4 class="text-lg font-semibold mb-4">Emergency Contact</h4>' +
            '<div class="space-y-2">';
        
        if (student.emergency_name) html += '<p><strong>Contact Name:</strong> ' + student.emergency_name + '</p>';
        if (student.emergency_phone) html += '<p><strong>Phone:</strong> ' + student.emergency_phone + '</p>';
        if (student.emergency_alt_phone) html += '<p><strong>Alt Phone:</strong> ' + student.emergency_alt_phone + '</p>';
        if (student.emergency_address) html += '<p><strong>Address:</strong> ' + student.emergency_address + '</p>';
        
        html += '</div></div>';
    }
    
    // Additional Information
    let hasAdditionalInfo = student.special_interests || student.siblings_in_school || student.physical_disability || student.weak_side;
    
    if (hasAdditionalInfo) {
        html += '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4">' +
            '<h4 class="text-lg font-semibold mb-4">Additional Information</h4>' +
            '<div class="space-y-2">';
        
        if (student.special_interests) html += '<p><strong>Special Interests:</strong> ' + student.special_interests + '</p>';
        if (student.siblings_in_school) html += '<p><strong>Siblings in School:</strong> ' + student.siblings_in_school + '</p>';
        if (student.physical_disability) html += '<p><strong>Physical Disability:</strong> ' + student.physical_disability + '</p>';
        if (student.weak_side) html += '<p><strong>Weak Side:</strong> ' + student.weak_side + '</p>';
        
        html += '</div></div>';
    }
    
    // Registration Information
    html += '<div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">' +
        '<h4 class="text-lg font-semibold mb-4">Registration Information</h4>' +
        '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
    
    if (student.created_at) {
        html += '<p><strong>Registered:</strong> ' + new Date(student.created_at).toLocaleDateString() + '</p>';
    }
    if (student.id) {
        html += '<p><strong>Student ID:</strong> ' + student.id + '</p>';
    }
    
    html += '</div></div></div>';
    
    content.innerHTML = html;
};

// Individual Actions
function viewStudentDetails(studentId, table = "students") {
    return window.viewStudentDetails(studentId, table);
}

function displayStudentDetails(student) {
    return window.displayStudentDetails(student);
}

function toggleFlag(studentId, table = "students") {
    const button = document.querySelector('[data-flag-btn="' + studentId + '"]');
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin text-sm"></i>';
    }
    
    fetch(window.location.href, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: 'action=toggle_flag&student_id=' + studentId + '&table=' + table
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateFlagButton(studentId, data.flagged);
            showToast(data.message, "success");
        } else {
            showToast(data.message, "error");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        showToast("Error updating flag status", "error");
    })
    .finally(() => {
        if (button) {
            button.innerHTML = '<i class="fas fa-flag text-sm"></i>';
        }
    });
}

function updateFlagButton(studentId, flagged) {
    const button = document.querySelector('[data-flag-btn="' + studentId + '"]');
    if (button) {
        button.title = flagged ? "Unflag" : "Flag";
        
        if (flagged) {
            button.classList.add("text-red-600", "dark:text-red-400", "bg-red-100", "dark:bg-red-900/50");
            button.classList.remove("text-gray-400", "dark:text-gray-500", "hover:bg-gray-100", "dark:hover:bg-gray-700");
        } else {
            button.classList.remove("text-red-600", "dark:text-red-400", "bg-red-100", "dark:bg-red-900/50");
            button.classList.add("text-gray-400", "dark:text-gray-500", "hover:bg-gray-100", "dark:hover:bg-gray-700");
        }
    }
}

function editCurrentStudent() {
    if (currentStudentId) {
        editStudent(currentStudentId, currentTable);
    } else {
        showToast("No student selected for editing", "warning");
    }
}

function editStudent(studentId, table = "students") {
    return window.editStudent(studentId, table);
}

function deleteStudent(studentId, table = "students") {
    return window.deleteStudent(studentId, table);
}

// Utility Functions
function clearSearch() {
    document.getElementById("search").value = "";
    document.getElementById("filter-form").submit();
}

function applyQuickFilter(type) {
    const url = new URL(window.location);
    if (type === "flagged") {
        url.searchParams.set("status", "flagged");
    }
    window.location.href = url.toString();
}

function exportData() {
    const selectedColumns = [];
    document.querySelectorAll('.column-checkbox:checked').forEach(checkbox => {
        selectedColumns.push(checkbox.value);
    });
    
    // Construct export URL with current filters and selected columns
    const url = new URL(window.location);
    url.pathname = url.pathname.replace('students.php', 'export_students.php');
    if (selectedColumns.length > 0) {
        url.searchParams.set('columns', selectedColumns.join(','));
    }
    
    // Open export in new tab
    window.open(url.toString(), '_blank');
    showToast('Export started. Check your downloads folder.', 'info');
}

function closeStudentDetailsModal() {
    document.getElementById("student-details-modal").classList.add("hidden");
    currentStudentId = null;
}

function printStudentDetails() {
    // Hide modal background and other UI elements for printing
    const modal = document.getElementById("student-details-modal");
    const originalClasses = modal.className;
    
    // Temporarily modify modal for printing
    modal.className = "fixed inset-0 bg-white overflow-y-auto h-full w-full z-50";
    
    // Print the modal content
    window.print();
    
    // Restore original modal classes
    setTimeout(() => {
        modal.className = originalClasses;
    }, 100);
}

function viewFullProfile() {
    if (currentStudentId) {
        window.open("student_view.php?id=" + currentStudentId, "_blank");
    } else {
        showToast("No student selected", "warning");
    }
}

function closeModal(event) {
    if (event.target === event.currentTarget) {
        event.currentTarget.classList.add("hidden");
    }
}

function closeAllModals() {
    document.querySelectorAll(".modal").forEach(modal => {
        modal.classList.add("hidden");
    });
}

function initializeTooltips() {
    document.querySelectorAll("[title]").forEach(element => {
        element.addEventListener("mouseenter", showTooltip);
        element.addEventListener("mouseleave", hideTooltip);
    });
}

function showTooltip(event) {
    const tooltip = document.createElement("div");
    tooltip.className = "tooltip fixed z-50 px-2 py-1 text-xs text-white bg-gray-900 dark:bg-gray-700 rounded shadow-lg pointer-events-none";
    tooltip.textContent = event.target.title;
    
    event.target.dataset.originalTitle = event.target.title;
    event.target.removeAttribute("title");
    
    document.body.appendChild(tooltip);
    
    const rect = event.target.getBoundingClientRect();
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + "px";
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + "px";
}

function hideTooltip(event) {
    if (event.target.dataset.originalTitle) {
        event.target.title = event.target.dataset.originalTitle;
        delete event.target.dataset.originalTitle;
    }
    document.querySelectorAll(".tooltip").forEach(tooltip => tooltip.remove());
}