<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin_layout.php';

requireAdminLogin();

$page_title = 'Youth Categorization (17+)';

// Build page content
ob_start();
?>
    <div class="space-y-3 sm:space-y-4">
      <!-- Filters Card -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-3 sm:p-4">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-2">
          <input id="q" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400" placeholder="Search name/phone..." />
          <select id="edu" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></select>
          <select id="fld" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></select>
          <select id="status" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            <option value="">All statuses</option>
            <option value="student">Student</option>
            <option value="worker">Worker</option>
          </select>
          <select id="f_prof" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></select>
          <select id="f_study" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></select>
          <div class="flex gap-2">
            <button id="btnFilter" class="px-3 py-2 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Filter</button>
            <button id="btnClear" class="px-3 py-2 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Clear</button>
          </div>
        </div>
      </div>

      <!-- Actions Card -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-2 sm:p-3">
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-sm">
          <button id="setStudent" class="px-2.5 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Set as Student</button>
          <button id="setWorker" class="px-2.5 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Set as Worker</button>
          <select id="selProfession" class="px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white min-w-[11rem]"></select>
          <button id="applyProfession" class="px-2.5 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Apply Profession</button>
          <button id="addProfession" class="px-2.5 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Add</button>
          <select id="selStudyField" class="px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white min-w-[11rem]"></select>
          <button id="applyStudyField" class="px-2.5 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Apply Study Field</button>
          <button id="addStudyField" class="px-2.5 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Add</button>
          <div class="ml-auto flex items-center gap-2">
            <span id="totalTxt" class="text-sm text-gray-600 dark:text-gray-300"></span>
            <button id="btnExport" class="px-2.5 py-1.5 bg-primary-600 hover:bg-primary-700 text-white border border-primary-600 rounded-md"><i class="fa fa-file-export mr-1"></i>Export CSV</button>
          </div>
        </div>
      </div>

      <!-- Table Card -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="p-2"><input type="checkbox" id="checkAll" /></th>
                <th class="p-2 text-left">Name</th>
                <th class="p-2 text-left">Phone</th>
                <th class="p-2 text-left">Current Grade</th>
                <th class="p-2 text-left">Education</th>
                <th class="p-2 text-left">Field of Study</th>
                <th class="p-2 text-left">Status</th>
                <th class="p-2 text-left">Profession</th>
                <th class="p-2 text-left">Study Field Cat</th>
              </tr>
            </thead>
            <tbody id="tbody"></tbody>
          </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <span id="pageInfo"></span>
            <div class="flex items-center gap-1">
              <label for="pageSelect" class="text-xs text-gray-500 dark:text-gray-400">Go to:</label>
              <select id="pageSelect" class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></select>
            </div>
          </div>
          <div class="flex gap-2">
            <div class="flex items-center gap-1 mr-2">
              <label for="perPage" class="text-xs text-gray-500 dark:text-gray-400">Rows:</label>
              <select id="perPage" class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="all">All</option>
              </select>
            </div>
            <button id="prev" class="px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Prev</button>
            <button id="next" class="px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">Next</button>
          </div>
        </div>
      </div>
    </div>
<?php
$content = ob_get_clean();

// Page script (kept lightweight)
ob_start();
?>
<script>
(function(){
  const q = document.getElementById('q');
  const edu = document.getElementById('edu');
  const fld = document.getElementById('fld');
  const status = document.getElementById('status');
  const fProf = document.getElementById('f_prof');
  const fStudy = document.getElementById('f_study');
  const tbody = document.getElementById('tbody');
  const checkAll = document.getElementById('checkAll');
  const totalTxt = document.getElementById('totalTxt');
  const pageInfo = document.getElementById('pageInfo');
  const prev = document.getElementById('prev');
  const next = document.getElementById('next');
  let perPage = '25';
  let page = 1, total = 0;
  const pageSelect = document.getElementById('pageSelect');
  const perPageSel = document.getElementById('perPage');

  function optionEls(arr, firstLabel){
    const opts = [`<option value="">${firstLabel}</option>`];
    arr.forEach(v => { const s = String(v||'').trim(); if(s) opts.push(`<option>${escapeHtml(s)}</option>`); });
    return opts.join('');
  }
  function escapeHtml(s){
    const d = document.createElement('div'); d.innerText = String(s); return d.innerHTML;
  }

  async function fetchData(){
    const params = new URLSearchParams({
      page: String(page), per_page: String(perPage),
      search: q.value.trim(), education_level: edu.value, field_of_study: fld.value, status: status.value,
      profession_category: fProf.value, study_field_category: fStudy.value
    });
    const res = await fetch('api/youth_classify_fetch.php?' + params.toString());
    if (!res.ok){ tbody.innerHTML = '<tr><td colspan="9" class="p-3 text-red-600">Load error</td></tr>'; return; }
    const j = await res.json();
    const rows = j.data || [];
    total = j.pagination?.total || rows.length;
    totalTxt.textContent = `Total: ${total}`;
    const totalPages = Math.max(1, Math.ceil(total/perPage));
    pageInfo.textContent = `Page ${page} of ${totalPages}`;
    // Populate page selector with all pages
    pageSelect.innerHTML = Array.from({length: totalPages}, (_, i) => i+1)
      .map(n => `<option value="${n}" ${n===page?'selected':''}>${n}</option>`)
      .join('');

    // Seed filters once
    if (edu.options.length === 0){ edu.innerHTML = optionEls(j.filters?.education_levels||[], 'All education levels'); }
    if (fld.options.length === 0){ fld.innerHTML = optionEls(j.filters?.fields_of_study||[], 'All fields'); }
    if (fProf.options.length === 0){ fProf.innerHTML = optionEls(j.filters?.profession_categories||[], 'All professions'); }
    if (fStudy.options.length === 0){ fStudy.innerHTML = optionEls(j.filters?.study_field_categories||[], 'All study fields'); }

    tbody.innerHTML = rows.map(r => `
      <tr class="border-t">
        <td class="p-2"><input type="checkbox" class="rowChk" data-id="${r.id}" /></td>
        <td class="p-2">
          <div class="font-medium">${escapeHtml(r.full_name||'')}</div>
          ${r.christian_name?`<div class="text-xs text-gray-500">${escapeHtml(r.christian_name)}</div>`:''}
        </td>
        <td class="p-2">${escapeHtml(r.phone_number||'')}</td>
        <td class="p-2">${escapeHtml(r.current_grade||'')}</td>
        <td class="p-2">
          ${r.education_level?`<span class="chip">${escapeHtml(r.education_level)}</span>`:''}
        </td>
        <td class="p-2">${escapeHtml(r.field_of_study||'')}</td>
        <td class="p-2">${r.status?`<span class="badge ${r.status==='worker'?'yellow':'green'}">${r.status}</span>`:'<span class="badge gray">unset</span>'}</td>
        <td class="p-2">${escapeHtml(r.profession_category||'')}</td>
        <td class="p-2">${escapeHtml(r.study_field_category||'')}</td>
      </tr>
    `).join('');

    checkAll.checked = false;
  }

  checkAll.addEventListener('change', ()=>{
    document.querySelectorAll('.rowChk').forEach(cb => cb.checked = checkAll.checked);
  });

  document.getElementById('btnFilter').addEventListener('click', ()=>{ page = 1; fetchData(); });
  document.getElementById('btnClear').addEventListener('click', ()=>{ q.value=''; edu.selectedIndex=0; fld.selectedIndex=0; status.selectedIndex=0; fProf.selectedIndex=0; fStudy.selectedIndex=0; page=1; fetchData(); });
  prev.addEventListener('click', ()=>{ if(page>1){ page--; fetchData(); }});
  next.addEventListener('click', ()=>{ if(page < Math.ceil(total/perPage)){ page++; fetchData(); }});
  pageSelect.addEventListener('change', ()=>{ const n = parseInt(pageSelect.value)||1; page = Math.min(Math.max(1,n), Math.max(1, Math.ceil(total/perPage))); fetchData(); });
  perPageSel.addEventListener('change', ()=>{ perPage = perPageSel.value; page = 1; fetchData(); });

  function selectedIds(){
    const ids = []; document.querySelectorAll('.rowChk:checked').forEach(cb => ids.push(parseInt(cb.dataset.id)));
    return ids;
  }

  async function loadCategories(){
    const [profRes, fieldRes] = await Promise.all([
      fetch('api/admin_categories.php?type=profession'),
      fetch('api/admin_categories.php?type=study_field')
    ]);
    const prof = profRes.ok ? (await profRes.json()).data||[] : [];
    const field = fieldRes.ok ? (await fieldRes.json()).data||[] : [];
    const selProfession = document.getElementById('selProfession');
    const selStudyField = document.getElementById('selStudyField');
    selProfession.innerHTML = optionEls(prof.map(x=>x.name), 'Profession category');
    selStudyField.innerHTML = optionEls(field.map(x=>x.name), 'Study field category');
  }

  async function saveClassification(payload){
    const res = await fetch('api/youth_classify_save.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
    if(!res.ok){ alert('Save failed'); return; }
    await fetchData();
  }

  document.getElementById('setStudent').addEventListener('click', ()=>{
    const ids = selectedIds(); if(ids.length===0) return alert('Select rows first');
    saveClassification({student_ids: ids, status: 'student'});
  });
  document.getElementById('setWorker').addEventListener('click', ()=>{
    const ids = selectedIds(); if(ids.length===0) return alert('Select rows first');
    saveClassification({student_ids: ids, status: 'worker'});
  });

  document.getElementById('addProfession').addEventListener('click', async ()=>{
    const name = prompt('New profession category name'); if(!name) return;
    await fetch('api/admin_categories.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({type:'profession', name})});
    loadCategories();
  });
  document.getElementById('addStudyField').addEventListener('click', async ()=>{
    const name = prompt('New study field category name'); if(!name) return;
    await fetch('api/admin_categories.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({type:'study_field', name})});
    loadCategories();
  });

  document.getElementById('selProfession').addEventListener('change', ()=>{
    const ids = selectedIds(); if(ids.length===0) return; const v = document.getElementById('selProfession').value; if(!v) return;
    saveClassification({student_ids: ids, profession_category: v});
  });
  document.getElementById('selStudyField').addEventListener('change', ()=>{
    const ids = selectedIds(); if(ids.length===0) return; const v = document.getElementById('selStudyField').value; if(!v) return;
    saveClassification({student_ids: ids, study_field_category: v});
  });

  document.getElementById('applyProfession').addEventListener('click', ()=>{
    const ids = selectedIds(); if(ids.length===0) return alert('Select rows first');
    const v = document.getElementById('selProfession').value; if(!v) return alert('Select a profession category');
    saveClassification({student_ids: ids, profession_category: v});
  });
  document.getElementById('applyStudyField').addEventListener('click', ()=>{
    const ids = selectedIds(); if(ids.length===0) return alert('Select rows first');
    const v = document.getElementById('selStudyField').value; if(!v) return alert('Select a study field category');
    saveClassification({student_ids: ids, study_field_category: v});
  });

  document.getElementById('btnExport').addEventListener('click', ()=>{
    const params = new URLSearchParams({
      search: q.value.trim(), education_level: edu.value, field_of_study: fld.value, status: status.value,
      profession_category: fProf.value, study_field_category: fStudy.value
    });
    window.location = 'api/youth_classify_export.php?' + params.toString();
  });

  // init
  loadCategories();
  fetchData();
})();
</script>
<?php
$page_script = ob_get_clean();

echo renderAdminLayout($page_title, $content, $page_script);
