<?php
/**
 * Run All Optimizations Script
 * Executes all performance optimization tools at once
 */

require_once 'config.php';
require_once 'includes/admin_layout.php';

// Check admin authentication
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    die('Access denied. Admin login required.');
}

ob_start();
?>

<div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Run All Optimizations</h1>
                <p class="text-gray-600 mt-1">Execute all performance optimization tools at once</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-bolt text-blue-600 text-xl"></i>
            </div>
        </div>
        
        <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Optimization Process</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>This tool will run all optimization scripts sequentially. The process may take a few minutes to complete. Please do not close this page until the process finishes.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-3">Optimization Progress</h2>
            <div class="bg-gray-200 rounded-full h-2.5">
                <div class="bg-blue-600 h-2.5 rounded-full" id="progressBar" style="width: 0%"></div>
            </div>
            <div class="flex justify-between text-sm text-gray-500 mt-1">
                <span id="progressText">0%</span>
                <span id="progressStatus">Ready to start</span>
            </div>
        </div>
        
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900">Optimization Steps</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <div id="output" class="space-y-3">
                    <div class="text-center py-8 text-gray-500" id="initialMessage">
                        <i class="fas fa-rocket text-gray-300 text-4xl mb-3"></i>
                        <p>Click "Run All Optimizations" to start the process</p>
                    </div>
                    <div id="resultsList" class="hidden"></div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button id="runAllBtn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-play mr-2"></i> Run All Optimizations
            </button>
            <a href="optimization_tools.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i> Back to Optimization
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const runBtn = document.getElementById('runAllBtn');
    const resultsList = document.getElementById('resultsList');
    const initialMessage = document.getElementById('initialMessage');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const progressStatus = document.getElementById('progressStatus');
    
    // Optimization steps (real async API calls)
    const steps = [
        { name: 'Database Optimization', url: 'api/run_database_optimization.php' },
        { name: 'Asset Optimization', url: 'api/run_asset_optimization.php' },
        { name: 'PHP Optimization', url: 'api/run_php_optimization.php' },
        { name: 'Cache Management', url: 'api/clear_cache.php' }
    ];
    
    runBtn.addEventListener('click', function() {
        // Disable button during optimization
        runBtn.disabled = true;
        runBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Running...';
        
        // Reset UI
        resultsList.innerHTML = '';
        resultsList.classList.remove('hidden');
        initialMessage.classList.add('hidden');
        progressBar.style.width = '0%';
        progressText.textContent = '0%';
        progressStatus.textContent = 'Starting optimization...';
        
        // Run each step
        let completed = 0;
        const total = steps.length;
        
        function runStep(index) {
            if (index >= total) {
                progressStatus.textContent = 'All optimizations completed successfully!';
                runBtn.innerHTML = '<i class="fas fa-check mr-2"></i> Completed';
                return;
            }

            const step = steps[index];
            progressStatus.textContent = `Running ${step.name}...`;

            const resultItem = document.createElement('div');
            resultItem.className = 'p-3 rounded-md border border-blue-200 bg-blue-50';
            resultItem.innerHTML = `
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-cog fa-spin text-blue-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-800">
                            <span class="font-medium">Running:</span> ${step.name}
                        </p>
                    </div>
                </div>
            `;
            resultsList.appendChild(resultItem);
            resultsList.scrollTop = resultsList.scrollHeight;

            // Perform async POST to the API endpoint
            fetch(step.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: ''
            })
            .then(res => res.json().catch(() => ({ success: false, message: 'Invalid JSON response' })))
            .then(data => {
                // Determine success
                const ok = data && data.success !== false;
                // Update result item
                if (ok) {
                    resultItem.className = 'p-3 rounded-md border border-green-200 bg-green-50';
                    let details = '';
                    if (step.name === 'Database Optimization' && Array.isArray(data.results)) {
                        const successes = data.successCount ?? 0;
                        const errors = data.errorCount ?? 0;
                        details = `<span class="text-xs">Success: ${successes}, Errors: ${errors}</span>`;
                    } else if (step.name === 'Asset Optimization') {
                        const jsOk = data.js_success ? 'Yes' : 'No';
                        const jsSize = (data.js_size || 0).toLocaleString();
                        details = `<span class=\"text-xs\">JS Optimized: ${jsOk}, Size: ${jsSize} bytes</span>`;
                    } else if (step.name === 'PHP Optimization') {
                        const changed = Array.isArray(data.changed) ? data.changed.length : 0;
                        const opcache = data.opcache && data.opcache.enabled ? 'enabled' : 'not enabled';
                        details = `<span class=\"text-xs\">Settings changed: ${changed}, OPcache: ${opcache}</span>`;
                    } else if (step.name === 'Cache Management' && data.before && data.after) {
                        details = `<span class=\"text-xs\">Before: ${data.before.count} items, After: ${data.after.count} items</span>`;
                    }
                    resultItem.innerHTML = `
                        <div class=\"flex items-center\">
                            <div class=\"flex-shrink-0\">
                                <i class=\"fas fa-check-circle text-green-500\"></i>
                            </div>
                            <div class=\"ml-3\">
                                <p class=\"text-sm text-green-800\">
                                    <span class=\"font-medium\">Completed:</span> ${step.name}
                                    <br>${details}
                                </p>
                            </div>
                        </div>
                    `;
                } else {
                    resultItem.className = 'p-3 rounded-md border border-red-200 bg-red-50';
                    const msg = (data && data.message) ? data.message : 'Unknown error';
                    resultItem.innerHTML = `
                        <div class=\"flex items-center\">
                            <div class=\"flex-shrink-0\">
                                <i class=\"fas fa-times-circle text-red-500\"></i>
                            </div>
                            <div class=\"ml-3\">
                                <p class=\"text-sm text-red-800\">
                                    <span class=\"font-medium\">Failed:</span> ${step.name}
                                    <br><span class=\"text-xs\">${msg}</span>
                                </p>
                            </div>
                        </div>
                    `;
                }

                // Update progress
                completed++;
                const percent = (completed / total) * 100;
                progressBar.style.width = percent + '%';
                progressText.textContent = Math.round(percent) + '%';

                // Next step
                runStep(index + 1);
            })
            .catch(err => {
                resultItem.className = 'p-3 rounded-md border border-red-200 bg-red-50';
                resultItem.innerHTML = `
                    <div class=\"flex items-center\">
                        <div class=\"flex-shrink-0\">
                            <i class=\"fas fa-times-circle text-red-500\"></i>
                        </div>
                        <div class=\"ml-3\">
                            <p class=\"text-sm text-red-800\">
                                <span class=\"font-medium\">Failed:</span> ${step.name}
                                <br><span class=\"text-xs\">${err && err.message ? err.message : 'Network error'}</span>
                            </p>
                        </div>
                    </div>
                `;

                // Update progress even on failure
                completed++;
                const percent = (completed / total) * 100;
                progressBar.style.width = percent + '%';
                progressText.textContent = Math.round(percent) + '%';

                runStep(index + 1);
            });
        }
        
        // Start with first step
        runStep(0);
    });
});
</script>

<?php
$content = ob_get_clean();
echo renderAdminLayout('Run All Optimizations - Student Management System', $content);
?>