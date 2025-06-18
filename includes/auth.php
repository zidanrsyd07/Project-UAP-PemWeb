<?php
// Authentication Functions

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is member
 */
function isMember() {
    return isLoggedIn() && $_SESSION['role'] === 'member';
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../auth/login.php");
        exit;
    }
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: ../index.php");
        exit;
    }
}

/**
 * Require member access
 */
function requireMember() {
    requireLogin();
    if (!isMember()) {
        header("Location: ../index.php");
        exit;
    }
}

/**
 * Login user
 */
function loginUser($user) {
    $_SESSION['user_id'] = $user['id_user'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    
    if ($user['role'] === 'member') {
        $_SESSION['user_number'] = $user['user_number'];
    } else {
        $_SESSION['username'] = $user['username'];
    }
}

/**
 * Logout user
 */
function logoutUser() {
    session_destroy();
    header("Location: ../index.php");
    exit;
}
?>
