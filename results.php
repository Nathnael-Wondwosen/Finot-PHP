<?php
require 'config.php';
require 'includes/admin_layout.php';
require 'includes/security_helpers.php';
requireAdminLogin();

function table_has_column(PDO $pdo, string $table, string $column): bool {
    try {
        $db = active_database($pdo);
        if ($db === '') return false;
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$db, $table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function active_database(PDO $pdo): string {
    try {
        $row = $pdo->query('SELECT DATABASE() AS db')->fetch(PDO::FETCH_ASSOC);
        return (string)($row['db'] ?? '');
    } catch (Exception $e) {
        return '';
    }
}

function fetch_grades(PDO $pdo): array {
    $stmt = $pdo->query("SELECT DISTINCT current_grade FROM students WHERE current_grade IS NOT NULL AND current_grade<>'' ORDER BY current_grade");
    return array_map(fn($r)=>$r['current_grade'], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function fetch_classes_by_grade(PDO $pdo, string $grade): array {
    $stmt = $pdo->prepare("SELECT id, name, grade, section FROM classes WHERE grade = ? ORDER BY section, name");
    $stmt->execute([$grade]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_students_for_view(PDO $pdo, ?string $grade, ?int $classId, string $search = '', bool $hasCRS = true): array {
    $selectCRS = $hasCRS ? 's.current_result_status' : 'NULL AS current_result_status';
    $sql = "SELECT s.id, s.full_name, s.current_grade, s.phone_number, $selectCRS,
                   c.id AS current_class_id, c.name AS current_class_name
            FROM students s
            LEFT JOIN class_enrollments ce ON s.id = ce.student_id AND ce.status='active'
            LEFT JOIN classes c ON ce.class_id = c.id
            WHERE 1=1";
    $params = [];
    if ($grade) { $sql .= " AND s.current_grade = ?"; $params[] = $grade; }
    if ($classId) { $sql .= " AND c.id = ?"; $params[] = $classId; }
    if ($search) {
        $sql .= " AND (s.full_name LIKE ? OR s.phone_number LIKE ? )";
        $params[] = "%$search%"; $params[] = "%$search%";
    }
    $sql .= " ORDER BY s.full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function compute_next_grade(string $grade): string {
    $g = trim($grade);
    if ($g === '') return $grade;
    if (preg_match('/^(\d+)/', $g, $m)) {
        $n = (int)$m[1];
        $n2 = $n + 1;
        $suffix = '';
        if ($n2 >= 7) { $suffix = '-youth'; }
        // Preserve ordinal suffix if present (st/nd/rd/th) or default to 'th'
        $ord = 'th';
        if ($n2 % 10 === 1 && $n2 % 100 !== 11) $ord = 'st';
        elseif ($n2 % 10 === 2 && $n2 % 100 !== 12) $ord = 'nd';
        elseif ($n2 % 10 === 3 && $n2 % 100 !== 13) $ord = 'rd';
        return $n2 . $ord . $suffix;
    }
    // Fallback: if ends with '-youth' and has leading number, increment number
    if (preg_match('/^(\d+).*youth$/i', $g, $m)) {
        $n = (int)$m[1] + 1;
        return $n . 'th-youth';
    }
    return $grade;
}

// Handle actions
$msg = '';
$hasCRS = table_has_column($pdo, 'students', 'current_result_status');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) {
        $msg = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_results') {
            $statuses = $_POST['status'] ?? [];
            if (is_array($statuses) && $statuses) {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE students SET current_result_status = ? WHERE id = ?");
                    foreach ($statuses as $sid => $val) {
                        $v = ($val === 'pass' || $val === 'fail') ? $val : null;
                        $stmt->execute([$v, (int)$sid]);
                    }
                    $pdo->commit();
                    $msg = 'Result statuses saved.';
                    // PRG redirect
                    $fg = $_POST['filter_grade'] ?? '';
                    $fc = (int)($_POST['filter_class_id'] ?? 0);
                    header('Location: results.php?grade=' . urlencode($fg) . ($fc? ('&class_id=' . $fc) : '') . '&msg=' . urlencode($msg));
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $err = $e->getMessage();
                    if (stripos($err, "Unknown column 'current_result_status'") !== false || $e->getCode() === '42S22') {
                        $msg = "Database column 'current_result_status' is missing. Please run the migration to add it.";
                    } else {
                        $msg = 'Error saving: ' . $err;
                    }
                    // Redirect with error to avoid resubmission
                    $fg = $_POST['filter_grade'] ?? '';
                    $fc = (int)($_POST['filter_class_id'] ?? 0);
                    header('Location: results.php?grade=' . urlencode($fg) . ($fc? ('&class_id=' . $fc) : '') . '&msg=' . urlencode($msg));
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg = 'Error saving: ' . $e->getMessage();
                    $fg = $_POST['filter_grade'] ?? '';
                    $fc = (int)($_POST['filter_class_id'] ?? 0);
                    header('Location: results.php?grade=' . urlencode($fg) . ($fc? ('&class_id=' . $fc) : '') . '&msg=' . urlencode($msg));
                    exit;
                }
            } else {
                $msg = 'No changes to save.';
                $fg = $_POST['filter_grade'] ?? '';
                $fc = (int)($_POST['filter_class_id'] ?? 0);
                header('Location: results.php?grade=' . urlencode($fg) . ($fc? ('&class_id=' . $fc) : '') . '&msg=' . urlencode($msg));
                exit;
            }
        } elseif ($action === 'apply_promotions') {
            $grade = $_POST['filter_grade'] ?? '';
            $classId = (int)($_POST['filter_class_id'] ?? 0);
            $scope = $_POST['promote_scope'] ?? 'visible_pass';
            $ids = [];
            if ($scope === 'selected' && !empty($_POST['selected_ids'])) {
                $ids = array_map('intval', (array)$_POST['selected_ids']);
            } else {
                // Promote all visible students with status pass in current filter
                $students = fetch_students_for_view($pdo, $grade ?: null, $classId ?: null, '', $hasCRS);
                foreach ($students as $s) {
                    if (($s['current_result_status'] ?? null) === 'pass') $ids[] = (int)$s['id'];
                }
            }
            $ids = array_values(array_unique($ids));
            if ($ids) {
                try {
                    $pdo->beginTransaction();
                    // Fetch current grades for selected ids
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("SELECT id, current_grade FROM students WHERE id IN ($in)");
                    $stmt->execute($ids);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $upd = $pdo->prepare("UPDATE students SET current_grade = ?, current_result_status = NULL WHERE id = ?");
                    foreach ($rows as $r) {
                        $next = compute_next_grade((string)$r['current_grade']);
                        $upd->execute([$next, (int)$r['id']]);
                    }
                    $pdo->commit();
                    $msg = 'Promotions applied to ' . count($rows) . ' students.';
                    header('Location: results.php?grade=' . urlencode($grade) . ($classId? ('&class_id=' . $classId) : '') . '&msg=' . urlencode($msg));
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg = 'Error promoting: ' . $e->getMessage();
                    header('Location: results.php?grade=' . urlencode($grade) . ($classId? ('&class_id=' . $classId) : '') . '&msg=' . urlencode($msg));
                    exit;
                }
            } else {
                $msg = 'No students to promote in current selection.';
                header('Location: results.php?grade=' . urlencode($grade) . ($classId? ('&class_id=' . $classId) : '') . '&msg=' . urlencode($msg));
                exit;
            }
        }
    }
}

$grades = fetch_grades($pdo);
$filter_grade = $_GET['grade'] ?? ($_POST['filter_grade'] ?? ($grades[0] ?? ''));
$classes = $filter_grade ? fetch_classes_by_grade($pdo, $filter_grade) : [];
$filter_class_id = (int)($_GET['class_id'] ?? ($_POST['filter_class_id'] ?? 0));
$search = trim((string)($_GET['q'] ?? ''));
$students = fetch_students_for_view($pdo, $filter_grade ?: null, $filter_class_id ?: null, $search, $hasCRS);
$msg = $_GET['msg'] ?? $msg;
$activeDb = active_database($pdo);

ob_start();
?>
<style>
.rs-wrap{max-width:1200px;margin:0 auto}
.rs-toolbar{display:flex;gap:8px;align-items:center;justify-content:space-between;margin:10px 0}
.rs-form{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;background:#fafafa;border:1px solid #e5e7eb;border-radius:8px;padding:8px}
.rs-field{display:flex;flex-direction:column;gap:2px}
.rs-field label{font-size:12px;color:#374151}
.rs-control{padding:6px 8px;font-size:12px;border:1px solid #d1d5db;border-radius:6px}
.rs-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0}
.rs-tab{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border:1px solid #d1d5db;border-radius:9999px;font-size:12px;background:#f9fafb;color:#374151;text-decoration:none}
.rs-tab.active{background:#2563eb;border-color:#2563eb;color:#fff}
.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th,.tbl td{border-bottom:1px solid #eee;padding:6px;text-align:left}
.badge{display:inline-block;padding:2px 6px;font-size:11px;border:1px solid #d1d5db;border-radius:9999px}
.btn{padding:6px 10px;font-size:12px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer}
</style>
<div class="rs-wrap">
  <div class="rs-toolbar">
    <h2 style="margin:0;font-size:16px;font-weight:600">Results</h2>
    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <div class="badge" style="<?= $msg? 'background:#ecfeff;border-color:#a5f3fc;color:#0369a1':'' ?>"><?php echo htmlspecialchars($msg,ENT_QUOTES,'UTF-8'); ?></div>
      <div class="badge" title="Active database name">DB: <?= htmlspecialchars($activeDb,ENT_QUOTES,'UTF-8') ?: '-' ?></div>
      <div class="badge" title="Detection of students.current_result_status">Status Col: <?= $hasCRS ? 'yes' : 'no' ?></div>
    </div>
  </div>
  <form method="get" class="rs-form">
    <div class="rs-field">
      <label for="rs-grade">Grade</label>
      <select id="rs-grade" class="rs-control" name="grade" onchange="this.form.submit()">
        <?php foreach ($grades as $g): $sel = ($g===$filter_grade)?'selected':''; ?>
          <option value="<?= htmlspecialchars($g,ENT_QUOTES,'UTF-8') ?>" <?=$sel?>><?= htmlspecialchars($g,ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="rs-field" style="min-width:200px">
      <label for="rs-search">Search</label>
      <input id="rs-search" class="rs-control" type="text" name="q" value="<?= htmlspecialchars($search,ENT_QUOTES,'UTF-8') ?>" placeholder="Name or Phone">
    </div>
    <div class="rs-field">
      <button type="submit" class="btn">Apply</button>
    </div>
    <div class="rs-field">
      <a class="btn" href="results.php?grade=<?= urlencode($filter_grade) ?>">Reset</a>
    </div>
  </form>
  <div class="rs-tabs">
    <a class="rs-tab <?= $filter_class_id? '':'active' ?>" href="results.php?grade=<?= urlencode($filter_grade) ?>">All Classes</a>
    <?php foreach ($classes as $cl): $active = ($filter_class_id==(int)$cl['id'])?'active':''; ?>
      <a class="rs-tab <?=$active?>" href="results.php?grade=<?= urlencode($filter_grade) ?>&class_id=<?= (int)$cl['id'] ?>"><?= htmlspecialchars($cl['name'] ?? ($cl['section'] ?? 'Class '.$cl['id']),ENT_QUOTES,'UTF-8') ?></a>
    <?php endforeach; ?>
  </div>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= SecurityHelper::generateCSRFToken() ?>">
    <input type="hidden" name="filter_grade" value="<?= htmlspecialchars($filter_grade,ENT_QUOTES,'UTF-8') ?>">
    <input type="hidden" name="filter_class_id" value="<?= (int)$filter_class_id ?>">
    <div class="rs-toolbar">
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <button type="submit" name="action" value="save_results" class="btn">Save Statuses</button>
        <button type="submit" name="action" value="apply_promotions" class="btn" style="background:#dcfce7;border-color:#86efac;color:#166534">Apply Promotions</button>
        <label class="badge">Promote scope
          <select name="promote_scope" style="margin-left:6px">
            <option value="visible_pass">Visible (status=pass)</option>
            <option value="selected">Selected rows only</option>
          </select>
        </label>
      </div>
    </div>
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:28px"><input type="checkbox" id="selAll" onclick="document.querySelectorAll('.rowSel').forEach(cb=>cb.checked=this.checked)"></th>
          <th>ID</th>
          <th>Name</th>
          <th>Grade</th>
          <th>Class</th>
          <th>Phone</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): $sid=(int)$s['id']; $st=$s['current_result_status'] ?? null; ?>
          <tr>
            <td><input type="checkbox" class="rowSel" name="selected_ids[]" value="<?= $sid ?>"></td>
            <td><?= $sid ?></td>
            <td><?= htmlspecialchars($s['full_name']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($s['current_grade']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($s['current_class_name']??'-',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($s['phone_number']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td>
              <label class="badge"><input type="radio" name="status[<?= $sid ?>]" value="pass" <?= $st==='pass'?'checked':'' ?>> Pass</label>
              <label class="badge"><input type="radio" name="status[<?= $sid ?>]" value="fail" <?= $st==='fail'?'checked':'' ?>> Fail</label>
              <label class="badge"><input type="radio" name="status[<?= $sid ?>]" value="" <?= $st===null?'checked':'' ?>> Unset</label>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$students): ?>
          <tr><td colspan="7" style="color:#6b7280">No students.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </form>
</div>
<?php
$content = ob_get_clean();
echo renderAdminLayout('Results', $content);
