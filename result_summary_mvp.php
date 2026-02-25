<?php
require 'config.php';
require 'includes/admin_layout.php';
require 'includes/security_helpers.php';
requireAdminLogin();

$csrf = SecurityHelper::generateCSRFToken();

ob_start();
?>
<div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-4">
  <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
    <h2 class="text-lg font-semibold text-gray-800 mb-3">Result Summary MVP (Phase 4)</h2>
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
      <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Class</label>
        <select id="class_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Term</label>
        <select id="term_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></select>
      </div>
      <div class="sm:col-span-2 flex items-end gap-2">
        <button id="refresh_all_btn" class="js-action bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">Refresh Current View</button>
      </div>
    </div>
    <div class="mt-3 flex flex-wrap gap-2 text-xs">
      <span id="context_badge" class="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 text-blue-700 px-3 py-1"></span>
      <span id="homeroom_status_badge" class="inline-flex items-center rounded-full bg-slate-100 border border-slate-300 text-slate-700 px-3 py-1">Homeroom: checking...</span>
      <span id="perf_badge" class="inline-flex items-center rounded-full bg-slate-100 border border-slate-300 text-slate-700 px-3 py-1">Last action: -</span>
    </div>
    <div id="status_box" class="mt-3 text-sm text-gray-700"></div>
  </div>

  <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
    <div class="flex flex-wrap gap-2 mb-4">
      <button id="tab_term_btn" class="tab-btn px-3 py-1.5 rounded bg-blue-600 text-white text-xs">Term Summary</button>
      <button id="tab_course_btn" class="tab-btn px-3 py-1.5 rounded bg-slate-200 text-slate-700 text-xs">Courses + Teachers</button>
      <button id="tab_readiness_btn" class="tab-btn px-3 py-1.5 rounded bg-slate-200 text-slate-700 text-xs">Readiness</button>
      <button id="tab_yearly_btn" class="tab-btn px-3 py-1.5 rounded bg-slate-200 text-slate-700 text-xs">Yearly Promotion</button>
      <button id="tab_homeroom_btn" class="tab-btn px-3 py-1.5 rounded bg-slate-200 text-slate-700 text-xs">Homeroom Submitted</button>
    </div>

    <div id="tab_term" class="tab-panel">
      <div class="mb-3 text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded px-3 py-2">
        Read-only view: term results are finalized and submitted by homeroom teachers.
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Rank</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Student</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Subjects</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Total</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Average</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Attendance %</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Finalized</th>
            </tr>
          </thead>
          <tbody id="term_rows" class="divide-y divide-gray-100">
            <tr><td colspan="7" class="px-3 py-4 text-gray-500">Load summary to view rows.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab_course" class="tab-panel hidden">
      <div class="mb-3 flex flex-wrap gap-2">
        <button id="course_load_btn" class="js-action bg-gray-700 text-white rounded px-3 py-2 text-xs hover:bg-gray-800">Load Course View</button>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Course</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Code</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Teacher</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Entered Rows</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Total Marks</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Average Mark</th>
            </tr>
          </thead>
          <tbody id="course_rows" class="divide-y divide-gray-100">
            <tr><td colspan="6" class="px-3 py-4 text-gray-500">Load course view to see rows.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab_readiness" class="tab-panel hidden">
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <button id="readiness_load_btn" class="js-action bg-teal-600 text-white rounded px-3 py-2 text-xs hover:bg-teal-700">Load Readiness</button>
        <button id="readiness_filter_ready_btn" class="bg-emerald-600 text-white rounded px-3 py-2 text-xs hover:bg-emerald-700">Only Ready For Admin</button>
        <span id="readiness_count_ready" class="inline-flex items-center rounded-full bg-emerald-100 border border-emerald-300 text-emerald-700 px-2 py-1 text-xs">0</span>
        <button id="readiness_filter_homeroom_btn" class="bg-cyan-600 text-white rounded px-3 py-2 text-xs hover:bg-cyan-700">Only Needs Homeroom</button>
        <span id="readiness_count_homeroom" class="inline-flex items-center rounded-full bg-cyan-100 border border-cyan-300 text-cyan-700 px-2 py-1 text-xs">0</span>
        <button id="readiness_filter_updates_btn" class="bg-indigo-600 text-white rounded px-3 py-2 text-xs hover:bg-indigo-700">Only New Teacher Updates</button>
        <span id="readiness_count_updates" class="inline-flex items-center rounded-full bg-indigo-100 border border-indigo-300 text-indigo-700 px-2 py-1 text-xs">0</span>
        <button id="readiness_filter_clear_btn" class="bg-slate-200 text-slate-700 rounded px-3 py-2 text-xs hover:bg-slate-300">Clear Filter</button>
        <span id="readiness_count_total" class="inline-flex items-center rounded-full bg-slate-100 border border-slate-300 text-slate-700 px-2 py-1 text-xs">Total: 0</span>
        <span id="readiness_summary_badge" class="inline-flex items-center rounded-full bg-slate-100 border border-slate-300 text-slate-700 px-3 py-1 text-xs">Readiness: -</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Class</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Students</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Teacher Progress</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Homeroom</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Finalized Rows</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Pipeline State</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Teacher Updated</th>
            </tr>
          </thead>
          <tbody id="readiness_rows" class="divide-y divide-gray-100">
            <tr><td colspan="7" class="px-3 py-4 text-gray-500">Load readiness to view rows.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab_yearly" class="tab-panel hidden">
      <div class="mb-3 flex flex-wrap items-center gap-2 md:gap-3">
        <div class="flex flex-wrap items-center gap-2 rounded border border-slate-200 bg-slate-50 px-2 py-2">
          <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Summary</span>
          <button id="yearly_recalc_btn" class="js-action bg-blue-600 text-white rounded px-3 py-2 text-xs hover:bg-blue-700">Recalculate Yearly</button>
          <button id="yearly_load_btn" class="js-action bg-gray-700 text-white rounded px-3 py-2 text-xs hover:bg-gray-800">Load Yearly</button>
          <button id="yearly_finalize_btn" class="js-action bg-emerald-600 text-white rounded px-3 py-2 text-xs hover:bg-emerald-700">Finalize Yearly</button>
        </div>
        <div class="flex flex-wrap items-center gap-2 rounded border border-slate-200 bg-slate-50 px-2 py-2">
          <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Decision</span>
          <select id="yearly_decision_select" class="border border-slate-300 rounded px-3 py-2 text-xs bg-white">
            <option value="pass">Pass</option>
            <option value="fail">Fail</option>
            <option value="pending">Pending</option>
          </select>
          <button id="yearly_apply_decision_btn" class="js-action bg-sky-600 text-white rounded px-3 py-2 text-xs hover:bg-sky-700">Apply Decision (Selected)</button>
        </div>
        <div class="flex flex-wrap items-center gap-2 rounded border border-slate-200 bg-slate-50 px-2 py-2">
          <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Promotion</span>
          <button id="yearly_promote_selected_btn" class="js-action bg-fuchsia-700 text-white rounded px-3 py-2 text-xs hover:bg-fuchsia-800">Promote Selected</button>
          <button id="yearly_promote_all_pass_btn" class="js-action bg-indigo-700 text-white rounded px-3 py-2 text-xs hover:bg-indigo-800">Promote All Pass</button>
        </div>
      </div>
      <div class="mb-3 text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded px-3 py-2">
        Promotion updates only <span class="font-semibold">pass</span> students. <span class="font-semibold">fail</span> and <span class="font-semibold">pending</span> are not changed.
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-gray-700"><input id="yearly_select_all" type="checkbox"></th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Rank</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Student</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Terms</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Year Total</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Year Average</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Decision</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Finalized</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Promote</th>
            </tr>
          </thead>
          <tbody id="yearly_rows" class="divide-y divide-gray-100">
            <tr><td colspan="9" class="px-3 py-4 text-gray-500">Load yearly summary to view rows.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab_homeroom" class="tab-panel hidden">
      <div class="mb-3 flex flex-wrap gap-2">
        <button id="homeroom_load_btn" class="js-action bg-indigo-600 text-white rounded px-3 py-2 text-xs hover:bg-indigo-700">Load Class+Term</button>
        <button id="homeroom_load_term_btn" class="js-action bg-indigo-500 text-white rounded px-3 py-2 text-xs hover:bg-indigo-600">Load All In Term</button>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-gray-700"><input id="homeroom_select_all" type="checkbox"></th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Class</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Term</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Teacher</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Status</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Notes</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Submitted</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Reviewed</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Action</th>
            </tr>
          </thead>
          <tbody id="homeroom_rows" class="divide-y divide-gray-100">
            <tr><td colspan="9" class="px-3 py-4 text-gray-500">Load homeroom submissions to view rows.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="mt-3">
        <button id="homeroom_mark_reviewed_btn" class="js-action bg-emerald-600 text-white rounded px-3 py-2 text-xs hover:bg-emerald-700">Mark Selected Reviewed</button>
      </div>
    </div>
  </div>
</div>

<div id="homeroom_view_modal" class="fixed inset-0 z-50 hidden">
  <div id="homeroom_view_backdrop" class="absolute inset-0 bg-black/40"></div>
  <div class="relative mx-auto mt-16 w-[92%] max-w-2xl rounded-lg bg-white border border-slate-200 shadow-xl">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
      <h3 class="font-semibold text-slate-800">Homeroom Submission Details</h3>
      <button id="homeroom_view_close_btn" class="text-slate-500 hover:text-slate-800">Close</button>
    </div>
    <div id="homeroom_view_content" class="p-4 text-sm text-slate-700 whitespace-pre-wrap"></div>
  </div>
</div>

<script>
const csrf = <?= json_encode($csrf) ?>;
const classEl = document.getElementById("class_id");
const termEl = document.getElementById("term_id");
const contextBadgeEl = document.getElementById("context_badge");
const homeroomStatusBadgeEl = document.getElementById("homeroom_status_badge");
const perfBadgeEl = document.getElementById("perf_badge");
const statusEl = document.getElementById("status_box");
const termRowsEl = document.getElementById("term_rows");
const courseRowsEl = document.getElementById("course_rows");
const readinessRowsEl = document.getElementById("readiness_rows");
const yearlyRowsEl = document.getElementById("yearly_rows");
const homeroomRowsEl = document.getElementById("homeroom_rows");
const readinessSummaryBadgeEl = document.getElementById("readiness_summary_badge");
const readinessCountReadyEl = document.getElementById("readiness_count_ready");
const readinessCountHomeroomEl = document.getElementById("readiness_count_homeroom");
const readinessCountUpdatesEl = document.getElementById("readiness_count_updates");
const readinessCountTotalEl = document.getElementById("readiness_count_total");
const tabPanels = {
  term: document.getElementById("tab_term"),
  course: document.getElementById("tab_course"),
  readiness: document.getElementById("tab_readiness"),
  yearly: document.getElementById("tab_yearly"),
  homeroom: document.getElementById("tab_homeroom"),
};
const tabButtons = {
  term: document.getElementById("tab_term_btn"),
  course: document.getElementById("tab_course_btn"),
  readiness: document.getElementById("tab_readiness_btn"),
  yearly: document.getElementById("tab_yearly_btn"),
  homeroom: document.getElementById("tab_homeroom_btn"),
};
const yearlySelectAllEl = document.getElementById("yearly_select_all");
const homeroomSelectAllEl = document.getElementById("homeroom_select_all");
const homeroomViewModalEl = document.getElementById("homeroom_view_modal");
const homeroomViewContentEl = document.getElementById("homeroom_view_content");

let activeTab = "term";
let homeroomRowsCache = [];
let readinessRowsCache = [];
let readinessFilterMode = "all";
let latestHomeroomStatus = "none";
const activeControllers = {};
const fastCache = new Map();
const fastCacheTTL = 15000;
let busyDepth = 0;

function endpoint(params) {
  return `api/result_summary_mvp.php?${new URLSearchParams(params).toString()}`;
}

function contextCacheKey(kind) {
  return `${kind}|c:${classEl.value}|t:${termEl.value}`;
}

function setFastCache(kind, value) {
  fastCache.set(contextCacheKey(kind), { ts: Date.now(), value });
}

function getFastCache(kind) {
  const item = fastCache.get(contextCacheKey(kind));
  if (!item) return null;
  if ((Date.now() - item.ts) > fastCacheTTL) return null;
  return item.value;
}

function clearContextCaches() {
  ["term", "course", "readiness", "yearly", "homeroom_status", "homeroom_class_term", "homeroom_term"].forEach((kind) => {
    fastCache.delete(contextCacheKey(kind));
  });
}

function setPerf(label, ms) {
  const timeTxt = typeof ms === "number" ? `${Math.round(ms)} ms` : "-";
  perfBadgeEl.textContent = `Last action: ${label} (${timeTxt})`;
}

async function fetchJson(url, channel, useAbort = true) {
  const t0 = performance.now();
  let signal = undefined;
  if (useAbort) {
    if (activeControllers[channel]) activeControllers[channel].abort();
    activeControllers[channel] = new AbortController();
    signal = activeControllers[channel].signal;
  }
  const res = await fetch(url, signal ? { signal } : undefined);
  const data = await res.json();
  const dt = performance.now() - t0;
  return { data, dt };
}

function setStatus(msg, bad = false) {
  statusEl.textContent = msg;
  statusEl.className = bad ? "mt-3 text-sm text-red-600" : "mt-3 text-sm text-gray-700";
}

function selectedLabel(selectEl) {
  if (!selectEl || selectEl.selectedIndex < 0) return "";
  return selectEl.options[selectEl.selectedIndex]?.textContent?.trim() || "";
}

function updateContextBadge() {
  contextBadgeEl.textContent = `Context: ${selectedLabel(classEl) || "Class"} | ${selectedLabel(termEl) || "Term"}`;
}

function setBusy(on, label = "Working...") {
  if (on) {
    busyDepth += 1;
    if (busyDepth > 1) return;
    document.querySelectorAll(".js-action").forEach((btn) => {
      btn.disabled = true;
      if (!btn.dataset._origTxt) {
        btn.dataset._origTxt = btn.textContent;
      }
      btn.textContent = label;
    });
    return;
  }

  busyDepth = Math.max(0, busyDepth - 1);
  if (busyDepth > 0) return;
  document.querySelectorAll(".js-action").forEach((btn) => {
    btn.disabled = false;
    if (btn.dataset._origTxt) {
      btn.textContent = btn.dataset._origTxt;
      delete btn.dataset._origTxt;
    }
  });
}

async function runAction(fn, busyLabel = "Working...") {
  const t0 = performance.now();
  setBusy(true, busyLabel);
  try {
    await fn();
    setPerf(busyLabel.replace("...", ""), performance.now() - t0);
  } catch (err) {
    if (err && err.name === "AbortError") {
      return;
    }
    setStatus(err?.message || "Operation failed", true);
    setPerf("Error", performance.now() - t0);
  } finally {
    setBusy(false);
  }
}

function setActiveTab(tab) {
  activeTab = tab;
  Object.keys(tabPanels).forEach((key) => {
    const active = key === tab;
    tabPanels[key].classList.toggle("hidden", !active);
    tabButtons[key].className = active
      ? "tab-btn px-3 py-1.5 rounded bg-blue-600 text-white text-xs"
      : "tab-btn px-3 py-1.5 rounded bg-slate-200 text-slate-700 text-xs";
  });
}

async function bootstrap() {
  const { data } = await fetchJson(endpoint({ action: "bootstrap" }), "bootstrap", false);
  if (!data.success) throw new Error(data.message || "Bootstrap failed");
  classEl.innerHTML = (data.classes || []).map(c => `<option value="${c.id}">${c.name} (${c.grade}${c.section ? " - " + c.section : ""})</option>`).join("");
  termEl.innerHTML = (data.terms || []).map(t => `<option value="${t.id}">${t.name}</option>`).join("");
  updateContextBadge();
}

async function loadHomeroomStatus(force = false) {
  if (!force) {
    const cached = getFastCache("homeroom_status");
    if (cached) {
      applyHomeroomStatus(cached);
      return;
    }
  }
  const { data } = await fetchJson(endpoint({ action: "get_homeroom_status", class_id: classEl.value, term_id: termEl.value }), "homeroom_status");
  if (!data.success) return;
  setFastCache("homeroom_status", data);
  applyHomeroomStatus(data);
}

function applyHomeroomStatus(data) {
  latestHomeroomStatus = data.status || "none";
  let text = "Homeroom: Not submitted";
  let cls = "bg-slate-100 border-slate-300 text-slate-700";
  if (data.status === "draft") {
    text = "Homeroom: Draft saved";
    cls = "bg-amber-100 border-amber-300 text-amber-800";
  } else if (data.status === "submitted") {
    text = "Homeroom: Submitted";
    cls = "bg-emerald-100 border-emerald-300 text-emerald-800";
  }
  homeroomStatusBadgeEl.className = `inline-flex items-center rounded-full border px-3 py-1 text-xs ${cls}`;
  homeroomStatusBadgeEl.textContent = text;
  applyTermWorkflowGuard();
}

function applyTermWorkflowGuard() {
  const finalizeBtn = document.getElementById("term_finalize_btn");
  const applyBtn = document.getElementById("term_apply_btn");
  if (!finalizeBtn || !applyBtn) return;
  const allowFinalize = latestHomeroomStatus === "submitted";
  finalizeBtn.disabled = !allowFinalize;
  applyBtn.disabled = !allowFinalize;
  finalizeBtn.title = allowFinalize ? "" : "Finalize disabled until homeroom submits this term.";
  applyBtn.title = allowFinalize ? "" : "Apply disabled until homeroom submits this term.";
}

function renderTermRows(rows) {
  if (!rows.length) {
    termRowsEl.innerHTML = `<tr><td colspan="7" class="px-3 py-4 text-gray-500">No rows found.</td></tr>`;
    return;
  }
  termRowsEl.innerHTML = rows.map(r => `
    <tr>
      <td class="px-3 py-2">${r.rank_in_class ?? "-"}</td>
      <td class="px-3 py-2 font-medium text-gray-800">${r.full_name}</td>
      <td class="px-3 py-2">${r.subject_count}</td>
      <td class="px-3 py-2">${Number(r.total_score || 0).toFixed(2)}</td>
      <td class="px-3 py-2">${Number(r.average_score || 0).toFixed(2)}</td>
      <td class="px-3 py-2">${Number(r.attendance_percent || 0).toFixed(2)}</td>
      <td class="px-3 py-2">${Number(r.is_finalized) === 1 ? "Yes" : "No"}</td>
    </tr>
  `).join("");
}

function renderCourseRows(rows) {
  if (!rows.length) {
    courseRowsEl.innerHTML = `<tr><td colspan="6" class="px-3 py-4 text-gray-500">No rows found.</td></tr>`;
    return;
  }
  courseRowsEl.innerHTML = rows.map(r => `
    <tr>
      <td class="px-3 py-2 font-medium text-gray-800">${r.course_name}</td>
      <td class="px-3 py-2">${r.course_code || "-"}</td>
      <td class="px-3 py-2">${r.teacher_name || "-"}</td>
      <td class="px-3 py-2">${Number(r.entered_rows || 0)}</td>
      <td class="px-3 py-2">${Number(r.total_score || 0).toFixed(2)}</td>
      <td class="px-3 py-2">${Number(r.average_score || 0).toFixed(2)}</td>
    </tr>
  `).join("");
}

function renderReadinessRows(rows) {
  readinessRowsCache = Array.isArray(rows) ? rows : [];
  updateReadinessCounts();
  let viewRows = readinessRowsCache;
  if (readinessFilterMode === "ready") {
    viewRows = readinessRowsCache.filter(r => String(r.pipeline_state || "") === "ready_for_admin");
  } else if (readinessFilterMode === "homeroom") {
    viewRows = readinessRowsCache.filter(r => String(r.pipeline_state || "") === "ready_for_homeroom");
  } else if (readinessFilterMode === "updates") {
    viewRows = readinessRowsCache.filter(r => !!r.has_new_teacher_updates);
  }

  if (!viewRows.length) {
    readinessRowsEl.innerHTML = `<tr><td colspan="7" class="px-3 py-4 text-gray-500">No readiness rows found.</td></tr>`;
    return;
  }
  readinessRowsEl.innerHTML = viewRows.map(r => {
    const classLabel = `${r.class_name || "-"} (${r.grade || "-"}${r.section ? " - " + r.section : ""})`;
    const teacherProgress = `${Number(r.submitted_courses || 0)}/${Number(r.expected_courses || 0)} (${Number(r.teacher_completion_percent || 0).toFixed(2)}%)`;
    const hStatus = String(r.homeroom_status || "none");
    const hChip = hStatus === "submitted" ? "bg-emerald-100 text-emerald-700" : (hStatus === "draft" ? "bg-amber-100 text-amber-700" : "bg-slate-100 text-slate-700");
    const state = String(r.pipeline_state || "awaiting_teacher");
    const stateLabel = state === "ready_for_admin"
      ? "Ready For Admin"
      : (state === "ready_for_homeroom" ? "Ready For Homeroom" : (state === "teacher_in_progress" ? "Teacher In Progress" : "Awaiting Teacher"));
    const stateChip = state === "ready_for_admin"
      ? "bg-emerald-100 text-emerald-700"
      : (state === "ready_for_homeroom" ? "bg-cyan-100 text-cyan-700" : "bg-slate-100 text-slate-700");
    return `
      <tr>
        <td class="px-3 py-2 font-medium text-gray-800">${classLabel}</td>
        <td class="px-3 py-2">${Number(r.students || 0)}</td>
        <td class="px-3 py-2">${teacherProgress}</td>
        <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-0.5 ${hChip}">${hStatus}</span></td>
        <td class="px-3 py-2">${Number(r.finalized_term_rows || 0)}</td>
        <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-0.5 ${stateChip}">${stateLabel}</span></td>
        <td class="px-3 py-2">${r.latest_teacher_update || "-"}</td>
      </tr>
    `;
  }).join("");
}

function updateReadinessCounts() {
  const total = readinessRowsCache.length;
  const ready = readinessRowsCache.filter(r => String(r.pipeline_state || "") === "ready_for_admin").length;
  const homeroom = readinessRowsCache.filter(r => String(r.pipeline_state || "") === "ready_for_homeroom").length;
  const updates = readinessRowsCache.filter(r => !!r.has_new_teacher_updates).length;
  readinessCountReadyEl.textContent = String(ready);
  readinessCountHomeroomEl.textContent = String(homeroom);
  readinessCountUpdatesEl.textContent = String(updates);
  readinessCountTotalEl.textContent = `Total: ${total}`;
}

async function loadReadiness(force = false) {
  if (!force) {
    const cached = getFastCache("readiness");
    if (cached) {
      renderReadinessRows(cached.rows || []);
      const summary = cached.summary || {};
      readinessSummaryBadgeEl.textContent = `Readiness: ${Number(summary.ready_for_admin || 0)}/${Number(summary.classes || 0)} ready, ${Number(summary.with_new_updates || 0)} new updates`;
      setStatus(`Loaded ${cached.rows.length} readiness row(s) (cached).`);
      return;
    }
  }
  const { data } = await fetchJson(endpoint({ action: "get_readiness", term_id: termEl.value }), "readiness");
  if (!data.success) throw new Error(data.message || "Failed to load readiness");
  setFastCache("readiness", data);
  readinessFilterMode = "all";
  renderReadinessRows(data.rows || []);
  const summary = data.summary || {};
  readinessSummaryBadgeEl.textContent = `Readiness: ${Number(summary.ready_for_admin || 0)}/${Number(summary.classes || 0)} ready, ${Number(summary.with_new_updates || 0)} new updates`;
  setStatus(`Loaded ${data.rows.length} readiness row(s).`);
}

function renderYearlyRows(rows) {
  if (!rows.length) {
    yearlyRowsEl.innerHTML = `<tr><td colspan="9" class="px-3 py-4 text-gray-500">No yearly rows found.</td></tr>`;
    return;
  }
  yearlyRowsEl.innerHTML = rows.map(r => `
    <tr>
      <td class="px-3 py-2"><input type="checkbox" class="yearly-row-check" data-student-id="${Number(r.student_id || 0)}"></td>
      <td class="px-3 py-2">${r.rank_in_class ?? "-"}</td>
      <td class="px-3 py-2 font-medium text-gray-800">${r.full_name}</td>
      <td class="px-3 py-2">${Number(r.terms_count || 0)}</td>
      <td class="px-3 py-2">${Number(r.year_total || 0).toFixed(2)}</td>
      <td class="px-3 py-2">${Number(r.year_average || 0).toFixed(2)}</td>
      <td class="px-3 py-2">${r.decision}</td>
      <td class="px-3 py-2">${Number(r.is_finalized) === 1 ? "Yes" : "No"}</td>
      <td class="px-3 py-2"><button class="js-row-promote bg-indigo-600 text-white rounded px-2 py-1 text-xs hover:bg-indigo-700" data-student-id="${Number(r.student_id || 0)}">Promote</button></td>
    </tr>
  `).join("");
  yearlySelectAllEl.checked = false;
}

function renderHomeroomRows(rows) {
  homeroomRowsCache = Array.isArray(rows) ? rows : [];
  if (!rows.length) {
    homeroomRowsEl.innerHTML = `<tr><td colspan="9" class="px-3 py-4 text-gray-500">No homeroom submissions found.</td></tr>`;
    return;
  }
  homeroomRowsEl.innerHTML = rows.map(r => {
    const status = String(r.status || "new");
    const chip = status === "submitted" ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700";
    const classLabel = `${r.class_name || "-"} (${r.grade || "-"}${r.section ? " - " + r.section : ""})`;
    const reviewed = r.reviewed_at ? `Yes (${r.reviewed_at})` : "No";
    return `
      <tr>
        <td class="px-3 py-2"><input type="checkbox" class="homeroom-row-check" data-id="${Number(r.id || 0)}"></td>
        <td class="px-3 py-2">${classLabel}</td>
        <td class="px-3 py-2">${r.term_name || "-"}</td>
        <td class="px-3 py-2">${r.teacher_name || "-"}</td>
        <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-0.5 ${chip}">${status}</span></td>
        <td class="px-3 py-2">${r.notes || "-"}</td>
        <td class="px-3 py-2">${r.submitted_at || "-"}</td>
        <td class="px-3 py-2">${reviewed}</td>
        <td class="px-3 py-2"><button class="js-homeroom-view bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded px-2 py-1 text-xs" data-id="${Number(r.id || 0)}">View</button></td>
      </tr>
    `;
  }).join("");
  homeroomSelectAllEl.checked = false;
}

async function loadTermSummary(force = false) {
  if (!force) {
    const cached = getFastCache("term");
    if (cached) {
      renderTermRows(cached.rows || []);
      setStatus(`Loaded ${cached.rows.length} term rows (cached).`);
      return;
    }
  }
  const { data } = await fetchJson(endpoint({ action: "get_summary", class_id: classEl.value, term_id: termEl.value }), "term");
  if (!data.success) throw new Error(data.message || "Failed to load term summary");
  setFastCache("term", data);
  renderTermRows(data.rows || []);
  setStatus(`Loaded ${data.rows.length} term rows.`);
}

async function recalcTermSummary() {
  const form = new FormData();
  form.append("action", "recalculate");
  form.append("class_id", classEl.value);
  form.append("term_id", termEl.value);
  const res = await fetch("api/result_summary_mvp.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || "Term recalculate failed");
  clearContextCaches();
  setStatus(`${data.message} (${data.rows} rows)`);
  await loadTermSummary(true);
}

async function finalizeTermSummary() {
  const form = new FormData();
  form.append("action", "finalize");
  form.append("class_id", classEl.value);
  form.append("term_id", termEl.value);
  form.append("csrf", csrf);
  const res = await fetch("api/result_summary_mvp.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || "Term finalize failed");
  clearContextCaches();
  setStatus(`${data.message} (${data.affected} rows)`);
  await loadTermSummary(true);
}

async function applyTermStatus() {
  const form = new FormData();
  form.append("action", "apply_status");
  form.append("class_id", classEl.value);
  form.append("term_id", termEl.value);
  form.append("csrf", csrf);
  const res = await fetch("api/result_summary_mvp.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || "Apply term status failed");
  setStatus(`${data.message} (${data.affected} students)`);
}

async function loadCourseSummary(force = false) {
  if (!force) {
    const cached = getFastCache("course");
    if (cached) {
      renderCourseRows(cached.rows || []);
      setStatus(`Loaded ${cached.rows.length} course rows (cached).`);
      return;
    }
  }
  const { data } = await fetchJson(endpoint({ action: "get_course_summary", class_id: classEl.value, term_id: termEl.value }), "course");
  if (!data.success) throw new Error(data.message || "Failed to load course summary");
  setFastCache("course", data);
  renderCourseRows(data.rows || []);
  setStatus(`Loaded ${data.rows.length} course rows.`);
}

async function recalcYearly() {
  const form = new FormData();
  form.append("action", "recalculate_yearly");
  form.append("class_id", classEl.value);
  const res = await fetch("api/result_summary_mvp.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || "Yearly recalculate failed");
  clearContextCaches();
  setStatus(`${data.message} (${data.rows} rows)`);
  await loadYearly(true);
}

async function loadYearly(force = false) {
  if (!force) {
    const cached = getFastCache("yearly");
    if (cached) {
      renderYearlyRows(cached.rows || []);
      setStatus(`Loaded ${cached.rows.length} yearly rows (${cached.academic_year}) (cached).`);
      return;
    }
  }
  const { data } = await fetchJson(endpoint({ action: "get_yearly_summary", class_id: classEl.value }), "yearly");
  if (!data.success) throw new Error(data.message || "Failed to load yearly summary");
  setFastCache("yearly", data);
  renderYearlyRows(data.rows || []);
  setStatus(`Loaded ${data.rows.length} yearly rows (${data.academic_year}).`);
}

async function finalizeYearly() {
  const form = new FormData();
  form.append("action", "finalize_yearly");
  form.append("class_id", classEl.value);
  form.append("csrf", csrf);
  const res = await fetch("api/result_summary_mvp.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || "Yearly finalize failed");
  clearContextCaches();
  setStatus(`${data.message} (${data.affected} rows)`);
  await loadYearly(true);
}

function getSelectedYearlyStudentIds() {
  return Array.from(document.querySelectorAll(".yearly-row-check:checked"))
    .map(el => Number(el.getAttribute("data-student-id") || 0))
    .filter(v => v > 0);
}

async function setYearlyDecisionBulk(decision) {
  const ids = getSelectedYearlyStudentIds();
  if (!ids.length) throw new Error("Select students first.");
  const form = new FormData();
  form.append("action", "set_yearly_decision_bulk");
  form.append("class_id", classEl.value);
  form.append("decision", decision);
  form.append("student_ids", JSON.stringify(ids));
  form.append("csrf", csrf);
  const res = await fetch("api/result_summary_mvp.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || "Failed to update decisions");
  clearContextCaches();
  setStatus(`${data.message} (${data.affected} students)`);
  await loadYearly(true);
}

async function promoteYearlySelected(studentIds) {
  const ids = Array.isArray(studentIds) ? studentIds : getSelectedYearlyStudentIds();
  if (!ids.length) throw new Error("Select students first.");
  const form = new FormData();
  form.append("action", "promote_yearly_selected");
  form.append("class_id", classEl.value);
  form.append("student_ids", JSON.stringify(ids));
  form.append("csrf", csrf);
  const res = await fetch("api/result_summary_mvp.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || "Promotion failed");
  clearContextCaches();
  setStatus(`${data.message}. Promoted: ${Number(data.summary?.promoted || 0)}`);
  await loadYearly(true);
}

async function promoteYearlyAllPass() {
  const form = new FormData();
  form.append("action", "promote_yearly_passed");
  form.append("class_id", classEl.value);
  form.append("csrf", csrf);
  const res = await fetch("api/result_summary_mvp.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || "Promotion failed");
  clearContextCaches();
  setStatus(`${data.message}. Promoted: ${Number(data.summary?.promoted || 0)}`);
  await loadYearly(true);
}

async function loadHomeroomSubmissions(scope = "class_term", force = false) {
  const cacheName = scope === "term" ? "homeroom_term" : "homeroom_class_term";
  if (!force) {
    const cached = getFastCache(cacheName);
    if (cached) {
      renderHomeroomRows(cached.rows || []);
      setStatus(cached.message || `Loaded ${cached.rows.length} homeroom submission row(s) (cached).`);
      return;
    }
  }
  const params = { action: "get_homeroom_submissions" };
  if (scope === "term") {
    params.term_id = termEl.value;
    params.only_submitted = 1;
  } else {
    params.class_id = classEl.value;
    params.term_id = termEl.value;
  }
  const { data } = await fetchJson(endpoint(params), "homeroom_list");
  if (!data.success) throw new Error(data.message || "Failed to load homeroom submissions");
  setFastCache(cacheName, data);
  renderHomeroomRows(data.rows || []);
  setStatus(data.message || `Loaded ${data.rows.length} homeroom submission row(s).`);
}

function getSelectedHomeroomIds() {
  return Array.from(document.querySelectorAll(".homeroom-row-check:checked"))
    .map(el => Number(el.getAttribute("data-id") || 0))
    .filter(v => v > 0);
}

async function markHomeroomReviewedBulk() {
  const ids = getSelectedHomeroomIds();
  if (!ids.length) throw new Error("Select submissions first.");
  const form = new FormData();
  form.append("action", "mark_homeroom_reviewed_bulk");
  form.append("ids", JSON.stringify(ids));
  form.append("csrf", csrf);
  const res = await fetch("api/result_summary_mvp.php", { method: "POST", body: form });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || "Failed to mark reviewed");
  clearContextCaches();
  setStatus(`${data.message} (${data.affected} rows)`);
  await loadHomeroomSubmissions("class_term", true);
}

function openHomeroomViewById(id) {
  const row = homeroomRowsCache.find((r) => Number(r.id || 0) === Number(id));
  if (!row) return;
  homeroomViewContentEl.textContent =
`Class: ${row.class_name || "-"} (${row.grade || "-"}${row.section ? " - " + row.section : ""})
Term: ${row.term_name || "-"}
Teacher: ${row.teacher_name || "-"}
Status: ${row.status || "-"}
Submitted At: ${row.submitted_at || "-"}
Reviewed At: ${row.reviewed_at || "-"}
Notes:
${row.notes || "-"}`;
  homeroomViewModalEl.classList.remove("hidden");
}

function closeHomeroomView() {
  homeroomViewModalEl.classList.add("hidden");
}

async function refreshCurrentView() {
  updateContextBadge();
  if (activeTab === "term") return Promise.all([loadHomeroomStatus(true), loadTermSummary(true)]);
  if (activeTab === "course") return Promise.all([loadHomeroomStatus(true), loadCourseSummary(true)]);
  if (activeTab === "readiness") return Promise.all([loadHomeroomStatus(true), loadReadiness(true)]);
  if (activeTab === "yearly") return Promise.all([loadHomeroomStatus(true), loadYearly(true)]);
  return Promise.all([loadHomeroomStatus(true), loadHomeroomSubmissions("class_term", true)]);
}

document.getElementById("refresh_all_btn").addEventListener("click", () => runAction(refreshCurrentView, "Refreshing..."));
classEl.addEventListener("change", () => runAction(refreshCurrentView, "Refreshing..."));
termEl.addEventListener("change", () => runAction(refreshCurrentView, "Refreshing..."));

tabButtons.term.addEventListener("click", () => runAction(async () => { setActiveTab("term"); await loadTermSummary(); }, "Loading..."));
tabButtons.course.addEventListener("click", () => runAction(async () => { setActiveTab("course"); await loadCourseSummary(); }, "Loading..."));
tabButtons.readiness.addEventListener("click", () => runAction(async () => { setActiveTab("readiness"); await loadReadiness(); }, "Loading..."));
tabButtons.yearly.addEventListener("click", () => runAction(async () => { setActiveTab("yearly"); await loadYearly(); }, "Loading..."));
tabButtons.homeroom.addEventListener("click", () => runAction(async () => { setActiveTab("homeroom"); await loadHomeroomSubmissions(); }, "Loading..."));

document.getElementById("course_load_btn").addEventListener("click", () => runAction(loadCourseSummary, "Loading..."));
document.getElementById("readiness_load_btn").addEventListener("click", () => runAction(loadReadiness, "Loading..."));
document.getElementById("readiness_filter_ready_btn").addEventListener("click", () => {
  readinessFilterMode = "ready";
  renderReadinessRows(readinessRowsCache);
  setStatus("Readiness filter applied: Ready For Admin");
});
document.getElementById("readiness_filter_homeroom_btn").addEventListener("click", () => {
  readinessFilterMode = "homeroom";
  renderReadinessRows(readinessRowsCache);
  setStatus("Readiness filter applied: Needs Homeroom");
});
document.getElementById("readiness_filter_updates_btn").addEventListener("click", () => {
  readinessFilterMode = "updates";
  renderReadinessRows(readinessRowsCache);
  setStatus("Readiness filter applied: New Teacher Updates");
});
document.getElementById("readiness_filter_clear_btn").addEventListener("click", () => {
  readinessFilterMode = "all";
  renderReadinessRows(readinessRowsCache);
  setStatus("Readiness filter cleared.");
});

document.getElementById("yearly_recalc_btn").addEventListener("click", () => runAction(recalcYearly, "Recalculating..."));
document.getElementById("yearly_load_btn").addEventListener("click", () => runAction(loadYearly, "Loading..."));
document.getElementById("yearly_finalize_btn").addEventListener("click", () => runAction(finalizeYearly, "Finalizing..."));
document.getElementById("yearly_apply_decision_btn").addEventListener("click", () => {
  const decision = document.getElementById("yearly_decision_select").value;
  runAction(() => setYearlyDecisionBulk(decision), "Updating...");
});
document.getElementById("yearly_promote_selected_btn").addEventListener("click", () => runAction(() => promoteYearlySelected(), "Promoting..."));
document.getElementById("yearly_promote_all_pass_btn").addEventListener("click", () => runAction(promoteYearlyAllPass, "Promoting..."));

document.getElementById("homeroom_load_btn").addEventListener("click", () => runAction(() => loadHomeroomSubmissions("class_term"), "Loading..."));
document.getElementById("homeroom_load_term_btn").addEventListener("click", () => runAction(() => loadHomeroomSubmissions("term"), "Loading..."));
document.getElementById("homeroom_mark_reviewed_btn").addEventListener("click", () => runAction(markHomeroomReviewedBulk, "Reviewing..."));

yearlySelectAllEl.addEventListener("change", () => {
  const checked = !!yearlySelectAllEl.checked;
  document.querySelectorAll(".yearly-row-check").forEach(el => { el.checked = checked; });
});
homeroomSelectAllEl.addEventListener("change", () => {
  const checked = !!homeroomSelectAllEl.checked;
  document.querySelectorAll(".homeroom-row-check").forEach(el => { el.checked = checked; });
});

yearlyRowsEl.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-row-promote");
  if (!btn) return;
  const sid = Number(btn.getAttribute("data-student-id") || 0);
  if (!sid) return;
  runAction(() => promoteYearlySelected([sid]), "Promoting...");
});
homeroomRowsEl.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-homeroom-view");
  if (!btn) return;
  openHomeroomViewById(Number(btn.getAttribute("data-id") || 0));
});
document.getElementById("homeroom_view_close_btn").addEventListener("click", closeHomeroomView);
document.getElementById("homeroom_view_backdrop").addEventListener("click", closeHomeroomView);

bootstrap()
  .then(async () => {
    setActiveTab("term");
    await refreshCurrentView();
  })
  .catch(err => setStatus(err.message || "Bootstrap failed", true));
</script>
<?php
$content = ob_get_clean();
echo renderAdminLayout('Result Summary MVP', $content);
?>
