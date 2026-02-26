<?php
/**
 * Cache Management Interface
 * Allows admins to view and manage cached data
 */

require_once 'config.php';
require_once 'includes/cache.php';
require_once 'includes/admin_layout.php';

// Check admin authentication
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Handle actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'clear_cache':
            if (cache_clear()) {
                $message = 'Cache cleared successfully!';
            } else {
                $message = 'Error clearing cache.';
            }
            break;
        case 'view_stats':
            // Stats are displayed below
            break;
    }
}

// Get cache statistics
$cacheStats = $cache->getStats();

ob_start();
?>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Cache Management</h2>
        
        <?php if ($message): ?>
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="text-blue-800">
                    <i class="fas fa-database text-2xl mb-2"></i>
                    <h3 class="font-bold text-lg">File Cache</h3>
                    <p class="text-2xl font-bold"><?php echo $cacheStats['count']; ?></p>
                    <p class="text-sm">Cached Items</p>
                </div>
            </div>
            
            <div class="bg-purple-50 p-4 rounded-lg">
                <div class="text-purple-800">
                    <i class="fas fa-memory text-2xl mb-2"></i>
                    <h3 class="font-bold text-lg">Memory Cache</h3>
                    <p class="text-2xl font-bold"><?php echo $cacheStats['memory_count']; ?></p>
                    <p class="text-sm">Cached Items</p>
                </div>
            </div>
            
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="text-green-800">
                    <i class="fas fa-weight-hanging text-2xl mb-2"></i>
                    <h3 class="font-bold text-lg">Cache Size</h3>
                    <p class="text-2xl font-bold"><?php echo $cacheStats['size_formatted']; ?></p>
                    <p class="text-sm">Total Size</p>
                </div>
            </div>
        </div>
        
        <form method="POST" class="mb-6">
            <input type="hidden" name="action" value="clear_cache">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                <i class="fas fa-trash mr-2"></i> Clear All Cache
            </button>
        </form>
        
        <div class="bg-yellow-50 p-4 rounded-lg mb-6">
            <h3 class="font-medium text-yellow-800 mb-2">Cache Information</h3>
            <ul class="text-sm text-yellow-700 list-disc pl-5">
                <li>File cache stores data on disk for persistence between requests</li>
                <li>Memory cache stores data in PHP memory for faster access during the same request</li>
                <li>Cache improves performance by reducing database queries and computations</li>
                <li>Clear cache when making changes to data or code</li>
            </ul>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-medium text-gray-800 mb-2">Cache Optimization Tips</h3>
            <ul class="text-sm text-gray-700 list-disc pl-5">
                <li>Cache frequently accessed data like student lists and instrument registrations</li>
                <li>Use appropriate TTL (time-to-live) values for different types of data</li>
                <li>Clear cache after updating student information</li>
                <li>Monitor cache size to prevent disk space issues</li>
            </ul>
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
echo renderAdminLayout('Cache Manager - Student Management System', $content);
?>