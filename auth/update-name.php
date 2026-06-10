<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/database.php';
require_once 'session.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$name  = trim($input['name'] ?? '');

if (empty($name) || mb_strlen($name) > 100) {
    echo json_encode(['success' => false, 'message' => 'Invalid name']);
    exit;
}

$name   = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$userId = getCurrentUserId();

try {
    $conn = getConnection();
    $conn->prepare("UPDATE users SET name = ? WHERE id = ?")
         ->execute([$name, $userId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('update-name.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
