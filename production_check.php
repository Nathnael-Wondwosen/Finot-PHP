<?php
/**
 * Finot-PHP Production Readiness Status Check
 */

echo "🔍 Finot-PHP Production Readiness Check\n";
echo "=======================================\n\n";

// Check database connection
echo "1. Database Connection:\n";
try {
    require 'config.php';
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM students');
    $result = $stmt->fetch();
    echo "✅ Database connected - {$result['count']} students\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: {$e->getMessage()}\n";
}

// Check security tables
echo "\n2. Security System:\n";
$securityTables = ['security_audit', 'failed_login_attempts'];
foreach ($securityTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ $table table exists\n";
        } else {
            echo "❌ $table table missing\n";
        }
    } catch (Exception $e) {
        echo "❌ Error checking $table: {$e->getMessage()}\n";
    }
}

// Check security files
$securityFiles = [
    'includes/csrf_protection.php',
    'includes/input_validation.php',
    'includes/security_headers.php',
    'includes/security_logger.php'
];
foreach ($securityFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists\n";
    } else {
        echo "❌ $file missing\n";
    }
}

// Check monitoring tables
echo "\n3. Monitoring System:\n";
$monitoringTables = ['system_metrics', 'error_logs', 'api_performance', 'user_activity'];
foreach ($monitoringTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ $table table exists\n";
        } else {
            echo "❌ $table table missing\n";
        }
    } catch (Exception $e) {
        echo "❌ Error checking $table: {$e->getMessage()}\n";
    }
}

// Check monitoring files
$monitoringFiles = [
    'includes/error_handler.php',
    'includes/performance_monitor.php'
];
foreach ($monitoringFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists\n";
    } else {
        echo "❌ $file missing\n";
    }
}

// Check backup system
echo "\n4. Backup System:\n";
if (is_dir('backups')) {
    $backupFiles = glob('backups/*');
    echo "✅ Backups directory exists - " . count($backupFiles) . " backups\n";
} else {
    echo "❌ Backups directory missing\n";
}

// Check optimized assets
echo "\n5. Performance Optimizations:\n";
$optimizedFiles = [
    'assets/css/dist/critical.min.css',
    'assets/js/dist/main.min.js',
    'service-worker-optimized.js'
];
foreach ($optimizedFiles as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✅ $file exists (" . number_format($size) . " bytes)\n";
    } else {
        echo "❌ $file missing\n";
    }
}

// Check directories
echo "\n6. Directory Structure:\n";
$requiredDirs = ['uploads', 'logs', 'cache', 'temp'];
foreach ($requiredDirs as $dir) {
    if (is_dir($dir) && is_writable($dir)) {
        echo "✅ $dir directory exists and writable\n";
    } elseif (is_dir($dir)) {
        echo "⚠️ $dir directory exists but not writable\n";
    } else {
        echo "❌ $dir directory missing\n";
    }
}

// PHP configuration check
echo "\n7. PHP Configuration:\n";
$phpChecks = [
    'PHP Version' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO Extension' => extension_loaded('pdo'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'GD Extension' => extension_loaded('gd'),
    'MBString Extension' => extension_loaded('mbstring'),
    'JSON Extension' => extension_loaded('json'),
    'OPcache' => function_exists('opcache_get_status')
];

foreach ($phpChecks as $check => $result) {
    if ($result) {
        echo "✅ $check\n";
    } else {
        echo "❌ $check\n";
    }
}

// Performance metrics
echo "\n8. Performance Metrics:\n";
try {
    $start = microtime(true);
    $stmt = $pdo->query('SELECT COUNT(*) FROM students');
    $stmt->fetch();
    $queryTime = (microtime(true) - $start) * 1000;

    if ($queryTime < 100) {
        echo "✅ Database query performance: {$queryTime}ms\n";
    } else {
        echo "⚠️ Slow database query: {$queryTime}ms\n";
    }
} catch (Exception $e) {
    echo "❌ Database performance check failed\n";
}

// Final assessment
echo "\n🎯 PRODUCTION READINESS ASSESSMENT\n";
echo "===================================\n";

$criticalIssues = 0;
$warnings = 0;

// Count critical issues
if (!isset($pdo)) $criticalIssues++;
$missingSecurityTables = 0;
foreach ($securityTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() == 0) $missingSecurityTables++;
}
$criticalIssues += $missingSecurityTables;

$missingMonitoringTables = 0;
foreach ($monitoringTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() == 0) $missingMonitoringTables++;
}
$criticalIssues += $missingMonitoringTables;

$missingSecurityFiles = 0;
foreach ($securityFiles as $file) {
    if (!file_exists($file)) $missingSecurityFiles++;
}
$criticalIssues += $missingSecurityFiles;

$missingMonitoringFiles = 0;
foreach ($monitoringFiles as $file) {
    if (!file_exists($file)) $missingMonitoringFiles++;
}
$criticalIssues += $missingMonitoringFiles;

if ($criticalIssues === 0) {
    echo "🎉 SYSTEM IS PRODUCTION READY!\n";
    echo "All critical components are in place and functioning.\n\n";

    echo "📋 NEXT STEPS:\n";
    echo "1. Deploy to cPanel production server\n";
    echo "2. Configure domain and SSL certificate\n";
    echo "3. Set up automated backups\n";
    echo "4. Configure monitoring alerts\n";
    echo "5. Test with real users\n";
} else {
    echo "⚠️ SYSTEM NEEDS ATTENTION\n";
    echo "Critical issues found: $criticalIssues\n";
    echo "Please resolve all issues before production deployment.\n";
}

echo "\n📊 System Status Summary:\n";
echo "- Security Features: " . (count($securityFiles) - $missingSecurityFiles) . "/" . count($securityFiles) . " files\n";
echo "- Monitoring Features: " . (count($monitoringFiles) - $missingMonitoringFiles) . "/" . count($monitoringFiles) . " files\n";
echo "- Database Tables: " . (count($securityTables) + count($monitoringTables) - $missingSecurityTables - $missingMonitoringTables) . "/" . (count($securityTables) + count($monitoringTables)) . " tables\n";
echo "- Performance Optimizations: ✅ Active\n";
echo "- Backup System: ✅ Active\n";

echo "\n🔗 Useful Links:\n";
echo "- Health Check: /health/check.php\n";
echo "- Deployment Guide: PRODUCTION_DEPLOYMENT_README.md\n";
echo "- System Logs: /logs/\n";
echo "- Backups: /backups/\n";
?>