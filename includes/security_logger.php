<?php
/**
 * Security Event Logging System
 */

function logSecurityEvent($eventType, $userId = null, $ipAddress = null, $eventData = []) {
    global $pdo;

    if (!$ipAddress) {
        $ipAddress = $_SERVER["HTTP_X_FORWARDED_FOR"] ??
                    $_SERVER["HTTP_X_REAL_IP"] ??
                    $_SERVER["REMOTE_ADDR"] ??
                    "unknown";
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO security_audit
            (event_type, user_id, ip_address, user_agent, event_data)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $eventType,
            $userId,
            $ipAddress,
            $_SERVER["HTTP_USER_AGENT"] ?? "",
            json_encode($eventData)
        ]);
    } catch (Exception $e) {
        // Log to file if database logging fails
        error_log("Security event logging failed: " . $e->getMessage());
        error_log("Event: $eventType, IP: $ipAddress, Data: " . json_encode($eventData));
    }
}

function logFailedLogin($username, $ipAddress = null) {
    global $pdo;

    if (!$ipAddress) {
        $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO failed_login_attempts
            (username, ip_address) VALUES (?, ?)
        ");
        $stmt->execute([$username, $ipAddress]);

        // Check for brute force attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts
            FROM failed_login_attempts
            WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ipAddress]);
        $result = $stmt->fetch();

        if ($result["attempts"] >= 5) {
            logSecurityEvent("brute_force_attempt", null, $ipAddress, [
                "username" => $username,
                "attempts" => $result["attempts"]
            ]);
        }
    } catch (Exception $e) {
        error_log("Failed login logging failed: " . $e->getMessage());
    }
}

function isIPBlocked($ipAddress = null) {
    global $pdo;

    if (!$ipAddress) {
        $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as recent_attempts
            FROM failed_login_attempts
            WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ipAddress]);
        $result = $stmt->fetch();

        return $result["recent_attempts"] >= 10; // Block after 10 failed attempts
    } catch (Exception $e) {
        return false;
    }
}
?>