<?php
// Google OAuth Configuration
// Replace these with actual values from Google Cloud Console
define('GOOGLE_CLIENT_ID', '340757900430-i8nl6l45ndveq9jmbvbah7ugquauj803.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost/referral-system/auth/google-callback.php');

// Site Configuration
define('SITE_NAME', 'Your E-Commerce Site');
define('SITE_URL', 'https://yourdomain.com');
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// Referral System Configuration
define('REFERRAL_POINT_PERCENT', 5); // 5% of purchase value
define('MIN_POINTS_TO_CLAIM', 10); // Minimum points needed to claim
?>