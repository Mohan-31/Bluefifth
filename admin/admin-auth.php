<?php
// admin/admin-auth.php — Admin login handler
// Authenticates against the admin_users table using bcrypt.
// Run setup-admin-password.php once to store a proper hash.

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/database.php';

try {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }

    $conn = getConnection();

    // Look up admin by username
    $stmt = $conn->prepare(
        "SELECT id, username, password_hash, is_active FROM admin_users WHERE username = ? LIMIT 1"
    );
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !$admin['is_active']) {
        sleep(1); // slow brute-force
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit;
    }

    $hash = $admin['password_hash'];

    // Support both bcrypt hashes ($2y$…) and the legacy plain-text
    // stored before the migration ran. Remove the plain-text branch
    // once setup-admin-password.php has been executed.
    $valid = false;
    if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$')) {
        $valid = password_verify($password, $hash);
    } else {
        // Legacy: direct comparison — only works before migration/setup
        $valid = hash_equals($hash, $password);
    }

    if ($valid) {
        // Rotate session ID on privilege escalation
        session_regenerate_id(true);

        $_SESSION['admin_logged_in']   = true;
        $_SESSION['admin_id']          = $admin['id'];
        $_SESSION['admin_username']    = $admin['username'];
        $_SESSION['admin_login_time']  = time();
        $_SESSION['admin_ip']          = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Update last-login timestamp
        $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")
             ->execute([$admin['id']]);

        echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => 'admin.php']);
    } else {
        sleep(1);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }

} catch (Exception $e) {
    error_log("Admin auth error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Authentication error']);
}
