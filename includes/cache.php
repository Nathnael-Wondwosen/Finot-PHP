<?php
/**
 * Enhanced caching system with multiple backends
 */

class EnhancedCache {
    private $cacheDir;
    private $memoryCache = [];
    private $memoryCacheTimestamps = [];
    
    public function __construct($cacheDir = null) {
        $this->cacheDir = $cacheDir ?: __DIR__ . '/../cache/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Generate a cache key from input parameters
     */
    private function generateKey($key) {
        return md5(is_array($key) ? json_encode($key) : $key);
    }
    
    /**
     * Get cached data with memory caching layer
     */
    public function get($key, $ttl = 300) { // Default 5 minutes TTL
        $cacheKey = $this->generateKey($key);
        
        // Check memory cache first (fastest)
        if (isset($this->memoryCache[$cacheKey])) {
            $cacheTime = $this->memoryCacheTimestamps[$cacheKey];
            if (time() - $cacheTime < $ttl) {
                return $this->memoryCache[$cacheKey];
            } else {
                // Expired, remove from memory
                unset($this->memoryCache[$cacheKey]);
                unset($this->memoryCacheTimestamps[$cacheKey]);
            }
        }
        
        // Check file cache
        $cacheFile = $this->cacheDir . $cacheKey . '.cache';
        
        if (file_exists($cacheFile)) {
            $cacheTime = filemtime($cacheFile);
            if (time() - $cacheTime < $ttl) {
                $data = file_get_contents($cacheFile);
                $unserializedData = unserialize($data);
                
                // Store in memory cache for next access
                $this->memoryCache[$cacheKey] = $unserializedData;
                $this->memoryCacheTimestamps[$cacheKey] = $cacheTime;
                
                return $unserializedData;
            } else {
                // Expired, remove the file
                unlink($cacheFile);
            }
        }
        
        return null;
    }
    
    /**
     * Store data in cache with both memory and file layers
     */
    public function set($key, $data) {
        $cacheKey = $this->generateKey($key);
        
        // Store in memory cache
        $this->memoryCache[$cacheKey] = $data;
        $this->memoryCacheTimestamps[$cacheKey] = time();
        
        // Store in file cache
        $cacheFile = $this->cacheDir . $cacheKey . '.cache';
        
        $serializedData = serialize($data);
        return file_put_contents($cacheFile, $serializedData, LOCK_EX) !== false;
    }
    
    /**
     * Delete cached data
     */
    public function delete($key) {
        $cacheKey = $this->generateKey($key);
        
        // Remove from memory cache
        unset($this->memoryCache[$cacheKey]);
        unset($this->memoryCacheTimestamps[$cacheKey]);
        
        // Remove from file cache
        $cacheFile = $this->cacheDir . $cacheKey . '.cache';
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        // Clear memory cache
        $this->memoryCache = [];
        $this->memoryCacheTimestamps = [];
        
        // Clear file cache
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $files = glob($this->cacheDir . '*.cache');
        $totalSize = 0;
        foreach ($files as $file) {
            $totalSize += filesize($file);
        }
        
        return [
            'count' => count($files),
            'memory_count' => count($this->memoryCache),
            'size' => $totalSize,
            'size_formatted' => $this->formatBytes($totalSize)
        ];
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUp($key, $callback, $ttl = 300) {
        $data = $this->get($key, $ttl);
        if ($data === null) {
            $data = $callback();
            $this->set($key, $data);
        }
        return $data;
    }
}

// Initialize global cache instance
$cache = new EnhancedCache();

// Helper functions for easier usage
function cache_get($key, $ttl = 300) {
    global $cache;
    return $cache->get($key, $ttl);
}

function cache_set($key, $data) {
    global $cache;
    return $cache->set($key, $data);
}

function cache_delete($key) {
    global $cache;
    return $cache->delete($key);
}

function cache_clear() {
    global $cache;
    return $cache->clear();
}

function cache_warmup($key, $callback, $ttl = 300) {
    global $cache;
    return $cache->warmUp($key, $callback, $ttl);
}
?>