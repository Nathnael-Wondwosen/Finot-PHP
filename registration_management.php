<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin_layout.php';

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

ob_start();
?>
<div class="p-4">
  <div class="bg-white rounded-lg shadow p-4">
    <h2 class="text-lg font-semibold mb-3">Registration Management</h2>
    <div class="border-b mb-4">
      <nav class="flex gap-2">
        <button id="tab-activation" class="px-3 py-1.5 rounded-t bg-blue-600 text-white text-sm">Activation</button>
        <button id="tab-formfields" class="px-3 py-1.5 rounded-t bg-gray-100 text-gray-700 text-sm">Form Fields</button>
      </nav>
    </div>

    <!-- Activation Tab -->
    <section id="panel-activation">
      <p class="text-sm text-gray-600 mb-4">Toggle availability for each registration type, set closed messages, and use router <code>go_registration.php?type=...</code>.</p>
      <div id="statusArea" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="border rounded p-3">
          <div class="font-medium">Youth</div>
          <div class="text-xs text-gray-500 mb-2">17+ age group</div>
          <div class="flex items-center gap-2 mb-2">
            <span class="text-sm">Status:</span>
            <span id="status-youth" class="text-sm font-semibold">Loading...</span>
          </div>
          <div class="flex gap-2">
            <button data-type="youth" data-active="1" class="btn-activate px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm">Activate</button>
            <button data-type="youth" data-active="0" class="btn-deactivate px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm">Deactivate</button>
          </div>
          <div class="mt-3">
            <label class="block text-xs text-gray-600 mb-1">Closed Title</label>
            <input id="title-youth" type="text" class="w-full px-2 py-1 border rounded text-sm" placeholder="Custom closed title">
          </div>
          <div class="mt-2">
            <label class="block text-xs text-gray-600 mb-1">Closed Message</label>
            <textarea id="message-youth" rows="3" class="w-full px-2 py-1 border rounded text-sm" placeholder="Custom message shown when closed"></textarea>
          </div>
          <div class="mt-2 flex justify-end">
            <button data-type="youth" class="btn-save-config px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">Save Text</button>
          </div>
          <div class="mt-2 text-xs text-gray-500">Open: go_registration.php?type=youth</div>
        </div>

        <div class="border rounded p-3">
          <div class="font-medium">Instrument</div>
          <div class="text-xs text-gray-500 mb-2">Music instrument registrations</div>
          <div class="flex items-center gap-2 mb-2">
            <span class="text-sm">Status:</span>
            <span id="status-instrument" class="text-sm font-semibold">Loading...</span>
          </div>
          <div class="flex gap-2">
            <button data-type="instrument" data-active="1" class="btn-activate px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm">Activate</button>
            <button data-type="instrument" data-active="0" class="btn-deactivate px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm">Deactivate</button>
          </div>
          <div class="mt-3">
            <label class="block text-xs text-gray-600 mb-1">Closed Title</label>
            <input id="title-instrument" type="text" class="w-full px-2 py-1 border rounded text-sm" placeholder="Custom closed title">
          </div>
          <div class="mt-2">
            <label class="block text-xs text-gray-600 mb-1">Closed Message</label>
            <textarea id="message-instrument" rows="3" class="w-full px-2 py-1 border rounded text-sm" placeholder="Custom message shown when closed"></textarea>
          </div>
          <div class="mt-2 flex justify-end">
            <button data-type="instrument" class="btn-save-config px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">Save Text</button>
          </div>
          <div class="mt-2 text-xs text-gray-500">Open: go_registration.php?type=instrument</div>
        </div>

        <div class="border rounded p-3">
          <div class="font-medium">Children</div>
          <div class="text-xs text-gray-500 mb-2">Under 18</div>
          <div class="flex items-center gap-2 mb-2">
            <span class="text-sm">Status:</span>
            <span id="status-children" class="text-sm font-semibold">Loading...</span>
          </div>
          <div class="flex gap-2">
            <button data-type="children" data-active="1" class="btn-activate px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm">Activate</button>
            <button data-type="children" data-active="0" class="btn-deactivate px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm">Deactivate</button>
          </div>
          <div class="mt-3">
            <label class="block text-xs text-gray-600 mb-1">Closed Title</label>
            <input id="title-children" type="text" class="w-full px-2 py-1 border rounded text-sm" placeholder="Custom closed title">
          </div>
          <div class="mt-2">
            <label class="block text-xs text-gray-600 mb-1">Closed Message</label>
            <textarea id="message-children" rows="3" class="w-full px-2 py-1 border rounded text-sm" placeholder="Custom message shown when closed"></textarea>
          </div>
          <div class="mt-2 flex justify-end">
            <button data-type="children" class="btn-save-config px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">Save Text</button>
          </div>
          <div class="mt-2 text-xs text-gray-500">Open: go_registration.php?type=children</div>
        </div>
      </div>

      <div id="msg" class="mt-4 text-sm"></div>
    </section>

    <!-- Form Fields Tab -->
    <section id="panel-formfields" class="hidden">
      <p class="text-sm text-gray-600 mb-3">Edit field labels, placeholders, and required flags per registration type. This stores config centrally.</p>
      <div class="flex items-center gap-2 mb-4">
        <label class="text-sm">Type:</label>
        <select id="ff-type" class="px-2 py-1 border rounded text-sm">
          <option value="youth">Youth</option>
          <option value="instrument">Instrument</option>
          <option value="children">Children</option>
        </select>
        <button id="ff-reload" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 border rounded text-sm">Reload</button>
        <button id="ff-save" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">Save</button>
      </div>
      <div class="overflow-auto">
        <table class="min-w-full border text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="border px-2 py-1 text-left">Field Key</th>
              <th class="border px-2 py-1 text-left">Label</th>
              <th class="border px-2 py-1 text-left">Placeholder</th>
              <th class="border px-2 py-1 text-center">Required</th>
              <th class="border px-2 py-1 text-center">Order</th>
            </tr>
          </thead>
          <tbody id="ff-body"></tbody>
        </table>
      </div>
      <div id="ff-msg" class="mt-3 text-sm"></div>
    </section>
  </div>
</div>

<script>
async function fetchStatuses(){
  const el = (id)=>document.getElementById(id);
  try {
    const res = await fetch('api/get_registration_status.php');
    const data = await res.json();
    if (!data || !data.success) throw new Error('Failed to load');
    const map = data.status || {};
    updateStatus('youth', map.youth && map.youth.active);
    updateStatus('instrument', map.instrument && map.instrument.active);
    updateStatus('children', map.children && map.children.active);
    // Populate title/message fields
    setConfigFields('youth', map.youth);
    setConfigFields('instrument', map.instrument);
    setConfigFields('children', map.children);
  } catch(e) {
    setMsg('Failed to load statuses', true);
  }
}
function updateStatus(type, v){
  const id = 'status-' + type;
  const el = document.getElementById(id);
  if (!el) return;
  if (v === 1 || v === '1') { el.textContent = 'Active'; el.className = 'text-sm font-semibold text-green-600'; }
  else { el.textContent = 'Inactive'; el.className = 'text-sm font-semibold text-red-600'; }
}
function setConfigFields(type, cfg){
  if (!cfg) return;
  const t = document.getElementById('title-' + type);
  const m = document.getElementById('message-' + type);
  if (t) t.value = cfg.title || '';
  if (m) m.value = cfg.message || '';
}
function setMsg(t, isErr){
  const m = document.getElementById('msg');
  if (!m) return;
  m.textContent = t;
  m.className = 'mt-4 text-sm ' + (isErr? 'text-red-600' : 'text-green-600');
  setTimeout(()=>{ m.textContent=''; m.className='mt-4 text-sm'; }, 3000);
}
async function setStatus(type, active){
  try {
    const fd = new FormData();
    fd.append('type', type);
    fd.append('active', String(active));
    const res = await fetch('api/set_registration_status.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data || !data.success) throw new Error(data && data.message ? data.message : 'Failed');
    updateStatus(type, data.active);
    setMsg('Updated ' + type + ' to ' + (data.active ? 'Active' : 'Inactive'));
  } catch(e) {
    setMsg('Error: ' + (e && e.message ? e.message : 'update failed'), true);
  }
}

document.addEventListener('DOMContentLoaded', function(){
  // Tabs
  const tabAct = document.getElementById('tab-activation');
  const tabFields = document.getElementById('tab-formfields');
  const panelAct = document.getElementById('panel-activation');
  const panelFields = document.getElementById('panel-formfields');
  function showTab(which){
    if (which==='activation'){
      tabAct.className='px-3 py-1.5 rounded-t bg-blue-600 text-white text-sm';
      tabFields.className='px-3 py-1.5 rounded-t bg-gray-100 text-gray-700 text-sm';
      panelAct.classList.remove('hidden');
      panelFields.classList.add('hidden');
    } else {
      tabFields.className='px-3 py-1.5 rounded-t bg-blue-600 text-white text-sm';
      tabAct.className='px-3 py-1.5 rounded-t bg-gray-100 text-gray-700 text-sm';
      panelFields.classList.remove('hidden');
      panelAct.classList.add('hidden');
    }
  }
  tabAct.addEventListener('click', ()=>showTab('activation'));
  tabFields.addEventListener('click', ()=>showTab('fields'));

  fetchStatuses();
  document.querySelectorAll('.btn-activate').forEach(btn=>{
    btn.addEventListener('click', function(){ setStatus(this.getAttribute('data-type'), 1); });
  });
  document.querySelectorAll('.btn-deactivate').forEach(btn=>{
    btn.addEventListener('click', function(){ setStatus(this.getAttribute('data-type'), 0); });
  });
  document.querySelectorAll('.btn-save-config').forEach(btn=>{
    btn.addEventListener('click', async function(){
      const type = this.getAttribute('data-type');
      const title = (document.getElementById('title-' + type) || {}).value || '';
      const message = (document.getElementById('message-' + type) || {}).value || '';
      try {
        const fd = new FormData();
        fd.append('type', type);
        fd.append('title', title);
        fd.append('message', message);
        const res = await fetch('api/set_registration_config.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data || !data.success) throw new Error(data && data.message ? data.message : 'Failed');
        setMsg('Saved text for ' + type);
      } catch(e) { setMsg('Error: ' + (e && e.message ? e.message : 'save failed'), true); }
    });
  });

  // Form Fields Tab logic
  const ffType = document.getElementById('ff-type');
  const ffBody = document.getElementById('ff-body');
  const ffMsg = document.getElementById('ff-msg');
  async function loadFormFields(){
    try {
      ffBody.innerHTML = '<tr><td colspan="5" class="border px-2 py-3 text-center text-gray-500">Loading...</td></tr>';
      const res = await fetch('api/get_form_config.php?type=' + encodeURIComponent(ffType.value));
      const data = await res.json();
      if (!data || !data.success) throw new Error('Failed to load');
      const fields = Array.isArray(data.fields) ? data.fields : [];
      if (fields.length === 0){ ffBody.innerHTML = '<tr><td colspan="5" class="border px-2 py-3 text-center text-gray-500">No fields</td></tr>'; return; }
      ffBody.innerHTML = fields.map((f,i)=>{
        const req = (String(f.required)==='1') ? 'checked' : '';
        return `
          <tr>
            <td class="border px-2 py-1 align-top"><code>${f.field_key}</code><input type="hidden" class="ff-key" value="${f.field_key}"></td>
            <td class="border px-2 py-1"><input type="text" class="ff-label w-full px-2 py-1 border rounded" value="${(f.label||'').replace(/"/g,'&quot;')}"></td>
            <td class="border px-2 py-1"><input type="text" class="ff-ph w-full px-2 py-1 border rounded" value="${(f.placeholder||'').replace(/"/g,'&quot;')}"></td>
            <td class="border px-2 py-1 text-center"><input type="checkbox" class="ff-req" ${req}></td>
            <td class="border px-2 py-1 text-center"><input type="number" class="ff-order w-20 px-2 py-1 border rounded" value="${typeof f.sort_order==='number'?f.sort_order:i}"></td>
          </tr>
        `;
      }).join('');
    } catch(e) {
      ffBody.innerHTML = '<tr><td colspan="5" class="border px-2 py-3 text-center text-red-600">Error loading</td></tr>';
    }
  }
  async function saveFormFields(){
    try {
      const rows = Array.from(ffBody.querySelectorAll('tr'));
      const payload = rows.map(r=>({
        field_key: (r.querySelector('.ff-key')||{}).value || '',
        label: (r.querySelector('.ff-label')||{}).value || '',
        placeholder: (r.querySelector('.ff-ph')||{}).value || '',
        required: (r.querySelector('.ff-req')||{checked:false}).checked ? 1 : 0,
        sort_order: parseInt((r.querySelector('.ff-order')||{}).value||'0',10) || 0
      })).filter(f=>f.field_key);
      const fd = new FormData();
      fd.append('type', ffType.value);
      fd.append('fields', JSON.stringify(payload));
      const res = await fetch('api/set_form_config.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (!data || !data.success) throw new Error('Save failed');
      ffMsg.textContent = 'Saved.'; ffMsg.className = 'mt-3 text-sm text-green-600';
      setTimeout(()=>{ ffMsg.textContent=''; ffMsg.className='mt-3 text-sm'; }, 2000);
    } catch(e) {
      ffMsg.textContent = 'Error saving.'; ffMsg.className = 'mt-3 text-sm text-red-600';
    }
  }
  document.getElementById('ff-reload').addEventListener('click', loadFormFields);
  document.getElementById('ff-save').addEventListener('click', saveFormFields);
  ffType.addEventListener('change', loadFormFields);
  // Init
  showTab('activation');
  loadFormFields();
});
</script>
<?php
$content = ob_get_clean();
echo renderAdminLayout('Registration Management', $content);
