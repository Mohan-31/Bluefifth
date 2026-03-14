<?php
// track-purchase.php - FIXED TO USE UNIFIED REFERRAL SYSTEM
header('Content-Type: application/json');

require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';
require_once '../includes/config.php';

// Check if request has required parameters
if (!isset($_POST['order_id']) || !isset($_POST['order_amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$orderId = $_POST['order_id'];
$orderAmount = floatval($_POST['order_amount']);
$buyerUserId = isset($_POST['buyer_user_id']) ? intval($_POST['buyer_user_id']) : (isLoggedIn() ? getCurrentUserId() : null);

// Check if this purchase was referred
$referralCode = null;

// Check cookies for referral code
if (isset($_COOKIE['referral_code'])) {
    $referralCode = $_COOKIE['referral_code'];
}

// Check session for referral code
if (!$referralCode && isset($_SESSION['referral_code'])) {
    $referralCode = $_SESSION['referral_code'];
}

// If no referral, return success but no processing
if (!$referralCode) {
    echo json_encode(['success' => true, 'message' => 'No referral found - order processed normally']);
    exit;
}

// FIXED: Use the unified processOrderReferral function from functions.php
// This handles all the logic: rates, months, wallet updates, everything
$result = processOrderReferral($orderId, $orderId, $orderAmount, $referralCode, $buyerUserId);

// Log the result
if ($result['success']) {
    error_log("Referral processed successfully: Order {$orderId}, Points {$result['points_earned']}, Code {$referralCode}");
} else {
    error_log("Referral processing failed: Order {$orderId}, Error: {$result['message']}");
}

// Return the result
echo json_encode($result);

// Clear referral from session/cookie after processing (successful or not)
if (isset($_SESSION['referral_code'])) {
    unset($_SESSION['referral_code']);
}

if (isset($_COOKIE['referral_code'])) {
    setcookie('referral_code', '', time() - 3600, '/');
}
?>