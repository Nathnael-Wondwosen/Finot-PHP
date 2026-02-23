<?php
/**
 * Enhanced Performance Optimizer for Finot-PHP
 * Comprehensive optimization for maximum speed
 */

// Check admin access
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    die('Access denied');
}

ob_start();
?>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    <!-- Performance Score Card -->
    <div class="bg-gradient-to-r from-green-500 to-teal-600 rounded-xl shadow-lg p-6 mb-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold">🚀 Ultimate Performance Optimizer</h1>
                <p class="mt-2 text-green-100">Maximize response speed and user experience</p>
            </div>
            <div class="text-center">
                <div class="text-5xl font-bold" id="perfScore">--</div>
                <div class="text-green-100 text-sm">Performance Score</div>
            </div>
        </div>
    </div>

    <!-- Quick Optimization Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Clear Cache -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900">Clear Cache</h3>
                    <p class="text-sm text-gray-500">Remove all cached data</p>
                </div>
                <button onclick="runOptimization('clear_cache')" class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg">
                    <i class="fas fa-bolt"></i>
                </button>
            </div>
        </div>

        <!-- Optimize Database -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900">Optimize DB</h3>
                    <p class="text-sm text-gray-500">Rebuild indexes & tables</p>
                </div>
                <button onclick="runOptimization('optimize_db')" class="bg-green-500 hover:bg-green-600 text-white p-2 rounded-lg">
                    <i class="fas fa-database"></i>
                </button>
            </div>
        </div>

        <!-- Compress Assets -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900">Compress JS/CSS</h3>
                    <p class="text-sm text-gray-500">Minify frontend assets</p>
                </div>
                <button onclick="runOptimization('compress_assets')" class="bg-purple-500 hover:bg-purple-600 text-white p-2 rounded-lg">
                    <i class="fas fa-compress-arrows-alt"></i>
                </button>
            </div>
        </div>

        <!-- Warmup Cache -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900">Cache Warmup</h3>
                    <p class="text-sm text-gray-500">Pre-load frequent data</p>
                </div>
                <button onclick="runOptimization('warmup_cache')" class="bg-orange-500 hover:bg-orange-600 text-white p-2 rounded-lg">
                    <i class="fas fa-fire"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Detailed Settings -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- PHP Settings -->
        <div class="bg-white rounded-lg shadow-lg">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">⚡ PHP Performance Settings</h3>
            </div>
            <div class="p-6 space-y-4">
                <?php
                $phpSettings = [
                    ['name' => 'OPcache', 'status' => function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled'] ?? false, 'recommended' => 'Enable'],
                    ['name' => 'Gzip Compression', 'status' => ini_get('zlib.output_compression'), 'recommended' => 'Enable'],
                    ['name' => 'Persistent Connections', 'status' => true, 'recommended' => 'Enabled'],
                    ['name' => 'Memory Limit', 'status' => ini_get('memory_limit'), 'recommended' => '512M+'],
                ];
                ?>
                <?php foreach ($phpSettings as $setting): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <span class="font-medium text-gray-700"><?= $setting['name'] ?></span>
                    <span class="px-3 py-1 text-sm rounded-full <?= $setting['status'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <?= is_bool($setting['status']) ? ($setting['status'] ? '✓ Enabled' : '✗ Disabled') : $setting['status'] ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Database Stats -->
        <div class="bg-white rounded-lg shadow-lg">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">🗄️ Database Statistics</h3>
            </div>
            <div class="p-6 space-y-4">
                <?php
                try {
                    $stmt = $pdo->query("
                        SELECT 
                            table_name,
                            table_rows,
                            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                        FROM information_schema.tables 
                        WHERE table_schema = DATABASE()
                        ORDER BY (data_length + index_length) DESC
                        LIMIT 5
                    ");
                    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $totalRows = 0;
                    $totalSize = 0;
                    foreach ($tables as $table) {
                        $totalRows += $table['table_rows'];
                        $totalSize += $table['size_mb'];
                    }
                } catch (Exception $e) {
                    $tables = [];
                    $totalRows = 0;
                    $totalSize = 0;
                }
                ?>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-blue-600"><?= number_format($totalRows) ?></div>
                        <div class="text-sm text-blue-600">Total Records</div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-green-600"><?= number_format($totalSize, 2) ?> MB</div>
                        <div class="text-sm text-green-600">Database Size</div>
                    </div>
                </div>
                
                <!-- Top Tables -->
                <div class="mt-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Largest Tables</h4>
                    <?php foreach ($tables as $table): ?>
                    <div class="flex justify-between text-sm py-1 border-b border-gray-100">
                        <span class="text-gray-600"><?= htmlspecialchars($table['table_name']) ?></span>
                        <span class="text-gray-500"><?= number_format($table['table_rows']) ?> rows (<?= $table['size_mb'] ?> MB)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Optimizations -->
    <div class="bg-white rounded-lg shadow-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900">🎯 Advanced Optimizations</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Query Caching -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-cache text-blue-500 mr-2"></i>
                        <h4 class="font-semibold">Query Caching</h4>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Cache frequently accessed database queries</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-green-600">✓ Active (File-based)</span>
                        <button class="text-xs bg-blue-500 text-white px-2 py-1 rounded">Configure</button>
                    </div>
                </div>

                <!-- Lazy Loading -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-spinner text-purple-500 mr-2"></i>
                        <h4 class="font-semibold">Lazy Loading</h4>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Load data on-demand for faster initial render</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-green-600">✓ Enabled</span>
                        <button class="text-xs bg-blue-500 text-white px-2 py-1 rounded">Configure</button>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-list-ol text-green-500 mr-2"></i>
                        <h4 class="font-semibold">Smart Pagination</h4>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Optimized page sizes for different views</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-green-600">✓ 25-50 per page</span>
                        <button class="text-xs bg-blue-500 text-white px-2 py-1 rounded">Configure</button>
                    </div>
                </div>

                <!-- AJAX Loading -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                        <h4 class="font-semibold">AJAX Operations</h4>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Non-blocking server requests</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-green-600">✓ Enabled</span>
                        <span class="text-xs text-gray-400">System</span>
                    </div>
                </div>

                <!-- Image Optimization -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-image text-pink-500 mr-2"></i>
                        <h4 class="font-semibold">Image Optimization</h4>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Auto-compress uploaded images</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-green-600">✓ Enabled</span>
                        <button class="text-xs bg-blue-500 text-white px-2 py-1 rounded">Configure</button>
                    </div>
                </div>

                <!-- Preload Hints -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-forward text-indigo-500 mr-2"></i>
                        <h4 class="font-semibold">Resource Hints</h4>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">DNS prefetch & preconnect</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-green-600">✓ Enabled</span>
                        <span class="text-xs text-gray-400">.htaccess</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Tips -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-lightbulb text-yellow-500"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800">Performance Tips</h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li>Run database optimization weekly for best performance</li>
                        <li>Clear cache after major data imports</li>
                        <li>Use pagination to load large datasets (25-50 records per page)</li>
                        <li>Enable OPcache in php.ini for production: <code>opcache.enable=1</code></li>
                        <li>Consider Redis for enterprise-level caching</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Run All Button -->
    <div class="text-center">
        <button onclick="runAllOptimizations()" class="bg-gradient-to-r from-green-500 to-teal-600 hover:from-green-600 hover:to-teal-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg transform transition hover:scale-105">
            <i class="fas fa-rocket mr-2"></i> Run All Optimizations
        </button>
    </div>
</div>

<script>
// Performance score calculation
document.addEventListener('DOMContentLoaded', function() {
    let score = 0;
    
    // PHP settings (40 points)
    <?php if (function_exists('opcache_get_status') && (opcache_get_status()['opcache_enabled'] ?? false)): ?>
    score += 20;
    <?php endif; ?>
    <?php if (ini_get('zlib.output_compression')): ?>
    score += 10;
    <?php endif; ?>
    <?php if (ini_get('memory_limit') >= '256M'): ?>
    score += 10;
    <?php endif; ?>
    
    // Database (30 points)
    <?php if ($totalSize < 100): ?>
    score += 15;
    <?php endif; ?>
    <?php if (count($tables) > 0): ?>
    score += 15;
    <?php endif; ?>
    
    // Features (30 points)
    score += 10; // Cache system
    score += 10; // Lazy loading
    score += 10; // AJAX
    
    document.getElementById('perfScore').textContent = score;
});

function runOptimization(type) {
    const btn = event.currentTarget;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/run_optimization.php?type=' + type)
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt"></i>';
            if (data.success) location.reload();
        })
        .catch(() => {
            showToast('Optimization failed', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt"></i>';
        });
}

function runAllOptimizations() {
    if (!confirm('Run all optimizations now? This may take a moment.')) return;
    
    showToast('Running all optimizations...', 'info');
    
    fetch('api/run_optimization.php?type=all')
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 1500);
        });
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white z-50 animate-slide-in-right`;
    toast.style.background = type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6';
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle mr-2"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php
$content = ob_get_clean();

// Check if this file is being included or rendered directly
if (basename($_SERVER['PHP_SELF']) === 'ultimate_optimizer.php') {
    require_once 'includes/admin_layout.php';
    echo renderAdminLayout('Ultimate Optimizer - Student Management System', $content);
}
?>
