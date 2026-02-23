<?php
/**
 * Production Deployment Automation Script for Finot-PHP
 * Handles automated deployment, environment setup, and production configuration
 */

class DeploymentManager {
    private $sourceDir;
    private $deployDir;
    private $backupDir;
    private $config;

    public function __construct($config = []) {
        $this->sourceDir = $config['source_dir'] ?? __DIR__;
        $this->deployDir = $config['deploy_dir'] ?? '/var/www/html/finot-php';
        $this->backupDir = $config['backup_dir'] ?? '/var/backups/finot-php';
        $this->config = $config;
    }

    /**
     * Run full deployment process
     */
    public function deploy($environment = 'production') {
        echo "🚀 Starting Finot-PHP Deployment to $environment\n";
        echo "================================================\n\n";

        try {
            $this->validateEnvironment();
            $this->createBackup();
            $this->setupDirectories();
            $this->copyFiles();
            $this->configureEnvironment($environment);
            $this->setupPermissions();
            $this->runDatabaseMigrations();
            $this->optimizeForProduction();
            $this->runPostDeployTests();
            $this->cleanup();

            echo "\n✅ Deployment completed successfully!\n";
            return true;

        } catch (Exception $e) {
            echo "\n❌ Deployment failed: " . $e->getMessage() . "\n";
            $this->rollback();
            return false;
        }
    }

    /**
     * Validate deployment environment
     */
    private function validateEnvironment() {
        echo "🔍 Validating deployment environment...\n";

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            throw new Exception('PHP 8.1.0 or higher is required');
        }

        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'mbstring', 'json', 'zip'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("PHP extension '$ext' is required but not loaded");
            }
        }

        // Check disk space
        $freeSpace = disk_free_space($this->deployDir);
        if ($freeSpace < 100 * 1024 * 1024) { // 100MB
            throw new Exception('Insufficient disk space for deployment');
        }

        // Check if deployment directory is writable
        if (!is_writable(dirname($this->deployDir))) {
            throw new Exception('Deployment directory is not writable');
        }

        echo "✅ Environment validation passed\n";
    }

    /**
     * Create backup of current deployment
     */
    private function createBackup() {
        echo "💾 Creating backup of current deployment...\n";

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        $backupName = 'pre-deploy-' . date('Y-m-d_H-i-s');
        $backupPath = $this->backupDir . '/' . $backupName;

        if (is_dir($this->deployDir)) {
            $this->copyDirectory($this->deployDir, $backupPath);
            echo "✅ Backup created: $backupName\n";
        } else {
            echo "ℹ️ No existing deployment found, skipping backup\n";
        }
    }

    /**
     * Setup deployment directories
     */
    private function setupDirectories() {
        echo "📁 Setting up deployment directories...\n";

        $directories = [
            $this->deployDir,
            $this->deployDir . '/uploads',
            $this->deployDir . '/backups',
            $this->deployDir . '/logs',
            $this->deployDir . '/cache',
            $this->deployDir . '/temp',
            $this->deployDir . '/assets/css/dist',
            $this->deployDir . '/assets/js/dist'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                echo "📁 Created directory: $dir\n";
            }
        }

        echo "✅ Directories setup completed\n";
    }

    /**
     * Copy application files
     */
    private function copyFiles() {
        echo "📋 Copying application files...\n";

        $excludePatterns = [
            '.git',
            'node_modules',
            'test_reports',
            '*.log',
            '.env.local',
            'deploy.php',
            'ultimate_optimizer.php',
            'security_hardener.php',
            'system_monitor.php',
            'backup_manager.php',
            'testing_framework.php'
        ];

        $this->copyDirectory($this->sourceDir, $this->deployDir, $excludePatterns);

        echo "✅ Files copied successfully\n";
    }

    /**
     * Configure environment-specific settings
     */
    private function configureEnvironment($environment) {
        echo "⚙️ Configuring $environment environment...\n";

        $configFile = $this->deployDir . '/config.php';

        if (!file_exists($configFile)) {
            throw new Exception('Config file not found in deployment');
        }

        $configContent = file_get_contents($configFile);

        // Environment-specific configurations
        switch ($environment) {
            case 'production':
                // Enable production optimizations
                $configContent = $this->configureProductionSettings($configContent);
                break;

            case 'staging':
                $configContent = $this->configureStagingSettings($configContent);
                break;

            case 'development':
                $configContent = $this->configureDevelopmentSettings($configContent);
                break;
        }

        file_put_contents($configFile, $configContent);

        // Create environment-specific .htaccess
        $this->createEnvironmentHtaccess($environment);

        echo "✅ Environment configuration completed\n";
    }

    /**
     * Configure production settings
     */
    private function configureProductionSettings($config) {
        // Enable OPcache
        $config = preg_replace(
            '/opcache\.enable\s*=\s*\d+/',
            'opcache.enable=1',
            $config
        );

        // Enable JIT compilation
        $config = preg_replace(
            '/opcache\.jit\s*=\s*\w+/',
            'opcache.jit=on',
            $config
        );

        // Set production error reporting
        $config .= "\n// Production error settings\n";
        $config .= "ini_set('display_errors', 0);\n";
        $config .= "ini_set('log_errors', 1);\n";
        $config .= "error_reporting(E_ALL & ~E_DEPRECATED);\n";

        return $config;
    }

    /**
     * Configure staging settings
     */
    private function configureStagingSettings($config) {
        $config .= "\n// Staging environment settings\n";
        $config .= "ini_set('display_errors', 1);\n";
        $config .= "error_reporting(E_ALL);\n";
        $config .= "define('APP_ENV', 'staging');\n";

        return $config;
    }

    /**
     * Configure development settings
     */
    private function configureDevelopmentSettings($config) {
        $config .= "\n// Development environment settings\n";
        $config .= "ini_set('display_errors', 1);\n";
        $config .= "error_reporting(E_ALL);\n";
        $config .= "define('APP_ENV', 'development');\n";

        return $config;
    }

    /**
     * Create environment-specific .htaccess
     */
    private function createEnvironmentHtaccess($environment) {
        $htaccess = $this->deployDir . '/.htaccess';

        $content = "# Finot-PHP $environment environment configuration\n";
        $content .= "# Generated on " . date('Y-m-d H:i:s') . "\n\n";

        // Basic Apache configuration
        $content .= "RewriteEngine On\n";
        $content .= "RewriteBase /\n\n";

        // Security headers
        $content .= "# Security Headers\n";
        $content .= "Header always set X-Frame-Options SAMEORIGIN\n";
        $content .= "Header always set X-Content-Type-Options nosniff\n";
        $content .= "Header always set X-XSS-Protection \"1; mode=block\"\n";
        $content .= "Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n\n";

        // Compression
        $content .= "# Enable compression\n";
        $content .= "AddOutputFilterByType DEFLATE text/plain\n";
        $content .= "AddOutputFilterByType DEFLATE text/html\n";
        $content .= "AddOutputFilterByType DEFLATE text/xml\n";
        $content .= "AddOutputFilterByType DEFLATE text/css\n";
        $content .= "AddOutputFilterByType DEFLATE application/xml\n";
        $content .= "AddOutputFilterByType DEFLATE application/xhtml+xml\n";
        $content .= "AddOutputFilterByType DEFLATE application/rss+xml\n";
        $content .= "AddOutputFilterByType DEFLATE application/javascript\n";
        $content .= "AddOutputFilterByType DEFLATE application/x-javascript\n\n";

        // Browser caching
        $content .= "# Browser caching\n";
        $content .= "ExpiresActive On\n";
        $content .= "ExpiresByType text/css \"access plus 1 year\"\n";
        $content .= "ExpiresByType application/javascript \"access plus 1 year\"\n";
        $content .= "ExpiresByType image/png \"access plus 1 year\"\n";
        $content .= "ExpiresByType image/jpg \"access plus 1 year\"\n";
        $content .= "ExpiresByType image/jpeg \"access plus 1 year\"\n";
        $content .= "ExpiresByType image/gif \"access plus 1 year\"\n";
        $content .= "ExpiresByType image/webp \"access plus 1 year\"\n\n";

        // Environment-specific settings
        if ($environment === 'production') {
            $content .= "# Production-specific settings\n";
            $content .= "php_value upload_max_filesize 10M\n";
            $content .= "php_value post_max_size 12M\n";
            $content .= "php_value memory_limit 256M\n";
            $content .= "php_value max_execution_time 300\n\n";

            // SSL enforcement (uncomment when SSL is available)
            // $content .= "# Force HTTPS\n";
            // $content .= "RewriteCond %{HTTPS} off\n";
            // $content .= "RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n\n";
        }

        // Protect sensitive files
        $content .= "# Protect sensitive files\n";
        $content .= "RewriteRule ^(config\.php|.*\.log|backups/.*)$ - [F,L]\n\n";

        // Front controller for clean URLs
        $content .= "# Front controller\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $content .= "RewriteRule . index.php [L]\n";

        file_put_contents($htaccess, $content);
    }

    /**
     * Setup proper file permissions
     */
    private function setupPermissions() {
        echo "🔐 Setting up file permissions...\n";

        // Set directory permissions
        $writableDirs = [
            $this->deployDir . '/uploads',
            $this->deployDir . '/backups',
            $this->deployDir . '/logs',
            $this->deployDir . '/cache',
            $this->deployDir . '/temp'
        ];

        foreach ($writableDirs as $dir) {
            chmod($dir, 0755);
        }

        // Set file permissions
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->deployDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                chmod($file->getPathname(), 0644);
            }
        }

        // Make PHP files executable
        $phpFiles = glob($this->deployDir . '/*.php');
        foreach ($phpFiles as $file) {
            chmod($file, 0644);
        }

        echo "✅ Permissions setup completed\n";
    }

    /**
     * Run database migrations
     */
    private function runDatabaseMigrations() {
        echo "🗄️ Running database migrations...\n";

        // This would typically run migration scripts
        // For now, we'll just check database connectivity

        try {
            // Include config to get database connection
            require_once $this->deployDir . '/config.php';

            $stmt = $pdo->query('SELECT 1');
            $result = $stmt->fetch();

            if ($result[0] === 1) {
                echo "✅ Database connection verified\n";
            } else {
                throw new Exception('Database connection test failed');
            }

        } catch (Exception $e) {
            throw new Exception('Database migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Optimize for production
     */
    private function optimizeForProduction() {
        echo "⚡ Running production optimizations...\n";

        // Clear any existing cache
        $cacheDir = $this->deployDir . '/cache';
        if (is_dir($cacheDir)) {
            $this->clearDirectory($cacheDir);
        }

        // Pre-compile key PHP files (if OPcache is available)
        if (function_exists('opcache_compile_file')) {
            $keyFiles = [
                $this->deployDir . '/config.php',
                $this->deployDir . '/includes/database.php',
                $this->deployDir . '/includes/functions.php'
            ];

            foreach ($keyFiles as $file) {
                if (file_exists($file)) {
                    opcache_compile_file($file);
                }
            }
        }

        // Generate optimized autoloader (if using Composer)
        if (file_exists($this->deployDir . '/vendor/autoload.php')) {
            // Optimize Composer autoloader
            shell_exec('cd ' . escapeshellarg($this->deployDir) . ' && composer dump-autoload --optimize');
        }

        echo "✅ Production optimizations completed\n";
    }

    /**
     * Run post-deployment tests
     */
    private function runPostDeployTests() {
        echo "🧪 Running post-deployment tests...\n";

        // Test basic functionality
        $tests = [
            'config.php' => file_exists($this->deployDir . '/config.php'),
            'index.php' => file_exists($this->deployDir . '/index.php'),
            'uploads_dir' => is_writable($this->deployDir . '/uploads'),
            'logs_dir' => is_writable($this->deployDir . '/logs')
        ];

        $failedTests = [];
        foreach ($tests as $test => $result) {
            if (!$result) {
                $failedTests[] = $test;
            }
        }

        if (!empty($failedTests)) {
            throw new Exception('Post-deployment tests failed: ' . implode(', ', $failedTests));
        }

        echo "✅ Post-deployment tests passed\n";
    }

    /**
     * Cleanup temporary files
     */
    private function cleanup() {
        echo "🧹 Cleaning up temporary files...\n";

        // Remove development files
        $devFiles = [
            $this->deployDir . '/deploy.php',
            $this->deployDir . '/README.md',
            $this->deployDir . '/.gitignore'
        ];

        foreach ($devFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        echo "✅ Cleanup completed\n";
    }

    /**
     * Rollback deployment on failure
     */
    private function rollback() {
        echo "🔄 Rolling back deployment...\n";

        // Find latest backup
        $backups = glob($this->backupDir . '/pre-deploy-*');
        if (!empty($backups)) {
            rsort($backups);
            $latestBackup = $backups[0];

            // Remove failed deployment
            $this->clearDirectory($this->deployDir);

            // Restore from backup
            $this->copyDirectory($latestBackup, $this->deployDir);

            echo "✅ Rollback completed using backup: " . basename($latestBackup) . "\n";
        } else {
            echo "⚠️ No backup found for rollback\n";
        }
    }

    /**
     * Utility methods
     */
    private function copyDirectory($src, $dst, $excludePatterns = []) {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            // Check exclude patterns
            $excluded = false;
            foreach ($excludePatterns as $pattern) {
                if (fnmatch($pattern, $file) || fnmatch($pattern, $srcPath)) {
                    $excluded = true;
                    break;
                }
            }

            if ($excluded) continue;

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath, $excludePatterns);
            } else {
                copy($srcPath, $dstPath);
            }
        }

        closedir($dir);
    }

    private function clearDirectory($dir) {
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
    }

    /**
     * Get deployment status
     */
    public function getDeploymentStatus() {
        $status = [
            'environment' => 'unknown',
            'version' => 'unknown',
            'last_deployed' => 'unknown',
            'health_checks' => []
        ];

        // Check if deployment exists
        if (is_dir($this->deployDir)) {
            $status['deployed'] = true;

            // Check version file
            $versionFile = $this->deployDir . '/version.txt';
            if (file_exists($versionFile)) {
                $status['version'] = trim(file_get_contents($versionFile));
            }

            // Check deployment timestamp
            $deployFile = $this->deployDir . '/.deployed';
            if (file_exists($deployFile)) {
                $status['last_deployed'] = date('Y-m-d H:i:s', filemtime($deployFile));
            }

            // Basic health checks
            $status['health_checks'] = [
                'config_exists' => file_exists($this->deployDir . '/config.php'),
                'index_exists' => file_exists($this->deployDir . '/index.php'),
                'uploads_writable' => is_writable($this->deployDir . '/uploads'),
                'logs_writable' => is_writable($this->deployDir . '/logs'),
                'cache_writable' => is_writable($this->deployDir . '/cache')
            ];
        } else {
            $status['deployed'] = false;
        }

        return $status;
    }

    /**
     * Create deployment report
     */
    public function createDeploymentReport($success, $details = []) {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => $this->config['environment'] ?? 'production',
            'success' => $success,
            'details' => $details,
            'server_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
            ]
        ];

        $reportFile = $this->deployDir . '/deployment_report_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));

        return $report;
    }
}

// Handle command line deployment
if (php_sapi_name() === 'cli') {
    $options = getopt('', ['env:', 'source:', 'deploy:']);

    $config = [
        'environment' => $options['env'] ?? 'production',
        'source_dir' => $options['source'] ?? __DIR__,
        'deploy_dir' => $options['deploy'] ?? '/var/www/html/finot-php'
    ];

    $deployer = new DeploymentManager($config);
    $success = $deployer->deploy($config['environment']);

    $report = $deployer->createDeploymentReport($success);

    exit($success ? 0 : 1);
}

// Web-based deployment (with authentication)
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    session_start();

    // Require admin authentication
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? 'status';

    $deployer = new DeploymentManager();

    try {
        switch ($action) {
            case 'deploy':
                $environment = $_POST['environment'] ?? 'production';
                $success = $deployer->deploy($environment);
                $result = [
                    'success' => $success,
                    'message' => $success ? 'Deployment completed successfully!' : 'Deployment failed!'
                ];
                break;

            case 'status':
                $result = $deployer->getDeploymentStatus();
                break;

            default:
                throw new Exception('Invalid action');
        }

        header('Content-Type: application/json');
        echo json_encode($result);

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Operation failed: ' . $e->getMessage()
        ]);
    }
}
?>