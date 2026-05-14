<?php
/**
 * OTPService — MSG91 phone-OTP integration with mock fallback.
 *
 * Mock mode is active when MSG91_AUTH_KEY constant equals 'dev' or is empty.
 * In mock mode every phone number is accepted and OTP 123456 always verifies.
 *
 * Rate-limit state is stored in the otp_verifications DB table.
 * The actual OTP digit-string lives on MSG91's servers.
 */

require_once __DIR__ . '/../includes/database.php';

class OTPService
{
    private string $authKey;
    private string $templateId;
    private string $senderId;
    private bool   $mockMode;

    // Tunable limits
    private const MAX_SEND_PER_HOUR    = 5;
    private const MAX_VERIFY_ATTEMPTS  = 3;
    private const OTP_EXPIRY_MINUTES   = 10;
    private const RESEND_COOLDOWN_SECS = 30;

    // MSG91 API base
    private const API_BASE = 'https://api.msg91.com/api/v5/otp';

    public function __construct(
        string $authKey    = '',
        string $templateId = '',
        string $senderId   = 'BLUEFTH'
    ) {
        $this->authKey    = $authKey;
        $this->templateId = $templateId;
        $this->senderId   = $senderId;
        $this->mockMode   = (empty($authKey) || $authKey === 'dev');
    }

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    /**
     * Send OTP to the given phone number.
     * Returns ['success' => bool, 'message' => string, 'mock' => bool]
     */
    public function sendOTP(string $phone): array
    {
        $phone = $this->normalisePhone($phone);

        if (!$this->validatePhone($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        // Rate limit: max MAX_SEND_PER_HOUR sends in the last hour
        if ($this->exceedsSendLimit($phone)) {
            return ['success' => false, 'message' => 'Too many OTP requests. Please try after an hour.'];
        }

        if ($this->mockMode) {
            $this->recordSend($phone);
            return [
                'success' => true,
                'message' => 'OTP sent (dev mode — use 123456).',
                'mock'    => true,
            ];
        }

        // Real MSG91 call
        $payload = [
            'mobile'       => '91' . $phone,
            'otp_expiry'   => self::OTP_EXPIRY_MINUTES,
            'authkey'      => $this->authKey,
        ];
        if (!empty($this->templateId)) {
            $payload['template_id'] = $this->templateId;
        }

        $response = $this->curlPost(self::API_BASE, $payload);

        if ($response && isset($response['type']) && $response['type'] === 'success') {
            $this->recordSend($phone);
            return ['success' => true, 'message' => 'OTP sent to your mobile number.', 'mock' => false];
        }

        $msg = $response['message'] ?? 'Failed to send OTP. Please try again.';
        return ['success' => false, 'message' => $msg];
    }

    /**
     * Verify the OTP supplied by the user.
     * Returns ['success' => bool, 'message' => string]
     */
    public function verifyOTP(string $phone, string $otp): array
    {
        $phone = $this->normalisePhone($phone);
        $otp   = trim($otp);

        if (!$this->validatePhone($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        if (!preg_match('/^\d{4,6}$/', $otp)) {
            return ['success' => false, 'message' => 'Invalid OTP format.'];
        }

        // Check attempt limit
        if ($this->exceededAttemptLimit($phone)) {
            return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new OTP.'];
        }

        if ($this->mockMode) {
            if ($otp === '123456') {
                $this->markVerified($phone);
                return ['success' => true, 'message' => 'Phone verified.'];
            }
            $this->incrementAttempts($phone);
            return ['success' => false, 'message' => 'Incorrect OTP. (Dev mode: use 123456)'];
        }

        // Real MSG91 verify
        $url = self::API_BASE . '/verify?' . http_build_query([
            'mobile'  => '91' . $phone,
            'otp'     => $otp,
            'authkey' => $this->authKey,
        ]);

        $response = $this->curlGet($url);

        if ($response && isset($response['type']) && $response['type'] === 'success') {
            $this->markVerified($phone);
            return ['success' => true, 'message' => 'Phone verified.'];
        }

        $this->incrementAttempts($phone);
        $msg = $response['message'] ?? 'Incorrect OTP. Please try again.';
        return ['success' => false, 'message' => $msg];
    }

    /**
     * Resend OTP (MSG91 retry endpoint — does not consume a new credit).
     * Returns ['success' => bool, 'message' => string, 'retry_after' => int]
     */
    public function resendOTP(string $phone): array
    {
        $phone = $this->normalisePhone($phone);

        if (!$this->validatePhone($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.', 'retry_after' => 0];
        }

        // Cooldown check: don't resend within RESEND_COOLDOWN_SECS
        $secondsSinceLast = $this->secondsSinceLastSend($phone);
        if ($secondsSinceLast !== null && $secondsSinceLast < self::RESEND_COOLDOWN_SECS) {
            $wait = self::RESEND_COOLDOWN_SECS - $secondsSinceLast;
            return [
                'success'     => false,
                'message'     => "Please wait {$wait} seconds before resending.",
                'retry_after' => $wait,
            ];
        }

        if ($this->mockMode) {
            $this->recordSend($phone);
            return ['success' => true, 'message' => 'OTP resent (dev mode — use 123456).', 'retry_after' => self::RESEND_COOLDOWN_SECS];
        }

        $url = self::API_BASE . '/resend?' . http_build_query([
            'mobile'    => '91' . $phone,
            'retrytype' => 'text',
            'authkey'   => $this->authKey,
        ]);

        $response = $this->curlGet($url);

        if ($response && isset($response['type']) && $response['type'] === 'success') {
            $this->recordSend($phone);
            return ['success' => true, 'message' => 'OTP resent.', 'retry_after' => self::RESEND_COOLDOWN_SECS];
        }

        $msg = $response['message'] ?? 'Could not resend OTP.';
        return ['success' => false, 'message' => $msg, 'retry_after' => 0];
    }

    // ----------------------------------------------------------------
    // DB helpers
    // ----------------------------------------------------------------

    private function recordSend(string $phone): void
    {
        try {
            $conn = getConnection();
            // Expire any open record first so verify picks up the latest
            $conn->prepare("
                UPDATE otp_verifications
                SET    expires_at = NOW()
                WHERE  phone = ? AND purpose = 'checkout' AND is_verified = 0
            ")->execute([$phone]);

            $conn->prepare("
                INSERT INTO otp_verifications (phone, purpose, expires_at)
                VALUES (?, 'checkout', DATE_ADD(NOW(), INTERVAL ? MINUTE))
            ")->execute([$phone, self::OTP_EXPIRY_MINUTES]);
        } catch (Exception $e) {
            error_log('OTPService::recordSend error: ' . $e->getMessage());
        }
    }

    private function markVerified(string $phone): void
    {
        try {
            $conn = getConnection();
            $conn->prepare("
                UPDATE otp_verifications
                SET    is_verified = 1, verified_at = NOW()
                WHERE  phone = ? AND purpose = 'checkout'
                  AND  is_verified = 0
                  AND  expires_at > NOW()
                ORDER  BY id DESC
                LIMIT  1
            ")->execute([$phone]);
        } catch (Exception $e) {
            error_log('OTPService::markVerified error: ' . $e->getMessage());
        }
    }

    private function incrementAttempts(string $phone): void
    {
        try {
            $conn = getConnection();
            $conn->prepare("
                UPDATE otp_verifications
                SET    attempts = attempts + 1
                WHERE  phone = ? AND purpose = 'checkout' AND is_verified = 0
                ORDER  BY id DESC
                LIMIT  1
            ")->execute([$phone]);
        } catch (Exception $e) {
            error_log('OTPService::incrementAttempts error: ' . $e->getMessage());
        }
    }

    private function exceededAttemptLimit(string $phone): bool
    {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("
                SELECT attempts FROM otp_verifications
                WHERE  phone = ? AND purpose = 'checkout'
                  AND  is_verified = 0 AND expires_at > NOW()
                ORDER  BY id DESC LIMIT 1
            ");
            $stmt->execute([$phone]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && (int)$row['attempts'] >= self::MAX_VERIFY_ATTEMPTS;
        } catch (Exception $e) {
            return false;
        }
    }

    private function exceedsSendLimit(string $phone): bool
    {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS cnt FROM otp_verifications
                WHERE  phone = ? AND purpose = 'checkout'
                  AND  created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$phone]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && (int)$row['cnt'] >= self::MAX_SEND_PER_HOUR;
        } catch (Exception $e) {
            return false;
        }
    }

    private function secondsSinceLastSend(string $phone): ?int
    {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("
                SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS secs
                FROM   otp_verifications
                WHERE  phone = ? AND purpose = 'checkout'
                ORDER  BY id DESC LIMIT 1
            ");
            $stmt->execute([$phone]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['secs'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    // ----------------------------------------------------------------
    // Validation helpers
    // ----------------------------------------------------------------

    private function normalisePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        // Strip leading 91 country code if present
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        return $phone;
    }

    private function validatePhone(string $phone): bool
    {
        return (bool) preg_match('/^[6-9]\d{9}$/', $phone);
    }

    // ----------------------------------------------------------------
    // cURL helpers
    // ----------------------------------------------------------------

    private function curlPost(string $url, array $data): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw   = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('OTPService cURL error: ' . $error);
            return null;
        }
        return json_decode($raw, true);
    }

    private function curlGet(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw   = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('OTPService cURL error: ' . $error);
            return null;
        }
        return json_decode($raw, true);
    }
}
