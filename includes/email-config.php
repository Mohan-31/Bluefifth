<?php
// includes/email-config.php
// Brevo (Sendinblue) email configuration.
// Real credentials live in .env — this file is safe to commit.

return [
    'settings' => [
        'enabled'             => true,
        'test_mode'           => false,
        'fallback_to_php_mail' => true,
    ],
    'sendinblue' => [
        'api_key'    => getenv('SENDINBLUE_API_KEY')    ?: '',
        'from_email' => getenv('SENDINBLUE_FROM_EMAIL') ?: 'info@bluefifth.in',
        'from_name'  => getenv('SENDINBLUE_FROM_NAME')  ?: 'Bluefifth',
    ],
    'smtp' => [
        'host'       => getenv('SMTP_HOST')     ?: '',
        'port'       => (int)(getenv('SMTP_PORT') ?: 587),
        'username'   => getenv('SMTP_USER')     ?: '',
        'password'   => getenv('SMTP_PASS')     ?: '',
        'encryption' => getenv('SMTP_ENC')      ?: 'tls',
    ],
];
