<?php
/**
 * Advanced Backup and Recovery System for Finot-PHP
 * Provides automated backups, recovery procedures, and data integrity checks
 */

require_once 'config.php';

class BackupManager {
    private $pdo;
    private $backupDir;
    private $retentionDays;

    public function __construct($pdo, $backupDir = 'backups/', $retentionDays = 30) {
        $this->pdo = $pdo;
        $this->backupDir = $backupDir;
        $this->retentionDays = $retentionDays;

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
    }

    /**
     * Create comprehensive backup
     */
    public function createFullBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $backupId = uniqid('backup_', true);

        $backup = [
            'id' => $backupId,
            'timestamp' => $timestamp,
            'type' => 'full',
            'files' => [],
            'database' => [],
            'status' => 'in_progress'
        ];

        try {
            // Create backup directory
            $backupPath = $this->backupDir . $backupId . '/';
            mkdir($backupPath, 0755, true);

            // Backup database
            $backup['database'] = $this->backupDatabase($backupPath);

            // Backup files
            $backup['files'] = $this->backupFiles($backupPath);

            // Create backup manifest
            $backup['status'] = 'completed';
            $backup['size'] = $this->calculateBackupSize($backupPath);
            $backup['checksum'] = $this->generateBackupChecksum($backupPath);

            file_put_contents($backupPath . 'manifest.json', json_encode($backup, JSON_PRETTY_PRINT));

            // Log backup creation
            $this->logBackupOperation('create', $backupId, 'success', $backup);

            return $backup;

        } catch (Exception $e) {
            $backup['status'] = 'failed';
            $backup['error'] = $e->getMessage();

            $this->logBackupOperation('create', $backupId, 'failed', $backup);

            // Cleanup failed backup
            $this->cleanupFailedBackup($backupPath);

            throw $e;
        }
    }

    /**
     * Backup database with compression
     */
    private function backupDatabase($backupPath) {
        $dbBackup = [
            'tables' => [],
            'total_records' => 0,
            'compressed_size' => 0
        ];

        // Get all tables
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $tableInfo = $this->backupTable($table, $backupPath);
            $dbBackup['tables'][] = $tableInfo;
            $dbBackup['total_records'] += $tableInfo['records'];
        }

        // Create compressed database dump
        $dbFile = $backupPath . 'database.sql.gz';
        $sql = $this->generateDatabaseDump($tables);
        file_put_contents('compress.zlib://' . $dbFile, $sql);

        $dbBackup['compressed_size'] = filesize($dbFile);
        $dbBackup['dump_file'] = 'database.sql.gz';

        return $dbBackup;
    }

    /**
     * Backup individual table
     */
    private function backupTable($tableName, $backupPath) {
        // Get table structure
        $stmt = $this->pdo->query("SHOW CREATE TABLE `$tableName`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get record count
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM `$tableName`");
        $recordCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Get table data in chunks to handle large tables
        $tableFile = $backupPath . "table_{$tableName}.json.gz";
        $data = [];

        $offset = 0;
        $chunkSize = 1000;

        while (true) {
            $stmt = $this->pdo->prepare("SELECT * FROM `$tableName` LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $chunkSize, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) break;

            $data = array_merge($data, $rows);
            $offset += $chunkSize;

            // Prevent memory exhaustion for very large tables
            if (count($data) >= 10000) {
                $this->writeCompressedData($tableFile, $data, false);
                $data = [];
            }
        }

        if (!empty($data)) {
            $this->writeCompressedData($tableFile, $data, true);
        }

        return [
            'name' => $tableName,
            'structure' => $createTable['Create Table'],
            'records' => $recordCount,
            'data_file' => "table_{$tableName}.json.gz",
            'data_size' => filesize($tableFile)
        ];
    }

    /**
     * Write compressed data to file
     */
    private function writeCompressedData($filePath, $data, $append = false) {
        $jsonData = json_encode($data) . "\n";
        $flag = $append ? FILE_APPEND : 0;
        file_put_contents('compress.zlib://' . $filePath, $jsonData, $flag);
    }

    /**
     * Generate SQL dump for database structure
     */
    private function generateDatabaseDump($tables) {
        $sql = "-- Finot-PHP Database Backup\n";
        $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);

            $sql .= "-- Table: $table\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createTable['Create Table'] . ";\n\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        return $sql;
    }

    /**
     * Backup important files
     */
    private function backupFiles($backupPath) {
        $filesToBackup = [
            'config.php',
            'includes/',
            'assets/',
            'js/',
            'images/',
            'uploads/',
            'database/',
            'index.php',
            'login.php',
            'dashboard.php',
            'students.php'
        ];

        $fileBackup = [
            'directories' => [],
            'total_files' => 0,
            'total_size' => 0
        ];

        foreach ($filesToBackup as $item) {
            if (is_dir($item)) {
                $dirInfo = $this->backupDirectory($item, $backupPath);
                $fileBackup['directories'][] = $dirInfo;
                $fileBackup['total_files'] += $dirInfo['files'];
                $fileBackup['total_size'] += $dirInfo['size'];
            } elseif (is_file($item)) {
                $this->backupSingleFile($item, $backupPath);
                $fileBackup['total_files']++;
                $fileBackup['total_size'] += filesize($item);
            }
        }

        return $fileBackup;
    }

    /**
     * Backup directory with compression
     */
    private function backupDirectory($dir, $backupPath) {
        $archiveName = str_replace('/', '_', trim($dir, '/')) . '.tar.gz';
        $archivePath = $backupPath . $archiveName;

        // Use PHP's Phar for creating tar.gz archives
        $phar = new PharData($archivePath);

        // Add directory to archive
        $phar->buildFromDirectory($dir);

        // Compress to gzip
        $phar->compress(Phar::GZ);

        // Remove uncompressed archive
        unlink($archivePath);

        $compressedPath = $archivePath . '.gz';
        $size = filesize($compressedPath);

        // Count files in directory
        $fileCount = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile()) $fileCount++;
        }

        return [
            'directory' => $dir,
            'archive' => $archiveName . '.gz',
            'files' => $fileCount,
            'size' => $size
        ];
    }

    /**
     * Backup single file
     */
    private function backupSingleFile($file, $backupPath) {
        $fileName = basename($file);
        $backupFile = $backupPath . 'files/' . $fileName;

        if (!is_dir($backupPath . 'files/')) {
            mkdir($backupPath . 'files/', 0755, true);
        }

        copy($file, $backupFile);
    }

    /**
     * Calculate total backup size
     */
    private function calculateBackupSize($backupPath) {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backupPath));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Generate backup checksum for integrity verification
     */
    private function generateBackupChecksum($backupPath) {
        $checksums = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backupPath));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($backupPath, '', $file->getPathname());
                $checksums[$relativePath] = hash_file('sha256', $file->getPathname());
            }
        }

        return $checksums;
    }

    /**
     * Restore from backup
     */
    public function restoreBackup($backupId) {
        $backupPath = $this->backupDir . $backupId . '/';

        if (!is_dir($backupPath)) {
            throw new Exception("Backup $backupId not found");
        }

        $manifest = json_decode(file_get_contents($backupPath . 'manifest.json'), true);

        if (!$manifest) {
            throw new Exception("Invalid backup manifest");
        }

        try {
            // Verify backup integrity
            $this->verifyBackupIntegrity($backupPath, $manifest);

            // Restore database
            $this->restoreDatabase($backupPath, $manifest['database']);

            // Restore files
            $this->restoreFiles($backupPath, $manifest['files']);

            // Log successful restore
            $this->logBackupOperation('restore', $backupId, 'success', $manifest);

            return [
                'status' => 'success',
                'message' => 'Backup restored successfully',
                'backup_id' => $backupId
            ];

        } catch (Exception $e) {
            $this->logBackupOperation('restore', $backupId, 'failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Restore database from backup
     */
    private function restoreDatabase($backupPath, $dbManifest) {
        // Restore from compressed SQL dump
        $sqlFile = $backupPath . $dbManifest['dump_file'];
        $sql = file_get_contents('compress.zlib://' . $sqlFile);

        // Execute SQL dump
        $this->pdo->exec($sql);

        // Alternatively, restore from table JSON files
        foreach ($dbManifest['tables'] as $table) {
            $this->restoreTableData($backupPath, $table);
        }
    }

    /**
     * Restore table data from JSON backup
     */
    private function restoreTableData($backupPath, $tableInfo) {
        $dataFile = $backupPath . $tableInfo['data_file'];

        if (!file_exists($dataFile)) return;

        // Read compressed JSON data
        $jsonData = file_get_contents('compress.zlib://' . $dataFile);
        $lines = explode("\n", trim($jsonData));

        foreach ($lines as $line) {
            if (empty($line)) continue;

            $data = json_decode($line, true);
            if (!$data) continue;

            $this->insertBatchData($tableInfo['name'], $data);
        }
    }

    /**
     * Insert batch data into table
     */
    private function insertBatchData($tableName, $data) {
        if (empty($data)) return;

        $columns = array_keys($data[0]);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';

        $sql = "INSERT INTO `$tableName` (" . implode(',', $columns) . ") VALUES ($placeholders)";

        $stmt = $this->pdo->prepare($sql);

        foreach ($data as $row) {
            $values = array_values($row);
            $stmt->execute($values);
        }
    }

    /**
     * Restore files from backup
     */
    private function restoreFiles($backupPath, $filesManifest) {
        // Extract directory archives
        foreach ($filesManifest['directories'] as $dir) {
            $archivePath = $backupPath . $dir['archive'];

            if (file_exists($archivePath)) {
                $phar = new PharData($archivePath);
                $phar->extractTo('.', null, true);
            }
        }

        // Restore individual files
        $filesDir = $backupPath . 'files/';
        if (is_dir($filesDir)) {
            $iterator = new DirectoryIterator($filesDir);
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    copy($file->getPathname(), $file->getFilename());
                }
            }
        }
    }

    /**
     * Verify backup integrity
     */
    private function verifyBackupIntegrity($backupPath, $manifest) {
        $currentChecksums = $this->generateBackupChecksum($backupPath);

        if ($currentChecksums !== $manifest['checksum']) {
            throw new Exception("Backup integrity check failed");
        }
    }

    /**
     * Cleanup old backups
     */
    public function cleanupOldBackups() {
        $backups = glob($this->backupDir . 'backup_*/');

        foreach ($backups as $backup) {
            $manifestFile = $backup . 'manifest.json';

            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true);

                if (isset($manifest['timestamp'])) {
                    $backupDate = strtotime($manifest['timestamp']);

                    if (time() - $backupDate > $this->retentionDays * 24 * 60 * 60) {
                        $this->deleteDirectory($backup);
                        $this->logBackupOperation('cleanup', basename($backup), 'success');
                    }
                }
            }
        }
    }

    /**
     * List available backups
     */
    public function listBackups() {
        $backups = [];

        $dirs = glob($this->backupDir . 'backup_*/');

        foreach ($dirs as $dir) {
            $manifestFile = $dir . 'manifest.json';

            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true);

                if ($manifest) {
                    $backups[] = [
                        'id' => $manifest['id'],
                        'timestamp' => $manifest['timestamp'],
                        'type' => $manifest['type'],
                        'status' => $manifest['status'],
                        'size' => isset($manifest['size']) ? $this->formatBytes($manifest['size']) : 'Unknown',
                        'path' => $dir
                    ];
                }
            }
        }

        // Sort by timestamp descending
        usort($backups, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return $backups;
    }

    /**
     * Log backup operations
     */
    private function logBackupOperation($operation, $backupId, $status, $details = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO backup_logs
                (operation, backup_id, status, details, performed_by, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $operation,
                $backupId,
                $status,
                json_encode($details),
                $_SESSION['admin_id'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Backup logging failed: " . $e->getMessage());
        }
    }

    /**
     * Create backup logs table
     */
    public function createBackupLogsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS backup_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                operation VARCHAR(50) NOT NULL,
                backup_id VARCHAR(100) NOT NULL,
                status VARCHAR(20) NOT NULL,
                details JSON,
                performed_by INT DEFAULT NULL,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_operation (operation),
                INDEX idx_backup_id (backup_id),
                INDEX idx_created_at (created_at)
            )
        ");
    }

    /**
     * Utility methods
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }

    private function cleanupFailedBackup($backupPath) {
        if (is_dir($backupPath)) {
            $this->deleteDirectory($backupPath);
        }
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats() {
        $backups = $this->listBackups();

        $stats = [
            'total_backups' => count($backups),
            'successful_backups' => 0,
            'failed_backups' => 0,
            'total_size' => 0,
            'oldest_backup' => null,
            'newest_backup' => null
        ];

        foreach ($backups as $backup) {
            if ($backup['status'] === 'completed') {
                $stats['successful_backups']++;
            } elseif ($backup['status'] === 'failed') {
                $stats['failed_backups']++;
            }

            if (isset($backup['size'])) {
                $stats['total_size'] += $this->parseBytes($backup['size']);
            }

            $timestamp = strtotime($backup['timestamp']);

            if (!$stats['oldest_backup'] || $timestamp < strtotime($stats['oldest_backup'])) {
                $stats['oldest_backup'] = $backup['timestamp'];
            }

            if (!$stats['newest_backup'] || $timestamp > strtotime($stats['newest_backup'])) {
                $stats['newest_backup'] = $backup['timestamp'];
            }
        }

        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);

        return $stats;
    }

    private function parseBytes($sizeString) {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1024*1024, 'GB' => 1024*1024*1024, 'TB' => 1024*1024*1024*1024];

        preg_match('/^(\d+(?:\.\d+)?)\s*(B|KB|MB|GB|TB)$/i', $sizeString, $matches);

        if (count($matches) === 3) {
            return $matches[1] * $units[strtoupper($matches[2])];
        }

        return 0;
    }
}

// Initialize backup system if called directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    if (!isset($_SESSION['admin_id'])) {
        die('Admin access required');
    }

    $backupManager = new BackupManager($pdo);

    // Create backup logs table
    $backupManager->createBackupLogsTable();

    // Handle different actions
    $action = $_GET['action'] ?? 'create';

    try {
        switch ($action) {
            case 'create':
                $result = $backupManager->createFullBackup();
                $message = 'Backup created successfully!';
                break;

            case 'list':
                $result = $backupManager->listBackups();
                $message = 'Backups retrieved successfully!';
                break;

            case 'stats':
                $result = $backupManager->getBackupStats();
                $message = 'Backup statistics retrieved successfully!';
                break;

            case 'cleanup':
                $backupManager->cleanupOldBackups();
                $result = ['message' => 'Old backups cleaned up'];
                $message = 'Cleanup completed successfully!';
                break;

            default:
                throw new Exception('Invalid action');
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $result
        ]);

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Operation failed: ' . $e->getMessage()
        ]);
    }
}
?>