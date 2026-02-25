<?php
require '../../config.php';
require '../../includes/portal_auth.php';

$ctx = getAuthContext();
if (!$ctx || $ctx['actor'] !== 'portal' || ($ctx['portal_role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit;
}

$teacherId = (int)$ctx['teacher_id'];
$portalUserId = (int)$ctx['portal_user_id'];

$stmt = $pdo->prepare('SELECT full_name, email FROM teachers WHERE id = ?');
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'Teacher', 'email' => ''];

$filterClassId = (int)($_GET['class_id'] ?? 0);
$filterTermId = (int)($_GET['term_id'] ?? 0);
$filterYear = trim((string)($_GET['academic_year'] ?? ''));

$classesStmt = $pdo->prepare("\n    SELECT DISTINCT c.id, c.name, c.grade, c.section, c.academic_year\n    FROM course_teachers ct\n    INNER JOIN classes c ON c.id = ct.class_id\n    WHERE ct.teacher_id = ? AND ct.is_active = 1\n    ORDER BY c.academic_year DESC, c.grade, c.section, c.name\n");
$classesStmt->execute([$teacherId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

$terms = $pdo->query("SELECT id, name, term_order FROM academic_terms_mvp ORDER BY term_order")->fetchAll(PDO::FETCH_ASSOC);

$years = [];
foreach ($classes as $c) {
    $y = (string)($c['academic_year'] ?? '');
    if ($y !== '') {
        $years[$y] = true;
    }
}
$yearList = array_keys($years);
rsort($yearList);

$where = [
    'm.entered_by_portal_user_id = :portal_user_id',
    'ct.teacher_id = :teacher_id',
    'ct.is_active = 1'
];
$params = [
    ':portal_user_id' => $portalUserId,
    ':teacher_id' => $teacherId,
];
if ($filterClassId > 0) {
    $where[] = 'm.class_id = :class_id';
    $params[':class_id'] = $filterClassId;
}
if ($filterTermId > 0) {
    $where[] = 'm.term_id = :term_id';
    $params[':term_id'] = $filterTermId;
}
if ($filterYear !== '') {
    $where[] = 'c.academic_year = :academic_year';
    $params[':academic_year'] = $filterYear;
}

$sql = "\n    SELECT\n        m.class_id,\n        m.course_id,\n        m.term_id,\n        c.name AS class_name,\n        c.grade,\n        c.section,\n        c.academic_year,\n        co.name AS course_name,\n        co.code AS course_code,\n        t.name AS term_name,\n        COUNT(*) AS rows_count,\n        COALESCE(AVG(m.total_mark), 0) AS avg_total,\n        COALESCE(MIN(m.total_mark), 0) AS min_total,\n        COALESCE(MAX(m.total_mark), 0) AS max_total,\n        MAX(m.updated_at) AS last_updated\n    FROM student_course_marks_mvp m\n    INNER JOIN classes c ON c.id = m.class_id\n    INNER JOIN courses co ON co.id = m.course_id\n    INNER JOIN academic_terms_mvp t ON t.id = m.term_id\n    INNER JOIN course_teachers ct\n        ON ct.class_id = m.class_id\n       AND ct.course_id = m.course_id\n    WHERE " . implode(' AND ', $where) . "\n    GROUP BY\n        m.class_id, m.course_id, m.term_id,\n        c.name, c.grade, c.section, c.academic_year,\n        co.name, co.code, t.name\n    ORDER BY last_updated DESC\n    LIMIT 300\n";

$listStmt = $pdo->prepare($sql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$totalContexts = count($rows);
$totalRows = array_sum(array_map(fn($r) => (int)$r['rows_count'], $rows));
$latestUpdate = $rows[0]['last_updated'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Marklist History</title>
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
  </style>
</head>
<body id="teacher_portal_body" class="bg-slate-100 font-body text-ink min-h-screen overflow-x-hidden">
<div id="sidebar_backdrop" class="fixed inset-0 bg-slate-900/50 z-30 hidden md:hidden"></div>

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
      <p class="font-semibold mt-1"><?= htmlspecialchars((string)$teacher['full_name']) ?></p>
      <?php if (!empty($teacher['email'])): ?><p class="text-xs text-sky/80 mt-1"><?= htmlspecialchars((string)$teacher['email']) ?></p><?php endif; ?>
    </div>

    <nav class="mt-6 space-y-2">
      <a href="dashboard.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
        <span class="w-6 text-center">DB</span><span class="sidebar-label">Dashboard</span>
      </a>
      <a href="marklist.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
        <span class="w-6 text-center">ML</span><span class="sidebar-label">Marklist Entry</span>
      </a>
      <a href="history.php" class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 text-sm font-medium">
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

  <main id="teacher_main" class="flex-1 p-4 md:p-7 md:h-screen md:overflow-y-auto min-w-0">
    <div class="flex items-center justify-between mb-3 md:mb-0">
      <button id="sidebar_open_mobile" class="inline-flex md:hidden items-center justify-center bg-ink text-white px-3 py-2 rounded-lg text-sm">Menu</button>
    </div>

    <div class="bg-gradient-to-r from-ocean to-cyan-700 rounded-2xl p-5 md:p-6 text-white shadow-lg">
      <p class="text-xs uppercase tracking-[0.2em] opacity-85">Teacher Workspace</p>
      <h2 class="font-display text-2xl md:text-3xl mt-1">Marklist History</h2>
      <p class="text-sm mt-2 opacity-90">Track all your saved marklists by class, course, term, and year.</p>
    </div>

    <section class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Saved Contexts</p>
        <p class="text-3xl font-display mt-1"><?= (int)$totalContexts ?></p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Saved Rows</p>
        <p class="text-3xl font-display mt-1"><?= (int)$totalRows ?></p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Last Activity</p>
        <p class="text-sm font-semibold mt-2"><?= $latestUpdate ? htmlspecialchars((string)$latestUpdate) : 'No activity yet' ?></p>
      </div>
    </section>

    <section class="mt-5 bg-white rounded-xl border border-slate-200 p-4 md:p-5">
      <form method="get" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Class</label>
          <select name="class_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            <option value="0">All Classes</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $filterClassId ? 'selected' : '') ?>>
                <?= htmlspecialchars((string)$c['name']) ?> (<?= htmlspecialchars((string)$c['grade']) ?><?= !empty($c['section']) ? ' - ' . htmlspecialchars((string)$c['section']) : '' ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Term</label>
          <select name="term_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            <option value="0">All Terms</option>
            <?php foreach ($terms as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= ((int)$t['id'] === $filterTermId ? 'selected' : '') ?>><?= htmlspecialchars((string)$t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Academic Year</label>
          <select name="academic_year" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            <option value="">All Years</option>
            <?php foreach ($yearList as $y): ?>
              <option value="<?= htmlspecialchars($y) ?>" <?= ($y === $filterYear ? 'selected' : '') ?>><?= htmlspecialchars($y) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex items-end gap-2">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">Filter</button>
          <a href="history.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded text-sm">Reset</a>
        </div>
      </form>

      <div class="mt-4 overflow-x-auto border border-slate-200 rounded-lg">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Class</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Course</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Term</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Year</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Rows</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Avg</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Range</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Last Updated</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (!$rows): ?>
              <tr><td colspan="9" class="px-3 py-4 text-slate-500">No marklist history found for current filters.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="px-3 py-2"><?= htmlspecialchars((string)$r['class_name']) ?> (<?= htmlspecialchars((string)$r['grade']) ?><?= !empty($r['section']) ? ' - ' . htmlspecialchars((string)$r['section']) : '' ?>)</td>
                  <td class="px-3 py-2"><?= htmlspecialchars((string)$r['course_name']) ?><?= !empty($r['course_code']) ? ' (' . htmlspecialchars((string)$r['course_code']) . ')' : '' ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars((string)$r['term_name']) ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars((string)$r['academic_year']) ?></td>
                  <td class="px-3 py-2"><?= (int)$r['rows_count'] ?></td>
                  <td class="px-3 py-2"><?= number_format((float)$r['avg_total'], 2) ?></td>
                  <td class="px-3 py-2"><?= number_format((float)$r['min_total'], 2) ?> - <?= number_format((float)$r['max_total'], 2) ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars((string)$r['last_updated']) ?></td>
                  <td class="px-3 py-2">
                    <a class="inline-flex items-center bg-ocean hover:bg-sky-700 text-white text-xs px-3 py-1.5 rounded"
                       href="marklist.php?class_id=<?= (int)$r['class_id'] ?>&course_id=<?= (int)$r['course_id'] ?>&term_id=<?= (int)$r['term_id'] ?>&autoload=1">
                      Open
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<script>
const sidebarKey = "teacher_sidebar_collapsed";
const body = document.getElementById("teacher_portal_body");
const sidebar = document.getElementById("teacher_sidebar");
const backdrop = document.getElementById("sidebar_backdrop");
const toggleBtn = document.getElementById("sidebar_toggle");
const openMobileBtn = document.getElementById("sidebar_open_mobile");
const closeMobileBtn = document.getElementById("sidebar_close_mobile");

function applyDesktopSidebarState(collapsed) {
  body.classList.toggle("sidebar-collapsed", collapsed);
  toggleBtn.textContent = collapsed ? ">>" : "<<";
}

function initDesktopSidebar() {
  const stored = localStorage.getItem(sidebarKey);
  applyDesktopSidebarState(stored === "1");
}

function openMobileSidebar() {
  sidebar.classList.remove("-translate-x-full");
  backdrop.classList.remove("hidden");
}

function closeMobileSidebar() {
  sidebar.classList.add("-translate-x-full");
  backdrop.classList.add("hidden");
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
  if (e.key === "Escape") closeMobileSidebar();
});

initDesktopSidebar();
</script>
</body>
</html>

