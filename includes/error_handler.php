<?php
/**
 * Advanced Error Handling and Logging System
 */

class ErrorHandler {
    private static $pdo;
    private static $logFile;

    public static function initialize($pdo, $logFile = "logs/errors.log") {
        self::$pdo = $pdo;
        self::$logFile = $logFile;

        // Set error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', $logFile);

        // Register error handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError($errno, $errstr, $errfile, $errline) {
        $errorTypes = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];

        $errorType = $errorTypes[$errno] ?? 'UNKNOWN';

        self::logError($errorType, $errstr, $errfile, $errline, debug_backtrace());

        // Don't execute PHP internal error handler
        return true;
    }

    public static function handleException($exception) {
        self::logError(
            'EXCEPTION',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTrace()
        );

        self::displayErrorPage($exception);
    }

    public static function handleShutdown() {
        $error = error_get_last();
        if ($error !== null) {
            self::logError(
                'FATAL_ERROR',
                $error['message'],
                $error['file'],
                $error['line'],
                debug_backtrace()
            );
        }
    }

    private static function logError($level, $message, $file, $line, $context) {
        $errorData = [
            'error_level' => $level,
            'error_message' => $message,
            'error_file' => $file,
            'error_line' => $line,
            'error_context' => json_encode($context),
            'user_id' => $_SESSION['admin_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ];

        try {
            if (self::$pdo) {
                $stmt = self::$pdo->prepare('
                    INSERT INTO error_logs
                    (error_level, error_message, error_file, error_line, error_context,
                     user_id, ip_address, user_agent, request_uri, request_method)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute(array_values($errorData));
            }
        } catch (Exception $e) {
            // Fallback to file logging
            $logMessage = sprintf(
                '[%s] %s: %s in %s:%d\nContext: %s\n\n',
                date('Y-m-d H:i:s'),
                $level,
                $message,
                $file,
                $line,
                json_encode($context)
            );
            error_log($logMessage, 3, self::$logFile);
        }
    }

    private static function displayErrorPage($exception) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        if (getenv('APP_ENV') === 'production') {
            include 'templates/error_500.php';
        } else {
            echo '<h1>Application Error</h1>';
            echo '<p><strong>' . htmlspecialchars($exception->getMessage()) . '</strong></p>';
            echo '<p>File: ' . htmlspecialchars($exception->getFile()) . ' Line: ' . $exception->getLine() . '</p>';
            echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        }
        exit;
    }
}

// Initialize error handling
if (isset($pdo)) {
    ErrorHandler::initialize($pdo);
}
?>