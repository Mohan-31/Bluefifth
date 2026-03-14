<?php
// wallet/get-balance.php - ENHANCED
header('Content-Type: application/json');
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userId = getCurrentUserId();
$conn = getConnection();

// Get wallet balance 
$stmt = $conn->prepare("SELECT points, pending_points, total_earned, total_claimed FROM wallet WHERE user_id = ?");
$stmt->execute([$userId]);

if ($stmt->rowCount() > 0) {
    $balance = $stmt->fetch();
} else {
    // Create wallet if it doesn't exist
    $walletId = ensureUserWallet($userId);
    $balance = ['points' => 0, 'pending_points' => 0, 'total_earned' => 0, 'total_claimed' => 0];
}

// ENHANCED: Get held points data (NEW FEATURE)
$heldPointsData = [];
$totalHeldPoints = 0;

try {
    // Get held points details with return risk information
        $stmt = $conn->prepare("
        SELECT 
            rp.id,
            rp.order_id,
            rp.points_earned,
            rp.created_at,
            rp.hold_until,
            rp.earning_rate,
            rp.purchase_month,
            DATEDIFF(rp.hold_until, NOW()) as days_remaining,
            CASE 
                WHEN rp.hold_until <= NOW() THEN 'ready_to_release'
                WHEN DATEDIFF(rp.hold_until, NOW()) <= 1 THEN 'releasing_soon'
                ELSE 'holding'
            END as release_status,
            -- Check if order has any pending/approved returns
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM order_returns 
                    WHERE order_number COLLATE utf8mb4_unicode_ci = rp.order_id COLLATE utf8mb4_unicode_ci
                    AND return_status IN ('requested', 'pickup_scheduled', 'collected', 'received')
                ) THEN 1 
                ELSE 0 
            END as has_return_risk
        FROM referral_purchases rp
        JOIN referrals r ON rp.referral_id COLLATE utf8mb4_unicode_ci = r.id COLLATE utf8mb4_unicode_ci
        WHERE r.user_id = ? 
        AND rp.hold_status = 'hold'
        AND rp.status = 'credited'
        ORDER BY rp.hold_until ASC
    ");
    
    $stmt->execute([$userId]);
    $heldPointsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total held points
    foreach ($heldPointsData as $held) {
        $totalHeldPoints += floatval($held['points_earned']);
    }
    
    // Add this temporarily for debugging
    error_log("Held Points Debug: " . json_encode([
        'total_held' => $totalHeldPoints,
        'held_data_count' => count($heldPointsData),
        'user_id' => $userId
    ]));
    
} catch (Exception $e) {
    error_log("Error getting held points: " . $e->getMessage());
    $heldPointsData = [];
    $totalHeldPoints = 0;
}

// Get referral stats
$stmt = $conn->prepare("
    SELECT 
        r.code,
        r.link,
        r.created_at,
        COUNT(DISTINCT rv.id) as visit_count,
        COUNT(DISTINCT rp.id) as purchase_count,
        SUM(CASE WHEN rp.hold_status IN ('immediate', 'released') THEN rp.points_earned ELSE 0 END) as total_points_earned,
        SUM(CASE WHEN rp.hold_status = 'hold' THEN rp.points_earned ELSE 0 END) as held_points_earned,
        SUM(CASE WHEN rp.hold_status = 'canceled' THEN rp.points_earned ELSE 0 END) as canceled_points
    FROM referrals r
    LEFT JOIN referral_visits rv ON r.id = rv.referral_id
    LEFT JOIN referral_purchases rp ON r.id = rp.referral_id
    WHERE r.user_id = ?
    GROUP BY r.id
");
$stmt->execute([$userId]);
$referralStats = $stmt->fetch();

if (!$referralStats) {
    // No referral found, create one
    $code = generateReferralCode();
    $link = generateReferralLink($code);
    
    $stmt = $conn->prepare("INSERT INTO referrals (user_id, code, link) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $code, $link]);
    
    $referralStats = [
        'code' => $code,
        'link' => $link,
        'visit_count' => 0,
        'purchase_count' => 0,
        'total_points_earned' => 0,
        'held_points_earned' => 0,
        'canceled_points' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// FIXED: Calculate total wallet points correctly
$totalWalletPoints = floatval($balance['points']) + floatval($balance['pending_points']);

// FIXED: Use only isMonthEnd() function for claiming
$canClaim = isMonthEnd() && $totalWalletPoints >= 100;

// ENHANCED: Get recent transactions with real-time status updates + held points transactions
$stmt = $conn->prepare("
    SELECT 
        wt.points,
        wt.transaction_type,
        wt.created_at,
        wt.description,
        wt.reference_id
    FROM wallet_transactions wt
    JOIN wallet w ON wt.wallet_id = w.id
    WHERE w.user_id = ?
    ORDER BY wt.created_at DESC, wt.id DESC
    LIMIT 15
");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll();

// ENHANCED: Get current claim statuses for real-time updates
$stmt = $conn->prepare("
    SELECT 
        id as claim_id,
        points_claimed,
        status,
        created_at
    FROM claims 
    WHERE user_id = ? 
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$claims = $stmt->fetchAll();

// Get user payment details
$stmt = $conn->prepare("
    SELECT mobile_number, upi_id 
    FROM users 
    WHERE id = ?
");
$stmt->execute([$userId]);
$userDetails = $stmt->fetch();

// ENHANCED: Update transaction statuses based on current claim status
foreach ($transactions as &$transaction) {
    if ($transaction['transaction_type'] === 'claimed' && $transaction['reference_id']) {
        // Check current claim status
        foreach ($claims as $claim) {
            if ($claim['claim_id'] == $transaction['reference_id']) {
                switch ($claim['status']) {
                    case 'processed':
                        $transaction['transaction_type'] = 'processed';
                        break;
                    case 'rejected':
                        $transaction['transaction_type'] = 'rejected';
                        break;
                    case 'pending':
                        // Keep as 'claimed' but it will show as "Pending Admin"
                        break;
                }
                break;
            }
        }
    }
}

// ENHANCED: Calculate projected total (current + held)
$projectedTotal = $totalWalletPoints + $totalHeldPoints;

// ENHANCED: Add held points summary to referral stats
$referralStats['held_points_earned'] = floatval($referralStats['held_points_earned'] ?? 0);
$referralStats['canceled_points'] = floatval($referralStats['canceled_points'] ?? 0);

// Return enhanced response with held points data
echo json_encode([
    'success' => true,
    'balance' => [
        'available' => $totalWalletPoints,
        'points' => floatval($balance['points']),
        'pending_points' => floatval($balance['pending_points']),
        'held_points' => $totalHeldPoints, // NEW: Held points from referrals
        'projected_total' => $projectedTotal, // NEW: Total including held points
        'total_earned' => floatval($balance['total_earned']),
        'total_claimed' => floatval($balance['total_claimed'])
    ],
    'held_details' => $heldPointsData, // NEW: Detailed breakdown of held points
    'referral' => $referralStats,
    'can_claim' => $canClaim,
    'transactions' => $transactions,
    'user_details' => $userDetails ?: [],
    'held_points_summary' => [ // NEW: Summary for frontend
        'total_held' => $totalHeldPoints,
        'orders_on_hold' => count($heldPointsData),
        'at_risk_points' => array_sum(array_column(array_filter($heldPointsData, function($item) {
            return $item['has_return_risk'] == 1;
        }), 'points_earned')),
        'earliest_release' => !empty($heldPointsData) ? min(array_column($heldPointsData, 'hold_until')) : null
    ],
    'debug_claims' => $claims, // For debugging - remove in production
    'timestamp' => date('Y-m-d H:i:s') // Cache busting
]);
?>