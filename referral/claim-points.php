<?php
// referral/claim-points.php - WITH TDS INTEGRATION
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure clean output
ob_start();

// CRITICAL: Always output JSON
header('Content-Type: application/json');

// TDS Helper Functions
function fyBounds() {
    $currentDate = new DateTime();
    $currentYear = (int)$currentDate->format('Y');
    $currentMonth = (int)$currentDate->format('n');
    
    if ($currentMonth >= 4) {
        // April to December - FY is current year to next year
        $fyStart = $currentYear . '-04-01';
        $fyEnd = ($currentYear + 1) . '-03-31';
    } else {
        // January to March - FY is previous year to current year
        $fyStart = ($currentYear - 1) . '-04-01';
        $fyEnd = $currentYear . '-03-31';
    }
    
    return [$fyStart, $fyEnd];
}

function getGrossFYEarnings($conn, $userId) {
    list($fyStart, $fyEnd) = fyBounds();
    
    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(rp.points_earned), 0) as total_earnings
            FROM referral_purchases rp
            JOIN referrals r ON rp.referral_id = r.id
            WHERE r.user_id = ? 
            AND rp.status = 'paid'
            AND rp.created_at >= ? 
            AND rp.created_at <= ?
        ");
        $stmt->execute([$userId, $fyStart, $fyEnd . ' 23:59:59']);
        $result = $stmt->fetch();
        
        return round(floatval($result['total_earnings'] ?? 0), 2);
    } catch (Exception $e) {
        error_log("[TDS] Error getting FY earnings for user {$userId}: " . $e->getMessage());
        return 0.0;
    }
}

function getFYTdsAlreadyDeducted($conn, $userId) {
    list($fyStart, $fyEnd) = fyBounds();
    
    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(tax_deducted), 0) as total_tax_deducted
            FROM wallet_transactions wt
            JOIN wallet w ON wt.wallet_id = w.id
            WHERE w.user_id = ? 
            AND wt.created_at >= ? 
            AND wt.created_at <= ?
        ");
        $stmt->execute([$userId, $fyStart, $fyEnd . ' 23:59:59']);
        $result = $stmt->fetch();
        
        return round(floatval($result['total_tax_deducted'] ?? 0), 2);
    } catch (Exception $e) {
        error_log("[TDS] Error getting FY TDS for user {$userId}: " . $e->getMessage());
        return 0.0;
    }
}

function computeTdsForClaim($conn, $userId, $claimAmount) {
    $grossFYEarnings = getGrossFYEarnings($conn, $userId);
    $alreadyDeducted = getFYTdsAlreadyDeducted($conn, $userId);
    
    // TDS applies once FY earnings reach ₹15,000
    if ($grossFYEarnings < 15000) {
        return [
            'tax' => 0.0,
            'net' => round($claimAmount, 2),
            'total_due' => 0.0,
            'already' => $alreadyDeducted,
            'fy_earnings' => $grossFYEarnings
        ];
    }
    
    $totalTaxDue = round($grossFYEarnings * 0.05, 2);
    $additionalTaxNeeded = max(0, $totalTaxDue - $alreadyDeducted);
    $taxThisClaim = min($claimAmount, $additionalTaxNeeded);
    $netCredit = $claimAmount - $taxThisClaim;
    
    error_log("[TDS] User {$userId}: FY Earnings=₹{$grossFYEarnings}, Total Due=₹{$totalTaxDue}, Already=₹{$alreadyDeducted}, Additional=₹{$additionalTaxNeeded}, Claim=₹{$claimAmount}, Tax=₹{$taxThisClaim}, Net=₹{$netCredit}");
    
    return [
        'tax' => round($taxThisClaim, 2),
        'net' => round($netCredit, 2),
        'total_due' => $totalTaxDue,
        'already' => $alreadyDeducted,
        'fy_earnings' => $grossFYEarnings
    ];
}

try {
    // Include your actual files
    require_once '../includes/database.php';
    require_once '../auth/session.php';
    
    // Check if user is logged in
    if (!isLoggedIn()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }

    $userId = getCurrentUserId();
    if (!$userId) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid user session']);
        exit;
    }

    // Enhanced month-end check
    function isMonthEndCheck() {
        $currentDay = date('j');
        $daysInMonth = date('t');
        return ($currentDay == 30 || $currentDay == 31) && $currentDay <= $daysInMonth;
    }

    // Get user's wallet details (wallet.points = claimable valid points)
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT 
            u.name,
            u.email,
            w.id as wallet_id,
            COALESCE(w.points, 0) as points,
            COALESCE(w.pending_points, 0) as pending_points,
            COALESCE(w.total_earned, 0) as total_earned,
            COALESCE(w.total_tax_paid, 0) as total_tax_paid
        FROM users u
        LEFT JOIN wallet w ON u.id = w.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);

    if ($stmt->rowCount() == 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'User not found in database']);
        exit;
    }

    $userData = $stmt->fetch();
    $userName = $userData['name'] ?? 'User';
    $userEmail = $userData['email'] ?? '';
    $walletId = $userData['wallet_id'];
    $availablePoints = floatval($userData['points']); // These are the claimable wallet points
    $pendingPoints = floatval($userData['pending_points']);
    $totalEarned = floatval($userData['total_earned']);
    $totalTaxPaid = floatval($userData['total_tax_paid'] ?? 0);

    // Create wallet if doesn't exist
    if (is_null($walletId)) {
        try {
            $stmt = $conn->prepare("INSERT INTO wallet (user_id, points, pending_points, total_earned, total_claimed, total_tax_paid) VALUES (?, 0, 0, 0, 0, 0)");
            $stmt->execute([$userId]);
            $walletId = $conn->lastInsertId();
            $availablePoints = 0;
            $pendingPoints = 0;
            $totalTaxPaid = 0;
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to create wallet: ' . $e->getMessage()]);
            exit;
        }
    }

    // Get FY TDS information for display
    $grossFYEarnings = getGrossFYEarnings($conn, $userId);
    $fyTaxDeducted = getFYTdsAlreadyDeducted($conn, $userId);

    // Business logic - Check wallet.points for claim eligibility
    $testingMode = true; // Set to true only for testing
    $currentDay = date('j');
    $minPointsRequired = 100; // ALWAYS ₹100 minimum, regardless of testing mode
    $isClaimDate = $testingMode ? true : isMonthEndCheck(); // Testing mode bypasses DATE only
    $hasEnoughPoints = $availablePoints >= $minPointsRequired; // Check wallet.points (valid claimable points)

    error_log("CLAIM CHECK: User {$userId}, Day: {$currentDay}, Is Claim Date: " . ($isClaimDate ? 'YES' : 'NO') . ", Wallet Points: {$availablePoints}, Pending Points: {$pendingPoints}, Enough: " . ($hasEnoughPoints ? 'YES' : 'NO') . ", Testing: " . ($testingMode ? 'YES' : 'NO'));

    // Check points FIRST, then date (proper priority)
    
    // SCENARIO 1: Insufficient points (regardless of date)
    if (!$hasEnoughPoints) {
        // Send insufficient balance email
        $emailSent = false;
        try {
            if (file_exists('../includes/sendinblue-mailer.php') && file_exists('../includes/email-config.php')) {
                require_once '../includes/sendinblue-mailer.php';
                $emailConfig = include '../includes/email-config.php';
                
                if ($emailConfig['settings']['enabled'] && 
                    !empty($emailConfig['sendinblue']['api_key']) && 
                    $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE') {
                    
                    $mailer = new SendinblueMailer(
                        $emailConfig['sendinblue']['api_key'],
                        $emailConfig['sendinblue']['from_email'],
                        $emailConfig['sendinblue']['from_name']
                    );
                    
                    $emailSent = $mailer->sendInsufficientBalanceEmail($userEmail, $userName, $availablePoints, $minPointsRequired);
                }
            }
        } catch (Exception $e) {
            error_log("Insufficient balance email failed: " . $e->getMessage());
        }
        
        ob_clean();
        echo json_encode([
            'success' => false,
            'show_popup' => true,
            'popup_type' => 'insufficient_balance',
            'message' => "Minimum ₹{$minPointsRequired} required to claim. You have ₹{$availablePoints}. " . ($isClaimDate ? "Today is claim date, but you need more points!" : "Also, claims are only allowed on 30th & 31st."),
            'available_points' => $availablePoints,
            'pending_points' => $pendingPoints,
            'min_required' => $minPointsRequired,
            'shortfall' => $minPointsRequired - $availablePoints,
            'is_claim_date' => $isClaimDate,
            'current_day' => $currentDay,
            'email_sent' => $emailSent,
            'fy_earnings' => $grossFYEarnings,
            'fy_tax_deducted' => $fyTaxDeducted
        ]);
        exit;
    }

    // SCENARIO 2: Enough points but wrong date
    if (!$isClaimDate) {
        // Send reminder email about claim dates
        $emailSent = false;
        try {
            if (file_exists('../includes/sendinblue-mailer.php') && file_exists('../includes/email-config.php')) {
                require_once '../includes/sendinblue-mailer.php';
                $emailConfig = include '../includes/email-config.php';
                
                if ($emailConfig['settings']['enabled'] && 
                    !empty($emailConfig['sendinblue']['api_key']) && 
                    $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE') {
                    
                    $mailer = new SendinblueMailer(
                        $emailConfig['sendinblue']['api_key'],
                        $emailConfig['sendinblue']['from_email'],
                        $emailConfig['sendinblue']['from_name']
                    );
                    
                    $emailSent = $mailer->sendClaimReminderEmail($userEmail, $userName, $currentDay, $availablePoints);
                }
            }
        } catch (Exception $e) {
            error_log("Reminder email failed: " . $e->getMessage());
        }
        
        ob_clean();
        echo json_encode([
            'success' => false,
            'show_popup' => true,
            'popup_type' => 'date_restriction',
            'message' => "Claims are only allowed on 30th & 31st of every month. Today is day {$currentDay}.",
            'current_day' => $currentDay,
            'available_points' => $availablePoints,
            'pending_points' => $pendingPoints,
            'min_required' => $minPointsRequired,
            'next_claim_dates' => '30th & 31st of this month',
            'email_sent' => $emailSent,
            'fy_earnings' => $grossFYEarnings,
            'fy_tax_deducted' => $fyTaxDeducted
        ]);
        exit;
    }

    // SCENARIO 3: Valid claim (enough wallet points + correct date)
    // CORRECT FLOW: Move points from wallet.points → wallet.pending_points WITH TDS CALCULATION
    $conn->beginTransaction();

    try {
        // TDS CALCULATION - Compute tax before processing claim
        $tdsInfo = computeTdsForClaim($conn, $userId, $availablePoints);
        $taxDeducted = $tdsInfo['tax'];
        $netCredited = $tdsInfo['net'];
        
        error_log("[TDS] Claim processing for user {$userId}: Claim=₹{$availablePoints}, Tax=₹{$taxDeducted}, Net=₹{$netCredited}");
        
        // Create claim record
        $stmt = $conn->prepare("INSERT INTO claims (user_id, points_claimed, money_value, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->execute([$userId, $availablePoints, $availablePoints]);
        $claimId = $conn->lastInsertId();
        
        if (!$claimId) {
            throw new Exception("Failed to create claim record");
        }
        
        error_log("CLAIM CREATED: ID {$claimId}, Amount: {$availablePoints}");
        
        // CORRECT FLOW: Move wallet.points to wallet.pending_points and reset wallet.points to 0
        $stmt = $conn->prepare("
            UPDATE wallet 
            SET points = 0,
                pending_points = pending_points + ?,
                total_claimed = COALESCE(total_claimed, 0) + ?,
                total_tax_paid = COALESCE(total_tax_paid, 0) + ?
            WHERE id = ?
        ");
        $stmt->execute([$availablePoints, $availablePoints, $taxDeducted, $walletId]);
        
        // Add transaction record WITH TDS INFORMATION
        try {
            $stmt = $conn->prepare("
                INSERT INTO wallet_transactions 
                (wallet_id, points, tax_deducted, net_credited, transaction_type, reference_id, description, created_at) 
                VALUES (?, ?, ?, ?, 'claimed', ?, ?, NOW())
            ");
            $stmt->execute([
                $walletId, 
                -$availablePoints, 
                $taxDeducted, 
                $netCredited, 
                $claimId, 
                "Claimed ₹{$availablePoints} on " . date('Y-m-d') . " (TDS: ₹{$taxDeducted}, Net: ₹{$netCredited})"
            ]);
        } catch (Exception $e) {
            error_log("Transaction record failed: " . $e->getMessage());
            // Continue without transaction record if table structure is different
        }
        
        // Get monthly breakdown (optional) - use 'claimed' status
        $monthlyBreakdown = [];
        try {
            $stmt = $conn->prepare("
                SELECT 
                    rp.purchase_month,
                    rp.earning_rate,
                    COUNT(*) as purchase_count,
                    SUM(rp.points_earned) as month_points
                FROM referral_purchases rp
                JOIN referrals r ON rp.referral_id = r.id
                WHERE r.user_id = ? AND rp.status = 'claimed'
                GROUP BY rp.purchase_month, rp.earning_rate
                ORDER BY rp.purchase_month
            ");
            $stmt->execute([$userId]);
            $monthlyBreakdown = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Monthly breakdown error: " . $e->getMessage());
            // Continue without breakdown if there's an error
        }
        
        $conn->commit();
        
        error_log("CLAIM COMMITTED: ID {$claimId}, Amount: {$availablePoints}, Tax: {$taxDeducted}, Net: {$netCredited}, Moved from wallet.points to pending_points");
        
        // Send claim submitted email
        $emailSent = false;
        try {
            if (file_exists('../includes/sendinblue-mailer.php') && file_exists('../includes/email-config.php')) {
                require_once '../includes/sendinblue-mailer.php';
                $emailConfig = include '../includes/email-config.php';
                
                if ($emailConfig['settings']['enabled'] && 
                    !empty($emailConfig['sendinblue']['api_key']) && 
                    $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE') {
                    
                    $mailer = new SendinblueMailer(
                        $emailConfig['sendinblue']['api_key'],
                        $emailConfig['sendinblue']['from_email'],
                        $emailConfig['sendinblue']['from_name']
                    );
                    
                    $emailSent = $mailer->sendClaimSubmittedEmail($userEmail, $userName, $claimId, $availablePoints, $monthlyBreakdown);
                    
                    // Log email in database
                    if ($emailSent) {
                        try {
                            $stmt = $conn->prepare("
                                INSERT INTO email_notifications 
                                (user_id, email_type, subject, message, sent_at, status) 
                                VALUES (?, ?, ?, ?, NOW(), 'sent')
                            ");
                            $stmt->execute([$userId, 'claim_submitted', "Claim Submitted - ₹{$availablePoints} Processing", "Claim submission confirmation"]);
                        } catch (Exception $e) {
                            error_log("Email logging failed: " . $e->getMessage());
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Email sending failed (continuing anyway): " . $e->getMessage());
        }
        
        // TDS SUCCESS MESSAGE
        $tdsMessage = ($taxDeducted > 0) 
            ? "₹{$availablePoints} claimed, ₹{$taxDeducted} deducted as TDS, ₹{$netCredited} credited to wallet."
            : "₹{$availablePoints} successfully claimed and credited to wallet.";
        
        // Success response
        ob_clean();
        echo json_encode([
            'success' => true,
            'show_popup' => true,
            'popup_type' => 'claim_success',
            'message' => "Claim submitted successfully! Admin will process your payment. " . $tdsMessage,
            'claim_id' => $claimId,
            'points_claimed' => $availablePoints,
            'tax_deducted' => $taxDeducted,
            'net_credited' => $netCredited,
            'tds_message' => $tdsMessage,
            'email_sent' => $emailSent,
            'debug' => [
                'wallet_id' => $walletId,
                'original_points' => $availablePoints,
                'now_wallet_points' => 0, // wallet.points is now 0
                'now_pending_points' => $availablePoints, // this amount is now in pending_points
                'tax_deducted' => $taxDeducted,
                'net_credited' => $netCredited,
                'fy_earnings' => $tdsInfo['fy_earnings'],
                'total_tax_due' => $tdsInfo['total_due'],
                'tax_already_paid' => $tdsInfo['already'],
                'testing_mode' => $testingMode,
                'user_name' => $userName,
                'user_email' => $userEmail
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("CLAIM TRANSACTION ERROR: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to process claim: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    error_log("CLAIM FATAL ERROR: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}

// Clean up output buffer
ob_end_flush();
?>