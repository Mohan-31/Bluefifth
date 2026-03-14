<?php
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';
require_once '../includes/config.php';
require_once '../includes/email-templates.php';

// This file handles adding points to a user's wallet
// It can be called directly by the referral system or via API

// Check if request has required parameters
if (!isset($_POST['user_id']) || !isset($_POST['points']) || !isset($_POST['source'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$userId = $_POST['user_id'];
$points = intval($_POST['points']);
$source = $_POST['source']; // 'referral', 'admin', 'promo', etc.
$referenceId = $_POST['reference_id'] ?? null; // Optional reference ID (e.g., referral purchase ID)

// Validate points value
if ($points <= 0) {
    echo json_encode(['success' => false, 'message' => 'Points must be a positive number']);
    exit;
}

// Begin database transaction
$conn = getConnection();
$conn->beginTransaction();

try {
    // Ensure user has a wallet
    $walletId = ensureUserWallet($userId);
    
    // Determine if points should be added to available or pending
    $addToPending = ($source === 'referral'); // Referral points start as pending
    
    // Always add directly to available balance
    $stmt = $conn->prepare("UPDATE wallet SET points = points + ? WHERE id = ?");
    $stmt->execute([$points, $walletId]);
    
    // Record transaction
    $stmt = $conn->prepare("
        INSERT INTO wallet_transactions 
        (wallet_id, points, transaction_type, reference_id) 
        VALUES (?, ?, 'earned', ?)
    ");
    $stmt->execute([$walletId, $points, $referenceId]);
    $transactionId = $conn->lastInsertId();
    
    // If this is a referral purchase, update its status
    if ($source === 'referral' && $referenceId) {
        $stmt = $conn->prepare("
            UPDATE referral_purchases
            SET status = 'credited', points_earned = ?
            WHERE id = ?
        ");
        $stmt->execute([$points, $referenceId]);
        
        // Get purchase details for notification
        $stmt = $conn->prepare("
            SELECT amount FROM referral_purchases WHERE id = ?
        ");
        $stmt->execute([$referenceId]);
        $purchase = $stmt->fetch();
        
        // Get user details for notification
        $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        // Send notification email
        if ($user) {
            sendReferralPointsEarned($user, $points, $purchase['amount']);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Points added successfully',
        'transaction_id' => $transactionId,
        'new_balance' => getWalletBalance($userId)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add points: ' . $e->getMessage()
    ]);
}
?>