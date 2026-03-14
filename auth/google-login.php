<?php
require_once '../includes/config.php';

// Redirect to Google OAuth page
$params = array(
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
    'access_type' => 'online',
    'prompt' => 'select_account'
);

$auth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);

// Redirect to Google's OAuth server
header('Location: ' . $auth_url);
exit;
?>