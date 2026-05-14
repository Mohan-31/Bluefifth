<?php
// ============================================================
// SITE CONFIGURATION
// Change BASE_URL to your actual domain before deployment.
// For local XAMPP: http://localhost/ecommerce-project
// For production: https://bluefifth.in
// ============================================================

define('BASE_URL', 'http://localhost/ecommerce-project');
define('SITE_NAME', 'bluefifth');
define('SITE_URL',   BASE_URL);
define('ADMIN_EMAIL', 'velonauk@gmail.com');

// ============================================================
// GOOGLE OAUTH — DEPRECATED
// Google OAuth has been replaced by phone-OTP checkout.
// These constants are kept for reference only and are not used.
// ============================================================
// define('GOOGLE_CLIENT_ID',     '...');
// define('GOOGLE_CLIENT_SECRET', '...');
// define('GOOGLE_REDIRECT_URI',  BASE_URL . '/auth/google-callback.php');

// ============================================================
// MSG91 — OTP Service
// Sign up at https://msg91.com — get your Auth Key from
// API > Auth Key in the MSG91 dashboard.
// Set MSG91_AUTH_KEY to 'dev' (or leave empty) for mock mode:
//   mock mode accepts OTP 123456 for any number (no SMS sent).
// DLT template registration required for production in India.
// ============================================================
define('MSG91_AUTH_KEY',    getenv('MSG91_AUTH_KEY')    ?: 'dev');
define('MSG91_TEMPLATE_ID', getenv('MSG91_TEMPLATE_ID') ?: '');
define('MSG91_SENDER_ID',   getenv('MSG91_SENDER_ID')   ?: 'BLUEFTH');

// ============================================================
// REFERRAL SYSTEM
// ============================================================

define('REFERRAL_POINT_PERCENT', 5);   // 5 % of purchase value becomes points
define('MIN_POINTS_TO_CLAIM',   100);  // minimum ₹ equivalent before claim allowed

// ============================================================
// UPLOAD PATHS
// ============================================================

define('UPLOAD_BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/ecommerce-project/uploads');
define('UPLOAD_BASE_URL',  BASE_URL . '/uploads');
