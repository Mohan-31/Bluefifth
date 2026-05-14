<?php
// Google OAuth has been replaced by phone-OTP checkout. This file is disabled.
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Google Sign-In is no longer supported. Please use phone verification at checkout.']);
exit;

// auth/google-redirect.php - Handle Google OAuth callback
session_start();

// Include files
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once 'session.php';

// Handle OAuth callback
if (isset($_GET['code']) && isset($_GET['state'])) {
    $code = $_GET['code'];
    $state = $_GET['state'];
    
    // Exchange code for access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => 'http://localhost/ecommerce-project/auth/google-redirect.php'
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($tokenData)
        ]
    ]);
    
    $tokenResponse = file_get_contents($tokenUrl, false, $context);
    $tokenInfo = json_decode($tokenResponse, true);
    
    if (isset($tokenInfo['id_token'])) {
        // Verify and decode the ID token
        $idToken = $tokenInfo['id_token'];
        $userInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
        $userInfoResponse = file_get_contents($userInfoUrl);
        $userInfo = json_decode($userInfoResponse, true);
        
        if (isset($userInfo['email']) && isset($userInfo['sub'])) {
            try {
                $conn = getConnection();
                
                // Check if user exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR google_id = ?");
                $stmt->execute([$userInfo['email'], $userInfo['sub']]);
                
                if ($stmt->rowCount() > 0) {
                    // Existing user
                    $user = $stmt->fetch();
                    $userId = $user['id'];
                    
                    // Update user info
                    $stmt = $conn->prepare("UPDATE users SET google_id = ?, name = ?, profile_image = ?, last_login = NOW() WHERE id = ?");
                    $stmt->execute([
                        $userInfo['sub'],
                        $userInfo['name'],
                        $userInfo['picture'] ?? null,
                        $userId
                    ]);
                    
                } else {
                    // New user
                    $stmt = $conn->prepare("INSERT INTO users (google_id, email, name, profile_image, last_login) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $userInfo['sub'],
                        $userInfo['email'],
                        $userInfo['name'],
                        $userInfo['picture'] ?? null
                    ]);
                    
                    $userId = $conn->lastInsertId();
                    
                    // Create referral code
                    $code = generateReferralCode();
                    $link = generateReferralLink($code);
                    
                    $stmt = $conn->prepare("INSERT INTO referrals (user_id, code, link) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, $code, $link]);
                    
                    // Create wallet
                    ensureUserWallet($userId);
                }
                
                // Login user
                loginUser($userId);
                
                // Redirect back to main page
                echo "
                <script>
                    // Close popup if opened from popup
                    if (window.opener) {
                        window.opener.location.reload();
                        window.close();
                    } else {
                        // Direct redirect
                        window.location.href = '/ecommerce-project/index.php';
                    }
                </script>
                <p>Login successful! Redirecting...</p>
                ";
                
            } catch (Exception $e) {
                echo "
                <script>
                    if (window.opener) {
                        window.opener.alert('Login failed: Database error');
                        window.close();
                    } else {
                        alert('Login failed: Database error');
                        window.location.href = '/ecommerce-project/index.php';
                    }
                </script>
                ";
            }
        } else {
            echo "
            <script>
                if (window.opener) {
                    window.opener.alert('Login failed: Invalid user data');
                    window.close();
                } else {
                    alert('Login failed: Invalid user data');
                    window.location.href = '/ecommerce-project/index.php';
                }
            </script>
            ";
        }
    } else {
        echo "
        <script>
            if (window.opener) {
                window.opener.alert('Login failed: No access token');
                window.close();
            } else {
                alert('Login failed: No access token');
                window.location.href = '/ecommerce-project/index.php';
            }
        </script>
        ";
    }
} else if (isset($_GET['error'])) {
    // Handle OAuth error
    echo "
    <script>
        if (window.opener) {
            window.opener.alert('Login cancelled or failed');
            window.close();
        } else {
            alert('Login cancelled or failed');
            window.location.href = '/ecommerce-project/index.php';
        }
    </script>
    ";
} else {
    // No code or error, redirect to home
    echo "
    <script>
        if (window.opener) {
            window.close();
        } else {
            window.location.href = '/ecommerce-project/index.php';
        }
    </script>
    ";
}
?>