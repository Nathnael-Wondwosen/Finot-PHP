<?php
/**
 * Smoke test for marklist/result workflow APIs.
 *
 * Example:
 * php scripts/smoke_marklist_result_workflow.php ^
 *   --base-url=https://lms.finoteselamss.org ^
 *   --teacher-username=teacher_user ^
 *   --teacher-password=secret ^
 *   --class-id=1 --course-id=2 --term-id=1 ^
 *   --admin-username=admin_user --admin-password=secret
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from CLI only.\n");
    exit(1);
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "cURL extension is required.\n");
    exit(1);
}

function argValue(string $key): ?string {
    global $argv;
    $prefix = "--{$key}=";
    foreach ($argv as $a) {
        if (strpos($a, $prefix) === 0) {
            return substr($a, strlen($prefix));
        }
    }
    return null;
}

function hasFlag(string $key): bool {
    global $argv;
    return in_array("--{$key}", $argv, true);
}

function out(string $line): void {
    fwrite(STDOUT, $line . PHP_EOL);
}

function failNow(string $line, int $code = 1): void {
    fwrite(STDERR, "[FAIL] {$line}" . PHP_EOL);
    exit($code);
}

function httpRequest(string $method, string $url, string $cookieFile, array $headers = [], $body = null, bool $insecure = false): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if ($insecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        failNow("HTTP request failed: {$err}");
    }
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawHeaders = substr($raw, 0, $headerSize);
    $responseBody = substr($raw, $headerSize);

    return ['status' => $status, 'headers' => $rawHeaders, 'body' => $responseBody];
}

function parseJsonOrFail(string $body, string $context): array {
    $json = json_decode($body, true);
    if (!is_array($json)) {
        failNow("Invalid JSON for {$context}. Body starts with: " . substr($body, 0, 180));
    }
    return $json;
}

function extractCsrfFromPage(string $html, string $varName): ?string {
    $pattern = '/const\s+' . preg_quote($varName, '/') . '\s*=\s*"([^"]+)"/';
    if (preg_match($pattern, $html, $m)) {
        return $m[1];
    }
    return null;
}

function login(string $baseUrl, string $username, string $password, string $cookieFile, bool $insecure): void {
    $loginUrl = rtrim($baseUrl, '/') . '/login.php';
    $payload = http_build_query(['username' => $username, 'password' => $password], '', '&');
    $res = httpRequest('POST', $loginUrl, $cookieFile, ['Content-Type: application/x-www-form-urlencoded'], $payload, $insecure);

    if (!in_array($res['status'], [200, 302, 303], true)) {
        failNow("Login failed with HTTP {$res['status']}");
    }

    if (stripos($res['body'], 'Invalid username or password') !== false) {
        failNow('Login rejected: invalid username/password');
    }
}

$baseUrl = trim((string)argValue('base-url'));
$teacherUser = trim((string)argValue('teacher-username'));
$teacherPass = (string)argValue('teacher-password');
$adminUser = trim((string)argValue('admin-username'));
$adminPass = (string)argValue('admin-password');
$classId = (int)(argValue('class-id') ?? 0);
$courseId = (int)(argValue('course-id') ?? 0);
$termId = (int)(argValue('term-id') ?? 0);
$insecure = hasFlag('insecure');

if ($baseUrl === '' || $teacherUser === '' || $teacherPass === '' || $classId <= 0 || $courseId <= 0 || $termId <= 0) {
    failNow('Missing required args. Required: --base-url, --teacher-username, --teacher-password, --class-id, --course-id, --term-id');
}

out('[INFO] Starting smoke test');
out("[INFO] Base URL: {$baseUrl}");

$teacherCookies = tempnam(sys_get_temp_dir(), 'finot_teacher_cookie_');
if ($teacherCookies === false) {
    failNow('Unable to create temp cookie file');
}

try {
    out('[STEP] Teacher login');
    login($baseUrl, $teacherUser, $teacherPass, $teacherCookies, $insecure);
    out('[PASS] Teacher login succeeded');

    out('[STEP] Load teacher marklist page and extract CSRF');
    $marklistPageUrl = rtrim($baseUrl, '/') . '/portal/teacher/marklist.php';
    $pageRes = httpRequest('GET', $marklistPageUrl, $teacherCookies, [], null, $insecure);
    if ($pageRes['status'] !== 200) {
        failNow("Teacher marklist page returned HTTP {$pageRes['status']}");
    }
    $teacherCsrf = extractCsrfFromPage($pageRes['body'], 'csrfToken');
    if (!$teacherCsrf) {
        failNow('Could not extract teacher csrfToken from marklist page');
    }
    out('[PASS] Teacher CSRF extracted');

    out('[STEP] Marklist bootstrap');
    $bootstrapUrl = rtrim($baseUrl, '/') . '/portal/api/marklist.php?action=bootstrap';
    $bootRes = httpRequest('GET', $bootstrapUrl, $teacherCookies, [], null, $insecure);
    $bootJson = parseJsonOrFail($bootRes['body'], 'marklist bootstrap');
    if (($bootJson['success'] ?? false) !== true) {
        failNow('Marklist bootstrap failed: ' . ($bootJson['message'] ?? 'unknown'));
    }
    out('[PASS] Marklist bootstrap');

    out('[STEP] Marklist students/load context');
    $studentsUrl = rtrim($baseUrl, '/') . '/portal/api/marklist.php?action=students&class_id=' . $classId . '&course_id=' . $courseId . '&term_id=' . $termId;
    $studentsRes = httpRequest('GET', $studentsUrl, $teacherCookies, [], null, $insecure);
    $studentsJson = parseJsonOrFail($studentsRes['body'], 'marklist students');
    if (($studentsJson['success'] ?? false) !== true) {
        failNow('Marklist students failed: ' . ($studentsJson['message'] ?? 'unknown'));
    }
    out('[PASS] Marklist students');
    out('[INFO] Context lock: ' . (($studentsJson['is_locked'] ?? false) ? 'yes' : 'no') . '; reason=' . ($studentsJson['lock_reason'] ?? 'none'));
    out('[INFO] Weight frozen: ' . (($studentsJson['weight_frozen'] ?? false) ? 'yes' : 'no'));

    out('[STEP] Marklist save conflict-check with stale context');
    $staleVersion = '1970-01-01 00:00:00';
    $saveForm = http_build_query([
        'action' => 'save_marks',
        'csrf' => $teacherCsrf,
        'class_id' => $classId,
        'course_id' => $courseId,
        'term_id' => $termId,
        'context_version' => $staleVersion,
        'rows' => json_encode([['student_id' => 0]]),
        'weights' => json_encode([
            'book_weight' => 10,
            'assignment_weight' => 10,
            'quiz_weight' => 10,
            'mid_exam_weight' => 20,
            'final_exam_weight' => 40,
            'attendance_weight' => 10
        ])
    ], '', '&');
    $saveRes = httpRequest(
        'POST',
        rtrim($baseUrl, '/') . '/portal/api/marklist.php',
        $teacherCookies,
        ['Content-Type: application/x-www-form-urlencoded'],
        $saveForm,
        $insecure
    );
    $saveJson = parseJsonOrFail($saveRes['body'], 'marklist save conflict');
    if (($saveJson['success'] ?? true) === true) {
        out('[WARN] Save conflict-check did not fail; verify context/version or lock behavior manually.');
    } else {
        out('[PASS] Save conflict-check returned expected failure: ' . ($saveJson['message'] ?? ''));
    }

    if ($adminUser !== '' && $adminPass !== '') {
        $adminCookies = tempnam(sys_get_temp_dir(), 'finot_admin_cookie_');
        if ($adminCookies === false) {
            failNow('Unable to create admin temp cookie file');
        }
        try {
            out('[STEP] Admin login');
            login($baseUrl, $adminUser, $adminPass, $adminCookies, $insecure);
            out('[PASS] Admin login succeeded');

            out('[STEP] Load admin result page and extract CSRF');
            $resultPageUrl = rtrim($baseUrl, '/') . '/result_summary_mvp.php';
            $resultPageRes = httpRequest('GET', $resultPageUrl, $adminCookies, [], null, $insecure);
            if ($resultPageRes['status'] !== 200) {
                failNow("Admin result page returned HTTP {$resultPageRes['status']}");
            }
            $adminCsrf = extractCsrfFromPage($resultPageRes['body'], 'csrf');
            if (!$adminCsrf) {
                failNow('Could not extract admin csrf from result summary page');
            }
            out('[PASS] Admin CSRF extracted');

            out('[STEP] Get readiness');
            $readyUrl = rtrim($baseUrl, '/') . '/api/result_summary_mvp.php?action=get_readiness&term_id=' . $termId;
            $readyRes = httpRequest('GET', $readyUrl, $adminCookies, [], null, $insecure);
            $readyJson = parseJsonOrFail($readyRes['body'], 'get_readiness');
            if (($readyJson['success'] ?? false) !== true) {
                failNow('Readiness failed: ' . ($readyJson['message'] ?? 'unknown'));
            }
            out('[PASS] Readiness endpoint');

            out('[STEP] Get homeroom status');
            $hStatusUrl = rtrim($baseUrl, '/') . '/api/result_summary_mvp.php?action=get_homeroom_status&class_id=' . $classId . '&term_id=' . $termId;
            $hRes = httpRequest('GET', $hStatusUrl, $adminCookies, [], null, $insecure);
            $hJson = parseJsonOrFail($hRes['body'], 'get_homeroom_status');
            if (($hJson['success'] ?? false) !== true) {
                failNow('Homeroom status failed: ' . ($hJson['message'] ?? 'unknown'));
            }
            out('[PASS] Homeroom status endpoint');

            out('[INFO] Homeroom status=' . ($hJson['status'] ?? 'unknown'));
            out('[INFO] Smoke test completed');
        } finally {
            @unlink($adminCookies);
        }
    } else {
        out('[INFO] Admin credentials not supplied; admin checks skipped.');
        out('[INFO] Smoke test completed (teacher-only mode).');
    }
} finally {
    @unlink($teacherCookies);
}

