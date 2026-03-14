<?php
echo "CRON JOB STARTED AT: " . date('Y-m-d H:i:s') . "<br>";
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Set execution time limit
set_time_limit(300); // 5 minutes max

// Include required files
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Log start
error_log("=== CRON JOB: Release Held Points - Starting at " . date('Y-m-d H:i:s') . " ===");

try {
    $conn = getConnection();
    
    if (!$conn) {
        throw new Exception('Failed to get database connection');
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Find all held points that are ready to be released
    $stmt = $conn->prepare("
        SELECT 
            rp.id,
            rp.referral_id,
            rp.points_earned,
            rp.order_id,
            rp.hold_until,
            r.user_id as referrer_id,
            u.name as referrer_name,
            u.email as referrer_email
        FROM referral_purchases rp
        JOIN referrals r ON rp.referral_id = r.id
        JOIN users u ON r.user_id = u.id
        WHERE rp.hold_status = 'hold' 
        AND rp.hold_until <= NOW()
        AND rp.status = 'credited'
        ORDER BY rp.hold_until ASC
    ");
    
    $stmt->execute();
    $readyToRelease = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debugging: log how many and which records are ready
    error_log("DEBUG: Found " . count($readyToRelease) . " records matching criteria");
    foreach ($readyToRelease as $record) {
        error_log("DEBUG: Record ID {$record['id']}, Points {$record['points_earned']}, Referrer {$record['referrer_id']}");
    }
    
    if (empty($readyToRelease)) {
        error_log("No held points ready for release.");
        $conn->commit();
        exit("No points to release.\n");
    }
    
    error_log("Found " . count($readyToRelease) . " purchases with points ready to release");
    
    $totalPointsReleased = 0;
    $totalUsersAffected = 0;
    $processedUsers = [];
    
    foreach ($readyToRelease as $purchase) {
        $purchaseId = $purchase['id'];
        $referrerId = $purchase['referrer_id'];
        $points = $purchase['points_earned'];
        $orderNumber = $purchase['order_id'];
        
        try {
            // SAFETY CHECK: Re-verify this record hasn't been processed already
            $recheckStmt = $conn->prepare("SELECT hold_status FROM referral_purchases WHERE id = ?");
            $recheckStmt->execute([$purchaseId]);
            $currentStatus = $recheckStmt->fetchColumn();
            
            if ($currentStatus !== 'hold') {
                error_log("SKIPPED: Purchase ID {$purchaseId} already processed (status: {$currentStatus})");
                continue;
            }
            
            // Check if the order has any APPROVED returns
            $returnCheck = $conn->prepare("
                SELECT id, return_status, return_reason 
                FROM order_returns 
                WHERE order_number = ? 
                AND return_status IN ('processed', 'received')
                LIMIT 1
            ");
            $returnCheck->execute([$orderNumber]);
            
            if ($returnCheck->rowCount() > 0) {
                // Order has approved return - cancel the held points
                $cancelStmt = $conn->prepare("
                    UPDATE referral_purchases 
                    SET hold_status = 'canceled'
                    WHERE id = ?
                ");
                $cancelStmt->execute([$purchaseId]);
                
                error_log("CANCELED: Purchase ID {$purchaseId} - Order {$orderNumber} has approved return");
                continue;
            }
            
            // No approved return - release the points
            
            // Ensure referrer has a wallet
            $walletId = ensureUserWallet($referrerId);
            if (!$walletId) {
                error_log("ERROR: Could not create wallet for user {$referrerId}");
                continue;
            }
            
            // CRITICAL: Update hold_status FIRST to prevent duplicate processing
            $updatePurchaseStmt = $conn->prepare("
                UPDATE referral_purchases 
                SET hold_status = 'released'
                WHERE id = ? AND hold_status = 'hold'
            ");
            $updateResult = $updatePurchaseStmt->execute([$purchaseId]);
            
            // Check if the update actually affected a row (prevents race conditions)
            if ($updatePurchaseStmt->rowCount() === 0) {
                error_log("SKIPPED: Purchase ID {$purchaseId} - Another process already updated it");
                continue;
            }
            
            // Add points to wallet
            $walletStmt = $conn->prepare("
                UPDATE wallet 
                SET pending_points = pending_points + ?,
                    total_earned = total_earned + ?
                WHERE user_id = ?
            ");
            
            $walletResult = $walletStmt->execute([$points, $points, $referrerId]);
            
            if (!$walletResult) {
                error_log("ERROR: Failed to add points to wallet for user {$referrerId}");
                // Rollback the status change if wallet update fails
                $rollbackStmt = $conn->prepare("
                    UPDATE referral_purchases 
                    SET hold_status = 'hold'
                    WHERE id = ?
                ");
                $rollbackStmt->execute([$purchaseId]);
                continue;
            }
            
            // Record wallet transaction
            $transactionStmt = $conn->prepare("
                INSERT INTO wallet_transactions 
                (wallet_id, points, transaction_type, reference_id, description, created_at)
                VALUES (?, ?, 'earned', ?, ?, NOW())
            ");
            
            $transactionStmt->execute([
                $walletId,
                $points,
                $purchaseId,
                "Referral points released from order {$orderNumber} (7-day hold completed)"
            ]);
            
            // Update referral totals
            $updateReferralStmt = $conn->prepare("
                UPDATE referrals 
                SET total_earnings = total_earnings + ?
                WHERE id = ?
            ");
            $updateReferralStmt->execute([$points, $purchase['referral_id']]);
            
            $totalPointsReleased += $points;
            
            if (!in_array($referrerId, $processedUsers)) {
                $processedUsers[] = $referrerId;
                $totalUsersAffected++;
            }
            
            error_log("RELEASED: {$points} points to user {$referrerId} from order {$orderNumber}");
            
        } catch (Exception $e) {
            error_log("ERROR processing purchase {$purchaseId}: " . $e->getMessage());
            continue;
        }
    }
    
    // Commit all changes
    $conn->commit();
    
    // Log summary
    error_log("=== CRON JOB COMPLETED ===");
    error_log("Total points released: {$totalPointsReleased}");
    error_log("Total users affected: {$totalUsersAffected}");
    error_log("Processed purchases: " . count($readyToRelease));
    
    echo "SUCCESS: Released {$totalPointsReleased} points to {$totalUsersAffected} users\n";
    
    // Optional: Send admin summary email
    if ($totalPointsReleased > 0) {
        $adminEmail = getSetting('admin_email', 'admin@bluefifth.in');
        if ($adminEmail) {
            $subject = "Daily Referral Points Release Summary";
            $message = "
                Referral Points Release Summary - " . date('Y-m-d') . "
                
                Total Points Released: {$totalPointsReleased}
                Total Users Affected: {$totalUsersAffected}
                Purchases Processed: " . count($readyToRelease) . "
                
                This is an automated message from the referral system.
            ";
            
            // Send email using your existing mailer
            try {
                mail($adminEmail, $subject, $message);
            } catch (Exception $e) {
                error_log("Failed to send admin summary email: " . $e->getMessage());
            }
        }
    }
    
} catch (Exception $e) {
    // Rollback on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("CRON JOB ERROR: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

error_log("=== CRON JOB: Release Held Points - Completed at " . date('Y-m-d H:i:s') . " ===");
?>