<?php
/**
 * Asset Optimization Script
 * Minifies and combines CSS/JS files for better performance
 */

// Check if user is admin
session_start();
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    die('Access denied. Admin login required.');
}

echo "<h2>Asset Optimization</h2>";

// Create minified versions of CSS and JS files
$assetsDir = __DIR__ . '/assets/';
if (!is_dir($assetsDir)) {
    mkdir($assetsDir, 0755, true);
}

// Function to minify CSS
function minifyCSS($css) {
    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    // Remove space after colons
    $css = str_replace(': ', ':', $css);
    // Remove whitespace
    $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
    return $css;
}

// Function to minify JavaScript
function minifyJS($js) {
    // Remove comments (simple approach)
    $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
    $js = preg_replace('!//.*!', '', $js);
    // Remove whitespace
    $js = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $js);
    return $js;
}

// Combine and minify students.js
$studentsJsPath = __DIR__ . '/js/students.js';
if (file_exists($studentsJsPath)) {
    $jsContent = file_get_contents($studentsJsPath);
    $minifiedJs = minifyJS($jsContent);
    
    $optimizedJsPath = $assetsDir . 'students.min.js';
    if (file_put_contents($optimizedJsPath, $minifiedJs)) {
        echo "<p style='color: green;'>✓ Created optimized JavaScript: " . basename($optimizedJsPath) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create optimized JavaScript</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ students.js not found</p>";
}

echo "<h3>Optimization Complete</h3>";
echo "<p>Asset optimization helps reduce loading times by minimizing file sizes.</p>";
echo "<p><a href='index.php'>Back to Dashboard</a></p>";
?>