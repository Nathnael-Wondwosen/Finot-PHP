<?php
// includes/security_helpers.php

/**
 * Security Helper Functions for Student Management System
 */

class SecurityHelper {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate phone number (Ethiopian format)
     */
    public static function validatePhoneNumber($phone) {
        return preg_match('/^(\+?251|0)9\d{8}$/', $phone);
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Rate limiting for API calls
     */
    public static function checkRateLimit($identifier, $maxRequests = 60, $timeWindow = 3600) {
        $cacheKey = "rate_limit_" . md5($identifier);
        
        if (!isset($_SESSION[$cacheKey])) {
            $_SESSION[$cacheKey] = [
                'count' => 0,
                'start_time' => time()
            ];
        }
        
        $rateLimitData = $_SESSION[$cacheKey];
        
        // Reset if time window has passed
        if (time() - $rateLimitData['start_time'] > $timeWindow) {
            $_SESSION[$cacheKey] = [
                'count' => 1,
                'start_time' => time()
            ];
            return true;
        }
        
        // Check if limit exceeded
        if ($rateLimitData['count'] >= $maxRequests) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$cacheKey]['count']++;
        return true;
    }
    
    /**
     * Validate table name for database operations
     */
    public static function validateTableName($tableName) {
        $allowedTables = ['students', 'instrument_registrations', 'parents', 'admins'];
        return in_array($tableName, $allowedTables, true);
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id(),
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png'], $maxSize = 5242880) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Secure file name generation
     */
    public static function generateSecureFileName($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }
    
    /**
     * Check for suspicious patterns in input
     */
    public static function detectSuspiciousInput($input) {
        $suspiciousPatterns = [
            '/(<script|javascript:|on\w+=)/i',
            '/(union|select|insert|update|delete|drop|create|alter)\s/i',
            '/(\.\.|\/etc\/|\/bin\/|\.exe|\.bat|\.cmd)/i'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * Input Validation Class
 */
class InputValidator {
    
    public static function validateStudentData($data) {
        $errors = [];
        
        // Required fields
        $required = ['full_name', 'christian_name', 'gender', 'current_grade'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Field {$field} is required";
            }
        }
        
        // Validate full name (only letters, spaces, and Ethiopian characters)
        if (!empty($data['full_name']) && !preg_match('/^[a-zA-Z\s\p{Ethiopic}]+$/u', $data['full_name'])) {
            $errors[] = "Full name contains invalid characters";
        }
        
        // Validate phone number
        if (!empty($data['phone_number']) && !SecurityHelper::validatePhoneNumber($data['phone_number'])) {
            $errors[] = "Invalid phone number format";
        }
        
        // Validate email if provided
        if (!empty($data['email']) && !SecurityHelper::validateEmail($data['email'])) {
            $errors[] = "Invalid email format";
        }
        
        // Validate gender
        if (!empty($data['gender']) && !in_array($data['gender'], ['male', 'female'])) {
            $errors[] = "Invalid gender value";
        }
        
        return $errors;
    }
    
    public static function validateInstrumentData($data) {
        $errors = [];
        
        $required = ['instrument', 'full_name', 'phone_number'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Field {$field} is required";
            }
        }
        
        $allowedInstruments = ['begena', 'kebero', 'drum', 'piano', 'guitar'];
        if (!empty($data['instrument']) && !in_array($data['instrument'], $allowedInstruments)) {
            $errors[] = "Invalid instrument type";
        }
        
        return $errors;
    }
}
?>