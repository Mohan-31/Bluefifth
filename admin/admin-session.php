<?php
// admin/admin-session.php - Fixed version for API compatibility

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    // Ensure session is started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Check admin authentication and redirect if not logged in
 */
function requireAdminAuth() {
    // Ensure session is started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    if (!isAdminLoggedIn()) {
        // If this is an AJAX request, return JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
            exit;
        }
        
        // For regular requests, redirect to login
        header('Location: admin-login.php');
        exit;
    }
    
    // Check session timeout (24 hours)
    $sessionTimeout = 24 * 60 * 60; // 24 hours in seconds
    if (isset($_SESSION['admin_login_time']) && 
        (time() - $_SESSION['admin_login_time']) > $sessionTimeout) {
        destroyAdminSession();
        header('Location: admin-login.php?timeout=1');
        exit;
    }
    
    // Update last activity time
    $_SESSION['admin_last_activity'] = time();
}

/**
 * Simple admin authentication for API calls (more lenient)
 */
function checkAdminAuth() {
    // Ensure session is started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Debug logging
    error_log("API Auth Check - Session status: " . session_status());
    error_log("API Auth Check - Admin logged in: " . (isAdminLoggedIn() ? 'YES' : 'NO'));
    error_log("API Auth Check - Session ID: " . session_id());
    
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Admin authentication required',
            'debug' => [
                'session_status' => session_status(),
                'session_id' => session_id(),
                'admin_logged_in' => $_SESSION['admin_logged_in'] ?? 'not set'
            ]
        ]);
        exit;
    }
    
    error_log("API Auth Check - SUCCESS");
}

/**
 * Destroy admin session
 */
function destroyAdminSession() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_login_time']);
    unset($_SESSION['admin_ip']);
    unset($_SESSION['admin_last_activity']);
}

/**
 * Get admin username
 */
function getAdminUsername() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    return $_SESSION['admin_username'] ?? 'Admin';
}
?>