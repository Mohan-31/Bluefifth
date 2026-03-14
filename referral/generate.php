<?php
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userId = getCurrentUserId();

// Check if user already has a referral code
$conn = getConnection();
$stmt = $conn->prepare("SELECT code, link FROM referrals WHERE user_id = ?");
$stmt->execute([$userId]);

if ($stmt->rowCount() > 0) {
    // Return existing referral code
    $referral = $stmt->fetch();
    echo json_encode([
        'success' => true, 
        'code' => $referral['code'], 
        'link' => $referral['link']
    ]);
} else {
    // Generate new referral code
    $code = generateReferralCode();
    $link = generateReferralLink($code);
    
    // Save to database
    $stmt = $conn->prepare("INSERT INTO referrals (user_id, code, link) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $code, $link]);
    
    echo json_encode([
        'success' => true, 
        'code' => $code, 
        'link' => $link
    ]);
}
?>