<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = getCurrentUserId();
$mobileNumber = trim($_POST['mobile_number'] ?? '');
$upiId = trim($_POST['upi_id'] ?? '');

if (empty($mobileNumber) || empty($upiId)) {
    echo json_encode(['success' => false, 'message' => 'Both fields are required']);
    exit;
}

try {
    $conn = getConnection();
    
    $stmt = $conn->prepare("UPDATE users SET mobile_number = ?, upi_id = ? WHERE id = ?");
    $result = $stmt->execute([$mobileNumber, $upiId, $userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Payment details saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save payment details']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>