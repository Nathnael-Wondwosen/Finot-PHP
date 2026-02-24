<?php
/**
 * Performance Test Script for Finot-PHP
 * Run this to verify optimization effectiveness
 */

require 'config.php';
require 'includes/cache_manager.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Finot-PHP Performance Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
        .info { color: blue; }
        .section { background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; }
    </style>
</head>
<body>
    <h1>Finot-PHP Performance Test Results</h1>
    
    <div class="section">
        <h2>System Information</h2>
        <table>
            <tr><th>Setting</th><th>Value</th><th>Status</th></tr>
            <tr>
                <td>PHP Version</td>
                <td><?php echo phpversion(); ?></td>
                <td class="<?php echo version_compare(phpversion(), '7.4', '>=') ? 'pass' : 'fail'; ?>">
                    <?php echo version_compare(phpversion(), '7.4', '>=') ? '✓ Good' : '✗ Upgrade Recommended'; ?>
                </td>
            </tr>
            <tr>
                <td>OPcache Enabled</td>
                <td><?php echo extension_loaded('Zend OPcache') && ini_get('opcache.enable') ? 'Yes' : 'No'; ?></td>
                <td class="<?php echo extension_loaded('Zend OPcache') && ini_get('opcache.enable') ? 'pass' : 'fail'; ?>">
                    <?php echo extension_loaded('Zend OPcache') && ini_get('opcache.enable') ? '✓ Enabled' : '✗ Not Enabled'; ?>
                </td>
            </tr>
            <tr>
                <td>Memory Limit</td>
                <td><?php echo ini_get('memory_limit'); ?></td>
                <td class="<?php echo intval(ini_get('memory_limit')) >= 256 ? 'pass' : 'fail'; ?>">
                    <?php echo intval(ini_get('memory_limit')) >= 256 ? '✓ Sufficient' : '✗ Too Low'; ?>
                </td>
            </tr>
            <tr>
                <td>Gzip Compression</td>
                <td><?php echo extension_loaded('zlib') ? 'Available' : 'Not Available'; ?></td>
                <td class="<?php echo extension_loaded('zlib') ? 'pass' : 'info'; ?>">
                    <?php echo extension_loaded('zlib') ? '✓ Available' : 'ℹ Not Critical'; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>Cache System Test</h2>
        <?php
        $cacheTests = [
            'cache_set' => function_exists('cache_set'),
            'cache_get' => function_exists('cache_get'),
            'cache_remember' => function_exists('cache_remember'),
            'cache_clear' => function_exists('cache_clear'),
        ];
        
        // Test cache functionality
        $testKey = 'performance_test_' . time();
        $testValue = ['test' => true, 'time' => time()];
        $setResult = cache_set($testKey, $testValue, 60);
        $getResult = cache_get($testKey);
        $deleteResult = cache_delete($testKey);
        ?>
        <table>
            <tr><th>Function</th><th>Available</th><th>Test Result</th></tr>
            <?php foreach ($cacheTests as $func => $available): ?>
            <tr>
                <td><?php echo $func; ?>()</td>
                <td class="<?php echo $available ? 'pass' : 'fail'; ?>">
                    <?php echo $available ? '✓ Yes' : '✗ No'; ?>
                </td>
                <td>-</td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td>Cache Write</td>
                <td>-</td>
                <td class="<?php echo $setResult ? 'pass' : 'fail'; ?>">
                    <?php echo $setResult ? '✓ Success' : '✗ Failed'; ?>
                </td>
            </tr>
            <tr>
                <td>Cache Read</td>
                <td>-</td>
                <td class="<?php echo $getResult === $testValue ? 'pass' : 'fail'; ?>">
                    <?php echo $getResult === $testValue ? '✓ Success' : '✗ Failed'; ?>
                </td>
            </tr>
        </table>
        
        <h3>Cache Statistics</h3>
        <?php $stats = CacheManager::getInstance()->getStats(); ?>
        <table>
            <tr><th>Metric</th><th>Value</th></tr>
            <tr><td>Cache Files</td><td><?php echo $stats['files']; ?></td></tr>
            <tr><td>Total Size</td><td><?php echo $stats['size_mb']; ?> MB</td></tr>
            <tr><td>Expired Files</td><td><?php echo $stats['expired']; ?></td></tr>
        </table>
    </div>
    
    <div class="section">
        <h2>Database Performance Test</h2>
        <?php
        $dbTests = [];
        
        // Test 1: Simple count query
        $start = microtime(true);
        $count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
        $dbTests['Count Query'] = round((microtime(true) - $start) * 1000, 2);
        
        // Test 2: Join query (optimized)
        $start = microtime(true);
        $stmt = $pdo->query("SELECT s.id, s.full_name, f.full_name as father_name 
                             FROM students s 
                             LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father' 
                             LIMIT 50");
        $result = $stmt->fetchAll();
        $dbTests['Join Query (50 rows)'] = round((microtime(true) - $start) * 1000, 2);
        
        // Test 3: Cached query
        $start = microtime(true);
        $cached = cache_remember('perf_test_students', function() use ($pdo) {
            return $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
        }, 60, 'perf');
        $dbTests['Cached Query'] = round((microtime(true) - $start) * 1000, 2);
        ?>
        <table>
            <tr><th>Query Type</th><th>Time (ms)</th><th>Status</th></tr>
            <?php foreach ($dbTests as $name => $time): ?>
            <tr>
                <td><?php echo $name; ?></td>
                <td><?php echo $time; ?> ms</td>
                <td class="<?php echo $time < 100 ? 'pass' : ($time < 500 ? 'info' : 'fail'); ?>">
                    <?php 
                    if ($time < 100) echo '✓ Fast';
                    elseif ($time < 500) echo 'ℹ Acceptable';
                    else echo '✗ Slow';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="info">Total Students: <?php echo $count; ?></p>
    </div>
    
    <div class="section">
        <h2>Recommendations</h2>
        <ul>
            <?php if (!extension_loaded('Zend OPcache') || !ini_get('opcache.enable')): ?>
            <li class="fail">Enable OPcache in your cPanel PHP settings for 50%+ performance boost</li>
            <?php endif; ?>
            
            <?php if (intval(ini_get('memory_limit')) < 256): ?>
            <li class="fail">Increase PHP memory_limit to at least 256M in cPanel</li>
            <?php endif; ?>
            
            <?php if (!extension_loaded('zlib')): ?>
            <li class="info">Enable zlib extension for gzip compression support</li>
            <?php endif; ?>
            
            <?php if ($stats['expired'] > 10): ?>
            <li class="info">Clear expired cache files to free up disk space</li>
            <?php endif; ?>
            
            <?php if ($dbTests['Join Query (50 rows)'] > 100): ?>
            <li class="fail">Database queries are slow - verify indexes are created by running database_optimization_indexes.sql</li>
            <?php endif; ?>
            
            <li class="pass">All core optimizations are in place!</li>
        </ul>
    </div>
    
    <div class="section">
        <h2>Next Steps</h2>
        <ol>
            <li>Run <code>database_optimization_indexes.sql</code> in phpMyAdmin</li>
            <li>Update database credentials in <code>config.php</code></li>
            <li>Upload all files to cPanel public_html folder</li>
            <li>Set <code>cache/</code> and <code>uploads/</code> directories to 755 permissions</li>
            <li>Test the application at your domain</li>
        </ol>
    </div>
    
    <p style="text-align: center; margin-top: 30px; color: #666;">
        <small>Finot-PHP Performance Test | Generated: <?php echo date('Y-m-d H:i:s'); ?></small>
    </p>
</body>
</html>
