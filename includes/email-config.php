<?php
// includes/email-config.php - Email configuration

return array (
  'settings' =>
  array (
    'enabled' => true,
    'test_mode' => false,
    'fallback_to_php_mail' => true,
  ),
  'sendinblue' =>
  array (
    'api_key' => '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',
    'from_email' => 'info@bluefifth.in',
    'from_name' => 'Bluefifth Team',
  ),
  'smtp' =>
  array (
    'host' => '',
    'port' => 587,
    'username' => '',
    'password' => '',
    'encryption' => 'tls',
  ),
);
?>
