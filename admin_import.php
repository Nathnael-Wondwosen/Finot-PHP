<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin_layout.php';
requireAdminLogin();
if (!checkRateLimit('admin_import', 30, 60)) { http_response_code(429); die('Rate limit exceeded'); }
$errors = [];
$messages = [];
function respond_json($data){ header('Content-Type: application/json'); echo json_encode($data); exit; }
function with_error_capture(callable $fn){
  $prev = set_error_handler(function($sev,$msg,$file,$line){ throw new ErrorException($msg, 0, $sev, $file, $line); });
  try { $fn(); }
  catch (Throwable $e) { respond_json(['ok'=>false,'error'=>$e->getMessage()]); }
  finally { if ($prev !== null) set_error_handler($prev); else restore_error_handler(); }
}
function create_staging_db(PDO $rootPdo, $dbName){ $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"); }
function get_pdo_for_db($host,$username,$password,$db){ return new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]); }
function import_sql_file(PDO $pdo, $filePath){ $fh = fopen($filePath,'r'); if(!$fh){ throw new RuntimeException('Cannot open uploaded SQL'); } $delimiter = ';'; $buffer = ''; while(($line=fgets($fh))!==false){ $trim = trim($line); if ($trim === '' || str_starts_with($trim,'--') || str_starts_with($trim,'#')) { continue; } if (preg_match("~/\*!\d+.*\*/;?~", $trim)) { continue; } if (stripos($trim,'DELIMITER ')===0){ $parts = explode(' ', $trim, 2); $delimiter = trim($parts[1]); continue; } $buffer .= $line; if (substr(rtrim($buffer), -strlen($delimiter)) === $delimiter){ $stmt = substr($buffer, 0, -strlen($delimiter)); $buffer = ''; $sql = trim($stmt); if ($sql !== '') { $pdo->exec($sql); } }
 }
 fclose($fh); $sql = trim($buffer); if ($sql !== '') { $pdo->exec($sql); } }
function ensure_views(PDO $pdoNew, $dbNew, $dbOld){ $pdoNew->exec("CREATE OR REPLACE VIEW v_student_key AS SELECT id, LOWER(TRIM(full_name)) AS k_full_name, birth_date AS k_birth_date, gender AS k_gender, NULLIF(TRIM(phone_number),'') AS k_phone FROM `{$dbNew}`.students"); $pdoNew->exec("CREATE OR REPLACE VIEW `{$dbOld}`.v_student_key AS SELECT id, LOWER(TRIM(full_name)) AS k_full_name, birth_date AS k_birth_date, gender AS k_gender, NULLIF(TRIM(phone_number),'') AS k_phone FROM `{$dbOld}`.students"); }
function preview_counts(PDO $pdoNew, $dbNew, $dbOld){
  // Students missing by natural key
  $sqlMissing = "SELECT COUNT(*) AS cnt FROM `{$dbOld}`.students o LEFT JOIN `{$dbNew}`.v_student_key nk ON nk.k_full_name = LOWER(TRIM(o.full_name)) AND nk.k_birth_date = o.birth_date AND nk.k_gender = o.gender WHERE nk.id IS NULL";
  $missing = (int)$pdoNew->query($sqlMissing)->fetchColumn();
  // Parents missing for already matched students
  $sqlParents = "SELECT COUNT(*) FROM `{$dbOld}`.parents p JOIN `{$dbOld}`.students o ON o.id=p.student_id LEFT JOIN `{$dbNew}`.v_student_key nk ON nk.k_full_name = LOWER(TRIM(o.full_name)) AND nk.k_birth_date = o.birth_date AND nk.k_gender = o.gender JOIN `{$dbNew}`.students ns ON ns.id = nk.id LEFT JOIN `{$dbNew}`.parents np ON np.student_id = ns.id AND np.parent_type = p.parent_type AND COALESCE(np.full_name,'') = COALESCE(p.full_name,'') WHERE nk.id IS NOT NULL AND np.id IS NULL";
  $missingParents = (int)$pdoNew->query($sqlParents)->fetchColumn();
  // ID collisions: old student needs insert (missing by key) but its id is already used in new.students
  $sqlCollisions = "SELECT COUNT(*) FROM `{$dbOld}`.students o LEFT JOIN `{$dbNew}`.v_student_key nk ON nk.k_full_name = LOWER(TRIM(o.full_name)) AND nk.k_birth_date = o.birth_date AND nk.k_gender = o.gender WHERE nk.id IS NULL AND EXISTS (SELECT 1 FROM `{$dbNew}`.students s2 WHERE s2.id = o.id)";
  $idCollisions = (int)$pdoNew->query($sqlCollisions)->fetchColumn();
  return [$missing,$missingParents,$idCollisions];
}
function preview_samples(PDO $pdoNew, $dbNew, $dbOld){ $students = $pdoNew->query("SELECT o.id AS old_id, o.full_name, o.birth_date, o.gender, o.phone_number FROM `{$dbOld}`.students o LEFT JOIN `{$dbNew}`.v_student_key nk ON nk.k_full_name = LOWER(TRIM(o.full_name)) AND nk.k_birth_date = o.birth_date AND nk.k_gender = o.gender WHERE nk.id IS NULL ORDER BY o.full_name LIMIT 50")->fetchAll(); $parents = $pdoNew->query("SELECT p.* FROM `{$dbOld}`.parents p JOIN `{$dbOld}`.students o ON o.id=p.student_id LEFT JOIN `{$dbNew}`.v_student_key nk ON nk.k_full_name = LOWER(TRIM(o.full_name)) AND nk.k_birth_date = o.birth_date AND nk.k_gender = o.gender JOIN `{$dbNew}`.students ns ON ns.id = nk.id LEFT JOIN `{$dbNew}`.parents np ON np.student_id = ns.id AND np.parent_type = p.parent_type AND COALESCE(np.full_name,'') = COALESCE(p.full_name,'') WHERE nk.id IS NOT NULL AND np.id IS NULL LIMIT 50")->fetchAll(); return [$students,$parents]; }
function preview_exact_rows(PDO $pdoNew, $dbNew, $dbOld){
  // Detect optional target columns similar to do_import
  $colsStmt = $pdoNew->query("SHOW COLUMNS FROM `{$dbNew}`.students");
  $cols = array_map(function($r){ return strtolower($r['Field']); }, $colsStmt->fetchAll());
  $hasResultStatus = in_array('current_result_status', $cols, true);
  $hasIsNew = in_array('is_new_registration', $cols, true);
  $hasIsFlagged = in_array('is_flagged', $cols, true);

  $columns = [ 'id','photo_path','full_name','christian_name','gender','birth_date','current_grade' ];
  if ($hasResultStatus) $columns[] = 'current_result_status';
  if ($hasIsNew) $columns[] = 'is_new_registration';
  if ($hasIsFlagged) $columns[] = 'is_flagged';
  $columns = array_merge($columns, [
    'school_year_start','regular_school_name','regular_school_grade','phone_number','has_spiritual_father','spiritual_father_name','spiritual_father_phone','spiritual_father_church','sub_city','district','specific_area','house_number','living_with','special_interests','siblings_in_school','physical_disability','weak_side','transferred_from_other_school','came_from_other_religion','created_at','education_level','field_of_study','emergency_name','emergency_phone','emergency_alt_phone','emergency_address','flagged'
  ]);

  // Build select list with aliases matching column names
  $sel = [
    'o.id AS id','o.photo_path AS photo_path','o.full_name AS full_name','o.christian_name AS christian_name','o.gender AS gender','o.birth_date AS birth_date','o.current_grade AS current_grade'
  ];
  if ($hasResultStatus) $sel[] = 'NULL AS current_result_status';
  if ($hasIsNew) $sel[] = '1 AS is_new_registration';
  if ($hasIsFlagged) $sel[] = '0 AS is_flagged';
  $sel = array_merge($sel, [
    'o.school_year_start AS school_year_start','o.regular_school_name AS regular_school_name','o.regular_school_grade AS regular_school_grade','o.phone_number AS phone_number','o.has_spiritual_father AS has_spiritual_father','o.spiritual_father_name AS spiritual_father_name','o.spiritual_father_phone AS spiritual_father_phone','o.spiritual_father_church AS spiritual_father_church','o.sub_city AS sub_city','o.district AS district','o.specific_area AS specific_area','o.house_number AS house_number','o.living_with AS living_with','o.special_interests AS special_interests','o.siblings_in_school AS siblings_in_school','o.physical_disability AS physical_disability','o.weak_side AS weak_side','o.transferred_from_other_school AS transferred_from_other_school','o.came_from_other_religion AS came_from_other_religion','COALESCE(o.created_at, NOW()) AS created_at','o.education_level AS education_level','o.field_of_study AS field_of_study','o.emergency_name AS emergency_name','o.emergency_phone AS emergency_phone','o.emergency_alt_phone AS emergency_alt_phone','o.emergency_address AS emergency_address','COALESCE(o.flagged,0) AS flagged'
  ]);

  $sql =
    "SELECT " . implode(',', $sel) . "\n" .
    "FROM `{$dbOld}`.students o\n" .
    "LEFT JOIN `{$dbNew}`.v_student_key nk ON nk.k_full_name = LOWER(TRIM(o.full_name)) AND nk.k_birth_date = o.birth_date AND nk.k_gender = o.gender\n" .
    "WHERE nk.id IS NULL AND NOT EXISTS (SELECT 1 FROM `{$dbNew}`.students s2 WHERE s2.id = o.id)\n" .
    "ORDER BY o.full_name ASC LIMIT 50";

  $rows = $pdoNew->query($sql)->fetchAll();
  return [ 'columns' => $columns, 'rows' => $rows ];
}
function do_import(PDO $pdoNew, PDO $pdoOld, $dbNew, $dbOld){
  // We will preserve IDs when free; if an ID is already taken, we auto-assign a new one by omitting the id column
  $pdoNew->beginTransaction();
  // Detect optional columns present in the target students table
  $colsStmt = $pdoNew->query("SHOW COLUMNS FROM `{$dbNew}`.students");
  $cols = array_map(function($r){ return strtolower($r['Field']); }, $colsStmt->fetchAll());
  $hasResultStatus = in_array('current_result_status', $cols, true);
  $hasIsNew = in_array('is_new_registration', $cols, true);
  $hasIsFlagged = in_array('is_flagged', $cols, true);

  // Build INSERT columns and SELECT list dynamically
  $insertCols = [
    'id','photo_path','full_name','christian_name','gender','birth_date','current_grade'
  ];
  if ($hasResultStatus) $insertCols[] = 'current_result_status';
  if ($hasIsNew) $insertCols[] = 'is_new_registration';
  if ($hasIsFlagged) $insertCols[] = 'is_flagged';
  $insertCols = array_merge($insertCols, [
    'school_year_start','regular_school_name','regular_school_grade','phone_number','has_spiritual_father','spiritual_father_name','spiritual_father_phone','spiritual_father_church','sub_city','district','specific_area','house_number','living_with','special_interests','siblings_in_school','physical_disability','weak_side','transferred_from_other_school','came_from_other_religion','created_at','education_level','field_of_study','emergency_name','emergency_phone','emergency_alt_phone','emergency_address','flagged'
  ]);

  $selectList = [
    // Preserve old id if free; otherwise set NULL to trigger AUTO_INCREMENT
    'CASE WHEN NOT EXISTS (SELECT 1 FROM `'.$dbNew.'`.students s2 WHERE s2.id = o.id) THEN o.id ELSE NULL END',
    'o.photo_path','o.full_name','o.christian_name','o.gender','o.birth_date','o.current_grade'
  ];
  if ($hasResultStatus) $selectList[] = 'NULL';
  if ($hasIsNew) $selectList[] = '1';
  if ($hasIsFlagged) $selectList[] = '0';
  $selectList = array_merge($selectList, [
    'o.school_year_start','o.regular_school_name','o.regular_school_grade','o.phone_number','o.has_spiritual_father','o.spiritual_father_name','o.spiritual_father_phone','o.spiritual_father_church','o.sub_city','o.district','o.specific_area','o.house_number','o.living_with','o.special_interests','o.siblings_in_school','o.physical_disability','o.weak_side','o.transferred_from_other_school','o.came_from_other_religion','COALESCE(o.created_at, NOW())','o.education_level','o.field_of_study','o.emergency_name','o.emergency_phone','o.emergency_alt_phone','o.emergency_address','COALESCE(o.flagged,0)'
  ]);

  // Single-pass insert: preserve id when free; otherwise auto-assign
  $pdoNew->exec(
    "INSERT INTO `{$dbNew}`.students (" . implode(',', $insertCols) . ")\n".
    "SELECT " . implode(',', $selectList) . "\n".
    "FROM `{$dbOld}`.students o\n".
    "LEFT JOIN `{$dbNew}`.v_student_key nk ON nk.k_full_name = LOWER(TRIM(o.full_name)) AND nk.k_birth_date = o.birth_date AND nk.k_gender = o.gender\n".
    "WHERE nk.id IS NULL"
  );

  // Map old->new IDs (will be identity for newly inserted students)
  $pdoNew->exec("CREATE TEMPORARY TABLE tmp_student_id_map (old_student_id INT NOT NULL PRIMARY KEY, new_student_id INT NOT NULL) ENGINE=InnoDB");
  $pdoNew->exec("INSERT INTO tmp_student_id_map (old_student_id, new_student_id)
SELECT o.id, n.id
FROM `{$dbOld}`.v_student_key o
JOIN `{$dbNew}`.v_student_key n ON n.k_full_name=o.k_full_name AND n.k_birth_date=o.k_birth_date AND n.k_gender=o.k_gender");

  // Insert parents for mapped students, avoiding duplicates
  $pdoNew->exec("INSERT INTO `{$dbNew}`.parents (student_id, parent_type, full_name, church_name, work_category, phone_number)
SELECT m.new_student_id, p.parent_type, p.full_name, p.church_name, p.work_category, p.phone_number
FROM `{$dbOld}`.parents p
JOIN tmp_student_id_map m ON m.old_student_id = p.student_id
LEFT JOIN `{$dbNew}`.parents np ON np.student_id = m.new_student_id AND np.parent_type = p.parent_type AND COALESCE(np.full_name,'') = COALESCE(p.full_name,'')
WHERE np.id IS NULL");

  $pdoNew->commit();
}
$action = $_POST['action'] ?? null;
if ($action === 'upload_sql'){
    with_error_capture(function() use ($host,$username,$password,$dbname){
      if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error']!==UPLOAD_ERR_OK){ respond_json(['ok'=>false,'error'=>'Upload failed']); }
      $tmpPath = $_FILES['sql_file']['tmp_name'];
      $staging = 'finotek_old_tmp_' . date('Ymd_His');
      $pdoRoot = get_pdo_for_db($host,$username,$password,$dbname);
      create_staging_db($pdoRoot,$staging);
      $pdoOld = get_pdo_for_db($host,$username,$password,$staging);
      import_sql_file($pdoOld,$tmpPath);
      ensure_views($pdoRoot,$dbname,$staging);
      [$missing,$missingParents,$idCollisions] = preview_counts($pdoRoot,$dbname,$staging);
      [$students,$parents] = preview_samples($pdoRoot,$dbname,$staging);
      $exact = preview_exact_rows($pdoRoot,$dbname,$staging);
      respond_json(['ok'=>true,'staging'=>$staging,'missing_students'=>$missing,'missing_parents'=>$missingParents,'id_collisions'=>$idCollisions,'sample_students'=>$students,'sample_parents'=>$parents,'preview_table'=>$exact]);
    });
}
if ($action === 'preview' && isset($_POST['staging'])){
    with_error_capture(function() use ($host,$username,$password,$dbname){
      $staging = preg_replace('/[^a-zA-Z0-9_]/','',$_POST['staging']);
      $pdoRoot = get_pdo_for_db($host,$username,$password,$dbname);
      ensure_views($pdoRoot,$dbname,$staging);
      [$missing,$missingParents,$idCollisions] = preview_counts($pdoRoot,$dbname,$staging);
      [$students,$parents] = preview_samples($pdoRoot,$dbname,$staging);
      $exact = preview_exact_rows($pdoRoot,$dbname,$staging);
      respond_json(['ok'=>true,'staging'=>$staging,'missing_students'=>$missing,'missing_parents'=>$missingParents,'id_collisions'=>$idCollisions,'sample_students'=>$students,'sample_parents'=>$parents,'preview_table'=>$exact]);
    });
}
if ($action === 'import' && isset($_POST['staging'])){
    with_error_capture(function() use ($host,$username,$password,$dbname){
      $staging = preg_replace('/[^a-zA-Z0-9_]/','',$_POST['staging']);
      $pdoNew = get_pdo_for_db($host,$username,$password,$dbname);
      $pdoOld = get_pdo_for_db($host,$username,$password,$staging);
      ensure_views($pdoNew,$dbname,$staging);
      do_import($pdoNew,$pdoOld,$dbname,$staging);
      respond_json(['ok'=>true,'message'=>'Import completed']);
    });
}
 // Advanced UI using shared admin layout
 $title = 'Import Old SQL';
 ob_start();
 ?>
<div class="space-y-6">
  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
      <i class="fas fa-file-import mr-3 text-primary-600 dark:text-primary-400"></i>
      Import Old SQL
    </h1>
    <p class="text-gray-600 dark:text-gray-400 mt-1">Upload an old SQL dump, preview what will be added, and import safely with deduplication.</p>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5" style="grid-template-columns: 420px minmax(0,1fr); align-items:start;">
    <!-- Upload Card -->
    <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6" style="max-width:420px; align-self:start;">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">1) Upload SQL</h2>
      <form id="uploadForm" enctype="multipart/form-data" class="space-y-3">
        <input type="hidden" name="action" value="upload_sql">
        <input type="file" name="sql_file" accept=".sql" required class="w-full text-sm">
        <button id="uploadBtn" type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">Upload & Preview</button>
      </form>
      <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
        The file will be loaded into a temporary staging database. No changes are made to your current database until you click Import.
      </div>
    </div>

    <!-- Preview Stats Card -->
    <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5" style="min-width:0;">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">2) Preview & Import</h2>
      <div id="previewBox" class="hidden">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
          <div class="p-3 rounded-lg bg-gradient-to-r from-blue-500 to-blue-600 text-white">
            <div class="text-sm opacity-90">Staging Database</div>
            <div id="staging" class="text-lg font-semibold truncate"></div>
          </div>
          <div class="p-3 rounded-lg bg-gradient-to-r from-emerald-500 to-emerald-600 text-white">
            <div class="text-sm opacity-90">New Students</div>
            <div id="missing_students" class="text-2xl font-bold">0</div>
          </div>
          <div class="p-3 rounded-lg bg-gradient-to-r from-indigo-500 to-indigo-600 text-white">
            <div class="text-sm opacity-90">New Parents</div>
            <div id="missing_parents" class="text-2xl font-bold">0</div>
          </div>
          <div class="p-3 rounded-lg bg-gradient-to-r from-red-500 to-red-600 text-white">
            <div class="text-sm opacity-90">ID Collisions</div>
            <div id="id_collisions" class="text-2xl font-bold">0</div>
          </div>
        </div>
        <div class="flex gap-3 mb-3 flex-wrap">
          <button id="refresh" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium">Recalculate</button>
          <button id="doImport" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">Execute Import</button>
          <span id="collisionNote" class="hidden text-sm text-red-600 dark:text-red-400 self-center">Resolve ID collisions before importing.</span>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200">Sample Students (first 50)</div>
            <pre id="sample_students" class="p-3 text-xs overflow-auto max-h-60 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100"></pre>
          </div>
          <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200">Sample Parents (first 50)</div>
            <pre id="sample_parents" class="p-3 text-xs overflow-auto max-h-60 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100"></pre>
          </div>
        </div>
      </div>
      <div id="emptyState" class="text-sm text-gray-500 dark:text-gray-400">Upload a SQL file to see a preview.</div>
    </div>
  </div>
  <!-- Full-width exact preview table below the two columns -->
  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
    <div class="px-1 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Exact Rows Preview (first 50)</div>
    <div id="exact_table" class="w-full overflow-auto"></div>
  </div>
</div>
<?php
$content = ob_get_clean();

$page_script = <<<'SCRIPT'
<script>
function showToast(message, type = "info") {
  const toast = document.createElement("div");
  const bgColor = type === "success" ? "bg-green-500" : type === "error" ? "bg-red-500" : "bg-blue-500";
  toast.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
  toast.innerHTML = message;
  document.body.appendChild(toast);
  setTimeout(() => document.body.removeChild(toast), 3000);
}

const uploadForm = document.getElementById("uploadForm");
const previewBox = document.getElementById("previewBox");
const emptyState = document.getElementById("emptyState");
const stagingEl = document.getElementById("staging");
const msEl = document.getElementById("missing_students");
const mpEl = document.getElementById("missing_parents");
const collEl = document.getElementById("id_collisions");
const ssEl = document.getElementById("sample_students");
const spEl = document.getElementById("sample_parents");
const exactDiv = document.getElementById("exact_table");
const refreshBtn = document.getElementById("refresh");
const doImportBtn = document.getElementById("doImport");
const uploadBtn = document.getElementById("uploadBtn");
const collisionNote = document.getElementById("collisionNote");
let staging = null;

function showPreview(d){
  staging = d.staging;
  stagingEl.textContent = staging;
  msEl.textContent = d.missing_students;
  mpEl.textContent = d.missing_parents;
  if (collEl) collEl.textContent = (d.id_collisions||0);
  const hasCollisions = (d.id_collisions||0) > 0;
  if (collisionNote) {
    collisionNote.textContent = hasCollisions
      ? 'Some records will receive new IDs automatically because their old IDs are taken.'
      : '';
    collisionNote.classList.toggle('hidden', !hasCollisions);
  }
  if (doImportBtn) doImportBtn.disabled = false;
  ssEl.textContent = JSON.stringify(d.sample_students, null, 2);
  spEl.textContent = JSON.stringify(d.sample_parents, null, 2);
  // Build exact rows table
  if (exactDiv && d.preview_table && Array.isArray(d.preview_table.columns)){
    const cols = d.preview_table.columns;
    const rows = d.preview_table.rows || [];
    let html = `<table class="min-w-full text-xs"><thead><tr>`;
    cols.forEach(c=>{ html += `<th class="px-3 py-2 border-b text-left">${String(c)}</th>`; });
    html += `</tr></thead><tbody>`;
    rows.forEach(r=>{
      html += `<tr>`;
      cols.forEach(c=>{ const v = (r[c]!==undefined && r[c]!==null) ? r[c] : ''; html += `<td class="px-3 py-2 border-b">${String(v)}</td>`; });
      html += `</tr>`;
    });
    html += `</tbody></table>`;
    exactDiv.innerHTML = html;
  }
  emptyState.classList.add('hidden');
  previewBox.classList.remove('hidden');
}

if (uploadForm) {
  uploadForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(uploadForm);
    try {
      if (uploadBtn) { uploadBtn.disabled = true; uploadBtn.textContent = 'Uploading...'; }
      if (refreshBtn) refreshBtn.disabled = true;
      if (doImportBtn) doImportBtn.disabled = true;
      showToast("Uploading and staging SQL...", "info");
      const res = await fetch(location.href, { method: "POST", body: fd });
      const data = await res.json();
      if (!data.ok) { throw new Error(data.error||"Upload failed"); }
      showToast("Preview ready", "success");
      showPreview(data);
    } catch (err) {
      showToast(err.message||'Upload failed', 'error');
    } finally {
      if (uploadBtn) { uploadBtn.disabled = false; uploadBtn.textContent = 'Upload & Preview'; }
      if (refreshBtn) refreshBtn.disabled = false;
      if (doImportBtn) doImportBtn.disabled = false;
    }
  });
}

if (refreshBtn) {
  refreshBtn.addEventListener("click", async () => {
    if (!staging) return;
    const fd = new FormData();
    fd.append("action","preview");
    fd.append("staging", staging);
    try {
      refreshBtn.disabled = true; const prevText = refreshBtn.textContent; refreshBtn.textContent = 'Recalculating...';
      const res = await fetch(location.href, { method: "POST", body: fd });
      const data = await res.json();
      if (!data.ok) { throw new Error(data.error||"Preview failed"); }
      showPreview(data);
    } catch (err) {
      showToast(err.message||'Preview failed', 'error');
    } finally {
      refreshBtn.disabled = false; refreshBtn.textContent = 'Recalculate';
    }
  });
}

if (doImportBtn) {
  doImportBtn.addEventListener("click", async () => {
    if (!staging) return;
    if (!confirm("Proceed with import?")) return;
    const fd = new FormData();
    fd.append("action", "import");
    fd.append("staging", staging);
    try {
      doImportBtn.disabled = true; const prevText = doImportBtn.textContent; doImportBtn.textContent = 'Importing...';
      showToast("Importing...", "info");
      const res = await fetch(location.href, { method: "POST", body: fd });
      const data = await res.json();
      if (!data.ok) { throw new Error(data.error||"Import failed"); }
      showToast("Import completed", "success");
      // Optionally re-run preview to reflect post-import state
      if (refreshBtn) refreshBtn.click();
    } catch (err) {
      showToast(err.message||'Import failed', 'error');
    } finally {
      doImportBtn.disabled = false; doImportBtn.textContent = 'Execute Import';
    }
  });
}
</script>
SCRIPT;

echo renderAdminLayout($title, $content, $page_script);
