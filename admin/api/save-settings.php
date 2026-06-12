<?php
require_once __DIR__ . '/../../includes/database.php';

if (isset($_POST['timer_message'])) {
    $timerMessage = trim($_POST['timer_message']);

    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type, setting_description, is_editable)
        VALUES ('timer_message', ?, 'string', 'Message for the top timer banner', TRUE)
        ON CONFLICT (setting_key) DO UPDATE SET
            setting_value       = EXCLUDED.setting_value,
            setting_type        = EXCLUDED.setting_type,
            setting_description = EXCLUDED.setting_description,
            is_editable         = EXCLUDED.is_editable
    ");
    $stmt->execute([$timerMessage]);
}

header("Location: ../settings.php?saved=1");
exit;
?>
