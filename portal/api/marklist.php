<?php
session_start();
require '../../config.php';
require '../../includes/security_helpers.php';
require '../../includes/portal_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function tableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

if (!tableExists($pdo, 'student_course_marks_mvp') || !tableExists($pdo, 'academic_terms_mvp')) {
    fail('Base MVP tables are missing. Run database/marklist_mvp_migration.sql first.', 500);
}
if (!tableExists($pdo, 'mark_weight_settings_mvp')) {
    fail('Weight settings table missing. Run database/marklist_mvp_phase3_weights.sql first.', 500);
}

$ctx = getAuthContext();
if (!$ctx) {
    fail('Unauthorized', 401);
}
if ($ctx['actor'] !== 'portal' || ($ctx['portal_role'] ?? '') !== 'teacher') {
    fail('Access denied', 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'bootstrap':
            bootstrapData($pdo, $ctx);
            break;
        case 'students':
            studentsWithMarks($pdo, $ctx);
            break;
        case 'export_template_csv':
            exportTemplateCsv($pdo, $ctx);
            break;
        case 'export_marklist_csv':
            exportMarklistCsv($pdo, $ctx);
            break;
        case 'import_preview_csv':
            importPreviewFile($pdo, $ctx);
            break;
        case 'import_preview_file':
            importPreviewFile($pdo, $ctx);
            break;
        case 'save_marks':
            saveMarks($pdo, $ctx);
            break;
        default:
            fail('Invalid action');
    }
} catch (Throwable $e) {
    fail('Server error: ' . $e->getMessage(), 500);
}

function bootstrapData(PDO $pdo, array $ctx) {
    $stmt = $pdo->prepare("
        SELECT
            c.id AS class_id,
            c.name AS class_name,
            c.grade,
            c.section,
            c.academic_year,
            co.id AS course_id,
            co.name AS course_name,
            co.code AS course_code
        FROM course_teachers ct
        INNER JOIN classes c ON c.id = ct.class_id
        INNER JOIN courses co ON co.id = ct.course_id
        WHERE ct.teacher_id = ? AND ct.is_active = 1
        ORDER BY c.grade, c.section, c.name, co.name
    ");
    $stmt->execute([(int)$ctx['teacher_id']]);
    $assignmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $classes = [];
    $classSeen = [];
    $courses = [];
    $courseSeen = [];
    $classCourseMap = [];

    foreach ($assignmentRows as $row) {
        $classId = (int)$row['class_id'];
        $courseId = (int)$row['course_id'];
        $classKey = (string)$classId;

        if (!isset($classSeen[$classKey])) {
            $classes[] = [
                'id' => $classId,
                'name' => $row['class_name'],
                'grade' => $row['grade'],
                'section' => $row['section'],
                'academic_year' => (int)$row['academic_year']
            ];
            $classSeen[$classKey] = true;
        }

        if (!isset($courseSeen[(string)$courseId])) {
            $courses[] = [
                'id' => $courseId,
                'name' => $row['course_name'],
                'code' => $row['course_code']
            ];
            $courseSeen[(string)$courseId] = true;
        }

        if (!isset($classCourseMap[$classKey])) {
            $classCourseMap[$classKey] = [];
        }
        $classCourseMap[$classKey][] = [
            'id' => $courseId,
            'name' => $row['course_name'],
            'code' => $row['course_code']
        ];
    }

    $terms = $pdo->query("
        SELECT id, name, term_order, is_active
        FROM academic_terms_mvp
        ORDER BY term_order
    ")->fetchAll();

    $recentStmt = $pdo->prepare("
        SELECT
            m.class_id,
            m.course_id,
            m.term_id,
            c.name AS class_name,
            c.grade,
            c.section,
            co.name AS course_name,
            co.code AS course_code,
            t.name AS term_name,
            COUNT(*) AS row_count,
            MAX(m.updated_at) AS last_updated
        FROM student_course_marks_mvp m
        INNER JOIN course_teachers ct
            ON ct.class_id = m.class_id
           AND ct.course_id = m.course_id
           AND ct.teacher_id = ?
           AND ct.is_active = 1
        INNER JOIN classes c ON c.id = m.class_id
        INNER JOIN courses co ON co.id = m.course_id
        INNER JOIN academic_terms_mvp t ON t.id = m.term_id
        WHERE m.entered_by_portal_user_id = ?
        GROUP BY
            m.class_id, m.course_id, m.term_id,
            c.name, c.grade, c.section,
            co.name, co.code, t.name
        ORDER BY last_updated DESC
        LIMIT 12
    ");
    $recentStmt->execute([(int)$ctx['teacher_id'], (int)$ctx['portal_user_id']]);
    $recentMarklists = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'courses' => $courses,
        'class_course_map' => $classCourseMap,
        'terms' => $terms,
        'recent_marklists' => $recentMarklists
    ]);
}

function studentsWithMarks(PDO $pdo, array $ctx) {
    $classId = (int)($_GET['class_id'] ?? 0);
    $courseId = (int)($_GET['course_id'] ?? 0);
    $termId = (int)($_GET['term_id'] ?? 0);

    if (!$classId || !$courseId || !$termId) {
        fail('class_id, course_id, and term_id are required');
    }
    if (!termExists($pdo, $termId)) {
        fail('Selected term does not exist', 422);
    }
    if (!canAccessClassCourse($pdo, $ctx, $classId, $courseId)) {
        fail('Access denied for selected class/course', 403);
    }

    $weights = loadWeights($pdo, $classId, $courseId, $termId);

    $studentsStmt = $pdo->prepare("
        SELECT s.id AS student_id, s.full_name, s.current_grade
        FROM class_enrollments ce
        INNER JOIN students s ON s.id = ce.student_id
        WHERE ce.class_id = ? AND ce.status = 'active'
        ORDER BY s.full_name
    ");
    $studentsStmt->execute([$classId]);
    $students = $studentsStmt->fetchAll();

    $marksStmt = $pdo->prepare("
        SELECT
            student_id,
            book_mark,
            assignment_mark,
            quiz_mark,
            mid_exam_mark,
            final_exam_mark,
            attendance_percent,
            total_mark,
            remark,
            is_finalized,
            updated_at
        FROM student_course_marks_mvp
        WHERE class_id = ? AND course_id = ? AND term_id = ?
    ");
    $marksStmt->execute([$classId, $courseId, $termId]);
    $marks = $marksStmt->fetchAll();

    $markMap = [];
    foreach ($marks as $m) {
        $markMap[(int)$m['student_id']] = $m;
    }

    $rows = [];
    $existingCount = 0;
    $finalizedCount = 0;
    $contextVersion = null;
    foreach ($students as $s) {
        $sid = (int)$s['student_id'];
        $m = $markMap[$sid] ?? null;
        if ($m) {
            $existingCount++;
            if ((int)$m['is_finalized'] === 1) {
                $finalizedCount++;
            }
            $updatedAt = (string)($m['updated_at'] ?? '');
            if ($updatedAt !== '' && ($contextVersion === null || strcmp($updatedAt, $contextVersion) > 0)) {
                $contextVersion = $updatedAt;
            }
        }
        $rows[] = [
            'student_id' => $sid,
            'full_name' => $s['full_name'],
            'current_grade' => $s['current_grade'],
            'book_mark' => $m ? (float)$m['book_mark'] : null,
            'assignment_mark' => $m ? (float)$m['assignment_mark'] : null,
            'quiz_mark' => $m ? (float)$m['quiz_mark'] : null,
            'mid_exam_mark' => $m ? (float)$m['mid_exam_mark'] : null,
            'final_exam_mark' => $m ? (float)$m['final_exam_mark'] : null,
            'attendance_percent' => $m && $m['attendance_percent'] !== null ? (float)$m['attendance_percent'] : null,
            'total_mark' => $m ? (float)$m['total_mark'] : null,
            'remark' => $m['remark'] ?? null,
            'is_finalized' => $m ? (int)$m['is_finalized'] : 0
        ];
    }

    echo json_encode([
        'success' => true,
        'weights' => $weights,
        'students' => $rows,
        'count' => count($rows),
        'existing_rows' => $existingCount,
        'finalized_rows' => $finalizedCount,
        'is_locked' => $finalizedCount > 0,
        'context_version' => $contextVersion
    ]);
}

function exportTemplateCsv(PDO $pdo, array $ctx) {
    [$classId, $courseId, $termId] = requireContextIds($_GET);
    if (!termExists($pdo, $termId)) {
        fail('Selected term does not exist', 422);
    }
    if (!canAccessClassCourse($pdo, $ctx, $classId, $courseId)) {
        fail('Access denied for selected class/course', 403);
    }

    $students = loadActiveStudents($pdo, $classId);
    [$className, $courseName, $termName] = loadContextNames($pdo, $classId, $courseId, $termId);
    $filename = sprintf(
        'marklist_template_%s_%s_%s.csv',
        slugifyFilenamePart($className),
        slugifyFilenamePart($courseName),
        slugifyFilenamePart($termName)
    );
    streamCsv($filename, [
        'student_id', 'full_name', 'book_mark', 'assignment_mark', 'quiz_mark',
        'mid_exam_mark', 'final_exam_mark', 'attendance_percent', 'remark'
    ], array_map(function ($s) {
        return [
            (int)$s['student_id'],
            (string)$s['full_name'],
            '', '', '', '', '', '', ''
        ];
    }, $students));
}

function exportMarklistCsv(PDO $pdo, array $ctx) {
    [$classId, $courseId, $termId] = requireContextIds($_GET);
    if (!termExists($pdo, $termId)) {
        fail('Selected term does not exist', 422);
    }
    if (!canAccessClassCourse($pdo, $ctx, $classId, $courseId)) {
        fail('Access denied for selected class/course', 403);
    }

    $students = loadActiveStudents($pdo, $classId);
    $marksStmt = $pdo->prepare("
        SELECT student_id, book_mark, assignment_mark, quiz_mark, mid_exam_mark, final_exam_mark, attendance_percent, remark
        FROM student_course_marks_mvp
        WHERE class_id = ? AND course_id = ? AND term_id = ?
    ");
    $marksStmt->execute([$classId, $courseId, $termId]);
    $markMap = [];
    foreach ($marksStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $markMap[(int)$row['student_id']] = $row;
    }

    $rows = [];
    foreach ($students as $s) {
        $sid = (int)$s['student_id'];
        $m = $markMap[$sid] ?? [];
        $rows[] = [
            $sid,
            (string)$s['full_name'],
            csvNumber($m['book_mark'] ?? null),
            csvNumber($m['assignment_mark'] ?? null),
            csvNumber($m['quiz_mark'] ?? null),
            csvNumber($m['mid_exam_mark'] ?? null),
            csvNumber($m['final_exam_mark'] ?? null),
            csvNumber($m['attendance_percent'] ?? null),
            (string)($m['remark'] ?? '')
        ];
    }

    [$className, $courseName, $termName] = loadContextNames($pdo, $classId, $courseId, $termId);
    $filename = sprintf(
        'marklist_export_%s_%s_%s.csv',
        slugifyFilenamePart($className),
        slugifyFilenamePart($courseName),
        slugifyFilenamePart($termName)
    );
    streamCsv($filename, [
        'student_id', 'full_name', 'book_mark', 'assignment_mark', 'quiz_mark',
        'mid_exam_mark', 'final_exam_mark', 'attendance_percent', 'remark'
    ], $rows);
}

function importPreviewFile(PDO $pdo, array $ctx) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Method not allowed', 405);
    }
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) {
        fail('Invalid CSRF token', 403);
    }

    [$classId, $courseId, $termId] = requireContextIds($_POST);
    if (!termExists($pdo, $termId)) {
        fail('Selected term does not exist', 422);
    }
    if (!canAccessClassCourse($pdo, $ctx, $classId, $courseId)) {
        fail('Access denied for selected class/course', 403);
    }
    if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        fail('Import file is required', 422);
    }

    $file = $_FILES['import_file'];
    if ((int)($file['size'] ?? 0) <= 0 || (int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
        fail('Import file must be between 1 byte and 5 MB', 422);
    }

    $fileExt = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($fileExt, ['csv', 'xlsx', 'xls'], true)) {
        fail('Only CSV, XLSX, and XLS files are supported', 422);
    }

    if ($fileExt === 'csv') {
        $sheetRows = readCsvRowsFromFile((string)$file['tmp_name']);
    } elseif ($fileExt === 'xlsx') {
        $sheetRows = readSpreadsheetRowsFromFile((string)$file['tmp_name'], 'xlsx');
    } else {
        $sheetRows = readSpreadsheetRowsFromFile((string)$file['tmp_name'], 'xls');
    }

    if (!$sheetRows || !isset($sheetRows[0]) || !is_array($sheetRows[0])) {
        fail('Header row is missing in the uploaded file', 422);
    }

    $header = normalizeCsvHeader($sheetRows[0]);
    $required = [
        'student_id', 'book_mark', 'assignment_mark', 'quiz_mark',
        'mid_exam_mark', 'final_exam_mark', 'attendance_percent', 'remark'
    ];
    foreach ($required as $col) {
        if (!in_array($col, $header, true)) {
            fail('Missing required column: ' . $col, 422);
        }
    }
    $idx = array_flip($header);

    $allowedStudentIds = loadEligibleStudentIds($pdo, $classId);
    $weights = loadWeights($pdo, $classId, $courseId, $termId);
    $maxByComponent = [
        'book_mark' => (float)$weights['book_weight'],
        'assignment_mark' => (float)$weights['assignment_weight'],
        'quiz_mark' => (float)$weights['quiz_weight'],
        'mid_exam_mark' => (float)$weights['mid_exam_weight'],
        'final_exam_mark' => (float)$weights['final_exam_weight'],
        'attendance_percent' => (float)$weights['attendance_weight']
    ];
    $errors = [];
    $rows = [];
    $seen = [];
    $line = 1;
    foreach ($sheetRows as $i => $row) {
        if ($i === 0) {
            continue;
        }
        $line++;
        if (!is_array($row)) {
            continue;
        }
        if (count($row) === 1 && trim((string)$row[0]) === '') {
            continue;
        }

        $studentId = (int)($row[$idx['student_id']] ?? 0);
        if ($studentId <= 0) {
            $errors[] = "Line {$line}: invalid student_id";
            continue;
        }
        if (isset($seen[$studentId])) {
            $errors[] = "Line {$line}: duplicate student_id {$studentId}";
            continue;
        }
        $seen[$studentId] = true;
        if (!isset($allowedStudentIds[$studentId])) {
            $errors[] = "Line {$line}: student {$studentId} is not active in this class";
            continue;
        }

        $book = parseScoreWithinWeight($row[$idx['book_mark']] ?? null, $maxByComponent['book_mark'], true);
        $assignment = parseScoreWithinWeight($row[$idx['assignment_mark']] ?? null, $maxByComponent['assignment_mark'], true);
        $quiz = parseScoreWithinWeight($row[$idx['quiz_mark']] ?? null, $maxByComponent['quiz_mark'], true);
        $mid = parseScoreWithinWeight($row[$idx['mid_exam_mark']] ?? null, $maxByComponent['mid_exam_mark'], true);
        $final = parseScoreWithinWeight($row[$idx['final_exam_mark']] ?? null, $maxByComponent['final_exam_mark'], true);
        $attendance = parseScoreWithinWeight($row[$idx['attendance_percent']] ?? null, $maxByComponent['attendance_percent'], true, true);
        $lineErrors = [];
        foreach ([
            $book['error'],
            $assignment['error'],
            $quiz['error'],
            $mid['error'],
            $final['error'],
            $attendance['error']
        ] as $err) {
            if ($err !== null) {
                $lineErrors[] = $err;
            }
        }
        if ($lineErrors) {
            $errors[] = "Line {$line}: " . implode('; ', $lineErrors);
            continue;
        }

        $parsed = [
            'student_id' => $studentId,
            'book_mark' => $book['value'],
            'assignment_mark' => $assignment['value'],
            'quiz_mark' => $quiz['value'],
            'mid_exam_mark' => $mid['value'],
            'final_exam_mark' => $final['value'],
            'attendance_percent' => $attendance['value'],
            'remark' => trim((string)($row[$idx['remark']] ?? ''))
        ];
        $rows[] = $parsed;
    }

    if (count($rows) > 5000) {
        fail('Imported file contains too many rows (max 5000)', 422);
    }

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'row_count' => count($rows),
        'errors' => $errors
    ]);
}

function saveMarks(PDO $pdo, array $ctx) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Method not allowed', 405);
    }
    if (!SecurityHelper::verifyCSRFToken($_POST['csrf'] ?? '')) {
        fail('Invalid CSRF token', 403);
    }

    $classId = (int)($_POST['class_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    $contextVersion = trim((string)($_POST['context_version'] ?? ''));
    $rows = json_decode((string)($_POST['rows'] ?? '[]'), true);
    $weights = json_decode((string)($_POST['weights'] ?? '{}'), true);

    if (!$classId || !$courseId || !$termId) {
        fail('class_id, course_id, and term_id are required');
    }
    if (!termExists($pdo, $termId)) {
        fail('Selected term does not exist', 422);
    }
    if (!canAccessClassCourse($pdo, $ctx, $classId, $courseId)) {
        fail('Access denied for selected class/course', 403);
    }
    if (($ctx['portal_role'] ?? '') !== 'teacher') {
        fail('Only teachers can save marks', 403);
    }
    if (!is_array($rows)) {
        fail('rows must be a valid JSON array');
    }
    if (!is_array($weights)) {
        fail('weights must be a valid JSON object');
    }
    if (count($rows) === 0) {
        fail('No student rows were submitted', 422);
    }
    if (count($rows) > 5000) {
        fail('Too many rows submitted at once', 422);
    }

    $duplicateStudentIds = findDuplicateStudentIds($rows);
    if ($duplicateStudentIds) {
        fail('Duplicate student rows detected: ' . implode(', ', $duplicateStudentIds), 422);
    }

    $allowedStudentIds = loadEligibleStudentIds($pdo, $classId);
    if (!$allowedStudentIds) {
        fail('No active students found for this class', 422);
    }

    $weights = sanitizeWeights($weights);
    $weightSum = array_sum($weights);
    if ($weightSum <= 0) {
        fail('Total weight must be greater than 0', 422);
    }
    $maxByComponent = [
        'book_mark' => (float)$weights['book_weight'],
        'assignment_mark' => (float)$weights['assignment_weight'],
        'quiz_mark' => (float)$weights['quiz_weight'],
        'mid_exam_mark' => (float)$weights['mid_exam_weight'],
        'final_exam_mark' => (float)$weights['final_exam_weight'],
        'attendance_percent' => (float)$weights['attendance_weight']
    ];
    $submittedStudentIds = [];
    foreach ($rows as $row) {
        $studentId = (int)($row['student_id'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }
        if (!isset($allowedStudentIds[$studentId])) {
            fail('Student ' . $studentId . ' is not an active member of this class', 422);
        }
        $submittedStudentIds[$studentId] = true;
    }
    if (!$submittedStudentIds) {
        fail('No valid student rows to save', 422);
    }

    $currentVersion = getCurrentContextVersion($pdo, $classId, $courseId, $termId);
    if ($contextVersion !== '' && $currentVersion !== null && $contextVersion !== $currentVersion) {
        fail('This marklist was changed by another user. Reload first to avoid overwriting data.', 409);
    }

    $finalizedStmt = $pdo->prepare("
        SELECT student_id
        FROM student_course_marks_mvp
        WHERE class_id = ? AND course_id = ? AND term_id = ? AND is_finalized = 1
    ");
    $finalizedStmt->execute([$classId, $courseId, $termId]);
    $finalizedStudentIds = array_map('intval', $finalizedStmt->fetchAll(PDO::FETCH_COLUMN));
    if ($finalizedStudentIds) {
        $submittedFinalized = [];
        foreach ($finalizedStudentIds as $sid) {
            if (isset($submittedStudentIds[$sid])) {
                $submittedFinalized[] = $sid;
            }
        }
        if ($submittedFinalized) {
            fail('Cannot edit finalized mark rows. Ask admin to unfinalize first.', 409);
        }
    }

    $adminId = null;
    $portalUserId = (int)$ctx['portal_user_id'];
    $hasAuditTable = tableExists($pdo, 'mark_entry_audit_mvp');
    $requestId = generateRequestId();
    $existingMarksMap = loadExistingMarksMap($pdo, $classId, $courseId, $termId, array_keys($submittedStudentIds));

    $upsertWeights = $pdo->prepare("
        INSERT INTO mark_weight_settings_mvp (
            class_id, course_id, term_id,
            book_weight, assignment_weight, quiz_weight, mid_exam_weight, final_exam_weight, attendance_weight,
            entered_by_admin_id, entered_by_portal_user_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            book_weight = VALUES(book_weight),
            assignment_weight = VALUES(assignment_weight),
            quiz_weight = VALUES(quiz_weight),
            mid_exam_weight = VALUES(mid_exam_weight),
            final_exam_weight = VALUES(final_exam_weight),
            attendance_weight = VALUES(attendance_weight),
            entered_by_admin_id = VALUES(entered_by_admin_id),
            entered_by_portal_user_id = VALUES(entered_by_portal_user_id)
    ");

    $upsertMarks = $pdo->prepare("
        INSERT INTO student_course_marks_mvp (
            class_id, course_id, term_id, student_id,
            book_mark, assignment_mark, quiz_mark, mid_exam_mark, final_exam_mark,
            continuous_mark, exam_mark, total_mark, attendance_percent,
            grade_letter, remark, is_finalized, entered_by_admin_id, entered_by_portal_user_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, 0, ?, ?)
        ON DUPLICATE KEY UPDATE
            book_mark = VALUES(book_mark),
            assignment_mark = VALUES(assignment_mark),
            quiz_mark = VALUES(quiz_mark),
            mid_exam_mark = VALUES(mid_exam_mark),
            final_exam_mark = VALUES(final_exam_mark),
            continuous_mark = VALUES(continuous_mark),
            exam_mark = VALUES(exam_mark),
            total_mark = VALUES(total_mark),
            attendance_percent = VALUES(attendance_percent),
            grade_letter = NULL,
            remark = VALUES(remark),
            entered_by_admin_id = VALUES(entered_by_admin_id),
            entered_by_portal_user_id = VALUES(entered_by_portal_user_id)
    ");

    $insertAudit = null;
    if ($hasAuditTable) {
        $insertAudit = $pdo->prepare("
            INSERT INTO mark_entry_audit_mvp (
                request_id, class_id, course_id, term_id, student_id,
                action_type, changed_fields, before_payload, after_payload,
                changed_by_admin_id, changed_by_portal_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    }

    $affected = 0;
    $auditCount = 0;
    $pdo->beginTransaction();
    try {
        $upsertWeights->execute([
            $classId, $courseId, $termId,
            $weights['book_weight'],
            $weights['assignment_weight'],
            $weights['quiz_weight'],
            $weights['mid_exam_weight'],
            $weights['final_exam_weight'],
            $weights['attendance_weight'],
            $adminId,
            $portalUserId
        ]);

        foreach ($rows as $row) {
            $studentId = (int)($row['student_id'] ?? 0);
            if (!$studentId) {
                continue;
            }

            $book = validateScoreWithinWeight($row['book_mark'] ?? null, $maxByComponent['book_mark'], 'Book mark', false);
            $assignment = validateScoreWithinWeight($row['assignment_mark'] ?? null, $maxByComponent['assignment_mark'], 'Assignment mark', false);
            $quiz = validateScoreWithinWeight($row['quiz_mark'] ?? null, $maxByComponent['quiz_mark'], 'Quiz mark', false);
            $mid = validateScoreWithinWeight($row['mid_exam_mark'] ?? null, $maxByComponent['mid_exam_mark'], 'Mid exam mark', false);
            $final = validateScoreWithinWeight($row['final_exam_mark'] ?? null, $maxByComponent['final_exam_mark'], 'Final exam mark', false);
            $attendance = validateScoreWithinWeight($row['attendance_percent'] ?? null, $maxByComponent['attendance_percent'], 'Attendance mark', true);
            if ($attendance === null) {
                $attendance = 0.00;
            }

            $scores = [
                'book' => $book,
                'assignment' => $assignment,
                'quiz' => $quiz,
                'mid_exam' => $mid,
                'final_exam' => $final,
                'attendance' => $attendance
            ];

            $continuous = round($book + $assignment + $quiz, 2);
            $exam = round($mid + $final, 2);
            $total = calculateComponentTotal($scores);
            $remark = trim((string)($row['remark'] ?? ''));
            if ($remark === '') {
                $remark = null;
            }

            $afterPayload = [
                'book_mark' => $book,
                'assignment_mark' => $assignment,
                'quiz_mark' => $quiz,
                'mid_exam_mark' => $mid,
                'final_exam_mark' => $final,
                'continuous_mark' => $continuous,
                'exam_mark' => $exam,
                'total_mark' => $total,
                'attendance_percent' => $attendance,
                'remark' => $remark
            ];
            $beforePayload = $existingMarksMap[$studentId] ?? null;
            $changedFields = diffPayloadFields($beforePayload, $afterPayload);
            if (!$changedFields) {
                continue;
            }

            $upsertMarks->execute([
                $classId,
                $courseId,
                $termId,
                $studentId,
                $book,
                $assignment,
                $quiz,
                $mid,
                $final,
                $continuous,
                $exam,
                $total,
                $attendance,
                $remark,
                $adminId,
                $portalUserId
            ]);

            if ($insertAudit) {
                $insertAudit->execute([
                    $requestId,
                    $classId,
                    $courseId,
                    $termId,
                    $studentId,
                    $beforePayload ? 'update' : 'insert',
                    json_encode(array_values($changedFields)),
                    $beforePayload ? json_encode($beforePayload) : null,
                    json_encode($afterPayload),
                    $adminId,
                    $portalUserId
                ]);
                $auditCount++;
            }
            $existingMarksMap[$studentId] = $afterPayload;
            $affected++;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $latestVersion = getCurrentContextVersion($pdo, $classId, $courseId, $termId);
    echo json_encode([
        'success' => true,
        'message' => 'Marks and weights saved successfully',
        'weights' => $weights,
        'affected_rows' => $affected,
        'audit_rows' => $auditCount,
        'context_version' => $latestVersion
    ]);
}

function loadWeights(PDO $pdo, $classId, $courseId, $termId) {
    $defaults = [
        'book_weight' => 10.0,
        'assignment_weight' => 10.0,
        'quiz_weight' => 10.0,
        'mid_exam_weight' => 20.0,
        'final_exam_weight' => 40.0,
        'attendance_weight' => 10.0
    ];

    $stmt = $pdo->prepare("
        SELECT
            book_weight, assignment_weight, quiz_weight, mid_exam_weight, final_exam_weight, attendance_weight
        FROM mark_weight_settings_mvp
        WHERE class_id = ? AND course_id = ? AND term_id = ?
        LIMIT 1
    ");
    $stmt->execute([$classId, $courseId, $termId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $defaults;
    }

    return [
        'book_weight' => (float)$row['book_weight'],
        'assignment_weight' => (float)$row['assignment_weight'],
        'quiz_weight' => (float)$row['quiz_weight'],
        'mid_exam_weight' => (float)$row['mid_exam_weight'],
        'final_exam_weight' => (float)$row['final_exam_weight'],
        'attendance_weight' => (float)$row['attendance_weight']
    ];
}

function sanitizeWeights(array $weights) {
    $normalized = [
        'book_weight' => normalizeWeight($weights['book_weight'] ?? 10),
        'assignment_weight' => normalizeWeight($weights['assignment_weight'] ?? 10),
        'quiz_weight' => normalizeWeight($weights['quiz_weight'] ?? 10),
        'mid_exam_weight' => normalizeWeight($weights['mid_exam_weight'] ?? 20),
        'final_exam_weight' => normalizeWeight($weights['final_exam_weight'] ?? 40),
        'attendance_weight' => normalizeWeight($weights['attendance_weight'] ?? 10)
    ];
    return $normalized;
}

function normalizeWeight($raw) {
    $v = (float)$raw;
    if ($v < 0) $v = 0;
    if ($v > 1000) $v = 1000;
    return round($v, 2);
}

function normalizeScore($raw, $max, $nullable = false) {
    if ($raw === null || $raw === '') {
        return $nullable ? null : 0.00;
    }
    $num = (float)$raw;
    if ($num < 0) $num = 0;
    if ($num > $max) $num = $max;
    return round($num, 2);
}

function validateScoreWithinWeight($raw, float $max, string $label, bool $nullable = false) {
    $parsed = parseScoreWithinWeight($raw, $max, true, $nullable);
    if ($parsed['error'] !== null) {
        fail($label . ': ' . $parsed['error'], 422);
    }
    return $parsed['value'];
}

function parseScoreWithinWeight($raw, float $max, bool $allowBlankAsZero = true, bool $nullable = false): array {
    if ($raw === null || $raw === '') {
        if ($nullable) {
            return ['value' => null, 'error' => null];
        }
        if ($allowBlankAsZero) {
            return ['value' => 0.00, 'error' => null];
        }
        return ['value' => null, 'error' => 'value is required'];
    }

    if (!is_numeric($raw)) {
        return ['value' => null, 'error' => 'must be numeric'];
    }
    $num = (float)$raw;
    if ($num < 0) {
        return ['value' => null, 'error' => 'cannot be negative'];
    }
    if ($num > $max) {
        return ['value' => null, 'error' => 'cannot be greater than configured weight (' . round($max, 2) . ')'];
    }
    return ['value' => round($num, 2), 'error' => null];
}

function calculateComponentTotal(array $scores): float {
    $total = 0.0;
    foreach ($scores as $value) {
        $total += (float)$value;
    }
    return round($total, 2);
}

function canAccessClassCourse(PDO $pdo, array $ctx, $classId, $courseId) {
    if (($ctx['portal_role'] ?? '') !== 'teacher') {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT 1
        FROM course_teachers
        WHERE teacher_id = ? AND class_id = ? AND course_id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([(int)$ctx['teacher_id'], $classId, $courseId]);
    return (bool)$stmt->fetchColumn();
}

function termExists(PDO $pdo, int $termId): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM academic_terms_mvp WHERE id = ? LIMIT 1");
    $stmt->execute([$termId]);
    return (bool)$stmt->fetchColumn();
}

function loadEligibleStudentIds(PDO $pdo, int $classId): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT student_id
        FROM class_enrollments
        WHERE class_id = ? AND status = 'active'
    ");
    $stmt->execute([$classId]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
        $sid = (int)$sid;
        if ($sid > 0) {
            $map[$sid] = true;
        }
    }
    return $map;
}

function findDuplicateStudentIds(array $rows): array {
    $seen = [];
    $dups = [];
    foreach ($rows as $row) {
        $studentId = (int)($row['student_id'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }
        if (isset($seen[$studentId])) {
            $dups[$studentId] = true;
            continue;
        }
        $seen[$studentId] = true;
    }
    return array_keys($dups);
}

function getCurrentContextVersion(PDO $pdo, int $classId, int $courseId, int $termId): ?string {
    $stmt = $pdo->prepare("
        SELECT MAX(updated_at)
        FROM student_course_marks_mvp
        WHERE class_id = ? AND course_id = ? AND term_id = ?
    ");
    $stmt->execute([$classId, $courseId, $termId]);
    $value = $stmt->fetchColumn();
    if (!$value) {
        return null;
    }
    return (string)$value;
}

function loadExistingMarksMap(PDO $pdo, int $classId, int $courseId, int $termId, array $studentIds): array {
    $cleanIds = [];
    foreach ($studentIds as $sid) {
        $sid = (int)$sid;
        if ($sid > 0) {
            $cleanIds[] = $sid;
        }
    }
    if (!$cleanIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $sql = "
        SELECT
            student_id,
            book_mark,
            assignment_mark,
            quiz_mark,
            mid_exam_mark,
            final_exam_mark,
            continuous_mark,
            exam_mark,
            total_mark,
            attendance_percent,
            remark
        FROM student_course_marks_mvp
        WHERE class_id = ? AND course_id = ? AND term_id = ? AND student_id IN ($placeholders)
    ";
    $params = array_merge([$classId, $courseId, $termId], $cleanIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)$row['student_id'];
        $map[$sid] = [
            'book_mark' => (float)$row['book_mark'],
            'assignment_mark' => (float)$row['assignment_mark'],
            'quiz_mark' => (float)$row['quiz_mark'],
            'mid_exam_mark' => (float)$row['mid_exam_mark'],
            'final_exam_mark' => (float)$row['final_exam_mark'],
            'continuous_mark' => (float)$row['continuous_mark'],
            'exam_mark' => (float)$row['exam_mark'],
            'total_mark' => (float)$row['total_mark'],
            'attendance_percent' => $row['attendance_percent'] === null ? null : (float)$row['attendance_percent'],
            'remark' => $row['remark'] === null ? null : (string)$row['remark']
        ];
    }
    return $map;
}

function diffPayloadFields(?array $before, array $after): array {
    if ($before === null) {
        return array_keys($after);
    }
    $changed = [];
    foreach ($after as $field => $value) {
        $beforeValue = $before[$field] ?? null;
        if (payloadValueDiffers($beforeValue, $value)) {
            $changed[] = $field;
        }
    }
    return $changed;
}

function payloadValueDiffers($before, $after): bool {
    if ($before === null || $after === null) {
        return $before !== $after;
    }
    if (is_numeric($before) || is_numeric($after)) {
        return round((float)$before, 2) !== round((float)$after, 2);
    }
    return trim((string)$before) !== trim((string)$after);
}

function generateRequestId(): string {
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return sha1(uniqid('', true));
    }
}

function requireContextIds(array $src): array {
    $classId = (int)($src['class_id'] ?? 0);
    $courseId = (int)($src['course_id'] ?? 0);
    $termId = (int)($src['term_id'] ?? 0);
    if (!$classId || !$courseId || !$termId) {
        fail('class_id, course_id, and term_id are required', 422);
    }
    return [$classId, $courseId, $termId];
}

function loadActiveStudents(PDO $pdo, int $classId): array {
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

function streamCsv(string $filename, array $headers, array $rows): void {
    if (function_exists('header_remove')) {
        header_remove('Content-Type');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Excel on Windows needs UTF-8 BOM to render non-Latin text (e.g., Amharic) correctly.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function csvNumber($value): string {
    if ($value === null || $value === '') {
        return '';
    }
    return number_format((float)$value, 2, '.', '');
}

function normalizeCsvHeader(array $header): array {
    $normalized = [];
    foreach ($header as $col) {
        $key = (string)$col;
        // Remove UTF-8 BOM and other invisible control chars that break header matching.
        $key = preg_replace('/^\xEF\xBB\xBF/', '', $key);
        $key = preg_replace('/[\x00-\x1F\x7F]/u', '', $key);
        $key = strtolower(trim($key));
        $key = str_replace([' ', '-'], '_', $key);
        $normalized[] = $key;
    }
    return $normalized;
}

function loadContextNames(PDO $pdo, int $classId, int $courseId, int $termId): array {
    $classStmt = $pdo->prepare("SELECT name, grade, section FROM classes WHERE id = ? LIMIT 1");
    $classStmt->execute([$classId]);
    $classRow = $classStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => (string)$classId, 'grade' => '', 'section' => ''];
    $className = trim((string)$classRow['name']);
    if ((string)$classRow['grade'] !== '' || (string)$classRow['section'] !== '') {
        $className .= ' ' . trim((string)$classRow['grade'] . ' ' . (string)$classRow['section']);
    }

    $courseStmt = $pdo->prepare("SELECT name, code FROM courses WHERE id = ? LIMIT 1");
    $courseStmt->execute([$courseId]);
    $courseRow = $courseStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => (string)$courseId, 'code' => ''];
    $courseName = trim((string)$courseRow['name']);
    if ((string)$courseRow['code'] !== '') {
        $courseName .= ' ' . trim((string)$courseRow['code']);
    }

    $termStmt = $pdo->prepare("SELECT name FROM academic_terms_mvp WHERE id = ? LIMIT 1");
    $termStmt->execute([$termId]);
    $termName = (string)($termStmt->fetchColumn() ?: ('term_' . $termId));

    return [$className, $courseName, $termName];
}

function slugifyFilenamePart(string $raw): string {
    $value = strtolower(trim($raw));
    if ($value === '') {
        return 'na';
    }
    $value = preg_replace('/[^a-z0-9]+/i', '_', $value);
    $value = trim((string)$value, '_');
    if ($value === '') {
        return 'na';
    }
    if (strlen($value) > 60) {
        $value = substr($value, 0, 60);
    }
    return $value;
}

function readCsvRowsFromFile(string $path): array {
    $rows = [];
    $handle = fopen($path, 'r');
    if (!$handle) {
        fail('Unable to read uploaded CSV file', 422);
    }
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function readSpreadsheetRowsFromFile(string $path, string $ext): array {
    if ($ext === 'xls') {
        fail('Legacy .xls import needs PhpSpreadsheet. Convert file to .xlsx or .csv and import again.', 422);
    }

    $nativeRows = readXlsxRowsNative($path);
    if ($nativeRows) {
        return $nativeRows;
    }

    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
        fail('Excel import requires PhpSpreadsheet. Install it first, or import using CSV.', 422);
    }

    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        return $sheet->toArray('', false, false, false);
    } catch (Throwable $e) {
        fail('Unable to parse Excel file: ' . $e->getMessage(), 422);
    }
}

function readXlsxRowsNative(string $path): array {
    if (!class_exists('ZipArchive')) {
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }

    try {
        $sharedStrings = readXlsxSharedStrings($zip);
        $sheetPath = resolveFirstWorksheetPath($zip);
        if ($sheetPath === '') {
            return [];
        }
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false || trim((string)$sheetXml) === '') {
            return [];
        }

        $sheetDoc = @simplexml_load_string((string)$sheetXml);
        if (!$sheetDoc || !isset($sheetDoc->sheetData)) {
            return [];
        }

        $rows = [];
        foreach ($sheetDoc->sheetData->row as $rowNode) {
            $rowMap = [];
            $maxCol = -1;
            foreach ($rowNode->c as $cell) {
                $ref = (string)($cell['r'] ?? '');
                $colLetters = preg_replace('/\d+/', '', $ref);
                $colIndex = xlsxColumnToIndex($colLetters);
                if ($colIndex < 0) {
                    continue;
                }
                $cellType = (string)($cell['t'] ?? '');
                $value = '';
                if ($cellType === 's') {
                    $sharedIndex = (int)($cell->v ?? 0);
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } elseif ($cellType === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string)$cell->is->t : '';
                } else {
                    $value = isset($cell->v) ? (string)$cell->v : '';
                }
                $rowMap[$colIndex] = $value;
                if ($colIndex > $maxCol) {
                    $maxCol = $colIndex;
                }
            }

            if ($maxCol < 0) {
                $rows[] = [''];
                continue;
            }

            $line = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $line[] = array_key_exists($i, $rowMap) ? $rowMap[$i] : '';
            }
            $rows[] = $line;
        }

        return $rows;
    } finally {
        $zip->close();
    }
}

function readXlsxSharedStrings(ZipArchive $zip): array {
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false || trim((string)$xml) === '') {
        return [];
    }
    $doc = @simplexml_load_string((string)$xml);
    if (!$doc) {
        return [];
    }

    $strings = [];
    foreach ($doc->si as $si) {
        if (isset($si->t)) {
            $strings[] = (string)$si->t;
            continue;
        }
        if (isset($si->r)) {
            $parts = [];
            foreach ($si->r as $run) {
                $parts[] = (string)($run->t ?? '');
            }
            $strings[] = implode('', $parts);
            continue;
        }
        $strings[] = '';
    }
    return $strings;
}

function resolveFirstWorksheetPath(ZipArchive $zip): string {
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    if ($workbookXml === false || trim((string)$workbookXml) === '') {
        return '';
    }
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($relsXml === false || trim((string)$relsXml) === '') {
        return '';
    }

    $workbook = @simplexml_load_string((string)$workbookXml);
    $rels = @simplexml_load_string((string)$relsXml);
    if (!$workbook || !$rels) {
        return '';
    }

    $sheetRelId = '';
    $wbNamespaces = $workbook->getNamespaces(true);
    if (isset($wbNamespaces['r'])) {
        $workbook->registerXPathNamespace('r', $wbNamespaces['r']);
    }
    $sheetNodes = $workbook->xpath('//*[local-name()="sheet"]');
    if ($sheetNodes && isset($sheetNodes[0])) {
        $attrs = $sheetNodes[0]->attributes($wbNamespaces['r'] ?? null, isset($wbNamespaces['r']));
        if ($attrs && isset($attrs['id'])) {
            $sheetRelId = (string)$attrs['id'];
        }
    }
    if ($sheetRelId === '') {
        return 'xl/worksheets/sheet1.xml';
    }

    foreach ($rels->Relationship as $rel) {
        if ((string)($rel['Id'] ?? '') !== $sheetRelId) {
            continue;
        }
        $target = (string)($rel['Target'] ?? '');
        if ($target === '') {
            continue;
        }
        $target = ltrim(str_replace('\\', '/', $target), '/');
        if (strpos($target, 'xl/') === 0) {
            return $target;
        }
        return 'xl/' . $target;
    }

    return 'xl/worksheets/sheet1.xml';
}

function xlsxColumnToIndex(string $letters): int {
    $letters = strtoupper(trim($letters));
    if ($letters === '') {
        return -1;
    }
    $index = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $char = ord($letters[$i]);
        if ($char < 65 || $char > 90) {
            return -1;
        }
        $index = ($index * 26) + ($char - 64);
    }
    return $index - 1;
}
