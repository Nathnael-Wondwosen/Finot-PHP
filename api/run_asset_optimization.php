<?php
/**
 * AJAX handler for asset optimization
 */

require_once '../config.php';

header('Content-Type: application/json');

// Admin check
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin login required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    require_once '../assets/optimize_assets.php';
    $optimizer = new AssetOptimizer('../assets/');

    // Keep file list in sync with asset_optimizer.php
    $jsFiles = [
        'js/students.js',
        'js/enhanced-edit-drawer.js',
        'js/instrument-edit-drawer.js',
        'js/column-customizer.js',
        'js/ethiopian-calendar-filter.js'
    ];

    $processedJS = [];
    foreach ($jsFiles as $file) {
        $path = "../{$file}";
        if (file_exists($path)) {
            $optimizer->addJSFile($path);
            $processedJS[] = $file;
        }
    }

    // CSS files (none configured yet, placeholder for future)
    $result = $optimizer->saveOptimizedAssets();

    echo json_encode([
        'success' => (bool)($result['js'] || $result['css']),
        'js_success' => (bool)$result['js'],
        'css_success' => (bool)$result['css'],
        'js_size' => (int)$result['js_size'],
        'css_size' => (int)$result['css_size'],
        'processed_js' => $processedJS
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
