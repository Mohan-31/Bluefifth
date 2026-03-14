<?php
// admin/admin-auth.php - Admin Authentication Handler
session_start();

// Admin credentials (you can change these)
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'bluefifth@2025'; // Change this to a strong password

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Basic validation
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }
    
    // Check credentials
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        // Set admin session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Log successful login
        error_log("Admin login successful from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => 'admin.php'
        ]);
    } else {
        // Log failed login attempt
        error_log("Admin login failed for username: {$username} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Add a small delay to prevent brute force attacks
        sleep(1);
        
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
     
} catch (Exception $e) {
    error_log("Admin auth error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Authentication error']);
}
?>