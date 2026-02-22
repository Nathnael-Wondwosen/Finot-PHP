<?php
/**
 * Database Optimization Script
 * Adds proper indexes to improve query performance
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
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Database Optimization</h1>
                <p class="text-gray-600 mt-1">Adding indexes and optimizing tables for better performance</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-database text-blue-600 text-xl"></i>
            </div>
        </div>
        
        <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Database Optimization</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>This process will add database indexes to improve query performance and optimize table structures. This may take a few moments to complete.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Successful Operations</p>
                        <p class="text-2xl font-semibold text-gray-900" id="successCount">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Errors</p>
                        <p class="text-2xl font-semibold text-gray-900" id="errorCount">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-tasks text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Operations</p>
                        <p class="text-2xl font-semibold text-gray-900" id="totalOperations">0</p>
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
                <span id="progressStatus">Ready to start optimization</span>
            </div>
        </div>
        
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900">Optimization Results</h3>
            </div>
            <div class="px-4 py-5 sm:p-6" id="resultsContainer">
                <div class="text-center py-8" id="initialMessage">
                    <i class="fas fa-database text-gray-300 text-4xl mb-3"></i>
                    <p class="text-gray-500">Click "Run Optimization" to start the database optimization process</p>
                </div>
                <div id="resultsList" class="space-y-3 hidden"></div>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button id="runOptimizationBtn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-bolt mr-2"></i> Run Optimization
            </button>
            <a href="optimization_tools.php" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i> Back to Optimization
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Table Information</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rows</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size (MB)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="tableInfoBody">
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT 
                                table_name,
                                table_rows,
                                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                            FROM information_schema.tables 
                            WHERE table_schema = DATABASE()
                            ORDER BY table_name
                        ");
                        
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<tr>";
                            echo "<td class='px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900'>" . htmlspecialchars($row['table_name']) . "</td>";
                            echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>" . number_format($row['table_rows']) . "</td>";
                            echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>" . $row['size_mb'] . "</td>";
                            echo "</tr>";
                        }
                    } catch (Exception $e) {
                        echo "<tr><td colspan='3' class='px-6 py-4 text-sm text-red-600'>Error fetching table information: " . $e->getMessage() . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const runBtn = document.getElementById('runOptimizationBtn');
    const resultsList = document.getElementById('resultsList');
    const initialMessage = document.getElementById('initialMessage');
    const successCountEl = document.getElementById('successCount');
    const errorCountEl = document.getElementById('errorCount');
    const totalOperationsEl = document.getElementById('totalOperations');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const progressStatus = document.getElementById('progressStatus');
    const tableInfoBody = document.getElementById('tableInfoBody');
    
    // Set total operations count
    const optimizationQueries = [
        "ALTER TABLE students ADD INDEX idx_full_name (full_name)",
        "ALTER TABLE students ADD INDEX idx_christian_name (christian_name)",
        "ALTER TABLE students ADD INDEX idx_birth_date (birth_date)",
        "ALTER TABLE students ADD INDEX idx_current_grade (current_grade)",
        "ALTER TABLE students ADD INDEX idx_phone_number (phone_number)",
        "ALTER TABLE students ADD INDEX idx_created_at (created_at)",
        "ALTER TABLE students ADD INDEX idx_flagged (flagged)",
        "ALTER TABLE students ADD INDEX idx_sub_city (sub_city)",
        "ALTER TABLE students ADD INDEX idx_district (district)",
        "ALTER TABLE instrument_registrations ADD INDEX idx_full_name (full_name)",
        "ALTER TABLE instrument_registrations ADD INDEX idx_instrument (instrument)",
        "ALTER TABLE instrument_registrations ADD INDEX idx_created_at (created_at)",
        "ALTER TABLE instrument_registrations ADD INDEX idx_flagged (flagged)",
        "ALTER TABLE instrument_registrations ADD INDEX idx_birth_year_et (birth_year_et)",
        "ALTER TABLE instrument_registrations ADD INDEX idx_phone_number (phone_number)",
        "ALTER TABLE parents ADD INDEX idx_student_id (student_id)",
        "ALTER TABLE parents ADD INDEX idx_parent_type (parent_type)",
        "ALTER TABLE admin_preferences ADD INDEX idx_admin_id (admin_id)",
        "ALTER TABLE admin_preferences ADD INDEX idx_table_name (table_name)",
        "OPTIMIZE TABLE students",
        "OPTIMIZE TABLE instrument_registrations",
        "OPTIMIZE TABLE parents",
        "OPTIMIZE TABLE admin_preferences",
        "OPTIMIZE TABLE admins"
    ];
    
    totalOperationsEl.textContent = optimizationQueries.length;
    
    runBtn.addEventListener('click', function() {
        // Disable button during optimization
        runBtn.disabled = true;
        runBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Running...';
        
        // Reset UI
        resultsList.innerHTML = '';
        resultsList.classList.remove('hidden');
        initialMessage.classList.add('hidden');
        successCountEl.textContent = '0';
        errorCountEl.textContent = '0';
        progressBar.style.width = '0%';
        progressText.textContent = '0%';
        progressStatus.textContent = 'Starting optimization...';
        
        // Make AJAX request to run optimization
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/run_database_optimization.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // Update counters
                            successCountEl.textContent = response.successCount;
                            errorCountEl.textContent = response.errorCount;
                            
                            // Update progress to 100%
                            progressBar.style.width = '100%';
                            progressText.textContent = '100%';
                            progressStatus.textContent = 'Optimization complete!';
                            
                            // Display results
                            response.results.forEach(result => {
                                const resultItem = document.createElement('div');
                                resultItem.className = 'p-3 rounded-md border';
                                
                                if (result.status === 'success') {
                                    resultItem.classList.add('bg-green-50', 'border-green-200');
                                    resultItem.innerHTML = `
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check-circle text-green-500"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-green-800">
                                                    <span class="font-medium">Success:</span> ${result.query}
                                                </p>
                                            </div>
                                        </div>
                                    `;
                                } else if (result.status === 'duplicate') {
                                    resultItem.classList.add('bg-yellow-50', 'border-yellow-200');
                                    resultItem.innerHTML = `
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-800">
                                                    <span class="font-medium">Already exists:</span> ${result.query}
                                                </p>
                                            </div>
                                        </div>
                                    `;
                                } else {
                                    resultItem.classList.add('bg-red-50', 'border-red-200');
                                    resultItem.innerHTML = `
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-times-circle text-red-500"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-red-800">
                                                    <span class="font-medium">Error:</span> ${result.query}
                                                    <br><span class="text-xs">${result.message}</span>
                                                </p>
                                            </div>
                                        </div>
                                    `;
                                }
                                
                                resultsList.appendChild(resultItem);
                            });
                            
                            // Update table information
                            if (response.tableInfo && response.tableInfo.length > 0) {
                                tableInfoBody.innerHTML = '';
                                response.tableInfo.forEach(table => {
                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                        <td class='px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900'>${table.table_name}</td>
                                        <td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>${Number(table.table_rows).toLocaleString()}</td>
                                        <td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>${table.size_mb}</td>
                                    `;
                                    tableInfoBody.appendChild(row);
                                });
                            }
                        } else {
                            // Handle error response
                            progressStatus.textContent = 'Error: ' + response.message;
                        }
                    } catch (e) {
                        progressStatus.textContent = 'Error parsing response: ' + e.message;
                    }
                } else {
                    progressStatus.textContent = 'HTTP Error: ' + xhr.status;
                }
                
                // Re-enable button
                runBtn.disabled = false;
                runBtn.innerHTML = '<i class="fas fa-bolt mr-2"></i> Run Optimization';
            }
        };
        
        xhr.send();
    });
});
</script>

<?php
$content = ob_get_clean();
echo renderAdminLayout('Database Optimization - Student Management System', $content);
?>