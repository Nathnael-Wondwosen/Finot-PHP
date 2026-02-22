<?php
/**
 * PHP Code Optimization Script
 * Implements various optimizations to improve PHP execution speed
 */

require_once 'config.php';
require_once 'includes/admin_layout.php';

// Check if user is admin
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    die('Access denied. Admin login required.');
}

ob_start();
?>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">PHP Optimization</h1>
                <p class="text-gray-600 mt-1">Configure PHP settings for better performance</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-cogs text-blue-600 text-xl"></i>
            </div>
        </div>
        
        <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">PHP Optimization</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>This tool configures PHP settings to improve execution speed and system performance. Some settings can only be changed in php.ini and require server restart.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">Runtime Configuration</h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <?php
                    // Check OPcache status
                    $opcacheStatus = 'Not available';
                    $opcacheClass = 'bg-yellow-100 text-yellow-800';
                    $opcacheInstructions = '';
                    
                    if (function_exists('opcache_get_status')) {
                        $status = opcache_get_status();
                        if ($status && isset($status['opcache_enabled']) && $status['opcache_enabled']) {
                            $opcacheStatus = 'Enabled';
                            $opcacheClass = 'bg-green-100 text-green-800';
                        } else {
                            $opcacheStatus = 'Disabled';
                            $opcacheClass = 'bg-red-100 text-red-800';
                            $opcacheInstructions = 'To enable OPcache, edit php.ini and add: zend_extension=php_opcache.dll and opcache.enable=1';
                        }
                    } else {
                        $opcacheInstructions = 'OPcache extension not installed. To install, edit php.ini and add: zend_extension=php_opcache.dll';
                    }
                    ?>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">OPcache Status</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $opcacheClass; ?>">
                                <?php echo $opcacheStatus; ?>
                            </span>
                        </div>
                        
                        <?php if ($opcacheInstructions): ?>
                        <div class="p-3 bg-yellow-50 rounded-md">
                            <p class="text-xs text-yellow-700">
                                <i class="fas fa-info-circle mr-1"></i> <?php echo $opcacheInstructions; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <?php
                        // Set optimal PHP settings for performance (only if session not started)
                        $settingsChanged = [];
                        $settingsNotChanged = [];
                        
                        // These settings can be changed at runtime
                        $runtimeSettings = [
                            'memory_limit' => '512M',
                            'max_execution_time' => 300,
                            'realpath_cache_size' => '4096K',
                            'realpath_cache_ttl' => 600
                        ];
                        
                        foreach ($runtimeSettings as $setting => $value) {
                            if (@ini_set($setting, $value) !== false) {
                                $settingsChanged[] = $setting;
                            } else {
                                $settingsNotChanged[] = $setting;
                            }
                        }
                        ?>
                        
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Runtime Settings</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo count($settingsChanged) > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo count($settingsChanged); ?> changed
                            </span>
                        </div>
                        
                        <?php
                        // Output compression
                        $compressionStatus = 'Not available';
                        $compressionClass = 'bg-yellow-100 text-yellow-800';
                        if (ini_get('zlib.output_compression')) {
                            $compressionStatus = 'Enabled';
                            $compressionClass = 'bg-green-100 text-green-800';
                        } else if (function_exists('ob_gzhandler') && !ini_get('zlib.output_compression')) {
                            // We can enable it
                            $compressionStatus = 'Available';
                            $compressionClass = 'bg-blue-100 text-blue-800';
                        }
                        ?>
                        
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Output Compression</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $compressionClass; ?>">
                                <?php echo $compressionStatus; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">Session Configuration</h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <div class="space-y-4">
                        <?php
                        // Session settings (can't be changed after session start, but we can show status)
                        $sessionSettings = [
                            'session.gc_maxlifetime' => 1440,
                            'session.cookie_lifetime' => 0,
                            'session.use_strict_mode' => 1
                        ];
                        
                        foreach ($sessionSettings as $setting => $recommended) {
                            $current = ini_get($setting);
                            $status = ($current == $recommended) ? 'Optimal' : 'Suboptimal';
                            $class = ($current == $recommended) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                        ?>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-medium text-gray-500"><?php echo $setting; ?></span>
                                <p class="text-xs text-gray-400">Current: <?php echo $current; ?>, Recommended: <?php echo $recommended; ?></p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $class; ?>">
                                <?php echo $status; ?>
                            </span>
                        </div>
                        <?php } ?>
                        
                        <div class="p-3 bg-blue-50 rounded-md">
                            <p class="text-xs text-blue-700">
                                <i class="fas fa-info-circle mr-1"></i> Session settings can only be changed in php.ini before session start. 
                                Add these to php.ini: session.gc_maxlifetime=1440, session.cookie_lifetime=0, session.use_strict_mode=1
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900">Database Configuration</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php
                // Database connection optimization
                function optimizeDatabaseConfig() {
                    $configFile = __DIR__ . '/config.php';
                    if (file_exists($configFile)) {
                        $configContent = file_get_contents($configFile);
                        
                        // Add persistent connection and other optimizations
                        $pdoOptions = "[\n        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n        PDO::ATTR_EMULATE_PREPARES => false,\n        PDO::ATTR_PERSISTENT => true,\n        PDO::MYSQL_ATTR_INIT_COMMAND => \"SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'\"\n    ]";
                        
                        if (strpos($configContent, 'PDO::ATTR_PERSISTENT') === false) {
                            // Update PDO options to include persistent connections
                            $oldOptions = "[\n        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n        PDO::ATTR_EMULATE_PREPARES => false,\n        PDO::MYSQL_ATTR_INIT_COMMAND => \"SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'\"\n    ]";
                            
                            $configContent = str_replace($oldOptions, $pdoOptions, $configContent);
                            
                            if (file_put_contents($configFile, $configContent)) {
                                return true;
                            }
                        } else {
                            return true; // Already optimized
                        }
                    }
                    return false;
                }
                
                $dbOptimized = optimizeDatabaseConfig();
                ?>
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-md font-medium text-gray-900">Persistent Connections</h4>
                        <p class="text-sm text-gray-500 mt-1">Enables persistent database connections for better performance</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $dbOptimized ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $dbOptimized ? 'Enabled' : 'Failed'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">File System Optimization</h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <?php
                    $cacheDir = __DIR__ . '/cache/';
                    $cacheStatus = 'Not found';
                    $cacheClass = 'bg-yellow-100 text-yellow-800';
                    $deleted = 0;
                    
                    if (is_dir($cacheDir)) {
                        $cacheStatus = 'Active';
                        $cacheClass = 'bg-green-100 text-green-800';
                        
                        // Clean up old cache files
                        $files = glob($cacheDir . '*.cache');
                        foreach ($files as $file) {
                            // Delete files older than 1 hour
                            if (time() - filemtime($file) > 3600) {
                                unlink($file);
                                $deleted++;
                            }
                        }
                    }
                    ?>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Cache Directory</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $cacheClass; ?>">
                                <?php echo $cacheStatus; ?>
                            </span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Cleaned Files</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?php echo $deleted; ?> files
                            </span>
                        </div>
                        
                        <div class="p-3 bg-blue-50 rounded-md">
                            <p class="text-xs text-blue-700">
                                <i class="fas fa-info-circle mr-1"></i> Old cache files are automatically cleaned up
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">Performance Tips</h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mt-0.5 mr-2"></i>
                            <span>To enable OPcache, edit php.ini and add: zend_extension=php_opcache.dll and opcache.enable=1</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mt-0.5 mr-2"></i>
                            <span>Set session settings in php.ini: session.gc_maxlifetime=1440, session.cookie_lifetime=0</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mt-0.5 mr-2"></i>
                            <span>Increase memory_limit in php.ini for large data operations</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mt-0.5 mr-2"></i>
                            <span>Enable output compression with zlib.output_compression=1 in php.ini</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">Optimization Running</h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p>PHP optimizations are automatically applied when you visit this page. For permanent changes, update your php.ini file and restart the server.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end">
            <a href="optimization_tools.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i> Back to Optimization
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo renderAdminLayout('PHP Optimization - Student Management System', $content);
?>