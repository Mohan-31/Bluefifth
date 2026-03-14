<?php
echo "<pre>";
print_r($_POST);
echo "</pre>";
exit;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files (make sure you installed it via Composer or uploaded PHPMailer library)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $order_id = htmlspecialchars(trim($_POST['order_id']));
    $message = htmlspecialchars(trim($_POST['message']));

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';   // SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'devprashath7@gmail.com'; // Your Gmail
        $mail->Password   = 'bluefifth';   // Gmail App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($email, $name);
        $mail->addAddress('devprashath7@gmail.com');  // Where you want to receive messages

        // Content
        $mail->isHTML(true);
        $mail->Subject = "New Contact Form Message from $name";
        $mail->Body    = "
            <h2>Contact Form Submission</h2>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Order ID:</strong> $order_id</p>
            <p><strong>Message:</strong><br>$message</p>
        ";

        $mail->send();
        echo "<script>alert('Message sent successfully!'); window.location.href='../index.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Message could not be sent. Mailer Error: {$mail->ErrorInfo}'); window.location.href='../index.php';</script>";
    }
} else {
    header("Location: ../index.php");
    exit;
}
?>
