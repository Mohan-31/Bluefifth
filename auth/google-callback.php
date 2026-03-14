<?php
// auth/google-callback.php - COMPLETE Enhanced with Welcome Email System
session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include files
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/sendinblue-mailer.php';
require_once 'session.php';

// Set content type
header('Content-Type: application/json');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id_token'])) {
    echo json_encode(['success' => false, 'message' => 'No ID token provided']);
    exit;
}

$id_token = $input['id_token'];
$isSilentAuth = isset($input['silent_auth']) && $input['silent_auth'];
$isReferralAuth = isset($input['referral_auth']) && $input['referral_auth'];
$isCheckoutAuth = isset($input['checkout_auth']) && $input['checkout_auth'];

// Handle magic checkout authentication
if (isset($data['checkout_auth']) && $data['checkout_auth'] === true) {
    // Set checkout session flag
    $_SESSION['checkout_login'] = true;
}

// Verify token with Google
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
$response = file_get_contents($url);

if ($response === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to verify token']);
    exit;
}

$user_info = json_decode($response, true);

if (!isset($user_info['email']) || !isset($user_info['sub'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid token response']);
    exit;
}

// Verify token is for our app
if ($user_info['aud'] !== GOOGLE_CLIENT_ID) {
    echo json_encode(['success' => false, 'message' => 'Token not for this application']);
    exit;
}

try {
    $conn = getConnection();
    $isNewUser = false;
    $welcomeEmailSent = false;
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, welcome_email_sent FROM users WHERE email = ? OR google_id = ?");
    $stmt->execute([$user_info['email'], $user_info['sub']]);
    
    if ($stmt->rowCount() > 0) {
        // Existing user
        $user = $stmt->fetch();
        $userId = $user['id'];
        $welcomeEmailAlreadySent = $user['welcome_email_sent'] ?? false;
        
        // Update user info
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, name = ?, profile_image = ?, last_login = NOW() WHERE id = ?");
        $stmt->execute([
            $user_info['sub'],
            $user_info['name'],
            $user_info['picture'] ?? null,
            $userId
        ]);
        
        // Send welcome email only if never sent before
        if (!$welcomeEmailAlreadySent) {
            $welcomeEmailSent = sendWelcomeEmail($userId, $user_info['name'], $user_info['email']);
            
            if ($welcomeEmailSent) {
                // Mark welcome email as sent
                $stmt = $conn->prepare("UPDATE users SET welcome_email_sent = 1 WHERE id = ?");
                $stmt->execute([$userId]);
            }
        }
        
    } else {
        // New user
        $isNewUser = true;
        
        $stmt = $conn->prepare("INSERT INTO users (google_id, email, name, profile_image, last_login, welcome_email_sent) VALUES (?, ?, ?, ?, NOW(), 1)");
        $stmt->execute([
            $user_info['sub'],
            $user_info['email'],
            $user_info['name'],
            $user_info['picture'] ?? null
        ]);
        
        $userId = $conn->lastInsertId();
        
        // Create referral code
        $code = generateReferralCode();
        $link = generateReferralLink($code);
        
        $stmt = $conn->prepare("INSERT INTO referrals (user_id, code, link) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $code, $link]);
        
        // Create wallet
        ensureUserWallet($userId);
        
        // Send welcome email for new users
        $welcomeEmailSent = sendWelcomeEmail($userId, $user_info['name'], $user_info['email'], $code, $link);
    }
    
    // Merge guest cart with user cart before login
    if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
        // Function is already available from functions.php
        $mergeSuccess = mergeGuestCartWithUserCart($userId);
        if ($mergeSuccess) {
            // Clear guest cart after successful merge
            unset($_SESSION['guest_cart']);
        }
    }

    // Login user
    loginUser($userId);
    
    $response = [
        'success' => true,
        'message' => 'Login successful',
        'user_id' => $userId,
        'new_user' => $isNewUser,
        'welcome_email_sent' => $welcomeEmailSent
    ];

    
    // Add authentication type for logging
    if ($isSilentAuth) {
        $response['auth_type'] = 'silent';
    } elseif ($isReferralAuth) {
        $response['auth_type'] = 'referral_panel';
    } elseif ($isCheckoutAuth) {
        $response['auth_type'] = 'checkout';
    } else {
        $response['auth_type'] = 'manual';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Send welcome email to new user with premium template
 */
function sendWelcomeEmail($userId, $userName, $userEmail, $referralCode = null, $referralLink = null) {
    try {
        // Load email configuration
        $emailConfig = include '../includes/email-config.php';
        
        if (!$emailConfig['settings']['enabled']) {
            return false;
        }
        
        // Get referral code and link if not provided
        if (!$referralCode || !$referralLink) {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT code, link FROM referrals WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            if ($stmt->rowCount() > 0) {
                $referral = $stmt->fetch();
                $referralCode = $referral['code'];
                $referralLink = $referral['link'];
            }
        }
        
        // Initialize Sendinblue mailer if configured
        if (!empty($emailConfig['sendinblue']['api_key']) && 
            $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE') {
            
            $mailer = new SendinblueMailer(
                $emailConfig['sendinblue']['api_key'],
                $emailConfig['sendinblue']['from_email'],
                $emailConfig['sendinblue']['from_name']
            );
            
            // Send premium welcome email
            $subject = "🎉 Welcome to Velona - Your Referral Journey Begins!";
            $htmlContent = getPremiumWelcomeTemplate($userName, $referralCode, $referralLink);
            $textContent = getWelcomeTextVersion($userName, $referralCode, $referralLink);
            
            $emailSent = $mailer->sendEmail($userEmail, $userName, $subject, $htmlContent, $textContent);
            
            // Log email in database
            if ($emailSent) {
                logWelcomeEmail($userId, $userEmail, $subject, $textContent);
            }
            
            return $emailSent;
            
        } else {
            // Fallback to basic email notification
            $subject = "Welcome to Velona - Your Referral Journey Begins!";
            $message = getWelcomeTextVersion($userName, $referralCode, $referralLink);
            
            $emailSent = sendEmailNotification($userEmail, $subject, $message, 'welcome');
            return $emailSent;
        }
        
    } catch (Exception $e) {
        error_log("Welcome email error: " . $e->getMessage());
        return false;
    }
}

/**
 * Premium welcome email template with exotic styling - COMPLETE VERSION
 */
function getPremiumWelcomeTemplate($userName, $referralCode, $referralLink) {
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Welcome to Velona</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;'>
        
        <!-- Main Container -->
        <div style='max-width: 650px; margin: 0 auto; background: transparent; padding: 40px 20px;'>
            
            <!-- Header with gradient background -->
            <div style='background: linear-gradient(135deg, #ff6b6b 0%, #feca57 25%, #48dbfb 50%, #ff9ff3 75%, #54a0ff 100%); background-size: 400% 400%; border-radius: 25px 25px 0 0; padding: 50px 40px; text-align: center; position: relative; overflow: hidden;'>
                <!-- Animated background overlay -->
                <div style='position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%), linear-gradient(-45deg, rgba(255,255,255,0.1) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.1) 75%), linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.1) 75%); background-size: 30px 30px; background-position: 0 0, 0 15px, 15px -15px, -15px 0px;'></div>
                
                <!-- Header content -->
                <div style='position: relative; z-index: 2;'>
                    <h1 style='margin: 0; font-size: 42px; font-weight: 800; color: white; text-shadow: 0 4px 15px rgba(0,0,0,0.3); letter-spacing: -1px;'>
                        🎉 Welcome to Velona!
                    </h1>
                    <p style='margin: 20px 0 0 0; font-size: 22px; color: rgba(255,255,255,0.95); font-weight: 500; text-shadow: 0 2px 10px rgba(0,0,0,0.2);'>
                        Your premium referral journey starts now!
                    </p>
                </div>
            </div>
            
            <!-- Main content -->
            <div style='background: white; padding: 50px 40px; border-radius: 0 0 25px 25px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);'>
                
                <!-- Personal greeting -->
                <div style='text-align: center; margin-bottom: 40px;'>
                    <h2 style='margin: 0 0 15px 0; font-size: 28px; color: #2c3e50; font-weight: 700;'>
                        Hello {$userName}! 👋
                    </h2>
                    <p style='margin: 0; font-size: 18px; color: #7f8c8d; line-height: 1.6;'>
                        You're now part of our exclusive community where sharing means earning!
                    </p>
                </div>
                
                <!-- Referral details card -->
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 35px; margin: 35px 0; text-align: center; box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);'>
                    <h3 style='margin: 0 0 25px 0; color: white; font-size: 24px; font-weight: 700;'>
                        🔗 Your Personal Referral Hub
                    </h3>
                    
                    <!-- Referral code -->
                    <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; margin: 20px 0; border: 1px solid rgba(255,255,255,0.2);'>
                        <p style='margin: 0 0 10px 0; color: rgba(255,255,255,0.9); font-size: 16px; font-weight: 600;'>Your Referral Code</p>
                        <div style='background: white; color: #667eea; padding: 15px 25px; border-radius: 12px; font-size: 28px; font-weight: 800; letter-spacing: 3px; font-family: monospace; box-shadow: inset 0 2px 10px rgba(0,0,0,0.1);'>
                            {$referralCode}
                        </div>
                    </div>
                    
                    <!-- Referral link -->
                    <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; margin: 20px 0; border: 1px solid rgba(255,255,255,0.2);'>
                        <p style='margin: 0 0 15px 0; color: rgba(255,255,255,0.9); font-size: 16px; font-weight: 600;'>Your Referral Link</p>
                        <div style='background: white; padding: 15px 20px; border-radius: 12px; word-break: break-all; box-shadow: inset 0 2px 10px rgba(0,0,0,0.1);'>
                            <a href='{$referralLink}' style='color: #667eea; text-decoration: none; font-weight: 600; font-size: 16px;'>{$referralLink}</a>
                        </div>
                    </div>
                </div>
                
                <!-- Earning structure -->
                <div style='background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%); border-radius: 20px; padding: 35px; margin: 35px 0; color: white; box-shadow: 0 15px 35px rgba(255, 107, 107, 0.3);'>
                    <h3 style='margin: 0 0 25px 0; font-size: 24px; font-weight: 700; text-align: center;'>
                        💰 Your Earning Structure
                    </h3>
                    
                    <div style='margin: 20px 0;'>
                        <!-- First month -->
                        <div style='background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; margin: 15px 0; border: 1px solid rgba(255,255,255,0.3);'>
                            <div style='text-align: center;'>
                                <h4 style='margin: 0 0 8px 0; font-size: 20px; font-weight: 700;'>🚀 First Month Bonus</h4>
                                <p style='margin: 0 0 15px 0; opacity: 0.9; font-size: 16px;'>Extra rewards for new referrals</p>
                                <div style='background: white; color: #ff6b6b; padding: 15px 25px; border-radius: 50px; font-size: 24px; font-weight: 800; box-shadow: 0 5px 15px rgba(0,0,0,0.2); display: inline-block;'>
                                    10%
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ongoing months -->
                        <div style='background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; margin: 15px 0; border: 1px solid rgba(255,255,255,0.3);'>
                            <div style='text-align: center;'>
                                <h4 style='margin: 0 0 8px 0; font-size: 20px; font-weight: 700;'>🔄 Ongoing Rewards</h4>
                                <p style='margin: 0 0 15px 0; opacity: 0.9; font-size: 16px;'>Sustainable long-term earnings</p>
                                <div style='background: white; color: #feca57; padding: 15px 25px; border-radius: 50px; font-size: 24px; font-weight: 800; box-shadow: 0 5px 15px rgba(0,0,0,0.2); display: inline-block;'>
                                    5%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Claim information -->
                <div style='background: linear-gradient(135deg, #54a0ff 0%, #2e86de 100%); border-radius: 20px; padding: 35px; margin: 35px 0; color: white; text-align: center; box-shadow: 0 15px 35px rgba(84, 160, 255, 0.3);'>
                    <h3 style='margin: 0 0 20px 0; font-size: 24px; font-weight: 700;'>
                        🗓️ Monthly Claim Schedule
                    </h3>
                    <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; border: 1px solid rgba(255,255,255,0.2);'>
                        <p style='margin: 0 0 15px 0; font-size: 18px; font-weight: 600;'>Claim your earnings on:</p>
                        <div style='font-size: 32px; font-weight: 800; margin: 15px 0;'>30th & 31st</div>
                        <p style='margin: 0; opacity: 0.9; font-size: 16px;'>of every month</p>
                    </div>
                    <p style='margin: 20px 0 0 0; opacity: 0.9; font-size: 16px;'>Minimum ₹100 required to claim</p>
                </div>
                
                <!-- Call to action -->
                <div style='text-align: center; margin: 40px 0;'>
                    <a href='{$referralLink}' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; padding: 18px 40px; border-radius: 50px; font-size: 18px; font-weight: 700; box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);'>
                        🚀 Start Sharing & Earning Now
                    </a>
                </div>
                
                <!-- Tips section -->
                <div style='background: #f8f9fa; border-radius: 15px; padding: 30px; margin: 35px 0; border-left: 5px solid #667eea;'>
                    <h4 style='margin: 0 0 20px 0; color: #2c3e50; font-size: 20px; font-weight: 700;'>
                        💡 Pro Tips for Maximum Earnings
                    </h4>
                    <ul style='margin: 0; padding-left: 25px; color: #5a6c7d; line-height: 1.8; font-size: 16px;'>
                        <li style='margin-bottom: 10px;'><strong>Share strategically:</strong> Post your link on social media, WhatsApp groups, and with friends</li>
                        <li style='margin-bottom: 10px;'><strong>Timing matters:</strong> New users in their first month give you 10% instead of 5%</li>
                        <li style='margin-bottom: 10px;'><strong>Be authentic:</strong> Recommend products you genuinely believe in</li>
                        <li style='margin-bottom: 0;'><strong>Track performance:</strong> Monitor your dashboard to see what's working</li>
                    </ul>
                </div>
                
                <!-- Footer -->
                <div style='text-align: center; margin-top: 50px; padding-top: 30px; border-top: 2px solid #ecf0f1;'>
                    <p style='margin: 0 0 15px 0; color: #7f8c8d; font-size: 16px;'>
                        Questions? We're here to help! 💬
                    </p>
                    <p style='margin: 0; color: #2c3e50; font-size: 18px; font-weight: 600;'>
                        Welcome to the Velona family! 🎊
                    </p>
                    <div style='margin: 25px 0 0 0; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px;'>
                        <p style='margin: 0; color: white; font-size: 16px; font-weight: 600;'>
                            Best regards,<br>
                            <strong style='font-size: 18px;'>The Velona Team</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Text version of welcome email - COMPLETE VERSION
 */
function getWelcomeTextVersion($userName, $referralCode, $referralLink) {
    return "🎉 Welcome to Velona - Your Referral Journey Begins!

Hello {$userName}! 👋

You're now part of our exclusive referral community where sharing means earning!

🔗 YOUR PERSONAL REFERRAL DETAILS:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Referral Code: {$referralCode}
Referral Link: {$referralLink}

💰 YOUR EARNING STRUCTURE:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 First Month Bonus: 10%
   Extra rewards for new referrals

🔄 Ongoing Rewards: 5%
   Sustainable long-term earnings

🗓️ MONTHLY CLAIM SCHEDULE:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Claim your earnings on: 30th & 31st of every month
Minimum ₹100 required to claim

💡 PRO TIPS FOR MAXIMUM EARNINGS:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• Share strategically: Post your link on social media, WhatsApp groups, and with friends
• Timing matters: New users in their first month give you 10% instead of 5%
• Be authentic: Recommend products you genuinely believe in
• Track performance: Monitor your dashboard to see what's working

🚀 Start Sharing & Earning Now: {$referralLink}

Questions? We're here to help! 💬

Welcome to the Velona family! 🎊

Best regards,
The Velona Team";
}

/**
 * Log welcome email in database - COMPLETE VERSION
 */
function logWelcomeEmail($userId, $userEmail, $subject, $message) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("
            INSERT INTO email_notifications 
            (user_id, email_type, subject, message, sent_at, status) 
            VALUES (?, 'welcome', ?, ?, NOW(), 'sent')
        ");
        $stmt->execute([$userId, $subject, $message]);
        return true;
    } catch (Exception $e) {
        error_log("Welcome email logging error: " . $e->getMessage());
        return false;
    }
}
?>