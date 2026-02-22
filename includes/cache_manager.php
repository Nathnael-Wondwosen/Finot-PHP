<?php
/**
 * High-Performance Cache Manager for Finot-PHP
 * Provides file-based caching with automatic expiration
 */

class CacheManager {
    private static $instance = null;
    private $cacheDir;
    private $defaultTTL = 3600; // 1 hour default
    private $enabled = true;
    
    private function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        $this->enabled = is_writable($this->cacheDir);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate cache key from data
     */
    public function generateKey($data) {
        if (is_array($data) || is_object($data)) {
            $data = serialize($data);
        }
        return md5($data);
    }
    
    /**
     * Get cached data
     */
    public function get($key, $group = 'default') {
        if (!$this->enabled) return null;
        
        $file = $this->getCacheFile($key, $group);
        
        if (!file_exists($file)) return null;
        
        $data = unserialize(file_get_contents($file));
        
        // Check expiration
        if ($data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Set cached data
     */
    public function set($key, $value, $ttl = null, $group = 'default') {
        if (!$this->enabled) return false;
        
        $ttl = $ttl ?? $this->defaultTTL;
        $file = $this->cacheDir . $group . '_' . $this->generateKey($key) . '.cache';
        
        $data = [
            'expires' => time() + $ttl,
            'value' => $value
        ];
        
        return @file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }
    
    /**
     * Delete cached data
     */
    public function delete($key, $group = 'default') {
        $file = $this->getCacheFile($key, $group);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }
    
    /**
     * Clear all cache or specific group
     */
    public function clear($group = null) {
        if (!$this->enabled) return false;
        
        $pattern = $group ? $group . '_*.cache' : '*.cache';
        $files = glob($this->cacheDir . $pattern);
        
        $success = true;
        foreach ($files as $file) {
            if (!@unlink($file)) $success = false;
        }
        
        return $success;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $files = glob($this->cacheDir . '*.cache');
        $totalSize = 0;
        $count = 0;
        $expired = 0;
        
        foreach ($files as $file) {
            $count++;
            $totalSize += filesize($file);
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] < time()) $expired++;
        }
        
        return [
            'files' => $count,
            'size_bytes' => $totalSize,
            'size_mb' => round($totalSize / 1024 / 1024, 2),
            'expired' => $expired
        ];
    }
    
    /**
     * Cache database query result
     */
    public function remember($key, $callback, $ttl = null, $group = 'db_queries') {
        $cached = $this->get($key, $group);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl, $group);
        
        return $value;
    }
    
    private function getCacheFile($key, $group) {
        return $this->cacheDir . $group . '_' . $this->generateKey($key) . '.cache';
    }
}

/**
 * Global cache helper functions
 */
function cache_get($key, $group = 'default') {
    return CacheManager::getInstance()->get($key, $group);
}

function cache_set($key, $value, $ttl = null, $group = 'default') {
    return CacheManager::getInstance()->set($key, $value, $ttl, $group);
}

function cache_remember($key, $callback, $ttl = null, $group = 'db_queries') {
    return CacheManager::getInstance()->remember($key, $callback, $ttl, $group);
}

function cache_clear($group = null) {
    return CacheManager::getInstance()->clear($group);
}

function cache_delete($key, $group = 'default') {
    return CacheManager::getInstance()->delete($key, $group);
}
