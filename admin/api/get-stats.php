<?php
// admin/api/get-stats.php - Backend API only, returns JSON data
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../admin-session.php';

// Set JSON header
header('Content-Type: application/json');

// Check admin authentication
checkAdminAuth();

$conn = getConnection();

try {
    // Get all users with complete stats
        $allUsers = $conn->query("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.mobile_number,
            u.upi_id,
            u.profile_image,
            u.created_at,
            u.last_login,
            COALESCE(w.points, 0) as points,
            COALESCE(w.pending_points, 0) as pending_points,
            COALESCE(w.total_earned, 0) as total_earned,
            COALESCE(w.total_claimed, 0) as total_claimed,
            r.code as referral_code,
            COALESCE(r.purchase_count, 0) as purchase_count,
            COALESCE(r.total_earnings, 0) as referral_earnings,
            COUNT(DISTINCT rv.id) as visit_count
        FROM users u
        LEFT JOIN wallet w ON u.id = w.user_id
        LEFT JOIN referrals r ON u.id = r.user_id
        LEFT JOIN referral_visits rv ON r.id = rv.referral_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ")->fetchAll();

    // Get all pending claims with user info
    $pendingClaims = $conn->query("
        SELECT 
            c.id,
            c.user_id,
            c.points_claimed,
            c.created_at,
            u.name,
            u.email,
            u.profile_image
        FROM claims c
        JOIN users u ON c.user_id = u.id
        WHERE c.status = 'pending'
        ORDER BY c.created_at DESC
    ")->fetchAll();

    // Get overall system stats
    $totalStats = $conn->query("
        SELECT 
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT r.user_id) as total_referrers,
            COUNT(DISTINCT rp.id) as total_purchases,
            COALESCE(SUM(rp.amount), 0) as total_sales,
            COALESCE(SUM(rp.points_earned), 0) as total_points_earned,
            COALESCE(SUM(w.total_claimed), 0) as total_money_paid
        FROM users u
        LEFT JOIN referrals r ON u.id = r.user_id
        LEFT JOIN referral_purchases rp ON r.id = rp.referral_id
        LEFT JOIN wallet w ON u.id = w.user_id
    ")->fetch();

    // Get month-wise statistics
    $monthlyStats = $conn->query("
        SELECT 
            rp.purchase_month,
            rp.earning_rate,
            COUNT(*) as purchase_count,
            COUNT(DISTINCT r.user_id) as unique_referrers,
            SUM(rp.amount) as total_sales,
            SUM(rp.points_earned) as total_points
        FROM referral_purchases rp
        JOIN referrals r ON rp.referral_id = r.id
        GROUP BY rp.purchase_month, rp.earning_rate
        ORDER BY rp.purchase_month
    ")->fetchAll();

    // Get monthly breakdown for each pending claim
    $claimsWithBreakdown = [];
    foreach ($pendingClaims as $claim) {
        $stmt = $conn->prepare("
            SELECT 
                rp.purchase_month,
                rp.earning_rate,
                COUNT(*) as purchase_count,
                SUM(rp.points_earned) as month_points,
                SUM(rp.amount) as month_sales
            FROM referral_purchases rp
            JOIN referrals r ON rp.referral_id = r.id
            WHERE r.user_id = ? AND rp.status = 'claimed'
            GROUP BY rp.purchase_month, rp.earning_rate
            ORDER BY rp.purchase_month
        ");
        $stmt->execute([$claim['user_id']]);
        
        $claim['monthly_breakdown'] = $stmt->fetchAll();
        $claimsWithBreakdown[] = $claim;
    }

    // Return all data as JSON
    echo json_encode([
        'success' => true,
        'data' => [
            'all_users' => $allUsers,
            'pending_claims' => $claimsWithBreakdown,
            'total_stats' => $totalStats,
            'monthly_stats' => $monthlyStats
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch stats: ' . $e->getMessage()
    ]);
}
?>