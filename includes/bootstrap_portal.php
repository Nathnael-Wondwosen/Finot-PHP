<?php
/**
 * Portal bootstrap with fixed root-path resolution for maximum speed.
 * - Uses O(1) root resolution
 * - Loads config and helper files when present
 * - Provides minimal fallback helpers if a file is missing
 */

if (!defined('FINOT_PORTAL_BOOTSTRAPPED')) {
    define('FINOT_PORTAL_BOOTSTRAPPED', true);

    // Override with FINOT_APP_ROOT if set externally, otherwise use project root from includes/.
    $finotRoot = defined('FINOT_APP_ROOT') ? (string)FINOT_APP_ROOT : dirname(__DIR__);
    if (!is_file($finotRoot . '/config.php')) {
        http_response_code(500);
        die('Bootstrap error: config.php not found at fixed root: ' . $finotRoot);
    }

    require_once $finotRoot . '/config.php';

    $securityPath = $finotRoot . '/includes/security_helpers.php';
    if (is_file($securityPath)) {
        require_once $securityPath;
    } else {
        if (!class_exists('SecurityHelper')) {
            class SecurityHelper {
                public static function generateCSRFToken() {
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        @session_start();
                    }
                    if (!isset($_SESSION['csrf_token'])) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                    return $_SESSION['csrf_token'];
                }

                public static function verifyCSRFToken($token) {
                    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
                }

                public static function sanitizeInput($data) {
                    if (is_array($data)) {
                        return array_map([self::class, 'sanitizeInput'], $data);
                    }
                    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
                }
            }
        }
    }

    $portalAuthPath = $finotRoot . '/includes/portal_auth.php';
    if (is_file($portalAuthPath)) {
        require_once $portalAuthPath;
    } else {
        if (!function_exists('portalUserIsLoggedIn')) {
            function portalUserIsLoggedIn() {
                return isset($_SESSION['portal_user_id'], $_SESSION['portal_role'], $_SESSION['portal_teacher_id']);
            }
        }

        if (!function_exists('requirePortalRoles')) {
            function requirePortalRoles(array $roles) {
                if (!portalUserIsLoggedIn()) {
                    header('Location: login.php');
                    exit;
                }
                $role = (string)($_SESSION['portal_role'] ?? '');
                if (!in_array($role, $roles, true)) {
                    http_response_code(403);
                    exit('Access denied');
                }
            }
        }

        if (!function_exists('isAdminSession')) {
            function isAdminSession() {
                return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
            }
        }

        if (!function_exists('getAuthContext')) {
            function getAuthContext() {
                if (isAdminSession()) {
                    return [
                        'actor' => 'admin',
                        'admin_id' => (int)$_SESSION['admin_id'],
                        'portal_user_id' => null,
                        'portal_role' => null,
                        'teacher_id' => null
                    ];
                }
                if (portalUserIsLoggedIn()) {
                    return [
                        'actor' => 'portal',
                        'admin_id' => null,
                        'portal_user_id' => (int)$_SESSION['portal_user_id'],
                        'portal_role' => (string)$_SESSION['portal_role'],
                        'teacher_id' => (int)$_SESSION['portal_teacher_id']
                    ];
                }
                return null;
            }
        }
    }
}
