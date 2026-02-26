<?php
require_once __DIR__ . '/../../includes/bootstrap_portal.php';

$ctx = getAuthContext();
if (!$ctx || $ctx['actor'] !== 'portal' || ($ctx['portal_role'] ?? '') !== 'homeroom') {
    header('Location: ../../login.php');
    exit;
}

$teacherId = (int)$ctx['teacher_id'];
$stmt = $pdo->prepare("SELECT full_name, email, phone FROM teachers WHERE id = ? LIMIT 1");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'Homeroom Teacher', 'email' => '', 'phone' => ''];
$teacherName = (string)$teacher['full_name'];

$terms = $pdo->query("SELECT id, name, term_order FROM academic_terms_mvp WHERE is_active = 1 ORDER BY term_order")->fetchAll(PDO::FETCH_ASSOC);
$selectedTermId = (int)($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.name, c.grade, c.section, c.academic_year
    FROM class_teachers ct
    INNER JOIN classes c ON c.id = ct.class_id
    WHERE ct.teacher_id = ? AND ct.role = 'homeroom' AND ct.is_active = 1
    ORDER BY c.grade, c.section, c.name
");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$selectedClassId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$view = (string)($_GET['view'] ?? 'dashboard');
if ($view !== 'dashboard' && $view !== 'matrix') {
    $view = 'dashboard';
}

function tableExistsForDashboard(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

$selectedTermName = '';
foreach ($terms as $termRow) {
    if ((int)$termRow['id'] === $selectedTermId) {
        $selectedTermName = (string)$termRow['name'];
        break;
    }
}
if ($selectedTermName === '' && count($terms)) {
    $selectedTermName = (string)$terms[0]['name'];
}

$classIds = array_map(static fn($c) => (int)$c['id'], $classes);
$dashboardTotals = [
    'classes' => count($classes),
    'students' => 0,
    'courses' => 0,
    'new_updates' => 0,
    'submitted' => 0,
    'draft' => 0
];
$dashboardSnapshots = [];
$dashboardCourseMap = [];

if (count($classIds)) {
    $in = implode(',', array_fill(0, count($classIds), '?'));

    $studentCounts = [];
    $studentStmt = $pdo->prepare("
        SELECT class_id, COUNT(*) AS total_students
        FROM class_enrollments
        WHERE status = 'active' AND class_id IN ($in)
        GROUP BY class_id
    ");
    $studentStmt->execute($classIds);
    foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentCounts[(int)$row['class_id']] = (int)$row['total_students'];
    }

    $courseCounts = [];
    $courseCountStmt = $pdo->prepare("
        SELECT class_id, COUNT(DISTINCT course_id) AS total_courses
        FROM course_teachers
        WHERE is_active = 1 AND class_id IN ($in)
        GROUP BY class_id
    ");
    $courseCountStmt->execute($classIds);
    foreach ($courseCountStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $courseCounts[(int)$row['class_id']] = (int)$row['total_courses'];
    }

    $marksMeta = [];
    if (tableExistsForDashboard($pdo, 'student_course_marks_mvp')) {
        $markStmt = $pdo->prepare("
            SELECT class_id, COUNT(*) AS mark_rows, MAX(updated_at) AS latest_marks_updated_at
            FROM student_course_marks_mvp
            WHERE term_id = ? AND class_id IN ($in)
            GROUP BY class_id
        ");
        $markStmt->execute(array_merge([$selectedTermId], $classIds));
        foreach ($markStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $marksMeta[(int)$row['class_id']] = [
                'mark_rows' => (int)$row['mark_rows'],
                'latest_marks_updated_at' => $row['latest_marks_updated_at'] ? (string)$row['latest_marks_updated_at'] : null
            ];
        }
    }

    $submissionMeta = [];
    if (tableExistsForDashboard($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        $submissionStmt = $pdo->prepare("
            SELECT class_id, status, updated_at, submitted_at
            FROM homeroom_term_matrix_submissions_mvp
            WHERE term_id = ? AND class_id IN ($in)
        ");
        $submissionStmt->execute(array_merge([$selectedTermId], $classIds));
        foreach ($submissionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $submissionMeta[(int)$row['class_id']] = [
                'status' => (string)$row['status'],
                'updated_at' => $row['updated_at'] ? (string)$row['updated_at'] : null,
                'submitted_at' => $row['submitted_at'] ? (string)$row['submitted_at'] : null
            ];
        }
    }

    $courseMapStmt = $pdo->prepare("
        SELECT ct.class_id, co.name AS course_name, co.code, t.full_name AS teacher_name
        FROM course_teachers ct
        INNER JOIN courses co ON co.id = ct.course_id
        LEFT JOIN teachers t ON t.id = ct.teacher_id
        WHERE ct.is_active = 1 AND ct.class_id IN ($in)
        ORDER BY ct.class_id, co.name
    ");
    $courseMapStmt->execute($classIds);
    foreach ($courseMapStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cid = (int)$row['class_id'];
        if (!isset($dashboardCourseMap[$cid])) {
            $dashboardCourseMap[$cid] = [];
        }
        $dashboardCourseMap[$cid][] = [
            'course_name' => (string)$row['course_name'],
            'code' => (string)($row['code'] ?? ''),
            'teacher_name' => (string)($row['teacher_name'] ?? '')
        ];
    }

    foreach ($classes as $classRow) {
        $cid = (int)$classRow['id'];
        $students = (int)($studentCounts[$cid] ?? 0);
        $courses = (int)($courseCounts[$cid] ?? 0);
        $markRows = (int)($marksMeta[$cid]['mark_rows'] ?? 0);
        $latestTeacherUpdate = $marksMeta[$cid]['latest_marks_updated_at'] ?? null;

        $status = (string)($submissionMeta[$cid]['status'] ?? 'new');
        $submissionUpdated = $submissionMeta[$cid]['updated_at'] ?? null;
        $submittedAt = $submissionMeta[$cid]['submitted_at'] ?? null;

        $hasNewTeacherUpdates = false;
        if ($markRows > 0) {
            if ($status === 'new') {
                $hasNewTeacherUpdates = true;
            } elseif ($latestTeacherUpdate === null) {
                $hasNewTeacherUpdates = false;
            } elseif ($submissionUpdated === null) {
                $hasNewTeacherUpdates = true;
            } else {
                $hasNewTeacherUpdates = strtotime($latestTeacherUpdate) > strtotime($submissionUpdated);
            }
        }

        if ($status === 'submitted') {
            $dashboardTotals['submitted']++;
        } elseif ($status === 'draft') {
            $dashboardTotals['draft']++;
        }
        if ($hasNewTeacherUpdates) {
            $dashboardTotals['new_updates']++;
        }
        $dashboardTotals['students'] += $students;
        $dashboardTotals['courses'] += $courses;

        $dashboardSnapshots[] = [
            'class_id' => $cid,
            'class_name' => (string)$classRow['name'],
            'grade' => (string)$classRow['grade'],
            'section' => (string)($classRow['section'] ?? ''),
            'academic_year' => (string)($classRow['academic_year'] ?? ''),
            'students' => $students,
            'courses' => $courses,
            'mark_rows' => $markRows,
            'latest_teacher_update' => $latestTeacherUpdate,
            'status' => $status,
            'submitted_at' => $submittedAt,
            'has_new_teacher_updates' => $hasNewTeacherUpdates
        ];
    }
}
$csrf = SecurityHelper::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homeroom Portal</title>
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
            mint: "#0e9f6e",
            sand: "#f7f2e8"
          }
        }
      }
    }
  </script>
  <style>
    .sidebar-collapsed .sidebar-label { display: none; }
    .sidebar-collapsed #homeroom_sidebar { width: 5.5rem; }
    .sidebar-collapsed .sidebar-user { display: none; }
    .sidebar-collapsed .sidebar-logo-mini { display: block; }
    .sidebar-collapsed .sidebar-logo-full { display: none; }
    .panel-card { box-shadow: 0 16px 36px rgba(15, 94, 131, 0.08); }
    .smooth-input { transition: border-color .15s ease, box-shadow .15s ease; }
    .smooth-input:focus { border-color: #0f5e83; box-shadow: 0 0 0 3px rgba(15, 94, 131, 0.16); outline: none; }
    .table-shell { height: min(64vh, 680px); overflow: auto; }
    .table-shell thead th { position: sticky; top: 0; z-index: 2; background: #f8fafc; }
    .soft-grid {
      background-image:
        linear-gradient(to right, rgba(15, 94, 131, 0.06) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(15, 94, 131, 0.06) 1px, transparent 1px);
      background-size: 28px 28px;
    }
  </style>
</head>
<body id="homeroom_body" class="bg-slate-100 font-body text-ink min-h-screen overflow-x-hidden">
<div id="sidebar_backdrop" class="fixed inset-0 bg-slate-900/50 z-30 hidden md:hidden"></div>

<div class="min-h-screen md:h-screen md:flex md:overflow-hidden">
  <aside id="homeroom_sidebar" class="fixed md:sticky md:top-0 top-0 left-0 h-screen w-72 bg-ink text-white p-4 md:p-5 z-40 transform -translate-x-full md:translate-x-0 transition-all duration-200 ease-out md:flex-shrink-0">
    <div class="flex items-center justify-between gap-2">
      <div>
        <p class="text-[11px] uppercase tracking-[0.2em] text-cyan-100/90 sidebar-label">Portal</p>
        <h1 class="font-display text-2xl mt-1 sidebar-logo-full">Homeroom Hub</h1>
        <h1 class="font-display text-2xl mt-1 hidden sidebar-logo-mini">HH</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="sidebar_toggle" class="hidden md:inline-flex items-center justify-center w-9 h-9 rounded bg-white/10 hover:bg-white/20 text-white" title="Toggle sidebar"><<</button>
        <button id="sidebar_close_mobile" class="inline-flex md:hidden items-center justify-center w-9 h-9 rounded bg-white/10 hover:bg-white/20 text-white" title="Close menu">X</button>
      </div>
    </div>

    <div class="mt-5 rounded-xl bg-white/10 p-4 sidebar-user sidebar-label">
      <p class="text-xs text-cyan-100/80">Logged In As</p>
      <p class="font-semibold mt-1"><?= htmlspecialchars($teacherName) ?></p>
      <?php if (!empty($teacher['email'])): ?><p class="text-xs text-cyan-100/80 mt-1"><?= htmlspecialchars($teacher['email']) ?></p><?php endif; ?>
      <?php if (!empty($teacher['phone'])): ?><p class="text-xs text-cyan-100/80"><?= htmlspecialchars($teacher['phone']) ?></p><?php endif; ?>
    </div>

    <nav class="mt-6 space-y-2">
      <a href="dashboard.php?view=dashboard" class="flex items-center gap-2 rounded-lg <?= $view === 'dashboard' ? 'bg-white/15 font-medium' : 'hover:bg-white/10' ?> px-3 py-2 text-sm">
        <span class="w-6 text-center">DB</span><span class="sidebar-label">Dashboard</span>
      </a>
      <a href="dashboard.php?view=matrix" class="flex items-center gap-2 rounded-lg <?= $view === 'matrix' ? 'bg-white/15 font-medium' : 'hover:bg-white/10' ?> px-3 py-2 text-sm">
        <span class="w-6 text-center">TM</span><span class="sidebar-label">Term Matrix</span>
      </a>
      <a href="history.php" class="flex items-center gap-2 rounded-lg hover:bg-white/10 px-3 py-2 text-sm">
        <span class="w-6 text-center">HS</span><span class="sidebar-label">History</span>
      </a>
    </nav>

    <div class="mt-8">
      <a href="../../logout.php" class="inline-flex items-center bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        <span class="sidebar-label">Logout</span><span class="md:hidden">Logout</span>
      </a>
    </div>
  </aside>

  <main class="flex-1 p-4 md:p-7 md:h-screen md:overflow-y-auto min-w-0">
    <div class="flex items-center justify-between mb-3 md:mb-0">
      <button id="sidebar_open_mobile" class="inline-flex md:hidden items-center justify-center bg-ink text-white px-3 py-2 rounded-lg text-sm">Menu</button>
    </div>

    <div class="bg-gradient-to-r from-ocean to-cyan-700 rounded-2xl p-5 md:p-6 text-white shadow-lg soft-grid">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-[0.2em] opacity-85">Homeroom Workspace</p>
          <h2 class="font-display text-2xl md:text-3xl mt-1">Term Results Workflow</h2>
          <p class="text-sm mt-2 opacity-90">Save or submit your class-term matrix, then wait for new teacher mark updates.</p>
        </div>
        <?php if ($view === 'matrix'): ?>
          <div class="flex flex-wrap gap-2">
            <button id="load_btn" class="bg-white text-ink rounded px-4 py-2 text-sm font-semibold hover:bg-slate-100">Refresh Matrix</button>
            <button id="save_attendance_btn" class="bg-cyan-600 text-white rounded px-4 py-2 text-sm font-semibold hover:bg-cyan-700">Save Attendance</button>
            <button id="save_draft_btn" class="bg-amber-500 text-white rounded px-4 py-2 text-sm font-semibold hover:bg-amber-600">Save Draft</button>
            <button id="submit_matrix_btn" class="bg-violet-700 text-white rounded px-4 py-2 text-sm font-semibold hover:bg-violet-800">Submit Matrix</button>
            <a href="history.php" class="inline-flex items-center bg-mint text-white rounded px-4 py-2 text-sm font-semibold hover:bg-emerald-700">History</a>
          </div>
        <?php else: ?>
          <div class="flex flex-wrap gap-2">
            <a href="dashboard.php?view=matrix" class="inline-flex items-center bg-white text-ink rounded px-4 py-2 text-sm font-semibold hover:bg-slate-100">Open Term Matrix</a>
            <a href="history.php" class="inline-flex items-center bg-mint text-white rounded px-4 py-2 text-sm font-semibold hover:bg-emerald-700">History</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($view === 'dashboard'): ?>
    <section class="mt-5 bg-white rounded-xl border border-slate-200 panel-card p-4 md:p-5">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="text-xs uppercase tracking-wide text-slate-500">Dashboard Term</p>
          <h3 class="font-display text-xl text-slate-800 mt-1"><?= htmlspecialchars($selectedTermName ?: 'Current Term') ?></h3>
        </div>
        <form method="get" class="flex items-center gap-2">
          <input type="hidden" name="view" value="dashboard">
          <select name="term_id" class="border border-slate-300 rounded px-3 py-2 text-sm">
            <?php foreach ($terms as $term): ?>
              <option value="<?= (int)$term['id'] ?>" <?= ((int)$term['id'] === $selectedTermId ? 'selected' : '') ?>><?= htmlspecialchars($term['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="bg-slate-800 text-white rounded px-3 py-2 text-sm hover:bg-slate-900">Apply</button>
        </form>
      </div>
      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-500">My Classes</p>
          <p class="text-3xl font-display mt-1 text-slate-800"><?= (int)$dashboardTotals['classes'] ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-500">Active Students</p>
          <p class="text-3xl font-display mt-1 text-slate-800"><?= (int)$dashboardTotals['students'] ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-500">Assigned Courses</p>
          <p class="text-3xl font-display mt-1 text-slate-800"><?= (int)$dashboardTotals['courses'] ?></p>
        </article>
        <article class="rounded-xl border border-cyan-200 bg-cyan-50 p-4">
          <p class="text-xs uppercase tracking-wide text-cyan-700">New Teacher Updates</p>
          <p class="text-3xl font-display mt-1 text-cyan-800"><?= (int)$dashboardTotals['new_updates'] ?></p>
        </article>
      </div>
    </section>

    <section class="mt-5 bg-white rounded-xl border border-slate-200 panel-card p-4 md:p-5">
      <h3 class="font-display text-xl text-slate-800">Class Workspace Status</h3>
      <div class="mt-3 overflow-auto border border-slate-200 rounded-xl">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Class</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Students</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Courses</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Teacher Mark Rows</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Latest Teacher Update</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Homeroom Status</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (!count($dashboardSnapshots)): ?>
              <tr><td colspan="7" class="px-3 py-4 text-slate-500">No assigned homeroom classes found.</td></tr>
            <?php else: ?>
              <?php foreach ($dashboardSnapshots as $snap): ?>
                <?php
                  $statusLabel = 'Not Started';
                  $statusCls = 'bg-slate-100 text-slate-700';
                  if ($snap['status'] === 'submitted') { $statusLabel = 'Submitted'; $statusCls = 'bg-emerald-100 text-emerald-700'; }
                  elseif ($snap['status'] === 'draft') { $statusLabel = 'Draft'; $statusCls = 'bg-amber-100 text-amber-700'; }
                  $updateLabel = $snap['has_new_teacher_updates'] ? 'New Update' : 'Synced';
                  $updateCls = $snap['has_new_teacher_updates'] ? 'bg-cyan-100 text-cyan-700' : 'bg-slate-100 text-slate-700';
                ?>
                <tr>
                  <td class="px-3 py-2">
                    <div class="font-medium text-slate-800"><?= htmlspecialchars($snap['class_name']) ?></div>
                    <div class="text-xs text-slate-500"><?= htmlspecialchars($snap['grade']) ?><?= $snap['section'] !== '' ? ' - ' . htmlspecialchars($snap['section']) : '' ?><?= $snap['academic_year'] !== '' ? ' | ' . htmlspecialchars($snap['academic_year']) : '' ?></div>
                  </td>
                  <td class="px-3 py-2"><?= (int)$snap['students'] ?></td>
                  <td class="px-3 py-2"><?= (int)$snap['courses'] ?></td>
                  <td class="px-3 py-2"><?= (int)$snap['mark_rows'] ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars($snap['latest_teacher_update'] ?: '-') ?></td>
                  <td class="px-3 py-2">
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs <?= $statusCls ?>"><?= htmlspecialchars($statusLabel) ?></span>
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs ml-1 <?= $updateCls ?>"><?= htmlspecialchars($updateLabel) ?></span>
                  </td>
                  <td class="px-3 py-2">
                    <a href="dashboard.php?view=matrix&class_id=<?= (int)$snap['class_id'] ?>&term_id=<?= (int)$selectedTermId ?>" class="inline-flex rounded bg-indigo-600 text-white px-2 py-1 text-xs hover:bg-indigo-700">Open Matrix</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="mt-5 bg-white rounded-xl border border-slate-200 panel-card p-4 md:p-5">
      <h3 class="font-display text-xl text-slate-800">Class Course Map</h3>
      <div class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-4">
        <?php if (!count($classes)): ?>
          <div class="text-sm text-slate-500">No classes assigned.</div>
        <?php else: ?>
          <?php foreach ($classes as $class): ?>
            <?php $cid = (int)$class['id']; $courseItems = $dashboardCourseMap[$cid] ?? []; ?>
            <article class="rounded-xl border border-slate-200 p-4">
              <h4 class="font-semibold text-slate-800"><?= htmlspecialchars($class['name']) ?> <span class="text-xs font-normal text-slate-500">(<?= htmlspecialchars($class['grade']) ?><?= !empty($class['section']) ? ' - ' . htmlspecialchars($class['section']) : '' ?>)</span></h4>
              <div class="mt-2 flex flex-wrap gap-2">
                <?php if (!count($courseItems)): ?>
                  <span class="text-xs text-slate-500">No courses assigned yet.</span>
                <?php else: ?>
                  <?php foreach ($courseItems as $courseItem): ?>
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700">
                      <?= htmlspecialchars($courseItem['course_name']) ?><?= $courseItem['code'] !== '' ? ' (' . htmlspecialchars($courseItem['code']) . ')' : '' ?><?= $courseItem['teacher_name'] !== '' ? ' - ' . htmlspecialchars($courseItem['teacher_name']) : '' ?>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="mt-5 bg-white rounded-xl border border-slate-200 panel-card p-4 md:p-5 <?= $view === 'matrix' ? '' : 'hidden' ?>">
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Class</label>
          <select id="class_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm smooth-input">
            <?php foreach ($classes as $class): ?>
              <option value="<?= (int)$class['id'] ?>" <?= ((int)$class['id'] === $selectedClassId ? 'selected' : '') ?>>
                <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['grade']) ?><?= !empty($class['section']) ? ' - ' . htmlspecialchars($class['section']) : '' ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Term</label>
          <select id="term_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm smooth-input">
            <?php foreach ($terms as $term): ?>
              <option value="<?= (int)$term['id'] ?>" <?= ((int)$term['id'] === $selectedTermId ? 'selected' : '') ?>><?= htmlspecialchars($term['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Search Student</label>
          <input id="student_search" type="text" placeholder="Type name..." class="w-full border border-gray-300 rounded px-3 py-2 text-sm smooth-input">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Context</label>
          <div id="context_badge" class="h-[38px] inline-flex w-full items-center rounded border border-blue-200 bg-blue-50 text-blue-700 text-xs px-3"></div>
        </div>
      </div>
      <div class="mt-3 grid grid-cols-1 xl:grid-cols-3 gap-3">
        <div class="xl:col-span-2">
          <label class="block text-xs font-medium text-gray-700 mb-1">Homeroom Notes (optional)</label>
          <textarea id="matrix_notes" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm smooth-input" placeholder="Notes for admin about this class-term matrix..."></textarea>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Matrix Status</label>
          <div id="matrix_status_badge" class="h-[66px] rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 flex items-center"></div>
        </div>
      </div>

      <div id="status_box" class="mt-3 hidden px-3 py-2 rounded text-sm"></div>
    </section>

    <section class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 <?= $view === 'matrix' ? '' : 'hidden' ?>">
      <article class="bg-white rounded-xl border border-slate-200 panel-card p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Students</p>
        <p id="k_students" class="text-3xl font-display mt-1">0</p>
      </article>
      <article class="bg-white rounded-xl border border-slate-200 panel-card p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Courses</p>
        <p id="k_courses" class="text-3xl font-display mt-1">0</p>
      </article>
      <article class="bg-white rounded-xl border border-slate-200 panel-card p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Class Average</p>
        <p id="k_average" class="text-3xl font-display mt-1">0.00</p>
      </article>
      <article class="bg-white rounded-xl border border-slate-200 panel-card p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Pass Rate</p>
        <p id="k_pass_rate" class="text-3xl font-display mt-1">0%</p>
      </article>
    </section>

    <section id="term_matrix_panel" class="mt-5 bg-white rounded-xl border border-slate-200 panel-card p-4 <?= $view === 'matrix' ? '' : 'hidden' ?>">
      <div class="flex items-center justify-between gap-3 mb-3">
        <h3 class="font-display text-xl">Student Term Matrix</h3>
        <button id="export_csv_btn" class="bg-indigo-600 text-white rounded px-3 py-2 text-xs hover:bg-indigo-700">Export CSV</button>
      </div>
      <div class="table-shell border border-slate-200 rounded-xl">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr id="matrix_head_row">
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Rank</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Student</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Attendance %</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Total Sum</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Term Average</th>
            </tr>
          </thead>
          <tbody id="matrix_body" class="divide-y divide-slate-100">
            <tr><td colspan="5" class="px-3 py-4 text-slate-500">Click "Refresh Matrix" to view student results.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<script>
const csrf = <?= json_encode($csrf) ?>;
const defaultClassId = <?= (int)$selectedClassId ?>;
const defaultTermId = <?= (int)$selectedTermId ?>;
const currentView = <?= json_encode($view) ?>;

const body = document.getElementById("homeroom_body");
const sidebar = document.getElementById("homeroom_sidebar");
const backdrop = document.getElementById("sidebar_backdrop");
const sidebarKey = "homeroom_sidebar_collapsed";

const classEl = document.getElementById("class_id");
const termEl = document.getElementById("term_id");
const statusEl = document.getElementById("status_box");
const contextEl = document.getElementById("context_badge");
const searchEl = document.getElementById("student_search");
const headRowEl = document.getElementById("matrix_head_row");
const bodyEl = document.getElementById("matrix_body");
const loadBtn = document.getElementById("load_btn");
const saveAttendanceBtn = document.getElementById("save_attendance_btn");
const saveDraftBtn = document.getElementById("save_draft_btn");
const submitMatrixBtn = document.getElementById("submit_matrix_btn");
const notesEl = document.getElementById("matrix_notes");
const matrixStatusBadgeEl = document.getElementById("matrix_status_badge");

const kStudentsEl = document.getElementById("k_students");
const kCoursesEl = document.getElementById("k_courses");
const kAverageEl = document.getElementById("k_average");
const kPassRateEl = document.getElementById("k_pass_rate");

let latestCourses = [];
let latestRows = [];
let loadController = null;
let searchTimer = null;
let latestMatrixStatus = { status: "new", notes: "", submitted_at: null, updated_at: null };
let latestMeta = { has_new_teacher_updates: true, latest_marks_updated_at: null };

function selectedLabel(selectEl) {
  if (!selectEl || selectEl.selectedIndex < 0) return "";
  return selectEl.options[selectEl.selectedIndex]?.textContent?.trim() || "";
}

function updateContext() {
  contextEl.textContent = `${selectedLabel(classEl) || "Class"} | ${selectedLabel(termEl) || "Term"}`;
}

function setStatus(message, ok = true) {
  statusEl.textContent = message;
  statusEl.className = ok
    ? "mt-3 px-3 py-2 rounded text-sm bg-emerald-50 border border-emerald-200 text-emerald-700"
    : "mt-3 px-3 py-2 rounded text-sm bg-red-50 border border-red-200 text-red-700";
}

function setLoadingState(loading) {
  if (!loadBtn) return;
  loadBtn.disabled = loading;
  loadBtn.textContent = loading ? "Loading..." : "Refresh Matrix";
}

function sanitizeCsvValue(v) {
  const s = String(v ?? "");
  if (/[",\n]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
  return s;
}

function renderTable(courses, rows) {
  const courseHeaders = courses.map(c => `<th class="px-3 py-2 text-left font-semibold text-slate-700">${c.name}</th>`).join("");
  headRowEl.innerHTML = `
    <th class="px-3 py-2 text-left font-semibold text-slate-700">Rank</th>
    <th class="px-3 py-2 text-left font-semibold text-slate-700">Student</th>
    ${courseHeaders}
    <th class="px-3 py-2 text-left font-semibold text-slate-700">Attendance %</th>
    <th class="px-3 py-2 text-left font-semibold text-slate-700">Total Sum</th>
    <th class="px-3 py-2 text-left font-semibold text-slate-700">Term Average</th>
  `;

  if (!rows.length) {
    bodyEl.innerHTML = `<tr><td colspan="${5 + courses.length}" class="px-3 py-4 text-slate-500">No students found.</td></tr>`;
    return;
  }

  bodyEl.innerHTML = rows.map(row => {
    const courseCells = courses.map(c => {
      const value = row.course_totals?.[String(c.id)];
      return `<td class="px-3 py-2">${value === null || value === undefined ? "-" : Number(value).toFixed(2)}</td>`;
    }).join("");

    return `
      <tr data-student-id="${row.student_id}" data-student-name="${(row.full_name || "").toLowerCase()}">
        <td class="px-3 py-2">${row.rank ?? "-"}</td>
        <td class="px-3 py-2 font-medium text-slate-800">${row.full_name}</td>
        ${courseCells}
        <td class="px-3 py-2">
          <input
            type="number"
            min="0"
            max="100"
            step="0.01"
            class="attendance-input w-28 border border-slate-300 rounded px-2 py-1 text-sm smooth-input"
            data-student-id="${row.student_id}"
            value="${Number(row.attendance_percent || 0).toFixed(2)}"
          />
        </td>
        <td class="px-3 py-2">${Number(row.course_total_sum || 0).toFixed(2)}</td>
        <td class="px-3 py-2 font-semibold">${Number(row.term_average || 0).toFixed(2)}</td>
      </tr>
    `;
  }).join("");
}

function renderSummary(summary = {}) {
  kStudentsEl.textContent = Number(summary.students || 0);
  kCoursesEl.textContent = Number(summary.courses || 0);
  kAverageEl.textContent = Number(summary.class_average || 0).toFixed(2);
  kPassRateEl.textContent = `${Number(summary.pass_rate || 0).toFixed(2)}%`;
}

function renderCleanState(message) {
  const colCount = Math.max(5, 5 + (latestCourses?.length || 0));
  bodyEl.innerHTML = `<tr><td colspan="${colCount}" class="px-3 py-8 text-center text-slate-500">${message}</td></tr>`;
}

function renderMatrixStatus(status = {}) {
  latestMatrixStatus = status || {};
  const s = String(latestMatrixStatus.status || "new");
  const updated = latestMatrixStatus.updated_at ? `Updated: ${latestMatrixStatus.updated_at}` : "Not saved yet";
  const submitted = latestMatrixStatus.submitted_at ? `Submitted: ${latestMatrixStatus.submitted_at}` : "";
  const label = s === "submitted" ? "Submitted" : (s === "draft" ? "Draft Saved" : "New");
  const cls = s === "submitted"
    ? "border-emerald-200 bg-emerald-50 text-emerald-700"
    : (s === "draft" ? "border-amber-200 bg-amber-50 text-amber-700" : "border-slate-200 bg-slate-50 text-slate-700");
  matrixStatusBadgeEl.className = `h-[66px] rounded px-3 py-2 text-xs flex items-center ${cls}`;
  matrixStatusBadgeEl.textContent = submitted ? `${label} | ${submitted}` : `${label} | ${updated}`;
  if (notesEl && typeof latestMatrixStatus.notes === "string") {
    notesEl.value = latestMatrixStatus.notes;
  }
  updateWorkflowActions();
}

function updateWorkflowActions() {
  if (!saveAttendanceBtn || !saveDraftBtn || !submitMatrixBtn) return;
  const status = String(latestMatrixStatus?.status || "new");
  const locked = status === "submitted";
  if (saveAttendanceBtn) {
    saveAttendanceBtn.disabled = locked;
    saveAttendanceBtn.classList.toggle("opacity-60", locked);
    saveAttendanceBtn.title = locked ? "Already finalized. Use History > Re-Submit to edit." : "";
  }
  if (saveDraftBtn) {
    saveDraftBtn.disabled = locked;
    saveDraftBtn.classList.toggle("opacity-60", locked);
    saveDraftBtn.classList.toggle("cursor-not-allowed", locked);
    saveDraftBtn.title = locked ? "Already finalized. Use History > Re-Submit to edit." : "";
  }
  if (submitMatrixBtn) {
    submitMatrixBtn.disabled = locked;
    submitMatrixBtn.classList.toggle("opacity-60", locked);
    submitMatrixBtn.classList.toggle("cursor-not-allowed", locked);
    submitMatrixBtn.title = locked ? "Already finalized. Use History > Re-Submit to edit." : "";
  }
}


function applySearch() {
  const term = (searchEl.value || "").trim().toLowerCase();
  const rows = Array.from(bodyEl.querySelectorAll("tr[data-student-id]"));
  rows.forEach((tr) => {
    const name = tr.getAttribute("data-student-name") || "";
    tr.style.display = (term === "" || name.includes(term)) ? "" : "none";
  });
}

async function loadMatrix() {
  const classId = Number(classEl.value || 0);
  const termId = Number(termEl.value || 0);
  if (!classId || !termId) {
    setStatus("Select class and term first.", false);
    return;
  }

  if (loadController) loadController.abort();
  loadController = new AbortController();
  setLoadingState(true);

  try {
    const params = new URLSearchParams({ action: "term_matrix", class_id: String(classId), term_id: String(termId) });
    const res = await fetch(`../api/homeroom.php?${params.toString()}`, { signal: loadController.signal });
    const data = await res.json();
    if (!data.success) {
      setStatus(data.message || "Failed to load matrix.", false);
      return;
    }

    latestCourses = data.courses || [];
    latestRows = data.rows || [];
    latestMeta = data.meta || { has_new_teacher_updates: true, latest_marks_updated_at: null };
    renderSummary(data.summary || {});
    renderMatrixStatus(data.matrix_status || {});
    const matrixStatus = String((data.matrix_status || {}).status || "new");

    // Workflow lock: once homeroom submits/finalizes, matrix is hidden from dashboard.
    // Teacher or homeroom corrections must start from History via Re-Submit.
    if (matrixStatus === "submitted") {
      latestRows = [];
      renderTable(latestCourses, []);
      renderCleanState("This term result is already submitted. Use History to view or Re-Submit for changes.");
      setStatus("Already submitted. Dashboard matrix is locked for this class-term.");
      return;
    }

    renderTable(latestCourses, latestRows);
    applySearch();
    const hasNewUpdates = !!latestMeta.has_new_teacher_updates;
    if (!hasNewUpdates && matrixStatus !== "new") {
      setStatus(`Loaded ${latestRows.length} students. No new teacher updates since last homeroom save/submit.`);
    } else {
      setStatus(`Loaded ${latestRows.length} students for ${selectedLabel(termEl)}.`);
    }
  } catch (err) {
    if (err.name !== "AbortError") {
      setStatus("Network error while loading matrix.", false);
    }
  } finally {
    setLoadingState(false);
  }
}

function collectAttendanceRows() {
  const inputs = Array.from(document.querySelectorAll(".attendance-input"));
  const rows = [];
  for (const input of inputs) {
    const studentId = Number(input.getAttribute("data-student-id") || 0);
    const value = (input.value || "").trim();
    if (!studentId || value === "") continue;
    const num = Number(value);
    if (Number.isNaN(num) || num < 0 || num > 100) {
      input.focus();
      throw new Error(`Invalid attendance for student ID ${studentId}. Use 0 to 100.`);
    }
    rows.push({ student_id: studentId, attendance_percent: Number(num.toFixed(2)) });
  }
  return rows;
}

async function saveAttendance() {
  const classId = Number(classEl.value || 0);
  const termId = Number(termEl.value || 0);
  if (!classId || !termId) {
    setStatus("Select class and term first.", false);
    return;
  }
  let rows = [];
  try {
    rows = collectAttendanceRows();
  } catch (e) {
    setStatus(e.message || "Invalid attendance values.", false);
    return;
  }
  if (!rows.length) {
    setStatus("No attendance values to save.", false);
    return;
  }

  const oldText = saveAttendanceBtn.textContent;
  saveAttendanceBtn.disabled = true;
  saveAttendanceBtn.textContent = "Saving...";
  try {
    const form = new FormData();
    form.append("action", "save_attendance");
    form.append("csrf", csrf);
    form.append("class_id", String(classId));
    form.append("term_id", String(termId));
    form.append("rows", JSON.stringify(rows));
    const res = await fetch("../api/homeroom.php", { method: "POST", body: form });
    const data = await res.json();
    if (!data.success) {
      setStatus(data.message || "Failed to save attendance.", false);
      return;
    }
    setStatus(`${data.message || "Attendance saved."} (${Number(data.saved || 0)} students)`);
    await loadMatrix();
  } catch (err) {
    setStatus("Network error while saving attendance.", false);
  } finally {
    saveAttendanceBtn.disabled = false;
    saveAttendanceBtn.textContent = oldText;
  }
}

async function saveOrSubmitMatrix(action) {
  const classId = Number(classEl.value || 0);
  const termId = Number(termEl.value || 0);
  if (!classId || !termId) {
    setStatus("Select class and term first.", false);
    return;
  }
  const form = new FormData();
  form.append("action", action);
  form.append("csrf", csrf);
  form.append("class_id", String(classId));
  form.append("term_id", String(termId));
  form.append("notes", notesEl?.value || "");

  const isSubmit = action === "submit_matrix";
  if (String(latestMatrixStatus?.status || "new") === "submitted") {
    setStatus("This class-term is finalized. Open History and click Re-Submit to edit again.", false);
    return;
  }
  const btn = isSubmit ? submitMatrixBtn : saveDraftBtn;
  const oldText = btn.textContent;
  btn.disabled = true;
  btn.textContent = isSubmit ? "Submitting..." : "Saving...";
  try {
    const res = await fetch("../api/homeroom.php", { method: "POST", body: form });
    const data = await res.json();
    if (!data.success) {
      setStatus(data.message || "Failed to update matrix status.", false);
      return;
    }
    setStatus(data.message || "Matrix status updated.");
    await loadMatrix();
  } catch (err) {
    setStatus("Network error while updating matrix status.", false);
  } finally {
    btn.disabled = false;
    btn.textContent = oldText;
  }
}

function exportCsv() {
  if (!latestRows.length) {
    setStatus("Load matrix before export.", false);
    return;
  }
  const headers = ["rank", "student_name", ...latestCourses.map(c => c.name), "attendance_percent", "total_sum", "term_average"];
  const lines = [headers.map(sanitizeCsvValue).join(",")];
  for (const row of latestRows) {
    const courseValues = latestCourses.map(c => {
      const v = row.course_totals?.[String(c.id)];
      return v === null || v === undefined ? "" : Number(v).toFixed(2);
    });
    const data = [
      row.rank ?? "",
      row.full_name ?? "",
      ...courseValues,
      Number(row.attendance_percent || 0).toFixed(2),
      Number(row.course_total_sum || 0).toFixed(2),
      Number(row.term_average || 0).toFixed(2)
    ];
    lines.push(data.map(sanitizeCsvValue).join(","));
  }

  const classSlug = (selectedLabel(classEl) || "class").replace(/[^a-zA-Z0-9_-]+/g, "_");
  const termSlug = (selectedLabel(termEl) || "term").replace(/[^a-zA-Z0-9_-]+/g, "_");
  const filename = `homeroom_term_matrix_${classSlug}_${termSlug}.csv`;
  const blob = new Blob(["\uFEFF" + lines.join("\r\n")], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  URL.revokeObjectURL(link.href);
}

function applyDesktopSidebarState(collapsed) {
  body.classList.toggle("sidebar-collapsed", collapsed);
  document.getElementById("sidebar_toggle").textContent = collapsed ? ">>" : "<<";
}

function initSidebar() {
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

document.getElementById("sidebar_toggle").addEventListener("click", () => {
  const collapsed = !body.classList.contains("sidebar-collapsed");
  applyDesktopSidebarState(collapsed);
  localStorage.setItem(sidebarKey, collapsed ? "1" : "0");
});
document.getElementById("sidebar_open_mobile").addEventListener("click", openMobileSidebar);
document.getElementById("sidebar_close_mobile").addEventListener("click", closeMobileSidebar);
backdrop.addEventListener("click", closeMobileSidebar);
window.addEventListener("resize", () => {
  if (window.innerWidth >= 768) closeMobileSidebar();
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeMobileSidebar();
});

if (loadBtn) loadBtn.addEventListener("click", loadMatrix);
if (saveAttendanceBtn) saveAttendanceBtn.addEventListener("click", saveAttendance);
if (saveDraftBtn) saveDraftBtn.addEventListener("click", () => saveOrSubmitMatrix("save_matrix_draft"));
if (submitMatrixBtn) submitMatrixBtn.addEventListener("click", () => saveOrSubmitMatrix("submit_matrix"));
const exportBtn = document.getElementById("export_csv_btn");
if (exportBtn) exportBtn.addEventListener("click", exportCsv);

searchEl.addEventListener("input", () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(applySearch, 100);
});
classEl.addEventListener("change", updateContext);
termEl.addEventListener("change", updateContext);

if (defaultClassId) classEl.value = String(defaultClassId);
if (defaultTermId) termEl.value = String(defaultTermId);
updateContext();
initSidebar();
if (currentView === "matrix") {
  loadMatrix();
}
</script>
</body>
</html>
