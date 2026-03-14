<?php
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';

// Check if referral code is provided
if (!isset($_GET['ref'])) {
    // No referral code, do nothing
    exit;
}

$referralCode = $_GET['ref'];

// Store referral code in session
$_SESSION['referral_code'] = $referralCode;

// Find referral in database
$conn = getConnection();
$stmt = $conn->prepare("SELECT id, user_id FROM referrals WHERE code = ?");
$stmt->execute([$referralCode]);

if ($stmt->rowCount() == 0) {
    // Invalid referral code
    exit;
}

$referral = $stmt->fetch();
$referralId = $referral['id'];
$referrerId = $referral['user_id'];

// Get current user if logged in
$visitorUserId = getCurrentUserId();

// Don't track if visitor is the referrer
if ($visitorUserId == $referrerId) {
    exit;
}

// Get visitor IP
$visitorIp = getClientIP();

// Check if this IP has visited via this referral before
$stmt = $conn->prepare("SELECT id FROM referral_visits WHERE referral_id = ? AND visitor_ip = ? AND DATE(visited_at) = CURDATE()");
$stmt->execute([$referralId, $visitorIp]);

if ($stmt->rowCount() == 0) {
    // Record new visit
    $stmt = $conn->prepare("INSERT INTO referral_visits (referral_id, visitor_ip, visitor_user_id) VALUES (?, ?, ?)");
    $stmt->execute([$referralId, $visitorIp, $visitorUserId]);
}

// JavaScript to set cookie for referral tracking
echo '<script>
document.cookie = "referral_code=' . $referralCode . '; path=/; max-age=2592000"; // 30 days
</script>';
?>