<?php
// admin/api/manage-orders.php - Order Management API
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
        case 'get_orders':
            handleGetOrders();
            break;
            
        case 'get_order':
            handleGetOrder();
            break;
            
        case 'update_status':
            handleUpdateStatus();
            break;
            
        case 'get_stats':
            handleGetStats();
            break;
            
        case 'export_orders':
            handleExportOrders();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Admin Orders API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

// ================================
// GET ORDERS WITH PAGINATION
// ================================
function handleGetOrders() {
    try {
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = max(1, min(100, intval($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        
        // Build filters
        $filters = [];
        $params = [];
        
        if (!empty($_GET['search'])) {
            $filters[] = "(o.order_number LIKE ? OR COALESCE(u.name, '') LIKE ? OR COALESCE(u.email, '') LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($_GET['status'])) {
            $filters[] = "o.status = ?";
            $params[] = $_GET['status'];
        }
        
        if (!empty($_GET['payment_status'])) {
            $filters[] = "o.payment_status = ?";
            $params[] = $_GET['payment_status'];
        }
        
        if (!empty($_GET['date_range'])) {
            switch ($_GET['date_range']) {
                case 'today':
                    $filters[] = "DATE(o.created_at) = CURDATE()";
                    break;
                case 'week':
                    $filters[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $filters[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
        }
        
        $whereClause = empty($filters) ? '' : 'WHERE ' . implode(' AND ', $filters);
        
        $conn = getConnection();
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) as total 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            {$whereClause}
        ";
        $stmt = $conn->prepare($countSql);
        $stmt->execute($params);
        $totalItems = $stmt->fetch()['total'];
        
        // Get orders with proper LIMIT syntax - ENHANCED QUERY
        $sql = "
            SELECT 
                o.id,
                o.order_number,
                o.status,
                o.payment_status,
                o.total_amount,
                COALESCE(o.tax_amount, 0) as tax_amount,
                COALESCE(o.shipping_amount, 0) as shipping_amount,
                COALESCE(o.wallet_points_used, 0) as wallet_points_used,
                o.final_amount,
                o.created_at,
                COALESCE(o.referral_code, '') as referral_code,
                COALESCE(u.name, '') as customer_name,
                COALESCE(u.email, '') as customer_email,
                COALESCE(u.profile_image, '') as customer_profile_image,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            {$whereClause}
            ORDER BY o.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // Process orders
        foreach ($orders as &$order) {
            $order['total_amount'] = floatval($order['total_amount']);
            $order['tax_amount'] = floatval($order['tax_amount']);
            $order['shipping_amount'] = floatval($order['shipping_amount']);
            $order['wallet_points_used'] = floatval($order['wallet_points_used']);
            $order['final_amount'] = floatval($order['final_amount']);
            $order['item_count'] = intval($order['item_count']);
        }
        
        // Pagination info
        $totalPages = ceil($totalItems / $perPage);
        
        echo json_encode([
            'success' => true,
            'orders' => $orders,
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
        error_log("Error in handleGetOrders: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load orders: ' . $e->getMessage()]);
    }
}

// ================================
// GET SINGLE ORDER
// ================================
function handleGetOrder() {
    try {
        $orderId = intval($_GET['id'] ?? 0);
        
        if ($orderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            return;
        }
        
        $conn = getConnection();
        
        // Get order details
        $stmt = $conn->prepare("
            SELECT 
                o.*,
                COALESCE(u.name, '') as customer_name,
                COALESCE(u.email, '') as customer_email,
                COALESCE(u.profile_image, '') as customer_profile_image
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        
        // Get order items - FIXED QUERY
        $stmt = $conn->prepare("
            SELECT 
                oi.*,
                COALESCE(p.name, oi.product_name) as product_name,
                COALESCE(p.image, p.main_image, p.product_image, '/images/placeholder-product.jpg') as product_image,
                p.description as product_description
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ");
        $stmt->execute([$orderId]);
        $order['items'] = $stmt->fetchAll();
        
        // Parse JSON fields safely
        $order['shipping_address'] = !empty($order['shipping_address']) ? json_decode($order['shipping_address'], true) : null;
        $order['billing_address'] = !empty($order['billing_address']) ? json_decode($order['billing_address'], true) : null;
        
        // Convert numeric fields
        $order['total_amount'] = floatval($order['total_amount']);
        $order['wallet_points_used'] = floatval($order['wallet_points_used'] ?? 0);
        $order['final_amount'] = floatval($order['final_amount']);
        
        // Add missing columns with default values if they don't exist
        if (!isset($order['tax_amount'])) {
            $order['tax_amount'] = 0.00;
        }
        if (!isset($order['shipping_amount'])) {
            $order['shipping_amount'] = 0.00;
        }
        
        echo json_encode([
            'success' => true,
            'order' => $order
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetOrder: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load order: ' . $e->getMessage()]);
    }
}

// ================================
// UPDATE ORDER STATUS
// ================================
function handleUpdateStatus() {
    try {
        $orderId = intval($_POST['order_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if ($orderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            return;
        }
        
        if (empty($newStatus)) {
            echo json_encode(['success' => false, 'message' => 'Status is required']);
            return;
        }
        
        // Validate status
        $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        if (!in_array($newStatus, $validStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            return;
        }
        
        $conn = getConnection();
        
        // Get current order
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        
        $order = $stmt->fetch();
        
        // Update order status
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $orderId]);
        
        // Log status change if notes provided
        if (!empty($notes)) {
            $statusNote = date('Y-m-d H:i:s') . " - Status changed to {$newStatus}: {$notes}\n";
            $stmt = $conn->prepare("
                UPDATE orders 
                SET notes = CONCAT(COALESCE(notes, ''), ?) 
                WHERE id = ?
            ");
            $stmt->execute([$statusNote, $orderId]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleUpdateStatus: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

// ================================
// GET ORDER STATISTICS
// ================================
function handleGetStats() {
    try {
        $conn = getConnection();
        
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN final_amount ELSE 0 END) as total_revenue,
                AVG(CASE WHEN payment_status = 'paid' THEN final_amount ELSE NULL END) as average_order_value
            FROM orders
        ");
        
        $stats = $stmt->fetch();
        
        // Ensure all values are numeric
        foreach ($stats as $key => $value) {
            if (in_array($key, ['total_revenue', 'average_order_value'])) {
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
// EXPORT ORDERS TO CSV
// ================================
function handleExportOrders() {
    try {
        $conn = getConnection();
        
        // Build filters (same as get_orders)
        $filters = [];
        $params = [];
        
        if (!empty($_GET['search'])) {
            $filters[] = "(o.order_number LIKE ? OR COALESCE(u.name, '') LIKE ? OR COALESCE(u.email, '') LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($_GET['status'])) {
            $filters[] = "o.status = ?";
            $params[] = $_GET['status'];
        }
        
        if (!empty($_GET['payment_status'])) {
            $filters[] = "o.payment_status = ?";
            $params[] = $_GET['payment_status'];
        }
        
        $whereClause = empty($filters) ? '' : 'WHERE ' . implode(' AND ', $filters);
        
        $stmt = $conn->prepare("
            SELECT 
                o.order_number,
                o.status,
                o.payment_status,
                o.total_amount,
                COALESCE(o.wallet_points_used, 0) as wallet_points_used,
                o.final_amount,
                o.created_at,
                COALESCE(u.name, 'Guest') as customer_name,
                COALESCE(u.email, 'N/A') as customer_email,
                COALESCE(o.referral_code, 'N/A') as referral_code
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            {$whereClause}
            ORDER BY o.created_at DESC
        ");
        
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // Set CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="orders_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        fputcsv($output, [
            'Order Number', 'Customer Name', 'Customer Email', 'Status', 'Payment Status',
            'Total Amount', 'Wallet Used', 'Final Amount', 'Referral Code', 'Order Date'
        ]);
        
        // Write data rows
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['order_number'],
                $order['customer_name'],
                $order['customer_email'],
                ucfirst($order['status']),
                ucfirst($order['payment_status']),
                $order['total_amount'],
                $order['wallet_points_used'],
                $order['final_amount'],
                $order['referral_code'],
                $order['created_at']
            ]);
        }
        
        fclose($output);
        
    } catch (Exception $e) {
        error_log("Error in handleExportOrders: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Export failed']);
    }
}

?>