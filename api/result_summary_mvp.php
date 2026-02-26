<?php
session_start();
require '../config.php';
require '../includes/security_helpers.php';

requireAdminLogin();
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

if (!tableExists($pdo, 'student_term_result_summary_mvp')) {
    fail('Phase 4 table missing. Run marklist_mvp_phase4_summary.sql', 500);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
switch ($action) {
    case 'bootstrap':
        bootstrap($pdo);
        break;
    case 'recalculate':
        recalculate($pdo);
        break;
    case 'get_summary':
        getSummary($pdo);
        break;
    case 'get_course_summary':
        getCourseSummary($pdo);
        break;
    case 'finalize':
        finalizeSummary($pdo);
        break;
    case 'apply_status':
        applyStatusToStudents($pdo);
        break;
    case 'recalculate_yearly':
        recalculateYearly($pdo);
        break;
    case 'get_yearly_summary':
        getYearlySummary($pdo);
        break;
    case 'finalize_yearly':
        finalizeYearlySummary($pdo);
        break;
    case 'apply_yearly_status':
        applyYearlyStatusToStudents($pdo);
        break;
    case 'preview_yearly_promotion':
        previewYearlyPromotion($pdo);
        break;
    case 'promote_yearly_passed':
        promoteYearlyPassedStudents($pdo);
        break;
    case 'get_homeroom_submissions':
        getHomeroomSubmissions($pdo);
        break;
    case 'mark_homeroom_reviewed':
        markHomeroomReviewed($pdo);
        break;
    case 'mark_homeroom_reviewed_bulk':
        markHomeroomReviewedBulk($pdo);
        break;
    case 'get_homeroom_status':
        getHomeroomStatus($pdo);
        break;
    case 'get_readiness':
        getReadiness($pdo);
        break;
    case 'set_yearly_decision_bulk':
        setYearlyDecisionBulk($pdo);
        break;
    case 'promote_yearly_selected':
        promoteYearlySelectedStudents($pdo);
        break;
    default:
        fail('Invalid action');
}

function bootstrap(PDO $pdo) {
    $classes = $pdo->query("
        SELECT id, name, grade, section, academic_year
        FROM classes
        ORDER BY grade, section, name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $terms = $pdo->query("
        SELECT id, name, term_order
        FROM academic_terms_mvp
        ORDER BY term_order
    ")->fetchAll(PDO::FETCH_ASSOC);
    $years = [];
    foreach ($classes as $c) {
        $y = (string)($c['academic_year'] ?? '');
        if ($y !== '') $years[$y] = true;
    }
    $yearList = array_keys($years);
    sort($yearList);

    echo json_encode(['success' => true, 'classes' => $classes, 'terms' => $terms, 'years' => $yearList]);
}

function recalculate(PDO $pdo) {
    $classId = (int)($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? $_GET['term_id'] ?? 0);
    if (!$classId || !$termId) {
        fail('class_id and term_id are required');
    }
    $force = (int)($_POST['force_recalculate'] ?? $_GET['force_recalculate'] ?? 0) === 1;
    if (!$force && hasAdminFinalizedTermSummary($pdo, $classId, $termId)) {
        fail('Summary is admin-finalized. Use force_recalculate=1 only when intentionally reopening workflow.', 409);
    }

    $studentsStmt = $pdo->prepare("
        SELECT s.id AS student_id, s.full_name
        FROM class_enrollments ce
        INNER JOIN students s ON s.id = ce.student_id
        WHERE ce.class_id = ? AND ce.status = 'active'
        ORDER BY s.full_name
    ");
    $studentsStmt->execute([$classId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $marksStmt = $pdo->prepare("
        SELECT student_id, COUNT(*) AS subject_count, COALESCE(SUM(total_mark),0) AS total_score, COALESCE(AVG(total_mark),0) AS average_score
        FROM student_course_marks_mvp
        WHERE class_id = ? AND term_id = ?
        GROUP BY student_id
    ");
    $marksStmt->execute([$classId, $termId]);
    $marksRows = $marksStmt->fetchAll(PDO::FETCH_ASSOC);
    $marksMap = [];
    foreach ($marksRows as $m) {
        $marksMap[(int)$m['student_id']] = $m;
    }

    $attStmt = $pdo->prepare("
        SELECT student_id, attendance_percent
        FROM student_term_attendance_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $attStmt->execute([$classId, $termId]);
    $attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);
    $attMap = [];
    foreach ($attRows as $a) {
        $attMap[(int)$a['student_id']] = (float)$a['attendance_percent'];
    }

    $rows = [];
    foreach ($students as $s) {
        $sid = (int)$s['student_id'];
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
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL)
        ON DUPLICATE KEY UPDATE
            subject_count = VALUES(subject_count),
            total_score = VALUES(total_score),
            average_score = VALUES(average_score),
            attendance_percent = VALUES(attendance_percent),
            rank_in_class = VALUES(rank_in_class),
            decision = VALUES(decision),
            is_finalized = 0,
            finalized_by_admin_id = NULL,
            finalized_at = NULL
    ");

    $pdo->beginTransaction();
    try {
        foreach ($rows as $r) {
            $upsert->execute([
                $classId, $termId, (int)$r['student_id'], (int)$r['subject_count'],
                (float)$r['total_score'], (float)$r['average_score'],
                (float)$r['attendance_percent'], (int)$r['rank_in_class'], $r['decision']
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    ok(['message' => 'Summary recalculated', 'rows' => count($rows)]);
}

function getSummary(PDO $pdo) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $termId = (int)($_GET['term_id'] ?? 0);
    if (!$classId || !$termId) {
        fail('class_id and term_id are required');
    }

    $stmt = $pdo->prepare("
        SELECT srs.*, s.full_name
        FROM student_term_result_summary_mvp srs
        INNER JOIN students s ON s.id = srs.student_id
        WHERE srs.class_id = ? AND srs.term_id = ?
        ORDER BY srs.rank_in_class ASC, s.full_name ASC
    ");
    $stmt->execute([$classId, $termId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'rows' => $rows]);
}

function finalizeSummary(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);

    $classId = (int)($_POST['class_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    if (!$classId || !$termId) fail('class_id and term_id are required');
    assertTermReadyForAdminFinalize($pdo, $classId, $termId);

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $stmt = $pdo->prepare("
        UPDATE student_term_result_summary_mvp
        SET is_finalized = 1, finalized_by_admin_id = ?, finalized_at = NOW()
        WHERE class_id = ? AND term_id = ?
    ");
    $stmt->execute([$adminId, $classId, $termId]);
    ok(['message' => 'Summary finalized', 'affected' => $stmt->rowCount()]);
}

function applyStatusToStudents(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);

    $classId = (int)($_POST['class_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    if (!$classId || !$termId) fail('class_id and term_id are required');

    $stmt = $pdo->prepare("
        UPDATE students s
        INNER JOIN student_term_result_summary_mvp srs
            ON srs.student_id = s.id
        SET s.current_result_status = CASE
            WHEN srs.decision = 'pass' THEN 'pass'
            WHEN srs.decision = 'fail' THEN 'fail'
            ELSE s.current_result_status
        END
        WHERE srs.class_id = ? AND srs.term_id = ? AND srs.is_finalized = 1
    ");
    $stmt->execute([$classId, $termId]);
    ok(['message' => 'Student statuses updated from finalized summary', 'affected' => $stmt->rowCount()]);
}

function getCourseSummary(PDO $pdo) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $termId = (int)($_GET['term_id'] ?? 0);
    if (!$classId || !$termId) {
        fail('class_id and term_id are required');
    }

    $stmt = $pdo->prepare("
        SELECT
            c.id AS course_id,
            c.name AS course_name,
            c.code AS course_code,
            COALESCE(te.full_name, '-') AS teacher_name,
            COUNT(m.id) AS entered_rows,
            COALESCE(SUM(m.total_mark),0) AS total_score,
            COALESCE(AVG(m.total_mark),0) AS average_score
        FROM course_teachers ct
        INNER JOIN courses c ON c.id = ct.course_id
        LEFT JOIN teachers te ON te.id = ct.teacher_id
        LEFT JOIN student_course_marks_mvp m
            ON m.class_id = ct.class_id AND m.course_id = ct.course_id AND m.term_id = ?
        WHERE ct.class_id = ? AND ct.is_active = 1
        GROUP BY c.id, c.name, c.code, te.full_name
        ORDER BY c.name
    ");
    $stmt->execute([$termId, $classId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'rows' => $rows]);
}

function ensureYearlyTable(PDO $pdo): void {
    if (!tableExists($pdo, 'student_year_result_summary_mvp')) {
        fail('Yearly summary table missing. Run database/marklist_mvp_phase6_yearly_summary.sql', 500);
    }
}

function recalculateYearly(PDO $pdo) {
    ensureYearlyTable($pdo);
    $classId = (int)($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
    if (!$classId) {
        fail('class_id is required');
    }
    $force = (int)($_POST['force_recalculate'] ?? $_GET['force_recalculate'] ?? 0) === 1;

    $classStmt = $pdo->prepare("SELECT academic_year FROM classes WHERE id = ? LIMIT 1");
    $classStmt->execute([$classId]);
    $academicYear = (string)$classStmt->fetchColumn();
    if ($academicYear === '') {
        fail('Academic year not found for selected class', 422);
    }
    if (!$force && hasAdminFinalizedYearlySummary($pdo, $classId, $academicYear)) {
        fail('Yearly summary is admin-finalized. Use force_recalculate=1 only when intentionally reopening workflow.', 409);
    }

    $studentsStmt = $pdo->prepare("
        SELECT s.id AS student_id, s.full_name
        FROM class_enrollments ce
        INNER JOIN students s ON s.id = ce.student_id
        WHERE ce.class_id = ? AND ce.status = 'active'
        ORDER BY s.full_name
    ");
    $studentsStmt->execute([$classId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $termAvgStmt = $pdo->prepare("
        SELECT m.student_id, m.term_id, AVG(m.total_mark) AS term_avg
        FROM student_course_marks_mvp m
        INNER JOIN classes c ON c.id = m.class_id
        WHERE m.class_id = ? AND c.academic_year = ?
        GROUP BY m.student_id, m.term_id
    ");
    $termAvgStmt->execute([$classId, $academicYear]);
    $termRows = $termAvgStmt->fetchAll(PDO::FETCH_ASSOC);

    $termMap = [];
    foreach ($termRows as $r) {
        $sid = (int)$r['student_id'];
        if (!isset($termMap[$sid])) {
            $termMap[$sid] = [];
        }
        $termMap[$sid][] = (float)$r['term_avg'];
    }

    $attendanceStmt = $pdo->prepare("
        SELECT a.student_id, AVG(a.attendance_percent) AS year_attendance
        FROM student_term_attendance_mvp a
        INNER JOIN classes c ON c.id = a.class_id
        WHERE a.class_id = ? AND c.academic_year = ?
        GROUP BY a.student_id
    ");
    $attendanceStmt->execute([$classId, $academicYear]);
    $attMap = [];
    foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $attMap[(int)$r['student_id']] = round((float)$r['year_attendance'], 2);
    }

    $rows = [];
    foreach ($students as $s) {
        $sid = (int)$s['student_id'];
        $termAverages = $termMap[$sid] ?? [];
        $termsCount = count($termAverages);
        $yearTotal = round(array_sum($termAverages), 2);
        $yearAvg = $termsCount > 0 ? round($yearTotal / $termsCount, 2) : 0.00;
        $attendance = (float)($attMap[$sid] ?? 0.00);
        $decision = 'pending';
        if ($termsCount > 0) {
            $decision = ($yearAvg >= 50.0 && $attendance >= 75.0) ? 'pass' : 'fail';
        }

        $rows[] = [
            'student_id' => $sid,
            'terms_count' => $termsCount,
            'year_total' => $yearTotal,
            'year_average' => $yearAvg,
            'attendance_percent' => $attendance,
            'decision' => $decision
        ];
    }

    usort($rows, function ($a, $b) {
        if ($a['year_average'] == $b['year_average']) {
            return $b['year_total'] <=> $a['year_total'];
        }
        return $b['year_average'] <=> $a['year_average'];
    });

    $rank = 0;
    $lastAvg = null;
    foreach ($rows as $i => &$r) {
        if ($lastAvg === null || abs($r['year_average'] - $lastAvg) > 0.0001) {
            $rank = $i + 1;
            $lastAvg = $r['year_average'];
        }
        $r['rank_in_class'] = $rank;
    }
    unset($r);

    $upsert = $pdo->prepare("
        INSERT INTO student_year_result_summary_mvp (
            class_id, academic_year, student_id, terms_count,
            year_total, year_average, attendance_percent, rank_in_class,
            decision, is_finalized, finalized_by_admin_id, finalized_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL)
        ON DUPLICATE KEY UPDATE
            terms_count = VALUES(terms_count),
            year_total = VALUES(year_total),
            year_average = VALUES(year_average),
            attendance_percent = VALUES(attendance_percent),
            rank_in_class = VALUES(rank_in_class),
            decision = VALUES(decision),
            is_finalized = 0,
            finalized_by_admin_id = NULL,
            finalized_at = NULL
    ");

    $pdo->beginTransaction();
    try {
        foreach ($rows as $r) {
            $upsert->execute([
                $classId,
                $academicYear,
                (int)$r['student_id'],
                (int)$r['terms_count'],
                (float)$r['year_total'],
                (float)$r['year_average'],
                (float)$r['attendance_percent'],
                (int)$r['rank_in_class'],
                $r['decision']
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    ok(['message' => 'Yearly summary recalculated', 'rows' => count($rows), 'academic_year' => $academicYear]);
}

function getYearlySummary(PDO $pdo) {
    ensureYearlyTable($pdo);
    $classId = (int)($_GET['class_id'] ?? 0);
    if (!$classId) {
        fail('class_id is required');
    }

    $classStmt = $pdo->prepare("SELECT academic_year FROM classes WHERE id = ? LIMIT 1");
    $classStmt->execute([$classId]);
    $academicYear = (string)$classStmt->fetchColumn();
    if ($academicYear === '') {
        fail('Academic year not found for selected class', 422);
    }

    $stmt = $pdo->prepare("
        SELECT y.*, s.full_name
        FROM student_year_result_summary_mvp y
        INNER JOIN students s ON s.id = y.student_id
        WHERE y.class_id = ? AND y.academic_year = ?
        ORDER BY y.rank_in_class ASC, s.full_name ASC
    ");
    $stmt->execute([$classId, $academicYear]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'rows' => $rows, 'academic_year' => $academicYear]);
}

function finalizeYearlySummary(PDO $pdo) {
    ensureYearlyTable($pdo);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);

    $classId = (int)($_POST['class_id'] ?? 0);
    if (!$classId) fail('class_id is required');

    $classStmt = $pdo->prepare("SELECT academic_year FROM classes WHERE id = ? LIMIT 1");
    $classStmt->execute([$classId]);
    $academicYear = (string)$classStmt->fetchColumn();
    if ($academicYear === '') {
        fail('Academic year not found for selected class', 422);
    }
    $rowsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_year_result_summary_mvp
        WHERE class_id = ? AND academic_year = ?
    ");
    $rowsStmt->execute([$classId, $academicYear]);
    if ((int)$rowsStmt->fetchColumn() <= 0) {
        fail('No yearly summary rows to finalize. Recalculate yearly summary first.', 422);
    }

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $stmt = $pdo->prepare("
        UPDATE student_year_result_summary_mvp
        SET is_finalized = 1, finalized_by_admin_id = ?, finalized_at = NOW()
        WHERE class_id = ? AND academic_year = ?
    ");
    $stmt->execute([$adminId, $classId, $academicYear]);
    ok(['message' => 'Yearly summary finalized', 'affected' => $stmt->rowCount()]);
}

function applyYearlyStatusToStudents(PDO $pdo) {
    ensureYearlyTable($pdo);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);

    $classId = (int)($_POST['class_id'] ?? 0);
    if (!$classId) fail('class_id is required');

    $classStmt = $pdo->prepare("SELECT academic_year FROM classes WHERE id = ? LIMIT 1");
    $classStmt->execute([$classId]);
    $academicYear = (string)$classStmt->fetchColumn();
    if ($academicYear === '') {
        fail('Academic year not found for selected class', 422);
    }

    $stmt = $pdo->prepare("
        UPDATE students s
        INNER JOIN student_year_result_summary_mvp y ON y.student_id = s.id
        SET s.current_result_status = CASE
            WHEN y.decision = 'pass' THEN 'pass'
            WHEN y.decision = 'fail' THEN 'fail'
            ELSE s.current_result_status
        END
        WHERE y.class_id = ? AND y.academic_year = ? AND y.is_finalized = 1
    ");
    $stmt->execute([$classId, $academicYear]);
    ok(['message' => 'Student statuses updated from finalized yearly summary', 'affected' => $stmt->rowCount()]);
}

function getNextGradeValue(string $grade): ?string {
    $order = ['new', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
    $idx = array_search($grade, $order, true);
    if ($idx === false || $idx >= count($order) - 1) {
        return null;
    }
    return $order[$idx + 1];
}

function getNextAcademicYearValue(string $academicYear): string {
    $value = trim($academicYear);
    if ($value === '') return '';
    if (ctype_digit($value)) {
        return (string)((int)$value + 1);
    }
    if (preg_match('/^(\d{4})\/(\d{4})$/', $value, $m)) {
        return ((int)$m[1] + 1) . '/' . ((int)$m[2] + 1);
    }
    return '';
}

function getClassContext(PDO $pdo, int $classId): array {
    $stmt = $pdo->prepare("SELECT id, name, grade, section, academic_year FROM classes WHERE id = ? LIMIT 1");
    $stmt->execute([$classId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        fail('Class not found', 404);
    }
    return $row;
}

function resolveTargetClassId(PDO $pdo, string $nextGrade, string $nextAcademicYear, ?string $section): ?int {
    $stmt = $pdo->prepare("
        SELECT id
        FROM classes
        WHERE grade = ? AND academic_year = ?
        ORDER BY CASE
            WHEN COALESCE(TRIM(section), '') = COALESCE(TRIM(?), '') THEN 0
            WHEN COALESCE(TRIM(section), '') = '' THEN 1
            ELSE 2
        END, id ASC
        LIMIT 1
    ");
    $stmt->execute([$nextGrade, $nextAcademicYear, $section]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function getFinalizedYearlyPassRows(PDO $pdo, int $classId, string $academicYear): array {
    $stmt = $pdo->prepare("
        SELECT y.student_id, s.full_name, y.decision, y.is_finalized
        FROM student_year_result_summary_mvp y
        INNER JOIN students s ON s.id = y.student_id
        WHERE y.class_id = ? AND y.academic_year = ? AND y.is_finalized = 1 AND y.decision = 'pass'
        ORDER BY s.full_name
    ");
    $stmt->execute([$classId, $academicYear]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function previewYearlyPromotion(PDO $pdo) {
    ensureYearlyTable($pdo);
    $classId = (int)($_GET['class_id'] ?? 0);
    if (!$classId) fail('class_id is required');

    $class = getClassContext($pdo, $classId);
    $currentGrade = (string)$class['grade'];
    $currentYear = (string)$class['academic_year'];
    $nextGrade = getNextGradeValue($currentGrade);
    if ($nextGrade === null) {
        fail('Selected class grade cannot be promoted further');
    }

    $nextAcademicYear = getNextAcademicYearValue($currentYear);
    if ($nextAcademicYear === '') {
        fail('Unsupported academic year format for promotion. Expected numeric year or YYYY/YYYY');
    }

    $targetClassId = resolveTargetClassId($pdo, $nextGrade, $nextAcademicYear, $class['section'] ?? null);
    $passRows = getFinalizedYearlyPassRows($pdo, $classId, $currentYear);
    if (!$passRows) {
        echo json_encode([
            'success' => true,
            'message' => 'No finalized yearly pass rows found for this class.',
            'summary' => [
                'eligible_passed' => 0,
                'promotable' => 0,
                'already_in_target' => 0,
                'missing_target_class' => $targetClassId ? 0 : 1
            ],
            'context' => [
                'from_class_id' => $classId,
                'from_grade' => $currentGrade,
                'from_academic_year' => $currentYear,
                'to_grade' => $nextGrade,
                'to_academic_year' => $nextAcademicYear,
                'to_class_id' => $targetClassId
            ],
            'rows' => []
        ]);
        return;
    }

    $checkTargetStmt = $pdo->prepare("
        SELECT 1
        FROM class_enrollments
        WHERE student_id = ? AND class_id = ? AND status = 'active'
        LIMIT 1
    ");

    $alreadyInTarget = 0;
    $promotable = 0;
    $rows = [];
    foreach ($passRows as $r) {
        $sid = (int)$r['student_id'];
        $status = 'ready';
        if (!$targetClassId) {
            $status = 'missing_target_class';
        } else {
            $checkTargetStmt->execute([$sid, $targetClassId]);
            if ($checkTargetStmt->fetchColumn()) {
                $status = 'already_in_target';
                $alreadyInTarget++;
            } else {
                $promotable++;
            }
        }
        $rows[] = [
            'student_id' => $sid,
            'full_name' => $r['full_name'],
            'status' => $status
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Promotion preview ready',
        'summary' => [
            'eligible_passed' => count($passRows),
            'promotable' => $promotable,
            'already_in_target' => $alreadyInTarget,
            'missing_target_class' => $targetClassId ? 0 : 1
        ],
        'context' => [
            'from_class_id' => $classId,
            'from_grade' => $currentGrade,
            'from_academic_year' => $currentYear,
            'to_grade' => $nextGrade,
            'to_academic_year' => $nextAcademicYear,
            'to_class_id' => $targetClassId
        ],
        'rows' => $rows
    ]);
}

function promoteYearlyPassedStudents(PDO $pdo) {
    promoteYearlyInternal($pdo, null);
}

function promoteYearlySelectedStudents(PDO $pdo) {
    $idsRaw = $_POST['student_ids'] ?? '[]';
    $ids = json_decode((string)$idsRaw, true);
    if (!is_array($ids)) {
        fail('Invalid student_ids payload');
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    if (empty($ids)) {
        fail('No valid student IDs selected', 422);
    }
    promoteYearlyInternal($pdo, $ids);
}

function promoteYearlyInternal(PDO $pdo, ?array $selectedStudentIds) {
    ensureYearlyTable($pdo);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);

    $classId = (int)($_POST['class_id'] ?? 0);
    if (!$classId) fail('class_id is required');

    $class = getClassContext($pdo, $classId);
    $currentGrade = (string)$class['grade'];
    $currentYear = (string)$class['academic_year'];
    $nextGrade = getNextGradeValue($currentGrade);
    if ($nextGrade === null) {
        fail('Selected class grade cannot be promoted further');
    }

    $nextAcademicYear = getNextAcademicYearValue($currentYear);
    if ($nextAcademicYear === '') {
        fail('Unsupported academic year format for promotion. Expected numeric year or YYYY/YYYY');
    }

    $targetClassId = resolveTargetClassId($pdo, $nextGrade, $nextAcademicYear, $class['section'] ?? null);
    if (!$targetClassId) {
        fail("No target class found for grade {$nextGrade} in academic year {$nextAcademicYear}. Create target class first.", 422);
    }

    $passRows = getFinalizedYearlyPassRows($pdo, $classId, $currentYear);
    if ($selectedStudentIds !== null) {
        $selectedMap = array_fill_keys($selectedStudentIds, true);
        $passRows = array_values(array_filter($passRows, function ($r) use ($selectedMap) {
            return isset($selectedMap[(int)$r['student_id']]);
        }));
    }
    if (!$passRows) {
        fail('No finalized yearly pass rows found for this class', 422);
    }

    $checkTargetAnyStmt = $pdo->prepare("
        SELECT id
        FROM class_enrollments
        WHERE student_id = ? AND class_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $checkTargetActiveStmt = $pdo->prepare("
        SELECT 1
        FROM class_enrollments
        WHERE student_id = ? AND class_id = ? AND status = 'active'
        LIMIT 1
    ");
    $deactivateActiveStmt = $pdo->prepare("
        UPDATE class_enrollments
        SET status = 'transferred'
        WHERE student_id = ? AND status = 'active' AND class_id <> ?
    ");
    $reactivateTargetStmt = $pdo->prepare("
        UPDATE class_enrollments
        SET status = 'active', enrollment_date = CURDATE()
        WHERE id = ?
    ");
    $insertTargetStmt = $pdo->prepare("
        INSERT INTO class_enrollments (class_id, student_id, enrollment_date, status)
        VALUES (?, ?, CURDATE(), 'active')
    ");
    $updateStudentStmt = $pdo->prepare("
        UPDATE students
        SET current_grade = ?, current_result_status = 'pass'
        WHERE id = ?
    ");

    $promoted = 0;
    $alreadyInTarget = 0;
    $pdo->beginTransaction();
    try {
        foreach ($passRows as $r) {
            $sid = (int)$r['student_id'];
            $checkTargetActiveStmt->execute([$sid, $targetClassId]);
            if ($checkTargetActiveStmt->fetchColumn()) {
                $alreadyInTarget++;
                $updateStudentStmt->execute([$nextGrade, $sid]);
                continue;
            }

            $deactivateActiveStmt->execute([$sid, $targetClassId]);
            $checkTargetAnyStmt->execute([$sid, $targetClassId]);
            $existingTargetEnrollmentId = $checkTargetAnyStmt->fetchColumn();
            if ($existingTargetEnrollmentId) {
                $reactivateTargetStmt->execute([(int)$existingTargetEnrollmentId]);
            } else {
                $insertTargetStmt->execute([$targetClassId, $sid]);
            }
            $updateStudentStmt->execute([$nextGrade, $sid]);
            $promoted++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Promotion completed from finalized yearly summary',
        'summary' => [
            'eligible_passed' => count($passRows),
            'promoted' => $promoted,
            'already_in_target' => $alreadyInTarget
        ],
        'context' => [
            'from_class_id' => $classId,
            'from_grade' => $currentGrade,
            'from_academic_year' => $currentYear,
            'to_class_id' => $targetClassId,
            'to_grade' => $nextGrade,
            'to_academic_year' => $nextAcademicYear
        ]
    ]);
}

function getHomeroomStatus(PDO $pdo) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $termId = (int)($_GET['term_id'] ?? 0);
    if (!$classId || !$termId) {
        fail('class_id and term_id are required');
    }
    if (!tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        ok(['status' => 'none']);
    }
    $stmt = $pdo->prepare("
        SELECT status, submitted_at, updated_at
        FROM homeroom_term_matrix_submissions_mvp
        WHERE class_id = ? AND term_id = ?
        LIMIT 1
    ");
    $stmt->execute([$classId, $termId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        ok(['status' => 'none']);
    }
    ok([
        'status' => (string)$row['status'],
        'submitted_at' => $row['submitted_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null
    ]);
}

function setYearlyDecisionBulk(PDO $pdo) {
    ensureYearlyTable($pdo);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);

    $classId = (int)($_POST['class_id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');
    $idsRaw = $_POST['student_ids'] ?? '[]';
    $ids = json_decode((string)$idsRaw, true);
    if (!$classId) fail('class_id is required');
    if (!in_array($decision, ['pass', 'fail', 'pending'], true)) fail('Invalid decision');
    if (!is_array($ids)) fail('Invalid student_ids payload');
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    if (empty($ids)) fail('No valid student IDs selected', 422);

    $classStmt = $pdo->prepare("SELECT academic_year FROM classes WHERE id = ? LIMIT 1");
    $classStmt->execute([$classId]);
    $academicYear = (string)$classStmt->fetchColumn();
    if ($academicYear === '') fail('Academic year not found for selected class', 422);

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$decision, $classId, $academicYear], $ids);
    $stmt = $pdo->prepare("
        UPDATE student_year_result_summary_mvp
        SET decision = ?, is_finalized = 0, finalized_by_admin_id = NULL, finalized_at = NULL
        WHERE class_id = ? AND academic_year = ? AND student_id IN ($ph)
    ");
    $stmt->execute($params);
    echo json_encode([
        'success' => true,
        'message' => 'Yearly decision updated for selected students',
        'affected' => $stmt->rowCount()
    ]);
}

function getHomeroomSubmissions(PDO $pdo) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $termId = (int)($_GET['term_id'] ?? 0);
    $onlySubmitted = (int)($_GET['only_submitted'] ?? 0) === 1;

    if (!tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        echo json_encode([
            'success' => true,
            'rows' => [],
            'message' => 'No homeroom submissions table found yet. It will be created once homeroom saves/submits first matrix.'
        ]);
        return;
    }
    ensureHomeroomReviewColumns($pdo);

    $where = [];
    $params = [];
    if ($classId > 0) {
        $where[] = "h.class_id = ?";
        $params[] = $classId;
    }
    if ($termId > 0) {
        $where[] = "h.term_id = ?";
        $params[] = $termId;
    }
    if ($onlySubmitted) {
        $where[] = "h.status = 'submitted'";
    }
    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("
        SELECT
            h.id,
            h.class_id,
            h.term_id,
            h.teacher_id,
            h.status,
            h.notes,
            h.submitted_at,
            h.updated_at,
            h.reviewed_at,
            h.reviewed_by_admin_id,
            c.name AS class_name,
            c.grade,
            c.section,
            t.name AS term_name,
            te.full_name AS teacher_name,
            ra.username AS reviewed_by_admin_username
        FROM homeroom_term_matrix_submissions_mvp h
        INNER JOIN classes c ON c.id = h.class_id
        INNER JOIN academic_terms_mvp t ON t.id = h.term_id
        INNER JOIN teachers te ON te.id = h.teacher_id
        LEFT JOIN admins ra ON ra.id = h.reviewed_by_admin_id
        {$whereSql}
        ORDER BY h.updated_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows]);
}

function getReadiness(PDO $pdo) {
    $termId = (int)($_GET['term_id'] ?? 0);
    if (!$termId) {
        fail('term_id is required');
    }

    $classes = $pdo->query("
        SELECT id, name, grade, section, academic_year
        FROM classes
        ORDER BY grade, section, name
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!count($classes)) {
        ok(['rows' => [], 'summary' => ['classes' => 0, 'ready_for_admin' => 0, 'with_new_updates' => 0]]);
    }

    $classIds = array_map(static fn($c) => (int)$c['id'], $classes);
    $in = implode(',', array_fill(0, count($classIds), '?'));

    $studentMap = [];
    $studentStmt = $pdo->prepare("
        SELECT class_id, COUNT(*) AS total_students
        FROM class_enrollments
        WHERE status = 'active' AND class_id IN ($in)
        GROUP BY class_id
    ");
    $studentStmt->execute($classIds);
    foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $studentMap[(int)$r['class_id']] = (int)$r['total_students'];
    }

    $courseMap = [];
    $courseStmt = $pdo->prepare("
        SELECT class_id, COUNT(DISTINCT course_id) AS expected_courses
        FROM course_teachers
        WHERE is_active = 1 AND class_id IN ($in)
        GROUP BY class_id
    ");
    $courseStmt->execute($classIds);
    foreach ($courseStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $courseMap[(int)$r['class_id']] = (int)$r['expected_courses'];
    }

    $teacherMap = [];
    if (tableExists($pdo, 'student_course_marks_mvp')) {
        $teacherStmt = $pdo->prepare("
            SELECT class_id,
                   COUNT(DISTINCT course_id) AS submitted_courses,
                   COUNT(*) AS mark_rows,
                   MAX(updated_at) AS latest_marks_updated_at
            FROM student_course_marks_mvp
            WHERE term_id = ? AND class_id IN ($in)
            GROUP BY class_id
        ");
        $teacherStmt->execute(array_merge([$termId], $classIds));
        foreach ($teacherStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $teacherMap[(int)$r['class_id']] = [
                'submitted_courses' => (int)$r['submitted_courses'],
                'mark_rows' => (int)$r['mark_rows'],
                'latest_marks_updated_at' => $r['latest_marks_updated_at'] ? (string)$r['latest_marks_updated_at'] : null
            ];
        }
    }

    $homeroomMap = [];
    if (tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        ensureHomeroomReviewColumns($pdo);
        $hrStmt = $pdo->prepare("
            SELECT class_id, status, updated_at, submitted_at, reviewed_at
            FROM homeroom_term_matrix_submissions_mvp
            WHERE term_id = ? AND class_id IN ($in)
        ");
        $hrStmt->execute(array_merge([$termId], $classIds));
        foreach ($hrStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $homeroomMap[(int)$r['class_id']] = [
                'status' => (string)$r['status'],
                'updated_at' => $r['updated_at'] ? (string)$r['updated_at'] : null,
                'submitted_at' => $r['submitted_at'] ? (string)$r['submitted_at'] : null,
                'reviewed_at' => $r['reviewed_at'] ? (string)$r['reviewed_at'] : null
            ];
        }
    }

    $summaryMap = [];
    $summaryStmt = $pdo->prepare("
        SELECT class_id, COUNT(*) AS finalized_rows
        FROM student_term_result_summary_mvp
        WHERE term_id = ? AND is_finalized = 1 AND class_id IN ($in)
        GROUP BY class_id
    ");
    $summaryStmt->execute(array_merge([$termId], $classIds));
    foreach ($summaryStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $summaryMap[(int)$r['class_id']] = (int)$r['finalized_rows'];
    }

    $rows = [];
    $readyForAdmin = 0;
    $withNewUpdates = 0;

    foreach ($classes as $c) {
        $classId = (int)$c['id'];
        $expectedCourses = (int)($courseMap[$classId] ?? 0);
        $submittedCourses = (int)($teacherMap[$classId]['submitted_courses'] ?? 0);
        $markRows = (int)($teacherMap[$classId]['mark_rows'] ?? 0);
        $students = (int)($studentMap[$classId] ?? 0);
        $teacherUpdated = $teacherMap[$classId]['latest_marks_updated_at'] ?? null;

        $hStatus = (string)($homeroomMap[$classId]['status'] ?? 'none');
        $hUpdated = $homeroomMap[$classId]['updated_at'] ?? null;
        $hSubmitted = $homeroomMap[$classId]['submitted_at'] ?? null;
        $hReviewed = $homeroomMap[$classId]['reviewed_at'] ?? null;

        $hasNewTeacherUpdates = false;
        if ($markRows > 0) {
            if ($hStatus === 'none') {
                $hasNewTeacherUpdates = true;
            } elseif ($teacherUpdated === null) {
                $hasNewTeacherUpdates = false;
            } elseif ($hUpdated === null) {
                $hasNewTeacherUpdates = true;
            } else {
                $hasNewTeacherUpdates = strtotime($teacherUpdated) > strtotime($hUpdated);
            }
        }

        $teacherPct = $expectedCourses > 0 ? round(($submittedCourses / $expectedCourses) * 100, 2) : 0.0;
        $finalizedRows = (int)($summaryMap[$classId] ?? 0);

        $pipelineState = 'awaiting_teacher';
        if ($hStatus === 'submitted' && $finalizedRows > 0) {
            $pipelineState = 'ready_for_admin';
        } elseif ($submittedCourses >= $expectedCourses && $expectedCourses > 0) {
            $pipelineState = 'ready_for_homeroom';
        } elseif ($submittedCourses > 0) {
            $pipelineState = 'teacher_in_progress';
        }
        if ($pipelineState === 'ready_for_admin') $readyForAdmin++;
        if ($hasNewTeacherUpdates) $withNewUpdates++;

        $rows[] = [
            'class_id' => $classId,
            'class_name' => (string)$c['name'],
            'grade' => (string)$c['grade'],
            'section' => (string)($c['section'] ?? ''),
            'academic_year' => (string)($c['academic_year'] ?? ''),
            'students' => $students,
            'expected_courses' => $expectedCourses,
            'submitted_courses' => $submittedCourses,
            'teacher_completion_percent' => $teacherPct,
            'mark_rows' => $markRows,
            'latest_teacher_update' => $teacherUpdated,
            'homeroom_status' => $hStatus,
            'homeroom_submitted_at' => $hSubmitted,
            'homeroom_reviewed_at' => $hReviewed,
            'finalized_term_rows' => $finalizedRows,
            'has_new_teacher_updates' => $hasNewTeacherUpdates,
            'pipeline_state' => $pipelineState
        ];
    }

    ok([
        'rows' => $rows,
        'summary' => [
            'classes' => count($rows),
            'ready_for_admin' => $readyForAdmin,
            'with_new_updates' => $withNewUpdates
        ]
    ]);
}

function hasAdminFinalizedTermSummary(PDO $pdo, int $classId, int $termId): bool {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM student_term_result_summary_mvp
        WHERE class_id = ? AND term_id = ? AND is_finalized = 1 AND finalized_by_admin_id IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$classId, $termId]);
    return (bool)$stmt->fetchColumn();
}

function hasAdminFinalizedYearlySummary(PDO $pdo, int $classId, string $academicYear): bool {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM student_year_result_summary_mvp
        WHERE class_id = ? AND academic_year = ? AND is_finalized = 1 AND finalized_by_admin_id IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$classId, $academicYear]);
    return (bool)$stmt->fetchColumn();
}

function assertTermReadyForAdminFinalize(PDO $pdo, int $classId, int $termId): void {
    $rowsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_term_result_summary_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $rowsStmt->execute([$classId, $termId]);
    if ((int)$rowsStmt->fetchColumn() <= 0) {
        fail('No term summary rows to finalize. Recalculate first.', 422);
    }

    if (!tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        fail('Homeroom matrix table is missing. Homeroom submission is required before finalization.', 422);
    }

    $statusStmt = $pdo->prepare("
        SELECT status, updated_at
        FROM homeroom_term_matrix_submissions_mvp
        WHERE class_id = ? AND term_id = ?
        LIMIT 1
    ");
    $statusStmt->execute([$classId, $termId]);
    $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
    if (!$statusRow || (string)$statusRow['status'] !== 'submitted') {
        fail('Homeroom matrix must be submitted before admin finalization.', 422);
    }

    $latestTeacherStmt = $pdo->prepare("
        SELECT MAX(updated_at)
        FROM student_course_marks_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $latestTeacherStmt->execute([$classId, $termId]);
    $latestTeacher = $latestTeacherStmt->fetchColumn();

    $homeroomUpdated = $statusRow['updated_at'] ?? null;
    if ($latestTeacher && $homeroomUpdated && strtotime((string)$latestTeacher) > strtotime((string)$homeroomUpdated)) {
        fail('Teacher marks changed after homeroom submission. Ask homeroom to reopen/review and submit again.', 409);
    }

    $expectedCoursesStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT course_id)
        FROM course_teachers
        WHERE class_id = ? AND is_active = 1
    ");
    $expectedCoursesStmt->execute([$classId]);
    $expectedCourses = (int)$expectedCoursesStmt->fetchColumn();

    $submittedCoursesStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT course_id)
        FROM student_course_marks_mvp
        WHERE class_id = ? AND term_id = ?
    ");
    $submittedCoursesStmt->execute([$classId, $termId]);
    $submittedCourses = (int)$submittedCoursesStmt->fetchColumn();

    if ($expectedCourses > 0 && $submittedCourses < $expectedCourses) {
        fail('Not all active class courses have submitted marklists for this term.', 422);
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function ensureHomeroomReviewColumns(PDO $pdo): void {
    if (!tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        return;
    }
    if (!columnExists($pdo, 'homeroom_term_matrix_submissions_mvp', 'reviewed_by_admin_id')) {
        $pdo->exec("ALTER TABLE homeroom_term_matrix_submissions_mvp ADD COLUMN reviewed_by_admin_id BIGINT UNSIGNED NULL AFTER submitted_at");
    }
    if (!columnExists($pdo, 'homeroom_term_matrix_submissions_mvp', 'reviewed_at')) {
        $pdo->exec("ALTER TABLE homeroom_term_matrix_submissions_mvp ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by_admin_id");
    }
}

function markHomeroomReviewed(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);

    $classId = (int)($_POST['class_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    if (!$classId || !$termId) {
        fail('class_id and term_id are required');
    }
    if (!tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        fail('No homeroom submission found to review', 422);
    }
    ensureHomeroomReviewColumns($pdo);

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $stmt = $pdo->prepare("
        UPDATE homeroom_term_matrix_submissions_mvp
        SET reviewed_by_admin_id = ?, reviewed_at = NOW()
        WHERE class_id = ? AND term_id = ?
    ");
    $stmt->execute([$adminId, $classId, $termId]);
    if ($stmt->rowCount() < 1) {
        fail('No homeroom submission found for selected class and term', 422);
    }
    echo json_encode(['success' => true, 'message' => 'Homeroom submission marked as reviewed', 'affected' => $stmt->rowCount()]);
}

function markHomeroomReviewedBulk(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) fail('Invalid CSRF token', 403);
    if (!tableExists($pdo, 'homeroom_term_matrix_submissions_mvp')) {
        fail('No homeroom submissions table found', 422);
    }
    ensureHomeroomReviewColumns($pdo);

    $idsRaw = $_POST['ids'] ?? '[]';
    $ids = json_decode((string)$idsRaw, true);
    if (!is_array($ids)) {
        fail('Invalid ids payload');
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    if (empty($ids)) {
        fail('No valid submission ids selected', 422);
    }

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$adminId], $ids);

    $stmt = $pdo->prepare("
        UPDATE homeroom_term_matrix_submissions_mvp
        SET reviewed_by_admin_id = ?, reviewed_at = NOW()
        WHERE id IN ($ph)
    ");
    $stmt->execute($params);
    echo json_encode([
        'success' => true,
        'message' => 'Selected homeroom submissions marked as reviewed',
        'affected' => $stmt->rowCount()
    ]);
}
