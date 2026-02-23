<?php
/**
 * Performance Optimization Helper
 * 
 * This file provides various optimizations for fast performance
 * even on slow/low network connections.
 */

// Start output buffering for compression
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}

// Set proper cache headers for static assets
function setCacheHeaders($seconds = 86400) { // 24 hours default
    header('Cache-Control: public, max-age=' . $seconds);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
}

// Disable ETags for simpler caching (reduces server load)
header('ETag: '');

// Remove X-Powered-By for security and smaller headers
header_remove('X-Powered-By');

// Enable slow query logging (for debugging only in development)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Compress JSON responses
if (function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
    if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
        // Already handled by ob_gzhandler
    }
}

// Optimize session handling
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);

// Reduce session garbage collection probability for better performance
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Prevent clickjacking
header('X-Frame-Options: SAMEORIGIN');

// XSS Protection
header('X-XSS-Protection: 1; mode=block');

// Content Security Policy (relaxed for local development)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://");

/**
 * Optimize database queries for better performance
 */
function optimizeQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('Query optimization error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get cached data or execute callback
 */
function getCachedOrExecute($pdo, $cache, $key, $callback, $ttl = 300) {
    // Try to get from cache first
    $cached = $cache->get($key, $ttl);
    if ($cached !== null) {
        return $cached;
    }
    
    // Execute callback and cache result
    $result = $callback();
    if ($result !== null) {
        $cache->set($key, $result);
    }
    
    return $result;
}

/**
 * Generate optimized JSON response
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Lazy load images with Intersection Observer
 */
function getLazyLoadScript() {
    return <<<SCRIPT
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Lazy load images using Intersection Observer
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                }
            });
        });
        
        document.querySelectorAll('img.lazy').forEach(function(img) {
            imageObserver.observe(img);
        });
    } else {
        // Fallback for older browsers
        document.querySelectorAll('img.lazy').forEach(function(img) {
            if (img.dataset.src) {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            }
        });
    }
    
    // Prefetch critical resources
    const criticalLinks = document.querySelectorAll('link[rel="preload"]');
    criticalLinks.forEach(function(link) {
        link.addEventListener('load', function() {
            this.rel = 'stylesheet';
        });
    });
});
</script>
SCRIPT;
}

/**
 * Add service worker registration for offline support
 */
function getServiceWorkerScript() {
    return <<<SCRIPT
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('service-worker.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(error) {
                console.log('ServiceWorker registration failed:', error);
            });
    });
}
</script>
SCRIPT;
}
