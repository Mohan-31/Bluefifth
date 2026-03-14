<?php
// admin/api/process-claim.php - FIXED VERSION WITH PROPER POINT TRANSFERS
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

// Prevent any output before JSON
ob_start();

try {
    require_once '../admin-session.php';

    // Check admin authentication
    checkAdminAuth();

    // Validate POST data
    $action = $_POST['action'] ?? '';
    $claimId = intval($_POST['claim_id'] ?? 0);
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (!in_array($action, ['approve', 'reject']) || $claimId <= 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    // Include files safely
    $requiredFiles = [
        '../../includes/database.php',
        '../../includes/functions.php',
        '../../includes/sendinblue-mailer.php'
    ];

    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => "Required file missing: " . basename($file)]);
            exit;
        }
        require_once $file;
    }

    // Get database connection
    $conn = getConnection();
    
    // Get claim details
    $stmt = $conn->prepare("
        SELECT c.*, u.name, u.email 
        FROM claims c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ? AND c.status = 'pending'
    ");
    $stmt->execute([$claimId]);
    
    if ($stmt->rowCount() == 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Claim not found or already processed']);
        exit;
    }
    
    $claim = $stmt->fetch();
    $userId = $claim['user_id'];
    $userName = $claim['name'];
    $userEmail = $claim['email'];
    $amount = $claim['points_claimed'];
    
    // Start transaction
    $conn->beginTransaction();
    
    $emailSent = false;
    
    if ($action === 'approve') {
        // APPROVE CLAIM - User gets money, pending_points become 0
        $stmt = $conn->prepare("
            UPDATE claims 
            SET status = 'processed', admin_notes = ?, processed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$adminNotes, $claimId]);
        
        // FIXED: Clear pending_points when approved (user gets money)
        $stmt = $conn->prepare("
            UPDATE wallet 
            SET pending_points = 0
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
        // Update referral purchases to 'paid' status
        $stmt = $conn->prepare("
            UPDATE referral_purchases rp
            JOIN referrals r ON rp.referral_id = r.id
            SET rp.status = 'paid'
            WHERE r.user_id = ? AND rp.status = 'claimed'
        ");
        $stmt->execute([$userId]);
        
        // Get breakdown for email
        $stmt = $conn->prepare("
            SELECT 
                rp.purchase_month,
                rp.earning_rate,
                COUNT(*) as purchase_count,
                SUM(rp.points_earned) as points,
                SUM(rp.amount) as sales
            FROM referral_purchases rp
            JOIN referrals r ON rp.referral_id = r.id
            WHERE r.user_id = ? AND rp.status = 'paid'
            GROUP BY rp.purchase_month, rp.earning_rate
            ORDER BY rp.purchase_month
        ");
        $stmt->execute([$userId]);
        $breakdown = $stmt->fetchAll();
        
        // Send email
        $emailSent = sendApprovalEmail($userEmail, $userName, $amount, $breakdown);
        
        $message = "Claim approved successfully! Payment of ₹{$amount} processed.";
        
    } else {
        // REJECT CLAIM - Transfer pending_points back to wallet.points
        $stmt = $conn->prepare("
            UPDATE claims 
            SET status = 'rejected', admin_notes = ?, processed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$adminNotes, $claimId]);
        
        // FIXED: Proper point transfer - pending_points → wallet.points
        $stmt = $conn->prepare("SELECT id, pending_points FROM wallet WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() == 0) {
            // Create wallet if doesn't exist
            $stmt = $conn->prepare("
                INSERT INTO wallet (user_id, points, pending_points, total_earned, total_claimed) 
                VALUES (?, ?, 0, ?, 0)
            ");
            $stmt->execute([$userId, $amount, $amount]);
        } else {
            $wallet = $stmt->fetch();
            $currentPendingPoints = floatval($wallet['pending_points']);
            
            // FIXED: Move pending_points back to wallet.points, clear pending_points
            $stmt = $conn->prepare("
                UPDATE wallet 
                SET points = points + ?,
                    pending_points = 0,
                    total_claimed = total_claimed - ?
                WHERE user_id = ?
            ");
            $stmt->execute([$currentPendingPoints, $amount, $userId]);
            
            error_log("REJECT: Moved ₹{$currentPendingPoints} from pending back to wallet points for user {$userId}");
        }
        
        // Update referral purchases back to 'credited' status
        $stmt = $conn->prepare("
            UPDATE referral_purchases rp
            JOIN referrals r ON rp.referral_id = r.id
            SET rp.status = 'credited'
            WHERE r.user_id = ? AND rp.status = 'claimed'
        ");
        $stmt->execute([$userId]);
        
        // Send email
        $emailSent = sendRejectionEmail($userEmail, $userName, $claimId, $amount, $adminNotes);
        
        $message = "Claim rejected successfully! ₹{$amount} returned to user's wallet.";
    }
    
    // Commit transaction
    $conn->commit();
    
    // Success response
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => $message,
        'action' => $action,
        'claim_id' => $claimId,
        'amount' => $amount,
        'email_sent' => $emailSent,
        'user_name' => $userName
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    error_log("Admin claim error: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Email functions
function sendApprovalEmail($userEmail, $userName, $amount, $breakdown) {
    try {
        if (!file_exists('../../includes/email-config.php')) {
            return false;
        }
        
        $emailConfig = include '../../includes/email-config.php';
        
        if (!$emailConfig['settings']['enabled'] || 
            empty($emailConfig['sendinblue']['api_key']) || 
            $emailConfig['sendinblue']['api_key'] === 'YOUR_SENDINBLUE_API_KEY_HERE') {
            return false;
        }
        
        $mailer = new SendinblueMailer(
            $emailConfig['sendinblue']['api_key'],
            $emailConfig['sendinblue']['from_email'],
            $emailConfig['sendinblue']['from_name']
        );
        
        return $mailer->sendPaymentProcessedEmail($userEmail, $userName, $amount, $breakdown);
        
    } catch (Exception $e) {
        error_log("Approval email error: " . $e->getMessage());
        return false;
    }
}

function sendRejectionEmail($userEmail, $userName, $claimId, $amount, $reason) {
    try {
        if (!file_exists('../../includes/email-config.php')) {
            return false;
        }
        
        $emailConfig = include '../../includes/email-config.php';
        
        if (!$emailConfig['settings']['enabled'] || 
            empty($emailConfig['sendinblue']['api_key']) || 
            $emailConfig['sendinblue']['api_key'] === 'YOUR_SENDINBLUE_API_KEY_HERE') {
            return false;
        }
        
        $mailer = new SendinblueMailer(
            $emailConfig['sendinblue']['api_key'],
            $emailConfig['sendinblue']['from_email'],
            $emailConfig['sendinblue']['from_name']
        );
        
        return $mailer->sendClaimRejectedEmail($userEmail, $userName, $claimId, $amount, $reason);
        
    } catch (Exception $e) {
        error_log("Rejection email error: " . $e->getMessage());
        return false;
    }
}

ob_end_flush();
?>