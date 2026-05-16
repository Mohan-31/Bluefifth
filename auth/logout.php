<?php
// auth/logout.php - Logout and clear all cart/session data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear the user's DB cart on logout so next login starts fresh
if (isset($_SESSION['user_id'])) {
    require_once '../includes/database.php';
    require_once '../includes/functions.php';

    $userId = $_SESSION['user_id'];

    try {
        $conn = getConnection();
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Error clearing cart on logout: " . $e->getMessage());
    }
}

// Destroy entire session — clears guest cart and user data
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?>
