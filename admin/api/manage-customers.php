<?php
// admin/api/manage-customers.php - Customer Management API
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../admin-session.php';

// Set content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check admin authentication for all requests
checkAdminAuth();

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_customers':
            handleGetCustomers();
            break;
            
        case 'get_customer':
            handleGetCustomer();
            break;
            
        case 'toggle_status':
            handleToggleStatus();
            break;
            
        case 'get_stats':
            handleGetStats();
            break;
            
        case 'export_customers':
            handleExportCustomers();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Admin Customers API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

// ================================
// GET CUSTOMERS WITH PAGINATION
// ================================
function handleGetCustomers() {
    try {
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = max(1, min(100, intval($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        
        // Build filters
        $filters = [];
        $params = [];
        
        if (!empty($_GET['search'])) {
            $filters[] = "(u.name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($_GET['status'])) {
            $filters[] = "COALESCE(u.status, 'active') = ?";
            $params[] = $_GET['status'];
        }
        
        if (!empty($_GET['has_orders'])) {
            if ($_GET['has_orders'] === 'yes') {
                $filters[] = "customer_stats.total_orders > 0";
            } else {
                $filters[] = "(customer_stats.total_orders = 0 OR customer_stats.total_orders IS NULL)";
            }
        }
        
        if (!empty($_GET['has_referrals'])) {
            if ($_GET['has_referrals'] === 'yes') {
                $filters[] = "u.referral_code IS NOT NULL AND u.referral_code != ''";
            } else {
                $filters[] = "(u.referral_code IS NULL OR u.referral_code = '')";
            }
        }
        
        $whereClause = empty($filters) ? '' : 'WHERE ' . implode(' AND ', $filters);
        
        $conn = getConnection();
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) as total 
            FROM users u 
            LEFT JOIN (
                SELECT 
                    user_id,
                    COUNT(*) as total_orders,
                    SUM(final_amount) as total_spent
                FROM orders 
                WHERE user_id IS NOT NULL 
                GROUP BY user_id
            ) customer_stats ON u.id = customer_stats.user_id
            {$whereClause}
        ";
        $stmt = $conn->prepare($countSql);
        $stmt->execute($params);
        $totalItems = $stmt->fetch()['total'];
        
        // Get customers with proper LIMIT syntax
        $sql = "
            SELECT 
                u.id,
                u.name,
                u.email,
                u.created_at,
                COALESCE(u.last_login, NULL) as last_login,
                COALESCE(u.status, 'active') as status,
                COALESCE(u.wallet_balance, 0) as wallet_balance,
                COALESCE(u.referral_code, '') as referral_code,
                COALESCE(u.profile_image, '') as profile_image,
                COALESCE(customer_stats.total_orders, 0) as total_orders,
                COALESCE(customer_stats.total_spent, 0) as total_spent,
                (
                    SELECT COUNT(*) 
                    FROM users referred 
                    WHERE referred.referred_by = u.referral_code 
                    AND u.referral_code IS NOT NULL
                ) as referral_count,
                (
                    SELECT COALESCE(SUM(month_points), 0)
                    FROM user_monthly_earnings 
                    WHERE user_id = u.id
                ) as referral_earnings,
                (
                    SELECT COALESCE(SUM(claimed_amount), 0)
                    FROM wallet_transactions 
                    WHERE wallet_id = u.id AND transaction_type = 'claimed'
                ) as total_claimed,
                (
                    SELECT COALESCE(SUM(points), 0)
                    FROM wallet_transactions 
                    WHERE wallet_id = u.id AND transaction_type = 'earned'
                ) as total_earned
            FROM users u
            LEFT JOIN (
                SELECT 
                    user_id,
                    COUNT(*) as total_orders,
                    SUM(final_amount) as total_spent
                FROM orders 
                WHERE user_id IS NOT NULL 
                GROUP BY user_id
            ) customer_stats ON u.id = customer_stats.user_id
            {$whereClause}
            ORDER BY u.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
        
        // Process customers
        foreach ($customers as &$customer) {
            $customer['wallet_balance'] = floatval($customer['wallet_balance']);
            $customer['total_orders'] = intval($customer['total_orders']);
            $customer['total_spent'] = floatval($customer['total_spent']);
            $customer['referral_count'] = intval($customer['referral_count']);
            $customer['referral_earnings'] = floatval($customer['referral_earnings']);
            $customer['total_claimed'] = floatval($customer['total_claimed']);
            $customer['total_earned'] = floatval($customer['total_earned']);
        }
        
        // Pagination info
        $totalPages = ceil($totalItems / $perPage);
        
        echo json_encode([
            'success' => true,
            'customers' => $customers,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => intval($totalItems),
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetCustomers: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load customers: ' . $e->getMessage()]);
    }
}

// ================================
// GET SINGLE CUSTOMER
// ================================
function handleGetCustomer() {
    try {
        $customerId = intval($_GET['id'] ?? 0);
        
        if ($customerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
            return;
        }
        
        $conn = getConnection();
        
        // Get customer details
        $stmt = $conn->prepare("
            SELECT 
                u.*,
                COALESCE(u.status, 'active') as status,
                COALESCE(u.wallet_balance, 0) as wallet_balance,
                (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
                (SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE user_id = u.id) as total_spent,
                (SELECT COUNT(*) FROM users WHERE referred_by = u.referral_code AND u.referral_code IS NOT NULL) as referral_count,
                (SELECT COALESCE(SUM(month_points), 0) FROM user_monthly_earnings WHERE user_id = u.id) as referral_earnings,
                (SELECT COALESCE(SUM(claimed_amount), 0) FROM wallet_transactions WHERE wallet_id = u.id AND transaction_type = 'claimed') as total_claimed,
                (SELECT COALESCE(SUM(points), 0) FROM wallet_transactions WHERE wallet_id = u.id AND transaction_type = 'earned') as total_earned
            FROM users u 
            WHERE u.id = ?
        ");
        $stmt->execute([$customerId]);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            return;
        }
        
        $customer = $stmt->fetch();
        
        // Get monthly breakdown if referral code exists
        if (!empty($customer['referral_code'])) {
            $stmt = $conn->prepare("
                SELECT * FROM user_monthly_earnings 
                WHERE user_id = ? 
                ORDER BY purchase_month DESC
            ");
            $stmt->execute([$customerId]);
            $customer['monthly_breakdown'] = $stmt->fetchAll();
        }
        
        // Process numeric values
        $customer['wallet_balance'] = floatval($customer['wallet_balance']);
        $customer['total_orders'] = intval($customer['total_orders']);
        $customer['total_spent'] = floatval($customer['total_spent']);
        $customer['referral_count'] = intval($customer['referral_count']);
        $customer['referral_earnings'] = floatval($customer['referral_earnings']);
        $customer['total_claimed'] = floatval($customer['total_claimed']);
        $customer['total_earned'] = floatval($customer['total_earned']);
        
        echo json_encode([
            'success' => true,
            'customer' => $customer
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetCustomer: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load customer']);
    }
}

// ================================
// TOGGLE CUSTOMER STATUS
// ================================
function handleToggleStatus() {
    try {
        $customerId = intval($_POST['customer_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        
        if ($customerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
            return;
        }
        
        if (!in_array($newStatus, ['active', 'inactive'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            return;
        }
        
        $conn = getConnection();
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $customerId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Customer status updated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleToggleStatus: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

// ================================
// GET CUSTOMER STATISTICS
// ================================
function handleGetStats() {
    try {
        $conn = getConnection();
        
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_customers,
                SUM(CASE WHEN COALESCE(status, 'active') = 'active' THEN 1 ELSE 0 END) as active_customers,
                SUM(COALESCE(wallet_balance, 0)) as total_wallet_balance,
                COUNT(CASE WHEN referral_code IS NOT NULL AND referral_code != '' THEN 1 END) as total_referrers
            FROM users
        ");
        
        $stats = $stmt->fetch();
        
        // Ensure all values are numeric
        foreach ($stats as $key => $value) {
            if ($key === 'total_wallet_balance') {
                $stats[$key] = floatval($value ?? 0);
            } else {
                $stats[$key] = intval($value ?? 0);
            }
        }
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetStats: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load statistics']);
    }
}

// ================================
// EXPORT CUSTOMERS TO CSV
// ================================
function handleExportCustomers() {
    try {
        $conn = getConnection();
        
        // Build filters (same as get_customers)
        $filters = [];
        $params = [];
        
        if (!empty($_GET['search'])) {
            $filters[] = "(u.name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($_GET['status'])) {
            $filters[] = "COALESCE(u.status, 'active') = ?";
            $params[] = $_GET['status'];
        }
        
        $whereClause = empty($filters) ? '' : 'WHERE ' . implode(' AND ', $filters);
        
        $stmt = $conn->prepare("
            SELECT 
                u.name,
                u.email,
                COALESCE(u.wallet_balance, 0) as wallet_balance,
                COALESCE(u.referral_code, '') as referral_code,
                COALESCE(u.status, 'active') as status,
                u.created_at,
                u.last_login,
                (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
                (SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE user_id = u.id) as total_spent,
                (SELECT COUNT(*) FROM users WHERE referred_by = u.referral_code AND u.referral_code IS NOT NULL) as referral_count
            FROM users u
            {$whereClause}
            ORDER BY u.created_at DESC
        ");
        
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
        
        // Set CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customers_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        fputcsv($output, [
            'Name', 'Email', 'Wallet Balance', 'Referral Code', 'Status',
            'Join Date', 'Last Login', 'Total Orders', 'Total Spent', 'Referrals Made'
        ]);
        
        // Write data rows
        foreach ($customers as $customer) {
            fputcsv($output, [
                $customer['name'],
                $customer['email'],
                $customer['wallet_balance'] ?: '0.00',
                $customer['referral_code'] ?: 'N/A',
                $customer['status'],
                $customer['created_at'],
                $customer['last_login'] ?: 'Never',
                $customer['total_orders'] ?: '0',
                $customer['total_spent'] ?: '0.00',
                $customer['referral_count'] ?: '0'
            ]);
        }
        
        fclose($output);
        
    } catch (Exception $e) {
        error_log("Error in handleExportCustomers: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Export failed']);
    }
}
?>