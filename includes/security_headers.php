<?php
/**
 * Security Headers Configuration
 */

// Set security headers
function setSecurityHeaders() {
    // Prevent clickjacking
    header("X-Frame-Options: SAMEORIGIN");

    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");

    // Enable XSS protection
    header("X-XSS-Protection: 1; mode=block");

    // Referrer policy
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // Content Security Policy
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdnjs.cloudflare.com; " .
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
           "font-src 'self' https://fonts.gstatic.com; " .
           "img-src 'self' data: https:; " .
           "connect-src 'self'; " .
           "frame-ancestors 'self'; " .
           "base-uri 'self'; " .
           "form-action 'self';";

    header("Content-Security-Policy: " . $csp);

    // Permissions policy
    header("Permissions-Policy: geolocation=(), microphone=(), camera=(self), payment=()");

    // HSTS (HTTP Strict Transport Security) - Only enable in production with HTTPS
    // header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Set headers for all pages
setSecurityHeaders();
?>