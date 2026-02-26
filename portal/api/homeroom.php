<?php
session_start();
require_once __DIR__ . '/../../includes/bootstrap_portal.php';

header('Content-Type: application/json');

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function ok(array $payload = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => true], $payload));
    exit;
}

function tableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

$ctx = getAuthContext();
if (!$ctx) {
    fail('Unauthorized', 401);
}
if ($ctx['actor'] !== 'portal' || ($ctx['portal_role'] ?? '') !== 'homeroom') {
    fail('Access denied', 403);
}

if (!tableExists($pdo, 'student_term_attendance_mvp') || !tableExists($pdo, 'student_course_marks_mvp')) {
    fail('Phase 2 tables missing. Run marklist_mvp_phase2.sql', 500);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
switch ($action) {
    case 'summary':
    case 'term_matrix':
        termMatrix($pdo, $ctx);
        break;
    case 'matrix_history':
        matrixHistory($pdo, $ctx);
        break;
    case 'matrix_detail':
        matrixDetail($pdo, $ctx);
        break;
    case 'finalize_history':
        finalizeHistory($pdo, $ctx);
        break;
    case 'reopen_history':
        reopenHistory($pdo, $ctx);
        break;
    case 'save_matrix_draft':
        saveOrSubmitMatrix($pdo, $ctx, 'draft');
        break;
    case 'submit_matrix':
        saveOrSubmitMatrix($pdo, $ctx, 'submitted');
        break;
    case 'save_attendance':
        saveAttendance($pdo, $ctx);
        break;
    default:
        fail('Invalid action');
}

function ensureClassAccess(PDO $pdo, $ctx, $classId) {
    if (($ctx['portal_role'] ?? '') !== 'homeroom') {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT 1
        FROM class_teachers
        WHERE class_id = ? AND teacher_id = ? AND role = 'homeroom' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([(int)$classId, (int)$ctx['teacher_id']]);
    return (bool)$stmt->fetchColumn();
}

function getStudentsByClass(PDO $pdo, int $classId): array {
    $stmt = $pdo->prepare("
        SELECT s.id AS student_id, s.full_name
        FROM class_enrollments ce
        INNER JOIN students s ON s.id = ce.student_id
        WHERE ce.class_id = ? AND ce.status = 'active'
        ORDER BY s.full_name
    ");
    $stmt->execute([$classId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCoursesByClass(PDO $pdo, int $classId): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT co.id, co.name, co.code
        FROM course_teachers ct
        INNER JOIN courses co ON co.id = ct.course_id
        WHERE ct.class_id = ? AND ct.is_active = 1
        ORDER BY co.name
    ");
    $stmt->execute([$classId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function termMatrix(PDO $pdo, $ctx) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $termId = (int)($_GET['term_id'] ?? 0);
    if (!$classId || !$termId) {
        fail('class_id and term_id are required');
    }
    if (!ensureClassAccess($pdo, $ctx, $classId)) {
        fail('Access denied for selected class', 403);
    }
    ensureMatrixSubmissionTable($pdo);

    $students = getStudentsByClass($pdo, $classId);
    $courses = getCoursesByClass($pdo, $classId);

    $attendanceStmt = $pdo->prepare("
        SELECT student_id, attendance_percent
        FROM student_term_attendance_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $attendanceStmt->execute([$classId, $termId]);
    $attendanceMap = [];
    foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attendanceMap[(int)$row['student_id']] = round((float)$row['attendance_percent'], 2);
    }

    $marksStmt = $pdo->prepare("
        SELECT student_id, course_id, total_mark
        FROM student_course_marks_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $marksStmt->execute([$classId, $termId]);
    $markMap = [];
    foreach ($marksStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)$row['student_id'];
        $cid = (int)$row['course_id'];
        if (!isset($markMap[$sid])) {
            $markMap[$sid] = [];
        }
        $markMap[$sid][$cid] = round((float)$row['total_mark'], 2);
    }

    $rows = [];
    $courseIds = array_map(fn($c) => (int)$c['id'], $courses);
    foreach ($students as $student) {
        $sid = (int)$student['student_id'];
        $courseTotals = [];
        $sum = 0.0;
        $count = 0;
        foreach ($courseIds as $cid) {
            $value = $markMap[$sid][$cid] ?? null;
            $courseTotals[(string)$cid] = $value;
            if ($value !== null) {
                $sum += (float)$value;
                $count++;
            }
        }
        $avg = $count > 0 ? round($sum / $count, 2) : 0.00;
        $rows[] = [
            'student_id' => $sid,
            'full_name' => $student['full_name'],
            'attendance_percent' => (float)($attendanceMap[$sid] ?? 0.00),
            'subject_count' => $count,
            'course_total_sum' => round($sum, 2),
            'term_average' => $avg,
            'course_totals' => $courseTotals
        ];
    }

    usort($rows, function ($a, $b) {
        if ($a['term_average'] === $b['term_average']) {
            return $b['course_total_sum'] <=> $a['course_total_sum'];
        }
        return $b['term_average'] <=> $a['term_average'];
    });

    $rank = 0;
    $lastAvg = null;
    $passCount = 0;
    foreach ($rows as $i => &$row) {
        if ($lastAvg === null || abs($row['term_average'] - $lastAvg) > 0.0001) {
            $rank = $i + 1;
            $lastAvg = $row['term_average'];
        }
        $row['rank'] = $rank;
        if ((float)$row['term_average'] >= 50.0) {
            $passCount++;
        }
    }
    unset($row);

    $studentCount = count($rows);
    $classAverage = 0.0;
    if ($studentCount > 0) {
        $classAverage = round(array_sum(array_map(fn($r) => (float)$r['term_average'], $rows)) / $studentCount, 2);
    }
    $passRate = $studentCount > 0 ? round(($passCount / $studentCount) * 100, 2) : 0.0;

    $statusStmt = $pdo->prepare("
        SELECT status, notes, submitted_at, updated_at
        FROM homeroom_term_matrix_submissions_mvp
        WHERE class_id = ? AND term_id = ?
        LIMIT 1
    ");
    $statusStmt->execute([$classId, $termId]);
    $matrixStatus = $statusStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'status' => 'new',
        'notes' => '',
        'submitted_at' => null,
        'updated_at' => null
    ];

    $latestMarksStmt = $pdo->prepare("
        SELECT MAX(updated_at)
        FROM student_course_marks_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $latestMarksStmt->execute([$classId, $termId]);
    $latestMarksUpdatedAt = $latestMarksStmt->fetchColumn();
    $latestMarksUpdatedAt = $latestMarksUpdatedAt ? (string)$latestMarksUpdatedAt : null;

    $status = (string)($matrixStatus['status'] ?? 'new');
    $matrixUpdatedAt = isset($matrixStatus['updated_at']) && $matrixStatus['updated_at'] !== null
        ? (string)$matrixStatus['updated_at']
        : null;
    $hasNewTeacherUpdates = true;
    if ($status === 'draft' || $status === 'submitted') {
        if ($latestMarksUpdatedAt === null) {
            $hasNewTeacherUpdates = false;
        } elseif ($matrixUpdatedAt === null) {
            $hasNewTeacherUpdates = true;
        } else {
            $hasNewTeacherUpdates = strtotime($latestMarksUpdatedAt) > strtotime($matrixUpdatedAt);
        }
    }

    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'rows' => $rows,
        'summary' => [
            'students' => $studentCount,
            'courses' => count($courses),
            'class_average' => $classAverage,
            'pass_rate' => $passRate
        ],
        'matrix_status' => $matrixStatus,
        'meta' => [
            'has_new_teacher_updates' => $hasNewTeacherUpdates,
            'latest_marks_updated_at' => $latestMarksUpdatedAt
        ]
    ]);
}

function matrixHistory(PDO $pdo, $ctx) {
    $classId = (int)($_GET['class_id'] ?? 0);
    if ($classId > 0 && !ensureClassAccess($pdo, $ctx, $classId)) {
        fail('Access denied for selected class', 403);
    }
    if (!tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        echo json_encode(['success' => true, 'rows' => []]);
        return;
    }

    $where = [];
    $params = [(int)$ctx['teacher_id']];
    if ($classId > 0) {
        $where[] = "h.class_id = ?";
        $params[] = $classId;
    }
    $whereSql = count($where) ? (" AND " . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("
        SELECT
            h.class_id,
            h.term_id,
            h.status,
            h.notes,
            h.submitted_at,
            h.updated_at,
            c.name AS class_name,
            c.grade,
            c.section,
            c.academic_year,
            t.name AS term_name,
            t.term_order
        FROM homeroom_term_matrix_submissions_mvp h
        INNER JOIN classes c ON c.id = h.class_id
        INNER JOIN academic_terms_mvp t ON t.id = h.term_id
        INNER JOIN class_teachers ct
            ON ct.class_id = h.class_id
           AND ct.teacher_id = ?
           AND ct.role = 'homeroom'
           AND ct.is_active = 1
        WHERE 1=1 {$whereSql}
        ORDER BY c.grade, c.section, c.name, t.term_order ASC, h.updated_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'rows' => $rows]);
}

function matrixDetail(PDO $pdo, $ctx) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $termId = (int)($_GET['term_id'] ?? 0);
    if (!$classId || !$termId) fail('class_id and term_id are required');
    if (!ensureClassAccess($pdo, $ctx, $classId)) fail('Access denied for selected class', 403);

    // Reuse same view logic as term matrix payload.
    $students = getStudentsByClass($pdo, $classId);
    $courses = getCoursesByClass($pdo, $classId);

    $attendanceStmt = $pdo->prepare("
        SELECT student_id, attendance_percent
        FROM student_term_attendance_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $attendanceStmt->execute([$classId, $termId]);
    $attendanceMap = [];
    foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attendanceMap[(int)$row['student_id']] = round((float)$row['attendance_percent'], 2);
    }

    $marksStmt = $pdo->prepare("
        SELECT student_id, course_id, total_mark
        FROM student_course_marks_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $marksStmt->execute([$classId, $termId]);
    $markMap = [];
    foreach ($marksStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)$row['student_id'];
        $cid = (int)$row['course_id'];
        if (!isset($markMap[$sid])) $markMap[$sid] = [];
        $markMap[$sid][$cid] = round((float)$row['total_mark'], 2);
    }

    $rows = [];
    $courseIds = array_map(fn($c) => (int)$c['id'], $courses);
    foreach ($students as $student) {
        $sid = (int)$student['student_id'];
        $courseTotals = [];
        $sum = 0.0;
        $count = 0;
        foreach ($courseIds as $cid) {
            $value = $markMap[$sid][$cid] ?? null;
            $courseTotals[(string)$cid] = $value;
            if ($value !== null) { $sum += (float)$value; $count++; }
        }
        $rows[] = [
            'student_id' => $sid,
            'full_name' => $student['full_name'],
            'attendance_percent' => (float)($attendanceMap[$sid] ?? 0.00),
            'subject_count' => $count,
            'course_total_sum' => round($sum, 2),
            'term_average' => $count > 0 ? round($sum / $count, 2) : 0.00,
            'course_totals' => $courseTotals
        ];
    }

    echo json_encode(['success' => true, 'courses' => $courses, 'rows' => $rows]);
}

function finalizeHistory(PDO $pdo, $ctx) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);
    $classId = (int)($_POST['class_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    if (!$classId || !$termId) fail('class_id and term_id are required');
    if (!ensureClassAccess($pdo, $ctx, $classId)) fail('Access denied for selected class', 403);
    $adminFinalized = isAdminFinalizedTerm($pdo, $classId, $termId);
    if ($adminFinalized) {
        fail('Admin has already finalized this term summary. Reopen is blocked until admin unlocks.', 409);
    }
    saveOrSubmitMatrix($pdo, $ctx, 'submitted');
}

function reopenHistory(PDO $pdo, $ctx) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);
    $classId = (int)($_POST['class_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    if (!$classId || !$termId) fail('class_id and term_id are required');
    if (!ensureClassAccess($pdo, $ctx, $classId)) fail('Access denied for selected class', 403);
    $adminFinalized = isAdminFinalizedTerm($pdo, $classId, $termId);
    if ($adminFinalized) {
        fail('Admin has already finalized this term summary. Reopen is blocked until admin unlocks.', 409);
    }
    saveOrSubmitMatrix($pdo, $ctx, 'draft');
}

function saveAttendance(PDO $pdo, $ctx) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Method not allowed', 405);
    }
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) {
        fail('Invalid CSRF token', 403);
    }

    $classId = (int)($_POST['class_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    $rowsJson = (string)($_POST['rows'] ?? '[]');
    if (!$classId || !$termId) {
        fail('class_id and term_id are required');
    }
    if (!ensureClassAccess($pdo, $ctx, $classId)) {
        fail('Access denied for selected class', 403);
    }
    $adminFinalized = isAdminFinalizedTerm($pdo, $classId, $termId);
    if ($adminFinalized) {
        fail('Attendance is locked because admin already finalized this term summary.', 409);
    }
    $matrixState = getMatrixStatusForContext($pdo, $classId, $termId);
    if (($matrixState['status'] ?? 'none') === 'submitted') {
        fail('Attendance is locked after homeroom submission. Reopen to draft first.', 409);
    }

    $rows = json_decode($rowsJson, true);
    if (!is_array($rows)) {
        fail('Invalid rows payload');
    }

    $studentIdsStmt = $pdo->prepare("
        SELECT ce.student_id
        FROM class_enrollments ce
        WHERE ce.class_id = ? AND ce.status = 'active'
    ");
    $studentIdsStmt->execute([$classId]);
    $allowedIds = array_map('intval', array_column($studentIdsStmt->fetchAll(PDO::FETCH_ASSOC), 'student_id'));
    $allowedMap = array_fill_keys($allowedIds, true);

    $preparedRows = [];
    foreach ($rows as $row) {
        $sid = (int)($row['student_id'] ?? 0);
        $attRaw = $row['attendance_percent'] ?? null;
        if (!$sid || !isset($allowedMap[$sid])) {
            continue;
        }
        if ($attRaw === null || $attRaw === '') {
            continue;
        }
        if (!is_numeric($attRaw)) {
            fail('Attendance must be numeric');
        }
        $att = round((float)$attRaw, 2);
        if ($att < 0 || $att > 100) {
            fail('Attendance must be between 0 and 100');
        }
        $preparedRows[] = ['student_id' => $sid, 'attendance_percent' => $att];
    }

    if (!count($preparedRows)) {
        fail('No attendance rows to save');
    }

    $pdo->beginTransaction();
    try {
        $upsert = $pdo->prepare("
            INSERT INTO student_term_attendance_mvp (
                class_id, term_id, student_id, attendance_percent, entered_by_admin_id, entered_by_portal_user_id
            ) VALUES (?, ?, ?, ?, NULL, ?)
            ON DUPLICATE KEY UPDATE
                attendance_percent = VALUES(attendance_percent),
                entered_by_admin_id = NULL,
                entered_by_portal_user_id = VALUES(entered_by_portal_user_id)
        ");
        foreach ($preparedRows as $r) {
            $upsert->execute([
                $classId,
                $termId,
                (int)$r['student_id'],
                (float)$r['attendance_percent'],
                (int)$ctx['portal_user_id']
            ]);
        }
        $pdo->commit();
        ok([
            'message' => 'Attendance marks saved successfully.',
            'saved' => count($preparedRows)
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function ensureMatrixSubmissionTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS homeroom_term_matrix_submissions_mvp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id BIGINT UNSIGNED NOT NULL,
            term_id BIGINT UNSIGNED NOT NULL,
            teacher_id BIGINT UNSIGNED NOT NULL,
            portal_user_id BIGINT UNSIGNED NOT NULL,
            status ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft',
            notes TEXT NULL,
            submitted_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_homeroom_matrix_context (class_id, term_id),
            KEY idx_homeroom_matrix_status (status),
            KEY idx_homeroom_matrix_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensureTermSummaryTable(PDO $pdo): void {
    if (!tableExists($pdo, 'student_term_result_summary_mvp')) {
        fail('Term summary table missing. Run marklist_mvp_phase4_summary.sql', 500);
    }
}

function syncFinalizedTermSummaryFromHomeroom(PDO $pdo, int $classId, int $termId): int {
    ensureTermSummaryTable($pdo);

    $studentsStmt = $pdo->prepare("
        SELECT s.id AS student_id
        FROM class_enrollments ce
        INNER JOIN students s ON s.id = ce.student_id
        WHERE ce.class_id = ? AND ce.status = 'active'
        ORDER BY s.full_name
    ");
    $studentsStmt->execute([$classId]);
    $studentIds = array_map('intval', array_column($studentsStmt->fetchAll(PDO::FETCH_ASSOC), 'student_id'));

    $marksStmt = $pdo->prepare("
        SELECT student_id, COUNT(*) AS subject_count, COALESCE(SUM(total_mark),0) AS total_score, COALESCE(AVG(total_mark),0) AS average_score
        FROM student_course_marks_mvp
        WHERE class_id = ? AND term_id = ?
        GROUP BY student_id
    ");
    $marksStmt->execute([$classId, $termId]);
    $marksMap = [];
    foreach ($marksStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $marksMap[(int)$m['student_id']] = $m;
    }

    $attStmt = $pdo->prepare("
        SELECT student_id, attendance_percent
        FROM student_term_attendance_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $attStmt->execute([$classId, $termId]);
    $attMap = [];
    foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $attMap[(int)$a['student_id']] = (float)$a['attendance_percent'];
    }

    $rows = [];
    foreach ($studentIds as $sid) {
        $subjectCount = (int)($marksMap[$sid]['subject_count'] ?? 0);
        $total = round((float)($marksMap[$sid]['total_score'] ?? 0), 2);
        $avg = round((float)($marksMap[$sid]['average_score'] ?? 0), 2);
        $att = round((float)($attMap[$sid] ?? 0), 2);
        $decision = 'pending';
        if ($subjectCount > 0) {
            $decision = ($avg >= 50.0 && $att >= 75.0) ? 'pass' : 'fail';
        }
        $rows[] = [
            'student_id' => $sid,
            'subject_count' => $subjectCount,
            'total_score' => $total,
            'average_score' => $avg,
            'attendance_percent' => $att,
            'decision' => $decision
        ];
    }

    usort($rows, function($a, $b) {
        if ($a['total_score'] == $b['total_score']) {
            return $b['average_score'] <=> $a['average_score'];
        }
        return $b['total_score'] <=> $a['total_score'];
    });

    $rank = 0;
    $lastTotal = null;
    foreach ($rows as $i => &$r) {
        if ($lastTotal === null || abs($r['total_score'] - $lastTotal) > 0.0001) {
            $rank = $i + 1;
            $lastTotal = $r['total_score'];
        }
        $r['rank_in_class'] = $rank;
    }
    unset($r);

    $upsert = $pdo->prepare("
        INSERT INTO student_term_result_summary_mvp (
            class_id, term_id, student_id, subject_count, total_score, average_score, attendance_percent, rank_in_class, decision, is_finalized, finalized_by_admin_id, finalized_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, NOW())
        ON DUPLICATE KEY UPDATE
            subject_count = VALUES(subject_count),
            total_score = VALUES(total_score),
            average_score = VALUES(average_score),
            attendance_percent = VALUES(attendance_percent),
            rank_in_class = VALUES(rank_in_class),
            decision = VALUES(decision),
            is_finalized = 1,
            finalized_by_admin_id = NULL,
            finalized_at = NOW()
    ");

    foreach ($rows as $r) {
        $upsert->execute([
            $classId,
            $termId,
            (int)$r['student_id'],
            (int)$r['subject_count'],
            (float)$r['total_score'],
            (float)$r['average_score'],
            (float)$r['attendance_percent'],
            (int)$r['rank_in_class'],
            $r['decision']
        ]);
    }
    return count($rows);
}

function saveOrSubmitMatrix(PDO $pdo, $ctx, string $status) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Method not allowed', 405);
    }
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) {
        fail('Invalid CSRF token', 403);
    }

    $classId = (int)($_POST['class_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));

    if (!$classId || !$termId) {
        fail('class_id and term_id are required');
    }
    if (!ensureClassAccess($pdo, $ctx, $classId)) {
        fail('Access denied for selected class', 403);
    }
    if ($status !== 'draft' && $status !== 'submitted') {
        fail('Invalid status');
    }

    ensureMatrixSubmissionTable($pdo);
    if (isAdminFinalizedTerm($pdo, $classId, $termId)) {
        fail('Term summary is admin-finalized and locked. Ask admin to unlock before changing matrix.', 409);
    }

    if ($status === 'submitted') {
        assertTeacherDataReadyForSubmission($pdo, $classId, $termId);
    }

    $submittedAt = $status === 'submitted' ? date('Y-m-d H:i:s') : null;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO homeroom_term_matrix_submissions_mvp (
                class_id, term_id, teacher_id, portal_user_id, status, notes, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                teacher_id = VALUES(teacher_id),
                portal_user_id = VALUES(portal_user_id),
                status = VALUES(status),
                notes = VALUES(notes),
                submitted_at = VALUES(submitted_at)
        ");
        $stmt->execute([
            $classId,
            $termId,
            (int)$ctx['teacher_id'],
            (int)$ctx['portal_user_id'],
            $status,
            $notes === '' ? null : $notes,
            $submittedAt
        ]);

        $syncedRows = 0;
        if ($status === 'submitted') {
            $syncedRows = syncFinalizedTermSummaryFromHomeroom($pdo, $classId, $termId);
        }
        $pdo->commit();

        $msg = $status === 'submitted'
            ? "Matrix submitted and finalized for admin review ({$syncedRows} students)."
            : 'Matrix draft saved.';
        ok([
            'message' => $msg,
            'status' => $status
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getMatrixStatusForContext(PDO $pdo, int $classId, int $termId): array {
    if (!tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        return ['status' => 'none', 'updated_at' => null, 'submitted_at' => null];
    }
    $stmt = $pdo->prepare("
        SELECT status, updated_at, submitted_at
        FROM homeroom_term_matrix_submissions_mvp
        WHERE class_id = ? AND term_id = ?
        LIMIT 1
    ");
    $stmt->execute([$classId, $termId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['status' => 'none', 'updated_at' => null, 'submitted_at' => null];
    }
    return [
        'status' => (string)$row['status'],
        'updated_at' => $row['updated_at'] ? (string)$row['updated_at'] : null,
        'submitted_at' => $row['submitted_at'] ? (string)$row['submitted_at'] : null
    ];
}

function isAdminFinalizedTerm(PDO $pdo, int $classId, int $termId): bool {
    if (!tableExists($pdo, 'student_term_result_summary_mvp')) {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT 1
        FROM student_term_result_summary_mvp
        WHERE class_id = ? AND term_id = ? AND is_finalized = 1 AND finalized_by_admin_id IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$classId, $termId]);
    return (bool)$stmt->fetchColumn();
}

function assertTeacherDataReadyForSubmission(PDO $pdo, int $classId, int $termId): void {
    $expectedStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT course_id)
        FROM course_teachers
        WHERE class_id = ? AND is_active = 1
    ");
    $expectedStmt->execute([$classId]);
    $expectedCourses = (int)$expectedStmt->fetchColumn();
    if ($expectedCourses <= 0) {
        fail('No active course assignments found for this class. Cannot submit matrix.', 422);
    }

    $submittedStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT course_id)
        FROM student_course_marks_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $submittedStmt->execute([$classId, $termId]);
    $submittedCourses = (int)$submittedStmt->fetchColumn();
    if ($submittedCourses < $expectedCourses) {
        fail('Teacher marklists are incomplete. Submit all assigned course marklists before homeroom submission.', 422);
    }

    $studentsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM class_enrollments
        WHERE class_id = ? AND status = 'active'
    ");
    $studentsStmt->execute([$classId]);
    $studentCount = (int)$studentsStmt->fetchColumn();
    if ($studentCount <= 0) {
        fail('No active students found for this class', 422);
    }
}
