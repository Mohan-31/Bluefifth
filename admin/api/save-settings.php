<?php
include '../../includes/database.php';

if (isset($_POST['timer_message'])) {
    $timer_message = mysqli_real_escape_string($conn, $_POST['timer_message']);

    $sql = "
        INSERT INTO settings (setting_key, setting_value, setting_type, setting_description, is_editable)
        VALUES ('timer_message', '$timer_message', 'string', 'Message for the top timer banner', 1)
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type),
            setting_description = VALUES(setting_description),
            is_editable = VALUES(is_editable)
    ";

    if (!mysqli_query($conn, $sql)) {
        die('Error saving timer_message: ' . mysqli_error($conn));
    }
}

header("Location: ../settings.php?saved=1");
exit;
?>
