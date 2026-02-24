<?php
session_start();
require 'config.php';
require 'includes/admin_layout.php';
require 'includes/security_helpers.php';

// Require admin authentication
requireAdminLogin();

// Handle AJAX requests for database operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'backup_database':
                $backupResult = createDatabaseBackup($pdo);
                echo json_encode($backupResult);
                break;
                

                
            case 'get_table_info':
                $tableInfo = getTableInformation($pdo);
                echo json_encode($tableInfo);
                break;
                
            case 'export_database':
                $exportResult = exportFullDatabase($pdo);
                echo json_encode($exportResult);
                break;
                
            case 'clear_database':
                $password = $_POST['security_password'] ?? '';
                $clearResult = clearDatabase($pdo, $password);
                echo json_encode($clearResult);
                break;
                
            case 'clear_cache':
                $clearCacheResult = clearApplicationCache();
                echo json_encode($clearCacheResult);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Function to create database backup
function createDatabaseBackup($pdo) {
    try {
        $backupDir = __DIR__ . '/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        // Get all tables
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $output = "-- Database Backup Created: " . date('Y-m-d H:i:s') . "\n";
        $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $output .= "SET AUTOCOMMIT = 0;\n";
        $output .= "START TRANSACTION;\n\n";
        
        foreach ($tables as $table) {
            // Get CREATE TABLE statement
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $createTable['Create Table'] . ";\n\n";
            
            // Get table data
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $output .= "INSERT INTO `$table` VALUES\n";
                $values = [];
                foreach ($rows as $row) {
                    $escapedValues = array_map(function($value) use ($pdo) {
                        return $value === null ? 'NULL' : $pdo->quote($value);
                    }, $row);
                    $values[] = '(' . implode(',', $escapedValues) . ')';
                }
                $output .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        $output .= "COMMIT;\n";
        
        if (file_put_contents($filepath, $output)) {
            return [
                'success' => true, 
                'message' => 'Database backup created successfully',
                'filename' => $filename
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to write backup file'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()];
    }
}



// Function to get table information
function getTableInformation($pdo) {
    try {
        $tables = [];
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
            $tables[] = $row;
        }
        
        return ['success' => true, 'tables' => $tables];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get table information: ' . $e->getMessage()];
    }
}

// Function to export full database in SQL format
function exportFullDatabase($pdo) {
    try {
        // Get database name
        $stmt = $pdo->query("SELECT DATABASE() as db_name");
        $dbName = $stmt->fetch(PDO::FETCH_ASSOC)['db_name'];
        
        $filename = $dbName . '_export_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Generate SQL export content
        $sql = "-- phpMyAdmin SQL Dump\n";
        $sql .= "-- version 5.2.1\n";
        $sql .= "-- https://www.phpmyadmin.net/\n";
        $sql .= "--\n";
        $sql .= "-- Host: localhost\n";
        $sql .= "-- Generation Time: " . date('M d, Y \\a\\t h:i A') . "\n";
        $sql .= "-- Server version: " . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
        $sql .= "-- PHP Version: " . phpversion() . "\n";
        $sql .= "\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "START TRANSACTION;\n";
        $sql .= "SET time_zone = \"+00:00\";\n";
        $sql .= "\n";
        $sql .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
        $sql .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
        $sql .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
        $sql .= "/*!40101 SET NAMES utf8mb4 */;\n";
        $sql .= "\n";
        $sql .= "--\n";
        $sql .= "-- Database: `{$dbName}`\n";
        $sql .= "--\n";
        $sql .= "\n";
        
        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "\n";
            $sql .= "--\n";
            $sql .= "-- Table structure for table `{$table}`\n";
            $sql .= "--\n";
            $sql .= "\n";
            
            // Drop table statement
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // Create table statement
            $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
            $sql .= $createTable['Create Table'] . ";\n";
            $sql .= "\n";
            
            // Insert data
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $sql .= "--\n";
                $sql .= "-- Dumping data for table `{$table}`\n";
                $sql .= "--\n";
                $sql .= "\n";
                $sql .= "INSERT INTO `{$table}` VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $escapedValues = array_map(function($value) use ($pdo) {
                        return $value === null ? 'NULL' : $pdo->quote($value);
                    }, $row);
                    $values[] = '(' . implode(',', $escapedValues) . ')';
                }
                
                $sql .= implode(",\n", $values) . ";\n";
                $sql .= "\n";
            }
        }
        
        $sql .= "COMMIT;\n";
        $sql .= "\n";
        $sql .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
        $sql .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
        $sql .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
        
        // Set headers for download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        
        // Output the SQL
        echo $sql;
        exit;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Export failed: ' . $e->getMessage()];
    }
}

// Function to clear database with security password
function clearDatabase($pdo, $password) {
    // Security password - change this to your desired password
    $securityPassword = 'CLEAR_DB_2024!'; // Change this password!
    
    if ($password !== $securityPassword) {
        return ['success' => false, 'message' => 'Invalid security password'];
    }
    
    try {
        // Get all tables except system tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $clearedTables = [];
        
        // Start transaction for safety
        $pdo->beginTransaction();
        
        foreach ($tables as $table) {
            // Skip system/admin tables for safety
            if (in_array($table, ['admins'])) {
                continue;
            }
            
            // Clear table data but keep structure
            $clearStmt = $pdo->prepare("DELETE FROM `{$table}`");
            $clearStmt->execute();
            
            // Reset auto-increment if applicable
            $resetStmt = $pdo->prepare("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            $resetStmt->execute();
            
            $clearedTables[] = $table;
        }
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Database cleared successfully',
            'tables_cleared' => count($clearedTables),
            'cleared_tables' => $clearedTables
        ];
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollback();
        return ['success' => false, 'message' => 'Clear failed: ' . $e->getMessage()];
    }
}

// Function to clear application cache
function clearApplicationCache() {
    try {
        // Clear session-based cache (rate limiting data)
        $sessionKeysToRemove = [];
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'rate_limit_') === 0) {
                $sessionKeysToRemove[] = $key;
            }
        }
        
        foreach ($sessionKeysToRemove as $key) {
            unset($_SESSION[$key]);
        }
        
        // Return success message
        return [
            'success' => true,
            'message' => 'Application cache cleared successfully'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Cache clear failed: ' . $e->getMessage()];
    }
}

// Get database statistics
try {
    $dbStats = [];
    
    // Get total records across main tables
    $totalRecords = 0;
    $mainTables = ['students', 'instrument_registrations', 'admins'];
    foreach ($mainTables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $stmt->fetchColumn();
            $dbStats[$table] = $count;
            $totalRecords += $count;
        } catch (Exception $e) {
            $dbStats[$table] = 0;
        }
    }
    
    $dbStats['total_records'] = $totalRecords;
    
} catch (Exception $e) {
    $dbStats = [
        'students' => 0,
        'instrument_registrations' => 0,
        'admins' => 0,
        'total_records' => 0
    ];
}

ob_start();
?>

<!-- Database Management Content -->
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
            <i class="fas fa-database mr-3 text-primary-600 dark:text-primary-400"></i>
            Database Management
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Manage, backup, and maintain your database</p>
    </div>

    <!-- Database Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Total Students</h3>
                    <p class="text-3xl font-bold mt-2"><?= number_format($dbStats['students']) ?></p>
                </div>
                <i class="fas fa-users text-2xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Instruments</h3>
                    <p class="text-3xl font-bold mt-2"><?= number_format($dbStats['instrument_registrations']) ?></p>
                </div>
                <i class="fas fa-music text-2xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Total Records</h3>
                    <p class="text-3xl font-bold mt-2"><?= number_format($dbStats['total_records']) ?></p>
                </div>
                <i class="fas fa-database text-2xl opacity-80"></i>
            </div>
        </div>
    </div>

    <!-- Database Operations -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
            <i class="fas fa-cogs mr-2 text-primary-600 dark:text-primary-400"></i>
            Database Operations
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Backup Database -->
            <div class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg text-center">
                <i class="fas fa-download text-green-600 dark:text-green-400 text-2xl mb-3"></i>
                <h3 class="font-medium text-gray-900 dark:text-white mb-2">Create Backup</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Create a complete backup of your database.
                </p>
                <button onclick="createBackup()" 
                        class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors font-medium">
                    Create Backup
                </button>
            </div>

            <!-- Export Database -->
            <div class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg text-center">
                <i class="fas fa-file-export text-indigo-600 dark:text-indigo-400 text-2xl mb-3"></i>
                <h3 class="font-medium text-gray-900 dark:text-white mb-2">Export SQL</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Export database in phpMyAdmin SQL format.
                </p>
                <button onclick="exportDatabase()" 
                        class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors font-medium">
                    Export SQL
                </button>
            </div>

            <!-- Import Old SQL -->
            <div class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg text-center">
                <i class="fas fa-file-import text-blue-600 dark:text-blue-400 text-2xl mb-3"></i>
                <h3 class="font-medium text-gray-900 dark:text-white mb-2">Import Old SQL</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Upload and import old dump with preview and deduplication.
                </p>
                <a href="admin_import.php"
                   class="w-full inline-block text-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium">
                    Open Import Page
                </a>
            </div>

            <!-- Table Information -->
            <div class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg text-center">
                <i class="fas fa-info-circle text-purple-600 dark:text-purple-400 text-2xl mb-3"></i>
                <h3 class="font-medium text-gray-900 dark:text-white mb-2">Table Info</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    View detailed table information.
                </p>
                <button onclick="showTableInfo()" 
                        class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors font-medium">
                    View Info
                </button>
            </div>

            <!-- Clear Cache -->
            <div class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg text-center">
                <i class="fas fa-broom text-yellow-600 dark:text-yellow-400 text-2xl mb-3"></i>
                <h3 class="font-medium text-gray-900 dark:text-white mb-2">Clear Cache</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Clear application cache and browser data.
                </p>
                <button onclick="clearCache()" 
                        class="w-full px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition-colors font-medium">
                    Clear Cache
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Table Information Modal -->
<div id="table-info-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-4/5 shadow-lg rounded-xl bg-white dark:bg-gray-800">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Database Tables Information</h3>
            <button onclick="closeTableInfoModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div id="table-info-content" class="py-4">
            <div class="flex justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
            </div>
        </div>
    </div>
</div>

<!-- Clear Database Modal -->
<div id="clear-database-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-96 shadow-lg rounded-xl bg-white dark:bg-gray-800">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-semibold text-red-600 dark:text-red-400 flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Clear Database
            </h3>
            <button onclick="closeClearDatabaseModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="py-4">
            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-sm text-red-800 dark:text-red-200 font-medium mb-2">
                    <i class="fas fa-warning mr-1"></i> DANGER: This action cannot be undone!
                </p>
                <p class="text-sm text-red-700 dark:text-red-300">
                    This will permanently delete all student data, instrument registrations, and other records.
                    Admin accounts will be preserved.
                </p>
            </div>
            
            <div class="mb-4">
                <label for="security-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Security Password:
                </label>
                <input type="password" id="security-password" 
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:bg-gray-700 dark:text-white"
                       placeholder="Enter security password">
            </div>
            
            <div class="flex space-x-3">
                <button onclick="closeClearDatabaseModal()" 
                        class="flex-1 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-medium">
                    Cancel
                </button>
                <button onclick="confirmClearDatabase()" 
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors font-medium">
                    Clear Database
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$page_script = '
<script>
function showToast(message, type = "info") {
    const toast = document.createElement("div");
    const bgColor = type === "success" ? "bg-green-500" : 
                   type === "error" ? "bg-red-500" : "bg-blue-500";
    
    toast.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    toast.innerHTML = message;
    document.body.appendChild(toast);
    
    setTimeout(() => document.body.removeChild(toast), 3000);
}

function createBackup() {
    if (!confirm("Create database backup?")) return;
    
    showToast("Creating backup...", "info");
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=backup_database"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast("Backup created successfully!", "success");
        } else {
            showToast("Backup failed: " + data.message, "error");
        }
    })
    .catch(() => showToast("Network error", "error"));
}

function exportDatabase() {
    if (!confirm("Export entire database in SQL format? This will download a file.")) return;
    
    showToast("Preparing export...", "info");
    
    // Create a form to submit the export request
    const formElement = document.createElement("form");
    formElement.method = "POST";
    formElement.action = window.location.href;
    
    const actionInput = document.createElement("input");
    actionInput.type = "hidden";
    actionInput.name = "action";
    actionInput.value = "export_database";
    
    formElement.appendChild(actionInput);
    document.body.appendChild(formElement);
    
    // Submit form to trigger download
    formElement.submit();
    
    // Clean up
    document.body.removeChild(formElement);
    
    showToast("Export started! Check your downloads.", "success");
}



function clearCache() {
    if (!confirm("Clear application cache? This will remove cached data and may require reloading some resources.")) return;
    
    showToast("Clearing cache...", "info");
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=clear_cache"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Also try to clear browser cache via service worker if available
            if ("serviceWorker" in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    for(let registration of registrations) {
                        registration.unregister();
                    }
                    showToast("Cache cleared successfully! Please refresh the page.", "success");
                }).catch(function() {
                    showToast("Cache cleared successfully!", "success");
                });
            } else {
                showToast("Cache cleared successfully!", "success");
            }
        } else {
            showToast("Cache clear failed: " + data.message, "error");
        }
    })
    .catch(() => showToast("Network error", "error"));
}

function showTableInfo() {
    document.getElementById("table-info-modal").classList.remove("hidden");
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=get_table_info"
    })
    .then(response => response.json())
    .then(data => {
        const content = document.getElementById("table-info-content");
        
        if (data.success) {
            let html = `
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Table Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rows</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size (MB)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
            `;
            
            data.tables.forEach(table => {
                html += `
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">${table.table_name}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">${table.table_rows || 0}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">${table.size_mb || 0}</td>
                    </tr>
                `;
            });
            
            html += `</tbody></table></div>`;
            content.innerHTML = html;
        } else {
            content.innerHTML = `<p class="text-center text-red-500">Failed to load table information</p>`;
        }
    })
    .catch(() => {
        document.getElementById("table-info-content").innerHTML = 
            `<p class="text-center text-red-500">Network error</p>`;
    });
}

function closeTableInfoModal() {
    document.getElementById("table-info-modal").classList.add("hidden");
}

function showClearDatabaseModal() {
    document.getElementById("clear-database-modal").classList.remove("hidden");
    document.getElementById("security-password").focus();
}

function closeClearDatabaseModal() {
    document.getElementById("clear-database-modal").classList.add("hidden");
    document.getElementById("security-password").value = "";
}

function confirmClearDatabase() {
    const password = document.getElementById("security-password").value;
    
    if (!password.trim()) {
        showToast("Please enter security password", "error");
        return;
    }
    
    if (!confirm("Are you absolutely sure? This will permanently delete ALL data except admin accounts!")) {
        return;
    }
    
    showToast("Clearing database...", "info");
    
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=clear_database&security_password=" + encodeURIComponent(password)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Database cleared successfully! ${data.tables_cleared} tables cleared.`, "success");
            closeClearDatabaseModal();
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast("Clear failed: " + data.message, "error");
        }
    })
    .catch(() => showToast("Network error", "error"));
}
</script>
';

echo renderAdminLayout('Database Management', $content, $page_script);
?>