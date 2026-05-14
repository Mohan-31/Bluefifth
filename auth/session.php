<?php
// auth/session.php - Session Management
// MERGED VERSION: Keeps essential login functions + removes conflicts

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================
// CORE LOGIN FUNCTIONS (PRESERVED)
// ================================

// Check if user is logged in - ADMIN COMPATIBLE VERSION
function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
}

// Get current user ID - ADMIN COMPATIBLE VERSION  
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
}

// Get current user data - ENHANCED VERSION
function getCurrentUser() {
    $userId = getCurrentUserId();
    if (!$userId) {
        return null;
    }
    
    try {
        require_once __DIR__ . '/../includes/database.php';
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $user = $stmt->fetch();
        if ($user) {
            return $user;
        }

        // User was deleted from DB — clear the stale session entry
        unset($_SESSION['user_id']);
        error_log("User ID {$userId} does not exist in users table");
        return null;
    } catch (Exception $e) {
        error_log("Error in getCurrentUser: " . $e->getMessage());
        return null;
    }
}

// Set user session - PRESERVED EXACTLY
function loginUser($userId) {
    $_SESSION['user_id'] = $userId;
    
    // Update last login time
    try {
        require_once __DIR__ . '/../includes/database.php';
        $conn = getConnection();
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Error updating last login: " . $e->getMessage());
    }
}

// Logout user - PRESERVED EXACTLY
function logoutUser() {
    // Clear all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
}

// ================================
// ADDITIONAL ADMIN FUNCTIONS
// ================================

// Check if current user is admin
function isAdmin() {
    return isset($_SESSION['admin_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin']);
}

// Set admin session
function loginAdmin($adminId) {
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['is_admin'] = true;
    
    // Update last login time
    try {
        require_once __DIR__ . '/../includes/database.php';
        $conn = getConnection();
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$adminId]);
    } catch (Exception $e) {
        error_log("Error updating admin last login: " . $e->getMessage());
    }
}

// Check admin authentication and redirect if not authenticated
function requireAdmin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

// ================================
// SESSION SECURITY & MANAGEMENT
// ================================

// Set session timeout
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 3600); // 1 hour
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    // Session has expired
    session_unset();
    session_destroy();
    
    // Redirect to login if this is a protected page
    if (basename($_SERVER['PHP_SELF']) !== 'index.php' && 
        strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
        header('Location: ../index.php?error=session_expired');
        exit;
    }
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// Additional session security measures
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
} else {
    // Check if user agent changed (possible session hijacking)
    if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_unset();
        session_destroy();
        header('Location: ../index.php?error=security_violation');
        exit;
    }
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

?>