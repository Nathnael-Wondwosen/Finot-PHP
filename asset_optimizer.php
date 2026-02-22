<?php
/**
 * Web Interface for Asset Optimization
 * Allows admins to optimize CSS/JS assets through the web interface
 */

require_once 'config.php';
require_once 'includes/admin_layout.php';

// Check admin authentication
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Handle form submission
$message = '';
$optimizationResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'optimize') {
        // Include the asset optimizer
        require_once 'assets/optimize_assets.php';
        
        $optimizer = new AssetOptimizer();
        
        // Add JavaScript files
        $jsFiles = [
            'js/students.js',
            'js/enhanced-edit-drawer.js',
            'js/instrument-edit-drawer.js',
            'js/column-customizer.js',
            'js/ethiopian-calendar-filter.js'
        ];
        
        $processedFiles = [];
        foreach ($jsFiles as $file) {
            if (file_exists($file)) {
                $optimizer->addJSFile($file);
                $processedFiles[] = $file;
            }
        }
        
        // Add CSS files (if any in the future)
        $cssFiles = [];
        $processedCSSFiles = [];
        foreach ($cssFiles as $file) {
            if (file_exists($file)) {
                $optimizer->addCSSFile($file);
                $processedCSSFiles[] = $file;
            }
        }
        
        // Optimize and save assets
        $result = $optimizer->saveOptimizedAssets();
        
        $optimizationResult = [
            'success' => ($result['js'] || $result['css']),
            'js_success' => $result['js'],
            'css_success' => $result['css'],
            'js_size' => $result['js_size'],
            'css_size' => $result['css_size'],
            'processed_files' => $processedFiles,
            'processed_css_files' => $processedCSSFiles
        ];
        
        if ($result['js']) {
            $message = "Asset optimization completed successfully!";
        } else {
            $message = "Asset optimization failed. Please check the logs.";
        }
    }
}

ob_start();
?>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Asset Optimizer</h1>
                <p class="text-gray-600 mt-1">Combine and minify CSS/JS files to improve page loading speed</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-file-code text-blue-600 text-xl"></i>
            </div>
        </div>
        
        <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Asset Optimization</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>This tool combines and minifies JavaScript and CSS files to reduce file sizes and HTTP requests, resulting in faster page loading times.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $optimizationResult['success'] ? 'bg-green-100 border border-green-400 text-green-800' : 'bg-red-100 border border-red-400 text-red-800'; ?>">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas <?php echo $optimizationResult['success'] ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'; ?>"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium <?php echo $optimizationResult['success'] ? 'text-green-800' : 'text-red-800'; ?>">
                        <?php echo $message; ?>
                    </h3>
                    <?php if ($optimizationResult): ?>
                    <div class="mt-2 text-sm <?php echo $optimizationResult['success'] ? 'text-green-700' : 'text-red-700'; ?>">
                        <ul class="list-disc pl-5 space-y-1">
                            <?php if ($optimizationResult['js_success']): ?>
                                <li>JavaScript optimized: <?php echo number_format($optimizationResult['js_size']); ?> bytes</li>
                            <?php else: ?>
                                <li>JavaScript optimization: Failed</li>
                            <?php endif; ?>
                            <?php if ($optimizationResult['css_success']): ?>
                                <li>CSS optimized: <?php echo number_format($optimizationResult['css_size']); ?> bytes</li>
                            <?php else: ?>
                                <li>CSS optimization: No files to optimize</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-medium text-gray-900">Files to be Optimized</h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-file-code text-yellow-500 mr-2"></i> JavaScript Files
                            </h4>
                            <ul class="border border-gray-200 rounded-md divide-y divide-gray-200">
                                <li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm">
                                    <div class="flex items-center w-0 flex-1">
                                        <span class="ml-2 flex-1 w-0 truncate">js/students.js</span>
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <?php if (file_exists('js/students.js')): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i> Found
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times mr-1"></i> Missing
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm">
                                    <div class="flex items-center w-0 flex-1">
                                        <span class="ml-2 flex-1 w-0 truncate">js/enhanced-edit-drawer.js</span>
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <?php if (file_exists('js/enhanced-edit-drawer.js')): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i> Found
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times mr-1"></i> Missing
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm">
                                    <div class="flex items-center w-0 flex-1">
                                        <span class="ml-2 flex-1 w-0 truncate">js/instrument-edit-drawer.js</span>
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <?php if (file_exists('js/instrument-edit-drawer.js')): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i> Found
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times mr-1"></i> Missing
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm">
                                    <div class="flex items-center w-0 flex-1">
                                        <span class="ml-2 flex-1 w-0 truncate">js/column-customizer.js</span>
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <?php if (file_exists('js/column-customizer.js')): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i> Found
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times mr-1"></i> Missing
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm">
                                    <div class="flex items-center w-0 flex-1">
                                        <span class="ml-2 flex-1 w-0 truncate">js/ethiopian-calendar-filter.js</span>
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <?php if (file_exists('js/ethiopian-calendar-filter.js')): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i> Found
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times mr-1"></i> Missing
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-file-alt text-blue-500 mr-2"></i> CSS Files
                            </h4>
                            <div class="text-center py-6 bg-gray-50 rounded-md">
                                <i class="fas fa-info-circle text-gray-400 text-2xl mb-2"></i>
                                <p class="text-gray-500">No CSS files configured for optimization</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-6">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-medium text-gray-900">Optimization Benefits</h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <ul class="space-y-3">
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-sm text-gray-700">Reduced file sizes up to 70%</p>
                            </li>
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-sm text-gray-700">Fewer HTTP requests</p>
                            </li>
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-sm text-gray-700">Faster page loading times</p>
                            </li>
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-sm text-gray-700">Improved user experience</p>
                            </li>
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-sm text-gray-700">Reduced bandwidth usage</p>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-medium text-gray-900">Important Notes</h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <ul class="space-y-3 text-sm text-gray-600">
                            <li class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5"></i>
                                <span class="ml-2">Run this optimization after making changes to JavaScript files</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5"></i>
                                <span class="ml-2">Clear browser cache after optimization to see the effects</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5"></i>
                                <span class="ml-2">Backup your original files before optimization</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end">
            <form method="POST">
                <input type="hidden" name="action" value="optimize">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-bolt mr-2"></i> Optimize Assets Now
                </button>
            </form>
            <a href="optimization_tools.php" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i> Back to Optimization
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo renderAdminLayout('Asset Optimizer - Student Management System', $content);
?>