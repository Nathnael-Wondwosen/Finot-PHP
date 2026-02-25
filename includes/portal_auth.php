<?php

function portalUserIsLoggedIn() {
    return isset($_SESSION['portal_user_id'], $_SESSION['portal_role'], $_SESSION['portal_teacher_id']);
}

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

function isAdminSession() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

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

