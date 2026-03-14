<?php
// admin/admin-logout.php - Admin Logout Handler
session_start();
require_once 'admin-session.php';

// Log the logout
if (isset($_SESSION['admin_username'])) {
    error_log("Admin logout: " . $_SESSION['admin_username'] . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// Destroy admin session
destroyAdminSession();

// Redirect to login page with logout message
header('Location: admin-login.php?logout=1');
exit;
?>