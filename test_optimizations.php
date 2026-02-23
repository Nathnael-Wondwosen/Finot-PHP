<?php
/**
 * Test Optimizations Script
 * Verifies that all optimizations are working correctly
 */

require_once 'config.php';
require_once 'includes/cache.php';
require_once 'includes/admin_layout.php';

// Check admin authentication
// if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
//     die('Access denied. Admin login required.');
// }

// Test results array
$tests = [];

// Test 1: Database connection with persistent connections
$start = microtime(true);
try {
    // Test if persistent connections are enabled
    $isPersistent = $pdo->getAttribute(PDO::ATTR_PERSISTENT);
    $tests['database_persistent'] = [
        'name' => 'Persistent Database Connections',
        'status' => $isPersistent ? 'PASS' : 'FAIL',
        'message' => $isPersistent ? 'Persistent connections enabled' : 'Persistent connections disabled'
    ];
} catch (Exception $e) {
    $tests['database_persistent'] = [
        'name' => 'Persistent Database Connections',
        'status' => 'FAIL',
        'message' => 'Error checking persistent connections: ' . $e->getMessage()
    ];
}
$tests['database_persistent']['time'] = round((microtime(true) - $start) * 1000, 2);

// Test 2: Cache functionality
$start = microtime(true);
$cacheKey = 'test_cache_key';
$testData = ['test' => 'data', 'timestamp' => time()];

// Test setting cache
$setResult = cache_set($cacheKey, $testData);

// Test getting cache
$getResult = cache_get($cacheKey, 300);

// Test cache deletion
$deleteResult = cache_delete($cacheKey);

$tests['cache_functionality'] = [
    'name' => 'Cache System',
    'status' => ($setResult && $getResult !== null && $deleteResult) ? 'PASS' : 'FAIL',
    'message' => ($setResult && $getResult !== null && $deleteResult) ? 'Cache system working correctly' : 'Cache system has issues',
    'time' => round((microtime(true) - $start) * 1000, 2)
];

// Test 3: File exists for lazy loading
$start = microtime(true);
$sampleImage = 'uploads/'; // Check if uploads directory exists
$tests['file_system'] = [
    'name' => 'File System Access',
    'status' => is_dir($sampleImage) ? 'PASS' : 'WARN',
    'message' => is_dir($sampleImage) ? 'File system accessible' : 'Uploads directory not found (may be normal)',
    'time' => round((microtime(true) - $start) * 1000, 2)
];

// Test 4: Memory limit
$start = microtime(true);
$memoryLimit = ini_get('memory_limit');
$memoryLimitBytes = returnBytes($memoryLimit);
$tests['memory_limit'] = [
    'name' => 'Memory Configuration',
    'status' => $memoryLimitBytes >= 512 * 1024 * 1024 ? 'PASS' : 'WARN',
    'message' => "Memory limit: $memoryLimit",
    'time' => round((microtime(true) - $start) * 1000, 2)
];

// Test 5: OPcache status
$start = microtime(true);
$opcacheEnabled = function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled'] ?? false;
$tests['opcache'] = [
    'name' => 'OPcache',
    'status' => $opcacheEnabled ? 'PASS' : 'WARN',
    'message' => $opcacheEnabled ? 'OPcache is enabled' : 'OPcache is not enabled (not critical)',
    'time' => round((microtime(true) - $start) * 1000, 2)
];

// Test 6: Compression
$start = microtime(true);
$compressionEnabled = ini_get('zlib.output_compression');
$tests['compression'] = [
    'name' => 'Output Compression',
    'status' => $compressionEnabled ? 'PASS' : 'WARN',
    'message' => $compressionEnabled ? 'Output compression enabled' : 'Output compression disabled',
    'time' => round((microtime(true) - $start) * 1000, 2)
];

// Helper function to convert memory limit to bytes
function returnBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = intval($val);
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Calculate summary
$passed = 0;
$failed = 0;
$warnings = 0;

foreach ($tests as $test) {
    switch ($test['status']) {
        case 'PASS':
            $passed++;
            break;
        case 'FAIL':
            $failed++;
            break;
        case 'WARN':
            $warnings++;
            break;
    }
}

ob_start();
?>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Optimization Test Results</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-green-50 p-4 rounded-lg text-center">
                <div class="text-3xl font-bold text-green-600"><?php echo $passed; ?></div>
                <div class="text-green-800">Tests Passed</div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg text-center">
                <div class="text-3xl font-bold text-yellow-600"><?php echo $warnings; ?></div>
                <div class="text-yellow-800">Warnings</div>
            </div>
            <div class="bg-red-50 p-4 rounded-lg text-center">
                <div class="text-3xl font-bold text-red-600"><?php echo $failed; ?></div>
                <div class="text-red-800">Tests Failed</div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time (ms)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($tests as $test): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $test['name']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($test['status'] === 'PASS'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-check mr-1"></i> PASS
                                </span>
                            <?php elseif ($test['status'] === 'FAIL'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    <i class="fas fa-times mr-1"></i> FAIL
                                </span>
                            <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> WARN
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo $test['message']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $test['time']; ?> ms</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Performance Recommendations</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-medium text-gray-900 mb-2">If All Tests Pass</h3>
                <ul class="text-gray-600 list-disc pl-5 space-y-1">
                    <li>Your system is optimized and running efficiently</li>
                    <li>Continue monitoring performance regularly</li>
                    <li>Run optimizations after major data updates</li>
                    <li>Clear cache when experiencing issues</li>
                </ul>
            </div>
            <div>
                <h3 class="font-medium text-gray-900 mb-2">If Tests Fail or Warn</h3>
                <ul class="text-gray-600 list-disc pl-5 space-y-1">
                    <li>Run the appropriate optimization tools</li>
                    <li>Check database connection settings</li>
                    <li>Verify file permissions for cache directory</li>
                    <li>Review PHP configuration settings</li>
                </ul>
            </div>
        </div>
        
        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <h3 class="font-medium text-blue-800 mb-2">Next Steps</h3>
            <p class="text-blue-700">
                <i class="fas fa-info-circle mr-2"></i>
                <?php if ($failed === 0): ?>
                    All critical optimizations are working correctly. For ongoing maintenance, 
                    visit the <a href="optimization_tools.php" class="text-blue-600 hover:underline">Optimization Tools</a> 
                    dashboard to run periodic optimizations.
                <?php else: ?>
                    Some optimizations need attention. Visit the 
                    <a href="optimization_tools.php" class="text-blue-600 hover:underline">Optimization Tools</a> 
                    dashboard to resolve issues and improve performance.
                <?php endif; ?>
            </p>
        </div>
        
        <div class="flex justify-end mt-6">
            <a href="optimization_tools.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i> Back to Optimization
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo renderAdminLayout('Optimization Test Results - Student Management System', $content);
?>