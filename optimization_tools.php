<?php
/**
 * Optimization Tools Dashboard
 * Central hub for accessing all performance optimization tools
 */

require 'config.php';
require 'includes/admin_layout.php';

// Check admin authentication
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$tools = [
    [
        'name' => 'Database Optimization',
        'description' => 'Add indexes and optimize database tables',
        'file' => 'database_optimization.php',
        'icon' => 'fa-database',
        'color' => 'blue'
    ],
    [
        'name' => 'Asset Optimizer',
        'description' => 'Minify and combine CSS/JS files',
        'file' => 'asset_optimizer.php',
        'icon' => 'fa-file-code',
        'color' => 'yellow'
    ],
    [
        'name' => 'Cache Manager',
        'description' => 'View and manage cached data',
        'file' => 'cache_manager.php',
        'icon' => 'fa-cache',
        'color' => 'green'
    ],
    [
        'name' => 'PHP Optimization',
        'description' => 'Configure PHP settings for performance',
        'file' => 'php_optimization.php',
        'icon' => 'fa-cogs',
        'color' => 'purple'
    ],

    [
        'name' => 'Performance Dashboard',
        'description' => 'View system performance metrics',
        'file' => 'performance_dashboard.php',
        'icon' => 'fa-tachometer-alt',
        'color' => 'red'
    ],
    [
        'name' => 'Run All Optimizations',
        'description' => 'Execute all optimization tools at once',
        'file' => 'run_all_optimizations.php',
        'icon' => 'fa-bolt',
        'color' => 'orange'
    ]
];

ob_start();
?>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-2">Performance Optimization Center</h2>
        <p class="text-gray-600 mb-6">
            Use these tools to optimize the performance of your Student Management System. 
            Each tool addresses different aspects of performance to make your application faster and more responsive.
        </p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($tools as $tool): ?>
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="p-5">
                    <div class="flex items-center mb-4">
                        <div class="p-3 rounded-full bg-<?php echo $tool['color']; ?>-100 text-<?php echo $tool['color']; ?>-600">
                            <i class="fas <?php echo $tool['icon']; ?> text-xl"></i>
                        </div>
                        <h3 class="ml-4 text-lg font-medium text-gray-900"><?php echo $tool['name']; ?></h3>
                    </div>
                    <p class="text-gray-600 text-sm mb-4"><?php echo $tool['description']; ?></p>
                    <a href="<?php echo $tool['file']; ?>" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-<?php echo $tool['color']; ?>-600 hover:bg-<?php echo $tool['color']; ?>-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-<?php echo $tool['color']; ?>-500">
                        Access Tool
                        <i class="fas fa-arrow-right ml-2 text-xs"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-blue-800 mb-2">Performance Tips</h3>
        <ul class="text-blue-700 list-disc pl-5 space-y-1">
            <li>Run optimizations regularly, especially after major data updates</li>
            <li>Clear cache after making changes to student information</li>
            <li>Re-optimize assets after modifying JavaScript or CSS files</li>
            <li>Monitor performance using the Performance Dashboard</li>
            <li>Use "Run All Optimizations" for a complete system tune-up</li>
        </ul>
    </div>
</div>

<?php
$content = ob_get_clean();
echo renderAdminLayout('Optimization Tools - Student Management System', $content);
?>