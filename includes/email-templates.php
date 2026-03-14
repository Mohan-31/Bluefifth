<?php
// Email templates for the referral system

// Function to send an email (this is a placeholder - use a proper email library in production)
function sendEmail($to, $subject, $body) {
    // In a real application, you would use a proper email sending library
    // For now, we'll just log that an email would be sent
    error_log("Email would be sent to $to: $subject");
    
    // For development, you can uncomment this to actually send emails
    // mail($to, $subject, $body, "From: noreply@yourdomain.com");
    
    return true;
}

// Send end-of-month reminder email
function sendClaimReminder($user) {
    $subject = "Don't Forget to Claim Your Referral Points!";
    
    $body = "Hello " . $user['name'] . ",\n\n";
    $body .= "Today is the last day of the month, and you have " . $user['pending_points'] . " points pending in your referral wallet.\n\n";
    $body .= "Don't forget to log in and claim your points today! Points can only be claimed on the last day of each month.\n\n";
    $body .= "Click here to claim your points: " . SITE_URL . "/user/referral.php\n\n";
    $body .= "Thank you for being a part of our referral program!\n\n";
    $body .= "Regards,\nThe " . SITE_NAME . " Team";
    
    return sendEmail($user['email'], $subject, $body);
}

// Send payment confirmation email
function sendPaymentConfirmation($user, $amount) {
    $subject = "Your Referral Payment Has Been Processed";
    
    $body = "Hello " . $user['name'] . ",\n\n";
    $body .= "Great news! Your referral payment of " . $amount . " points has been processed.\n\n";
    $body .= "The payment has been sent to your account as per your details.\n\n";
    $body .= "Thank you for participating in our referral program. Keep referring and earning!\n\n";
    $body .= "Regards,\nThe " . SITE_NAME . " Team";
    
    return sendEmail($user['email'], $subject, $body);
}

// Send purchase confirmation with referral points
function sendPurchaseWithReferralPoints($user, $points, $orderAmount) {
    $subject = "Order Confirmation - Points Used";
    
    $body = "Hello " . $user['name'] . ",\n\n";
    $body .= "Your order has been successfully placed!\n\n";
    $body .= "You used " . $points . " referral points for a discount of ₹" . ($points * $orderAmount / 100) . ".\n\n";
    $body .= "Order Total: ₹" . $orderAmount . "\n";
    $body .= "Discount: ₹" . ($points * $orderAmount / 100) . "\n";
    $body .= "Final Amount: ₹" . ($orderAmount - ($points * $orderAmount / 100)) . "\n\n";
    $body .= "Your order will be processed soon. Thank you for shopping with us!\n\n";
    $body .= "Regards,\nThe " . SITE_NAME . " Team";
    
    return sendEmail($user['email'], $subject, $body);
}

// Send referral points earned notification
function sendReferralPointsEarned($user, $points, $purchaseAmount) {
    $subject = "You've Earned Referral Points!";
    
    $body = "Hello " . $user['name'] . ",\n\n";
    $body .= "Great news! Someone has made a purchase using your referral link.\n\n";
    $body .= "Purchase Amount: ₹" . $purchaseAmount . "\n";
    $body .= "Points Earned: " . $points . "\n\n";
    $body .= "These points have been added to your pending balance and can be claimed at the end of the month.\n\n";
    $body .= "Keep referring and earning!\n\n";
    $body .= "Regards,\nThe " . SITE_NAME . " Team";
    
    return sendEmail($user['email'], $subject, $body);
}
?>