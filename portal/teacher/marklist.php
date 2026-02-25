<?php
require '../../config.php';
require '../../includes/security_helpers.php';
require '../../includes/portal_auth.php';

$ctx = getAuthContext();
if (!$ctx || $ctx['actor'] !== 'portal' || ($ctx['portal_role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit;
}

$teacherId = (int)$ctx['teacher_id'];
$stmt = $pdo->prepare("SELECT full_name FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$teacherName = (string)($stmt->fetchColumn() ?: 'Teacher');

$csrf = SecurityHelper::generateCSRFToken();
$defaultClassId = (int)($_GET['class_id'] ?? 0);
$defaultCourseId = (int)($_GET['course_id'] ?? 0);
$defaultTermId = (int)($_GET['term_id'] ?? 0);
$pageTitle = 'Teacher Marklist';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            body: ["Manrope", "sans-serif"],
            display: ["Space Grotesk", "sans-serif"]
          },
          colors: {
            ink: "#13212d",
            ocean: "#0f5e83",
            sky: "#daf2ff",
            mint: "#0e9f6e",
            sand: "#f7f2e8"
          }
        }
      }
    }
  </script>
  <style>
    .sidebar-collapsed .sidebar-label { display: none; }
    .sidebar-collapsed #teacher_sidebar { width: 5.5rem; }
    .sidebar-collapsed .sidebar-user { display: none; }
    .sidebar-collapsed .sidebar-logo-mini { display: block; }
    .sidebar-collapsed .sidebar-logo-full { display: none; }
    .panel-card { box-shadow: 0 14px 32px rgba(15, 94, 131, 0.08); }
    .scroll-shell { max-height: min(62vh, 620px); overflow: auto; }
    .scroll-shell thead th { position: sticky; top: 0; z-index: 2; background: #f8fafc; }
    .scroll-shell::-webkit-scrollbar { width: 10px; height: 10px; }
    .scroll-shell::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    .scroll-shell::-webkit-scrollbar-track { background: #f1f5f9; }
  </style>
</head>
<body id="teacher_portal_body" class="bg-slate-100 font-body text-ink min-h-screen overflow-x-hidden">
<div id="sidebar_backdrop" class="fixed inset-0 bg-slate-900/50 z-30 hidden md:hidden"></div>
<div id="import_modal_backdrop" class="fixed inset-0 bg-slate-900/60 z-40 hidden"></div>

<div class="min-h-screen md:h-screen md:flex md:overflow-hidden">
  <aside id="teacher_sidebar" class="fixed md:sticky md:top-0 top-0 left-0 h-screen w-72 bg-ink text-white p-4 md:p-5 z-40 transform -translate-x-full md:translate-x-0 transition-all duration-200 ease-out md:flex-shrink-0">
    <div class="flex items-center justify-between gap-2">
      <div>
        <p class="text-[11px] uppercase tracking-[0.2em] text-sky/90 sidebar-label">Portal</p>
        <h1 class="font-display text-2xl mt-1 sidebar-logo-full">Teacher Desk</h1>
        <h1 class="font-display text-2xl mt-1 hidden sidebar-logo-mini">TD</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="sidebar_toggle" class="hidden md:inline-flex items-center justify-center w-9 h-9 rounded bg-white/10 hover:bg-white/20 text-white" title="Toggle sidebar" aria-label="Toggle sidebar"><<</button>
        <button id="sidebar_close_mobile" class="inline-flex md:hidden items-center justify-center w-9 h-9 rounded bg-white/10 hover:bg-white/20 text-white" title="Close menu" aria-label="Close menu">X</button>
      </div>
    </div>

    <div class="mt-5 rounded-xl bg-white/10 p-4 sidebar-user sidebar-label">
      <p class="text-xs text-sky/80">Logged In As</p>
      <p class="font-semibold mt-1"><?= htmlspecialchars($teacherName) ?></p>
    </div>

    <nav class="mt-6 space-y-2">
      <a href="dashboard.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
        <span class="w-6 text-center">DB</span><span class="sidebar-label">Dashboard</span>
      </a>
      <a href="marklist.php" class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 text-sm font-medium">
        <span class="w-6 text-center">ML</span><span class="sidebar-label">Marklist Entry</span>
      </a>
      <a href="history.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
        <span class="w-6 text-center">HS</span><span class="sidebar-label">History</span>
      </a>
      <a href="analytics.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
        <span class="w-6 text-center">AN</span><span class="sidebar-label">Analytics</span>
      </a>
    </nav>

    <div class="mt-8">
      <a href="../../logout.php" class="inline-flex items-center bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        <span class="sidebar-label">Logout</span><span class="md:hidden">Logout</span>
      </a>
    </div>
  </aside>

  <main id="teacher_main" class="flex-1 p-4 md:p-7 md:ml-0 md:h-screen md:overflow-y-auto min-w-0">
    <div class="flex items-center justify-between mb-3 md:mb-0">
      <button id="sidebar_open_mobile" class="inline-flex md:hidden items-center justify-center bg-ink text-white px-3 py-2 rounded-lg text-sm">Menu</button>
    </div>

    <div class="bg-gradient-to-r from-ocean to-cyan-700 rounded-2xl p-5 md:p-6 text-white shadow-lg">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-[0.2em] opacity-85">Teacher Workspace</p>
          <h2 class="font-display text-2xl md:text-3xl mt-1">Marklist Entry</h2>
          <p class="text-sm mt-2 opacity-90">Book, Assignment, Quiz, Mid, Final, Attendance and direct total sum.</p>
        </div>
        <a href="dashboard.php" class="inline-flex items-center justify-center bg-white/15 hover:bg-white/25 border border-white/30 px-4 py-2 rounded-lg text-sm">
          Back To Dashboard
        </a>
      </div>
    </div>

    <div class="mt-5 bg-white border border-gray-200 rounded-xl panel-card p-4 sm:p-6">
      <div class="flex flex-col sm:flex-row sm:items-end gap-3 sm:gap-4 mb-4">
        <div class="min-w-[220px]">
          <label class="block text-xs font-medium text-gray-700 mb-1">Class</label>
          <select id="class_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></select>
        </div>
        <div class="min-w-[220px]">
          <label class="block text-xs font-medium text-gray-700 mb-1">Course</label>
          <select id="course_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></select>
        </div>
        <div class="min-w-[180px]">
          <label class="block text-xs font-medium text-gray-700 mb-1">Term</label>
          <select id="term_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></select>
        </div>
        <div class="flex flex-wrap gap-2">
          <button id="load_btn" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">Load Marklist</button>
          <button id="save_btn" class="bg-emerald-600 text-white rounded px-4 py-2 text-sm hover:bg-emerald-700" disabled>Save</button>
          <button id="export_current_btn" type="button" class="bg-indigo-600 text-white rounded px-3 py-2 text-xs hover:bg-indigo-700">Export CSV</button>
          <button id="open_import_modal_btn" type="button" class="bg-amber-600 text-white rounded px-3 py-2 text-xs hover:bg-amber-700">Import</button>
        </div>
      </div>
      <div class="border border-gray-200 rounded mb-4 bg-slate-50">
        <button id="weights_toggle_btn" type="button" class="w-full flex items-center justify-between p-3 text-left">
          <span class="text-xs font-semibold text-gray-700">Assessment Component Limits (set by teacher)</span>
          <span id="weights_toggle_icon" class="text-xs text-gray-600">Show</span>
        </button>
        <div id="weights_panel" class="px-3 pb-3 hidden">
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
            <label class="text-xs text-gray-700">Book Max
              <input type="number" min="0" max="1000" step="0.01" id="w_book" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" value="10">
            </label>
            <label class="text-xs text-gray-700">Assignment Max
              <input type="number" min="0" max="1000" step="0.01" id="w_assignment" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" value="10">
            </label>
            <label class="text-xs text-gray-700">Quiz Max
              <input type="number" min="0" max="1000" step="0.01" id="w_quiz" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" value="10">
            </label>
            <label class="text-xs text-gray-700">Mid Exam Max
              <input type="number" min="0" max="1000" step="0.01" id="w_mid" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" value="20">
            </label>
            <label class="text-xs text-gray-700">Final Exam Max
              <input type="number" min="0" max="1000" step="0.01" id="w_final" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" value="40">
            </label>
            <label class="text-xs text-gray-700">Attendance Max
              <input type="number" min="0" max="1000" step="0.01" id="w_attendance" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" value="10">
            </label>
          </div>
          <div id="weight_hint" class="text-xs text-gray-600 mt-2">Total configured weight: 100</div>
        </div>
      </div>

      <div id="status_box" class="hidden mb-3 px-3 py-2 rounded text-sm"></div>
      <div id="context_info" class="hidden mb-3 px-3 py-2 rounded border text-xs bg-amber-50 text-amber-800 border-amber-200"></div>

      <div id="recent_panel" class="mb-4 border border-slate-200 rounded-xl bg-white">
        <div class="px-3 py-2 border-b border-slate-200">
          <p class="text-xs font-semibold text-slate-700">Recent Marklist Sessions</p>
        </div>
        <div id="recent_list" class="p-3 text-sm text-slate-500">
          No recent sessions yet.
        </div>
      </div>

      <div id="marklist_workspace" class="hidden">
      <div class="scroll-shell border border-gray-200 rounded-xl bg-white">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">#</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Student</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Book</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Assignment</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Quiz</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Mid</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Final</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Attendance</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Total</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Remark</th>
            </tr>
          </thead>
          <tbody id="marklist_body" class="divide-y divide-gray-100">
            <tr><td class="px-3 py-4 text-gray-500" colspan="10">Select class/course/term and click "Load Marklist".</td></tr>
          </tbody>
        </table>
      </div>
      </div>
    </div>
  </main>
</div>

<div id="import_modal" class="fixed right-0 top-0 h-screen w-full sm:w-[380px] bg-white z-50 border-l border-slate-200 shadow-2xl translate-x-full transition-transform duration-200 ease-out">
  <div class="p-4 border-b border-slate-200 flex items-center justify-between">
    <h3 class="font-semibold text-sm text-slate-800">Import Marklist File</h3>
    <button id="close_import_modal_btn" type="button" class="text-slate-600 hover:text-slate-900 text-sm px-2 py-1 rounded">X</button>
  </div>
  <div class="p-4 space-y-3">
    <p class="text-xs text-slate-600">Upload CSV or Excel (.xlsx/.xls), preview rows, then click Save.</p>
    <input id="import_csv_file" type="file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" class="w-full text-xs border border-slate-300 rounded px-2 py-2 bg-white">
    <button id="import_preview_btn" type="button" class="w-full bg-amber-600 text-white rounded px-3 py-2 text-xs hover:bg-amber-700">Import Preview File</button>
  </div>
</div>

<script>
const csrfToken = <?= json_encode($csrf) ?>;
const preselect = {
  class_id: <?= (int)$defaultClassId ?>,
  course_id: <?= (int)$defaultCourseId ?>,
  term_id: <?= (int)$defaultTermId ?>
};

const sidebarKey = "teacher_sidebar_collapsed";
const body = document.getElementById("teacher_portal_body");
const sidebar = document.getElementById("teacher_sidebar");
const backdrop = document.getElementById("sidebar_backdrop");
const importBackdrop = document.getElementById("import_modal_backdrop");
const importModal = document.getElementById("import_modal");
const toggleBtn = document.getElementById("sidebar_toggle");
const openMobileBtn = document.getElementById("sidebar_open_mobile");
const closeMobileBtn = document.getElementById("sidebar_close_mobile");
const openImportModalBtn = document.getElementById("open_import_modal_btn");
const closeImportModalBtn = document.getElementById("close_import_modal_btn");
const weightsToggleBtn = document.getElementById("weights_toggle_btn");
const weightsPanel = document.getElementById("weights_panel");
const weightsToggleIcon = document.getElementById("weights_toggle_icon");

function applyDesktopSidebarState(collapsed) {
  body.classList.toggle("sidebar-collapsed", collapsed);
  toggleBtn.textContent = collapsed ? ">>" : "<<";
}

function initDesktopSidebar() {
  const stored = localStorage.getItem(sidebarKey);
  if (stored === null) {
    // Default collapsed on Marklist page.
    localStorage.setItem(sidebarKey, "1");
    applyDesktopSidebarState(true);
  } else {
    applyDesktopSidebarState(stored === "1");
  }
}

function openMobileSidebar() {
  sidebar.classList.remove("-translate-x-full");
  backdrop.classList.remove("hidden");
}

function closeMobileSidebar() {
  sidebar.classList.add("-translate-x-full");
  backdrop.classList.add("hidden");
}

function openImportModal() {
  importModal.classList.remove("translate-x-full");
  importBackdrop.classList.remove("hidden");
}

function closeImportModal() {
  importModal.classList.add("translate-x-full");
  importBackdrop.classList.add("hidden");
}

function setWeightsPanelState(collapsed) {
  if (collapsed) {
    weightsPanel.classList.add("hidden");
    weightsToggleIcon.textContent = "Show";
  } else {
    weightsPanel.classList.remove("hidden");
    weightsToggleIcon.textContent = "Hide";
  }
}

toggleBtn.addEventListener("click", () => {
  const collapsed = !body.classList.contains("sidebar-collapsed");
  applyDesktopSidebarState(collapsed);
  localStorage.setItem(sidebarKey, collapsed ? "1" : "0");
});
openMobileBtn.addEventListener("click", openMobileSidebar);
closeMobileBtn.addEventListener("click", closeMobileSidebar);
backdrop.addEventListener("click", closeMobileSidebar);
window.addEventListener("resize", () => {
  if (window.innerWidth >= 768) closeMobileSidebar();
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeMobileSidebar();
    closeImportModal();
  }
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s") {
    e.preventDefault();
    if (!saveBtn.disabled) {
      saveMarklist();
    }
  }
});
openImportModalBtn.addEventListener("click", openImportModal);
closeImportModalBtn.addEventListener("click", closeImportModal);
importBackdrop.addEventListener("click", closeImportModal);
weightsToggleBtn.addEventListener("click", () => {
  const collapsed = !weightsPanel.classList.contains("hidden");
  setWeightsPanelState(collapsed);
});
setWeightsPanelState(true);

initDesktopSidebar();

const bodyEl = document.getElementById("marklist_body");
const workspaceEl = document.getElementById("marklist_workspace");
const recentPanelEl = document.getElementById("recent_panel");
const recentListEl = document.getElementById("recent_list");
const loadBtn = document.getElementById("load_btn");
const saveBtn = document.getElementById("save_btn");
const exportCurrentBtn = document.getElementById("export_current_btn");
const importFileEl = document.getElementById("import_csv_file");
const importPreviewBtn = document.getElementById("import_preview_btn");
const classEl = document.getElementById("class_id");
const courseEl = document.getElementById("course_id");
const termEl = document.getElementById("term_id");
let classCourseMap = {};
const contextInfoEl = document.getElementById("context_info");
let currentContextVersion = "";
let baselineSignature = "";
let isDirty = false;
let isSaving = false;
let marklistLoaded = false;
let isLoadingMarklist = false;
const draftStoragePrefix = "teacher_marklist_draft_v1_<?= (int)$teacherId ?>";
let draftSaveTimer = null;
const shouldAutoLoad = new URLSearchParams(window.location.search).get("autoload") === "1";
const bootstrapCacheKey = "teacher_marklist_bootstrap_v1";
const bootstrapCacheTtlMs = 5 * 60 * 1000;
const marklistContextCache = new Map();
let inFlightLoadController = null;

const weightInputs = {
  book_weight: document.getElementById("w_book"),
  assignment_weight: document.getElementById("w_assignment"),
  quiz_weight: document.getElementById("w_quiz"),
  mid_exam_weight: document.getElementById("w_mid"),
  final_exam_weight: document.getElementById("w_final"),
  attendance_weight: document.getElementById("w_attendance")
};

function showStatus(message, type = "info") {
  const box = document.getElementById("status_box");
  box.classList.remove("hidden", "bg-red-50", "text-red-700", "border-red-200", "bg-emerald-50", "text-emerald-700", "border-emerald-200", "bg-blue-50", "text-blue-700", "border-blue-200", "border");
  if (type === "error") box.classList.add("bg-red-50", "text-red-700", "border-red-200", "border");
  else if (type === "success") box.classList.add("bg-emerald-50", "text-emerald-700", "border-emerald-200", "border");
  else box.classList.add("bg-blue-50", "text-blue-700", "border-blue-200", "border");
  box.textContent = message;
}

function setLoadState(loading) {
  isLoadingMarklist = !!loading;
  loadBtn.disabled = isLoadingMarklist;
  classEl.disabled = isLoadingMarklist;
  courseEl.disabled = isLoadingMarklist;
  termEl.disabled = isLoadingMarklist;
  exportCurrentBtn.disabled = isLoadingMarklist;
  openImportModalBtn.disabled = isLoadingMarklist;
}

function showContextInfo(message = "", type = "warning") {
  if (!message) {
    contextInfoEl.classList.add("hidden");
    contextInfoEl.textContent = "";
    return;
  }
  contextInfoEl.classList.remove("hidden", "bg-amber-50", "text-amber-800", "border-amber-200", "bg-slate-100", "text-slate-700", "border-slate-300");
  if (type === "neutral") {
    contextInfoEl.classList.add("bg-slate-100", "text-slate-700", "border-slate-300");
  } else {
    contextInfoEl.classList.add("bg-amber-50", "text-amber-800", "border-amber-200");
  }
  contextInfoEl.textContent = message;
}

function setDirtyState(nextDirty) {
  isDirty = !!nextDirty;
  const hasRows = bodyEl.querySelectorAll("tr[data-student-id]").length > 0;
  if (isSaving) {
    saveBtn.disabled = true;
    return;
  }
  saveBtn.disabled = !hasRows || !isDirty;
}

function markDirty() {
  if (!marklistLoaded) return;
  setDirtyState(true);
  scheduleDraftSave();
}

function hasCourseAccessForClass(classId, courseId) {
  const list = classCourseMap[String(classId)] || [];
  return list.some((item) => parseInt(item.id, 10) === parseInt(courseId, 10));
}

function num(v) {
  const n = parseFloat(v);
  return Number.isFinite(n) ? n : 0;
}

function getWeights() {
  return {
    book_weight: Math.max(0, num(weightInputs.book_weight.value)),
    assignment_weight: Math.max(0, num(weightInputs.assignment_weight.value)),
    quiz_weight: Math.max(0, num(weightInputs.quiz_weight.value)),
    mid_exam_weight: Math.max(0, num(weightInputs.mid_exam_weight.value)),
    final_exam_weight: Math.max(0, num(weightInputs.final_exam_weight.value)),
    attendance_weight: Math.max(0, num(weightInputs.attendance_weight.value))
  };
}

function setWeights(weights) {
  if (!weights) return;
  for (const [k, input] of Object.entries(weightInputs)) {
    if (weights[k] !== undefined && weights[k] !== null) input.value = Number(weights[k]).toFixed(2);
  }
  refreshWeightHint();
}

function refreshWeightHint() {
  const w = getWeights();
  const sum = w.book_weight + w.assignment_weight + w.quiz_weight + w.mid_exam_weight + w.final_exam_weight + w.attendance_weight;
  document.getElementById("weight_hint").textContent = `Configured max total: ${sum.toFixed(2)}`;
}

function calculateWeightedTotalFromRow(tr) {
  const w = getWeights();
  const caps = {
    book: w.book_weight,
    assignment: w.assignment_weight,
    quiz: w.quiz_weight,
    mid_exam: w.mid_exam_weight,
    final_exam: w.final_exam_weight,
    attendance: w.attendance_weight
  };
  const bookIn = tr.querySelector(".book");
  const assignmentIn = tr.querySelector(".assignment");
  const quizIn = tr.querySelector(".quiz");
  const midIn = tr.querySelector(".mid");
  const finalIn = tr.querySelector(".final");
  const attendanceIn = tr.querySelector(".attendance");
  const inputs = [
    [bookIn, caps.book],
    [assignmentIn, caps.assignment],
    [quizIn, caps.quiz],
    [midIn, caps.mid_exam],
    [finalIn, caps.final_exam],
    [attendanceIn, caps.attendance]
  ];
  inputs.forEach(([input, max]) => {
    input.max = String(max);
    let v = num(input.value);
    if (v < 0) v = 0;
    if (v > max) v = max;
    if (String(input.value).trim() !== "" && Math.abs(v - num(input.value)) > 0.0001) {
      input.value = v.toFixed(2);
    }
  });
  const scores = {
    book: Math.max(0, Math.min(caps.book, num(bookIn.value))),
    assignment: Math.max(0, Math.min(caps.assignment, num(assignmentIn.value))),
    quiz: Math.max(0, Math.min(caps.quiz, num(quizIn.value))),
    mid_exam: Math.max(0, Math.min(caps.mid_exam, num(midIn.value))),
    final_exam: Math.max(0, Math.min(caps.final_exam, num(finalIn.value))),
    attendance: Math.max(0, Math.min(caps.attendance, num(attendanceIn.value)))
  };
  const total = scores.book + scores.assignment + scores.quiz + scores.mid_exam + scores.final_exam + scores.attendance;
  tr.querySelector(".total").value = total.toFixed(2);
}

function recalcAllRows() {
  bodyEl.querySelectorAll("tr[data-student-id]").forEach(calculateWeightedTotalFromRow);
}

function collectRows() {
  return Array.from(bodyEl.querySelectorAll("tr[data-student-id]")).map((tr) => ({
    student_id: parseInt(tr.getAttribute("data-student-id"), 10),
    is_finalized: tr.getAttribute("data-finalized") === "1",
    book_mark: tr.querySelector(".book").value,
    assignment_mark: tr.querySelector(".assignment").value,
    quiz_mark: tr.querySelector(".quiz").value,
    mid_exam_mark: tr.querySelector(".mid").value,
    final_exam_mark: tr.querySelector(".final").value,
    attendance_percent: tr.querySelector(".attendance").value,
    remark: tr.querySelector(".remark").value.trim()
  }));
}

function getCurrentSignature() {
  const rows = collectRows().map((row) => ({
    student_id: row.student_id,
    is_finalized: row.is_finalized ? 1 : 0,
    book_mark: row.book_mark,
    assignment_mark: row.assignment_mark,
    quiz_mark: row.quiz_mark,
    mid_exam_mark: row.mid_exam_mark,
    final_exam_mark: row.final_exam_mark,
    attendance_percent: row.attendance_percent,
    remark: row.remark
  }));
  return JSON.stringify({
    class_id: classEl.value || "",
    course_id: courseEl.value || "",
    term_id: termEl.value || "",
    weights: getWeights(),
    rows
  });
}

function onEditorChange(tr = null) {
  if (tr) {
    calculateWeightedTotalFromRow(tr);
  } else {
    recalcAllRows();
  }
  markDirty();
}

function confirmDiscardIfDirty() {
  if (!isDirty) return true;
  return window.confirm("You have unsaved changes. Continue and discard them?");
}

function optionLabel(item, kind) {
  if (kind === "class") return `${item.name} (${item.grade}${item.section ? " - " + item.section : ""})`;
  if (kind === "course") return `${item.name} (${item.code})`;
  return item.name;
}

function fillSelect(selectEl, data, kind) {
  selectEl.innerHTML = "";
  if (!data || data.length === 0) {
    const op = document.createElement("option");
    op.value = "";
    op.textContent = "No options";
    selectEl.appendChild(op);
    return;
  }
  for (const item of data) {
    const op = document.createElement("option");
    op.value = item.id;
    op.textContent = optionLabel(item, kind);
    selectEl.appendChild(op);
  }
}

function trySelectByValue(selectEl, value) {
  if (!value) return;
  const exists = Array.from(selectEl.options).some(op => parseInt(op.value, 10) === value);
  if (exists) selectEl.value = String(value);
}

function populateCoursesForClass(classId, preferredCourseId = 0) {
  const list = classCourseMap[String(classId)] || [];
  fillSelect(courseEl, list, "course");
  if (preferredCourseId) {
    trySelectByValue(courseEl, preferredCourseId);
  }
}

function getSelectedContext() {
  const classId = classEl.value;
  const courseId = courseEl.value;
  const termId = termEl.value;
  if (!classId || !courseId || !termId) {
    showStatus("Class, assigned course, and term are required.", "error");
    return null;
  }
  if (!hasCourseAccessForClass(classId, courseId)) {
    showStatus("Selected course is not assigned for this class/teacher.", "error");
    return null;
  }
  return { classId, courseId, termId };
}

function getContextSilently() {
  const classId = classEl.value;
  const courseId = courseEl.value;
  const termId = termEl.value;
  if (!classId || !courseId || !termId) return null;
  if (!hasCourseAccessForClass(classId, courseId)) return null;
  return { classId, courseId, termId };
}

function getDraftKey(ctx) {
  return `${draftStoragePrefix}_${ctx.classId}_${ctx.courseId}_${ctx.termId}`;
}

function getContextKey(classId, courseId, termId) {
  return `${classId}_${courseId}_${termId}`;
}

function readBootstrapCache() {
  try {
    const raw = sessionStorage.getItem(bootstrapCacheKey);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (!parsed || !parsed.ts || !parsed.data) return null;
    if ((Date.now() - Number(parsed.ts)) > bootstrapCacheTtlMs) return null;
    return parsed.data;
  } catch (e) {
    return null;
  }
}

function writeBootstrapCache(data) {
  try {
    sessionStorage.setItem(bootstrapCacheKey, JSON.stringify({ ts: Date.now(), data }));
  } catch (e) {
    // ignore storage failures
  }
}

function resetWorkspacePrompt(message = "Select class/course/term and click \"Load Marklist\".") {
  marklistLoaded = false;
  workspaceEl.classList.add("hidden");
  recentPanelEl.classList.remove("hidden");
  bodyEl.innerHTML = `<tr><td class="px-3 py-4 text-gray-500" colspan="10">${message}</td></tr>`;
  baselineSignature = "";
  setDirtyState(false);
}

function collectDraftRows() {
  return Array.from(bodyEl.querySelectorAll("tr[data-student-id]")).map((tr) => ({
    student_id: parseInt(tr.getAttribute("data-student-id"), 10),
    is_finalized: tr.getAttribute("data-finalized") === "1",
    book_mark: tr.querySelector(".book")?.value ?? "",
    assignment_mark: tr.querySelector(".assignment")?.value ?? "",
    quiz_mark: tr.querySelector(".quiz")?.value ?? "",
    mid_exam_mark: tr.querySelector(".mid")?.value ?? "",
    final_exam_mark: tr.querySelector(".final")?.value ?? "",
    attendance_percent: tr.querySelector(".attendance")?.value ?? "",
    remark: tr.querySelector(".remark")?.value?.trim?.() ?? ""
  }));
}

function scheduleDraftSave() {
  if (!marklistLoaded) return;
  if (draftSaveTimer) {
    clearTimeout(draftSaveTimer);
  }
  draftSaveTimer = setTimeout(() => {
    saveDraftNow();
  }, 700);
}

function saveDraftNow() {
  const ctx = getContextSilently();
  if (!ctx || !marklistLoaded) return;
  const payload = {
    saved_at: Date.now(),
    weights: getWeights(),
    rows: collectDraftRows()
  };
  try {
    localStorage.setItem(getDraftKey(ctx), JSON.stringify(payload));
  } catch (e) {
    // Ignore quota/storage errors; marklist still works.
  }
}

function clearDraftForCurrentContext() {
  const ctx = getContextSilently();
  if (!ctx) return;
  try {
    localStorage.removeItem(getDraftKey(ctx));
  } catch (e) {
    // ignore
  }
}

function applyDraftRows(payloadRows) {
  const byId = new Map();
  (payloadRows || []).forEach((r) => byId.set(parseInt(r.student_id, 10), r));
  let applied = 0;
  bodyEl.querySelectorAll("tr[data-student-id]").forEach((tr) => {
    if (tr.getAttribute("data-finalized") === "1") return;
    const sid = parseInt(tr.getAttribute("data-student-id"), 10);
    const src = byId.get(sid);
    if (!src) return;
    tr.querySelector(".book").value = src.book_mark ?? "";
    tr.querySelector(".assignment").value = src.assignment_mark ?? "";
    tr.querySelector(".quiz").value = src.quiz_mark ?? "";
    tr.querySelector(".mid").value = src.mid_exam_mark ?? "";
    tr.querySelector(".final").value = src.final_exam_mark ?? "";
    tr.querySelector(".attendance").value = src.attendance_percent ?? "";
    tr.querySelector(".remark").value = src.remark ?? "";
    calculateWeightedTotalFromRow(tr);
    applied++;
  });
  return applied;
}

function renderRecentMarklists(list) {
  if (!Array.isArray(list) || list.length === 0) {
    recentListEl.textContent = "No recent sessions yet.";
    return;
  }
  recentListEl.innerHTML = list.map((item) => {
    const classLabel = `${item.class_name} (${item.grade}${item.section ? " - " + item.section : ""})`;
    const courseLabel = `${item.course_name}${item.course_code ? " (" + item.course_code + ")" : ""}`;
    const termLabel = item.term_name || "Term";
    const last = item.last_updated ? new Date(item.last_updated.replace(" ", "T")).toLocaleString() : "";
    return `
      <div class="flex items-start justify-between gap-3 py-2 border-b border-slate-100 last:border-b-0">
        <div>
          <p class="text-sm font-medium text-slate-800">${classLabel}</p>
          <p class="text-xs text-slate-600">${courseLabel} | ${termLabel}</p>
          <p class="text-[11px] text-slate-500 mt-0.5">Rows: ${Number(item.row_count || 0)}${last ? " | Last: " + last : ""}</p>
        </div>
        <button type="button" class="open-recent bg-slate-800 hover:bg-slate-900 text-white text-xs rounded px-2 py-1"
          data-class-id="${item.class_id}" data-course-id="${item.course_id}" data-term-id="${item.term_id}">
          Open
        </button>
      </div>
    `;
  }).join("");
}

function maybeRestoreDraft() {
  const ctx = getContextSilently();
  if (!ctx || !marklistLoaded) return;
  let payload = null;
  try {
    payload = JSON.parse(localStorage.getItem(getDraftKey(ctx)) || "null");
  } catch (e) {
    payload = null;
  }
  if (!payload || !Array.isArray(payload.rows) || payload.rows.length === 0) return;

  const when = payload.saved_at ? new Date(payload.saved_at).toLocaleString() : "recently";
  const ok = window.confirm(`Unsaved draft found for this class/course/term (${when}). Restore it?`);
  if (!ok) return;

  if (payload.weights && typeof payload.weights === "object") {
    setWeights(payload.weights);
  }
  const applied = applyDraftRows(payload.rows);
  if (applied > 0) {
    setDirtyState(true);
    showStatus(`Draft restored (${applied} rows). Click Save to commit.`, "success");
  }
}

function buildCsvActionUrl(action, ctx) {
  const query = new URLSearchParams({
    action,
    class_id: ctx.classId,
    course_id: ctx.courseId,
    term_id: ctx.termId
  });
  return `../api/marklist.php?${query.toString()}`;
}

function applyImportedRows(importRows) {
  const rowMap = new Map();
  importRows.forEach((r) => rowMap.set(parseInt(r.student_id, 10), r));
  let applied = 0;
  bodyEl.querySelectorAll("tr[data-student-id]").forEach((tr) => {
    if (tr.getAttribute("data-finalized") === "1") return;
    const sid = parseInt(tr.getAttribute("data-student-id"), 10);
    const src = rowMap.get(sid);
    if (!src) return;
    tr.querySelector(".book").value = src.book_mark ?? "";
    tr.querySelector(".assignment").value = src.assignment_mark ?? "";
    tr.querySelector(".quiz").value = src.quiz_mark ?? "";
    tr.querySelector(".mid").value = src.mid_exam_mark ?? "";
    tr.querySelector(".final").value = src.final_exam_mark ?? "";
    tr.querySelector(".attendance").value = src.attendance_percent ?? "";
    tr.querySelector(".remark").value = src.remark ?? "";
    calculateWeightedTotalFromRow(tr);
    applied++;
  });
  if (applied > 0) {
    markDirty();
  }
  return applied;
}

async function bootstrap() {
  const applyBootstrapData = (data) => {
    classCourseMap = data.class_course_map || {};
    renderRecentMarklists(data.recent_marklists || []);
    fillSelect(classEl, data.classes || [], "class");
    fillSelect(termEl, data.terms || [], "term");
    trySelectByValue(classEl, preselect.class_id);
    populateCoursesForClass(classEl.value, preselect.course_id);
    trySelectByValue(termEl, preselect.term_id);
    classEl.dataset.prev = classEl.value || "";
    courseEl.dataset.prev = courseEl.value || "";
    termEl.dataset.prev = termEl.value || "";
  };

  const cached = readBootstrapCache();
  if (cached) {
    applyBootstrapData(cached);
    resetWorkspacePrompt();
  }

  let loadedFresh = false;
  try {
    const res = await fetch("../api/marklist.php?action=bootstrap");
    const data = await res.json();
    if (!data.success) throw new Error(data.message || "Failed to load filters");
    applyBootstrapData(data);
    writeBootstrapCache(data);
    loadedFresh = true;
    if (cached) {
      showStatus("Workspace refreshed.", "info");
    }
  } catch (e) {
    if (!cached) {
      throw e;
    }
    showStatus("Using cached workspace data. Refresh page if assignments changed.", "info");
  }

  if (!cached || loadedFresh) {
    resetWorkspacePrompt();
  }
  if (shouldAutoLoad && classEl.value && courseEl.value && termEl.value) {
    loadMarklist();
  }
}

function renderRows(students) {
  workspaceEl.classList.remove("hidden");
  recentPanelEl.classList.add("hidden");
  if (!students.length) {
    bodyEl.innerHTML = `<tr><td class="px-3 py-4 text-gray-500" colspan="10">No active students in this class.</td></tr>`;
    marklistLoaded = false;
    setDirtyState(false);
    return;
  }

  let finalizedRows = 0;
  const w = getWeights();
  bodyEl.innerHTML = students.map((s, idx) => `
    <tr data-student-id="${s.student_id}" data-finalized="${s.is_finalized ? 1 : 0}" class="${s.is_finalized ? "bg-slate-50" : ""}">
      <td class="px-3 py-2 text-gray-500">${idx + 1}</td>
      <td class="px-3 py-2 font-medium text-gray-800">${s.full_name}${s.is_finalized ? ' <span class="ml-1 text-[10px] px-2 py-0.5 rounded bg-slate-200 text-slate-700">Finalized</span>' : ''}</td>
      <td class="px-3 py-2"><input type="number" min="0" max="${w.book_weight}" step="0.01" class="book w-20 border border-gray-300 rounded px-2 py-1 ${s.is_finalized ? "bg-slate-100" : ""}" value="${s.book_mark ?? ""}" ${s.is_finalized ? "disabled" : ""}></td>
      <td class="px-3 py-2"><input type="number" min="0" max="${w.assignment_weight}" step="0.01" class="assignment w-20 border border-gray-300 rounded px-2 py-1 ${s.is_finalized ? "bg-slate-100" : ""}" value="${s.assignment_mark ?? ""}" ${s.is_finalized ? "disabled" : ""}></td>
      <td class="px-3 py-2"><input type="number" min="0" max="${w.quiz_weight}" step="0.01" class="quiz w-20 border border-gray-300 rounded px-2 py-1 ${s.is_finalized ? "bg-slate-100" : ""}" value="${s.quiz_mark ?? ""}" ${s.is_finalized ? "disabled" : ""}></td>
      <td class="px-3 py-2"><input type="number" min="0" max="${w.mid_exam_weight}" step="0.01" class="mid w-20 border border-gray-300 rounded px-2 py-1 ${s.is_finalized ? "bg-slate-100" : ""}" value="${s.mid_exam_mark ?? ""}" ${s.is_finalized ? "disabled" : ""}></td>
      <td class="px-3 py-2"><input type="number" min="0" max="${w.final_exam_weight}" step="0.01" class="final w-20 border border-gray-300 rounded px-2 py-1 ${s.is_finalized ? "bg-slate-100" : ""}" value="${s.final_exam_mark ?? ""}" ${s.is_finalized ? "disabled" : ""}></td>
      <td class="px-3 py-2"><input type="number" min="0" max="${w.attendance_weight}" step="0.01" class="attendance w-24 border border-gray-300 rounded px-2 py-1 ${s.is_finalized ? "bg-slate-100" : ""}" value="${s.attendance_percent ?? ""}" ${s.is_finalized ? "disabled" : ""}></td>
      <td class="px-3 py-2"><input type="number" class="total w-24 border border-gray-300 rounded px-2 py-1 bg-gray-100" value="${s.total_mark ?? ""}" readonly></td>
      <td class="px-3 py-2"><input type="text" class="remark w-44 border border-gray-300 rounded px-2 py-1 ${s.is_finalized ? "bg-slate-100" : ""}" maxlength="255" value="${s.remark ?? ""}" ${s.is_finalized ? "disabled" : ""}></td>
    </tr>
  `).join("");

  bodyEl.querySelectorAll("tr").forEach((tr) => {
    if (tr.getAttribute("data-finalized") === "1") {
      finalizedRows++;
      calculateWeightedTotalFromRow(tr);
      return;
    }
    calculateWeightedTotalFromRow(tr);
  });

  if (finalizedRows > 0) {
    showContextInfo(`${finalizedRows} row(s) are finalized and locked for editing.`, "warning");
  } else {
    showContextInfo("", "neutral");
  }
  baselineSignature = getCurrentSignature();
  marklistLoaded = true;
  setDirtyState(false);
}

async function loadMarklist() {
  const classId = classEl.value;
  const courseId = courseEl.value;
  const termId = termEl.value;
  if (!classId || !courseId || !termId) {
    showStatus("Class, assigned course, and term are required.", "error");
    return;
  }
  if (!hasCourseAccessForClass(classId, courseId)) {
    showStatus("Selected course is not assigned for this class/teacher.", "error");
    return;
  }
  if (!confirmDiscardIfDirty()) {
    return;
  }
  const contextKey = getContextKey(classId, courseId, termId);
  const cachedContext = marklistContextCache.get(contextKey);
  if (cachedContext && (Date.now() - cachedContext.ts) < 45 * 1000) {
    applyLoadedMarklistData(cachedContext.data);
    showStatus("Loaded from quick cache.", "success");
    return;
  }

  if (inFlightLoadController) {
    inFlightLoadController.abort();
  }
  inFlightLoadController = new AbortController();
  saveBtn.disabled = true;
  setLoadState(true);
  showStatus("Loading marklist...", "info");

  const query = new URLSearchParams({
    action: "students",
    class_id: classId,
    course_id: courseId,
    term_id: termId
  });

  try {
    const res = await fetch(`../api/marklist.php?${query.toString()}`, { signal: inFlightLoadController.signal });
    const data = await res.json();
    if (!data.success) {
      showStatus(data.message || "Unable to load marklist.", "error");
      return;
    }
    marklistContextCache.set(contextKey, { ts: Date.now(), data });
    applyLoadedMarklistData(data);
    showStatus(`Loaded ${data.count || 0} students.`, "success");
  } catch (e) {
    if (e.name === "AbortError") {
      return;
    }
    showStatus("Unable to load marklist.", "error");
  } finally {
    setLoadState(false);
  }
}

function applyLoadedMarklistData(data) {
  currentContextVersion = data.context_version || "";
  setWeights(data.weights || {});
  renderRows(data.students || []);
  maybeRestoreDraft();
  classEl.dataset.prev = classEl.value || "";
  courseEl.dataset.prev = courseEl.value || "";
  termEl.dataset.prev = termEl.value || "";
  if ((data.existing_rows || 0) > 0) {
    showContextInfo(`Existing marklist found: ${data.existing_rows} saved row(s). You are editing existing data.`, "warning");
  } else {
    showContextInfo("", "neutral");
  }
}

async function saveMarklist() {
  if (isSaving) return;
  const classId = classEl.value;
  const courseId = courseEl.value;
  const termId = termEl.value;
  if (!classId || !courseId || !termId) {
    showStatus("Class, assigned course, and term are required.", "error");
    return;
  }
  if (!hasCourseAccessForClass(classId, courseId)) {
    showStatus("Selected course is not assigned for this class/teacher.", "error");
    return;
  }

  const weights = getWeights();
  const weightSum = Object.values(weights).reduce((acc, value) => acc + num(value), 0);
  if (weightSum <= 0) {
    showStatus("Total weight must be greater than 0.", "error");
    return;
  }

  const rows = collectRows().filter((row) => !row.is_finalized).map((row) => ({
    student_id: row.student_id,
    book_mark: row.book_mark,
    assignment_mark: row.assignment_mark,
    quiz_mark: row.quiz_mark,
    mid_exam_mark: row.mid_exam_mark,
    final_exam_mark: row.final_exam_mark,
    attendance_percent: row.attendance_percent,
    remark: row.remark
  }));
  const studentIds = rows.map((row) => row.student_id).filter((v) => Number.isFinite(v) && v > 0);
  const uniqueCount = new Set(studentIds).size;
  if (uniqueCount !== studentIds.length) {
    showStatus("Duplicate student rows detected. Reload and try again.", "error");
    return;
  }
  if (rows.length === 0) {
    showStatus("No editable rows available to save.", "error");
    return;
  }
  if (!isDirty) {
    showStatus("No changes to save.", "info");
    return;
  }

  isSaving = true;
  saveBtn.disabled = true;
  showStatus("Saving marks and weights...", "info");
  const form = new FormData();
  form.append("action", "save_marks");
  form.append("csrf", csrfToken);
  form.append("class_id", classId);
  form.append("course_id", courseId);
  form.append("term_id", termId);
  form.append("context_version", currentContextVersion || "");
  form.append("weights", JSON.stringify(weights));
  form.append("rows", JSON.stringify(rows));

  try {
    const res = await fetch("../api/marklist.php", { method: "POST", body: form });
    const data = await res.json();
    if (!data.success) {
      showStatus(data.message || "Save failed.", "error");
      return;
    }
    if (data.weights) setWeights(data.weights);
    currentContextVersion = data.context_version || currentContextVersion;
    recalcAllRows();
    baselineSignature = getCurrentSignature();
    setDirtyState(false);
    marklistContextCache.delete(getContextKey(classId, courseId, termId));
    clearDraftForCurrentContext();
    const auditPart = Number.isFinite(Number(data.audit_rows)) ? `, audit ${data.audit_rows}` : "";
    showStatus(`${data.message} (${data.affected_rows} rows${auditPart})`, "success");
  } finally {
    isSaving = false;
    if (isDirty) {
      saveBtn.disabled = false;
    }
  }
}

Object.values(weightInputs).forEach((input) => {
  input.addEventListener("input", () => {
    refreshWeightHint();
    onEditorChange(null);
  });
});
refreshWeightHint();

loadBtn.addEventListener("click", loadMarklist);
saveBtn.addEventListener("click", saveMarklist);
exportCurrentBtn.addEventListener("click", () => {
  const ctx = getSelectedContext();
  if (!ctx) return;
  window.location.href = buildCsvActionUrl("export_marklist_csv", ctx);
});
importPreviewBtn.addEventListener("click", async () => {
  const ctx = getSelectedContext();
  if (!ctx) return;
  if (!importFileEl.files || !importFileEl.files[0]) {
    showStatus("Choose a CSV or Excel file first.", "error");
    return;
  }
  if (!confirmDiscardIfDirty()) {
    return;
  }
  showStatus("Reading import preview...", "info");
  const form = new FormData();
  form.append("action", "import_preview_file");
  form.append("csrf", csrfToken);
  form.append("class_id", ctx.classId);
  form.append("course_id", ctx.courseId);
  form.append("term_id", ctx.termId);
  form.append("import_file", importFileEl.files[0]);

  const res = await fetch("../api/marklist.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) {
    showStatus(data.message || "Import preview failed.", "error");
    return;
  }
  if (!marklistLoaded) {
    await loadMarklist();
  }
  const applied = applyImportedRows(data.rows || []);
  const errors = data.errors || [];
  if (errors.length > 0) {
    showContextInfo(`Import preview warnings (${errors.length}): ${errors.slice(0, 5).join(" | ")}${errors.length > 5 ? " ..." : ""}`, "warning");
  } else {
    showContextInfo("Import preview completed with no row-level warnings.", "neutral");
  }
  showStatus(`Import preview applied to ${applied} rows. Click Save to commit.`, "success");
  closeImportModal();
});
classEl.addEventListener("change", () => {
  const previousClassId = classEl.dataset.prev || "";
  if (!confirmDiscardIfDirty()) {
    classEl.value = previousClassId;
    return;
  }
  populateCoursesForClass(classEl.value, 0);
  classEl.dataset.prev = classEl.value || "";
  courseEl.dataset.prev = courseEl.value || "";
  showContextInfo("", "neutral");
  resetWorkspacePrompt("Class changed. Click \"Load Marklist\" to view students.");
});
courseEl.addEventListener("change", () => {
  const previousCourseId = courseEl.dataset.prev || "";
  if (!confirmDiscardIfDirty()) {
    courseEl.value = previousCourseId;
    return;
  }
  courseEl.dataset.prev = courseEl.value || "";
  if (!hasCourseAccessForClass(classEl.value, courseEl.value)) {
    showStatus("Selected course is not assigned for this class/teacher.", "error");
  }
  showContextInfo("", "neutral");
  resetWorkspacePrompt("Course changed. Click \"Load Marklist\" to view students.");
});
termEl.addEventListener("change", () => {
  const previousTermId = termEl.dataset.prev || "";
  if (!confirmDiscardIfDirty()) {
    termEl.value = previousTermId;
    return;
  }
  termEl.dataset.prev = termEl.value || "";
  showContextInfo("", "neutral");
  resetWorkspacePrompt("Term changed. Click \"Load Marklist\" to view students.");
});
window.addEventListener("beforeunload", (e) => {
  if (isDirty) {
    saveDraftNow();
  }
  if (!isDirty) return;
  e.preventDefault();
  e.returnValue = "";
});

bodyEl.addEventListener("input", (e) => {
  const target = e.target;
  if (!(target instanceof HTMLInputElement)) return;
  const tr = target.closest("tr[data-student-id]");
  if (!tr || tr.getAttribute("data-finalized") === "1") return;
  if (target.classList.contains("book") || target.classList.contains("assignment") || target.classList.contains("quiz") || target.classList.contains("mid") || target.classList.contains("final") || target.classList.contains("attendance")) {
    onEditorChange(tr);
    return;
  }
  if (target.classList.contains("remark")) {
    markDirty();
  }
});

recentListEl.addEventListener("click", async (e) => {
  const btn = e.target.closest(".open-recent");
  if (!btn) return;
  if (!confirmDiscardIfDirty()) return;
  const classId = parseInt(btn.getAttribute("data-class-id"), 10) || 0;
  const courseId = parseInt(btn.getAttribute("data-course-id"), 10) || 0;
  const termId = parseInt(btn.getAttribute("data-term-id"), 10) || 0;
  if (!classId || !courseId || !termId) return;
  trySelectByValue(classEl, classId);
  populateCoursesForClass(classEl.value, courseId);
  trySelectByValue(termEl, termId);
  classEl.dataset.prev = classEl.value || "";
  courseEl.dataset.prev = courseEl.value || "";
  termEl.dataset.prev = termEl.value || "";
  await loadMarklist();
});

bootstrap().catch((err) => showStatus(err.message || "Failed to initialize page.", "error"));
</script>
</body>
</html>




