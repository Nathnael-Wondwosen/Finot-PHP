<?php
session_start();
require 'config.php';
require 'includes/students_helpers.php';
require 'includes/admin_layout.php';
require 'includes/security_helpers.php';

requireAdminLogin();
$admin_id = $_SESSION['admin_id'] ?? 1;

header('X-Content-Type-Options: nosniff');

// Utilities
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Ensure storage table exists (id, admin_id, grade, min_age, max_age, updated_at)
function ensure_ranges_table($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS allocation_ranges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        grade VARCHAR(32) NOT NULL,
        min_age INT NULL,
        max_age INT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_admin (admin_id),
        KEY idx_grade (grade)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Normalize grade label to a canonical safe set
// Returns canonical string like 'new','1st','2nd',...,'12th' or null if unrecognized
function normalize_grade_label($label) {
    if ($label === null) return null;
    $g = strtolower(trim((string)$label));
    // allowed canonical set (DB values)
    $allowed = [
        'new','1st','2nd','3rd','4th','5th','6th',
        '7th-youth','8th-youth','7th-adult','8th-adult',
        '7th','8th','9th','10th','11th','12th',
        // also accept age-group labels used elsewhere in the app
        'under','youth'
    ];
    if ($g === '' || $g === '0' || $g === 'new') return 'new';
    $norm = str_replace(['grade', 'class'], '', $g);
    $norm = preg_replace('/\s+/', ' ', $norm);
    $norm = trim($norm);
    // try to detect youth/adult stream
    $hasYouth = strpos($norm, 'youth') !== false;
    $hasAdult = strpos($norm, 'adult') !== false;
    $digits = null;
    if (preg_match('/\b(1[0-2]|[1-9])\b/', $norm, $m)) { $digits = (int)$m[1]; }
    if ($digits !== null) {
        // build base like 7th
        $n = $digits;
        $suffix = 'th';
        if ($n % 10 == 1 && $n != 11) $suffix = 'st';
        elseif ($n % 10 == 2 && $n != 12) $suffix = 'nd';
        elseif ($n % 10 == 3 && $n != 13) $suffix = 'rd';
        $base = $n . $suffix;
        // attach stream only for 7th/8th when present
        if (($n === 7 || $n === 8) && ($hasYouth || $hasAdult)) {
            $cand = $base . '-' . ($hasYouth ? 'youth' : 'adult');
            if (in_array($cand, $allowed, true)) return $cand;
        }
        // otherwise return base if allowed
        if (in_array($base, $allowed, true)) return $base;
    }
    // accept exact allowed tokens (e.g., already canonical)
    $g2 = str_replace([' ', '_'], '-', $g);
    if (in_array($g2, $allowed, true)) return $g2;
    return null;
}

// Define what "new" students means
// Prefer explicit DB flag is_new_registration when available; fallback to current_grade heuristic
function is_new_student_row($row) {
    if (isset($row['is_new_registration'])) {
        $flag = (int)($row['is_new_registration']);
        if ($flag === 1) return true;
    }
    $grade = trim((string)($row['current_grade'] ?? ''));
    if ($grade === '' || strtolower($grade) === 'new' || $grade === '0') return true;
    return false;
}

function compute_et_age_for_row($row) {
    $bd = $row['birth_date'] ?? '';
    $age = ethiopian_age_from_string($bd);
    return $age; // may be null
}

// POST: AJAX endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Rate limit basic (reuse students.php pattern but simpler here)
    if (!checkRateLimit('allocation_ajax', 20, 60)) {
        json_response(['success' => false, 'message' => 'Rate limit exceeded. Try again shortly.'], 429);
    }

    // Parse ranges JSON (expected from client)
    $ranges_json = $_POST['ranges'] ?? '[]';
    $filter_new_only = isset($_POST['filter_new_only']) ? (($_POST['filter_new_only']==='true'||$_POST['filter_new_only']==='1') ? true : false) : true;

    $ranges = json_decode($ranges_json, true);
    if (!is_array($ranges)) $ranges = [];

    // Normalize ranges: [{grade, min|null, max}] where max is required; if min is empty, treat as exact-age rule (age == max)
    $norm_ranges = [];
    foreach ($ranges as $r) {
        $grade = isset($r['grade']) ? trim((string)$r['grade']) : '';
        $minStr = isset($r['min']) ? trim((string)$r['min']) : '';
        $maxStr = isset($r['max']) ? trim((string)$r['max']) : '';
        if ($grade === '' || $maxStr === '' || !is_numeric($maxStr)) continue;
        $max = (int)$maxStr;
        $min = null;
        if ($minStr !== '' && is_numeric($minStr)) {
            $min = (int)$minStr;
            if ($min > $max) continue; // invalid interval
        }
        $norm_ranges[] = ['grade' => $grade, 'min' => $min, 'max' => $max];
    }

    if ($action === 'get_ranges') {
        try {
            ensure_ranges_table($pdo);
            $stmt = $pdo->prepare("SELECT grade, min_age as min, max_age as max FROM allocation_ranges WHERE (admin_id = ? OR admin_id IS NULL) ORDER BY grade ASC");
            $stmt->execute([$admin_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success' => true, 'ranges' => $rows]);
        } catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    if ($action === 'save_ranges') {
        try {
            ensure_ranges_table($pdo);
            $ranges_json = $_POST['ranges'] ?? '[]';
            $ranges = json_decode($ranges_json, true);
            if (!is_array($ranges)) $ranges = [];
            // normalize & validate using same logic: max required; min optional
            $to_save = [];
            foreach ($ranges as $r) {
                $grade = isset($r['grade']) ? trim((string)$r['grade']) : '';
                $minStr = isset($r['min']) ? trim((string)$r['min']) : '';
                $maxStr = isset($r['max']) ? trim((string)$r['max']) : '';
                if ($grade === '' || $maxStr === '' || !is_numeric($maxStr)) continue;
                $max = (int)$maxStr; $min = null;
                if ($minStr !== '' && is_numeric($minStr)) {
                    $min = (int)$minStr; if ($min > $max) continue;
                }
                $to_save[] = ['grade' => $grade, 'min' => $min, 'max' => $max];
            }
            $pdo->beginTransaction();
            // Clear previous for this admin scope
            $del = $pdo->prepare("DELETE FROM allocation_ranges WHERE admin_id = ?");
            $del->execute([$admin_id]);
            if (!empty($to_save)) {
                $ins = $pdo->prepare("INSERT INTO allocation_ranges (admin_id, grade, min_age, max_age) VALUES (?, ?, ?, ?)");
                foreach ($to_save as $r) {
                    $ins->execute([$admin_id, $r['grade'], $r['min'], $r['max']]);
                }
            }
            $pdo->commit();
            json_response(['success' => true, 'saved' => count($to_save)]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    if ($action === 'preview_allocation') {
        try {
            // Fetch all candidates; filter by new flag in PHP to support explicit is_new_registration
            $stmt = $pdo->prepare("SELECT s.* FROM students s ORDER BY s.created_at DESC");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute total count of 'new' students via PHP filter (supports is_new_registration flag)
            $total_new = 0;
            foreach ($rows as $r0) { if (is_new_student_row($r0)) $total_new++; }

            $out = [];
            foreach ($rows as $row) {
                if ($filter_new_only && !is_new_student_row($row)) continue;
                $age = compute_et_age_for_row($row);
                if ($age === null) {
                    $recommended = null; // unknown
                } else {
                    // Match first range that fits. If min is null -> exact age match (age == max). Else inclusive interval.
                    $recommended = null;
                    foreach ($norm_ranges as $rg) {
                        if ($rg['min'] === null) {
                            if ($age === (int)$rg['max']) { $recommended = $rg['grade']; break; }
                        } else {
                            if ($age >= (int)$rg['min'] && $age <= (int)$rg['max']) { $recommended = $rg['grade']; break; }
                        }
                    }
                }
                // normalize recommended for safe display
                $recommended_norm = normalize_grade_label($recommended);
                $out[] = [
                    'id' => (int)$row['id'],
                    'full_name' => (string)($row['full_name'] ?? ''),
                    'birth_date' => (string)($row['birth_date'] ?? ''),
                    'age' => $age,
                    'current_grade' => (string)($row['current_grade'] ?? ''),
                    'recommended_grade' => $recommended_norm,
                    'is_new_registration' => isset($row['is_new_registration']) ? (int)$row['is_new_registration'] : (is_new_student_row($row) ? 1 : 0),
                ];
            }
            json_response([
                'success' => true,
                'students' => $out,
                'listed_count' => count($out),
                'total_new' => $total_new
            ]);
        } catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    if ($action === 'apply_allocation') {
        // Expect student_ids array
        $ids_json = $_POST['student_ids'] ?? '[]';
        $ids = json_decode($ids_json, true);
        if (!is_array($ids) || empty($ids)) {
            json_response(['success' => false, 'message' => 'No students selected.']);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        try {
            // Re-fetch selected students
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT s.* FROM students s WHERE s.id IN ($ph)");
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build id->recommended and track who was "new" at allocation time
            $recommend = [];
            $wasNew = [];
            foreach ($rows as $row) {
                // Optionally enforce new-only on apply when requested
                $apply_new_only = isset($_POST['filter_new_only']) && (($_POST['filter_new_only']==='1'||$_POST['filter_new_only']==='true'));
                if ($apply_new_only && !is_new_student_row($row)) continue;
                $age = compute_et_age_for_row($row);
                if ($age === null) continue;
                $rec = null;
                foreach ($norm_ranges as $rg) {
                    if ($rg['min'] === null) {
                        if ($age === (int)$rg['max']) { $rec = $rg['grade']; break; }
                    } else {
                        if ($age >= (int)$rg['min'] && $age <= (int)$rg['max']) { $rec = $rg['grade']; break; }
                    }
                }
                $rec = normalize_grade_label($rec);
                if ($rec !== null && $rec !== '') {
                    $recommend[(int)$row['id']] = $rec;
                    // Track if this student was registered as "new" prior to allocation
                    $wasNew[(int)$row['id']] = is_new_student_row($row);
                }
            }

            if (empty($recommend)) {
                json_response(['success' => true, 'updated_count' => 0, 'skipped_count' => count($ids), 'message' => 'No applicable updates.']);
            }

            // Bulk update
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE students SET current_grade = ? WHERE id = ?");
            $updCohort = $pdo->prepare("UPDATE students SET school_year_start = ? WHERE id = ? AND (school_year_start IS NULL OR school_year_start = 0)");
            $updated = 0; $skipped = 0;
            foreach ($recommend as $sid => $grade) {
                $ok = $upd->execute([$grade, (int)$sid]);
                if ($ok) $updated++; else $skipped++;
                // If the student was "new" at allocation time, set their cohort year once
                if (!empty($wasNew[$sid])) {
                    $cohortYear = current_ethiopian_year();
                    $updCohort->execute([$cohortYear, (int)$sid]);
                }
            }
            $pdo->commit();

            json_response(['success' => true, 'updated_count' => $updated, 'skipped_count' => $skipped]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    // Mark selected students as new (is_new_registration = 1)
    if ($action === 'mark_new_flag') {
        $ids_json = $_POST['student_ids'] ?? '[]';
        $ids = json_decode($ids_json, true);
        if (!is_array($ids) || empty($ids)) {
            json_response(['success' => false, 'message' => 'No students selected.']);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        try {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE students SET is_new_registration = 1 WHERE id IN ($ph)";
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute($ids);
            if (!$ok) json_response(['success' => false, 'message' => 'Failed to update flag.']);
            json_response(['success' => true, 'updated_count' => $stmt->rowCount()]);
        } catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    // Clear new flag (is_new_registration = 0)
    if ($action === 'clear_new_flag') {
        $ids_json = $_POST['student_ids'] ?? '[]';
        $ids = json_decode($ids_json, true);
        if (!is_array($ids) || empty($ids)) {
            json_response(['success' => false, 'message' => 'No students selected.']);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        try {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE students SET is_new_registration = 0 WHERE id IN ($ph)";
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute($ids);
            if (!$ok) json_response(['success' => false, 'message' => 'Failed to update flag.']);
            json_response(['success' => true, 'updated_count' => $stmt->rowCount()]);
        } catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    json_response(['success' => false, 'message' => 'Unknown action.'], 400);
}

// GET: Render UI inside admin layout
ob_start();
?>
<script>
    function serializeRanges() {
        const rows = document.querySelectorAll('#ranges tbody tr');
        const data = [];
        rows.forEach(tr => {
            const grade = tr.querySelector('.rg-grade').value.trim();
            const min = parseInt(tr.querySelector('.rg-min').value, 10);
            const max = parseInt(tr.querySelector('.rg-max').value, 10);
            if (grade && !isNaN(min) && !isNaN(max)) {
                data.push({ grade, min, max });
            }
        });
        return data;
    }

    let currentGradeTab = 'all';

    function normalizeGrade(val) {
        const v = (val || '').toString().trim().toLowerCase();
        if (v === '' || v === '0') return 'new';
        return v;
    }

    function renderGradeTabsFromSet(gradeSet) {
        const wrap = document.getElementById('gradeTabs');
        if (!wrap) return;
        wrap.innerHTML = '';
        const grades = Array.from(gradeSet);
        // Preferred order for common grades
        const preferred = ['all','new','1st','2nd','3rd','4th','5th','6th','7th','8th','9th','10th','11th','12th'];
        const others = grades.filter(g => !preferred.includes(g)).sort((a,b)=>a.localeCompare(b));
        const finalList = preferred.filter(g => grades.includes(g)).concat(others);
        finalList.forEach(g => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.grade = g;
            btn.textContent = g.charAt(0).toUpperCase() + g.slice(1);
            btn.className = 'tab-btn' + (currentGradeTab === g ? ' active' : '');
            btn.addEventListener('click', () => {
                document.querySelectorAll('#gradeTabs .tab-btn').forEach(b=>b.classList.remove('active'));
                btn.classList.add('active');
                currentGradeTab = g;
                // If moving away from 'new' ensure we fetch all students, not only new
                const filterNewEl = document.getElementById('filterNew');
                if (currentGradeTab !== 'new' && filterNewEl && filterNewEl.checked) {
                    filterNewEl.checked = false;
                    previewAllocation();
                } else if (currentGradeTab === 'new' && filterNewEl && !filterNewEl.checked) {
                    // Switching to 'new' tab: allow user to auto-toggle to show only new
                    filterNewEl.checked = true;
                    previewAllocation();
                } else {
                    applyGradeTabFilter();
                }
            });
            wrap.appendChild(btn);
        });
    }

    function applyGradeTabFilter() {
        const active = document.querySelector('#gradeTabs button.active');
        const grade = active ? active.dataset.grade : currentGradeTab || 'all';
        const rows = document.querySelectorAll('#preview tbody tr');
        const stream = (document.getElementById('streamSelect')?.value || 'all');
        const mismatchesOnly = !!document.getElementById('mismatchOnly')?.checked;
        rows.forEach(tr => {
            let show = true;
            // Grade filter
            if (grade !== 'all') {
                const val = tr.getAttribute('data-grade') || '';
                if (val !== grade) show = false;
            }
            // Stream filter
            if (show && stream !== 'all') {
                const ageStr = tr.getAttribute('data-age') || '';
                const age = ageStr ? parseInt(ageStr,10) : null;
                if (stream === 'youth' && !(age!==null && age>=18)) show = false;
                if (stream === 'adult' && !(age!==null && age<18)) show = false;
            }
            // Mismatches only
            if (show && mismatchesOnly) {
                const rec = tr.getAttribute('data-recommended') || '';
                const cur = tr.getAttribute('data-grade') || '';
                if (!rec || rec === cur) show = false;
            }
            tr.style.display = show ? '' : 'none';
        });
    }

    async function previewAllocation() {
        const ranges = JSON.stringify(serializeRanges());
        // Decide filter based on active tab: if 'new', prefer new-only; else fetch all
        const filterNewEl = document.getElementById('filterNew');
        let filterNew = (filterNewEl && filterNewEl.checked) ? '1' : '0';
        if (currentGradeTab && currentGradeTab !== 'all' && currentGradeTab !== 'new') {
            filterNew = '0';
        }
        const form = new FormData();
        form.append('action', 'preview_allocation');
        form.append('ranges', ranges);
        form.append('filter_new_only', filterNew);
        const res = await fetch('allocation.php', { method: 'POST', body: form });
        const json = await res.json();
        const tbody = document.querySelector('#preview tbody');
        tbody.innerHTML = '';
        if (!json.success) { alert(json.message || 'Error'); return; }
        const foundGrades = new Set(['all']);
        (json.students || []).forEach(s => {
            const tr = document.createElement('tr');
            const cg = normalizeGrade(s.current_grade);
            tr.setAttribute('data-grade', cg);
            tr.setAttribute('data-age', (s.age===null?'' : String(s.age)));
            tr.setAttribute('data-recommended', normalizeGrade(s.recommended_grade));
            tr.innerHTML = `
                <td><input type=\"checkbox\" class=\"sel\" data-id=\"${s.id}\"></td>
                <td>${(s.full_name||'').replace(/</g,'&lt;')}</td>
                <td>${s.birth_date||''}</td>
                <td>${s.age===null?'-':s.age}</td>
                <td>${s.is_new_registration? '<span style="color:#2563eb;font-weight:600;">Yes</span>' : '<span style="color:#6b7280;">No</span>'}</td>
                <td>${(s.current_grade||'').replace(/</g,'&lt;')}</td>
                <td>${s.recommended_grade? s.recommended_grade : '-'}</td>
            `;
            tbody.appendChild(tr);
            if (cg) foundGrades.add(cg);
        });
        // Rebuild tabs dynamically based on discovered grades
        if (!foundGrades.has('new')) {
            // ensure new appears if there are empty/0 values transformed
        }
        if (!foundGrades.has('all')) foundGrades.add('all');
        renderGradeTabsFromSet(foundGrades);
        applyGradeTabFilter();
    }

    function collectSelectedIds() {
        return Array.from(document.querySelectorAll('#preview tbody .sel:checked')).map(cb => parseInt(cb.dataset.id, 10)).filter(Boolean);
    }

    async function applyAllocation() {
        const ids = collectSelectedIds();
        if (ids.length === 0) { alert('Select at least one student.'); return; }
        if (!confirm('Apply allocation to '+ids.length+' selected students?')) return;
        const ranges = JSON.stringify(serializeRanges());
        const form = new FormData();
        form.append('action', 'apply_allocation');
        form.append('ranges', ranges);
        // Pass through the same flag used in preview
        const filterNew = document.getElementById('filterNew').checked ? '1' : '0';
        form.append('filter_new_only', filterNew);
        form.append('student_ids', JSON.stringify(ids));
        const res = await fetch('allocation.php', { method: 'POST', body: form });
        const json = await res.json();
        if (!json.success) { alert(json.message || 'Error'); return; }
        alert('Updated: '+json.updated_count+' | Skipped: '+json.skipped_count);
        // Optionally refresh preview
        previewAllocation();
    }

    function addRangeRow(grade='', min='', max='') {
        const tbody = document.querySelector('#ranges tbody');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input class="rg-grade" type="text" value="${grade}"></td>
            <td><input class="rg-min" type="number" min="0" value="${min}"></td>
            <td><input class="rg-max" type="number" min="0" value="${max}"></td>
            <td><button type="button" onclick="this.closest('tr').remove()"><i class="fa fa-trash"></i></button></td>
        `;
        tbody.appendChild(tr);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Seed some default ranges (example)
        // seeding now happens via loadSavedRanges fallback
        document.getElementById('addRange').addEventListener('click', () => addRangeRow());
        document.getElementById('btnPreview').addEventListener('click', previewAllocation);
        const btnSave = document.getElementById('btnSaveRanges');
        if (btnSave) btnSave.addEventListener('click', saveRanges);
        document.getElementById('btnApply').addEventListener('click', applyAllocation);
        const btnMarkNew = document.getElementById('btnMarkNew');
        if (btnMarkNew) btnMarkNew.addEventListener('click', async () => {
            const ids = collectSelectedIds();
            if (!ids.length) { alert('Select students first.'); return; }
            const form = new FormData();
            form.append('action','mark_new_flag');
            form.append('student_ids', JSON.stringify(ids));
            const res = await fetch('allocation.php', { method:'POST', body: form });
            const json = await res.json();
            if (!json.success) { alert(json.message||'Error'); return; }
            previewAllocation();
        });
        const btnClearNew = document.getElementById('btnClearNew');
        if (btnClearNew) btnClearNew.addEventListener('click', async () => {
            const ids = collectSelectedIds();
            if (!ids.length) { alert('Select students first.'); return; }
            const form = new FormData();
            form.append('action','clear_new_flag');
            form.append('student_ids', JSON.stringify(ids));
            const res = await fetch('allocation.php', { method:'POST', body: form });
            const json = await res.json();
            if (!json.success) { alert(json.message||'Error'); return; }
            previewAllocation();
        });
        document.getElementById('selectAll').addEventListener('change', function(){
            const checked = this.checked;
            document.querySelectorAll('#preview tbody .sel').forEach(cb => cb.checked = checked);
        });
        // Load saved ranges first, then preview
        loadSavedRanges().then(loaded => {
            if (!loaded) seedDefaultRanges();
            previewAllocation();
        });
        // Wire stream and mismatch controls (created in markup below)
        const streamSel = document.getElementById('streamSelect');
        if (streamSel) streamSel.addEventListener('change', applyGradeTabFilter);
        const mismatchOnly = document.getElementById('mismatchOnly');
        if (mismatchOnly) mismatchOnly.addEventListener('change', applyGradeTabFilter);
    });

    function seedDefaultRanges() {
        const defaults = [
            {grade:'new', min:5, max:10},
            {grade:'3rd', min:11, max:15},
            {grade:'4th', min:16, max:17},
            {grade:'5th', min:18, max:20}
        ];
        const tbody = document.querySelector('#ranges tbody');
        tbody.innerHTML = '';
        defaults.forEach(r => addRangeRow(r.grade, r.min, r.max));
    }

    async function loadSavedRanges() {
        try {
            const form = new URLSearchParams();
            form.append('action','get_ranges');
            const res = await fetch('allocation.php', { method: 'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form });
            const json = await res.json();
            if (!json.success) return false;
            const rows = Array.isArray(json.ranges) ? json.ranges : [];
            if (!rows.length) return false;
            const tbody = document.querySelector('#ranges tbody');
            tbody.innerHTML = '';
            rows.forEach(r => addRangeRow(r.grade, r.min ?? '', r.max ?? ''));
            return true;
        } catch (e) { return false; }
    }

    async function saveRanges() {
        const ranges = JSON.stringify(serializeRanges());
        const form = new URLSearchParams();
        form.append('action','save_ranges');
        form.append('ranges', ranges);
        const res = await fetch('allocation.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form });
        const json = await res.json();
        if (!json.success) { alert(json.message || 'Save failed'); return; }
        alert('Saved '+(json.saved||0)+' ranges');
    }

    function applyGradeTabFilter() {
        const active = document.querySelector('#gradeTabs button.active');
        const grade = active ? active.dataset.grade : currentGradeTab || 'all';
        const rows = document.querySelectorAll('#preview tbody tr');
        rows.forEach(tr => {
            if (grade === 'all') { tr.style.display = ''; return; }
            const val = tr.getAttribute('data-grade') || '';
            tr.style.display = (val === grade) ? '' : 'none';
        });
    }
    </script>
<style>
    .alloc-container { max-width: 1200px; margin: 0 auto; }
    .alloc-grid { display: grid; grid-template-columns: 360px 1fr; gap: 12px; }
    .alloc-card { background:#fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; }
    .alloc-card.dark { background: #1f2937; border-color: #374151; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { border-bottom: 1px solid #eee; padding: 6px; text-align: left; }
    th { background:#fafafa; font-weight: 600; }
    input[type=text], input[type=number] { width: 100%; padding: 6px; border:1px solid #d1d5db; border-radius: 6px; }
    button { padding: 6px 10px; border: 1px solid #d1d5db; background:#fff; border-radius: 6px; cursor: pointer; }
    button.primary { background:#2563eb; color:#fff; border-color:#2563eb; }
    .actions { display:flex; gap:8px; flex-wrap:wrap; }
    .row { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; gap:8px; }
    label { font-size: 12px; color:#374151; }
    /* Grade tabs (compact) */
    #gradeTabs { display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px; }
    #gradeTabs .tab-btn { padding: 4px 8px; border:1px solid #d1d5db; background:#f9fafb; border-radius: 9999px; font-size:11px; line-height:1; }
    #gradeTabs .tab-btn.active { background:#2563eb; color:#fff; border-color:#2563eb; }
</style>

<div class="alloc-container">
    <div class="mb-3">
        <h2 class="text-md font-semibold text-gray-900 dark:text-white"><i class="fa fa-sliders"></i> Allocation by Age</h2>
    </div>
    <div class="alloc-grid">
        <div class="alloc-card">
            <div class="row">
                <label><input type="checkbox" id="filterNew" checked> Apply to "new" students only</label>
                <button id="addRange" type="button"><i class="fa fa-plus"></i> Add Range</button>
            </div>
            <div id="gradeTabs"></div>
            <table id="ranges">
                    <thead>
                        <tr>
                            <th>Grade</th>
                            <th>Min age (ET)</th>
                            <th>Max age (ET)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="actions" style="margin-top:10px;">
                    <button id="btnPreview" class="primary" type="button"><i class="fa fa-eye"></i> Preview</button>
                    <button id="btnSaveRanges" type="button"><i class="fa fa-save"></i> Save Ranges</button>
                    <button id="btnApply" type="button"><i class="fa fa-check"></i> Apply</button>
                </div>
        </div>
        <div class="alloc-card">
            <div class="row" style="gap:10px; align-items:center;">
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <strong>Preview</strong>
                    <label style="display:flex; align-items:center; gap:6px; font-weight:normal;">
                        <span style="font-size:12px; color:#374151;">Stream</span>
                        <select id="streamSelect" style="padding:4px 8px; font-size:12px; border:1px solid #d1d5db; border-radius:6px;">
                            <option value="all">All</option>
                            <option value="youth">Youth (>=18)</option>
                            <option value="adult">Adult (<18)</option>
                        </select>
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-weight:normal;">
                        <input type="checkbox" id="mismatchOnly">
                        <span style="font-size:12px; color:#374151;">Mismatches only</span>
                    </label>
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        <button id="btnMarkNew" type="button" title="Flag selected as new" style="padding:4px 8px; font-size:12px; border:1px solid #d1d5db; border-radius:6px; line-height:1;">
                            <i class="fa fa-flag"></i> Mark as New
                        </button>
                        <button id="btnClearNew" type="button" title="Clear new flag" style="padding:4px 8px; font-size:12px; border:1px solid #d1d5db; border-radius:6px; line-height:1;">
                            <i class="fa fa-times"></i> Clear New
                        </button>
                    </div>
                </div>
                <label><input type="checkbox" id="selectAll"> Select all</label>
            </div>
            <table id="preview">
                <thead>
                    <tr>
                        <th></th>
                        <th>Full Name</th>
                        <th>Birth Date (ET)</th>
                        <th>Age (ET)</th>
                        <th>New?</th>
                        <th>Current Grade</th>
                        <th>Recommended Grade</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
echo renderAdminLayout('Allocation', $content);
