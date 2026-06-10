<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');

if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid 10-digit Indian mobile number']);
    exit;
}

try {
    $conn = getConnection();

    // Rate limit: max 3 sends per phone per 30 minutes
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM otp_verifications
        WHERE phone = ?
          AND created_at > NOW() - INTERVAL '30 minutes'
    ");
    $stmt->execute([$phone]);
    if ((int)$stmt->fetchColumn() >= 3) {
        echo json_encode(['success' => false, 'message' => 'Too many OTP requests. Please wait 30 minutes.']);
        exit;
    }

    // Generate 6-digit OTP
    $otp     = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = hash('sha256', $otp);
    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    $stmt = $conn->prepare("
        INSERT INTO otp_verifications (phone, otp_hash, purpose, expires_at)
        VALUES (?, ?, 'login', ?)
    ");
    $stmt->execute([$phone, $otpHash, $expires]);

    // Send via Fast2SMS
    $apiKey = getenv('FAST2SMS_API_KEY') ?: '';

    if (empty($apiKey) || $apiKey === 'your_fast2sms_api_key_here') {
        // Dev mode: log OTP to Apache error log instead of sending SMS
        error_log("[DEV] OTP for +91{$phone}: {$otp}");
        echo json_encode(['success' => true, 'dev_mode' => true, 'message' => 'OTP logged (dev mode — check Apache error log)']);
        exit;
    }

    $payload = http_build_query([
        'authorization'   => $apiKey,
        'variables_values' => $otp,
        'route'           => 'otp',
        'numbers'         => $phone,
        'flash'           => '0',
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($payload),
            'content' => $payload,
            'timeout' => 10,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $response = @file_get_contents('https://www.fast2sms.com/dev/bulkV2', false, $ctx);
    $result   = $response ? json_decode($response, true) : null;

    if ($result && ($result['return'] === true || $result['return'] === 'true')) {
        echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
    } else {
        error_log('Fast2SMS error: ' . $response);
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
    }

} catch (Exception $e) {
    error_log('send-otp.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
