<?php
/**
 * shop/api/customer-lookup.php
 *
 * POST body (JSON): { "phone": "9XXXXXXXXX" }
 *
 * Returns customer profile from our DB if the phone exists (returning user).
 * Used by checkout.php to auto-fill the shipping form without any OTP —
 * the actual payment-time authentication is handled by Razorpay Magic Checkout.
 *
 * Response:
 *   { success: true, found: bool, user: {...}|null, guest_cart_count: int }
 */

session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$phone = trim($input['phone'] ?? '');

// Normalise: strip non-digits, remove leading 91 country code
$normalised = preg_replace('/\D/', '', $phone);
if (strlen($normalised) === 12 && str_starts_with($normalised, '91')) {
    $normalised = substr($normalised, 2);
}

if (!preg_match('/^[6-9]\d{9}$/', $normalised)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit Indian mobile number.']);
    exit;
}

$found       = false;
$userData    = null;
$guestCount  = 0;

// Count items in the current guest session cart
if (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
    $guestCount = count($_SESSION['guest_cart']);
}

try {
    $conn = getConnection();

    $stmt = $conn->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
    $stmt->execute([$normalised]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $found = true;

        // Pull the default saved address if it exists
        $addrStmt = $conn->prepare(
            "SELECT * FROM customer_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1"
        );
        $addrStmt->execute([$user['id']]);
        $savedAddr = $addrStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $userData = [
            'id'        => (int)$user['id'],
            'name'      => $user['name']    ?? '',
            'full_name' => $savedAddr['full_name']    ?? ($user['name']  ?? ''),
            'email'     => $savedAddr['email']        ?? ($user['email'] ?? ''),
            'phone'     => $normalised,
            'address'   => $savedAddr['address_line'] ?? ($user['address']  ?? ''),
            'apartment' => $savedAddr['apartment']    ?? '',
            'city'      => $savedAddr['city']         ?? ($user['city']     ?? ''),
            'state'     => $savedAddr['state']        ?? ($user['state']    ?? ''),
            'pincode'   => $savedAddr['pincode']      ?? ($user['pincode']  ?? ''),
        ];
    }
} catch (Exception $e) {
    error_log('customer-lookup error: ' . $e->getMessage());
    // Non-fatal — return not-found so checkout can proceed
}

echo json_encode([
    'success'          => true,
    'found'            => $found,
    'user'             => $userData,
    'guest_cart_count' => $guestCount,
]);
