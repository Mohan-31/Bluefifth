<?php
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if request has required parameters
if (!isset($_POST['points_to_use']) || !isset($_POST['order_total'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$userId = getCurrentUserId();
$pointsToUse = intval($_POST['points_to_use']);
$orderTotal = floatval($_POST['order_total']);

// Get wallet balance
$balance = getWalletBalance($userId);

// Check if user has enough points
if ($balance['points'] < $pointsToUse) {
    echo json_encode([
        'success' => false, 
        'message' => 'Not enough points. Available: ' . $balance['points']
    ]);
    exit;
}

// Calculate discount amount (1 point = 1% of order)
$discountPercent = min(100, $pointsToUse); // Cap at 100%
$discountAmount = ($orderTotal * $discountPercent) / 100;

// Calculate new total
$newTotal = max(0, $orderTotal - $discountAmount);

// Store in session for checkout
$_SESSION['points_used'] = $pointsToUse;
$_SESSION['discount_amount'] = $discountAmount;

echo json_encode([
    'success' => true,
    'discount_amount' => $discountAmount,
    'new_total' => $newTotal,
    'points_remaining' => $balance['points'] - $pointsToUse
]);
?>