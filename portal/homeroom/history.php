<?php
require '../../config.php';
require '../../includes/security_helpers.php';
require '../../includes/portal_auth.php';

$ctx = getAuthContext();
if (!$ctx || $ctx['actor'] !== 'portal' || ($ctx['portal_role'] ?? '') !== 'homeroom') {
    header('Location: ../../login.php');
    exit;
}

$teacherId = (int)$ctx['teacher_id'];
$stmt = $pdo->prepare("SELECT full_name, email, phone FROM teachers WHERE id = ? LIMIT 1");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'Homeroom Teacher', 'email' => '', 'phone' => ''];
$teacherName = (string)$teacher['full_name'];

$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.name, c.grade, c.section, c.academic_year
    FROM class_teachers ct
    INNER JOIN classes c ON c.id = ct.class_id
    WHERE ct.teacher_id = ? AND ct.role = 'homeroom' AND ct.is_active = 1
    ORDER BY c.grade, c.section, c.name
");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csrf = SecurityHelper::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homeroom History</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .sidebar-collapsed .sidebar-label { display: none; }
    .sidebar-collapsed #homeroom_sidebar { width: 5.5rem; }
    .sidebar-collapsed .sidebar-user { display: none; }
    .sidebar-collapsed .sidebar-logo-mini { display: block; }
    .sidebar-collapsed .sidebar-logo-full { display: none; }
  </style>
</head>
<body id="homeroom_body" class="bg-slate-100 min-h-screen font-[Manrope]">
<div id="sidebar_backdrop" class="fixed inset-0 bg-slate-900/50 z-30 hidden md:hidden"></div>

<div class="min-h-screen md:h-screen md:flex md:overflow-hidden">
  <aside id="homeroom_sidebar" class="fixed md:sticky md:top-0 top-0 left-0 h-screen w-72 bg-slate-900 text-white p-4 md:p-5 z-40 transform -translate-x-full md:translate-x-0 transition-all duration-200 ease-out md:flex-shrink-0">
    <div class="flex items-center justify-between gap-2">
      <div>
        <p class="text-[11px] uppercase tracking-[0.2em] text-cyan-100/90 sidebar-label">Portal</p>
        <h1 class="font-['Space_Grotesk'] text-2xl mt-1 sidebar-logo-full">Homeroom Hub</h1>
        <h1 class="font-['Space_Grotesk'] text-2xl mt-1 hidden sidebar-logo-mini">HH</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="sidebar_toggle" class="hidden md:inline-flex items-center justify-center w-9 h-9 rounded bg-white/10 hover:bg-white/20 text-white"><<</button>
        <button id="sidebar_close_mobile" class="inline-flex md:hidden items-center justify-center w-9 h-9 rounded bg-white/10 hover:bg-white/20 text-white">X</button>
      </div>
    </div>

    <div class="mt-5 rounded-xl bg-white/10 p-4 sidebar-user sidebar-label">
      <p class="text-xs text-cyan-100/80">Logged In As</p>
      <p class="font-semibold mt-1"><?= htmlspecialchars($teacherName) ?></p>
      <?php if (!empty($teacher['email'])): ?><p class="text-xs text-cyan-100/80 mt-1"><?= htmlspecialchars($teacher['email']) ?></p><?php endif; ?>
      <?php if (!empty($teacher['phone'])): ?><p class="text-xs text-cyan-100/80"><?= htmlspecialchars($teacher['phone']) ?></p><?php endif; ?>
    </div>

    <nav class="mt-6 space-y-2">
      <a href="dashboard.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
        <span class="w-6 text-center">DB</span><span class="sidebar-label">Dashboard</span>
      </a>
      <a href="history.php" class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 text-sm font-medium">
        <span class="w-6 text-center">HS</span><span class="sidebar-label">History</span>
      </a>
    </nav>

    <div class="mt-8">
      <a href="../../logout.php" class="inline-flex items-center bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        <span class="sidebar-label">Logout</span><span class="md:hidden">Logout</span>
      </a>
    </div>
  </aside>

  <main class="flex-1 p-4 md:p-7 md:h-screen md:overflow-y-auto min-w-0">
    <div class="flex items-center justify-between mb-3 md:mb-0">
      <button id="sidebar_open_mobile" class="inline-flex md:hidden items-center justify-center bg-slate-900 text-white px-3 py-2 rounded-lg text-sm">Menu</button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-4 md:p-5">
      <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-[260px]">
          <label class="block text-xs font-medium text-slate-700 mb-1">Class Filter (optional)</label>
          <select id="class_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            <option value="0">All My Classes</option>
            <?php foreach ($classes as $class): ?>
              <option value="<?= (int)$class['id'] ?>">
                <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['grade']) ?><?= !empty($class['section']) ? ' - ' . htmlspecialchars($class['section']) : '' ?>, <?= htmlspecialchars((string)$class['academic_year']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button id="load_all_btn" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">Load All History</button>
        <button id="finalize_selected_btn" class="bg-emerald-600 text-white rounded px-4 py-2 text-sm hover:bg-emerald-700">Finalize Selected</button>
        <button id="reopen_selected_btn" class="bg-amber-600 text-white rounded px-4 py-2 text-sm hover:bg-amber-700">Re-open Selected</button>
      </div>
      <div id="status_box" class="mt-3 text-sm text-slate-600"></div>
    </div>

    <div class="mt-4 bg-white rounded-xl border border-slate-200 p-4">
      <div class="overflow-auto border border-slate-200 rounded-xl">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-700"><input id="select_all" type="checkbox"></th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Class</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Term</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Status</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Notes</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Submitted At</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Updated At</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Actions</th>
            </tr>
          </thead>
          <tbody id="history_body" class="divide-y divide-slate-100">
            <tr><td colspan="8" class="px-3 py-4 text-slate-500">Load history to view rows.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div id="detail_modal" class="fixed inset-0 z-50 hidden">
  <div id="detail_backdrop" class="absolute inset-0 bg-black/40"></div>
  <div class="relative mx-auto mt-14 w-[94%] max-w-6xl rounded-lg bg-white border border-slate-200 shadow-xl">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
      <h3 class="font-semibold text-slate-800">Submitted Marklist Table</h3>
      <button id="detail_close_btn" class="text-slate-500 hover:text-slate-800">Close</button>
    </div>
    <div class="p-4 overflow-auto max-h-[72vh]">
      <table class="min-w-full text-xs">
        <thead class="bg-slate-50">
          <tr id="detail_head"></tr>
        </thead>
        <tbody id="detail_body" class="divide-y divide-slate-100"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const csrf = <?= json_encode($csrf) ?>;
const classEl = document.getElementById("class_id");
const statusEl = document.getElementById("status_box");
const bodyEl = document.getElementById("history_body");
const selectAllEl = document.getElementById("select_all");
const detailModalEl = document.getElementById("detail_modal");
const detailHeadEl = document.getElementById("detail_head");
const detailBodyEl = document.getElementById("detail_body");
let historyRows = [];

function setStatus(msg, bad = false) {
  statusEl.textContent = msg;
  statusEl.className = bad ? "mt-3 text-sm text-red-600" : "mt-3 text-sm text-slate-600";
}

function renderHistory(rows) {
  historyRows = Array.isArray(rows) ? rows : [];
  if (!rows.length) {
    bodyEl.innerHTML = `<tr><td colspan="8" class="px-3 py-4 text-slate-500">No matrix history found.</td></tr>`;
    return;
  }
  bodyEl.innerHTML = rows.map((r) => {
    const status = String(r.status || "draft");
    const chip = status === "submitted" ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700";
    const classLabel = `${r.class_name || "-"} (${r.grade || "-"}${r.section ? " - " + r.section : ""}, ${r.academic_year || "-"})`;
    return `
      <tr>
        <td class="px-3 py-2"><input type="checkbox" class="row-check" data-class-id="${Number(r.class_id || 0)}" data-term-id="${Number(r.term_id || 0)}"></td>
        <td class="px-3 py-2">${classLabel}</td>
        <td class="px-3 py-2">${r.term_name || "-"}</td>
        <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-0.5 ${chip}">${status}</span></td>
        <td class="px-3 py-2">${r.notes || "-"}</td>
        <td class="px-3 py-2">${r.submitted_at || "-"}</td>
        <td class="px-3 py-2">${r.updated_at || "-"}</td>
        <td class="px-3 py-2 space-x-1">
          <button class="finalize-btn bg-emerald-600 text-white rounded px-2 py-1 hover:bg-emerald-700" data-class-id="${Number(r.class_id || 0)}" data-term-id="${Number(r.term_id || 0)}">Finalize</button>
          <button class="reopen-btn bg-amber-600 text-white rounded px-2 py-1 hover:bg-amber-700" data-class-id="${Number(r.class_id || 0)}" data-term-id="${Number(r.term_id || 0)}">Re-Submit</button>
          <button class="view-btn bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded px-2 py-1" data-class-id="${Number(r.class_id || 0)}" data-term-id="${Number(r.term_id || 0)}">View Table</button>
        </td>
      </tr>
    `;
  }).join("");
  selectAllEl.checked = false;
}

async function loadHistory() {
  const classId = Number(classEl.value || 0);
  const params = new URLSearchParams({ action: "matrix_history", class_id: String(classId) });
  const res = await fetch(`../api/homeroom.php?${params.toString()}`);
  const data = await res.json();
  if (!data.success) {
    setStatus(data.message || "Failed to load history.", true);
    return;
  }
  renderHistory(data.rows || []);
  setStatus(`Loaded ${data.rows.length} history row(s).`);
}

function selectedRows() {
  return Array.from(document.querySelectorAll(".row-check:checked")).map((el) => ({
    class_id: Number(el.getAttribute("data-class-id") || 0),
    term_id: Number(el.getAttribute("data-term-id") || 0)
  })).filter((x) => x.class_id > 0 && x.term_id > 0);
}

async function finalizeSelected() {
  const rows = selectedRows();
  if (!rows.length) {
    setStatus("Select at least one history row.", true);
    return;
  }
  const ok = confirm(`Finalize ${rows.length} selected summary row(s)?`);
  if (!ok) return;
  for (const row of rows) {
    const form = new FormData();
    form.append("action", "finalize_history");
    form.append("class_id", String(row.class_id));
    form.append("term_id", String(row.term_id));
    form.append("csrf", csrf);
    await fetch("../api/homeroom.php", { method: "POST", body: form });
  }
  setStatus(`Finalized ${rows.length} summary row(s).`);
  await loadHistory();
}

async function reopenSelected() {
  const rows = selectedRows();
  if (!rows.length) {
    setStatus("Select at least one history row.", true);
    return;
  }
  const ok = confirm(`Re-open ${rows.length} selected summary row(s) as draft?`);
  if (!ok) return;
  for (const row of rows) {
    const form = new FormData();
    form.append("action", "reopen_history");
    form.append("class_id", String(row.class_id));
    form.append("term_id", String(row.term_id));
    form.append("csrf", csrf);
    await fetch("../api/homeroom.php", { method: "POST", body: form });
  }
  setStatus(`Re-opened ${rows.length} summary row(s) as draft.`);
  await loadHistory();
}

async function openDetail(classId, termId) {
  const params = new URLSearchParams({ action: "matrix_detail", class_id: String(classId), term_id: String(termId) });
  const res = await fetch(`../api/homeroom.php?${params.toString()}`);
  const data = await res.json();
  if (!data.success) {
    setStatus(data.message || "Failed to load detail table.", true);
    return;
  }
  const courses = data.courses || [];
  const rows = data.rows || [];
  detailHeadEl.innerHTML = `
    <th class="px-2 py-2 text-left">Student</th>
    ${courses.map(c => `<th class="px-2 py-2 text-left">${c.name}</th>`).join("")}
    <th class="px-2 py-2 text-left">Attendance</th>
    <th class="px-2 py-2 text-left">Total</th>
    <th class="px-2 py-2 text-left">Average</th>
  `;
  detailBodyEl.innerHTML = rows.map((r) => `
    <tr>
      <td class="px-2 py-2 font-medium">${r.full_name}</td>
      ${courses.map(c => `<td class="px-2 py-2">${r.course_totals?.[String(c.id)] == null ? "-" : Number(r.course_totals[String(c.id)]).toFixed(2)}</td>`).join("")}
      <td class="px-2 py-2">${Number(r.attendance_percent || 0).toFixed(2)}</td>
      <td class="px-2 py-2">${Number(r.course_total_sum || 0).toFixed(2)}</td>
      <td class="px-2 py-2">${Number(r.term_average || 0).toFixed(2)}</td>
    </tr>
  `).join("");
  detailModalEl.classList.remove("hidden");
}

async function postHistoryAction(action, classId, termId) {
  const form = new FormData();
  form.append("action", action);
  form.append("class_id", String(classId));
  form.append("term_id", String(termId));
  form.append("csrf", csrf);
  const res = await fetch("../api/homeroom.php", { method: "POST", body: form });
  return res.json();
}

function closeDetail() {
  detailModalEl.classList.add("hidden");
}

// Sidebar behavior
const bodyPage = document.getElementById("homeroom_body");
const sidebar = document.getElementById("homeroom_sidebar");
const backdrop = document.getElementById("sidebar_backdrop");
const sidebarKey = "homeroom_sidebar_collapsed";
function applyDesktopSidebarState(collapsed) {
  bodyPage.classList.toggle("sidebar-collapsed", collapsed);
  document.getElementById("sidebar_toggle").textContent = collapsed ? ">>" : "<<";
}
function initSidebar() {
  applyDesktopSidebarState(localStorage.getItem(sidebarKey) === "1");
}
function openMobileSidebar() { sidebar.classList.remove("-translate-x-full"); backdrop.classList.remove("hidden"); }
function closeMobileSidebar() { sidebar.classList.add("-translate-x-full"); backdrop.classList.add("hidden"); }
document.getElementById("sidebar_toggle").addEventListener("click", () => {
  const collapsed = !bodyPage.classList.contains("sidebar-collapsed");
  applyDesktopSidebarState(collapsed);
  localStorage.setItem(sidebarKey, collapsed ? "1" : "0");
});
document.getElementById("sidebar_open_mobile").addEventListener("click", openMobileSidebar);
document.getElementById("sidebar_close_mobile").addEventListener("click", closeMobileSidebar);
backdrop.addEventListener("click", closeMobileSidebar);

document.getElementById("load_all_btn").addEventListener("click", loadHistory);
document.getElementById("finalize_selected_btn").addEventListener("click", finalizeSelected);
document.getElementById("reopen_selected_btn").addEventListener("click", reopenSelected);
selectAllEl.addEventListener("change", () => {
  const checked = !!selectAllEl.checked;
  document.querySelectorAll(".row-check").forEach(el => { el.checked = checked; });
});
bodyEl.addEventListener("click", (e) => {
  const finalizeBtn = e.target.closest(".finalize-btn");
  if (finalizeBtn) {
    const classId = Number(finalizeBtn.getAttribute("data-class-id") || 0);
    const termId = Number(finalizeBtn.getAttribute("data-term-id") || 0);
    if (!classId || !termId) return;
    const ok = confirm("Finalize and submit this class-term matrix for admin term summary?");
    if (!ok) return;
    postHistoryAction("finalize_history", classId, termId).then((data) => {
      if (!data.success) {
        setStatus(data.message || "Failed to finalize row.", true);
        return;
      }
      setStatus(data.message || "Row finalized successfully.");
      loadHistory();
    }).catch(() => setStatus("Network error while finalizing row.", true));
    return;
  }

  const reopenBtn = e.target.closest(".reopen-btn");
  if (reopenBtn) {
    const classId = Number(reopenBtn.getAttribute("data-class-id") || 0);
    const termId = Number(reopenBtn.getAttribute("data-term-id") || 0);
    if (!classId || !termId) return;
    const ok = confirm("Re-open this finalized row for editing and re-submit?");
    if (!ok) return;
    postHistoryAction("reopen_history", classId, termId).then((data) => {
      if (!data.success) {
        setStatus(data.message || "Failed to re-open row.", true);
        return;
      }
      window.location.href = `dashboard.php?class_id=${classId}&term_id=${termId}`;
    }).catch(() => setStatus("Network error while re-opening row.", true));
    return;
  }

  const btn = e.target.closest(".view-btn");
  if (!btn) return;
  openDetail(Number(btn.getAttribute("data-class-id") || 0), Number(btn.getAttribute("data-term-id") || 0));
});
document.getElementById("detail_close_btn").addEventListener("click", closeDetail);
document.getElementById("detail_backdrop").addEventListener("click", closeDetail);
classEl.addEventListener("change", loadHistory);

initSidebar();
loadHistory();
</script>
</body>
</html>
