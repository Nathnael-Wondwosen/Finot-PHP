<?php
/**
 * CSRF Protection Functions
 */

function generateCSRFToken() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION["csrf_token"]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION["csrf_token"], $token);
}

function getCSRFTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function requireCSRFToken() {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (!isset($_POST["csrf_token"]) || !validateCSRFToken($_POST["csrf_token"])) {
            logSecurityEvent("csrf_attempt", null, $_SERVER["REMOTE_ADDR"], [
                "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "",
                "request_uri" => $_SERVER["REQUEST_URI"] ?? "",
                "post_data" => array_keys($_POST)
            ]);
            http_response_code(403);
            die("CSRF token validation failed");
        }
    }
}
?>