<?php
/**
 * shop/api/otp.php — OTP REST endpoint
 *
 * POST body (JSON):
 *   { "action": "send",   "phone": "9XXXXXXXXX" }
 *   { "action": "verify", "phone": "9XXXXXXXXX", "otp": "123456" }
 *   { "action": "resend", "phone": "9XXXXXXXXX" }
 *
 * All responses are JSON.
 */

session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/otp.php';
require_once __DIR__ . '/../../auth/session.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($input['action'] ?? '');
$phone  = trim($input['phone']  ?? '');

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing action.']);
    exit;
}

// Build OTPService instance from config constants
$otp = new OTPService(
    defined('MSG91_AUTH_KEY')    ? MSG91_AUTH_KEY    : 'dev',
    defined('MSG91_TEMPLATE_ID') ? MSG91_TEMPLATE_ID : '',
    defined('MSG91_SENDER_ID')   ? MSG91_SENDER_ID   : 'BLUEFTH'
);

// ----------------------------------------------------------------
switch ($action) {

    // ---- SEND --------------------------------------------------
    case 'send':
        if (empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
            exit;
        }

        $result = $otp->sendOTP($phone);

        if ($result['success']) {
            // Tell the frontend if this is a returning customer so it can
            // personalise the "Welcome back" message.
            $normalised    = preg_replace('/\D/', '', $phone);
            if (strlen($normalised) === 12 && str_starts_with($normalised, '91')) {
                $normalised = substr($normalised, 2);
            }

            $isReturning   = false;
            try {
                $conn = getConnection();
                $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
                $stmt->execute([$normalised]);
                $isReturning = $stmt->rowCount() > 0;
            } catch (Exception $e) {
                // non-fatal
            }

            $result['is_returning']  = $isReturning;
            $result['resend_after']  = 30; // seconds
        }

        echo json_encode($result);
        break;

    // ---- VERIFY ------------------------------------------------
    case 'verify':
        $otpCode = trim($input['otp'] ?? '');

        if (empty($phone) || empty($otpCode)) {
            echo json_encode(['success' => false, 'message' => 'Phone and OTP are required.']);
            exit;
        }

        $result = $otp->verifyOTP($phone, $otpCode);

        if ($result['success']) {
            // Create or retrieve the customer profile
            $user = findOrCreateUserByPhone($phone);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Could not create customer profile. Please try again.']);
                exit;
            }

            $userId = (int)$user['id'];

            // Merge any guest cart items into the DB cart
            if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
                mergeGuestCartWithUserCart($userId);
                unset($_SESSION['guest_cart']);
            }

            // Establish session
            loginUser($userId);
            $_SESSION['phone_verified'] = $phone;

            // Fetch saved default address for auto-fill
            $savedAddress = null;
            try {
                $conn = getConnection();
                $stmt = $conn->prepare("
                    SELECT * FROM customer_addresses
                    WHERE  user_id = ? AND is_default = 1
                    LIMIT  1
                ");
                $stmt->execute([$userId]);
                $savedAddress = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Exception $e) {
                // non-fatal — checkout form just stays blank
            }

            $result['user'] = [
                'id'       => $userId,
                'name'     => $user['name']    ?? '',
                'email'    => $user['email']   ?? '',
                'phone'    => $user['phone']   ?? $phone,
                'address'  => $savedAddress['address_line'] ?? ($user['address']  ?? ''),
                'apartment'=> $savedAddress['apartment']    ?? '',
                'city'     => $savedAddress['city']         ?? ($user['city']     ?? ''),
                'state'    => $savedAddress['state']        ?? ($user['state']    ?? ''),
                'pincode'  => $savedAddress['pincode']      ?? ($user['pincode']  ?? ''),
                'full_name'=> $savedAddress['full_name']    ?? ($user['name']     ?? ''),
            ];
        }

        echo json_encode($result);
        break;

    // ---- RESEND ------------------------------------------------
    case 'resend':
        if (empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
            exit;
        }

        $result = $otp->resendOTP($phone);
        echo json_encode($result);
        break;

    // ---- UNKNOWN -----------------------------------------------
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}
