<?php
// includes/sendinblue-mailer.php - Professional email system with Test Runner Quality Templates
class SendinblueMailer {
    private $apiKey;
    private $apiUrl = 'https://api.sendinblue.com/v3/smtp/email';
    private $fromEmail;
    private $fromName;
    
    public function __construct($apiKey, $fromEmail = 'info@bluefifth.in', $fromName = 'bluefifth Team') {
        $this->apiKey = $apiKey;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }
    
    public function sendEmail($to, $toName, $subject, $htmlContent, $textContent = null) {
        // Prepare email data
        $emailData = [
            'sender' => [
                'name' => $this->fromName,
                'email' => $this->fromEmail
            ],
            'to' => [
                [
                    'email' => $to,
                    'name' => $toName
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent
        ];
        
        // Add text content if provided
        if ($textContent) {
            $emailData['textContent'] = $textContent;
        }
        
        // Send via Sendinblue API and log to database
        $emailSent = $this->makeApiCall($emailData);
        
        if ($emailSent) {
            $this->logEmailToDatabase($to, 'sendinblue_email', $subject, $textContent ?: 'HTML email sent');
        }
        
        return $emailSent;
    }
    
    private function makeApiCall($data) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Sendinblue cURL Error: " . $error);
            return false;
        }
        
        if ($httpCode === 201) {
            error_log("Email sent successfully via Sendinblue");
            return true;
        } else {
            error_log("Sendinblue API Error: HTTP $httpCode - " . $response);
            return false;
        }
    }
    
    // Send referral points earned email - TEST RUNNER QUALITY
    public function sendPointsEarnedEmail($userEmail, $userName, $points, $monthlyBreakdown) {
        $subject = "🎉 You've Earned ₹{$points} in Referral Points!";
        
        $htmlContent = $this->getTestRunnerPointsEarnedTemplate($userName, $points, $monthlyBreakdown);
        $textContent = $this->getPointsEarnedTextVersion($userName, $points, $monthlyBreakdown);
        
        return $this->sendEmail($userEmail, $userName, $subject, $htmlContent, $textContent);
    }
    
    // Send claim reminder email - TEST RUNNER QUALITY
    public function sendClaimReminderEmail($userEmail, $userName, $currentDay, $availablePoints) {
        $subject = "⏰ Referral Claim Reminder - Month End Approaching";
        
        $htmlContent = $this->getTestRunnerClaimReminderTemplate($userName, $currentDay, $availablePoints);
        $textContent = $this->getClaimReminderTextVersion($userName, $currentDay, $availablePoints);
        
        return $this->sendEmail($userEmail, $userName, $subject, $htmlContent, $textContent);
    }
    
    // Send claim submitted email - TEST RUNNER QUALITY
    public function sendClaimSubmittedEmail($userEmail, $userName, $claimId, $amount, $breakdown) {
        $subject = "✅ Claim Submitted - ₹{$amount} Processing";
        
        $htmlContent = $this->getTestRunnerClaimSubmittedTemplate($userName, $claimId, $amount, $breakdown);
        $textContent = $this->getClaimSubmittedTextVersion($userName, $claimId, $amount, $breakdown);
        
        return $this->sendEmail($userEmail, $userName, $subject, $htmlContent, $textContent);
    }
    
    // Send payment processed email - TEST RUNNER QUALITY
    public function sendPaymentProcessedEmail($userEmail, $userName, $amount, $breakdown) {
        $subject = "💰 Payment Processed - ₹{$amount} Transferred!";
        
        $htmlContent = $this->getTestRunnerPaymentProcessedTemplate($userName, $amount, $breakdown);
        $textContent = $this->getPaymentProcessedTextVersion($userName, $amount, $breakdown);
        
        return $this->sendEmail($userEmail, $userName, $subject, $htmlContent, $textContent);
    }
    
    // Send insufficient balance reminder email - MISSING METHOD ADDED
    public function sendInsufficientBalanceEmail($userEmail, $userName, $currentPoints, $minRequired) {
        $shortfall = $minRequired - $currentPoints;
        $subject = "💰 Earn More to Claim - ₹{$shortfall} More Needed!";
        
        $htmlContent = $this->getTestRunnerInsufficientBalanceTemplate($userName, $currentPoints, $minRequired, $shortfall);
        $textContent = $this->getInsufficientBalanceTextVersion($userName, $currentPoints, $minRequired, $shortfall);
        
        return $this->sendEmail($userEmail, $userName, $subject, $htmlContent, $textContent);
    }
    
    // Send claim rejected email - MISSING METHOD ADDED
    public function sendClaimRejectedEmail($userEmail, $userName, $claimId, $amount, $reason) {
        $subject = "❌ Claim Rejected - ₹{$amount} Returned to Wallet";
        
        $htmlContent = $this->getTestRunnerClaimRejectedTemplate($userName, $claimId, $amount, $reason);
        $textContent = $this->getClaimRejectedTextVersion($userName, $claimId, $amount, $reason);
        
        return $this->sendEmail($userEmail, $userName, $subject, $htmlContent, $textContent);
    }
    
    // MONTHLY REMINDER SYSTEM - Send reminders on 30th/31st
    public function sendMonthlyClaimAvailableEmail($userEmail, $userName, $currentDay, $availablePoints) {
        $subject = "🎉 Time to Claim Your ₹{$availablePoints}! Today is Claim Date!";
        
        $htmlContent = $this->getMonthlyClaimAvailableTemplate($userName, $currentDay, $availablePoints);
        $textContent = $this->getMonthlyClaimAvailableTextVersion($userName, $currentDay, $availablePoints);
        
        return $this->sendEmail($userEmail, $userName, $subject, $htmlContent, $textContent);
    }
    
    // Send monthly "start earning" reminder for users with insufficient points
    public function sendMonthlyStartEarningEmail($userEmail, $userName, $currentDay, $currentPoints) {
        $subject = "💰 Start Earning Referral Points Today! Claim Date is Here!";
        
        $htmlContent = $this->getMonthlyStartEarningTemplate($userName, $currentDay, $currentPoints);
        $textContent = $this->getMonthlyStartEarningTextVersion($userName, $currentDay, $currentPoints);
        
        return $this->sendEmail($userEmail, $userName, $subject, $htmlContent, $textContent);
    }
    
    // Batch send monthly reminders to all users
    public function sendMonthlyRemindersToAllUsers() {
        // Check if today is 30th or 31st
        $currentDay = date('j');
        if ($currentDay != 30 && $currentDay != 31) {
            return ['success' => false, 'message' => 'Not a claim date'];
        }
        
        try {
            // Get database connection
            if (!function_exists('getConnection')) {
                return ['success' => false, 'message' => 'Database connection not available'];
            }
            
            $conn = getConnection();
            
            // Get all users with wallet info
            $stmt = $conn->query("
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    COALESCE(w.points, 0) as wallet_points
                FROM users u
                LEFT JOIN wallet w ON u.id = w.user_id
                WHERE u.email IS NOT NULL AND u.email != ''
                ORDER BY u.id
            ");
            
            $users = $stmt->fetchAll();
            $results = [
                'success' => true,
                'total_users' => count($users),
                'emails_sent' => 0,
                'emails_failed' => 0,
                'users_with_points' => 0,
                'users_without_points' => 0
            ];
            
            foreach ($users as $user) {
                $hasEnoughPoints = $user['wallet_points'] >= 100;
                $emailSent = false;
                
                try {
                    if ($hasEnoughPoints) {
                        // Send "You can claim now!" email
                        $emailSent = $this->sendMonthlyClaimAvailableEmail(
                            $user['email'], 
                            $user['name'], 
                            $currentDay, 
                            $user['wallet_points']
                        );
                        $results['users_with_points']++;
                    } else {
                        // Send "Start earning!" email
                        $emailSent = $this->sendMonthlyStartEarningEmail(
                            $user['email'], 
                            $user['name'], 
                            $currentDay, 
                            $user['wallet_points']
                        );
                        $results['users_without_points']++;
                    }
                    
                    if ($emailSent) {
                        $results['emails_sent']++;
                        
                        // Log in database
                        try {
                            $stmt = $conn->prepare("
                                INSERT INTO email_notifications 
                                (user_id, email_type, subject, message, sent_at, status) 
                                VALUES (?, ?, ?, ?, NOW(), 'sent')
                            ");
                            
                            $emailType = $hasEnoughPoints ? 'monthly_claim_available' : 'monthly_start_earning';
                            $subject = $hasEnoughPoints 
                                ? "Time to Claim Your ₹{$user['wallet_points']}!" 
                                : "Start Earning Referral Points Today!";
                            
                            $stmt->execute([$user['id'], $emailType, $subject, 'Monthly reminder email']);
                        } catch (Exception $e) {
                            error_log("Failed to log monthly email: " . $e->getMessage());
                        }
                    } else {
                        $results['emails_failed']++;
                    }
                    
                    // Small delay
                    usleep(100000); // 0.1 second
                    
                } catch (Exception $e) {
                    $results['emails_failed']++;
                    error_log("Monthly email error for user {$user['id']}: " . $e->getMessage());
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // TEST RUNNER QUALITY: Points Earned Template
    private function getTestRunnerPointsEarnedTemplate($userName, $points, $breakdown) {
        $breakdownHtml = '';
        foreach ($breakdown as $month) {
            $monthName = ($month['purchase_month'] == 1) ? "Month 1 (First Month Bonus)" : "Month {$month['purchase_month']}";
            $breakdownHtml .= "
                <tr style='background: rgba(255,255,255,0.8);'>
                    <td style='padding: 15px; border: none; border-radius: 8px 0 0 8px; font-weight: 600; color: #2c3e50;'>{$monthName}</td>
                    <td style='padding: 15px; border: none; text-align: center; font-weight: 600; color: #e74c3c;'>{$month['earning_rate']}%</td>
                    <td style='padding: 15px; border: none; text-align: center; color: #34495e;'>{$month['purchase_count']}</td>
                    <td style='padding: 15px; border: none; border-radius: 0 8px 8px 0; font-weight: 700; color: #27ae60; font-size: 16px;'>₹{$month['month_points']}</td>
                </tr>
                <tr style='height: 8px;'><td colspan='4'></td></tr>
            ";
        }
        
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Referral Points Earned</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;'>
            
            <!-- Main Container -->
            <div style='max-width: 650px; margin: 0 auto; background: transparent; padding: 40px 20px;'>
                
                <!-- Header with animated gradient -->
                <div style='background: linear-gradient(135deg, #27ae60 0%, #2ecc71 25%, #3498db 50%, #9b59b6 75%, #e74c3c 100%); background-size: 400% 400%; border-radius: 25px 25px 0 0; padding: 50px 40px; text-align: center; position: relative; overflow: hidden;'>
                    <!-- Animated pattern overlay -->
                    <div style='position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255,255,255,0.15) 0%, transparent 50%), radial-gradient(circle at 40% 40%, rgba(255,255,255,0.1) 0%, transparent 50%);'></div>
                    
                    <!-- Header content -->
                    <div style='position: relative; z-index: 2;'>
                        <h1 style='margin: 0; font-size: 48px; font-weight: 800; color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.3); letter-spacing: -1px;'>
                            🎉 Congratulations!
                        </h1>
                        <p style='margin: 20px 0 0 0; font-size: 24px; color: rgba(255,255,255,0.95); font-weight: 600; text-shadow: 0 2px 10px rgba(0,0,0,0.2);'>
                            You've earned referral points!
                        </p>
                    </div>
                </div>
                
                <!-- Main content -->
                <div style='background: white; padding: 50px 40px; border-radius: 0 0 25px 25px; box-shadow: 0 25px 50px rgba(0,0,0,0.15);'>
                    
                    <!-- Personal greeting -->
                    <div style='text-align: center; margin-bottom: 45px;'>
                        <h2 style='margin: 0 0 15px 0; font-size: 32px; color: #2c3e50; font-weight: 700;'>
                            Hello {$userName}! 👋
                        </h2>
                        <p style='margin: 0; font-size: 18px; color: #7f8c8d; line-height: 1.6;'>
                            Your referral network is growing and earning you real money!
                        </p>
                    </div>
                    
                    <!-- Amount earned highlight -->
                    <div style='background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); border-radius: 25px; padding: 40px; margin: 40px 0; text-align: center; box-shadow: 0 20px 40px rgba(39, 174, 96, 0.3); position: relative; overflow: hidden;'>
                        <!-- Decorative elements -->
                        <div style='position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;'></div>
                        <div style='position: absolute; bottom: -30px; left: -30px; width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%;'></div>
                        
                        <div style='position: relative; z-index: 2;'>
                            <h3 style='margin: 0 0 15px 0; color: white; font-size: 20px; font-weight: 600; opacity: 0.9;'>
                                Total Amount Earned
                            </h3>
                            <div style='font-size: 56px; font-weight: 800; color: white; margin: 20px 0; text-shadow: 0 4px 15px rgba(0,0,0,0.2);'>
                                ₹{$points}
                            </div>
                            <p style='margin: 0; color: rgba(255,255,255,0.9); font-size: 16px; font-weight: 500;'>
                                Added to your wallet • Ready to claim
                            </p>
                        </div>
                    </div>
                    
                    <!-- Earning breakdown -->
                    <div style='background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); border-radius: 25px; padding: 40px; margin: 40px 0; color: white; box-shadow: 0 20px 40px rgba(52, 152, 219, 0.3);'>
                        <h3 style='margin: 0 0 30px 0; font-size: 26px; font-weight: 700; text-align: center;'>
                            📊 Monthly Earning Breakdown
                        </h3>
                        
                        <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.2);'>
                            <table style='width: 100%; border-collapse: separate; border-spacing: 0;'>
                                <thead>
                                    <tr style='background: rgba(255,255,255,0.2); border-radius: 12px;'>
                                        <th style='padding: 18px 15px; color: white; font-weight: 700; font-size: 16px; text-align: left; border-radius: 12px 0 0 12px;'>Month</th>
                                        <th style='padding: 18px 15px; color: white; font-weight: 700; font-size: 16px; text-align: center;'>Rate</th>
                                        <th style='padding: 18px 15px; color: white; font-weight: 700; font-size: 16px; text-align: center;'>Purchases</th>
                                        <th style='padding: 18px 15px; color: white; font-weight: 700; font-size: 16px; text-align: center; border-radius: 0 12px 12px 0;'>Earned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style='height: 12px;'><td colspan='4'></td></tr>
                                    {$breakdownHtml}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Next steps -->
                    <div style='background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); border-radius: 25px; padding: 40px; margin: 40px 0; color: white; text-align: center; box-shadow: 0 20px 40px rgba(155, 89, 182, 0.3);'>
                        <h3 style='margin: 0 0 25px 0; font-size: 26px; font-weight: 700;'>
                            💡 What's Next?
                        </h3>
                        <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 18px; padding: 30px; border: 1px solid rgba(255,255,255,0.2);'>
                            <div style='margin-bottom: 20px;'>
                                <div style='font-size: 24px; margin-bottom: 8px;'>🗓️</div>
                                <h4 style='margin: 0 0 8px 0; font-size: 18px; font-weight: 700;'>Claim Schedule</h4>
                                <p style='margin: 0; opacity: 0.9; font-size: 16px;'>Available on 30th & 31st of every month</p>
                            </div>
                            <div style='margin-bottom: 20px;'>
                                <div style='font-size: 24px; margin-bottom: 8px;'>💰</div>
                                <h4 style='margin: 0 0 8px 0; font-size: 18px; font-weight: 700;'>Minimum Amount</h4>
                                <p style='margin: 0; opacity: 0.9; font-size: 16px;'>₹100 required to process claims</p>
                            </div>
                            <div>
                                <div style='font-size: 24px; margin-bottom: 8px;'>🔄</div>
                                <h4 style='margin: 0 0 8px 0; font-size: 18px; font-weight: 700;'>Keep Earning</h4>
                                <p style='margin: 0; opacity: 0.9; font-size: 16px;'>First month: 10% • Other months: 5%</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Call to action -->
                    <div style='text-align: center; margin: 50px 0;'>
                        <div style='background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); display: inline-block; border-radius: 50px; padding: 4px; box-shadow: 0 15px 30px rgba(231, 76, 60, 0.4);'>
                            <a href='https://velona.com/dashboard' style='display: block; background: white; color: #e74c3c; text-decoration: none; padding: 18px 45px; border-radius: 46px; font-size: 18px; font-weight: 700;'>
                                🚀 View Your Dashboard
                            </a>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style='text-align: center; margin-top: 60px; padding-top: 40px; border-top: 3px solid #ecf0f1;'>
                        <p style='margin: 0 0 20px 0; color: #7f8c8d; font-size: 16px;'>
                            Thank you for being part of our referral program! 💜
                        </p>
                        <div style='margin: 30px 0 0 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 18px;'>
                            <p style='margin: 0; color: white; font-size: 16px; font-weight: 600;'>
                                Best regards,<br>
                                <strong style='font-size: 20px;'>The bluefifth Team</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // TEST RUNNER QUALITY: Claim Reminder Template
    private function getTestRunnerClaimReminderTemplate($userName, $currentDay, $availablePoints) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Claim Reminder</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;'>
            
            <!-- Main Container -->
            <div style='max-width: 650px; margin: 0 auto; background: transparent; padding: 40px 20px;'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #ffc107 0%, #ff8f00 25%, #ff6f00 50%, #e65100 75%, #d84315 100%); background-size: 400% 400%; border-radius: 25px 25px 0 0; padding: 50px 40px; text-align: center; position: relative; overflow: hidden;'>
                    <div style='position: relative; z-index: 2;'>
                        <h1 style='margin: 0; font-size: 48px; font-weight: 800; color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.3);'>
                            ⏰ Claim Reminder
                        </h1>
                        <p style='margin: 20px 0 0 0; font-size: 24px; color: rgba(255,255,255,0.95); font-weight: 600;'>
                            Your points are waiting!
                        </p>
                    </div>
                </div>
                
                <!-- Main content -->
                <div style='background: white; padding: 50px 40px; border-radius: 0 0 25px 25px; box-shadow: 0 25px 50px rgba(0,0,0,0.15);'>
                    
                    <div style='text-align: center; margin-bottom: 40px;'>
                        <h2 style='margin: 0 0 15px 0; font-size: 32px; color: #2c3e50; font-weight: 700;'>
                            Hello {$userName}! 👋
                        </h2>
                        <p style='margin: 0; font-size: 18px; color: #7f8c8d; line-height: 1.6;'>
                            You tried to claim your points today, but claims are only allowed on month-end.
                        </p>
                    </div>
                    
                    <!-- Current Status -->
                    <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-radius: 20px; padding: 35px; margin: 35px 0; border-left: 5px solid #ffc107;'>
                        <h3 style='margin: 0 0 20px 0; color: #856404; font-size: 24px; font-weight: 700;'>
                            📅 Current Status
                        </h3>
                        <div style='background: rgba(255,255,255,0.7); border-radius: 15px; padding: 25px;'>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #856404;'>Today:</strong> <span style='color: #6c757d;'>" . date('jS F Y') . " (Day {$currentDay})</span>
                            </div>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #856404;'>Available Balance:</strong> <span style='color: #27ae60; font-weight: 700; font-size: 18px;'>₹{$availablePoints}</span>
                            </div>
                            <div>
                                <strong style='color: #856404;'>Next Claim Date:</strong> <span style='color: #e74c3c; font-weight: 600;'>30th or 31st of this month</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Claim Schedule -->
                    <div style='background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 20px; padding: 35px; margin: 35px 0; border-left: 5px solid #28a745;'>
                        <h3 style='margin: 0 0 20px 0; color: #155724; font-size: 24px; font-weight: 700;'>
                            ✅ Claim Schedule
                        </h3>
                        <div style='background: rgba(255,255,255,0.7); border-radius: 15px; padding: 25px;'>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #155724;'>Claims allowed:</strong> <span style='color: #6c757d;'>30th and 31st of every month only</span>
                            </div>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #155724;'>Minimum required:</strong> <span style='color: #e74c3c; font-weight: 600;'>₹100</span>
                            </div>
                            <div>
                                <strong style='color: #155724;'>Your points are safe</strong> <span style='color: #6c757d;'>and will be available for claiming</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer message -->
                    <div style='text-align: center; margin: 50px 0;'>
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 30px; color: white;'>
                            <h3 style='margin: 0 0 15px 0; font-size: 22px; font-weight: 700;'>
                                We'll remind you again when the claim window opens! 📬
                            </h3>
                            <p style='margin: 0; opacity: 0.9; font-size: 16px;'>
                                Keep earning more points by sharing your referral link!
                            </p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style='text-align: center; margin-top: 60px; padding-top: 40px; border-top: 3px solid #ecf0f1;'>
                        <div style='margin: 30px 0 0 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 18px;'>
                            <p style='margin: 0; color: white; font-size: 16px; font-weight: 600;'>
                                Best regards,<br>
                                <strong style='font-size: 20px;'>The bluefifth Team</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // TEST RUNNER QUALITY: Claim Submitted Template
    private function getTestRunnerClaimSubmittedTemplate($userName, $claimId, $amount, $breakdown) {
        $breakdownHtml = '';
        foreach ($breakdown as $month) {
            $monthName = ($month['purchase_month'] == 1) ? "Month 1 (First Month Bonus)" : "Month {$month['purchase_month']}";
            $breakdownHtml .= "
                <tr style='background: rgba(255,255,255,0.8);'>
                    <td style='padding: 15px; border: none; border-radius: 8px 0 0 8px; font-weight: 600; color: #2c3e50;'>{$monthName}</td>
                    <td style='padding: 15px; border: none; text-align: center; font-weight: 600; color: #e74c3c;'>{$month['earning_rate']}%</td>
                    <td style='padding: 15px; border: none; text-align: center; color: #34495e;'>{$month['purchase_count']}</td>
                    <td style='padding: 15px; border: none; border-radius: 0 8px 8px 0; font-weight: 700; color: #27ae60; font-size: 16px;'>₹{$month['month_points']}</td>
                </tr>
                <tr style='height: 8px;'><td colspan='4'></td></tr>
            ";
        }
        
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Claim Submitted</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;'>
            
            <!-- Main Container -->
            <div style='max-width: 650px; margin: 0 auto; background: transparent; padding: 40px 20px;'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #007bff 0%, #0056b3 25%, #004085 50%, #002752 75%, #001a33 100%); border-radius: 25px 25px 0 0; padding: 50px 40px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 48px; font-weight: 800; color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.3);'>
                        ✅ Claim Submitted
                    </h1>
                    <p style='margin: 20px 0 0 0; font-size: 24px; color: rgba(255,255,255,0.95); font-weight: 600;'>
                        Your request is being processed
                    </p>
                </div>
                
                <!-- Main content -->
                <div style='background: white; padding: 50px 40px; border-radius: 0 0 25px 25px; box-shadow: 0 25px 50px rgba(0,0,0,0.15);'>
                    
                    <div style='text-align: center; margin-bottom: 40px;'>
                        <h2 style='margin: 0 0 15px 0; font-size: 32px; color: #2c3e50; font-weight: 700;'>
                            Hello {$userName}! 👋
                        </h2>
                        <p style='margin: 0; font-size: 18px; color: #27ae60; font-weight: 600; line-height: 1.6;'>
                            Your referral points claim has been submitted successfully!
                        </p>
                    </div>
                    
                    <!-- Claim Details -->
                    <div style='background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%); border-radius: 20px; padding: 35px; margin: 35px 0; border-left: 5px solid #007bff;'>
                        <h3 style='margin: 0 0 25px 0; color: #007bff; font-size: 24px; font-weight: 700;'>
                            📋 Claim Details
                        </h3>
                        <div style='background: rgba(255,255,255,0.7); border-radius: 15px; padding: 25px;'>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #007bff;'>Claim ID:</strong> <span style='color: #6c757d; font-family: monospace; background: #f8f9fa; padding: 4px 8px; border-radius: 4px;'>{$claimId}</span>
                            </div>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #007bff;'>Amount Claimed:</strong> <span style='color: #27ae60; font-weight: 700; font-size: 20px;'>₹{$amount}</span>
                            </div>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #007bff;'>Submission Date:</strong> <span style='color: #6c757d;'>" . date('Y-m-d H:i:s') . "</span>
                            </div>
                            <div>
                                <strong style='color: #007bff;'>Status:</strong> <span style='color: #dc3545; font-weight: 600; background: #fff5f5; padding: 4px 12px; border-radius: 12px;'>PENDING ADMIN APPROVAL</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Breakdown -->
                    <div style='background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); border-radius: 25px; padding: 40px; margin: 40px 0; color: white; box-shadow: 0 20px 40px rgba(52, 152, 219, 0.3);'>
                        <h3 style='margin: 0 0 30px 0; font-size: 26px; font-weight: 700; text-align: center;'>
                            📊 Monthly Breakdown
                        </h3>
                        
                        <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.2);'>
                            <table style='width: 100%; border-collapse: separate; border-spacing: 0;'>
                                <thead>
                                    <tr style='background: rgba(255,255,255,0.2); border-radius: 12px;'>
                                        <th style='padding: 18px 15px; color: white; font-weight: 700; font-size: 16px; text-align: left; border-radius: 12px 0 0 12px;'>Month</th>
                                        <th style='padding: 18px 15px; color: white; font-weight: 700; font-size: 16px; text-align: center;'>Rate</th>
                                        <th style='padding: 18px 15px; color: white; font-weight: 700; font-size: 16px; text-align: center;'>Purchases</th>
                                        <th style='padding: 18px 15px; color: white; font-weight: 700; font-size: 16px; text-align: center; border-radius: 0 12px 12px 0;'>Earned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style='height: 12px;'><td colspan='4'></td></tr>
                                    {$breakdownHtml}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- What Happens Next -->
                    <div style='background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); border-radius: 25px; padding: 40px; margin: 40px 0; color: white; text-align: center; box-shadow: 0 20px 40px rgba(155, 89, 182, 0.3);'>
                        <h3 style='margin: 0 0 25px 0; font-size: 26px; font-weight: 700;'>
                            ⏳ What Happens Next?
                        </h3>
                        <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 18px; padding: 30px; border: 1px solid rgba(255,255,255,0.2);'>
                            <div style='margin-bottom: 20px;'>
                                <div style='font-size: 24px; margin-bottom: 8px;'>👨‍💼</div>
                                <h4 style='margin: 0 0 8px 0; font-size: 18px; font-weight: 700;'>Admin Review</h4>
                                <p style='margin: 0; opacity: 0.9; font-size: 16px;'>Our admin team will review your claim within 24 hours</p>
                            </div>
                            <div style='margin-bottom: 20px;'>
                                <div style='font-size: 24px; margin-bottom: 8px;'>📧</div>
                                <h4 style='margin: 0 0 8px 0; font-size: 18px; font-weight: 700;'>Email Confirmation</h4>
                                <p style='margin: 0; opacity: 0.9; font-size: 16px;'>You'll receive a confirmation email once payment is processed</p>
                            </div>
                            <div>
                                <div style='font-size: 24px; margin-bottom: 8px;'>💰</div>
                                <h4 style='margin: 0 0 8px 0; font-size: 18px; font-weight: 700;'>Payment Transfer</h4>
                                <p style='margin: 0; opacity: 0.9; font-size: 16px;'>Payment will be transferred to your registered account</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style='text-align: center; margin-top: 60px; padding-top: 40px; border-top: 3px solid #ecf0f1;'>
                        <p style='margin: 0 0 20px 0; color: #7f8c8d; font-size: 16px;'>
                            Thank you for using our referral program! 💜
                        </p>
                        <div style='margin: 30px 0 0 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 18px;'>
                            <p style='margin: 0; color: white; font-size: 16px; font-weight: 600;'>
                                Best regards,<br>
                                <strong style='font-size: 20px;'>The bluefifth Team</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // TEST RUNNER QUALITY: Payment Processed Template
    private function getTestRunnerPaymentProcessedTemplate($userName, $amount, $breakdown) {
        $breakdownHtml = '';
        foreach ($breakdown as $month) {
            $monthName = ($month['purchase_month'] == 1) ? "Month 1 (First Month Bonus)" : "Month {$month['purchase_month']}";
            $breakdownHtml .= "
                <tr style='background: rgba(255,255,255,0.8);'>
                    <td style='padding: 15px; border: none; border-radius: 8px 0 0 8px; font-weight: 600; color: #2c3e50;'>{$monthName}</td>
                    <td style='padding: 15px; border: none; text-align: center; font-weight: 600; color: #e74c3c;'>{$month['earning_rate']}%</td>
                    <td style='padding: 15px; border: none; text-align: center; color: #34495e;'>{$month['purchase_count']}</td>
                    <td style='padding: 15px; border: none; text-align: center; color: #3498db; font-weight: 600;'>₹{$month['sales']}</td>
                    <td style='padding: 15px; border: none; border-radius: 0 8px 8px 0; font-weight: 700; color: #27ae60; font-size: 16px;'>₹{$month['points']}</td>
                </tr>
                <tr style='height: 8px;'><td colspan='5'></td></tr>
            ";
        }
        
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Payment Processed</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;'>
            
            <!-- Main Container -->
            <div style='max-width: 650px; margin: 0 auto; background: transparent; padding: 40px 20px;'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 25%, #17a2b8 50%, #6f42c1 75%, #e83e8c 100%); background-size: 400% 400%; border-radius: 25px 25px 0 0; padding: 50px 40px; text-align: center; position: relative; overflow: hidden;'>
                    <div style='position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 30% 70%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 70% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);'></div>
                    
                    <div style='position: relative; z-index: 2;'>
                        <h1 style='margin: 0; font-size: 48px; font-weight: 800; color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.3);'>
                            💰 Payment Processed!
                        </h1>
                        <p style='margin: 20px 0 0 0; font-size: 24px; color: rgba(255,255,255,0.95); font-weight: 600;'>
                            Your money has been transferred
                        </p>
                    </div>
                </div>
                
                <!-- Main content -->
                <div style='background: white; padding: 50px 40px; border-radius: 0 0 25px 25px; box-shadow: 0 25px 50px rgba(0,0,0,0.15);'>
                    
                    <div style='text-align: center; margin-bottom: 40px;'>
                        <h2 style='margin: 0 0 15px 0; font-size: 32px; color: #2c3e50; font-weight: 700;'>
                            Hello {$userName}! 👋
                        </h2>
                        <p style='margin: 0; font-size: 20px; color: #27ae60; font-weight: 700; line-height: 1.6;'>
                            Excellent news! Your referral payment has been processed and transferred to your account.
                        </p>
                    </div>
                    
                    <!-- Payment Amount Highlight -->
                    <div style='background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 25px; padding: 40px; margin: 40px 0; text-align: center; box-shadow: 0 20px 40px rgba(40, 167, 69, 0.3); border: 3px solid #28a745; position: relative; overflow: hidden;'>
                        <div style='position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: rgba(40, 167, 69, 0.1); border-radius: 50%;'></div>
                        <div style='position: absolute; bottom: -30px; left: -30px; width: 60px; height: 60px; background: rgba(40, 167, 69, 0.1); border-radius: 50%;'></div>
                        
                        <div style='position: relative; z-index: 2;'>
                            <h3 style='margin: 0 0 15px 0; color: #155724; font-size: 22px; font-weight: 600;'>
                                Payment Amount
                            </h3>
                            <div style='font-size: 56px; font-weight: 800; color: #155724; margin: 20px 0; text-shadow: 0 2px 10px rgba(21, 87, 36, 0.2);'>
                                ₹{$amount}
                            </div>
                            <p style='margin: 0; color: #155724; font-size: 18px; font-weight: 700;'>
                                TRANSFERRED TO YOUR ACCOUNT
                            </p>
                            <p style='margin: 15px 0 0 0; color: #6c757d; font-size: 14px;'>
                                Payment Date: " . date('Y-m-d H:i:s') . "
                            </p>
                        </div>
                    </div>
                    
                    <!-- Complete Earning Breakdown -->
                    <div style='background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); border-radius: 25px; padding: 40px; margin: 40px 0; color: white; box-shadow: 0 20px 40px rgba(52, 152, 219, 0.3);'>
                        <h3 style='margin: 0 0 30px 0; font-size: 26px; font-weight: 700; text-align: center;'>
                            📊 Complete Earning Breakdown
                        </h3>
                        
                        <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.2);'>
                            <table style='width: 100%; border-collapse: separate; border-spacing: 0;'>
                                <thead>
                                    <tr style='background: rgba(255,255,255,0.2); border-radius: 12px;'>
                                        <th style='padding: 18px 12px; color: white; font-weight: 700; font-size: 14px; text-align: left; border-radius: 12px 0 0 12px;'>Month</th>
                                        <th style='padding: 18px 12px; color: white; font-weight: 700; font-size: 14px; text-align: center;'>Rate</th>
                                        <th style='padding: 18px 12px; color: white; font-weight: 700; font-size: 14px; text-align: center;'>Purchases</th>
                                        <th style='padding: 18px 12px; color: white; font-weight: 700; font-size: 14px; text-align: center;'>Sales Generated</th>
                                        <th style='padding: 18px 12px; color: white; font-weight: 700; font-size: 14px; text-align: center; border-radius: 0 12px 12px 0;'>Your Earning</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style='height: 12px;'><td colspan='5'></td></tr>
                                    {$breakdownHtml}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Keep Earning More -->
                    <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-radius: 20px; padding: 35px; margin: 35px 0; border-left: 5px solid #ffc107;'>
                        <h3 style='margin: 0 0 25px 0; color: #856404; font-size: 24px; font-weight: 700;'>
                            🚀 Keep Earning More!
                        </h3>
                        <div style='background: rgba(255,255,255,0.7); border-radius: 15px; padding: 25px;'>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #856404;'>First month referrals:</strong> <span style='color: #27ae60; font-weight: 600;'>10% commission</span>
                            </div>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #856404;'>Subsequent months:</strong> <span style='color: #3498db; font-weight: 600;'>5% commission</span>
                            </div>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #856404;'>Claims available:</strong> <span style='color: #6c757d;'>30th and 31st of each month</span>
                            </div>
                            <div>
                                <strong style='color: #856404;'>No limits:</strong> <span style='color: #e74c3c; font-weight: 600;'>Refer as many friends as you want!</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Thank You Message -->
                    <div style='text-align: center; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 30px; border-radius: 20px; margin: 40px 0; border: 2px solid #dee2e6;'>
                        <h3 style='margin: 0 0 15px 0; color: #495057; font-size: 22px; font-weight: 700;'>
                            Thank you for being part of our referral program! 🎉
                        </h3>
                        <p style='margin: 0; color: #6c757d; font-size: 16px;'>
                            Share your referral link and keep earning!
                        </p>
                    </div>
                    
                    <!-- Footer -->
                    <div style='text-align: center; margin-top: 60px; padding-top: 40px; border-top: 3px solid #ecf0f1;'>
                        <div style='margin: 30px 0 0 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 18px;'>
                            <p style='margin: 0; color: white; font-size: 16px; font-weight: 600;'>
                                Best regards,<br>
                                <strong style='font-size: 20px;'>The bluefifth Team</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // TEST RUNNER QUALITY: Insufficient Balance Template
    private function getTestRunnerInsufficientBalanceTemplate($userName, $currentPoints, $minRequired, $shortfall) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Insufficient Balance</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;'>
            
            <div style='max-width: 650px; margin: 0 auto; background: transparent; padding: 40px 20px;'>
                
                <div style='background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%); border-radius: 25px 25px 0 0; padding: 50px 40px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 48px; font-weight: 800; color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.3);'>
                        💰 Earn More to Claim!
                    </h1>
                    <p style='margin: 20px 0 0 0; font-size: 24px; color: rgba(255,255,255,0.95); font-weight: 600;'>
                        You're close to claiming your points!
                    </p>
                </div>
                
                <div style='background: white; padding: 50px 40px; border-radius: 0 0 25px 25px; box-shadow: 0 25px 50px rgba(0,0,0,0.15);'>
                    
                    <div style='text-align: center; margin-bottom: 40px;'>
                        <h2 style='margin: 0 0 15px 0; font-size: 32px; color: #2c3e50; font-weight: 700;'>
                            Hello {$userName}! 👋
                        </h2>
                        <p style='margin: 0; font-size: 18px; color: #7f8c8d; line-height: 1.6;'>
                            You need just ₹{$shortfall} more to claim your points!
                        </p>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%); border-radius: 20px; padding: 35px; margin: 35px 0; color: white; text-align: center;'>
                        <h3 style='margin: 0 0 20px 0; font-size: 24px; font-weight: 700;'>💳 Your Balance Status</h3>
                        <div style='background: rgba(255,255,255,0.2); border-radius: 15px; padding: 25px; margin: 20px 0;'>
                            <p style='margin: 0 0 10px 0; opacity: 0.9;'>Current Balance</p>
                            <div style='font-size: 36px; font-weight: 800; margin: 10px 0;'>₹{$currentPoints}</div>
                            <p style='margin: 0 0 15px 0; opacity: 0.9;'>Required: ₹{$minRequired}</p>
                            <p style='margin: 0; font-size: 18px; font-weight: 600; background: rgba(255,255,255,0.3); padding: 10px; border-radius: 8px;'>
                                Need ₹{$shortfall} more
                            </p>
                        </div>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 35px; margin: 35px 0; color: white; text-align: center;'>
                        <h3 style='margin: 0 0 20px 0; font-size: 24px; font-weight: 700;'>🚀 How to Earn More</h3>
                        <div style='background: rgba(255,255,255,0.15); border-radius: 15px; padding: 25px;'>
                            <p style='margin: 0 0 15px 0; font-size: 18px;'><strong>✅ Refer More Friends</strong></p>
                            <p style='margin: 0 0 15px 0; opacity: 0.9;'>First month: 10% • Other months: 5%</p>
                            <p style='margin: 0; font-size: 16px; opacity: 0.8;'>Share your referral link and start earning!</p>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin-top: 60px; padding-top: 40px; border-top: 3px solid #ecf0f1;'>
                        <div style='margin: 30px 0 0 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 18px;'>
                            <p style='margin: 0; color: white; font-size: 16px; font-weight: 600;'>
                                Best regards,<br>
                                <strong style='font-size: 20px;'>The bluefifth Team</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }

    // TEST RUNNER QUALITY: Claim Rejected Template
    private function getTestRunnerClaimRejectedTemplate($userName, $claimId, $amount, $reason) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Claim Rejected</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;'>
            
            <div style='max-width: 650px; margin: 0 auto; background: transparent; padding: 40px 20px;'>
                
                <div style='background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); border-radius: 25px 25px 0 0; padding: 50px 40px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 48px; font-weight: 800; color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.3);'>
                        ❌ Claim Rejected
                    </h1>
                    <p style='margin: 20px 0 0 0; font-size: 24px; color: rgba(255,255,255,0.95); font-weight: 600;'>
                        Points have been returned to your wallet
                    </p>
                </div>
                
                <div style='background: white; padding: 50px 40px; border-radius: 0 0 25px 25px; box-shadow: 0 25px 50px rgba(0,0,0,0.15);'>
                    
                    <div style='text-align: center; margin-bottom: 40px;'>
                        <h2 style='margin: 0 0 15px 0; font-size: 32px; color: #2c3e50; font-weight: 700;'>
                            Hello {$userName}! 👋
                        </h2>
                        <p style='margin: 0; font-size: 18px; color: #e74c3c; font-weight: 600; line-height: 1.6;'>
                            Unfortunately, your claim could not be processed at this time.
                        </p>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-radius: 20px; padding: 35px; margin: 35px 0; border-left: 5px solid #dc3545;'>
                        <h3 style='margin: 0 0 20px 0; color: #721c24; font-size: 24px; font-weight: 700;'>
                            📋 Rejection Details
                        </h3>
                        <div style='background: rgba(255,255,255,0.7); border-radius: 15px; padding: 25px;'>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #721c24;'>Claim ID:</strong> <span style='color: #6c757d; font-family: monospace;'>{$claimId}</span>
                            </div>
                            <div style='margin-bottom: 15px;'>
                                <strong style='color: #721c24;'>Amount:</strong> <span style='color: #6c757d; font-weight: 600;'>₹{$amount}</span>
                            </div>
                            <div>
                                <strong style='color: #721c24;'>Reason:</strong> <span style='color: #6c757d;'>{$reason}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border-radius: 20px; padding: 35px; margin: 35px 0; border-left: 5px solid #17a2b8;'>
                        <h3 style='margin: 0 0 20px 0; color: #0c5460; font-size: 24px; font-weight: 700;'>
                            ✅ Good News
                        </h3>
                        <div style='background: rgba(255,255,255,0.7); border-radius: 15px; padding: 25px;'>
                            <p style='margin: 0 0 15px 0; color: #0c5460; font-size: 18px; font-weight: 600;'>
                                Your ₹{$amount} has been returned to your wallet!
                            </p>
                            <ul style='margin: 0; padding-left: 20px; color: #0c5460;'>
                                <li>Points are immediately available for future claims</li>
                                <li>No points were lost in this process</li>
                                <li>You can try claiming again during the next claim window</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 35px; margin: 35px 0; color: white; text-align: center;'>
                        <h3 style='margin: 0 0 20px 0; font-size: 24px; font-weight: 700;'>
                            💡 Next Steps
                        </h3>
                        <div style='background: rgba(255,255,255,0.15); border-radius: 15px; padding: 25px;'>
                            <p style='margin: 0 0 15px 0; font-size: 16px; opacity: 0.9;'>
                                • <strong>Review the rejection reason</strong> and address any issues<br>
                                • <strong>Wait for the next claim window</strong> (30th & 31st of the month)<br>
                                • <strong>Contact support</strong> if you have questions about the rejection<br>
                                • <strong>Keep earning</strong> more points through referrals
                            </p>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin-top: 60px; padding-top: 40px; border-top: 3px solid #ecf0f1;'>
                        <p style='margin: 0 0 20px 0; color: #7f8c8d; font-size: 16px;'>
                            We apologize for any inconvenience. Feel free to contact us with questions.
                        </p>
                        <div style='margin: 30px 0 0 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 18px;'>
                            <p style='margin: 0; color: white; font-size: 16px; font-weight: 600;'>
                                Best regards,<br>
                                <strong style='font-size: 20px;'>The bluefifth Team</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // Text versions for compatibility
    private function getPointsEarnedTextVersion($userName, $points, $breakdown) {
        $text = "Hello {$userName},\n\nCongratulations! You've earned ₹{$points} in referral points!\n\nEARNING BREAKDOWN:\n";
        foreach ($breakdown as $month) {
            $monthName = ($month['purchase_month'] == 1) ? "Month 1" : "Month {$month['purchase_month']}";
            $text .= "• {$monthName} ({$month['earning_rate']}% rate): {$month['purchase_count']} purchases = ₹{$month['month_points']}\n";
        }
        $text .= "\nYour points can be claimed on month-end (30th or 31st).\n\nThank you for being part of our referral program!\n\nBest regards,\nThe bluefifth Team";
        return $text;
    }
    
    private function getClaimReminderTextVersion($userName, $currentDay, $availablePoints) {
        return "Hello {$userName},\n\nYou tried to claim your referral points today, but claims are only allowed on month-end.\n\nCURRENT STATUS:\n• Today: Day {$currentDay}\n• Available Balance: ₹{$availablePoints}\n• Next Claim Date: 30th or 31st of this month\n\nYour points are safe and will be available for claiming on the next allowed date.\n\nBest regards,\nThe bluefifth Team";
    }
    
    private function getClaimSubmittedTextVersion($userName, $claimId, $amount, $breakdown) {
        return "Hello {$userName},\n\nYour referral points claim has been submitted successfully!\n\nCLAIM DETAILS:\n• Claim ID: {$claimId}\n• Amount: ₹{$amount}\n• Status: PENDING ADMIN APPROVAL\n\nOur admin team will review your claim within 24 hours.\n\nBest regards,\nThe bluefifth Team";
    }
    
    private function getPaymentProcessedTextVersion($userName, $amount, $breakdown) {
        return "Hello {$userName},\n\nExcellent news! Your referral payment of ₹{$amount} has been processed and transferred to your account.\n\nPayment Date: " . date('Y-m-d H:i:s') . "\n\nKeep referring friends to earn more!\n\nBest regards,\nThe bluefifth Team";
    }
    
    private function getInsufficientBalanceTextVersion($userName, $currentPoints, $minRequired, $shortfall) {
        return "Hello {$userName},\n\nYou need ₹{$shortfall} more to claim your points.\n\nCURRENT STATUS:\n• Your Balance: ₹{$currentPoints}\n• Required: ₹{$minRequired}\n• Need: ₹{$shortfall} more\n\nKeep referring friends to earn more points!\n\nBest regards,\nThe bluefifth Team";
    }
    
    private function getClaimRejectedTextVersion($userName, $claimId, $amount, $reason) {
        return "Hello {$userName},\n\nYour claim #{$claimId} for ₹{$amount} was rejected.\n\nReason: {$reason}\n\nGood news: Your ₹{$amount} has been returned to your wallet and is available for future claims.\n\nBest regards,\nThe bluefifth Team";
    }
    
    // MONTHLY REMINDER TEMPLATES
    private function getMonthlyClaimAvailableTemplate($userName, $currentDay, $availablePoints) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Time to Claim Your Points!</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;'>
            
            <div style='max-width: 650px; margin: 0 auto; background: transparent; padding: 40px 20px;'>
                
                <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 25px 25px 0 0; padding: 50px 40px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 48px; font-weight: 800; color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.3);'>
                        🎉 Claim Day is Here!
                    </h1>
                    <p style='margin: 20px 0 0 0; font-size: 24px; color: rgba(255,255,255,0.95); font-weight: 600;'>
                        Your points are ready to be claimed!
                    </p>
                </div>
                
                <div style='background: white; padding: 50px 40px; border-radius: 0 0 25px 25px; box-shadow: 0 25px 50px rgba(0,0,0,0.15);'>
                    
                    <div style='text-align: center; margin-bottom: 40px;'>
                        <h2 style='margin: 0 0 15px 0; font-size: 32px; color: #2c3e50; font-weight: 700;'>
                            Hello {$userName}! 👋
                        </h2>
                        <p style='margin: 0; font-size: 18px; color: #28a745; font-weight: 600; line-height: 1.6;'>
                            Great news! Today (Day {$currentDay}) is claim date and you have points to claim!
                        </p>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 20px; padding: 35px; margin: 35px 0; color: white; text-align: center;'>
                        <h3 style='margin: 0 0 20px 0; font-size: 24px; font-weight: 700;'>💰 Available to Claim</h3>
                        <div style='background: rgba(255,255,255,0.2); border-radius: 15px; padding: 25px; margin: 20px 0;'>
                            <div style='font-size: 48px; font-weight: 800; margin: 10px 0;'>₹{$availablePoints}</div>
                            <p style='margin: 0; font-size: 18px; font-weight: 600;'>Ready to be claimed today!</p>
                        </div>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 35px; margin: 35px 0; color: white; text-align: center;'>
                        <h3 style='margin: 0 0 20px 0; font-size: 24px; font-weight: 700;'>🚀 How to Claim</h3>
                        <div style='background: rgba(255,255,255,0.15); border-radius: 15px; padding: 25px;'>
                            <p style='margin: 0 0 15px 0; font-size: 18px;'><strong>1. Login to your account</strong></p>
                            <p style='margin: 0 0 15px 0; opacity: 0.9;'>2. Go to your wallet section</p>
                            <p style='margin: 0 0 15px 0; opacity: 0.9;'>3. Click \"Claim Points\" button</p>
                            <p style='margin: 0; font-size: 16px; opacity: 0.8;'>Claims are processed within 24 hours!</p>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin: 50px 0;'>
                        <div style='background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); display: inline-block; border-radius: 50px; padding: 4px; box-shadow: 0 15px 30px rgba(231, 76, 60, 0.4);'>
                            <a href='#' style='display: block; background: white; color: #e74c3c; text-decoration: none; padding: 18px 45px; border-radius: 46px; font-size: 18px; font-weight: 700;'>
                                🎁 Claim Your ₹{$availablePoints}
                            </a>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin-top: 60px; padding-top: 40px; border-top: 3px solid #ecf0f1;'>
                        <div style='margin: 30px 0 0 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 18px;'>
                            <p style='margin: 0; color: white; font-size: 16px; font-weight: 600;'>
                                Best regards,<br>
                                <strong style='font-size: 20px;'>The bluefifth Team</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getMonthlyStartEarningTemplate($userName, $currentDay, $currentPoints) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Start Earning Today!</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;'>
            
            <div style='max-width: 650px; margin: 0 auto; background: transparent; padding: 40px 20px;'>
                
                <div style='background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%); border-radius: 25px 25px 0 0; padding: 50px 40px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 48px; font-weight: 800; color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.3);'>
                        💰 Start Earning Today!
                    </h1>
                    <p style='margin: 20px 0 0 0; font-size: 24px; color: rgba(255,255,255,0.95); font-weight: 600;'>
                        It's claim date - time to build your earnings!
                    </p>
                </div>
                
                <div style='background: white; padding: 50px 40px; border-radius: 0 0 25px 25px; box-shadow: 0 25px 50px rgba(0,0,0,0.15);'>
                    
                    <div style='text-align: center; margin-bottom: 40px;'>
                        <h2 style='margin: 0 0 15px 0; font-size: 32px; color: #2c3e50; font-weight: 700;'>
                            Hello {$userName}! 👋
                        </h2>
                        <p style='margin: 0; font-size: 18px; color: #7f8c8d; line-height: 1.6;'>
                            Today (Day {$currentDay}) is claim date, but you need more points to claim. Let's start earning!
                        </p>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%); border-radius: 20px; padding: 35px; margin: 35px 0; color: white; text-align: center;'>
                        <h3 style='margin: 0 0 20px 0; font-size: 24px; font-weight: 700;'>💳 Your Current Status</h3>
                        <div style='background: rgba(255,255,255,0.2); border-radius: 15px; padding: 25px; margin: 20px 0;'>
                            <p style='margin: 0 0 10px 0; opacity: 0.9;'>Current Balance</p>
                            <div style='font-size: 36px; font-weight: 800; margin: 10px 0;'>₹{$currentPoints}</div>
                            <p style='margin: 0 0 15px 0; opacity: 0.9;'>Required: ₹100</p>
                            <p style='margin: 0; font-size: 18px; font-weight: 600; background: rgba(255,255,255,0.3); padding: 10px; border-radius: 8px;'>
                                Need ₹" . (100 - $currentPoints) . " more
                            </p>
                        </div>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 35px; margin: 35px 0; color: white; text-align: center;'>
                        <h3 style='margin: 0 0 20px 0; font-size: 24px; font-weight: 700;'>🚀 How to Earn Points</h3>
                        <div style='background: rgba(255,255,255,0.15); border-radius: 15px; padding: 25px;'>
                            <p style='margin: 0 0 15px 0; font-size: 18px;'><strong>✅ Refer Friends & Family</strong></p>
                            <p style='margin: 0 0 15px 0; opacity: 0.9;'>First month: 10% commission</p>
                            <p style='margin: 0 0 15px 0; opacity: 0.9;'>Other months: 5% commission</p>
                            <p style='margin: 0; font-size: 16px; opacity: 0.8;'>Share your referral link and start earning!</p>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin: 50px 0;'>
                        <div style='background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); display: inline-block; border-radius: 50px; padding: 4px; box-shadow: 0 15px 30px rgba(231, 76, 60, 0.4);'>
                            <a href='#' style='display: block; background: white; color: #e74c3c; text-decoration: none; padding: 18px 45px; border-radius: 46px; font-size: 18px; font-weight: 700;'>
                                🔗 Get Your Referral Link
                            </a>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin-top: 60px; padding-top: 40px; border-top: 3px solid #ecf0f1;'>
                        <div style='margin: 30px 0 0 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 18px;'>
                            <p style='margin: 0; color: white; font-size: 16px; font-weight: 600;'>
                                Best regards,<br>
                                <strong style='font-size: 20px;'>The bluefifth Team</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getMonthlyClaimAvailableTextVersion($userName, $currentDay, $availablePoints) {
        return "Hello {$userName},\n\nGreat news! Today (Day {$currentDay}) is claim date and you have ₹{$availablePoints} available to claim!\n\nLOGIN TO CLAIM:\n• Go to your wallet section\n• Click \"Claim Points\" button\n• Your payment will be processed within 24 hours\n\nDon't miss out - claim your ₹{$availablePoints} today!\n\nBest regards,\nThe bluefifth Team";
    }
    
    private function getMonthlyStartEarningTextVersion($userName, $currentDay, $currentPoints) {
        $needed = 100 - $currentPoints;
        return "Hello {$userName},\n\nToday (Day {$currentDay}) is claim date, but you need more points to claim.\n\nCURRENT STATUS:\n• Your Balance: ₹{$currentPoints}\n• Required: ₹100\n• Need: ₹{$needed} more\n\nSTART EARNING TODAY:\n• Refer friends and family\n• First month: 10% commission\n• Other months: 5% commission\n\nGet your referral link and start earning!\n\nBest regards,\nThe bluefifth Team";
    }

    // Log email to database
    private function logEmailToDatabase($userEmail, $emailType, $subject, $message) {
        try {
            $conn = getConnection();
            
            // Get user ID from email
            $userStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $userStmt->execute([$userEmail]);
            
            if ($userStmt->rowCount() > 0) {
                $user = $userStmt->fetch();
                $stmt = $conn->prepare("
                    INSERT INTO email_notifications 
                    (user_id, email_type, subject, message, sent_at, status) 
                    VALUES (?, ?, ?, ?, NOW(), 'sent')
                ");
                $stmt->execute([$user['id'], $emailType, $subject, $message]);
                return true;
            }
        } catch (Exception $e) {
            error_log("Email logging error: " . $e->getMessage());
        }
        return false;
    }
}
?>