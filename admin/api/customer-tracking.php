<?php
// api/customer-tracking.php - Customer Order Tracking API
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to track orders']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'track_order':
        trackCustomerOrder();
        break;
    case 'get_order_status':
        getOrderStatus();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function trackCustomerOrder() {
    global $userId;
    
    $orderId = intval($_GET['order_id'] ?? 0);
    
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        return;
    }
    
    try {
        $conn = getConnection();
        
        // Verify order belongs to user
        $stmt = $conn->prepare("
            SELECT order_number, status, shiprocket_shipment_id, tracking_number 
            FROM orders 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$orderId, $userId]);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get comprehensive tracking data using your existing function
        $trackingData = getComprehensiveTrackingData($orderId, $order['order_number'], $order['status']);
        
        if ($trackingData) {
            echo json_encode([
                'success' => true,
                'tracking_data' => $trackingData
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Tracking information not available'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Customer tracking error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error loading tracking data']);
    }
}

function getOrderStatus() {
    global $userId;
    
    $orderId = intval($_GET['order_id'] ?? 0);
    
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        return;
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                order_number,
                status,
                payment_status,
                tracking_number,
                created_at,
                updated_at
            FROM orders 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$orderId, $userId]);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'order' => $order
        ]);
        
    } catch (Exception $e) {
        error_log("Get order status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error loading order status']);
    }
}
?>