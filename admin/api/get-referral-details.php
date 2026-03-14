<?php
// api/get-referral-details.php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'referral_details' => null, 'payment_details' => null];

if (!isLoggedIn()) {
    $response['message'] = 'User not authenticated.';
    echo json_encode($response);
    exit;
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

try {
    $conn = getConnection();
    
    // Fetch referral stats
    $referralStats = getReferralStats($userId);
    
    // Fetch user's payment details
    $stmt = $conn->prepare("SELECT mobile_number, upi_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $paymentDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['referral_details'] = $referralStats;
    $response['payment_details'] = $paymentDetails;
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>