<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የዜማ መሳሪያ ምዝገባ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Ethiopic', sans-serif; }
        .instrument-card { transition: box-shadow 0.2s, transform 0.2s; cursor: pointer; }
        .instrument-card.selected { box-shadow: 0 0 0 4px #3b82f6; transform: scale(1.04); border-color: #3b82f6; }
        .instrument-img { width: 80px; height: 80px; object-fit: contain; border-radius: 1rem; background: #f3f4f6; }
        .form-card { position: absolute; left: -9999px; }
        .form-card.active { position: static; left: auto; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px);} to { opacity: 1; transform: translateY(0);} }
        .progress-step { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background-color: #e5e7eb; color: #6b7280; font-weight: 700; font-size: 0.9rem; }
        .progress-step.active { background-color: #3b82f6; color: #fff; }
        .progress-step.completed { background-color: #10b981; color: #fff; }
    </style>
</head>
<body class="bg-gray-50">
    <?php
    // Inject form configuration for zero-runtime-fetch performance
    try {
        require_once __DIR__ . '/includes/form_config.php';
        $cfgRows = get_form_config('instrument', $pdo);
        $cfgAssoc = [];
        foreach ($cfgRows as $r) {
            $cfgAssoc[$r['field_key']] = [
                'label' => $r['label'],
                'placeholder' => $r['placeholder'],
                'required' => (int)$r['required'],
                'sort_order' => (int)$r['sort_order']
            ];
        }
        echo '<script>window.FORM_CFG_INSTRUMENT=' . json_encode($cfgAssoc, JSON_UNESCAPED_UNICODE) . ';</script>';
    } catch (Throwable $e) { /* ignore */ }
    ?>
    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_GET['error']) && $_GET['error']): ?>
        <div id="error-card" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 bg-red-100 border border-red-400 text-red-800 px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 animate-fade-in" style="min-width:300px;max-width:90vw;">
            <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            <span class="flex-1 text-base font-semibold">
                <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <button onclick="document.getElementById('error-card').remove()" class="ml-4 text-red-600 hover:text-red-900 focus:outline-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .animate-fade-in { animation: fade-in 0.5s cubic-bezier(.4,0,.2,1); }
        </style>
        <script>
    // Lightweight autosave + photo temp upload + ET date population
    (function(){
        const FORM_KEY = 'INSTR_FORM_AUTOSAVE';
        const TEMP_KEY = 'INSTR_TEMP_PHOTO_KEY';
        const TEMP_URL = 'INSTR_TEMP_PHOTO_URL';
        const TEMP_DATAURL = 'INSTR_TEMP_PHOTO_DATAURL';
        function saveDraft(){
            try {
                const form = document.getElementById('instrument-form');
                const data = {};
                Array.from(form.elements).forEach(el=>{
                    if (!el.name) return;
                    if (el.type==='radio') { if (el.checked) data[el.name]=el.value; }
                    else if (el.type==='checkbox') data[el.name]=!!el.checked;
                    else if (el.type!=='file') data[el.name]=el.value;
                });
                localStorage.setItem(FORM_KEY, JSON.stringify(data));
                // Persist ET date compact
                const y = document.getElementById('i_birth_year')?.value||'';
                const m = document.getElementById('i_birth_month')?.value||'';
                const d = document.getElementById('i_birth_day')?.value||'';
                localStorage.setItem('INSTR_BIRTH_ET', JSON.stringify({y,m,d}));
            } catch(_) {}
        }
        function restoreDraft(){
            try {
                const saved = localStorage.getItem(FORM_KEY); if (!saved) return;
                const data = JSON.parse(saved);
                const form = document.getElementById('instrument-form');
                Array.from(form.elements).forEach(el=>{
                    if (!el.name || !(el.name in data)) return;
                    if (el.type==='radio' || el.type==='checkbox') el.checked = (data[el.name]===true || data[el.name]===el.value);
                    else if (el.type!=='file') el.value = data[el.name];
                });
            } catch(_) {}
        }
        function debounce(fn, wait){ let t; return function(){ const a=arguments,c=this; clearTimeout(t); t=setTimeout(()=>fn.apply(c,a), wait); }; }
        document.addEventListener('DOMContentLoaded', function(){
            // Populate ET date (years, months, days)
            const ySel = document.getElementById('i_birth_year');
            const mSel = document.getElementById('i_birth_month');
            const dSel = document.getElementById('i_birth_day');
            function etIsLeap(y){ return ((y%4)===3); }
            function populateYears(){
                if (!ySel) return;
                ySel.innerHTML = '';
                const approxEt = (new Date()).getFullYear()-8; // rough Gregorian->Ethiopian offset
                for (let y=approxEt; y>=approxEt-30; y--){ const o=document.createElement('option'); o.value=String(y); o.textContent=String(y); ySel.appendChild(o); }
            }
            function populateMonths(){ if (!mSel) return; mSel.innerHTML=''; for(let m=1;m<=13;m++){ const o=document.createElement('option'); o.value=String(m); o.textContent=String(m); mSel.appendChild(o);} }
            function populateDays(){
                if (!dSel) return; dSel.innerHTML='';
                const y = parseInt(ySel?.value||'0',10);
                const m = parseInt(mSel?.value||'0',10);
                let days = 30; if (m===13) days = etIsLeap(y)?6:5;
                for(let d=1; d<=days; d++){ const o=document.createElement('option'); o.value=String(d); o.textContent=String(d); dSel.appendChild(o); }
            }
            if (ySel && mSel && dSel){
                populateYears(); populateMonths(); populateDays();
                mSel.addEventListener('change', ()=>{ populateDays(); saveDraft(); });
                ySel.addEventListener('change', ()=>{ populateDays(); saveDraft(); });
            }
            // Restore draft values
            restoreDraft();
            // Re-apply ET birth date saved values after pop
            try {
                const et = JSON.parse(localStorage.getItem('INSTR_BIRTH_ET')||'{}');
                if (et && (et.y||et.m||et.d)){
                    if (et.y && ySel) ySel.value = et.y;
                    if (et.m && mSel) mSel.value = et.m;
                    populateDays();
                    if (et.d && dSel) dSel.value = et.d;
                }
            } catch(_) {}
            // Restore photo preview from localStorage
            try {
                const key = localStorage.getItem(TEMP_KEY);
                const url = localStorage.getItem(TEMP_URL);
                const dataUrl = localStorage.getItem(TEMP_DATAURL);
                const prev = document.getElementById('i-photo-preview');
                const ph = document.getElementById('i-photo-placeholder');
                const keyEl = document.getElementById('i_temp_photo_key');
                const dataEl = document.getElementById('i_temp_photo_dataurl');
                if (dataUrl || url){ if (prev){ prev.src = dataUrl||url; prev.classList.remove('hidden'); } if (ph) ph.classList.add('hidden'); }
                if (key && keyEl && !keyEl.value) keyEl.value = key;
                if (dataUrl && dataEl && !dataEl.value) dataEl.value = dataUrl;
            } catch(_) {}
            // Debounced autosave
            const deb = debounce(saveDraft, 200);
            document.getElementById('instrument-form').addEventListener('input', deb, {passive:true});
            document.getElementById('instrument-form').addEventListener('change', deb, {passive:true});
            // Photo input change: preview, compress, temp upload
            const input = document.getElementById('i_person_photo');
            async function compressDataUrl(dataUrl, maxDim=600, quality=0.7){
                return await new Promise(res=>{ const img=new Image(); img.onload=()=>{ const s=Math.min(1, maxDim/Math.max(img.width,img.height)); const c=document.createElement('canvas'); c.width=Math.round(img.width*s); c.height=Math.round(img.height*s); const ctx=c.getContext('2d'); ctx.drawImage(img,0,0,c.width,c.height); res(c.toDataURL('image/jpeg', quality)); }; img.src=dataUrl; });
            }
            function dataURLToFile(dataUrl, filename){ const arr=dataUrl.split(','); const mime=arr[0].match(/:(.*?);/)[1]; const b=atob(arr[1]); const u8=new Uint8Array(b.length); for(let i=0;i<b.length;i++) u8[i]=b.charCodeAt(i); return new File([u8], filename, {type:mime}); }
            async function uploadTemp(file, previewDataUrl){
                const fd = new FormData(); fd.append('photo', file);
                const res = await fetch('api/upload_temp_photo.php', { method:'POST', body: fd });
                const data = await res.json(); if (!data || !data.success || !data.key) throw new Error('Upload failed');
                const url = 'uploads/tmp/'+data.key;
                try { localStorage.setItem(TEMP_KEY, data.key); localStorage.setItem(TEMP_URL, url); if (previewDataUrl) localStorage.setItem(TEMP_DATAURL, previewDataUrl); } catch(_) {}
                const keyEl = document.getElementById('i_temp_photo_key'); if (keyEl) keyEl.value = data.key;
                const dataEl = document.getElementById('i_temp_photo_dataurl'); if (dataEl && previewDataUrl) dataEl.value = previewDataUrl;
            }
            async function handleFile(file){
                const prev = document.getElementById('i-photo-preview');
                const ph = document.getElementById('i-photo-placeholder');
                let purl = '';
                await new Promise((resolve)=>{ const fr=new FileReader(); fr.onload=e=>{ purl=String(e.target.result||''); if (prev){ prev.src=purl; prev.classList.remove('hidden'); } if (ph) ph.classList.add('hidden'); try{ localStorage.setItem(TEMP_DATAURL, purl);}catch(_){ } resolve(); }; fr.readAsDataURL(file); });
                try { const comp = await compressDataUrl(purl, 600, 0.7); await uploadTemp(dataURLToFile(comp, (file.name||'photo')+'.jpg'), comp); }
                catch(_) { try { await uploadTemp(file, purl); } catch(__){} }
            }
            if (input) input.addEventListener('change', function(){ if (input.files && input.files[0]) handleFile(input.files[0]); });
            // Persist preview on unload
            function persist(){ try{ const img=document.getElementById('i-photo-preview'); if (img && img.src) localStorage.setItem(TEMP_DATAURL, img.src);}catch(_){} }
            document.addEventListener('visibilitychange', ()=>{ if (document.visibilityState==='hidden') persist(); });
            window.addEventListener('beforeunload', persist);
        });
    })();
    // Apply form configuration (labels/placeholders/required)
    function applyInstrumentFormConfig(){
        try {
            const CFG = window.FORM_CFG_INSTRUMENT || {};
            function setLabelForInput(el, text){
                if (!el) return;
                // Find a label associated with this input (for/id or previous sibling)
                let label = null;
                if (el.id) label = document.querySelector('label[for="'+el.id+'"]');
                if (!label) {
                    label = el.closest('div') && el.closest('div').previousElementSibling && el.closest('div').previousElementSibling.tagName==='LABEL' ? el.closest('div').previousElementSibling : null;
                }
                if (!label) {
                    // fallback to immediate previous sibling
                    label = el.previousElementSibling && el.previousElementSibling.tagName==='LABEL' ? el.previousElementSibling : null;
                }
                if (label && text) label.textContent = text;
            }
            function setPlaceholder(el, ph){ if (el && typeof ph==='string' && ph!=='') el.placeholder = ph; }
            function setRequired(el, req){ if (el) el.required = !!req; }

            // Map keys to elements
            const map = {
                student_photo: document.getElementById('i_person_photo'),
                full_name: document.querySelector('input[name="full_name"]'),
                christian_name: document.querySelector('input[name="christian_name"]'),
                gender: document.querySelector('input[name="gender"]'), // radios handled by group
                birth_date_et: document.getElementById('i_birth_year'),
                phone_number: document.querySelector('input[name="phone_number"]'),
                sub_city: document.querySelector('input[name="sub_city"]'),
                district: document.querySelector('input[name="district"]'),
                specific_area: document.querySelector('input[name="specific_area"]'),
                house_number: document.querySelector('input[name="house_number"]'),
                emergency_name: document.querySelector('input[name="emergency_name"]'),
                emergency_phone: document.querySelector('input[name="emergency_phone"]'),
                emergency_alt_phone: document.querySelector('input[name="emergency_alt_phone"]'),
                emergency_address: document.querySelector('input[name="emergency_address"]'),
                instrument: document.getElementById('instrument-input'),
                has_spiritual_father: document.querySelector('input[name="has_spiritual_father"]'),
                spiritual_father_name: document.querySelector('input[name="spiritual_father_name"]'),
                spiritual_father_phone: document.querySelector('input[name="spiritual_father_phone"]'),
                spiritual_father_church: document.querySelector('input[name="spiritual_father_church"]')
            };

            Object.keys(CFG).forEach(key=>{
                const conf = CFG[key]||{}; const el = map[key];
                if (!el) return;
                // Labels
                if (conf.label) setLabelForInput(el, conf.label + (conf.required? ' *':'') );
                // Placeholders
                setPlaceholder(el, conf.placeholder||'');
                // Required
                if (key==='gender') {
                    document.querySelectorAll('input[name="gender"]').forEach(r=>setRequired(r, conf.required));
                } else if (key==='birth_date_et') {
                    setRequired(document.getElementById('i_birth_year'), conf.required);
                    setRequired(document.getElementById('i_birth_month'), conf.required);
                    setRequired(document.getElementById('i_birth_day'), conf.required);
                } else if (key==='has_spiritual_father') {
                    document.querySelectorAll('input[name="has_spiritual_father"]').forEach(r=>setRequired(r, conf.required));
                } else if (key==='instrument') {
                    // hidden input; requirement enforced via step validation
                } else {
                    setRequired(el, conf.required);
                }
            });
        } catch(_) {}
    }
        setTimeout(function(){
            var card = document.getElementById('error-card');
            if (card) card.remove();
        }, 2500);
        </script>
    <?php endif; ?>
    <?php
    if (isset($_SESSION['instrument_success'])): ?>
        <div id="success-toast" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 bg-green-100 border border-green-400 text-green-800 px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 animate-fade-in" style="min-width:300px;max-width:90vw;">
            <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="flex-1 text-base font-semibold">
                <?php if ($_SESSION['instrument_success'] === 'matched'): ?>
                    ተማሪው በመዝገቡ ተገኝቷል፣ ምዝገባ ተሳክቷል ።
                <?php else: ?>
                    አዲስ ምዝገባ ተሳክቷል።
                <?php endif; ?>
            </span>
            <button onclick="document.getElementById('success-toast').remove()" class="ml-4 text-green-600 hover:text-green-900 focus:outline-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .animate-fade-in { animation: fade-in 0.5s cubic-bezier(.4,0,.2,1); }
        </style>
        <script>
        setTimeout(function(){
            var toast = document.getElementById('success-toast');
            if (toast) toast.remove();
        }, 7000);
        </script>
        <?php unset($_SESSION['instrument_success']); ?>
    <?php endif; ?>
    <div class="container mx-auto px-4 py-8 max-w-3xl">
        <div class="flex flex-col items-center mb-6">
            <a href="welcome.php">
                <img src="uploads/689636ec11381_finot logo.png" alt="Finot Logo" class="w-24 h-24 md:w-32 md:h-32 rounded-full shadow-lg object-contain border-4 border-blue-200 dark:border-blue-800 bg-white mb-2 transition-all duration-300 hover:scale-105">
            </a>
            <h1 class="text-2xl md:text-3xl font-bold text-blue-800 mt-2">የዜማ መሳሪያ ምዝገባ</h1>
            <p class="text-gray-600 mt-1">እባክዎ የሚመርጡትን ዜማ መሳሪያ ይምረጡ እና መረጃዎትን ይሙሉ።</p>
            <div class="mt-2">
                <button type="button" onclick="clearInstrumentForm()" class="px-3 py-1 text-xs md:text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 rounded">ፎርም አጥራ</button>
            </div>
        </div>
        <!-- Progress indicator -->
        <div class="flex justify-between items-center mb-6 relative">
            <div class="absolute top-1/2 left-0 right-0 h-1 bg-gray-200 -z-10"></div>
            <div id="i-progress-bar" class="absolute top-1/2 left-0 h-1 bg-blue-500 -z-10" style="width: 0%"></div>
            <div class="progress-step" id="i-step-1">1</div>
            <div class="progress-step" id="i-step-2">2</div>
            <div class="progress-step" id="i-step-3">3</div>
            <div class="progress-step" id="i-step-4">4</div>
            <div class="progress-step" id="i-step-5">5</div>
            <div class="progress-step" id="i-step-6">6</div>
        </div>
        <form id="instrument-form" action="process_instrument.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6" onsubmit="return debugSubmit(event);" novalidate>
        <input type="hidden" name="temp_photo_key" id="i_temp_photo_key" value="">
        <input type="hidden" name="temp_photo_dataurl" id="i_temp_photo_dataurl" value="">

    <script>
    // Debugging submit handler
    function debugSubmit(event) {
        // Remove previous error message
        let prevError = document.getElementById('form-error-message');
        if (prevError) prevError.remove();
        // Only validate if on card 6
        if (iCurrent !== 6) return true;
        // Validate all required fields again
        if (!validateICard(6)) {
            const card = document.getElementById('i-card-6');
            const errorDiv = document.createElement('div');
            errorDiv.id = 'form-error-message';
            errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-center';
            errorDiv.textContent = 'እባክዎ መስኮቹን በትክክል ይሙሉ። (Please fill all required fields)';
            card.insertBefore(errorDiv, card.firstChild);
            event.preventDefault();
            return false;
        }
        // Check photo again (accept either selected file or existing temp key)
        var personPhoto = document.getElementById('i_person_photo');
        var tempKeyEl = document.getElementById('i_temp_photo_key');
        var hasTempKey = tempKeyEl && tempKeyEl.value;
        if ((!personPhoto || !personPhoto.files || !personPhoto.files[0]) && !hasTempKey) {
            const card = document.getElementById('i-card-6');
            const errorDiv = document.createElement('div');
            errorDiv.id = 'form-error-message';
            errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-center';
            errorDiv.textContent = 'ፎቶ ያስገቡ። (Please attach a photo)';
            card.insertBefore(errorDiv, card.firstChild);
            event.preventDefault();
            return false;
        }
        // Show a visible message that the form is submitting
        const card = document.getElementById('i-card-6');
        const infoDiv = document.createElement('div');
        infoDiv.id = 'form-info-message';
        infoDiv.className = 'bg-blue-100 border border-blue-400 text-blue-700 px-4 py-2 rounded mb-4 text-center';
        infoDiv.textContent = 'Submitting form...';
        card.insertBefore(infoDiv, card.firstChild);
        // Allow form to submit
        return true;
    }
    // Final validation on submit (step 6)
    function validateFinalSubmit() {
        // Remove previous error message
        let prevError = document.getElementById('form-error-message');
        if (prevError) prevError.remove();
        // Only validate if on card 6
        if (iCurrent !== 6) return true;
        // Validate all required fields again
        if (!validateICard(6)) {
            const card = document.getElementById('i-card-6');
            const errorDiv = document.createElement('div');
            errorDiv.id = 'form-error-message';
            errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-center';
            errorDiv.textContent = 'እባክዎ መስኮቹን በትክክል ይሙሉ። (Please fill all required fields)';
            card.insertBefore(errorDiv, card.firstChild);
            return false;
        }
        // Check photo again
        var personPhoto = document.getElementById('i_person_photo');
        if (!personPhoto || !personPhoto.files || !personPhoto.files[0]) {
            const card = document.getElementById('i-card-6');
            const errorDiv = document.createElement('div');
            errorDiv.id = 'form-error-message';
            errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-center';
            errorDiv.textContent = 'ፎቶ ያስገቡ። (Please attach a photo)';
            card.insertBefore(errorDiv, card.firstChild);
            return false;
        }
        return true;
    }
    </script>
    <!-- Unconditional ET birthdate init and restore (ensure it runs even without error toast) -->
    <script>
    (function(){
        function etIsLeap(y){ return ((y%4)===3); }
        function populateYears(sel){
            if (!sel) return;
            sel.innerHTML='';
            // Placeholder like registration.php
            var ph=document.createElement('option'); ph.value=''; ph.textContent='ዓመት'; sel.appendChild(ph);
            const approxEt=(new Date()).getFullYear()-8;
            for(let y=approxEt;y>=approxEt-30;y--){ const o=document.createElement('option'); o.value=String(y); o.textContent=String(y); sel.appendChild(o);} }
        function populateMonths(sel){
            if (!sel) return;
            sel.innerHTML='';
            // Ethiopian month names (like registration.php)
            var ph=document.createElement('option'); ph.value=''; ph.textContent='ወር'; sel.appendChild(ph);
            const monthNames=['መስከረም','ጥቅምት','ህዳር','ታኅሣስ','ጥር','የካቲት','መጋቢት','ሚያዚያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ','ጳጉሜን'];
            monthNames.forEach((name, idx)=>{ const o=document.createElement('option'); o.value=String(idx+1); o.textContent=name; sel.appendChild(o); });
        }
        function populateDays(ySel,mSel,dSel){
            if(!(ySel&&mSel&&dSel))return;
            dSel.innerHTML='';
            // Placeholder like registration.php
            var ph=document.createElement('option'); ph.value=''; ph.textContent='ቀን'; dSel.appendChild(ph);
            const y=parseInt(ySel.value||'0',10); const m=parseInt(mSel.value||'0',10);
            if (!y || !m) return;
            let days=30; if(m===13) days=etIsLeap(y)?6:5;
            for(let d=1; d<=days; d++){ const o=document.createElement('option'); o.value=String(d); o.textContent=String(d); dSel.appendChild(o);} }
        document.addEventListener('DOMContentLoaded', function(){
            const ySel=document.getElementById('i_birth_year');
            const mSel=document.getElementById('i_birth_month');
            const dSel=document.getElementById('i_birth_day');
            if (!(ySel&&mSel&&dSel)) return;
            // Only populate if empty
            if (!ySel.options.length) populateYears(ySel);
            if (!mSel.options.length) populateMonths(mSel);
            populateDays(ySel,mSel,dSel);
            mSel.addEventListener('change', function(){ populateDays(ySel,mSel,dSel); try{ const et=JSON.stringify({y:ySel.value,m:mSel.value,d:dSel.value}); localStorage.setItem('INSTR_BIRTH_ET', et);}catch(_){}});
            ySel.addEventListener('change', function(){ populateDays(ySel,mSel,dSel); try{ const et=JSON.stringify({y:ySel.value,m:mSel.value,d:dSel.value}); localStorage.setItem('INSTR_BIRTH_ET', et);}catch(_){}});
            dSel.addEventListener('change', function(){ try{ const et=JSON.stringify({y:ySel.value,m:mSel.value,d:dSel.value}); localStorage.setItem('INSTR_BIRTH_ET', et);}catch(_){}});
            // Restore saved values
            try {
                const et=JSON.parse(localStorage.getItem('INSTR_BIRTH_ET')||'{}');
                if (et && (et.y||et.m||et.d)){
                    if (et.y) ySel.value = et.y;
                    if (et.m) mSel.value = et.m;
                    populateDays(ySel,mSel,dSel);
                    if (et.d) dSel.value = et.d;
                    // Retry applying day until options are present (race-proof)
                    let tries = 0; const maxTries = 30;
                    const timer = setInterval(()=>{
                        tries++;
                        const hasDays = dSel.options && dSel.options.length > 1;
                        if (hasDays) { if (et.d) dSel.value = et.d; clearInterval(timer); }
                        else if (tries >= maxTries) { clearInterval(timer); }
                    }, 100);
                    // Observe for late child insertions
                    if (window.MutationObserver) {
                        const obs = new MutationObserver(()=>{
                            const hasDays = dSel.options && dSel.options.length > 1;
                            if (hasDays) { if (et.d) dSel.value = et.d; try { obs.disconnect(); } catch(_) {} }
                        });
                        try { obs.observe(dSel, { childList: true }); } catch(_) {}
                    }
                }
            } catch(_) {}
        });
    })();
    </script>
            <!-- Card 1: Instrument Selection & Person Photo -->
            <div class="form-card active" id="i-card-1">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">1. የዜማ መሳሪያ ምርጫ</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="instrument-card border-2 p-3 flex flex-col items-center" data-instrument="begena" onclick="selectInstrument('begena')">
                        <img src="images/4.jpeg" alt="በገና" class="instrument-img mb-2">
                        <span class="font-semibold">በገና</span>
                    </div>
                    <div class="instrument-card border-2 p-3 flex flex-col items-center" data-instrument="masenqo" onclick="selectInstrument('masenqo')">
                        <img src="images/1.jpeg" alt="መሰንቆ" class="instrument-img mb-2">
                        <span class="font-semibold">መሰንቆ</span>
                    </div>
                    <div class="instrument-card border-2 p-3 flex flex-col items-center" data-instrument="kebero" onclick="selectInstrument('kebero')">
                        <img src="images/3.jpeg" alt="ከበሮ" class="instrument-img mb-2">
                        <span class="font-semibold">ከበሮ</span>
                    </div>
                    <div class="instrument-card border-2 p-3 flex flex-col items-center" data-instrument="krar" onclick="selectInstrument('krar')">
                        <img src="images/2.jpeg" alt="ክራር" class="instrument-img mb-2">
                        <span class="font-semibold">ክራር</span>
                    </div>
                </div>
                <input type="hidden" name="instrument" id="instrument-input" required>
                    <!-- Coming soon card for Krar -->
                    <div id="krar-coming-soon" class="hidden mt-4">
                        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded shadow flex items-center">
                            <svg class="w-8 h-8 mr-3 text-yellow-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/></svg>
                            <div>
                                <div class="font-bold text-lg mb-1">የክራር ትምህርት ለጊዚው እየሰጠን አይደለም።</div>
                                <div class="text-sm">ይህ ባለመጠቀም ላይ ያለ አገልግሎት ነው። በቅርቡ ይመጣል።</div>
                            </div>
                        </div>
                    </div>
                <div class="flex justify-between mt-6">
                    <button type="button" class="px-5 py-2 bg-gray-200 text-gray-700 rounded" disabled>ቀድሞ</button>
                    <button type="button" onclick="iNext(2)" class="px-5 py-2 bg-blue-600 text-white rounded">ቀጣይ</button>
                </div>
            </div>
            <!-- Card 2: Personal Info (like youth) -->
            <div class="form-card" id="i-card-2">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">2. የተማሪ መረጃ</h2>
                <!-- Person Photo Section (like youth) -->
                <label class="block text-gray-700 mb-1" for="i_person_photo">የተማሪ ፎቶ <span class="text-red-500">*</span></label>
                <div class="flex items-center mb-2">
                    <div class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                        <img id="i-photo-preview" src="" alt="" class="hidden w-full h-full object-cover">
                        <span id="i-photo-placeholder" class="text-gray-500">ፎቶ</span>
                    </div>
                    <input type="file" id="i_person_photo" name="person_photo" accept="image/*" class="ml-4 hidden" required>
                    <button type="button" onclick="document.getElementById('i_person_photo').click()" class="ml-4 px-3 py-1 text-xs md:text-sm bg-blue-500 text-white rounded hover:bg-blue-600">ፎቶ ይምረጡ</button>
                    <button type="button" onclick="openICameraModal()" class="ml-2 px-3 py-1 text-xs md:text-sm bg-green-500 text-white rounded hover:bg-green-600">ፎቶ አንሳ</button>
                </div>
                <!-- Camera Modal -->
                <div id="i-camera-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                    <div class="bg-white rounded-lg p-6 shadow-lg relative w-80">
                        <button type="button" onclick="closeICameraModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">&times;</button>
                        <video id="i-camera-video" autoplay playsinline class="w-full h-48 bg-gray-200 rounded"></video>
                        <canvas id="i-camera-canvas" class="hidden"></canvas>
                        <div class="flex justify-between mt-4">
                            <button type="button" onclick="switchICamera()" class="px-3 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Switch Camera</button>
                            <button type="button" onclick="captureIPhoto()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">ፎቶ አንሳ</button>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-1">JPEG/PNG, 5MB እስከ.</p>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1">ሙሉ ስም እስከ አያት <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" class="w-full px-3 py-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1">የክርስትና ስም <span class="text-red-500">*</span></label>
                    <input type="text" name="christian_name" class="w-full px-3 py-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1">ጾታ <span class="text-red-500">*</span></label>
                    <div class="flex gap-6">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="gender" value="male" required> ወንድ</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="gender" value="female"> ሴት</label>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1">የተወለዱበት ቀን (ዓ.ም) <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <select id="i_birth_year" name="birth_year_et" class="px-3 py-2 border rounded" required></select>
                        <select id="i_birth_month" name="birth_month_et" class="px-3 py-2 border rounded" required></select>
                        <select id="i_birth_day" name="birth_day_et" class="px-3 py-2 border rounded" required></select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1">የስልክ ቁጥር <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone_number" class="w-full px-3 py-2 border rounded" placeholder="09XXXXXXXX" pattern="^(\+?251|0)9\d{8}$" required>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" onclick="iPrev(1)" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">ቀድሞ</button>
                    <button type="button" onclick="iNext(3)" class="px-5 py-2 bg-blue-600 text-white rounded">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 3: Spiritual Father -->
            <div class="form-card" id="i-card-3">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">3. የንስሐ አባት</h2>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">የንስሐ አባት አለህ/ሽ?</label>
                    <div class="space-y-2">
                        <label class="inline-flex items-center">
                            <input type="radio" name="has_spiritual_father" value="own" class="h-5 w-5 text-blue-600" onchange="toggleISpiritualFather(true)">
                            <span class="ml-2">የራሴ አለኝ</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="has_spiritual_father" value="family" class="h-5 w-5 text-blue-600" onchange="toggleISpiritualFather(true)">
                            <span class="ml-2">የቤተሰብ (የጋራ)</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="has_spiritual_father" value="none" class="h-5 w-5 text-blue-600" onchange="toggleISpiritualFather(false)">
                            <span class="ml-2">የለኝም</span>
                        </label>
                    </div>
                </div>
                <div id="i-spiritual-father-info" class="hidden space-y-3">
                    <h3 class="text-md font-semibold text-gray-700">የንስሐ አባት መረጃ</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-gray-700 mb-1">የካህኑ ስም</label>
                            <input type="text" name="spiritual_father_name" class="w-full px-3 py-2 border rounded">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-1">የካህኑ ስልክ ቁጥር</label>
                            <input type="tel" name="spiritual_father_phone" class="w-full px-3 py-2 border rounded" placeholder="09XXXXXXXX">
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">ካህኑ የሚያገለግሉበት ደብር</label>
                        <input type="text" name="spiritual_father_church" class="w-full px-3 py-2 border rounded">
                    </div>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" onclick="iPrev(2)" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">ቀድሞ</button>
                    <button type="button" onclick="iNext(4)" class="px-5 py-2 bg-blue-600 text-white rounded">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 4: Address -->
            <div class="form-card" id="i-card-4">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">4. የመኖሪያ አድራሻ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">ክ/ከተማ</label>
                        <input type="text" name="sub_city" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">ወረዳ</label>
                        <input type="text" name="district" class="w-full px-3 py-2 border rounded">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">የሰፈሩ ልዩ ስም</label>
                        <input type="text" name="specific_area" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">የቤት ቁጥር</label>
                        <input type="text" name="house_number" class="w-full px-3 py-2 border rounded">
                    </div>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" onclick="iPrev(3)" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">ቀድሞ</button>
                    <button type="button" onclick="iNext(5)" class="px-5 py-2 bg-blue-600 text-white rounded">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 5: Emergency Contact -->
            <div class="form-card" id="i-card-5">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">5. የአደጋ ጊዜ ተጠሪ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">ሙሉ ስም እስከ አያት</label>
                        <input type="text" name="emergency_name" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">ስልክ ቁጥር</label>
                        <input type="tel" name="emergency_phone" class="w-full px-3 py-2 border rounded" placeholder="09XXXXXXXX" pattern="^(\+?251|0)9\d{8}$">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 mb-1">ተዋጭ ስልክ ቁጥር</label>
                        <input type="tel" name="emergency_alt_phone" class="w-full px-3 py-2 border rounded" placeholder="09XXXXXXXX" pattern="^(\+?251|0)9\d{8}$">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">አድራሻ</label>
                        <input type="text" name="emergency_address" class="w-full px-3 py-2 border rounded">
                    </div>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" onclick="iPrev(4)" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">ቀድሞ</button>
                    <button type="button" onclick="iNext(6)" class="px-5 py-2 bg-blue-600 text-white rounded">ቀጣይ</button>
                </div>
            </div>
            
            <!-- Card 6: Confirmation/Submit -->
            <div class="form-card" id="i-card-6">
                <h2 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">6. ማጠቃለያ</h2>
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">ማረጋገጫ</h3>
                    <div class="bg-gray-50 p-4 rounded">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <strong>የተመረጠ መሳሪያ:</strong> <span id="confirm-instrument"></span>
                            </div>
                            <div>
                                <strong>ሙሉ ስም:</strong> <span id="confirm-name"></span>
                            </div>
                            <div>
                                <strong>የክርስትና ስም:</strong> <span id="confirm-christian"></span>
                            </div>
                            <div>
                                <strong>ስልክ ቁጥር:</strong> <span id="confirm-phone"></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-center text-sm text-gray-600 mt-3">እባክዎ መረጃዎትን ያረጋግጡ እና ምዝገባ አስገባ የሚለውን ይጫኑ።</div>
                </div>
                <div class="flex justify-between mt-6">
                    <button type="button" onclick="iPrev(5)" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">ቀድሞ</button>
                    <button type="submit" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded">ምዝገባ አስገባ</button>
                </div>
            </div>
        </form>
    </div>
    <script>
    // Instrument selection logic
    function selectInstrument(val) {
        document.getElementById('instrument-input').value = val;
        document.querySelectorAll('.instrument-card').forEach(card => {
            card.classList.remove('selected');
            if (card.getAttribute('data-instrument') === val) card.classList.add('selected');
        });
            // Show coming soon card for krar
            var krarCard = document.getElementById('krar-coming-soon');
            var nextBtn = document.querySelector('#i-card-1 button[onclick*="iNext"]');
            if (val === 'krar') {
                if (krarCard) krarCard.classList.remove('hidden');
                if (nextBtn) nextBtn.disabled = true;
            } else {
                if (krarCard) krarCard.classList.add('hidden');
                if (nextBtn) nextBtn.disabled = false;
            }
    }
    // Card navigation and progress
    let iCurrent = 1, iTotal = 6;
    function updateIProgress() {
        const pct = ((iCurrent-1)/(iTotal-1))*100;
        document.getElementById('i-progress-bar').style.width = pct+'%';
        for(let i=1;i<=iTotal;i++){
            const step = document.getElementById('i-step-'+i);
            step.classList.remove('active','completed');
            if(i<iCurrent) step.classList.add('completed');
            else if(i===iCurrent) step.classList.add('active');
        }
    }
    function showICard(n){
        document.getElementById('i-card-'+iCurrent).classList.remove('active');
        document.getElementById('i-card-'+n).classList.add('active');
        iCurrent = n; updateIProgress(); window.scrollTo(0,0);
    }
    function iNext(n){
        // Remove previous error message
        let prevError = document.getElementById('form-error-message');
        if (prevError) prevError.remove();
        if(!validateICard(iCurrent)) {
            // Show error message if validation fails
            const card = document.getElementById('i-card-'+iCurrent);
            const errorDiv = document.createElement('div');
            errorDiv.id = 'form-error-message';
            errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-center';
            errorDiv.textContent = 'እባክዎ መስኮቹን በትክክል ይሙሉ። (Please fill all required fields)';
            card.insertBefore(errorDiv, card.firstChild);
            return;
        }
        // Update confirmation on step 6
        if (n === 6) {
            updateConfirmation();
        }
        showICard(n);
    }
    function updateConfirmation() {
        const instrument = document.getElementById('instrument-input').value;
        const instrumentName = {
            'begena': 'በገና',
            'masenqo': 'መሰንቆ', 
            'kebero': 'ከበሮ',
            'krar': 'ክራር'
        }[instrument] || instrument;
        
        document.getElementById('confirm-instrument').textContent = instrumentName;
        document.getElementById('confirm-name').textContent = document.querySelector('input[name="full_name"]').value || '-';
        document.getElementById('confirm-christian').textContent = document.querySelector('input[name="christian_name"]').value || '-';
        document.getElementById('confirm-phone').textContent = document.querySelector('input[name="phone_number"]').value || '-';
    }
    function iPrev(n){ showICard(n); }
    
    // Toggle spiritual father information display
    function toggleISpiritualFather(show) {
        const infoDiv = document.getElementById('i-spiritual-father-info');
        if (show) {
            infoDiv.classList.remove('hidden');
        } else {
            infoDiv.classList.add('hidden');
            // Clear the fields when hiding
            const inputs = infoDiv.querySelectorAll('input');
            inputs.forEach(input => input.value = '');
        }
    }
    function validateICard(n){
        const card = document.getElementById('i-card-'+n);
        const required = card.querySelectorAll('[required]');
        let ok = true;
        for (var i = 0; i < required.length; i++) {
            var el = required[i];
            var valid = true;
            if (el.type === 'radio') {
                var any = card.querySelector('input[name="'+el.name+'"]:checked');
                valid = !!any;
            } else if (el.type === 'file') {
                var tempKeyEl = document.getElementById('i_temp_photo_key');
                var hasTempKey = tempKeyEl && tempKeyEl.value;
                valid = (el.files && el.files.length > 0) || hasTempKey;
            } else if (el.tagName === 'SELECT' || el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                valid = !!String(el.value || '').trim();
            }
            if (!valid) {
                ok = false;
                try { el.classList.add('border-red-500'); } catch(_) {}
            } else {
                try { el.classList.remove('border-red-500'); } catch(_) {}
            }
        }
        // Instrument required on Card 1
        if (n === 1 && !document.getElementById('instrument-input').value) {
            ok = false;
        }
        // All validations for final step
        if (n === 6) {
            var personPhoto = document.getElementById('i_person_photo');
            var tempKeyEl = document.getElementById('i_temp_photo_key');
            var hasTempKey = tempKeyEl && tempKeyEl.value;
            if ((!personPhoto || !personPhoto.files || !personPhoto.files[0]) && !hasTempKey) ok = false;
        }
        return ok;
    }
    // Ethiopian calendar dropdowns
    document.addEventListener('DOMContentLoaded', function(){
        applyInstrumentFormConfig();
        const ySel = document.getElementById('i_birth_year');
        const mSel = document.getElementById('i_birth_month');
        const dSel = document.getElementById('i_birth_day');
        const months = ['መስከረም','ጥቅምት','ሕዳር','ታህሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ','ጳጉሜ'];
        function getEY(){ const t=new Date(); const Y=t.getFullYear(), M=t.getMonth()+1, D=t.getDate(); let e=Y-8; if(M>9||(M===9&&D>=11)) e=Y-7; return e; }
        function isLeap(ey){ return ey%4===3; }
        function dim(ey,em){ return em>=1&&em<=12?30:(isLeap(ey)?6:5); }
        function fillYears(){ const cur=getEY(); ySel.innerHTML=''; const ph=document.createElement('option'); ph.value=''; ph.textContent='ዓመት'; ySel.appendChild(ph); for(let y=cur;y>=cur-40;y--){ const o=document.createElement('option'); o.value=y; o.textContent=y; ySel.appendChild(o);} }
        function fillMonths(){ mSel.innerHTML=''; const ph=document.createElement('option'); ph.value=''; ph.textContent='ወር'; mSel.appendChild(ph); months.forEach((n,i)=>{ const o=document.createElement('option'); o.value=i+1; o.textContent=n; mSel.appendChild(o);}); }
        function fillDays(){ dSel.innerHTML=''; const ph=document.createElement('option'); ph.value=''; ph.textContent='ቀን'; dSel.appendChild(ph); const ey=parseInt(ySel.value,10); const em=parseInt(mSel.value,10); if(!ey||!em) return; const count=dim(ey,em||1); for(let d=1; d<=count; d++){ const o=document.createElement('option'); o.value=d; o.textContent=d; dSel.appendChild(o);} }
        fillYears(); fillMonths(); ySel.addEventListener('change', fillDays); mSel.addEventListener('change', fillDays); fillDays();

        // Restore saved autosave data
        try {
            const saved = JSON.parse(localStorage.getItem('INSTRUMENT_FORM_AUTOSAVE')||'{}');
            if (saved.instrument) { selectInstrument(saved.instrument); }
            const map = {
                full_name: 'input[name="full_name"]',
                christian_name: 'input[name="christian_name"]',
                gender: 'input[name="gender"]',
                birth_year_et: '#i_birth_year',
                birth_month_et: '#i_birth_month',
                birth_day_et: '#i_birth_day',
                phone_number: 'input[name="phone_number"]',
                has_spiritual_father: 'input[name="has_spiritual_father"]',
                spiritual_father_name: 'input[name="spiritual_father_name"]',
                spiritual_father_phone: 'input[name="spiritual_father_phone"]',
                spiritual_father_church: 'input[name="spiritual_father_church"]',
                sub_city: 'input[name="sub_city"]',
                district: 'input[name="district"]',
                specific_area: 'input[name="specific_area"]',
                house_number: 'input[name="house_number"]',
                emergency_name: 'input[name="emergency_name"]',
                emergency_phone: 'input[name="emergency_phone"]',
                emergency_alt_phone: 'input[name="emergency_alt_phone"]',
                emergency_address: 'input[name="emergency_address"]',
                temp_photo_key: '#i_temp_photo_key'
            };
            if (saved.birth_year_et) ySel.value = saved.birth_year_et;
            if (saved.birth_month_et) mSel.value = saved.birth_month_et;
            fillDays();
            if (saved.birth_day_et) dSel.value = saved.birth_day_et;
            Object.keys(map).forEach(k=>{
                const sel = map[k];
                if (k === 'gender' && saved[k]) {
                    const radio = document.querySelector(sel+`[value="${saved[k]}"]`);
                    if (radio) radio.checked = true;
                } else if (k === 'has_spiritual_father' && saved[k]) {
                    const radio = document.querySelector(sel+`[value="${saved[k]}"]`);
                    if (radio) { radio.checked = true; toggleISpiritualFather(saved[k]!=="none"); }
                } else if (sel) {
                    const el = document.querySelector(sel);
                    if (el && typeof saved[k] !== 'undefined') el.value = saved[k];
                }
            });
            if (saved.temp_photo_key) {
                const tempKeyEl = document.getElementById('i_temp_photo_key');
                const preview = document.getElementById('i-photo-preview');
                const placeholder = document.getElementById('i-photo-placeholder');
                if (tempKeyEl) tempKeyEl.value = saved.temp_photo_key;
                if (preview && placeholder) {
                    preview.src = 'uploads/tmp/' + saved.temp_photo_key;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                }
            }
        } catch(_) {}
    });
    // Person photo preview (bind directly, not inside unrelated event)
    document.addEventListener('DOMContentLoaded', function(){
        const input = document.getElementById('i_person_photo');
        const preview = document.getElementById('i-photo-preview');
        const placeholder = document.getElementById('i-photo-placeholder');
        const tempKeyEl = document.getElementById('i_temp_photo_key');
        if (input) {
            input.addEventListener('change', function(){
                if (input.files && input.files[0]){
                    const reader = new FileReader();
                    reader.onload = function(ev){
                        preview.src = ev.target.result;
                        preview.classList.remove('hidden');
                        placeholder.classList.add('hidden');
                    }
                    reader.readAsDataURL(input.files[0]);
                    const fd = new FormData();
                    fd.append('photo', input.files[0]);
                    fetch('api/upload_temp_photo.php', { method: 'POST', body: fd })
                        .then(r=>r.json())
                        .then(j=>{ if (j && j.success && j.key) { tempKeyEl.value = j.key; autosave(); } });
                }
            });
        }
    });
    // Camera logic for person photo (instrument registration)
    let iCameraStream = null;
    let iFacing = 'user';
    function openICameraModal(){
        document.getElementById('i-camera-modal').classList.remove('hidden');
        startICamera(iFacing);
    }
    function closeICameraModal(){
        document.getElementById('i-camera-modal').classList.add('hidden');
        const video = document.getElementById('i-camera-video');
        if (iCameraStream){ iCameraStream.getTracks().forEach(t=>t.stop()); iCameraStream=null; }
        video.srcObject = null;
    }
    function startICamera(facing){
        const video = document.getElementById('i-camera-video');
        if (iCameraStream){ iCameraStream.getTracks().forEach(t=>t.stop()); iCameraStream=null; }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: facing }})
        .then(stream => { iCameraStream = stream; video.srcObject = stream; })
        .catch(()=>{ alert('Camera access denied or not available.'); closeICameraModal(); });
    }
    function switchICamera(){ iFacing = (iFacing==='user'?'environment':'user'); startICamera(iFacing); }
    function captureIPhoto(){
        const video = document.getElementById('i-camera-video');
        const canvas = document.getElementById('i-camera-canvas');
        const preview = document.getElementById('i-photo-preview');
        const placeholder = document.getElementById('i-photo-placeholder');
        const input = document.getElementById('i_person_photo');
        const tempKeyEl = document.getElementById('i_temp_photo_key');
        canvas.width = video.videoWidth || 320;
        canvas.height = video.videoHeight || 240;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/jpeg');
        // preview
        preview.src = dataUrl; preview.classList.remove('hidden'); placeholder.classList.add('hidden');
        // convert to file and attach to input
        fetch(dataUrl).then(r=>r.arrayBuffer()).then(buf=>{
            const file = new File([buf], 'captured_photo.jpg', { type: 'image/jpeg' });
            const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
            const fd = new FormData();
            fd.append('photo', file);
            fetch('api/upload_temp_photo.php', { method: 'POST', body: fd })
                .then(r=>r.json())
                .then(j=>{ if (j && j.success && j.key) { tempKeyEl.value = j.key; autosave(); } });
        });
        closeICameraModal();
    }
    // Clear form mid-registration
    function clearInstrumentForm(){
        if (!confirm('በእርግጥ ፎርሙን ሙሉ በሙሉ ማጥፋት ትፈልጋለህ/ሽ?')) return;
        try { localStorage.removeItem('INSTRUMENT_FORM_AUTOSAVE'); } catch(_) {}
        const form = document.getElementById('instrument-form');
        if (form) form.reset();
        const tempKeyEl = document.getElementById('i_temp_photo_key');
        if (tempKeyEl) tempKeyEl.value = '';
        const input = document.getElementById('i_person_photo');
        if (input) input.value = '';
        const preview = document.getElementById('i-photo-preview');
        const placeholder = document.getElementById('i-photo-placeholder');
        if (preview && placeholder) { preview.src=''; preview.classList.add('hidden'); placeholder.classList.remove('hidden'); }
        // Deselect instrument
        const instInput = document.getElementById('instrument-input');
        if (instInput) instInput.value='';
        document.querySelectorAll('.instrument-card').forEach(c=>c.classList.remove('selected'));
        const cs = document.getElementById('krar-coming-soon'); if (cs) cs.classList.add('hidden');
        const nextBtn = document.querySelector('#i-card-1 button[onclick*="iNext"]'); if (nextBtn) nextBtn.disabled = false;
        // Hide spiritual father info
        const infoDiv = document.getElementById('i-spiritual-father-info'); if (infoDiv) { infoDiv.classList.add('hidden'); infoDiv.querySelectorAll('input').forEach(i=>i.value=''); }
        // Go back to first card
        try { showICard(1); } catch(_) {}
    }
    // Autosave on input changes
    document.addEventListener('input', function(e){ autosave(); });
    document.addEventListener('change', function(e){ autosave(); });
    function autosave(){
        try {
            const data = {
                instrument: document.getElementById('instrument-input').value,
                full_name: (document.querySelector('input[name="full_name"]')||{}).value || '',
                christian_name: (document.querySelector('input[name="christian_name"]')||{}).value || '',
                gender: (document.querySelector('input[name="gender"]:checked')||{}).value || '',
                birth_year_et: (document.getElementById('i_birth_year')||{}).value || '',
                birth_month_et: (document.getElementById('i_birth_month')||{}).value || '',
                birth_day_et: (document.getElementById('i_birth_day')||{}).value || '',
                phone_number: (document.querySelector('input[name="phone_number"]')||{}).value || '',
                has_spiritual_father: (document.querySelector('input[name="has_spiritual_father"]:checked')||{}).value || '',
                spiritual_father_name: (document.querySelector('input[name="spiritual_father_name"]')||{}).value || '',
                spiritual_father_phone: (document.querySelector('input[name="spiritual_father_phone"]')||{}).value || '',
                spiritual_father_church: (document.querySelector('input[name="spiritual_father_church"]')||{}).value || '',
                sub_city: (document.querySelector('input[name="sub_city"]')||{}).value || '',
                district: (document.querySelector('input[name="district"]')||{}).value || '',
                specific_area: (document.querySelector('input[name="specific_area"]')||{}).value || '',
                house_number: (document.querySelector('input[name="house_number"]')||{}).value || '',
                emergency_name: (document.querySelector('input[name="emergency_name"]')||{}).value || '',
                emergency_phone: (document.querySelector('input[name="emergency_phone"]')||{}).value || '',
                emergency_alt_phone: (document.querySelector('input[name="emergency_alt_phone"]')||{}).value || '',
                emergency_address: (document.querySelector('input[name="emergency_address"]')||{}).value || '',
                temp_photo_key: (document.getElementById('i_temp_photo_key')||{}).value || ''
            };
            localStorage.setItem('INSTRUMENT_FORM_AUTOSAVE', JSON.stringify(data));
        } catch(_) {}
    }
    </script>
</body>
</html>
