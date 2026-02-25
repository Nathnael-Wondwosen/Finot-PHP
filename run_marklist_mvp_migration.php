<?php
/**
 * One-time migration runner for Marklist MVP.
 *
 * Usage (CLI):
 *   php run_marklist_mvp_migration.php --key=CHANGE_THIS_KEY
 *
 * Usage (HTTP/cURL):
 *   https://your-domain/run_marklist_mvp_migration.php?key=CHANGE_THIS_KEY
 */

require __DIR__ . '/config.php';

// Set a strong key before running, then delete this file after success.
$migrationKey = 'finot27';
$migrationName = '';
$allowedMigrations = [
    'marklist_mvp_migration.sql',
    'marklist_mvp_phase2.sql',
    'marklist_mvp_phase3_weights.sql',
    'marklist_mvp_phase4_summary.sql',
    'marklist_mvp_phase5_audit.sql',
    'marklist_mvp_phase6_yearly_summary.sql',
    'marklist_mvp_phase7_perf.sql'
];

function out($message, $isCli) {
    if ($isCli) {
        echo $message . PHP_EOL;
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $message . "\n";
    }
}

function parseSqlStatements($sql) {
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($char === "'" && !$inDouble && $prev !== '\\') {
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle && $prev !== '\\') {
            $inDouble = !$inDouble;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

$isCli = (php_sapi_name() === 'cli');

if ($migrationKey === 'CHANGE_THIS_KEY') {
    out('Blocked: set $migrationKey in run_marklist_mvp_migration.php first.', $isCli);
    exit(1);
}

if ($isCli) {
    $providedKey = '';
    $migrationName = 'marklist_mvp_migration.sql';
    foreach ($argv as $arg) {
        if (strpos($arg, '--key=') === 0) {
            $providedKey = substr($arg, 6);
        } elseif (strpos($arg, '--migration=') === 0) {
            $migrationName = basename(substr($arg, 12));
        }
    }
} else {
    $providedKey = isset($_GET['key']) ? (string)$_GET['key'] : '';
    $migrationName = isset($_GET['migration']) ? basename((string)$_GET['migration']) : 'marklist_mvp_migration.sql';
}

if (!hash_equals($migrationKey, $providedKey)) {
    if (!$isCli) {
        http_response_code(403);
    }
    out('Unauthorized: invalid migration key.', $isCli);
    exit(1);
}

$migrationFile = __DIR__ . '/database/' . $migrationName;

if (!in_array($migrationName, $allowedMigrations, true)) {
    out('Invalid migration name. Allowed: ' . implode(', ', $allowedMigrations), $isCli);
    exit(1);
}

if (!is_file($migrationFile)) {
    out('Migration file not found: ' . $migrationFile, $isCli);
    exit(1);
}

$sql = file_get_contents($migrationFile);
if ($sql === false || trim($sql) === '') {
    out('Migration file is empty or unreadable.', $isCli);
    exit(1);
}

// Remove UTF-8 BOM if present.
$sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
$statements = parseSqlStatements($sql);

if (count($statements) === 0) {
    out('No SQL statements detected.', $isCli);
    exit(1);
}

try {
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
    out('Migration completed successfully. Executed statements: ' . count($statements), $isCli);
    out('Next step: delete run_marklist_mvp_migration.php for security.', $isCli);
} catch (Throwable $e) {
    if (!$isCli) {
        http_response_code(500);
    }
    out('Migration failed: ' . $e->getMessage(), $isCli);
    exit(1);
}


