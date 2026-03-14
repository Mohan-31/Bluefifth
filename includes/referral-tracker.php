<?php
// Referral tracking for all pages
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referralCode = trim($_GET['ref']);
    if (function_exists('isValidReferralCode') && isValidReferralCode($referralCode)) {
        $_SESSION['referral_code'] = $referralCode;
        
        // Set/refresh cookie for persistence
        if (PHP_VERSION_ID >= 70300) {
            setcookie('referral_code', $referralCode, [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        } else {
            setcookie('referral_code', $referralCode, time() + (30 * 24 * 60 * 60), '/');
        }
        
        error_log("Referral code preserved on " . basename($_SERVER['PHP_SELF']) . ": " . $referralCode);
    }
}

// Track page visits for analytics (optional)
if (isset($_SESSION['referral_code']) || isset($_COOKIE['referral_code'])) {
    $currentReferralCode = $_SESSION['referral_code'] ?? $_COOKIE['referral_code'];
    error_log("User browsing with referral code: " . $currentReferralCode . " on page: " . basename($_SERVER['PHP_SELF']));
}
?>