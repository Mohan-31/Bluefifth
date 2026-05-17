<?php
// ============================================================
// SITE CONFIGURATION
// Change BASE_URL to your actual domain before deployment.
// For local XAMPP: http://localhost/ecommerce-project
// For production: https://bluefifth.in
// ============================================================

// Simple .env loader — reads .env from project root if it exists.
// Works on XAMPP, shared hosting (InfinityFree), and any server.
// Does NOT overwrite env vars already set by the host/server.
(static function () {
    $f = __DIR__ . '/../.env';
    if (!file_exists($f)) return;
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if ($k !== '' && !array_key_exists($k, $_SERVER)) {
            putenv("$k=$v");
            $_ENV[$k]    = $v;
            $_SERVER[$k] = $v;
        }
    }
})();

define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/ecommerce-project');
// BASE_PATH: URL-path prefix for all internal links.
// Vercel (domain root): set BASE_PATH=  (empty) or BASE_PATH=/
// XAMPP local:          leave unset → defaults to /ecommerce-project
define('BASE_PATH', rtrim(getenv('BASE_PATH') !== false ? (string)getenv('BASE_PATH') : '/ecommerce-project', '/'));
define('SITE_NAME', 'bluefifth');
define('SITE_URL',   BASE_URL);
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'immohan017@gmail.com');

// ============================================================
// GOOGLE OAUTH — DEPRECATED
// Google OAuth has been replaced by phone-OTP checkout.
// These constants are kept for reference only and are not used.
// ============================================================
// define('GOOGLE_CLIENT_ID',     '...');
// define('GOOGLE_CLIENT_SECRET', '...');
// define('GOOGLE_REDIRECT_URI',  BASE_URL . '/auth/google-callback.php');

// ============================================================
// RAZORPAY MAGIC CHECKOUT
// Keys are stored in the database settings table (admin > settings).
// The constants below are NOT used directly — getSetting() reads
// from the DB at runtime so keys can be changed without a deploy.
// RAZORPAY_KEY_ID    — rzp_test_xxx  or  rzp_live_xxx
// RAZORPAY_KEY_SECRET — secret key
// ============================================================
// (Razorpay keys managed via admin settings panel — no constants needed here)

// ============================================================
// REFERRAL SYSTEM
// ============================================================

define('REFERRAL_POINT_PERCENT', 5);   // 5 % of purchase value becomes points
define('MIN_POINTS_TO_CLAIM',   100);  // minimum ₹ equivalent before claim allowed

// ============================================================
// SHIPROCKET WEBHOOK TOKENS
// Set these via environment variables on production.
// Register webhook URLs in Shiprocket dashboard:
//   Order updates: https://yourdomain.com/ecommerce-project/shiprocket-webhook.php
//   Returns:       https://yourdomain.com/ecommerce-project/return_webhook.php
// Shiprocket sends the token in the X-API-Key header.
// ============================================================
define('SHIPROCKET_ORDER_WEBHOOK_TOKEN',  getenv('SHIPROCKET_ORDER_WEBHOOK_TOKEN')  ?: 'BFOrder2025$SecretKey');
define('SHIPROCKET_RETURN_WEBHOOK_TOKEN', getenv('SHIPROCKET_RETURN_WEBHOOK_TOKEN') ?: 'BFReturn2025$SecretKey');

// ============================================================
// UPLOAD PATHS
// ============================================================

define('UPLOAD_BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/ecommerce-project/uploads');
define('UPLOAD_BASE_URL',  BASE_URL . '/uploads');
