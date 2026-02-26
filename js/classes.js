// Global variables
let currentClassId = null;
let currentClassStudents = [];
let currentStudentsFilter = "";
let studentsSearchDebounceTimer = null;
let currentClassGrade = "";
let currentStudentsGradeFilter = "all"; // all | match | mismatch

// Initialize page
document.addEventListener("DOMContentLoaded", function() {
    loadClasses();
    
    // Handle form submission
    document.getElementById("class-form").addEventListener("submit", function(e) {
        e.preventDefault();
        saveClass();
    });
    
    // Handle search
    document.getElementById("class-search").addEventListener("input", function() {
        loadClasses(this.value);
    });
    
    // Handle grade selection for auto allocation
    document.getElementById("allocate-grade").addEventListener("change", function() {
        const grade = this.value;
        if (grade) {
            getGradeStudentCount(grade);
        } else {
            document.getElementById("student-count-info").style.display = "none";
        }

// Ensure extra UI elements exist in auto allocate modal
function ensureAutoAllocateEnhancements() {
    const modal = document.getElementById('auto-allocate-modal');
    if (!modal) return;
    const container = modal.querySelector('.compact-modal-content');
    if (!container) return;

    // Grade tabs
    if (!document.getElementById('allocate-grade-tabs')) {
        const tabs = document.createElement('div');
        tabs.id = 'allocate-grade-tabs';
        tabs.className = 'flex gap-2 compact-mb-3';
        tabs.innerHTML = `
            <button class="compact-btn bg-gray-100 dark:bg-gray-700" data-grade="new">New</button>
            <button class="compact-btn bg-gray-100 dark:bg-gray-700" data-grade="7th">7th</button>
            <button class="compact-btn bg-gray-100 dark:bg-gray-700" data-grade="8th">8th</button>
        `;
        container.prepend(tabs);
        tabs.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('allocate-grade').value = btn.getAttribute('data-grade');
                getGradeStudentCount(btn.getAttribute('data-grade'));
            });
        });
    }

    // Preview controls area
    if (!document.getElementById('allocation-controls')) {
        const controls = document.createElement('div');
        controls.id = 'allocation-controls';
        controls.className = 'flex items-center justify-between compact-mt-2 compact-mb-2';
        controls.innerHTML = `
            <div class="flex items-center gap-2">
                <input id="alloc-update-grade" type="checkbox" class="rounded" />
                <label for="alloc-update-grade" class="text-xs text-gray-700 dark:text-gray-300">Update student current grade on approval</label>
            </div>
            <div class="flex gap-2">
                <button id="btn-preview-allocation" class="compact-btn bg-blue-600 hover:bg-blue-700 text-white">Preview Allocation</button>
                <button id="btn-approve-allocation" class="compact-btn bg-green-600 hover:bg-green-700 text-white">Approve Allocation</button>
            </div>
        `;
        container.appendChild(controls);
        document.getElementById('btn-preview-allocation').addEventListener('click', previewAutoAllocation);
        document.getElementById('btn-approve-allocation').addEventListener('click', approveAutoAllocation);
    }

    // Preview output area
    if (!document.getElementById('allocation-preview')) {
        const preview = document.createElement('div');
        preview.id = 'allocation-preview';
        preview.className = 'compact-mt-2';
        container.appendChild(preview);
    }
}

// Preview allocation using backend dry_run=1
function previewAutoAllocation() {
    const grade = (window.__allocPreview?.grade) || document.getElementById('allocate-grade').value;
    const maxCapacity = (window.__allocPreview?.maxCapacity) || document.getElementById('max-capacity').value;
    const classIds = (window.__allocPreview?.classIds) || [];
    if (!grade) { alert('Please select a grade'); return; }
    if (!classIds.length) { alert('No classes available to preview. Create or select classes first.'); return; }

    const fd = new FormData();
    fd.append('action', 'auto_allocate_students');
    fd.append('grade', grade);
    classIds.forEach(id => fd.append('class_ids[]', id));
    fd.append('max_capacity', maxCapacity);
    fd.append('dry_run', '1');

    fetch(window.location.href, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(async res => {
        if (!res.success) { alert('Preview failed: ' + res.message); return; }
        // Fetch students for this grade to map names
        const map = await fetchStudentsMapByGrade(grade);
        renderAllocationPreview(res, map);
      })
      .catch(() => alert('Network error during preview'));
}

// Approve allocation using backend dry_run=0
function approveAutoAllocation() {
    const grade = (window.__allocPreview?.grade) || document.getElementById('allocate-grade').value;
    const maxCapacity = (window.__allocPreview?.maxCapacity) || document.getElementById('max-capacity').value;
    const classIds = (window.__allocPreview?.classIds) || [];
    const updateGrade = document.getElementById('alloc-update-grade')?.checked ? '1' : '0';
    if (!grade) { alert('Please select a grade'); return; }
    if (!classIds.length) { alert('No classes to approve.'); return; }

    const fd = new FormData();
    fd.append('action', 'auto_allocate_students');
    fd.append('grade', grade);
    classIds.forEach(id => fd.append('class_ids[]', id));
    fd.append('max_capacity', maxCapacity);
    fd.append('dry_run', '0');
    fd.append('update_current_grade', updateGrade);

    fetch(window.location.href, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
            loadClasses();
            alert(res.message);
            closeAutoAllocateModal();
        } else {
            alert('Approval failed: ' + res.message);
        }
      })
      .catch(() => alert('Network error during approval'));
}

async function fetchStudentsMapByGrade(grade) {
    const fd = new URLSearchParams();
    fd.append('action', 'get_students');
    fd.append('grade', grade);
    const res = await fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fd
    });
    const data = await res.json();
    const map = new Map();
    if (data.success && Array.isArray(data.students)) {
        data.students.forEach(s => map.set(Number(s.id), s));
    }
    return map;
}

function renderAllocationPreview(result, studentsMap) {
    const preview = document.getElementById('allocation-preview');
    if (!preview) return;
    const allocations = result.allocations || [];
    if (!allocations.length) {
        preview.innerHTML = `<div class="text-xs text-gray-600 dark:text-gray-300">No students would be allocated.</div>`;
        return;
    }
    let html = '';
    html += `<div class="text-xs text-gray-700 dark:text-gray-300 compact-mb-2">${result.message}</div>`;
    html += `<div class="border border-gray-200 dark:border-gray-700 rounded">`;
    allocations.forEach(a => {
        const list = (a.student_ids || []).map(id => {
            const s = studentsMap.get(Number(id));
            return s ? `${s.full_name} (${s.phone_number || '-'})` : `ID ${id}`;
        });
        html += `
            <div class="border-b border-gray-200 dark:border-gray-700 compact-p-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">Class #${a.class_id}</div>
                    <div class="text-xs text-gray-600">${a.student_count} student(s)</div>
                </div>
                ${list.length ? `<div class="mt-1 text-xs text-gray-700 dark:text-gray-300">${list.join(' • ')}</div>` : ''}
            </div>
        `;
    });
    html += `</div>`;
    preview.innerHTML = html;
}
    });
    
    // Load courses for drag and drop functionality
    loadCoursesForDragDrop();
});

// Load classes
function loadClasses(search = "") {
    const tableBody = document.getElementById("classes-table-body");
    tableBody.innerHTML = `<tr><td colspan="6" class="text-center compact-py-3"><div class="flex justify-center"><div class="animate-spin rounded-full compact-h-4 compact-w-4 border-b-2 border-primary-600"></div></div></td></tr>`;
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_classes" + (search ? "&search=" + encodeURIComponent(search) : "")
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.classes.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-gray-500 text-xs compact-py-3">No classes found</td></tr>`;
                return;
            }
            
            let html = "";
            data.classes.forEach(cls => {
                html += `
                <tr class="hover:compact-bg">
                    <td class="whitespace-nowrap compact-font-medium text-gray-900 dark:text-white text-xs compact-py-2 compact-px-3">
                        ${cls.name}
                    </td>
                    <td class="whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs compact-py-2 compact-px-3">
                        ${cls.grade}
                    </td>
                    <td class="whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs compact-py-2 compact-px-3">
                        ${cls.section || "-"}
                    </td>
                    <td class="whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs compact-py-2 compact-px-3">
                        ${cls.teacher_name || "-"}
                    </td>
                    <td class="whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs compact-py-2 compact-px-3">
                        ${cls.student_count || 0}
                    </td>
                    <td class="whitespace-nowrap text-xs compact-py-2 compact-px-3">
                        <button onclick="viewClassDetails(${cls.id})" class="compact-action-btn text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 mr-1">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="openEditClassModal(${cls.id})" class="compact-action-btn text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-1">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteClass(${cls.id})" class="compact-action-btn text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-red-500 text-xs compact-py-3">Error loading classes</td></tr>`;
        }
    })
    .catch(error => {
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-red-500 text-xs compact-py-3">Network error</td></tr>`;
    });
}

// Create class modal
function openCreateClassModal() {
    document.getElementById("class-modal-title").textContent = "Create Class";
    document.getElementById("class-id").value = "";
    document.getElementById("class-name").value = "";
    document.getElementById("class-grade").value = "";
    document.getElementById("class-section").value = "";
    document.getElementById("class-year").value = new Date().getFullYear();
    document.getElementById("class-capacity").value = "";
    document.getElementById("class-description").value = "";
    document.getElementById("class-modal").classList.remove("hidden");
}

// Edit class modal
function openEditClassModal(classId) {
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_class_details&class_id=" + classId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cls = data.class;
            document.getElementById("class-modal-title").textContent = "Edit Class";
            document.getElementById("class-id").value = cls.id;
            document.getElementById("class-name").value = cls.name;
            document.getElementById("class-grade").value = cls.grade;
            document.getElementById("class-section").value = cls.section || "";
            document.getElementById("class-year").value = cls.academic_year;
            document.getElementById("class-capacity").value = cls.capacity || "";
            document.getElementById("class-description").value = cls.description || "";
            document.getElementById("class-modal").classList.remove("hidden");
        } else {
            alert("Error loading class: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Close class modal
function closeClassModal() {
    document.getElementById("class-modal").classList.add("hidden");
}

// Save class
function saveClass() {
    const classId = document.getElementById("class-id").value;
    const name = document.getElementById("class-name").value;
    const grade = document.getElementById("class-grade").value;
    const section = document.getElementById("class-section").value;
    const year = document.getElementById("class-year").value;
    const capacity = document.getElementById("class-capacity").value;
    const description = document.getElementById("class-description").value;
    
    if (!name || !grade) {
        alert("Class name and grade are required");
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append("action", classId ? "update_class" : "create_class");
    formData.append("name", name);
    formData.append("grade", grade);
    formData.append("section", section);
    formData.append("academic_year", year);
    if (capacity) formData.append("capacity", capacity);
    formData.append("description", description);
    if (classId) formData.append("class_id", classId);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeClassModal();
            loadClasses();
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Delete class
function deleteClass(classId) {
    if (!confirm("Are you sure you want to delete this class?")) return;
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=delete_class&class_id=" + classId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadClasses();
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// View class details
function viewClassDetails(classId) {
    currentClassId = classId;
    document.getElementById("class-details-modal").classList.remove("hidden");
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_class_details&class_id=" + classId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cls = data.class;
            const students = data.students;
            currentClassStudents = students || [];
            currentClassGrade = String(cls.grade || '').trim();
            currentStudentsGradeFilter = 'all';
            const courses = data.courses || [];
            const teachersData = data.teachers || [];
            
            // Calculate statistics
            const studentCount = students.length;
            const courseCount = courses.length;
            const capacity = cls.capacity || "Unlimited";
            
            // Create HTML structure
            let html = "";
            html += "<div class='space-y-6'>";
            html += "<div class='flex justify-between items-center'>";
            html += "<div>";
            html += "<h3 class='text-xl font-semibold text-gray-900 dark:text-white'>Class Details: " + cls.name + "</h3>";
            const primaryTeacher = cls.teacher_name || (teachersData.length ? teachersData[0].teacher_name : "-");
            html += "<p class='text-xs text-gray-600 dark:text-gray-400 mt-1'>Teacher: " + (primaryTeacher || "-") + "</p>";
            html += "</div>";
            html += "<button onclick='closeClassDetailsModal()' class='text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'>";
            html += "<i class='fas fa-times'></i>";
            html += "</button>";
            html += "</div>";
            
            // Statistics cards
            html += "<div class='grid grid-cols-2 md:grid-cols-4 gap-4'>";
            html += "<div class='p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg'>";
            html += "<div class='text-sm font-medium text-blue-800 dark:text-blue-200'>Grade</div>";
            html += "<div class='text-lg font-semibold text-blue-900 dark:text-blue-100'>" + cls.grade + "</div>";
            html += "</div>";
            html += "<div class='p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg'>";
            html += "<div class='text-sm font-medium text-green-800 dark:text-green-200'>Section</div>";
            html += "<div class='text-lg font-semibold text-green-900 dark:text-green-100'>" + (cls.section || "-") + "</div>";
            html += "</div>";
            html += "<div class='p-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg'>";
            html += "<div class='text-sm font-medium text-purple-800 dark:text-purple-200'>Students</div>";
            html += "<div class='text-lg font-semibold text-purple-900 dark:text-purple-100'>" + studentCount + "</div>";
            html += "</div>";
            html += "<div class='p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg'>";
            html += "<div class='text-sm font-medium text-amber-800 dark:text-amber-200'>Capacity</div>";
            html += "<div class='text-lg font-semibold text-amber-900 dark:text-amber-100'>" + capacity + "</div>";
            html += "</div>";
            html += "</div>";
            
            // Tabs
            html += "<div class='border-b border-gray-200 dark:border-gray-700 mb-6'>";
            html += "<nav class='flex gap-6'>";
            html += "<button id='courses-tab' onclick='showClassTab(\"courses\")' class='py-3 px-1 border-primary-500 text-primary-600 dark:text-primary-400 whitespace-nowrap border-b-2 font-medium'>";
            html += "Courses (" + courseCount + ")";
            html += "</button>";
            html += "<button id='students-tab' onclick='showClassTab(\"students\")' class='py-3 px-1 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-500 whitespace-nowrap border-b-2 font-medium'>";
            html += "Students (" + studentCount + ")";
            html += "</button>";
            html += "<button id='teachers-tab' onclick='showClassTab(\"teachers\")' class='py-3 px-1 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-500 whitespace-nowrap border-b-2 font-medium'>";
            html += "Teachers";
            html += "</button>";
            html += "</nav>";
            html += "</div>";
            
            // Courses tab content with compact design
            html += "<div id='courses-content'>";
            html += "<div class='flex justify-between items-center compact-mb-2'>";
            html += "<h4 class='font-medium text-gray-900 dark:text-white text-sm'>Assigned Courses</h4>";
            html += "<button onclick='openAssignCourseModal(" + cls.id + ")' class='compact-btn bg-primary-600 hover:bg-primary-700 text-white flex items-center'>";
            html += "<i class='fas fa-plus mr-1'></i> Add Course";
            html += "</button>";
            html += "</div>";
            
            if (courses.length > 0) {
                html += "<div class='border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden'>";
                html += "<table class='compact-table min-w-full divide-y divide-gray-200 dark:divide-gray-700'>";
                html += "<thead class='bg-gray-50 dark:bg-gray-700'>";
                html += "<tr>";
                html += "<th class='text-left font-medium text-gray-500 dark:text-gray-300'>Course</th>";
                html += "<th class='text-left font-medium text-gray-500 dark:text-gray-300'>Teacher</th>";
                html += "<th class='text-left font-medium text-gray-500 dark:text-gray-300'>Semester</th>";
                html += "<th class='text-left font-medium text-gray-500 dark:text-gray-300'>Actions</th>";
                html += "</tr>";
                html += "</thead>";
                html += "<tbody class='bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700'>";
                
                courses.forEach(course => {
                    html += "<tr class='hover:compact-bg'>";
                    html += "<td class='whitespace-nowrap compact-font-medium text-gray-900 dark:text-white compact-py-2 compact-px-3'>";
                    html += "<div>" + course.course_name + "</div>";
                    html += "<div class='text-xs text-gray-500 dark:text-gray-400'>" + course.course_code + "</div>";
                    html += "</td>";
                    html += "<td class='whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs compact-py-2 compact-px-3'>" + course.teacher_name + "</td>";
                    html += "<td class='whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs compact-py-2 compact-px-3'>" + course.semester + "</td>";
                    html += "<td class='whitespace-nowrap text-xs compact-py-2 compact-px-3'>";
                    html += "<button onclick='removeCourseAssignment(" + course.id + ")' class='compact-action-btn text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300'>";
                    html += "<i class='fas fa-trash'></i>";
                    html += "</button>";
                    html += "</td>";
                    html += "</tr>";
                });
                
                html += "</tbody>";
                html += "</table>";
                html += "</div>";
            } else {
                html += "<div class='text-center compact-py-6'>";
                html += "<i class='fas fa-book-open text-gray-400 text-xl compact-mb-2'></i>";
                html += "<p class='text-gray-500 dark:text-gray-400 text-xs'>No courses assigned to this class</p>";
                html += "<button onclick='openAssignCourseModal(" + cls.id + ")' class='compact-btn bg-primary-600 hover:bg-primary-700 text-white compact-mt-2'>";
                html += "Assign Course";
                html += "</button>";
                html += "</div>";
            }
            
            html += "</div>";
            
            // Students tab content
            html += "<div id='students-content' class='hidden'>";
            html += "<div class='flex flex-col md:flex-row md:items-center md:justify-between gap-2 compact-mb-2'>";
            html += "<div class='flex items-center gap-2'>";
            html += "<h4 class='font-medium text-gray-900 dark:text-white text-sm'>Enrolled Students</h4>";
            html += "<div class='relative md:w-64'>";
            html += "<input type='text' id='students-search' placeholder='Search by name, grade, or phone' class='compact-input pl-8 pr-7 h-8 rounded-md bg-gray-50 border border-gray-300 shadow-sm focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white w-full'/>";
            html += "<div class='absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none'><i class='fas fa-search text-gray-400 text-xs'></i></div>";
            html += "<button type='button' id='students-search-clear' class='hidden absolute inset-y-0 right-0 pr-2 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300' aria-label='Clear search'><i class='fas fa-times-circle text-sm'></i></button>";
            html += "</div>";
            html += "</div>";
            // Simple grade filter chips
            html += "<div class='flex items-center gap-1'>";
            html += "  <button type='button' id='gradeflt-all' class='compact-btn bg-gray-200 hover:bg-gray-300 text-gray-800'>All</button>";
            html += "  <button type='button' id='gradeflt-match' class='compact-btn bg-gray-100 hover:bg-gray-200 text-gray-800'>Class grade</button>";
            html += "  <button type='button' id='gradeflt-mismatch' class='compact-btn bg-gray-100 hover:bg-gray-200 text-gray-800'>Mismatched</button>";
            html += "</div>";
            html += "<div class='flex gap-2'>";
            html += "<button onclick='removeSelectedStudents(" + cls.id + ")' id='remove-selected-btn' class='compact-btn bg-red-600 hover:bg-red-700 text-white hidden'>Remove Selected</button>";
            html += "<button onclick='openAssignStudentsModal(" + cls.id + ")' class='compact-btn bg-primary-600 hover:bg-primary-700 text-white flex items-center'>";
            html += "<i class='fas fa-plus mr-1'></i> Add Students";
            html += "</button>";
            html += "<button onclick='exportClassStudents(" + cls.id + ")' class='compact-btn bg-gray-600 hover:bg-gray-700 text-white'>Export CSV</button>";
            html += "</div>";
            html += "</div>";
            
            if (students.length > 0) {
                html += "<div class='border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden'>";
                html += "<table class='compact-table min-w-full divide-y divide-gray-200 dark:divide-gray-700'>";
                html += "<thead class='bg-gray-50 dark:bg-gray-700'>";
                html += "<tr>";
                html += "<th class='text-left font-medium text-gray-500 dark:text-gray-300'><input type='checkbox' id='select-all-students' class='rounded'></th>";
                html += "<th class='text-left font-medium text-gray-500 dark:text-gray-300'>Name</th>";
                html += "<th class='text-left font-medium text-gray-500 dark:text-gray-300'>Grade</th>";
                html += "<th class='text-left font-medium text-gray-500 dark:text-gray-300'>Phone</th>";
                html += "<th class='text-left font-medium text-gray-500 dark:text-gray-300'>Actions</th>";
                html += "</tr>";
                html += "</thead>";
                html += "<tbody class='bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700' id='students-table-body'>";
                
                students.forEach(student => {
                    const isNewFlag = Number(student.is_new_registration || 0) === 1;
                    const isNewGrade = String(student.current_grade || '').toLowerCase() === 'new';
                    const isNew = isNewFlag || isNewGrade;
                    const rowClass = (isNew ? ' border-l-4 border-amber-600 bg-amber-50 dark:bg-amber-900/20' : '');
                    html += "<tr class='hover:compact-bg" + rowClass + "'>";
                    html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap'><input type='checkbox' class='student-checkbox rounded' data-student-id='" + student.id + "'></td>";
                    html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap compact-font-medium text-gray-900 dark:text-white'>" + student.full_name 
                        + (isNew ? " <span class='ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200'>NEW</span>" : "")
                        + "</td>";
                    html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs'>" + student.current_grade + "</td>";
                    html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs'>" + (student.phone_number || "-") + "</td>";
                    html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap text-xs'>";
                    html += "<button onclick='removeStudent(" + cls.id + ", " + student.id + ")' class='compact-action-btn text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300'>";
                    html += "<i class='fas fa-user-minus'></i>";
                    html += "</button>";
                    html += "</td>";
                    html += "</tr>";
                });
                
                html += "</tbody>";
                html += "</table>";
                html += "</div>";
            } else {
                html += "<div class='text-center py-8'>";
                html += "<i class='fas fa-users text-gray-400 text-2xl mb-3'></i>";
                html += "<p class='text-gray-500 dark:text-gray-400 mb-4'>No students enrolled in this class</p>";
                html += "<button onclick='openAssignStudentsModal(" + cls.id + ")' class='px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md'>";
                html += "Assign Students";
                html += "</button>";
                html += "</div>";
            }
            
            html += "</div>";
            
            // Teachers tab content
            html += "<div id='teachers-content' class='hidden'>";
            html += "<div class='flex justify-between items-center mb-4'>";
            html += "<h4 class='font-medium text-gray-900 dark:text-white text-lg'>Assigned Teachers</h4>";
            html += "<button onclick='openAssignTeacherModal(" + cls.id + ")' class='px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md flex items-center'>";
            html += "<i class='fas fa-plus mr-2'></i> Assign Teacher";
            html += "</button>";
            html += "</div>";
            
            html += "<div class='border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden'>";
            html += "<table class='min-w-full divide-y divide-gray-200 dark:divide-gray-700'>";
            html += "<thead class='bg-gray-50 dark:bg-gray-700'>";
            html += "<tr>";
            html += "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>Teacher</th>";
            html += "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>Role</th>";
            html += "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>Assigned Date</th>";
            html += "<th class='px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>Actions</th>";
            html += "</tr>";
            html += "</thead>";
            html += "<tbody id='teachers-list' class='bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700'>";
            html += "</tbody>";
            html += "</table>";
            html += "</div>";
            html += "</div>";
            
            // Action buttons
            html += "<div class='flex justify-end gap-3 pt-6'>";
            html += "<button onclick='closeClassDetailsModal()' class='px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md'>";
            html += "Close";
            html += "</button>";
            html += "</div>";
            html += "</div>";
            
            document.getElementById("class-details-content").innerHTML = html;
            
            // Initialize tab functionality
            showClassTab('courses');
            // Attach search handler (will be used when switching to Students tab too)
            const studentsSearch = document.getElementById('students-search');
            const studentsSearchClear = document.getElementById('students-search-clear');
            if (studentsSearch) {
                studentsSearch.addEventListener('input', handleStudentsSearchInput);
            }
            if (studentsSearchClear) {
                studentsSearchClear.addEventListener('click', () => {
                    studentsSearch.value = '';
                    handleStudentsSearchInput();
                    studentsSearch.focus();
                });
            }
            // Grade filter chip handlers
            const btnAll = document.getElementById('gradeflt-all');
            const btnMatch = document.getElementById('gradeflt-match');
            const btnMismatch = document.getElementById('gradeflt-mismatch');
            function refreshChipStyles(){
                const activeCls = 'bg-primary-600 text-white';
                const idleCls = 'bg-gray-100 text-gray-800';
                [btnAll, btnMatch, btnMismatch].forEach(b=>{ if(!b) return; b.classList.remove('bg-primary-600','text-white','bg-gray-100','text-gray-800','bg-gray-200'); });
                if (btnAll) btnAll.classList.add(currentStudentsGradeFilter==='all'? 'bg-primary-600':'bg-gray-200', currentStudentsGradeFilter==='all'?'text-white':'text-gray-800');
                if (btnMatch) btnMatch.classList.add(currentStudentsGradeFilter==='match'? 'bg-primary-600':'bg-gray-100', currentStudentsGradeFilter==='match'?'text-white':'text-gray-800');
                if (btnMismatch) btnMismatch.classList.add(currentStudentsGradeFilter==='mismatch'? 'bg-primary-600':'bg-gray-100', currentStudentsGradeFilter==='mismatch'?'text-white':'text-gray-800');
            }
            if (btnAll) btnAll.addEventListener('click', ()=>{ currentStudentsGradeFilter='all'; refreshChipStyles(); handleStudentsSearchInput(); });
            if (btnMatch) btnMatch.addEventListener('click', ()=>{ currentStudentsGradeFilter='match'; refreshChipStyles(); handleStudentsSearchInput(); });
            if (btnMismatch) btnMismatch.addEventListener('click', ()=>{ currentStudentsGradeFilter='mismatch'; refreshChipStyles(); handleStudentsSearchInput(); });
            refreshChipStyles();
        } else {
            document.getElementById("class-details-content").innerHTML = "<p class='text-red-500 text-center compact-py-6'>Error loading class details: " + data.message + "</p>";
        }
    })
    .catch(error => {
        document.getElementById("class-details-content").innerHTML = "<p class='text-red-500 text-center compact-py-6'>Network error: " + error.message + "</p>";
    });
}

function showClassTab(tabName) {
    // Hide all tab content
    document.getElementById('courses-content').classList.add('hidden');
    document.getElementById('students-content').classList.add('hidden');
    document.getElementById('teachers-content').classList.add('hidden');
    
    // Remove active classes from all tabs
    document.getElementById('courses-tab').classList.remove('border-primary-500', 'text-primary-600', 'dark:text-primary-400');
    document.getElementById('courses-tab').classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300', 'dark:hover:border-gray-500');
    
    document.getElementById('students-tab').classList.remove('border-primary-500', 'text-primary-600', 'dark:text-primary-400');
    document.getElementById('students-tab').classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300', 'dark:hover:border-gray-500');
    
    document.getElementById('teachers-tab').classList.remove('border-primary-500', 'text-primary-600', 'dark:text-primary-400');
    document.getElementById('teachers-tab').classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300', 'dark:hover:border-gray-500');
    
    // Show the selected tab content and activate the tab
    if (tabName === 'courses') {
        document.getElementById('courses-content').classList.remove('hidden');
        document.getElementById('courses-tab').classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300', 'dark:hover:border-gray-500');
        document.getElementById('courses-tab').classList.add('border-primary-500', 'text-primary-600', 'dark:text-primary-400');
    } else if (tabName === 'students') {
        document.getElementById('students-content').classList.remove('hidden');
        document.getElementById('students-tab').classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300', 'dark:hover:border-gray-500');
        document.getElementById('students-tab').classList.add('border-primary-500', 'text-primary-600', 'dark:text-primary-400');
        
        // Add event listener for select all checkbox
        const selectAllCheckbox = document.getElementById('select-all-students');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.student-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                
                // Show/hide remove selected button
                const removeSelectedBtn = document.getElementById('remove-selected-btn');
                if (this.checked && checkboxes.length > 0) {
                    removeSelectedBtn.classList.remove('hidden');
                } else {
                    removeSelectedBtn.classList.add('hidden');
                }
            });
        }
        
        // Add event listeners for individual checkboxes
        const studentCheckboxes = document.querySelectorAll('.student-checkbox');
        studentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const anyChecked = document.querySelectorAll('.student-checkbox:checked').length > 0;
                const allChecked = document.querySelectorAll('.student-checkbox:checked').length === studentCheckboxes.length;
                
                // Update select all checkbox
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
                
                // Show/hide remove selected button
                const removeSelectedBtn = document.getElementById('remove-selected-btn');
                if (anyChecked) {
                    removeSelectedBtn.classList.remove('hidden');
                } else {
                    removeSelectedBtn.classList.add('hidden');
                }
            });
        });

        // Attach search handler on tab activation
        const studentsSearch = document.getElementById('students-search');
        const studentsSearchClear = document.getElementById('students-search-clear');
        if (studentsSearch) {
            studentsSearch.addEventListener('input', handleStudentsSearchInput);
            // Apply current filter when switching tabs
            handleStudentsSearchInput();
        }
        if (studentsSearchClear) {
            studentsSearchClear.addEventListener('click', () => {
                studentsSearch.value = '';
                handleStudentsSearchInput();
                studentsSearch.focus();
            });
        }
    } else if (tabName === 'teachers') {
        document.getElementById('teachers-content').classList.remove('hidden');
        document.getElementById('teachers-tab').classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300', 'dark:hover:border-gray-500');
        document.getElementById('teachers-tab').classList.add('border-primary-500', 'text-primary-600', 'dark:text-primary-400');
        
        // Load teachers data
        loadClassTeachers(currentClassId);
    }
}

// Filter students in the modal and re-render table
function filterStudentsInModal() {
    const input = document.getElementById('students-search');
    currentStudentsFilter = input ? (input.value || "").toLowerCase().trim() : "";
    const filtered = getFilteredStudents();
    renderStudentsTable(filtered);
}

function handleStudentsSearchInput() {
    const input = document.getElementById('students-search');
    const clearBtn = document.getElementById('students-search-clear');
    if (clearBtn) {
        if (input && input.value) clearBtn.classList.remove('hidden');
        else clearBtn.classList.add('hidden');
    }
    if (studentsSearchDebounceTimer) {
        clearTimeout(studentsSearchDebounceTimer);
    }
    studentsSearchDebounceTimer = setTimeout(() => {
        filterStudentsInModal();
    }, 200);
}

function getFilteredStudents() {
    let list = (currentClassStudents || []).slice();
    // Apply grade filter based on chips
    const clsGrade = String(currentClassGrade || '').trim();
    if (currentStudentsGradeFilter === 'match') {
        list = list.filter(s => String(s.current_grade || '').trim() === clsGrade);
    } else if (currentStudentsGradeFilter === 'mismatch') {
        list = list.filter(s => String(s.current_grade || '').trim() !== clsGrade);
    }
    // Apply text search
    if (!currentStudentsFilter) return list;
    return list.filter(s => {
        const name = (s.full_name || "").toLowerCase();
        const phone = (s.phone_number || "").toLowerCase();
        const grade = (s.current_grade || "").toLowerCase();
        return name.includes(currentStudentsFilter) || phone.includes(currentStudentsFilter) || grade.includes(currentStudentsFilter);
    });
}

function renderStudentsTable(students) {
    const tbody = document.getElementById('students-table-body');
    if (!tbody) return;
    if (!students || students.length === 0) {
        tbody.innerHTML = "<tr><td colspan='5' class='text-center text-gray-500 dark:text-gray-400 text-sm py-4'>No students found</td></tr>";
        return;
    }
    let html = "";
    students.forEach(student => {
        const isNewFlag = Number(student.is_new_registration || 0) === 1;
        const isNewGrade = String(student.current_grade || '').toLowerCase() === 'new';
        const isNew = isNewFlag || isNewGrade;
        const rowClass = (isNew ? ' border-l-4 border-amber-600 bg-amber-50 dark:bg-amber-900/20' : '');
        html += "<tr class='hover:compact-bg" + rowClass + "'>";
        html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap'><input type='checkbox' class='student-checkbox rounded' data-student-id='" + student.id + "'></td>";
        html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap compact-font-medium text-gray-900 dark:text-white'>" + (student.full_name || "")
            + (isNew ? " <span class='ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200'>NEW</span>" : "")
            + "</td>";
        html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs'>" + (student.current_grade || "-") + "</td>";
        html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap text-gray-500 dark:text-gray-400 text-xs'>" + (student.phone_number || "-") + "</td>";
        html += "<td class='compact-py-2 compact-px-3 whitespace-nowrap text-xs'>";
        html += "<button onclick='removeStudent(" + currentClassId + ", " + student.id + ")' class='compact-action-btn text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300'>";
        html += "<i class='fas fa-user-minus'></i>";
        html += "</button>";
        html += "</td>";
        html += "</tr>";
    });
    tbody.innerHTML = html;
    attachStudentCheckboxHandlers();
}

function attachStudentCheckboxHandlers() {
    const selectAllCheckbox = document.getElementById('select-all-students');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            studentCheckboxes.forEach(cb => cb.checked = this.checked);
            const removeSelectedBtn = document.getElementById('remove-selected-btn');
            if (this.checked && studentCheckboxes.length > 0) removeSelectedBtn.classList.remove('hidden');
            else removeSelectedBtn.classList.add('hidden');
        });
    }
    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const anyChecked = document.querySelectorAll('.student-checkbox:checked').length > 0;
            const allChecked = document.querySelectorAll('.student-checkbox:checked').length === studentCheckboxes.length;
            if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
            const removeSelectedBtn = document.getElementById('remove-selected-btn');
            if (anyChecked) removeSelectedBtn.classList.remove('hidden');
            else removeSelectedBtn.classList.add('hidden');
        });
    });
}

// Export current (filtered) students to CSV
function exportClassStudents(classId) {
    const students = getFilteredStudents();
    if (!students || students.length === 0) {
        alert("No students to export");
        return;
    }
    const headers = ["Name", "Grade", "Phone", "Enrollment Date"]; 
    const rows = students.map(s => [
        sanitizeCsv(s.full_name),
        sanitizeCsv(s.current_grade),
        sanitizeCsv(s.phone_number || ""),
        sanitizeCsv(s.enrollment_date || "")
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `class_${classId}_students.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function sanitizeCsv(value) {
    if (value == null) return "";
    const s = String(value).replace(/\r?\n|\r/g, ' ');
    if (/[",\n]/.test(s)) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

// Close class details modal
function closeClassDetailsModal() {
    document.getElementById("class-details-modal").classList.add("hidden");
}

// Open assign students modal
function openAssignStudentsModal(classId) {
    const modal = document.createElement("div");
    modal.id = "assign-students-modal";
    modal.className = "fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50";
    modal.innerHTML = `
        <div class="relative top-6 mx-auto p-3 border w-11/12 md:w-4/5 max-h-[95vh] overflow-y-auto shadow-lg rounded-md bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Assign Students</h3>
                <button onclick="closeAssignStudentsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <div class="py-3">
                <div class="mb-3 flex flex-col md:flex-row md:items-center gap-3">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by Grade</label>
                        <select id="filter-grade" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-md focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">All Grades</option>
                            <option value="new">New Students</option>
                            <option value="1st">Grade 1</option>
                            <option value="2nd">Grade 2</option>
                            <option value="3rd">Grade 3</option>
                            <option value="4th">Grade 4</option>
                            <option value="5th">Grade 5</option>
                            <option value="6th">Grade 6</option>
                            <option value="7th">Grade 7</option>
                            <option value="8th">Grade 8</option>
                            <option value="9th">Grade 9</option>
                            <option value="10th">Grade 10</option>
                            <option value="11th">Grade 11</option>
                            <option value="12th">Grade 12</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Search Students</label>
                        <div class="relative">
                            <input type="text" id="student-search" placeholder="Search by name or phone..." class="w-full pl-3 pr-8 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-md focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400 text-xs"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3 flex flex-col md:flex-row gap-3">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Auto Select Students</label>
                        <div class="flex gap-2">
                            <input type="number" id="auto-select-count" min="1" placeholder="Number of students" class="flex-1 px-2.5 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-md focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <button type="button" onclick="autoSelectStudents()" class="px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded-md">Select</button>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">Enter number of students to automatically select</p>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Selected Students</label>
                            <button type="button" onclick="openQuickAddModal(${classId})" class="px-2 py-1 text-[11px] bg-emerald-600 hover:bg-emerald-700 text-white rounded">
                                Quick add
                            </button>
                        </div>
                        <div class="px-2.5 py-1.5 text-xs bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-between">
                            <span>
                                <span id="selected-count" class="font-medium">0</span> selected
                                (<span id="assign-count" class="font-medium">0</span> assign,
                                <span id="move-count" class="font-medium">0</span> move)
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-1.5">
                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Available Students</h4>
                        <span id="student-count" class="text-xs text-gray-500 dark:text-gray-400">0 students</span>
                    </div>
                    <div class="border border-gray-200 dark:border-gray-700 rounded-md max-h-[70vh] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                <tr>
                                    <th class="text-left font-medium text-gray-500 uppercase py-2 px-3">
                                        <input type="checkbox" id="select-all" class="rounded">
                                    </th>
                                    <th class="text-left font-medium text-gray-500 uppercase py-2 px-3">Name</th>
                                    <th class="text-left font-medium text-gray-500 uppercase py-2 px-3">Grade</th>
                                    <th class="text-left font-medium text-gray-500 uppercase py-2 px-3">Phone</th>
                                    <th class="text-left font-medium text-gray-500 uppercase py-2 px-3">Status</th>
                                </tr>
                            </thead>
                            <tbody id="students-list" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr>
                                    <td colspan="5" class="text-center py-3">
                                        <div class="flex justify-center">
                                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-600"></div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeAssignStudentsModal()" class="px-3 py-1.5 text-xs bg-gray-600 hover:bg-gray-700 text-white rounded-md">Cancel</button>
                    <button type="button" onclick="moveSelectedStudents(${classId})" class="px-3 py-1.5 text-xs bg-amber-600 hover:bg-amber-700 text-white rounded-md">Move Selected</button>
                    <button type="button" onclick="assignSelectedStudents(${classId})" class="px-3 py-1.5 text-xs bg-primary-600 hover:bg-primary-700 text-white rounded-md">Assign Selected</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Load students
    loadAvailableStudents(classId);
    
    // Add event listeners
    document.getElementById("filter-grade").addEventListener("change", function() {
        loadAvailableStudents(classId);
    });
    
    document.getElementById("student-search").addEventListener("input", function() {
        loadAvailableStudents(classId);
    });
    
    document.getElementById("select-all").addEventListener("change", function() {
        const checkboxes = document.querySelectorAll("#students-list input[type=checkbox]:not(:disabled)");
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
}

// Load available students
function loadAvailableStudents(classId) {
    const grade = document.getElementById("filter-grade").value;
    const search = document.getElementById("student-search").value;
    
    const formData = new URLSearchParams();
    formData.append("action", "get_students");
    if (grade) formData.append("grade", grade);
    if (search) formData.append("search", search);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const students = data.students;
            const tbody = document.getElementById("students-list");
            // Dynamically populate the grade filter from returned data
            try {
                const sel = document.getElementById('filter-grade');
                if (sel) {
                    const prev = sel.value;
                    const gradesSet = new Set();
                    students.forEach(s => {
                        const g = String(s.current_grade||'').trim();
                        if (g) gradesSet.add(g);
                    });
                    const grades = Array.from(gradesSet).sort((a,b)=>a.localeCompare(b, undefined, {numeric:true,sensitivity:'base'}));
                    // Rebuild options: All Grades + dynamic
                    sel.innerHTML = '';
                    const optAll = document.createElement('option');
                    optAll.value = '';
                    optAll.textContent = 'All Grades';
                    sel.appendChild(optAll);
                    grades.forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g;
                        opt.textContent = g;
                        sel.appendChild(opt);
                    });
                    // Restore previous selection if still present
                    if ([...sel.options].some(o=>o.value===prev)) sel.value = prev;
                }
            } catch (e) { /* ignore UI population errors */ }
            
            if (students.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-gray-500 text-sm py-4">No students found</td></tr>`;
                document.getElementById("student-count").textContent = "0 students";
                return;
            }
            
            let html = "";
            students.forEach(student => {
                // Compute indicators
                const inThisClass = Number(student.current_class_id || 0) === Number(classId);
                const inAnotherClass = !!student.current_class_id && !inThisClass;
                const status = inThisClass ? 'Enrolled' : (inAnotherClass ? 'Assigned' : 'Available');
                const isNew = Number(student.is_new_registration || 0) === 1;
                const isFlagged = Number(student.is_flagged || 0) === 1;
                // Styling: de-emphasize non-available, highlight NEW
                const rowClass = ((inThisClass || inAnotherClass) ? "opacity-60 " : "") + (isNew ? 'border-l-4 border-amber-600 bg-amber-50 dark:bg-amber-900/20 ' : '');
                const statusClass = inThisClass
                    ? 'bg-blue-100 text-blue-700'
                    : (inAnotherClass ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700');
                const statusHint = inAnotherClass && student.current_class_name ? `title="Currently in: ${student.current_class_name}"` : '';
                const safeClassName = String(student.current_class_name || '')
                    .replace(/\\/g, '\\\\')
                    .replace(/'/g, "\\'");
                const moveButton = inAnotherClass
                    ? `<button type="button" onclick="moveStudentToClass(${student.id}, ${classId}, '${safeClassName}'); event.stopPropagation();" class="inline-flex items-center px-1.5 py-0.5 rounded bg-amber-600 hover:bg-amber-700 text-white text-[10px] leading-none">Move here</button>`
                    : '';
                const checkboxClass = inAnotherClass ? 'move-checkbox' : 'student-checkbox';
                const disabled = inThisClass ? "disabled" : "";

                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 ${rowClass}">
                        <td class="px-2 py-1">
                            <input type="checkbox" class="rounded ${checkboxClass}" data-id="${student.id}" ${disabled}>
                        </td>
                        <td class="px-2 py-1 font-medium text-gray-900 dark:text-white text-xs">
                            ${isNew ? '<span class="mr-1 inline-block align-middle w-2 h-2 rounded-full bg-amber-600" title="New"></span>' : ''}
                            ${student.full_name}
                            ${isFlagged ? '<span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200">Flag</span>' : ''}
                        </td>
                        <td class="px-2 py-1 text-gray-500 dark:text-gray-400 text-xs">${student.current_grade}</td>
                        <td class="px-2 py-1 text-gray-500 dark:text-gray-400 text-xs">${student.phone_number || "-"}</td>
                        <td class="px-2 py-1 text-xs">
                            <div class="flex flex-col">
                                <div class="inline-flex items-center gap-1">
                                    <span ${statusHint} class="inline-flex w-fit items-center px-2 py-0.5 rounded-full ${statusClass}">
                                        ${status}
                                    </span>
                                    ${moveButton}
                                </div>
                                ${inThisClass ? `<span class="mt-0.5 text-[10px] text-gray-500">(this class)</span>` : ''}
                                ${inAnotherClass && student.current_class_name ? `<span class="mt-0.5 text-[10px] text-gray-500">${student.current_class_name}</span>` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            document.getElementById("student-count").textContent = `${students.length} students`;
            
            // Update select all checkbox
            document.getElementById("select-all").checked = false;
            
            // Add event listeners for checkboxes
            document.querySelectorAll('.student-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
            document.querySelectorAll('.move-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
        } else {
            document.getElementById("students-list").innerHTML = `<tr><td colspan="5" class="text-center text-red-500 text-sm py-4">Error loading students</td></tr>`;
        }
    })
    .catch(error => {
        document.getElementById("students-list").innerHTML = `<tr><td colspan="5" class="text-center text-red-500 text-sm py-4">Network error</td></tr>`;
    });
}

function moveStudentToClass(studentId, targetClassId, fromClassName) {
    const fromName = String(fromClassName || 'another class');
    if (!confirm(`Move this student from "${fromName}" to this class?`)) return;

    const formData = new URLSearchParams();
    formData.append('action', 'move_student');
    formData.append('class_id', targetClassId);
    formData.append('student_id', studentId);

    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAvailableStudents(targetClassId);
            viewClassDetails(targetClassId);
            loadClasses();
            alert(data.message || 'Student moved successfully');
        } else {
            alert('Error: ' + (data.message || 'Failed to move student'));
        }
    })
    .catch(() => {
        alert('Network error');
    });
}

// Update selected count
function updateSelectedCount() {
    const assignCount = document.querySelectorAll('.student-checkbox:checked').length;
    const moveCount = document.querySelectorAll('.move-checkbox:checked').length;
    const selectedCount = assignCount + moveCount;
    document.getElementById('selected-count').textContent = selectedCount;
    const assignEl = document.getElementById('assign-count');
    const moveEl = document.getElementById('move-count');
    if (assignEl) assignEl.textContent = assignCount;
    if (moveEl) moveEl.textContent = moveCount;
}

// Auto select students
function autoSelectStudents() {
    const count = parseInt(document.getElementById('auto-select-count').value);
    if (isNaN(count) || count <= 0) {
        alert('Please enter a valid number');
        return;
    }
    
    const checkboxes = document.querySelectorAll('.student-checkbox:not(:disabled)');
    if (count > checkboxes.length) {
        alert(`Only ${checkboxes.length} students are available to select`);
        return;
    }
    
    // Uncheck all checkboxes first
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
    
    // Select the specified number of students
    for (let i = 0; i < count; i++) {
        checkboxes[i].checked = true;
    }
    
    // Update selected count
    updateSelectedCount();
    
    // Update select all checkbox
    document.getElementById('select-all').checked = (count === checkboxes.length);
}

function openQuickAddModal(classId) {
    const existing = document.getElementById('quick-add-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'quick-add-modal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-[60]';
    modal.innerHTML = `
        <div class="relative top-12 mx-auto p-3 border w-[92%] sm:w-[84%] md:w-[60%] lg:w-[42%] xl:w-[36%] shadow-lg rounded-md bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Quick Add Students</h3>
                <button type="button" onclick="closeQuickAddModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <div class="pt-3">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Full names</label>
                <textarea id="quick-add-modal-names" rows="5" placeholder="One full name per line, or comma separated" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white"></textarea>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">Students are auto-enrolled to this class.</p>
                <div class="mt-3 flex justify-end gap-2">
                    <button id="quick-add-cancel-btn" type="button" onclick="closeQuickAddModal()" class="px-3 py-1.5 text-xs bg-gray-600 hover:bg-gray-700 text-white rounded-md">Cancel</button>
                    <button id="quick-add-submit-btn" type="button" onclick="submitQuickAddStudents(${classId})" class="px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white rounded-md">Add now</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    const input = document.getElementById('quick-add-modal-names');
    if (input) input.focus();
}

function closeQuickAddModal() {
    const modal = document.getElementById('quick-add-modal');
    if (modal) modal.remove();
}

function submitQuickAddStudents(classId) {
    const namesInput = document.getElementById('quick-add-modal-names');
    const submitBtn = document.getElementById('quick-add-submit-btn');
    const cancelBtn = document.getElementById('quick-add-cancel-btn');
    if (!namesInput) return;

    const fullNames = (namesInput.value || '').trim();
    if (!fullNames) {
        alert('Please enter at least one full name');
        return;
    }

    if (submitBtn && submitBtn.disabled) {
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
    }
    if (cancelBtn) {
        cancelBtn.disabled = true;
        cancelBtn.classList.add('opacity-70', 'cursor-not-allowed');
    }

    const formData = new URLSearchParams();
    formData.append('action', 'quick_add_students');
    formData.append('class_id', classId);
    formData.append('full_names', fullNames);

    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            namesInput.value = '';
            closeQuickAddModal();
            loadAvailableStudents(classId);
            loadClasses();
            alert(data.message);
        } else {
            alert('Error: ' + data.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
                submitBtn.innerHTML = 'Add now';
            }
            if (cancelBtn) {
                cancelBtn.disabled = false;
                cancelBtn.classList.remove('opacity-70', 'cursor-not-allowed');
            }
        }
    })
    .catch(() => {
        alert('Network error');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
            submitBtn.innerHTML = 'Add now';
        }
        if (cancelBtn) {
            cancelBtn.disabled = false;
            cancelBtn.classList.remove('opacity-70', 'cursor-not-allowed');
        }
    });
}

// Close assign students modal
function closeAssignStudentsModal() {
    const modal = document.getElementById("assign-students-modal");
    if (modal) modal.remove();
}

// Assign selected students
function assignSelectedStudents(classId) {
    const selectedCheckboxes = document.querySelectorAll(".student-checkbox:checked");
    const studentIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.getAttribute("data-id")));
    
    if (studentIds.length === 0) {
        alert("Please select at least one student");
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append("action", "assign_students");
    formData.append("class_id", classId);
    formData.append("student_ids", JSON.stringify(studentIds));
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAssignStudentsModal();
            viewClassDetails(classId); // Refresh class details
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

function moveSelectedStudents(classId) {
    const selectedCheckboxes = document.querySelectorAll(".move-checkbox:checked");
    const studentIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.getAttribute("data-id"), 10)).filter(Boolean);

    if (studentIds.length === 0) {
        alert("Please select at least one assigned student to move");
        return;
    }

    if (!confirm("Move selected students to this class?")) return;

    const formData = new URLSearchParams();
    formData.append("action", "move_students_bulk");
    formData.append("class_id", classId);
    formData.append("student_ids", JSON.stringify(studentIds));

    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAvailableStudents(classId);
            viewClassDetails(classId);
            loadClasses();
            alert(data.message || "Students moved successfully");
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(() => {
        alert("Network error");
    });
}

// Remove student from class
function removeStudent(classId, studentId) {
    if (!confirm("Are you sure you want to remove this student from the class?")) return;
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=remove_student&class_id=" + classId + "&student_id=" + studentId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            viewClassDetails(classId); // Refresh class details
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Open assign teacher modal
function openAssignTeacherModal(classId) {
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_teachers"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const teachers = data.teachers;
            
            const modal = document.createElement("div");
            modal.id = "assign-teacher-modal";
            modal.className = "fixed inset-0 compact-modal-overlay overflow-y-auto compact-h-full compact-w-full z-50";
            modal.innerHTML = `
                <div class="compact-modal compact-modal-container bg-white dark:bg-gray-800 border compact-w-11/12 md:compact-w-2/5">
                    <div class="flex items-center justify-between compact-modal-header border-b border-gray-200 dark:border-gray-700">
                        <h3 class="compact-modal-header text-gray-900 dark:text-white">Assign Teacher</h3>
                        <button onclick="closeAssignTeacherModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                    <div class="compact-modal-content">
                        <div class="compact-mb-3">
                            <label class="compact-form-label text-gray-700 dark:text-gray-300">Select Teacher</label>
                            <select id="teacher-select" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select a teacher</option>
                                ${teachers.map(teacher => `<option value="${teacher.id}">${teacher.full_name} ${teacher.phone ? "(" + teacher.phone + ")" : ""}</option>`).join("")}
                            </select>
                        </div>
                        
                        <div class="compact-mb-3">
                            <label class="compact-form-label text-gray-700 dark:text-gray-300">Role</label>
                            <select id="role-select" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="primary">Primary Teacher</option>
                                <option value="assistant">Assistant Teacher</option>
                                <option value="homeroom">Homeroom Teacher</option>
                            </select>
                        </div>
                        
                        <div class="flex justify-end compact-gap-2">
                            <button onclick="closeAssignTeacherModal()" class="compact-btn bg-gray-600 hover:bg-gray-700 text-white">Cancel</button>
                            <button onclick="assignTeacher(${classId})" class="compact-btn bg-primary-600 hover:bg-primary-700 text-white">Assign Teacher</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        } else {
            alert("Error loading teachers: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Close assign teacher modal
function closeAssignTeacherModal() {
    const modal = document.getElementById("assign-teacher-modal");
    if (modal) modal.remove();
}

// Assign teacher
function assignTeacher(classId) {
    const teacherId = document.getElementById("teacher-select").value;
    const role = document.getElementById("role-select").value;
    
    if (!teacherId) {
        alert("Please select a teacher");
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append("action", "assign_teacher");
    formData.append("class_id", classId);
    formData.append("teacher_id", teacherId);
    formData.append("role", role);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAssignTeacherModal();
            viewClassDetails(classId); // Refresh class details
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Open auto allocate modal
function openAutoAllocateModal() {
    document.getElementById("allocate-grade").value = "";
    document.getElementById("max-capacity").value = "50";
    document.getElementById("sections").value = "";
    document.getElementById("student-count-info").style.display = "none";
    document.getElementById("auto-allocate-modal").classList.remove("hidden");

    // Inject grade tabs and preview controls if not present
    ensureAutoAllocateEnhancements();
}

// Close auto allocate modal
function closeAutoAllocateModal() {
    document.getElementById("auto-allocate-modal").classList.add("hidden");
}

// Get student count for grade
function getGradeStudentCount(grade) {
    const formData = new URLSearchParams();
    formData.append("action", "get_grade_student_count");
    formData.append("grade", grade);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const count = data.count;
            const maxCapacity = parseInt(document.getElementById("max-capacity").value) || 50;
            const recommendedClasses = Math.ceil(count / maxCapacity);
            
            document.getElementById("student-count").textContent = count;
            document.getElementById("recommended-classes").textContent = recommendedClasses;
            document.getElementById("student-count-info").style.display = "block";
            
            // Generate recommended sections
            let sections = [];
            for (let i = 1; i <= recommendedClasses; i++) {
                sections.push(String.fromCharCode(64 + i)); // A, B, C...
            }
            document.getElementById("sections").value = sections.join(",");
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Create section classes and allocate students
function createSectionClasses() {
    const grade = document.getElementById("allocate-grade").value;
    const sections = document.getElementById("sections").value.split(",").map(s => s.trim()).filter(s => s);
    const maxCapacity = document.getElementById("max-capacity").value;
    
    if (!grade) {
        alert("Please select a grade");
        return;
    }
    
    if (sections.length === 0) {
        alert("Please enter at least one section");
        return;
    }
    
    // Create section classes
    const formData = new FormData();
    formData.append("action", "create_section_classes");
    formData.append("grade", grade);
    // Send sections as individual parameters
    sections.forEach((section, index) => {
        formData.append("sections[]", section);
    });
    if (maxCapacity) formData.append("capacity", maxCapacity);
    
    fetch(window.location.href, {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Store created class IDs for allocation
            const classIds = data.classes.map(cls => cls.id);
            
            // Store created class IDs for preview/approval flow
            window.__allocPreview = { classIds, grade, maxCapacity };
            previewAutoAllocation();
            
            // Auto allocate students to these classes
            const allocateData = new FormData();
            allocateData.append("action", "auto_allocate_students");
            allocateData.append("grade", grade);
            // Send class IDs as individual parameters
            classIds.forEach((classId, index) => {
                allocateData.append("class_ids[]", classId);
            });
            allocateData.append("max_capacity", maxCapacity);
            
            fetch(window.location.href, {
                method: "POST",
                body: allocateData
            })
            .then(response => response.json())
            .then(allocateResult => {
                if (allocateResult.success) {
                    closeAutoAllocateModal();
                    loadClasses();
                    alert(allocateResult.message);
                } else {
                    alert("Error allocating students: " + allocateResult.message);
                }
            })
            .catch(error => {
                alert("Network error during allocation: " + error.message);
            });
        } else {
            alert("Error creating classes: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error during class creation: " + error.message);
    });
}

// Open assign course modal
function openAssignCourseModal(classId) {
    // First get courses and teachers
    Promise.all([
        fetch(window.location.href, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=get_courses"
        }).then(response => response.json()),
        fetch(window.location.href, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=get_teachers"
        }).then(response => response.json())
    ])
    .then(([coursesData, teachersData]) => {
        if (coursesData.success && teachersData.success) {
            const courses = coursesData.courses;
            const teachers = teachersData.teachers;
            
            const modal = document.createElement("div");
            modal.id = "assign-course-modal";
            modal.className = "fixed inset-0 compact-modal-overlay overflow-y-auto compact-h-full compact-w-full z-50";
            modal.innerHTML = `
                <div class="compact-modal compact-modal-container bg-white dark:bg-gray-800 border compact-w-11/12 md:compact-w-2/5">
                    <div class="flex items-center justify-between compact-modal-header border-b border-gray-200 dark:border-gray-700">
                        <h3 class="compact-modal-header text-gray-900 dark:text-white">Assign Course</h3>
                        <button onclick="closeAssignCourseModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                    <div class="compact-modal-content">
                        <div class="compact-mb-3">
                            <label class="compact-form-label text-gray-700 dark:text-gray-300">Select Course *</label>
                            <select id="course-select" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select a course</option>
                                ${courses.map(course => `<option value="${course.id}">${course.name} (${course.code})</option>`).join("")}
                            </select>
                        </div>
                        
                        <div class="compact-mb-3">
                            <label class="compact-form-label text-gray-700 dark:text-gray-300">Select Teacher *</label>
                            <select id="course-teacher-select" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select a teacher</option>
                                ${teachers.map(teacher => `<option value="${teacher.id}">${teacher.full_name} ${teacher.phone ? "(" + teacher.phone + ")" : ""}</option>`).join("")}
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 compact-gap-2 compact-mb-3">
                            <div>
                                <label class="compact-form-label text-gray-700 dark:text-gray-300">Semester</label>
                                <select id="semester-select" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                    <option value="1st">1st Semester</option>
                                    <option value="2nd">2nd Semester</option>
                                </select>
                            </div>
                            <div>
                                <label class="compact-form-label text-gray-700 dark:text-gray-300">Academic Year</label>
                                <input type="number" id="academic-year" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="${new Date().getFullYear()}">
                            </div>
                        </div>
                        
                        <div class="compact-mb-3">
                            <label class="compact-form-label text-gray-700 dark:text-gray-300">Hours Per Week</label>
                            <input type="number" id="hours-per-week" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="3" min="1" max="20">
                        </div>
                        
                        <div class="flex justify-end compact-gap-2">
                            <button onclick="closeAssignCourseModal()" class="compact-btn bg-gray-600 hover:bg-gray-700 text-white">Cancel</button>
                            <button onclick="assignCourse(${classId})" class="compact-btn bg-primary-600 hover:bg-primary-700 text-white">Assign Course</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        } else {
            alert("Error loading data: " + (coursesData.message || teachersData.message));
        }
    })
    .catch(error => {
        alert("Network error: " + error.message);
    });
}

// Close assign course modal
function closeAssignCourseModal() {
    const modal = document.getElementById("assign-course-modal");
    if (modal) modal.remove();
}

// Assign course to class
function assignCourse(classId) {
    const courseId = document.getElementById("course-select").value;
    const teacherId = document.getElementById("course-teacher-select").value;
    const semester = document.getElementById("semester-select").value;
    const academicYear = document.getElementById("academic-year").value;
    const hoursPerWeek = document.getElementById("hours-per-week").value;
    
    if (!courseId || !teacherId) {
        alert("Please select both a course and a teacher");
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append("action", "assign_course");
    formData.append("class_id", classId);
    formData.append("course_id", courseId);
    formData.append("teacher_id", teacherId);
    formData.append("semester", semester);
    formData.append("academic_year", academicYear);
    formData.append("hours_per_week", hoursPerWeek);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAssignCourseModal();
            viewClassDetails(classId); // Refresh class details
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Remove course assignment
function removeCourseAssignment(assignmentId) {
    if (!confirm("Are you sure you want to remove this course assignment?")) return;
    
    const formData = new URLSearchParams();
    formData.append("action", "remove_course_assignment");
    formData.append("assignment_id", assignmentId);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the class details modal
            if (currentClassId) {
                viewClassDetails(currentClassId);
            }
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Load class teachers
function loadClassTeachers(classId) {
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_class_details&class_id=" + classId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const teachers = data.teachers || [];
            const tbody = document.getElementById("teachers-list");
            
            if (teachers.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-gray-500 text-sm py-4">No teachers assigned to this class</td></tr>`;
                return;
            }
            
            let html = "";
            teachers.forEach(teacher => {
                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">${teacher.teacher_name}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">${teacher.teacher_phone || "-"}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">${teacher.role}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${teacher.assigned_date}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <button onclick="removeTeacherAssignment(${teacher.id})" class="p-2 text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        } else {
            document.getElementById("teachers-list").innerHTML = `<tr><td colspan="4" class="text-center text-red-500 text-sm py-4">Error loading teachers</td></tr>`;
        }
    })
    .catch(error => {
        document.getElementById("teachers-list").innerHTML = `<tr><td colspan="4" class="text-center text-red-500 text-sm py-4">Network error</td></tr>`;
    });
}

// Remove teacher assignment
function removeTeacherAssignment(assignmentId) {
    if (!confirm("Are you sure you want to remove this teacher assignment?")) return;
    
    const formData = new URLSearchParams();
    formData.append("action", "remove_teacher_assignment");
    formData.append("assignment_id", assignmentId);
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the class details modal
            if (currentClassId) {
                viewClassDetails(currentClassId);
            }
            alert(data.message);
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        alert("Network error");
    });
}

// Remove selected students
function removeSelectedStudents(classId) {
    const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        alert("Please select at least one student to remove");
        return;
    }
    
    if (!confirm(`Are you sure you want to remove ${selectedCheckboxes.length} selected student(s) from this class?`)) return;
    
    // Remove each selected student
    let removedCount = 0;
    selectedCheckboxes.forEach(checkbox => {
        const studentId = checkbox.getAttribute('data-student-id');
        
        const formData = new URLSearchParams();
        formData.append("action", "remove_student");
        formData.append("class_id", classId);
        formData.append("student_id", studentId);
        
        fetch(window.location.href, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                removedCount++;
                // Remove the row from the table
                checkbox.closest('tr').remove();
                
                // If all selected students are removed, refresh the view
                if (removedCount === selectedCheckboxes.length) {
                    // Hide the remove selected button
                    document.getElementById('remove-selected-btn').classList.add('hidden');
                    
                    // Uncheck select all checkbox
                    document.getElementById('select-all-students').checked = false;
                    
                    // Show success message
                    alert(`${removedCount} student(s) removed successfully`);
                }
            } else {
                alert("Error removing student: " + data.message);
            }
        })
        .catch(error => {
            alert("Network error removing student");
        });
    });
}

// Global variables for drag and drop
let draggedCourse = null;

// Initialize drag and drop functionality
function initializeDragAndDrop() {
    // Add drop zone event listeners
    const dropZones = document.querySelectorAll('.drop-zone');
    dropZones.forEach(zone => {
        zone.addEventListener('dragover', handleDragOver);
        zone.addEventListener('dragleave', handleDragLeave);
        zone.addEventListener('drop', handleDrop);
    });
}

// Handle drag over event
function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.add('drag-over');
}

// Handle drag leave event
function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('drag-over');
}

// Handle drop event
function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('drag-over');
    
    const classId = this.getAttribute('data-class-id');
    
    if (draggedCourse && classId) {
        // Show assign course modal with pre-filled data
        openAssignCourseModalWithCourse(classId, draggedCourse);
    }
}

// Open assign course modal with a specific course pre-selected
function openAssignCourseModalWithCourse(classId, course) {
    // First get courses and teachers
    Promise.all([
        fetch(window.location.href, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=get_courses"
        }).then(response => response.json()),
        fetch(window.location.href, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=get_teachers"
        }).then(response => response.json())
    ])
    .then(([coursesData, teachersData]) => {
        if (coursesData.success && teachersData.success) {
            const courses = coursesData.courses;
            const teachers = teachersData.teachers;
            
            const modal = document.createElement("div");
            modal.id = "assign-course-modal";
            modal.className = "fixed inset-0 compact-modal-overlay overflow-y-auto compact-h-full compact-w-full z-50";
            modal.innerHTML = `
                <div class="compact-modal compact-modal-container bg-white dark:bg-gray-800 border compact-w-11/12 md:compact-w-2/5">
                    <div class="flex items-center justify-between compact-modal-header border-b border-gray-200 dark:border-gray-700">
                        <h3 class="compact-modal-header text-gray-900 dark:text-white">Assign Course to Class</h3>
                        <button onclick="closeAssignCourseModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                    <div class="compact-modal-content">
                        <div class="compact-mb-3">
                            <label class="compact-form-label text-gray-700 dark:text-gray-300">Select Course *</label>
                            <select id="course-select" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select a course</option>
                                ${courses.map(courseItem => `<option value="${courseItem.id}" ${courseItem.id == course.id ? 'selected' : ''}>${courseItem.name} (${courseItem.code})</option>`).join("")}
                            </select>
                        </div>
                        
                        <div class="compact-mb-3">
                            <label class="compact-form-label text-gray-700 dark:text-gray-300">Select Teacher *</label>
                            <select id="course-teacher-select" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select a teacher</option>
                                ${teachers.map(teacher => `<option value="${teacher.id}">${teacher.full_name} ${teacher.phone ? "(" + teacher.phone + ")" : ""}</option>`).join("")}
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 compact-gap-2 compact-mb-3">
                            <div>
                                <label class="compact-form-label text-gray-700 dark:text-gray-300">Semester</label>
                                <select id="semester-select" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                    <option value="1st">1st Semester</option>
                                    <option value="2nd">2nd Semester</option>
                                </select>
                            </div>
                            <div>
                                <label class="compact-form-label text-gray-700 dark:text-gray-300">Academic Year</label>
                                <input type="number" id="academic-year" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="${new Date().getFullYear()}">
                            </div>
                        </div>
                        
                        <div class="compact-mb-3">
                            <label class="compact-form-label text-gray-700 dark:text-gray-300">Hours Per Week</label>
                            <input type="number" id="hours-per-week" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="3" min="1" max="20">
                        </div>
                        
                        <div class="flex justify-end compact-gap-2">
                            <button onclick="closeAssignCourseModal()" class="compact-btn bg-gray-600 hover:bg-gray-700 text-white">Cancel</button>
                            <button onclick="assignCourse(${classId})" class="compact-btn bg-primary-600 hover:bg-primary-700 text-white">Assign Course</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        } else {
            alert("Error loading data: " + (coursesData.message || teachersData.message));
        }
    })
    .catch(error => {
        alert("Network error: " + error.message);
    });
}
