<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once 'session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');
$otp   = trim($input['otp'] ?? '');

if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number']);
    exit;
}

if (!preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid 6-digit OTP']);
    exit;
}

try {
    $conn = getConnection();

    // Fetch latest unexpired, unverified OTP record
    $stmt = $conn->prepare("
        SELECT id, otp_hash, attempts
        FROM otp_verifications
        WHERE phone = ?
          AND purpose = 'login'
          AND is_verified = FALSE
          AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$phone]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'OTP expired or not found. Please request a new one.']);
        exit;
    }

    if ((int)$record['attempts'] >= 5) {
        echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts. Please request a new OTP.']);
        exit;
    }

    if (hash('sha256', $otp) !== $record['otp_hash']) {
        $conn->prepare("UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = ?")
             ->execute([$record['id']]);
        echo json_encode(['success' => false, 'message' => 'Incorrect OTP. Please try again.']);
        exit;
    }

    // Mark as verified
    $conn->prepare("UPDATE otp_verifications SET is_verified = TRUE, verified_at = NOW() WHERE id = ?")
         ->execute([$record['id']]);

    // Find or create user
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $isNewUser = false;

    if ($user) {
        $userId = $user['id'];
        $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
             ->execute([$userId]);
    } else {
        $isNewUser        = true;
        $placeholderName  = 'User' . substr($phone, -4);
        $placeholderEmail = 'phone_' . $phone . '@otp.bluefifth.local';

        $stmt = $conn->prepare("
            INSERT INTO users (name, email, phone, user_type, last_login)
            VALUES (?, ?, ?, 'registered', NOW())
            RETURNING id
        ");
        $stmt->execute([$placeholderName, $placeholderEmail, $phone]);
        $userId = (int)$stmt->fetchColumn();

        // Create referral code and wallet
        $code = generateReferralCode();
        $link = generateReferralLink($code);
        $conn->prepare("INSERT INTO referrals (user_id, code, link) VALUES (?, ?, ?)")
             ->execute([$userId, $code, $link]);

        ensureUserWallet($userId);
    }

    // Merge guest cart
    if (!empty($_SESSION['guest_cart'])) {
        mergeGuestCartWithUserCart($userId);
        unset($_SESSION['guest_cart']);
    }

    loginUser($userId);

    echo json_encode([
        'success'  => true,
        'new_user' => $isNewUser,
        'message'  => $isNewUser ? 'Welcome to Bluefifth!' : 'Login successful',
    ]);

} catch (Exception $e) {
    error_log('verify-otp.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
