<?php
// auth/check-session.php
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/includes/database.php';

if (isset($_SESSION['user_id'])) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        echo json_encode([
            'logged_in' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode(['logged_in' => false]);
    }
} else {
    echo json_encode(['logged_in' => false]);
}
?>