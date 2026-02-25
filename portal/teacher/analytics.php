<?php
require '../../config.php';
require '../../includes/portal_auth.php';

$ctx = getAuthContext();
if (!$ctx || $ctx['actor'] !== 'portal' || ($ctx['portal_role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit;
}

$teacherId = (int)$ctx['teacher_id'];
$stmt = $pdo->prepare('SELECT full_name, email FROM teachers WHERE id = ?');
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'Teacher', 'email' => ''];

$classesStmt = $pdo->prepare("\n    SELECT DISTINCT c.id, c.name, c.grade, c.section\n    FROM course_teachers ct\n    INNER JOIN classes c ON c.id = ct.class_id\n    WHERE ct.teacher_id = ? AND ct.is_active = 1\n    ORDER BY c.grade, c.section, c.name\n");
$classesStmt->execute([$teacherId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

$terms = $pdo->query("SELECT id, name, term_order FROM academic_terms_mvp ORDER BY term_order")->fetchAll(PDO::FETCH_ASSOC);

$selectedClassId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$selectedTermId = (int)($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

$coursesStmt = $pdo->prepare("\n    SELECT co.id, co.name, co.code\n    FROM course_teachers ct\n    INNER JOIN courses co ON co.id = ct.course_id\n    WHERE ct.teacher_id = ? AND ct.class_id = ? AND ct.is_active = 1\n    ORDER BY co.name\n");
$coursesStmt->execute([$teacherId, $selectedClassId]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedCourseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));

$summary = [
    'students' => 0,
    'avg' => 0.0,
    'min' => 0.0,
    'max' => 0.0,
    'pass_count' => 0,
    'fail_count' => 0,
    'pass_rate' => 0.0,
];
$bands = [
    '0-24.99' => 0,
    '25-49.99' => 0,
    '50-74.99' => 0,
    '75-100' => 0,
];

if ($selectedClassId > 0 && $selectedCourseId > 0 && $selectedTermId > 0) {
    $aggStmt = $pdo->prepare("\n        SELECT\n            COUNT(*) AS students,\n            COALESCE(AVG(total_mark),0) AS avg_mark,\n            COALESCE(MIN(total_mark),0) AS min_mark,\n            COALESCE(MAX(total_mark),0) AS max_mark,\n            SUM(CASE WHEN total_mark >= 50 THEN 1 ELSE 0 END) AS pass_count,\n            SUM(CASE WHEN total_mark < 50 THEN 1 ELSE 0 END) AS fail_count\n        FROM student_course_marks_mvp\n        WHERE class_id = ? AND course_id = ? AND term_id = ?\n    ");
    $aggStmt->execute([$selectedClassId, $selectedCourseId, $selectedTermId]);
    $a = $aggStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $students = (int)($a['students'] ?? 0);
    $passCount = (int)($a['pass_count'] ?? 0);
    $failCount = (int)($a['fail_count'] ?? 0);

    $summary = [
        'students' => $students,
        'avg' => round((float)($a['avg_mark'] ?? 0), 2),
        'min' => round((float)($a['min_mark'] ?? 0), 2),
        'max' => round((float)($a['max_mark'] ?? 0), 2),
        'pass_count' => $passCount,
        'fail_count' => $failCount,
        'pass_rate' => $students > 0 ? round(($passCount / $students) * 100, 2) : 0.0,
    ];

    $bandStmt = $pdo->prepare("\n        SELECT\n            SUM(CASE WHEN total_mark < 25 THEN 1 ELSE 0 END) AS b1,\n            SUM(CASE WHEN total_mark >= 25 AND total_mark < 50 THEN 1 ELSE 0 END) AS b2,\n            SUM(CASE WHEN total_mark >= 50 AND total_mark < 75 THEN 1 ELSE 0 END) AS b3,\n            SUM(CASE WHEN total_mark >= 75 THEN 1 ELSE 0 END) AS b4\n        FROM student_course_marks_mvp\n        WHERE class_id = ? AND course_id = ? AND term_id = ?\n    ");
    $bandStmt->execute([$selectedClassId, $selectedCourseId, $selectedTermId]);
    $b = $bandStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $bands['0-24.99'] = (int)($b['b1'] ?? 0);
    $bands['25-49.99'] = (int)($b['b2'] ?? 0);
    $bands['50-74.99'] = (int)($b['b3'] ?? 0);
    $bands['75-100'] = (int)($b['b4'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Analytics</title>
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
      <a href="history.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
        <span class="w-6 text-center">HS</span><span class="sidebar-label">History</span>
      </a>
      <a href="analytics.php" class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 text-sm font-medium">
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
      <h2 class="font-display text-2xl md:text-3xl mt-1">Performance Analytics</h2>
      <p class="text-sm mt-2 opacity-90">Instant snapshot of course outcomes for faster teaching decisions.</p>
    </div>

    <section class="mt-5 bg-white border border-slate-200 rounded-xl p-4">
      <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Class</label>
          <select id="class_id" name="class_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            <?php foreach ($classes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $selectedClassId ? 'selected' : '') ?>>
                <?= htmlspecialchars((string)$c['name']) ?> (<?= htmlspecialchars((string)$c['grade']) ?><?= !empty($c['section']) ? ' - ' . htmlspecialchars((string)$c['section']) : '' ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Course</label>
          <select id="course_id" name="course_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            <?php foreach ($courses as $co): ?>
              <option value="<?= (int)$co['id'] ?>" <?= ((int)$co['id'] === $selectedCourseId ? 'selected' : '') ?>>
                <?= htmlspecialchars((string)$co['name']) ?><?= !empty($co['code']) ? ' (' . htmlspecialchars((string)$co['code']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Term</label>
          <select name="term_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            <?php foreach ($terms as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= ((int)$t['id'] === $selectedTermId ? 'selected' : '') ?>><?= htmlspecialchars((string)$t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex items-end">
          <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded px-4 py-2 text-sm">Refresh Analytics</button>
        </div>
      </form>
    </section>

    <section class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4">
      <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-xs text-slate-500">Students</p><p class="text-2xl font-display mt-1"><?= (int)$summary['students'] ?></p></div>
      <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-xs text-slate-500">Average</p><p class="text-2xl font-display mt-1"><?= number_format((float)$summary['avg'], 2) ?></p></div>
      <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-xs text-slate-500">Min</p><p class="text-2xl font-display mt-1"><?= number_format((float)$summary['min'], 2) ?></p></div>
      <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-xs text-slate-500">Max</p><p class="text-2xl font-display mt-1"><?= number_format((float)$summary['max'], 2) ?></p></div>
      <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-xs text-slate-500">Pass</p><p class="text-2xl font-display mt-1 text-emerald-700"><?= (int)$summary['pass_count'] ?></p></div>
      <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-xs text-slate-500">Pass Rate</p><p class="text-2xl font-display mt-1 text-ocean"><?= number_format((float)$summary['pass_rate'], 2) ?>%</p></div>
    </section>

    <section class="mt-5 bg-white border border-slate-200 rounded-xl p-4">
      <h3 class="font-semibold text-slate-800 mb-3">Score Distribution</h3>
      <div class="space-y-3">
        <?php foreach ($bands as $label => $count): ?>
          <?php $pct = $summary['students'] > 0 ? round(($count / $summary['students']) * 100, 2) : 0; ?>
          <div>
            <div class="flex items-center justify-between text-xs text-slate-700 mb-1">
              <span><?= htmlspecialchars($label) ?></span>
              <span><?= (int)$count ?> students (<?= number_format($pct, 2) ?>%)</span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-ocean" style="width: <?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
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
const classSelect = document.getElementById("class_id");
const courseSelect = document.getElementById("course_id");

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

classSelect.addEventListener("change", () => {
  const url = new URL(window.location.href);
  url.searchParams.set("class_id", classSelect.value);
  url.searchParams.delete("course_id");
  window.location.href = url.toString();
});
</script>
</body>
</html>
