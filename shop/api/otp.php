<?php
/**
 * shop/api/otp.php — DEPRECATED
 *
 * MSG91 OTP endpoints have been removed. Authentication is now handled
 * natively by Razorpay Magic Checkout during the payment step.
 *
 * For returning-customer autofill (phone lookup), use:
 *   shop/api/customer-lookup.php
 */
http_response_code(410);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'OTP authentication has been replaced by Razorpay Magic Checkout.',
]);
