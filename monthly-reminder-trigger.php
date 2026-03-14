<?php
// monthly-reminder-trigger.php - Trigger monthly reminders (put this in root directory)
require_once 'includes/database.php';
require_once 'includes/sendinblue-mailer.php';

// Load email config
if (!file_exists('includes/email-config.php')) {
    die("❌ Email config not found");
}

$emailConfig = include 'includes/email-config.php';

if (!$emailConfig['settings']['enabled'] || empty($emailConfig['sendinblue']['api_key'])) {
    die("❌ Email system not configured");
}

// Initialize mailer
$mailer = new SendinblueMailer(
    $emailConfig['sendinblue']['api_key'],
    $emailConfig['sendinblue']['from_email'],
    $emailConfig['sendinblue']['from_name']
);

// Send monthly reminders
echo "🚀 Triggering monthly reminders...\n";
$results = $mailer->sendMonthlyRemindersToAllUsers();

if ($results['success']) {
    echo "✅ Monthly reminders sent successfully!\n";
    echo "📊 Results:\n";
    echo "   Total users: {$results['total_users']}\n";
    echo "   Emails sent: {$results['emails_sent']}\n";
    echo "   Emails failed: {$results['emails_failed']}\n";
    echo "   Users with points: {$results['users_with_points']}\n";
    echo "   Users without points: {$results['users_without_points']}\n";
} else {
    echo "❌ Monthly reminder failed: {$results['message']}\n";
}
?>