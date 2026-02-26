<?php
require_once __DIR__ . '/../../includes/bootstrap_portal.php';

$ctx = getAuthContext();
if (!$ctx || $ctx['actor'] !== 'portal' || $ctx['portal_role'] !== 'teacher') {
    header('Location: ../../login.php');
    exit;
}

function tableExists(PDO $pdo, string $tableName): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

$teacherId = (int)$ctx['teacher_id'];
$stmt = $pdo->prepare("SELECT full_name, email, phone FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'Teacher', 'email' => '', 'phone' => ''];
$teacherName = (string)$teacher['full_name'];

$terms = $pdo->query("SELECT id, name, term_order FROM academic_terms_mvp WHERE is_active = 1 ORDER BY term_order")->fetchAll(PDO::FETCH_ASSOC);
$selectedTermId = (int)($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));
$selectedTermName = '';
foreach ($terms as $t) {
    if ((int)$t['id'] === $selectedTermId) {
        $selectedTermName = (string)$t['name'];
        break;
    }
}
if ($selectedTermName === '' && !empty($terms[0]['name'])) {
    $selectedTermName = (string)$terms[0]['name'];
}

$hasMarksTable = tableExists($pdo, 'student_course_marks_mvp');
$savedRowsSql = $hasMarksTable ? "COALESCE(ms.saved_rows, 0) AS saved_rows" : "0 AS saved_rows";
$savedJoinSql = $hasMarksTable
    ? "LEFT JOIN (
            SELECT class_id, course_id, COUNT(*) AS saved_rows
            FROM student_course_marks_mvp
            WHERE term_id = ?
            GROUP BY class_id, course_id
        ) ms ON ms.class_id = ct.class_id AND ms.course_id = ct.course_id"
    : "";

$sql = "
    SELECT
        ct.class_id,
        ct.course_id,
        c.name AS class_name,
        c.grade,
        c.section,
        c.academic_year,
        co.name AS course_name,
        co.code,
        COALESCE(ce.active_students, 0) AS class_size,
        {$savedRowsSql}
    FROM course_teachers ct
    INNER JOIN classes c ON c.id = ct.class_id
    INNER JOIN courses co ON co.id = ct.course_id
    LEFT JOIN (
        SELECT class_id, COUNT(*) AS active_students
        FROM class_enrollments
        WHERE status = 'active'
        GROUP BY class_id
    ) ce ON ce.class_id = ct.class_id
    {$savedJoinSql}
    WHERE ct.teacher_id = ? AND ct.is_active = 1
    ORDER BY c.grade, c.section, co.name
";

$stmt = $pdo->prepare($sql);
if ($hasMarksTable) {
    $stmt->execute([$selectedTermId, $teacherId]);
} else {
    $stmt->execute([$teacherId]);
}
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAssignments = count($assignments);
$totalClasses = count(array_unique(array_map(fn($r) => (int)$r['class_id'], $assignments)));
$totalStudents = array_sum(array_map(fn($r) => (int)$r['class_size'], $assignments));
$completedAssignments = 0;
foreach ($assignments as $r) {
    if ((int)$r['saved_rows'] > 0) {
        $completedAssignments++;
    }
}

$assignmentsByClass = [];
foreach ($assignments as $row) {
    $key = (string)$row['class_id'];
    if (!isset($assignmentsByClass[$key])) {
        $assignmentsByClass[$key] = [
            'class_id' => (int)$row['class_id'],
            'class_name' => $row['class_name'],
            'grade' => $row['grade'],
            'section' => $row['section'],
            'academic_year' => $row['academic_year'],
            'class_size' => (int)$row['class_size'],
            'courses' => []
        ];
    }
    $assignmentsByClass[$key]['courses'][] = $row;
}

$homeroomStatusByClass = [];
if (!empty($assignmentsByClass) && tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
    $classIds = array_map(fn($c) => (int)$c['class_id'], $assignmentsByClass);
    if (!empty($classIds)) {
        $ph = implode(',', array_fill(0, count($classIds), '?'));
        $params = array_merge([$selectedTermId], $classIds);
        $stmt = $pdo->prepare("
            SELECT class_id, status, submitted_at, updated_at
            FROM homeroom_term_matrix_submissions_mvp
            WHERE term_id = ? AND class_id IN ($ph)
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $homeroomStatusByClass[(int)$row['class_id']] = [
                'status' => (string)($row['status'] ?? ''),
                'submitted_at' => $row['submitted_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null
            ];
        }
    }
}

$lastSession = null;
if ($hasMarksTable) {
    $lastStmt = $pdo->prepare("
        SELECT m.class_id, m.course_id, m.term_id, MAX(m.updated_at) AS last_updated
        FROM student_course_marks_mvp m
        INNER JOIN course_teachers ct
            ON ct.class_id = m.class_id
           AND ct.course_id = m.course_id
           AND ct.teacher_id = ?
           AND ct.is_active = 1
        WHERE m.entered_by_portal_user_id = ?
        GROUP BY m.class_id, m.course_id, m.term_id
        ORDER BY last_updated DESC
        LIMIT 1
    ");
    $lastStmt->execute([$teacherId, (int)$ctx['portal_user_id']]);
    $lastSession = $lastStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Portal</title>
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
      .panel-card { box-shadow: 0 16px 36px rgba(15, 94, 131, 0.08); }
      .smooth-input { transition: border-color .15s ease, box-shadow .15s ease; }
      .smooth-input:focus { border-color: #0f5e83; box-shadow: 0 0 0 3px rgba(15, 94, 131, 0.16); outline: none; }
      .soft-grid {
        background-image:
          linear-gradient(to right, rgba(15, 94, 131, 0.06) 1px, transparent 1px),
          linear-gradient(to bottom, rgba(15, 94, 131, 0.06) 1px, transparent 1px);
        background-size: 28px 28px;
      }
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
      <p class="font-semibold mt-1"><?= htmlspecialchars($teacherName) ?></p>
      <?php if (!empty($teacher['email'])): ?><p class="text-xs text-sky/80 mt-1"><?= htmlspecialchars($teacher['email']) ?></p><?php endif; ?>
      <?php if (!empty($teacher['phone'])): ?><p class="text-xs text-sky/80"><?= htmlspecialchars($teacher['phone']) ?></p><?php endif; ?>
    </div>

    <nav class="mt-6 space-y-2">
      <a href="dashboard.php?term_id=<?= $selectedTermId ?>" class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 text-sm font-medium">
        <span class="w-6 text-center">DB</span><span class="sidebar-label">Dashboard</span>
      </a>
      <a href="marklist.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
        <span class="w-6 text-center">ML</span><span class="sidebar-label">Marklist Entry</span>
      </a>
      <a href="history.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
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

  <main id="teacher_main" class="flex-1 p-4 md:p-7 md:ml-0 md:h-screen md:overflow-y-auto min-w-0">
    <div class="flex items-center justify-between mb-3 md:mb-0">
      <button id="sidebar_open_mobile" class="inline-flex md:hidden items-center justify-center bg-ink text-white px-3 py-2 rounded-lg text-sm">Menu</button>
    </div>

    <div class="bg-gradient-to-r from-ocean to-cyan-700 rounded-2xl p-5 md:p-6 text-white shadow-lg soft-grid">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-[0.2em] opacity-85">Teacher Workspace</p>
          <h2 class="font-display text-2xl md:text-3xl mt-1">Assignments & Marklist Control</h2>
          <p class="text-sm mt-2 opacity-90">Use this dashboard to open marklists quickly per class and course.</p>
          <?php if ($lastSession): ?>
            <div class="mt-3">
              <a href="marklist.php?class_id=<?= (int)$lastSession['class_id'] ?>&course_id=<?= (int)$lastSession['course_id'] ?>&term_id=<?= (int)$lastSession['term_id'] ?>&autoload=1"
                 class="inline-flex items-center bg-white/15 hover:bg-white/25 border border-white/35 px-3 py-2 rounded-lg text-xs font-semibold">
                Continue Last Session
              </a>
            </div>
          <?php endif; ?>
        </div>
        <div class="bg-white/15 rounded-xl p-3 min-w-[260px]">
          <label for="term_id" class="block text-xs uppercase tracking-wider mb-1">Active Term</label>
          <select id="term_id" class="w-full text-sm rounded border border-white/40 bg-white/15 text-white px-3 py-2 smooth-input">
            <?php foreach ($terms as $term): ?>
              <option value="<?= (int)$term['id'] ?>" <?= ((int)$term['id'] === $selectedTermId ? 'selected' : '') ?> style="color:#13212d;">
                <?= htmlspecialchars($term['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <section class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4 panel-card">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Assignments</p>
        <p class="text-3xl font-display mt-1"><?= $totalAssignments ?></p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4 panel-card">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Classes</p>
        <p class="text-3xl font-display mt-1"><?= $totalClasses ?></p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4 panel-card">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Students (active)</p>
        <p class="text-3xl font-display mt-1"><?= $totalStudents ?></p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4 panel-card">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Marked Courses</p>
        <p class="text-3xl font-display mt-1"><?= $completedAssignments ?></p>
      </div>
    </section>

    <section class="mt-5">
      <div class="bg-white rounded-xl border border-slate-200 p-4 md:p-5 panel-card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
          <h3 class="font-display text-xl">My Class Workspaces</h3>
          <input id="assignment_search" type="text" placeholder="Search class or course..." class="w-full sm:w-72 border border-slate-300 rounded-lg px-3 py-2 text-sm smooth-input">
        </div>

        <?php if (!$assignmentsByClass): ?>
          <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center bg-slate-50">
            <p class="font-semibold text-slate-700">No active class-course assignments found.</p>
            <p class="text-sm text-slate-500 mt-1">Ask admin to assign you classes and courses in the Class/Course assignment modules.</p>
          </div>
        <?php else: ?>
          <div id="class_cards" class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <?php foreach ($assignmentsByClass as $block): ?>
              <article class="class-card rounded-xl border border-slate-200 overflow-hidden bg-white transition-transform duration-150 hover:-translate-y-0.5" data-search="<?= htmlspecialchars(strtolower($block['class_name'] . ' ' . $block['grade'] . ' ' . $block['section'])) ?>">
                <header class="px-4 py-3 bg-sand border-b border-slate-200">
                  <?php
                    $classSize = max(1, (int)$block['class_size']);
                    $totalCourses = count($block['courses']);
                    $fullySubmittedCourses = 0;
                    foreach ($block['courses'] as $cs) {
                        $sRows = (int)($cs['saved_rows'] ?? 0);
                        if ($sRows >= $classSize) {
                            $fullySubmittedCourses++;
                        }
                    }
                    $classSubmitPct = $totalCourses > 0 ? (int)round(($fullySubmittedCourses / $totalCourses) * 100) : 0;
                  ?>
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <h4 class="font-semibold text-slate-800"><?= htmlspecialchars($block['class_name']) ?></h4>
                      <p class="text-xs text-slate-600 mt-0.5">
                        <?= htmlspecialchars($block['grade']) ?><?= !empty($block['section']) ? ' - ' . htmlspecialchars($block['section']) : '' ?> | <?= htmlspecialchars((string)$block['academic_year']) ?> | <?= (int)$block['class_size'] ?> active students
                      </p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                      <span class="text-xs bg-white border border-slate-300 rounded-full px-2 py-1"><?= count($block['courses']) ?> courses</span>
                      <?php
                        $homeroom = $homeroomStatusByClass[(int)$block['class_id']] ?? null;
                        $hStatus = (string)($homeroom['status'] ?? '');
                        $badgeCls = 'bg-slate-100 border-slate-300 text-slate-700';
                        $badgeText = 'Homeroom: Not submitted';
                        if ($hStatus === 'submitted') {
                            $badgeCls = 'bg-emerald-100 border-emerald-300 text-emerald-800';
                            $badgeText = 'Homeroom: Submitted';
                        } elseif ($hStatus === 'draft') {
                            $badgeCls = 'bg-amber-100 border-amber-300 text-amber-800';
                            $badgeText = 'Homeroom: Draft saved';
                        }
                      ?>
                      <span class="text-[11px] rounded-full border px-2 py-1 <?= $badgeCls ?>"><?= htmlspecialchars($badgeText) ?></span>
                      <span class="text-[11px] rounded-full border px-2 py-1 bg-white border-slate-300 text-slate-700">
                        Course Submit: <?= $fullySubmittedCourses ?>/<?= $totalCourses ?>
                      </span>
                    </div>
                  </div>
                  <?php if ($hStatus === 'submitted' && !empty($homeroom['submitted_at'])): ?>
                    <p class="text-[11px] text-emerald-700 mt-2">Submitted at: <?= htmlspecialchars((string)$homeroom['submitted_at']) ?></p>
                  <?php endif; ?>
                  <details class="mt-2 rounded-lg border border-slate-200 bg-white/70">
                    <summary class="cursor-pointer list-none px-3 py-2 text-[11px] font-semibold text-slate-700">
                      Class Term Status (collapsed by default)
                    </summary>
                    <div class="px-3 pb-3 pt-1 text-[11px] text-slate-600 space-y-1">
                      <p>Academic Year: <?= htmlspecialchars((string)$block['academic_year']) ?></p>
                      <p>Term: <?= htmlspecialchars($selectedTermName !== '' ? $selectedTermName : (string)$selectedTermId) ?></p>
                      <p>Fully Submitted Courses: <?= $fullySubmittedCourses ?> / <?= $totalCourses ?> (<?= $classSubmitPct ?>%)</p>
                    </div>
                  </details>
                </header>
                <div class="p-3 space-y-2">
                  <?php foreach ($block['courses'] as $course): ?>
                    <?php
                      $savedRows = (int)($course['saved_rows'] ?? 0);
                      $progress = min(100, (int)round(($savedRows / $classSize) * 100));
                    ?>
                    <div class="course-row border border-slate-200 rounded-lg p-3" data-search="<?= htmlspecialchars(strtolower($course['course_name'] . ' ' . $course['code'])) ?>">
                      <div class="flex items-start justify-between gap-2">
                        <div>
                          <p class="font-medium text-slate-800"><?= htmlspecialchars($course['course_name']) ?></p>
                          <p class="text-xs text-slate-500"><?= htmlspecialchars($course['code']) ?> | Saved rows: <?= $savedRows ?></p>
                        </div>
                        <a class="inline-flex items-center bg-ocean hover:bg-sky-700 text-white text-xs px-3 py-1.5 rounded"
                           href="marklist.php?class_id=<?= (int)$course['class_id'] ?>&course_id=<?= (int)$course['course_id'] ?>&term_id=<?= $selectedTermId ?>&autoload=1">
                          Open Marklist
                        </a>
                      </div>
                      <div class="mt-2">
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                          <div class="h-full bg-mint" style="width: <?= $progress ?>%"></div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Progress estimate: <?= $progress ?>%</p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
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
  const collapsed = stored === "1";
  applyDesktopSidebarState(collapsed);
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

const termSelect = document.getElementById("term_id");
const searchInput = document.getElementById("assignment_search");

if (termSelect) {
  termSelect.addEventListener("change", () => {
    const url = new URL(window.location.href);
    url.searchParams.set("term_id", termSelect.value);
    window.location.href = url.toString();
  });
}

if (searchInput) {
  let searchTimer = null;
  searchInput.addEventListener("input", () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      const term = searchInput.value.trim().toLowerCase();
      document.querySelectorAll(".class-card").forEach((card) => {
        const classText = card.dataset.search || "";
        let courseHit = false;
        card.querySelectorAll(".course-row").forEach((row) => {
          const hit = (row.dataset.search || "").includes(term);
          row.style.display = hit || term === "" ? "" : "none";
          if (hit) courseHit = true;
        });
        const classHit = classText.includes(term);
        card.style.display = (classHit || courseHit || term === "") ? "" : "none";
      });
    }, 120);
  });
}
</script>
</body>
</html>



