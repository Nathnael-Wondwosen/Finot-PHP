<?php
require 'config.php';
require 'includes/admin_layout.php';
require 'includes/security_helpers.php';
require 'includes/students_helpers.php';
requireAdminLogin();

// Ethiopian calendar helpers (local minimal copy for exact age filtering)
if (!function_exists('gregorian_to_jdn')) {
  function gregorian_to_jdn($y, $m, $d) {
    $a = intdiv(14 - $m, 12);
    $yy = $y + 4800 - $a;
    $mm = $m + 12 * $a - 3;
    return $d + intdiv(153 * $mm + 2, 5) + 365 * $yy + intdiv($yy, 4) - intdiv($yy, 100) + intdiv($yy, 400) - 32045;
  }
}
if (!function_exists('jdn_to_ethiopian')) {
  function jdn_to_ethiopian($jdn) {
    $r = ($jdn - 1723856) % 1461;
    if ($r < 0) $r += 1461;
    $n = ($r % 365) + 365 * intdiv($r, 1460);
    $year = 4 * intdiv(($jdn - 1723856), 1461) + intdiv($r, 365) - intdiv($r, 1460);
    $month = intdiv($n, 30) + 1;
    $day = ($n % 30) + 1;
    return [$year, $month, $day];
  }
}
if (!function_exists('ethiopian_today')) {
  function ethiopian_today() {
    $t = new DateTime();
    [$ey, $em, $ed] = jdn_to_ethiopian(gregorian_to_jdn((int)$t->format('Y'), (int)$t->format('m'), (int)$t->format('d')));
    return [$ey, $em, $ed];
  }
}
if (!function_exists('ethiopian_age_from_ymd')) {
  function ethiopian_age_from_ymd($ey, $em, $ed) {
    [$cy, $cm, $cd] = ethiopian_today();
    $age = $cy - $ey;
    if ($cm < $em || ($cm === $em && $cd < $ed)) $age--;
    return $age;
  }
}
if (!function_exists('ethiopian_age_from_row')) {
  function ethiopian_age_from_row(array $s) {
    if (!empty($s['birth_year_et'])) {
      $ey = (int)$s['birth_year_et'];
      $em = (int)($s['birth_month_et'] ?? 0);
      $ed = (int)($s['birth_day_et'] ?? 0);
      if ($ey>0 && $em>0 && $ed>0) return ethiopian_age_from_ymd($ey,$em,$ed);
      if ($ey>0) { // fallback to year-only if month/day missing
        [$cy] = ethiopian_today();
        return $cy - $ey;
      }
    }
    if (!empty($s['birth_date'])) {
      [$ey,$em,$ed] = array_map('intval', explode('-', (string)$s['birth_date']));
      if ($ey>0 && $em>0 && $ed>0) return ethiopian_age_from_ymd($ey,$em,$ed);
      if ($ey>0) { [$cy] = ethiopian_today(); return $cy - $ey; }
    }
    return null;
  }
}

$export = (isset($_GET['export']) && $_GET['export'] === '1');

// Handle duplicate deletion actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dq_action']) && $_POST['dq_action'] === 'delete_duplicates') {
    $ids = $_POST['selected_ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if ($ids) {
        try {
            $pdo->beginTransaction();
            // Best-effort: delete related enrollments before deleting students (avoid FK errors)
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM class_enrollments WHERE student_id IN ($in)");
            $stmt->execute($ids);
            // Delete students themselves
            $stmt = $pdo->prepare("DELETE FROM students WHERE id IN ($in)");
            $stmt->execute($ids);
            $pdo->commit();
            $msg = count($ids) . ' duplicate record(s) removed.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Error removing duplicates: ' . $e->getMessage();
        }
        // Redirect to avoid resubmission
        header('Location: data_quality.php?view=duplicate_students&msg=' . urlencode($msg));
        exit;
    } else {
        header('Location: data_quality.php?view=duplicate_students&msg=' . urlencode('No records selected.'));
        exit;
    }
}

function dq_count(PDO $pdo, string $type): int {
    switch ($type) {
        case 'missing_birth':
            return (int)$pdo->query("SELECT COUNT(*) FROM students s WHERE s.birth_date IS NULL OR s.birth_date='0000-00-00'")->fetchColumn();
        case 'by_age':
            // Count students with any Ethiopian birth info (birth_date or birth_year_et if column exists)
            try {
                $hasBirthYearEt = $pdo->query("SHOW COLUMNS FROM students LIKE 'birth_year_et'")->fetch();
            } catch (Exception $e) { $hasBirthYearEt = false; }
            if ($hasBirthYearEt) {
                return (int)$pdo->query("SELECT COUNT(*) FROM students s WHERE (s.birth_date IS NOT NULL AND s.birth_date<>'0000-00-00') OR (s.birth_year_et IS NOT NULL AND s.birth_year_et<>0)")->fetchColumn();
            } else {
                return (int)$pdo->query("SELECT COUNT(*) FROM students s WHERE (s.birth_date IS NOT NULL AND s.birth_date<>'0000-00-00')")->fetchColumn();
            }
        case 'invalid_birth':
            return (int)$pdo->query("SELECT COUNT(*) FROM students s WHERE s.birth_date IS NOT NULL AND s.birth_date<>'0000-00-00' AND (s.birth_date<'1900-01-01' OR s.birth_date>'2100-12-31')")->fetchColumn();
        case 'missing_phone':
            // Avoid TRIM in WHERE to allow index usage if any
            return (int)$pdo->query("SELECT COUNT(*) FROM students s WHERE s.phone_number IS NULL OR s.phone_number=''")->fetchColumn();
        case 'duplicate_names':
            // Prefer normalized_name if exists; fallback to LOWER(TRIM(full_name))
            try {
                $hasNorm = $pdo->query("SHOW COLUMNS FROM students LIKE 'normalized_name'")->fetch();
                if ($hasNorm) {
                    return (int)$pdo->query("SELECT COUNT(*) FROM (SELECT normalized_name nm, COUNT(*) c FROM students GROUP BY nm HAVING c>1 AND nm<>'') t")->fetchColumn();
                }
            } catch (Exception $e) {}
            return (int)$pdo->query("SELECT COUNT(*) FROM (SELECT LOWER(TRIM(full_name)) nm, COUNT(*) c FROM students GROUP BY nm HAVING c>1 AND nm<>'') t")->fetchColumn();
        case 'duplicate_students_groups':
            try {
                $hasNorm = $pdo->query("SHOW COLUMNS FROM students LIKE 'normalized_name'")->fetch();
                if ($hasNorm) {
                    return (int)$pdo->query("SELECT COUNT(*) FROM (SELECT normalized_name nm, COUNT(*) c FROM students GROUP BY nm HAVING c>1 AND nm<>'') g")->fetchColumn();
                }
            } catch (Exception $e) {}
            return (int)$pdo->query("SELECT COUNT(*) FROM (SELECT LOWER(TRIM(full_name)) nm, COUNT(*) c FROM students GROUP BY nm HAVING c>1 AND nm<>'') g")->fetchColumn();
        case 'by_grade':
            // Count of students with a set current_grade
            try {
                return (int)$pdo->query("SELECT COUNT(*) FROM students WHERE current_grade IS NOT NULL AND current_grade <> ''")->fetchColumn();
            } catch (Exception $e) { return 0; }
        case 'by_class':
            // Count active enrollments as a proxy indicator
            try {
                return (int)$pdo->query("SELECT COUNT(*) FROM class_enrollments WHERE status = 'active'")->fetchColumn();
            } catch (Exception $e) { return 0; }
        default: return 0;
    }
}

$view = $_GET['view'] ?? '';
$view = $view === '' ? 'missing_birth' : $view; // default to a tab
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perParam = $_GET['per'] ?? '25';
if (is_string($perParam) && strtolower($perParam) === 'all') {
  $per = 100000; // cap to a large number
} else {
  $per = max(1, (int)$perParam ?: 25);
}
$off = ($page-1)*$per;
$title = 'Data Quality';
ob_start();
?>
<style>
.dq-wrap{max-width:1200px;margin:0 auto}
.dq-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.card{padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
.card h3{margin:0 0 6px;font-size:13px;font-weight:600}
.card .v{font-size:18px;font-weight:700}
.a{color:#2563eb;text-decoration:none;font-size:12px}
.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th,.tbl td{border-bottom:1px solid #eee;padding:6px;text-align:left}
.toolbar{display:flex;align-items:center;justify-content:space-between;margin:8px 0}
.badge{display:inline-block;padding:2px 6px;font-size:11px;border:1px solid #d1d5db;border-radius:9999px}
.btn{padding:6px 10px;font-size:12px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0}
.tab{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border:1px solid #d1d5db;border-radius:9999px;font-size:12px;background:#f9fafb;color:#374151;text-decoration:none}
.tab.active{background:#2563eb;border-color:#2563eb;color:#fff}
.tab .count{background:#fff;color:#2563eb;border-radius:9999px;padding:1px 6px;font-size:11px}
</style>
<div class="dq-wrap">
  <div class="toolbar">
    <h2 style="margin:0;font-size:16px;font-weight:600">Data Quality</h2>
    <div style="display:flex; gap:6px; align-items:center;">
      <input id="quickFilter" type="text" placeholder="Filter current list (name or phone)" style="padding:6px 8px; font-size:12px; border:1px solid #d1d5db; border-radius:6px; width:240px;">
      <span class="badge" id="matchCount" style="display:none"></span>
    </div>
  </div>
  <?php 
    $counts = [
      'missing_birth' => dq_count($pdo,'missing_birth'),
      'by_age' => dq_count($pdo,'by_age'),
      'missing_phone' => dq_count($pdo,'missing_phone'),
      'duplicate_names' => dq_count($pdo,'duplicate_names'),
      'duplicate_students_groups' => dq_count($pdo,'duplicate_students_groups'),
      'by_grade' => dq_count($pdo,'by_grade'),
      'by_class' => dq_count($pdo,'by_class'),
    ];
    $labels = [
      'missing_birth' => 'Missing Birth',
      'by_age' => 'By Age',
      'missing_phone' => 'Missing Phone',
      'duplicate_names' => 'Duplicate Names',
      'duplicate_students' => 'Duplicate Students',
      'by_grade' => 'By Grade',
      'by_class' => 'By Class',
    ];
  ?>
  <div class="tabs">
    <?php foreach ($labels as $k => $lbl): $active = ($view===$k) ? 'active' : ''; ?>
      <a class="tab <?=$active?>" href="data_quality.php?view=<?=$k?>">
        <span><?= htmlspecialchars($lbl,ENT_QUOTES,'UTF-8') ?></span>
        <span class="count"><?= number_format($counts[$k] ?? ($k==='duplicate_students' ? ($counts['duplicate_students_groups'] ?? 0) : 0)) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php
    $heading=''; $rows=[]; $total=0; $sql=''; $whereExtra=''; $params=[]; $countSql='';
    if ($view==='missing_birth') {
        $heading='Missing Birth Date';
        $sql = "FROM students s WHERE (s.birth_date IS NULL OR s.birth_date='0000-00-00')";
    } elseif ($view==='missing_phone') {
        $heading='Missing Phone Number';
        $sql = "FROM students s WHERE (s.phone_number IS NULL OR s.phone_number='')";
    } elseif ($view==='duplicate_names') {
        $heading='Duplicate Names';
        // Prefer normalized_name if exists
        $hasNorm = $pdo->query("SHOW COLUMNS FROM students LIKE 'normalized_name'")->fetch();
        if ($hasNorm) {
            $sql = "FROM students s JOIN (SELECT normalized_name nm FROM students GROUP BY nm HAVING COUNT(*)>1 AND nm<>'') d ON s.normalized_name=d.nm";
        } else {
            $sql = "FROM students s JOIN (SELECT LOWER(TRIM(full_name)) nm FROM students GROUP BY nm HAVING COUNT(*)>1 AND nm<>'') d ON LOWER(TRIM(s.full_name))=d.nm";
        }
    } elseif ($view==='duplicate_students') {
        $heading='Duplicate Students (Grouped)';
        // Build grouped duplicates using normalized_name or normalized full_name
        $hasNorm = $pdo->query("SHOW COLUMNS FROM students LIKE 'normalized_name'")->fetch();
        if ($hasNorm) {
            $sql = "FROM students s JOIN (SELECT normalized_name nm, MIN(id) keep_id, COUNT(*) c FROM students GROUP BY nm HAVING COUNT(*)>1 AND nm<>'') d ON s.normalized_name=d.nm";
        } else {
            $sql = "FROM students s JOIN (SELECT LOWER(TRIM(full_name)) nm, MIN(id) keep_id, COUNT(*) c FROM students GROUP BY nm HAVING COUNT(*)>1 AND nm<>'') d ON LOWER(TRIM(s.full_name))=d.nm";
        }
    } elseif ($view==='by_class') {
        $heading='Filter by Class and Fix Grades';
        // Use custom rendering below; skip generic $sql flow for this view
        $sql = '';
    } elseif ($view==='by_grade') {
        $heading='Filter by Current Grade and Fix Grades';
        $sql = '';
    } elseif ($view==='by_age') {
        $heading='Filter by Age';
        $sql = '';
    }
    if ($sql!=='') {
        // CSV export for SQL-backed lists (generic and duplicate_students)
        if ($export) {
            if ($view==='duplicate_students') {
                $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.birth_date, s.phone_number, s.current_grade, d.nm AS group_key, d.keep_id, d.c AS group_count " . $sql . " ORDER BY d.nm ASC, (s.id=d.keep_id) DESC, s.id ASC");
                $stmt->execute();
                $all = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (function_exists('ob_get_level')) { while (ob_get_level()>0) { ob_end_clean(); } }
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="duplicate_students_'.date('Ymd_His').'.csv"');
                header('Pragma: no-cache'); header('Expires: 0');
                $tmp=fopen('php://temp','r+');
                fputcsv($tmp, ['Full Name','Phone','Current Grade','ID']);
                foreach ($all as $r) {
                    fputcsv($tmp, [
                        (string)($r['full_name']??''),
                        (string)($r['phone_number']??''),
                        (string)($r['current_grade']??''),
                        (int)($r['id']??0),
                    ]);
                }
                rewind($tmp); $csv=stream_get_contents($tmp); fclose($tmp);
                echo "\xEF\xBB\xBF" . $csv; exit;
            } else {
                $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.birth_date, s.phone_number, s.current_grade " . $sql . " ORDER BY s.created_at DESC");
                $stmt->execute();
                $all = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (function_exists('ob_get_level')) { while (ob_get_level()>0) { ob_end_clean(); } }
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="'. $view . '_' . date('Ymd_His') . '.csv"');
                header('Pragma: no-cache'); header('Expires: 0');
                $tmp=fopen('php://temp','r+');
                fputcsv($tmp, ['ID','Full Name','Birth Date','Phone','Current Grade'], "\t");
                foreach ($all as $r) {
                    fputcsv($tmp, [
                        (int)($r['id']??0),
                        (string)($r['full_name']??''),
                        (string)($r['birth_date']??''),
                        (string)($r['phone_number']??''),
                        (string)($r['current_grade']??''),
                    ], "\t");
                }
                rewind($tmp); $csv=stream_get_contents($tmp); fclose($tmp);
                echo "\xEF\xBB\xBF" . $csv; exit;
            }
        }
        // Count
        if ($view==='duplicate_students') {
            // Count groups for pager
            $countSql = $hasNorm
                ? "SELECT COUNT(*) FROM (SELECT normalized_name nm, COUNT(*) c FROM students GROUP BY nm HAVING COUNT(*)>1 AND nm<>'') x"
                : "SELECT COUNT(*) FROM (SELECT LOWER(TRIM(full_name)) nm, COUNT(*) c FROM students GROUP BY nm HAVING COUNT(*)>1 AND nm<>'') x";
            $total = (int)$pdo->query($countSql)->fetchColumn();
        } else {
            $countSql = "SELECT COUNT(*) ".$sql;
            $total = (int)$pdo->query($countSql)->fetchColumn();
        }

        // Rows
        if ($view==='duplicate_students') {
            $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.birth_date, s.phone_number, s.current_grade, d.nm, d.keep_id, d.c ".$sql." ORDER BY d.nm ASC, (s.id=d.keep_id) DESC, s.id ASC LIMIT :lim OFFSET :off");
        } else {
            $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.birth_date, s.phone_number, s.current_grade ".$sql." ORDER BY s.created_at DESC LIMIT :lim OFFSET :off");
        }
        $stmt->bindValue(':lim',$per,PDO::PARAM_INT);
        $stmt->bindValue(':off',$off,PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $pages = max(1,(int)ceil($total/$per));
  ?>
  <?php if ($sql !== ''): ?>
  <div class="toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
    <div><span class="badge">Total: <?=number_format($total)?></span></div>
    <div style="display:flex;gap:6px;align-items:center">
      <span class="badge">Page <?=$page?> / <?=$pages?></span>
      <?php $exp = $_GET; $exp['export']='1'; $exp_q=http_build_query($exp); ?>
      <a class="btn" href="?<?=$exp_q?>" style="background:#10b981; border-color:#34d399; color:#fff">Export CSV</a>
    </div>
  </div>
  <?php endif; ?>
  <h3 style="margin:4px 0 10px;font-size:14px;font-weight:600"><?php echo htmlspecialchars($heading,ENT_QUOTES,'UTF-8'); ?></h3>
  <?php if ($view==='duplicate_students'): ?>
    <?php if (isset($_GET['msg'])): ?><div class="badge" style="margin-bottom:6px; background:#ecfeff; border-color:#a5f3fc; color:#0369a1"><?=
        htmlspecialchars($_GET['msg'],ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
    <form method="post" onsubmit="return confirm('Delete selected duplicate records permanently? This cannot be undone.');">
      <input type="hidden" name="dq_action" value="delete_duplicates">
      <div class="toolbar" style="margin-top:6px">
        <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
          <button type="button" class="btn" onclick="dqSelectAllDuplicates()">Select All Duplicates</button>
          <button type="submit" class="btn" style="background:#fee2e2; border-color:#fecaca; color:#b91c1c">Delete Selected Duplicates</button>
        </div>
      </div>
      <table class="tbl">
        <thead><tr><th style="width:28px"></th><th>Group Key</th><th>ID</th><th>Full Name</th><th>Birth Date</th><th>Phone</th><th>Current Grade</th><th>Keep/Dup</th><th>Actions</th></tr></thead>
        <tbody>
          <?php
            $curNm = null; $groupCount = 0;
            foreach ($rows as $r):
              $nm = (string)($r['nm'] ?? '');
              $isKeeper = ((int)$r['id'] === (int)($r['keep_id'] ?? 0));
              $isDup = !$isKeeper;
              if ($nm !== $curNm) {
                if ($curNm !== null) {
                  // spacer between groups
                  echo '<tr><td colspan="9" style="background:#f3f4f6; height:4px"></td></tr>';
                }
                $curNm = $nm; $groupCount = (int)($r['c'] ?? 0);
                echo '<tr style="background:#fff7ed">'
                   . '<td></td><td colspan="8" style="font-size:12px; font-weight:600; color:#9a3412">Group: '
                   . htmlspecialchars($nm,ENT_QUOTES,'UTF-8') . ' • '
                   . 'Count: ' . (int)$groupCount
                   . '</td></tr>';
              }
          ?>
          <tr class="<?= $isKeeper ? 'keeper' : 'dup' ?>" style="background:<?= $isKeeper ? '#ecfdf5' : '#fff1f2' ?>">
            <td>
              <?php if ($isDup): ?>
                <input type="checkbox" name="selected_ids[]" value="<?= (int)$r['id'] ?>" data-nm="<?= htmlspecialchars($nm,ENT_QUOTES,'UTF-8') ?>" data-keeper="0">
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($nm,ENT_QUOTES,'UTF-8') ?></td>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['full_name']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['birth_date']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['phone_number']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['current_grade']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= $isKeeper ? '<span class="badge" style="background:#dcfce7;border-color:#86efac;color:#166534">Keep</span>' : '<span class="badge" style="background:#ffe4e6;border-color:#fecdd3;color:#9f1239">Duplicate</span>' ?></td>
            <td>
              <button type="button" class="btn" style="padding:4px 8px;font-size:11px" onclick="openDQDrawer('view', <?= (int)$r['id'] ?>)">View</button>
              <button type="button" class="btn" style="padding:4px 8px;font-size:11px" onclick="openDQDrawer('edit', <?= (int)$r['id'] ?>)">Edit</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="9" style="color:#6b7280">No duplicate groups.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </form>
    <script>
      function dqSelectAllDuplicates(){
        document.querySelectorAll('input[name="selected_ids[]"][data-keeper="0"]').forEach(cb=>{ cb.checked=true; });
      }
    </script>
  <?php else: ?>
    <?php if ($view==='by_class'): ?>
      <?php
        $selected_class = (int)($_GET['class_id'] ?? 0);
        // Load classes for selector
        $classes = [];
        try {
            $stmt = $pdo->query("SELECT id, name, grade, section FROM classes ORDER BY grade, section, name");
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
      ?>
      <div class="toolbar" style="gap:8px; flex-wrap:wrap">
        <form method="get" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap">
          <input type="hidden" name="view" value="by_class">
          <label for="class_id" style="font-size:12px; color:#374151">Class</label>
          <select id="class_id" name="class_id" class="btn" style="min-width:240px">
            <option value="0">Select a class...</option>
            <?php foreach ($classes as $c): $cid=(int)$c['id']; $g=htmlspecialchars((string)($c['grade']??''),ENT_QUOTES,'UTF-8'); $sec=htmlspecialchars((string)($c['section']??''),ENT_QUOTES,'UTF-8'); $nm=htmlspecialchars((string)($c['name']??''),ENT_QUOTES,'UTF-8'); ?>
              <option value="<?=$cid?>" <?= $cid===$selected_class ? 'selected' : '' ?>><?= "Grade $g" . ($sec!==''?" - Sec $sec":"") . (" - $nm") ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn">Apply</button>
        </form>
        <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap">
          <span class="badge" id="byClassCount" style="display:none"></span>
          <?php if ($selected_class>0): $exp=$_GET; $exp['export']='1'; $exp_q=http_build_query($exp); ?>
            <a class="btn" href="?<?=$exp_q?>" style="background:#10b981; border-color:#34d399; color:#fff">Export CSV</a>
          <?php endif; ?>
        </div>
      </div>
      <?php
        $by_rows = [];
        if ($selected_class > 0) {
            try {
                $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.phone_number, s.current_grade FROM class_enrollments ce JOIN students s ON ce.student_id = s.id WHERE ce.class_id = ? AND ce.status = 'active' ORDER BY s.full_name");
                $stmt->execute([$selected_class]);
                $by_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) { $by_rows = []; }
            if ($export) {
                if (function_exists('ob_get_level')) { while (ob_get_level()>0) { ob_end_clean(); } }
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="by_class_'.date('Ymd_His').'.csv"');
                header('Pragma: no-cache'); header('Expires: 0');
                $tmp=fopen('php://temp','r+');
                fputcsv($tmp, ['Full Name','Phone','Current Grade','ID']);
                foreach ($by_rows as $r) {
                    fputcsv($tmp, [ (string)($r['full_name']??''), (string)($r['phone_number']??''), (string)($r['current_grade']??''), (int)($r['id']??0) ]);
                }
                rewind($tmp); $csv=stream_get_contents($tmp); fclose($tmp);
                echo "\xEF\xBB\xBF" . $csv; exit;
            }
        }
      ?>
      <table class="tbl">
        <thead><tr><th>ID</th><th>Full Name</th><th>Phone</th><th>Current Grade</th><th style="width:160px">Change Grade</th><th>Save</th></tr></thead>
        <tbody>
          <?php foreach ($by_rows as $r): $sid=(int)$r['id']; ?>
          <tr>
            <td><?= $sid ?></td>
            <td><?= htmlspecialchars($r['full_name']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['phone_number']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><span class="badge"><?= htmlspecialchars($r['current_grade']??'',ENT_QUOTES,'UTF-8') ?: '—' ?></span></td>
            <td>
              <select class="btn dq-grade" data-student-id="<?= $sid ?>" style="min-width:140px">
                <option value="">Select grade…</option>
              </select>
            </td>
            <td>
              <button type="button" class="btn dq-save" data-student-id="<?= $sid ?>">Save</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$by_rows && $selected_class>0): ?>
            <tr><td colspan="6" style="color:#6b7280">No students in this class.</td></tr>
          <?php elseif ($selected_class===0): ?>
            <tr><td colspan="6" style="color:#6b7280">Select a class to view students.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <script>
        (function(){
          // Load grade options into all selects
          function loadGrades(){
            fetch('students.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=get_grade_options'})
              .then(r=>r.json()).then(d=>{
                if (!d || !d.success) throw new Error('failed');
                document.querySelectorAll('.dq-grade').forEach(sel=>{
                  const current = sel.closest('tr')?.querySelector('td:nth-child(4) .badge')?.textContent.trim();
                  const seen = new Set(Array.from(sel.options).map(o=>o.value));
                  ['new'].concat(d.grades||[]).filter(Boolean).forEach(g=>{ if (!seen.has(String(g))) { const o=document.createElement('option'); o.value=g; o.textContent=g; sel.appendChild(o);} });
                  if (current && !sel.value) sel.value = current;
                });
              })
              .catch(()=>{ /* ignore */});
          }
          loadGrades();
          // Save handlers
          document.querySelectorAll('.dq-save').forEach(btn=>{
            btn.addEventListener('click', function(){
              const sid = this.getAttribute('data-student-id');
              const sel = document.querySelector('.dq-grade[data-student-id="'+sid+'"]');
              if (!sid || !sel) return;
              const val = sel.value || '';
              this.disabled = true;
              fetch('students.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'update_student', table:'students', student_id:sid, current_grade:val}).toString()})
                .then(r=>r.json()).then(d=>{
                  if (!d || !d.success) throw new Error('update failed');
                  // Update current badge
                  const badge = sel.closest('tr').querySelector('td:nth-child(4) .badge');
                  if (badge) badge.textContent = val || '—';
                })
                .catch(()=>{ alert('Failed to save grade'); })
                .finally(()=>{ this.disabled = false; });
            });
          });
        })();
      </script>
    <?php elseif ($view==='by_age'): ?>
      <?php
        // Parameters
        $age_mode = $_GET['age_mode'] ?? 'interval'; // interval | set | missing
        $min_provided = array_key_exists('min_age', $_GET);
        $max_provided = array_key_exists('max_age', $_GET);
        $min_age = $min_provided ? (int)($_GET['min_age'] ?? 0) : 0;
        $max_age = $max_provided ? (int)($_GET['max_age'] ?? 0) : 0;
        if ($age_mode === 'interval' && !$min_provided && !$max_provided) { $min_age = 0; $max_age = 120; }
        $ages_csv = trim((string)($_GET['ages'] ?? ''));
        $ages_list = array_values(array_unique(array_filter(array_map(function($x){ $x=trim($x); if($x==='') return null; if(!preg_match('/^\\d+$/',$x)) return null; return (int)$x; }, explode(',', $ages_csv)), function($v){ return $v!==null; })));

        // Exact Ethiopian age filtering in PHP for interval/set; SQL only for 'missing'
        $rows = [];
        $total = 0; $pages = 1;
        $dbg = isset($_GET['dbg']) && $_GET['dbg']==='1';
        $dbgCandidates = 0; $dbgFiltered = 0;
        if ($export && $age_mode !== 'missing') {
          // Build full filtered list for export (interval/set)
          $candidates = [];
          try { $stmt = $pdo->query("SELECT s.* FROM students s ORDER BY s.full_name ASC"); $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $candidates = []; }
          $filtered = [];
          foreach ($candidates as $row) {
            $age = ethiopian_age_from_row($row);
            if ($age === null) continue;
            if ($age_mode === 'set') {
              if (!in_array($age, $ages_list, true)) continue;
            } else {
              $min=(int)$min_age; $max=(int)$max_age; if ($min>$max){$t=$min;$min=$max;$max=$t;}
              if ($age < $min || $age > $max) continue;
            }
            $row['age'] = $age; $filtered[] = $row;
          }
          // Output CSV (Excel-friendly UTF-16LE with BOM)
          if (function_exists('ob_get_level')) { while (ob_get_level()>0) { ob_end_clean(); } }
          header('Content-Type: text/tab-separated-values; charset=UTF-16LE');
          header('Content-Disposition: attachment; filename="by_age_export_'.date('Ymd_His').'.tsv"');
          header('Pragma: no-cache'); header('Expires: 0');
          $tmp=fopen('php://temp','r+');
          fputcsv($tmp, ['ID','Full Name','Birth Date','Age','Phone','Current Grade'], "\t");
          foreach ($filtered as $r) {
            fputcsv($tmp, [(int)($r['id']??0), (string)($r['full_name']??''), (string)($r['birth_date']??''), (int)($r['age']??0), (string)($r['phone_number']??''), (string)($r['current_grade']??'')], "\t");
          }
          rewind($tmp); $csv=stream_get_contents($tmp); fclose($tmp);
          echo "\xFF\xFE" . mb_convert_encoding($csv, 'UTF-16LE','UTF-8'); exit;
        }
        if ($age_mode === 'missing') {
          // Handle DBs without birth_year_et column
          try { $hasBY = $pdo->query("SHOW COLUMNS FROM students LIKE 'birth_year_et'")->fetch(); } catch (Exception $e) { $hasBY = false; }
          $where = $hasBY
            ? "((s.birth_date IS NULL OR s.birth_date='0000-00-00') AND (s.birth_year_et IS NULL OR s.birth_year_et=0))"
            : "(s.birth_date IS NULL OR s.birth_date='0000-00-00')";
          // Export all missing birthdate rows to CSV (Excel-friendly)
          if ($export) {
            try {
              $stmt = $pdo->query("SELECT s.id, s.full_name, s.birth_date, s.phone_number, s.current_grade FROM students s WHERE $where ORDER BY s.full_name ASC");
              $all = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $e) { $all = []; }
            if (function_exists('ob_get_level')) { while (ob_get_level()>0) { ob_end_clean(); } }
            header('Content-Type: text/tab-separated-values; charset=UTF-16LE');
            header('Content-Disposition: attachment; filename="by_age_missing_'.date('Ymd_His').'.tsv"');
            header('Pragma: no-cache'); header('Expires: 0');
            $tmp=fopen('php://temp','r+');
            // Keep same columns as other by_age exports
            fputcsv($tmp, ['ID','Full Name','Birth Date','Age','Phone','Current Grade'], "\t");
            foreach ($all as $r) {
              fputcsv($tmp, [ (int)($r['id']??0), (string)($r['full_name']??''), (string)($r['birth_date']??''), '', (string)($r['phone_number']??''), (string)($r['current_grade']??'') ], "\t");
            }
            rewind($tmp); $csv=stream_get_contents($tmp); fclose($tmp);
            echo "\xFF\xFE" . mb_convert_encoding($csv, 'UTF-16LE','UTF-8'); exit;
          }
          try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM students s WHERE $where");
            $total = (int)$stmt->fetchColumn();
          } catch (Exception $e) { $total = 0; }
          try {
            $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.birth_date, s.phone_number, s.current_grade, NULL AS age FROM students s WHERE $where ORDER BY s.full_name ASC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, (int)$per, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$off, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
          } catch (Exception $e) { $rows = []; }
          $pages = max(1,(int)ceil($total/$per));
          $dbgCandidates = $total; $dbgFiltered = $total;
        } else {
          // Select all students; compute ET age in PHP and filter
          $candidates = [];
          try {
            $stmt = $pdo->query("SELECT s.* FROM students s ORDER BY s.full_name ASC");
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
          } catch (Exception $e) { $candidates = []; }
          // Compute exact ET age and filter
          $filtered = [];
          foreach ($candidates as $row) {
            $age = ethiopian_age_from_row($row);
            if ($age === null) continue;
            if ($age_mode === 'set') {
              if (!in_array($age, $ages_list, true)) continue;
            } else { // interval
              $min = (int)$min_age; $max = (int)$max_age;
              if ($min > $max) { $t=$min; $min=$max; $max=$t; }
              if ($age < $min || $age > $max) continue;
            }
            $row['age'] = $age;
            $filtered[] = $row;
          }
          $total = count($filtered);
          $dbgCandidates = is_array($candidates) ? count($candidates) : 0;
          $dbgFiltered = $total;
          $pages = max(1,(int)ceil($total/$per));
          // Ensure current page has results; if not, reset to first page
          if ($off >= $total) { $page = 1; $off = 0; }
          // Paginate
          $rows = array_slice($filtered, $off, $per);
        }
      ?>
      <div class="toolbar" style="gap:10px; flex-wrap:wrap">
        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap">
          <input type="hidden" name="view" value="by_age">
          <label for="age_mode" style="font-size:12px; color:#374151">Mode</label>
          <select id="age_mode" name="age_mode" class="btn">
            <option value="interval" <?= $age_mode==='interval' ? 'selected' : '' ?>>Age Interval</option>
            <option value="set" <?= $age_mode==='set' ? 'selected' : '' ?>>Specific Ages</option>
            <option value="missing" <?= $age_mode==='missing' ? 'selected' : '' ?>>Missing Birthdate</option>
          </select>
          <span id="age_interval_fields" style="display:<?= $age_mode==='interval' ? 'inline-flex' : 'none' ?>; gap:8px; align-items:center;">
            <label style="font-size:12px; color:#374151">Min</label>
            <input type="number" name="min_age" value="<?= (int)$min_age ?>" class="btn" style="width:90px">
            <label style="font-size:12px; color:#374151">Max</label>
            <input type="number" name="max_age" value="<?= (int)$max_age ?>" class="btn" style="width:90px">
          </span>
          <span id="age_set_fields" style="display:<?= $age_mode==='set' ? 'inline-flex' : 'none' ?>; gap:8px; align-items:center;">
            <label style="font-size:12px; color:#374151">Ages</label>
            <input type="text" name="ages" value="<?= htmlspecialchars($ages_csv,ENT_QUOTES,'UTF-8') ?>" placeholder="e.g. 12, 13, 18" class="btn" style="min-width:220px">
          </span>
          <label style="font-size:12px; color:#374151">Per page</label>
          <select name="per" class="btn">
            <?php $perOpts=['25','50','100','200','all']; foreach($perOpts as $opt): ?>
              <option value="<?=$opt?>" <?= (strtolower((string)($_GET['per']??'25'))===$opt?'selected':'') ?>><?= strtoupper($opt)==='ALL'?'All':$opt ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn">Apply</button>
        </form>
        <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap">
          <span class="badge">Total: <?= number_format($total) ?></span>
          <?php if ($pages>1): ?>
            <?php
              $base = $_GET; unset($base['page']); $base_q = http_build_query($base);
              $prev = max(1,$page-1); $next = min($pages,$page+1);
            ?>
            <a class="btn" href="?<?=$base_q?>&page=<?=$prev?>">Prev</a>
            <span class="badge">Page <?=$page?> / <?=$pages?></span>
            <a class="btn" href="?<?=$base_q?>&page=<?=$next?>">Next</a>
          <?php else: ?>
            <span class="badge">Page 1 / 1</span>
          <?php endif; ?>
          <?php $exp = $_GET; $exp['export']='1'; $exp_q=http_build_query($exp); ?>
          <a class="btn" href="?<?=$exp_q?>" style="background:#10b981; border-color:#34d399; color:#fff">Export CSV</a>
        </div>
        
        <?php if ($dbg): ?>
          <span class="badge" style="background:#eef2ff;border-color:#c7d2fe;color:#3730a3">Candidates: <?= number_format($dbgCandidates) ?></span>
          <span class="badge" style="background:#ecfeff;border-color:#a5f3fc;color:#0369a1">Filtered: <?= number_format($dbgFiltered) ?></span>
        <?php endif; ?>
      </div>
      <table class="tbl">
        <thead><tr><th>ID</th><th>Full Name</th><th>Birth Date</th><th>Age</th><th>Phone</th><th>Current Grade</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['full_name']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['birth_date']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= $r['age'] === null ? '—' : (int)$r['age'] ?></td>
            <td><?= htmlspecialchars($r['phone_number']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['current_grade']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td>
              <button class="btn" style="padding:4px 8px;font-size:11px" onclick="openDQDrawer('view', <?= (int)$r['id'] ?>)">View</button>
              <button class="btn" style="padding:4px 8px;font-size:11px" onclick="openDQDrawer('edit', <?= (int)$r['id'] ?>)">Edit</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="7" style="color:#6b7280">No rows.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <script>
        (function(){
          var mode = document.getElementById('age_mode');
          function toggle(){
            var m = mode.value;
            document.getElementById('age_interval_fields').style.display = (m==='interval') ? 'inline-flex' : 'none';
            document.getElementById('age_set_fields').style.display = (m==='set') ? 'inline-flex' : 'none';
          }
          if (mode) mode.addEventListener('change', toggle);
        })();
      </script>
    <?php elseif ($view==='by_grade'): ?>
      <?php
        $selected_grade = (string)($_GET['grade'] ?? '');
        // Load distinct grades for selector
        $grades = [];
        try {
            $stmt = $pdo->query("SELECT DISTINCT current_grade AS g FROM students WHERE current_grade IS NOT NULL AND current_grade<>'' ORDER BY current_grade");
            $grades = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}
      ?>
      <div class="toolbar" style="gap:8px; flex-wrap:wrap">
        <form method="get" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap">
          <input type="hidden" name="view" value="by_grade">
          <label for="grade_sel" style="font-size:12px; color:#374151">Current Grade</label>
          <select id="grade_sel" name="grade" class="btn" style="min-width:200px">
            <option value="">Select a grade...</option>
            <?php foreach ($grades as $g): $gh=htmlspecialchars((string)$g,ENT_QUOTES,'UTF-8'); ?>
              <option value="<?=$gh?>" <?= $selected_grade===(string)$g ? 'selected' : '' ?>><?=$gh?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn">Apply</button>
        </form>
        <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap">
          <?php if ($selected_grade!==''): $exp=$_GET; $exp['export']='1'; $exp_q=http_build_query($exp); ?>
            <a class="btn" href="?<?=$exp_q?>" style="background:#10b981; border-color:#34d399; color:#fff">Export CSV</a>
          <?php endif; ?>
        </div>
      </div>
      <?php
        $grade_rows = [];
        if ($selected_grade !== '') {
            try {
                $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.phone_number, s.current_grade FROM students s WHERE s.current_grade = ? ORDER BY s.full_name");
                $stmt->execute([$selected_grade]);
                $grade_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) { $grade_rows = []; }
            if ($export) {
                if (function_exists('ob_get_level')) { while (ob_get_level()>0) { ob_end_clean(); } }
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="by_grade_'.date('Ymd_His').'.csv"');
                header('Pragma: no-cache'); header('Expires: 0');
                $tmp=fopen('php://temp','r+');
                fputcsv($tmp, ['Full Name','Phone','Current Grade','ID']);
                foreach ($grade_rows as $r) {
                    fputcsv($tmp, [ (string)($r['full_name']??''), (string)($r['phone_number']??''), (string)($r['current_grade']??''), (int)($r['id']??0) ]);
                }
                rewind($tmp); $csv=stream_get_contents($tmp); fclose($tmp);
                echo "\xEF\xBB\xBF" . $csv; exit;
            }
        }
      ?>
      <table class="tbl">
        <thead><tr><th>ID</th><th>Full Name</th><th>Phone</th><th>Current Grade</th><th style="width:160px">Change Grade</th><th>Save</th></tr></thead>
        <tbody>
          <?php foreach ($grade_rows as $r): $sid=(int)$r['id']; ?>
          <tr>
            <td><?= $sid ?></td>
            <td><?= htmlspecialchars($r['full_name']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['phone_number']??'',ENT_QUOTES,'UTF-8') ?></td>
            <td><span class="badge"><?= htmlspecialchars($r['current_grade']??'',ENT_QUOTES,'UTF-8') ?: '—' ?></span></td>
            <td>
              <select class="btn dq-grade" data-student-id="<?= $sid ?>" style="min-width:140px">
                <option value="">Select grade…</option>
              </select>
            </td>
            <td>
              <button type="button" class="btn dq-save" data-student-id="<?= $sid ?>">Save</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if ($selected_grade!=='' && !$grade_rows): ?>
            <tr><td colspan="6" style="color:#6b7280">No students for this grade.</td></tr>
          <?php elseif ($selected_grade===''): ?>
            <tr><td colspan="6" style="color:#6b7280">Select a grade to view students.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <script>
        (function(){
          function loadGrades(){
            fetch('students.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=get_grade_options'})
              .then(r=>r.json()).then(d=>{
                if (!d || !d.success) throw new Error('failed');
                document.querySelectorAll('.dq-grade').forEach(sel=>{
                  const current = sel.closest('tr')?.querySelector('td:nth-child(4) .badge')?.textContent.trim();
                  const seen = new Set(Array.from(sel.options).map(o=>o.value));
                  ['new'].concat(d.grades||[]).filter(Boolean).forEach(g=>{ if (!seen.has(String(g))) { const o=document.createElement('option'); o.value=g; o.textContent=g; sel.appendChild(o);} });
                  if (current && !sel.value) sel.value = current;
                });
              })
              .catch(()=>{});
          }
          loadGrades();
          document.querySelectorAll('.dq-save').forEach(btn=>{
            btn.addEventListener('click', function(){
              const sid = this.getAttribute('data-student-id');
              const sel = document.querySelector('.dq-grade[data-student-id="'+sid+'"]');
              if (!sid || !sel) return;
              const val = sel.value || '';
              this.disabled = true;
              fetch('students.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'update_student', table:'students', student_id:sid, current_grade:val}).toString()})
                .then(r=>r.json()).then(d=>{
                  if (!d || !d.success) throw new Error('update failed');
                  const badge = sel.closest('tr').querySelector('td:nth-child(4) .badge');
                  if (badge) badge.textContent = val || '—';
                })
                .catch(()=>{ alert('Failed to save grade'); })
                .finally(()=>{ this.disabled = false; });
            });
          });
        })();
      </script>
    <?php else: ?>
    <table class="tbl">
      <thead><tr><th>ID</th><th>Full Name</th><th>Birth Date</th><th>Phone</th><th>Current Grade</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['full_name']??'',ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['birth_date']??'',ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['phone_number']??'',ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['current_grade']??'',ENT_QUOTES,'UTF-8') ?></td>
          <td>
            <button class="btn" style="padding:4px 8px;font-size:11px" onclick="openDQDrawer('view', <?=$r['id']?>)">View</button>
            <button class="btn" style="padding:4px 8px;font-size:11px" onclick="openDQDrawer('edit', <?=$r['id']?>)">Edit</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <tr><td colspan="6" style="color:#6b7280">No rows.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <?php endif; ?>
  <!-- Drawer -->
  <div id="dqOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.4);display:none;z-index:9998"></div>
  <div id="dqDrawer" style="position:fixed;top:0;right:0;height:100vh;width:92vw;max-width:900px;background:#fff;border-left:1px solid #e5e7eb;box-shadow:-8px 0 30px rgba(0,0,0,0.1);transform:translateX(100%);transition:transform .2s ease;z-index:9999;display:flex;flex-direction:column">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid #eee;background:#fafafa">
      <strong style="font-size:13px" id="dqTitle">Details</strong>
      <div style="display:flex;gap:6px;align-items:center">
        <button class="btn" style="padding:4px 8px;font-size:12px" onclick="reloadDQFrame()">Reload</button>
        <button class="btn" style="padding:4px 8px;font-size:12px" onclick="closeDQDrawer()">Close</button>
      </div>
    </div>
    <iframe id="dqFrame" src="about:blank" style="border:0;flex:1;width:100%"></iframe>
  </div>
  <script>
    function openDQDrawer(mode, id){
      var title = mode==='edit' ? 'Edit Student' : 'Student Details';
      var url = mode==='edit' ? ('student_edit.php?id='+id) : ('student_view.php?id='+id);
      document.getElementById('dqTitle').textContent = title + ' #' + id;
      var frame = document.getElementById('dqFrame');
      // Remove previous load handlers
      try { frame.onload = null; } catch(e) {}
      frame.src = url;
      frame.onload = function(){
        try { applyCompactStylesToFrame(frame); } catch(e) {}
      };
      document.getElementById('dqOverlay').style.display = 'block';
      document.getElementById('dqDrawer').style.transform = 'translateX(0)';
    }
    function closeDQDrawer(){
      document.getElementById('dqDrawer').style.transform = 'translateX(100%)';
      document.getElementById('dqOverlay').style.display = 'none';
      // Optionally refresh current list after closing
      // location.reload();
    }
    function reloadDQFrame(){
      var f = document.getElementById('dqFrame');
      if (f && f.contentWindow) f.contentWindow.location.reload();
    }
    // Inject compact CSS into iframe for modern compact view
    function applyCompactStylesToFrame(frame){
      if (!frame || !frame.contentDocument) return;
      var doc = frame.contentDocument;
      var style = doc.createElement('style');
      style.textContent = `
        html { font-size: 13px !important; }
        body { padding: 8px !important; }
        h1, h2, h3 { margin: 6px 0 !important; }
        table { font-size: 13px !important; }
        th, td { padding: 6px 8px !important; }
        input, select, textarea { font-size: 12px !important; padding: 6px 8px !important; }
        .btn, button, [type=button], [type=submit] { font-size: 12px !important; padding: 6px 10px !important; }
        .container, .card, .panel { padding: 8px !important; }
      `;
      doc.head.appendChild(style);
    }

    // Close when clicking overlay
    (function(){
      var ov = document.getElementById('dqOverlay');
      if (ov) ov.addEventListener('click', closeDQDrawer);
    })();
  </script>
  <script>
    (function(){
      const input = document.getElementById('quickFilter');
      const table = document.querySelector('.tbl');
      const tbody = table?.querySelector('tbody');
      const thead = table?.querySelector('thead');
      const match = document.getElementById('matchCount');
      if (!input || !tbody) return;
      // Detect column indices by header labels
      let nameIdx = 1, phoneIdx = -1, idIdx = 0;
      if (thead){
        const headers = Array.from(thead.querySelectorAll('th')).map(th => (th.textContent||'').trim().toLowerCase());
        idIdx = Math.max(0, headers.findIndex(h => h === 'id'));
        const nameCand = headers.findIndex(h => h.includes('full name') || h === 'name');
        const phoneCand = headers.findIndex(h => h.includes('phone'));
        if (nameCand >= 0) nameIdx = nameCand;
        if (phoneCand >= 0) phoneIdx = phoneCand;
      }
      let t;
      function applyFilter(){
        const raw = (input.value || '').trim().toLowerCase();
        const terms = raw.split(/\s+/).filter(Boolean);
        let shown = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
          const cells = tr.querySelectorAll('td');
          if (!cells.length) return; // skip separators
          const id = (cells[idIdx]?.textContent || '').toLowerCase();
          const name = (cells[nameIdx]?.textContent || '').toLowerCase();
          const phone = (phoneIdx>=0 ? (cells[phoneIdx]?.textContent || '') : '').toLowerCase();
          const hay = id + ' ' + name + ' ' + phone;
          const ok = !terms.length || terms.every(term => hay.includes(term));
          tr.style.display = ok ? '' : 'none';
          if (ok) shown++;
        });
        if (match){ match.style.display='inline-block'; match.textContent = 'Matches: '+shown; }
      }
      input.addEventListener('input', ()=>{ clearTimeout(t); t = setTimeout(applyFilter, 120); });
    })();
  </script>
  <div class="toolbar">
    <?php if ($page>1): $p=$page-1; ?><a class="btn" href="data_quality.php?view=<?=urlencode($view)?>&page=<?=$p?>">« Prev</a><?php endif; ?>
    <?php $allq = $_GET; $allq['per'] = 'all'; $allq['page'] = 1; $allqs = http_build_query($allq); ?>
    <a class="btn" href="?<?=$allqs?>">Show all lists</a>
    <?php if ($page<$pages): $n=$page+1; ?><a class="btn" href="data_quality.php?view=<?=urlencode($view)?>&page=<?=$n?>">Next »</a><?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
echo renderAdminLayout('Data Quality', $content);
