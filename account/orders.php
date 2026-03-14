<?php
// account/orders.php - Customer Orders with Shiprocket Tracking Integration - FIXED VERSION
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// ENHANCED AJAX handling with better error checking and debugging
// ENHANCED AJAX handling with better error checking and debugging
if (isset($_GET['action']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Get connection for AJAX
    $conn = getConnection();
    
    // FORCE PDO settings for live server compatibility
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // GET ACTION FROM BOTH GET AND POST
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    error_log("AJAX DEBUG: Action = " . $action);
    error_log("AJAX DEBUG: Method = " . $_SERVER['REQUEST_METHOD']);
    error_log("AJAX DEBUG: Order ID = " . ($_GET['order_id'] ?? $_POST['order_id'] ?? 'NONE'));
    error_log("AJAX DEBUG: User ID = " . ($userId ?? 'NONE'));
    
    switch ($action) {
        case 'get_order_details':
            $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
            
            error_log("AJAX: get_order_details for order ID: " . $orderId);
            
            if ($orderId <= 0) {
                error_log("AJAX: Invalid order ID");
                echo json_encode(['success' => false, 'message' => 'Invalid order ID: ' . $orderId]);
                exit;
            }
            
            try {
                if ($isLoggedIn) {
                    // Logged-in user: check ownership
                    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
                    $stmt->execute([$orderId, $userId]);
                } else {
                    // Guest: allow access to any order (you may want to add email verification here)
                    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
                    $stmt->execute([$orderId]);
                }
                
                $order = $stmt->fetch();
                
                error_log("AJAX: Order query result: " . ($order ? 'FOUND' : 'NOT_FOUND'));
                
                if (!$order) {
                    if ($isLoggedIn) {
                        // Check if order exists but belongs to different user
                        $checkStmt = $conn->prepare("SELECT id, user_id FROM orders WHERE id = ?");
                        $checkStmt->execute([$orderId]);
                        $checkResult = $checkStmt->fetch();
                        
                        if ($checkResult) {
                            error_log("AJAX: Order exists but belongs to user " . $checkResult['user_id'] . ", current user: " . $userId);
                            echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
                        } else {
                            error_log("AJAX: Order ID " . $orderId . " does not exist");
                            echo json_encode(['success' => false, 'message' => 'Order does not exist']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Order not found']);
                    }
                    exit;
                }
                
                // Get order items
                $itemStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $itemStmt->execute([$orderId]);
                $items = $itemStmt->fetchAll();
                
                // Parse shipping address
                $shippingAddress = json_decode($order['shipping_address'] ?? '{}', true) ?: [];
                
                echo json_encode([
                    'success' => true,
                    'order' => $order,
                    'items' => $items,
                    'shipping_address' => $shippingAddress
                ]);
                
            } catch (Exception $e) {
                error_log("AJAX: Database error in get_order_details: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'track_order':
            $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
            
            error_log("AJAX: track_order for order ID: " . $orderId);
            
            if ($orderId <= 0) {
                error_log("AJAX: Invalid order ID for tracking");
                echo json_encode(['success' => false, 'message' => 'Invalid order ID: ' . $orderId]);
                exit;
            }
            
            try {
                if ($isLoggedIn) {
                    // Logged-in user: check ownership
                    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
                    $stmt->execute([$orderId, $userId]);
                } else {
                    // Guest: allow access to any order
                    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
                    $stmt->execute([$orderId]);
                }
                
                $order = $stmt->fetch();
                
                error_log("AJAX: Tracking order query result: " . ($order ? 'FOUND' : 'NOT_FOUND'));
                
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    exit;
                }
                
                // Create simple tracking data
                $trackingData = createSimpleTrackingData($order);
                
                echo json_encode([
                    'success' => true,
                    'tracking_data' => $trackingData
                ]);
                
            } catch (Exception $e) {
                error_log("AJAX: Tracking error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Tracking error: ' . $e->getMessage()]);
            }
            exit;
            
            case 'create_return':
                // FIX: Handle both GET and POST parameters for file uploads
                $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : (isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0);
                $returnReasonKey = isset($_POST['return_reason']) ? trim($_POST['return_reason']) : (isset($_GET['return_reason']) ? trim($_GET['return_reason']) : 'other');
            
                if ($orderId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
                    exit;
                }
            
                try {
                    if ($isLoggedIn) {
                        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
                        $stmt->execute([$orderId, $userId]);
                    } else {
                        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
                        $stmt->execute([$orderId]);
                    }
            
                    $order = $stmt->fetch();
            
                    if (!$order) {
                        echo json_encode(['success' => false, 'message' => 'Order not found']);
                        exit;
                    }
            
                    if (!canOrderBeReturned($order)) {
                        echo json_encode(['success' => false, 'message' => 'Order cannot be returned. Returns are only allowed within 7 days of delivery.']);
                        exit;
                    }
            
                    // Map frontend reasons → Shiprocket valid reasons
                    $validReasons = [
                        "quality"   => "performance or quality not adequate",
                        "wrong"     => "wrong item was sent", 
                        "damaged"   => "product damaged, but shipping box ok",
                        "size"      => "size not as expected",
                        "incompatible" => "incompatible or not useful",
                        "other"     => "other"
                    ];
            
                    // Fallback to "other" if invalid key passed
                    $returnReason = $validReasons[$returnReasonKey] ?? "other";
            
                    // CRITICAL FIX: Pass $_FILES to createShiprocketReturn for file handling
                    $result = createShiprocketReturn($orderId, $returnReason);
                    echo json_encode($result);
            
                } catch (Exception $e) {
                    error_log("Return AJAX error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Failed to process return request']);
                }
                exit;
                
            case 'track_return':
                $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
                
                error_log("AJAX: track_return for order ID: " . $orderId);
                
                if ($orderId <= 0) {
                    error_log("AJAX: Invalid order ID for return tracking");
                    echo json_encode(['success' => false, 'message' => 'Invalid order ID: ' . $orderId]);
                    exit;
                }
                
                try {
                    if ($isLoggedIn) {
                        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
                        $stmt->execute([$orderId, $userId]);
                    } else {
                        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
                        $stmt->execute([$orderId]);
                    }
                    
                    $order = $stmt->fetch();
                    
                    if (!$order) {
                        echo json_encode(['success' => false, 'message' => 'Order not found']);
                        exit;
                    }
                    
                    // Get return data
                    $returnData = getOrderReturnStatus($orderId);
                    
                    if (!$returnData) {
                        echo json_encode(['success' => false, 'message' => 'No return request found for this order']);
                        exit;
                    }
                    
                    // Create return tracking data
                    $trackingData = createReturnTrackingData($order, $returnData);
                    
                    echo json_encode([
                        'success' => true,
                        'tracking_data' => $trackingData
                    ]);
                    
                } catch (Exception $e) {
                    error_log("AJAX: Return tracking error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Return tracking error: ' . $e->getMessage()]);
                }
                exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

// Check for order association message
$associationMessage = null;
if (isset($_SESSION['orders_associated_message'])) {
    $associationMessage = $_SESSION['orders_associated_message'];
    unset($_SESSION['orders_associated_message']); // Clear after showing
}

// Handle guest order lookup
$guestLookupOrders = [];
$guestLookupError = null;

if (!$isLoggedIn && isset($_POST['lookup_email'])) {
    $lookupEmail = trim($_POST['lookup_email']);
    
    if (!empty($lookupEmail) && filter_var($lookupEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("
                SELECT o.*, u.email 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE u.email = ?
                ORDER BY o.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$lookupEmail]);
            $guestLookupOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($guestLookupOrders)) {
                $guestLookupError = "No orders found for this email address.";
            }
        } catch (Exception $e) {
            $guestLookupError = "Error looking up orders. Please try again.";
            error_log("Guest lookup error: " . $e->getMessage());
        }
    } else {
        $guestLookupError = "Please enter a valid email address.";
    }
}

// Handle both logged-in users and guest order lookup
if (!$isLoggedIn && empty($_POST['lookup_email']) && empty($guestLookupOrders)) {
    // Show guest lookup form instead of redirecting
    showGuestOrderLookup();
    exit;
}

// Get dynamic settings
$siteName = getSetting('site_name', 'VELONA');
$currency = getSetting('currency', 'INR');
$currencySymbol = getSetting('currency_symbol', '₹');

// Shiprocket settings - FIXED SETTINGS KEYS
$shiprocketEnabled = getSetting('shiprocket_enabled', 'false') === 'true';
$shiprocketApiToken = getSetting('shiprocket_api_token', '');

// Get user info (only for logged-in users)
$userInfo = $isLoggedIn ? getUserById($userId) : null;

// Pagination settings
$ordersPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $ordersPerPage;

// Get orders for the user
// Get orders for the user
if ($isLoggedIn) {
    // Logged-in user: Get orders from database
    try {
        $conn = getConnection();
        
        // CRITICAL FIX: Set PDO fetch mode explicitly for this connection
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        // ENHANCED DEBUG: First check what user we're dealing with
        error_log("=== ORDERS DEBUG START ===");
        error_log("Session User ID: " . ($_SESSION['user_id'] ?? 'NONE'));
        error_log("Variable User ID: " . ($userId ?? 'NONE'));
        
        // Get total orders count
        $countStmt = $conn->prepare("SELECT COUNT(*) as total_count FROM orders WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalOrders = $countResult['total_count'];
        error_log("Count query result: " . $totalOrders);
        
        // Calculate pagination
        $totalPages = ceil($totalOrders / $ordersPerPage);
        
        // Get orders for this user
        $sql = "
            SELECT 
                o.id,
                o.order_number,
                o.total_amount,
                o.tax_amount,
                o.shipping_amount,
                o.wallet_points_used,
                o.final_amount,
                o.status,
                o.payment_status,
                o.shipping_address,
                o.shiprocket_order_id,
                o.shiprocket_shipment_id,
                o.tracking_number,
                o.created_at,
                o.updated_at
            FROM orders o
            WHERE o.user_id = ? 
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ";

        error_log("SQL query: " . $sql);
        error_log("Executing with User ID: " . $userId);

        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId, $ordersPerPage, $offset]);
        
        // Get orders
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Raw orders fetched: " . count($orders));
        
        // Process orders to ensure proper ID handling
        $processedOrders = [];
        foreach ($orders as $order) {
            if (isset($order['id']) && is_numeric($order['id']) && $order['id'] > 0) {
                $order['id'] = (int)$order['id'];
                $processedOrders[] = $order;
                error_log("Processed order ID: " . $order['id'] . " - " . $order['order_number']);
            } else {
                error_log("SKIPPING: Order with invalid ID: " . print_r($order, true));
                continue;
            }
        }
        $orders = $processedOrders;
        error_log("Final processed orders count: " . count($orders));
        error_log("=== ORDERS DEBUG END ===");

    } catch (Exception $e) {
        error_log("Orders loading error: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        $error = 'Failed to load orders: ' . $e->getMessage();
        $orders = [];
        $totalOrders = 0;
        $totalPages = 0;
    }
} else {
    // Guest user: Use lookup results
    $orders = $guestLookupOrders;
    $totalOrders = count($orders);
    $totalPages = 1; // No pagination for guest lookup
    $currentPage = 1;
    
    // Process guest orders to ensure proper ID handling
    $processedOrders = [];
    foreach ($orders as $order) {
        if (isset($order['id']) && is_numeric($order['id']) && $order['id'] > 0) {
            $order['id'] = (int)$order['id'];
            $processedOrders[] = $order;
        }
    }
    $orders = $processedOrders;
    
    error_log("Guest orders processed: " . count($orders));
}

function createSimpleTrackingData($order) {
    $baseTime = strtotime($order['created_at']);
    $status = strtolower($order['status']);
    
    $events = [];
    
    // Order Confirmed (always completed)
    $events[] = [
        'current_status' => 'Order Confirmed',
        'activity' => 'Your order has been placed and confirmed',
        'date' => date('Y-m-d H:i:s', $baseTime),
        'status' => 'completed'
    ];
    
    // Processing
    if (in_array($status, ['processing', 'shipped', 'delivered'])) {
        $events[] = [
            'current_status' => 'Order Processing',
            'activity' => 'Your order is being prepared for shipment',
            'date' => date('Y-m-d H:i:s', $baseTime + 3600),
            'status' => 'completed'
        ];
    } else {
        $events[] = [
            'current_status' => 'Order Processing',
            'activity' => 'Your order will be processed soon',
            'date' => '',
            'status' => $status === 'pending' ? 'active' : 'pending'
        ];
    }
    
    // Shipped
    if (in_array($status, ['shipped', 'delivered'])) {
        $events[] = [
            'current_status' => 'Shipped',
            'activity' => 'Your order has been shipped and is on the way',
            'date' => date('Y-m-d H:i:s', $baseTime + 86400),
            'status' => 'completed'
        ];
    } else {
        $events[] = [
            'current_status' => 'Shipped',
            'activity' => 'Your order will be shipped soon',
            'date' => '',
            'status' => $status === 'processing' ? 'active' : 'pending'
        ];
    }
    
    // Out for Delivery
    if ($status === 'delivered') {
        $events[] = [
            'current_status' => 'Out For Delivery',
            'activity' => 'Your order is out for delivery',
            'date' => date('Y-m-d H:i:s', $baseTime + 172800),
            'status' => 'completed'
        ];
    } else {
        $events[] = [
            'current_status' => 'Out For Delivery',
            'activity' => 'Your order will be out for delivery',
            'date' => '',
            'status' => $status === 'shipped' ? 'active' : 'pending'
        ];
    }
    
    // Delivered
    if ($status === 'delivered') {
        $events[] = [
            'current_status' => 'Delivered',
            'activity' => 'Your order has been delivered successfully',
            'date' => date('Y-m-d H:i:s', $baseTime + 259200),
            'status' => 'completed'
        ];
    } else {
        $events[] = [
            'current_status' => 'Delivered',
            'activity' => 'Your order will be delivered',
            'date' => '',
            'status' => 'pending'
        ];
    }
    
    return [
        'tracking_data' => [
            'awb_code' => $order['tracking_number'] ?: $order['order_number'],
            'courier_name' => 'Express Delivery',
            'track_status' => ucfirst($status),
            'edd' => date('M j, Y', $baseTime + 259200),
            'shipment_track' => $events, // Keep chronological order
            'is_return' => false
        ]
    ];
}

function getOrderItems($orderId) {
    if (!$orderId || !is_numeric($orderId) || $orderId <= 0) {
        error_log("Invalid order ID in getOrderItems: " . var_export($orderId, true));
        return [];
    }
    
    try {
        $conn = getConnection();
        // Set fetch mode for safety
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // ✅ CORRECT: order_items table uses 'order_id' as foreign key to reference orders.id
        $stmt = $conn->prepare("
            SELECT 
                product_id,
                product_name,
                product_price, 
                quantity,
                size,
                total_price
            FROM order_items 
            WHERE order_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting order items for order {$orderId}: " . $e->getMessage());
        return [];
    }
}

?>

<?php
function showGuestOrderLookup() {
    global $siteName;
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
        <title>Find My Orders - <?= htmlspecialchars($siteName) ?></title>
        <style>
            body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
            .lookup-container { min-height: 85vh; display: flex; align-items: center; }
            .lookup-card { background: white; border-radius: 15px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class="container lookup-container">
            <div class="row justify-content-center m-auto w-100">
                <div class="col-md-6">
                    <div class="lookup-card text-center">
                        <h2 class="mb-4" style="font-size:24px;"><i class="fas fa-search text-primary mb-4"></i>  Find Your Orders</h2>
                        <p class="text-muted mb-4">Enter your email address to find orders you placed as a guest.</p>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <input type="email" name="lookup_email" class="form-control form-control-lg" 
                                       placeholder="Enter your email address" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg btn-block mb-3">
                                <i class="fas fa-search mr-2"></i>Find My Orders
                            </button>
                        </form>
                        
                        <div class="text-center mt-4 d-none">
                            <p class="mb-2">Have an account?</p>
                            <a href="../auth/login.php?redirect=account/orders.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login to Your Account
                            </a>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="../index.php" class="text-muted">
                                <i class="fas fa-arrow-left mr-1"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>

<!doctype html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <title>My Orders - <?= htmlspecialchars($siteName) ?></title>
    
    <!-- Your existing styles remain the same -->
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .orders-container {
            min-height: 100vh;
        }
        
        .page-header {
            background: linear-gradient(to bottom right, #6C803F, #879D60, #879D60);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .orders-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: none;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-number {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .order-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .order-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
            border: none;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .badge-primary {
            background-color: #007bff;
            color: white;
        }
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .order-amount {
            font-size: 1.3rem;
            font-weight: 700;
            color: #28a745;
        }
        
        .order-items-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        
        .item-preview {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #555;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary-action {
            background: linear-gradient(45deg, #303030, #181818);
            color: white;
        }
        
        .btn-primary-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-secondary-action {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #e9ecef;
        }
        
        .btn-secondary-action:hover {
            background: #e9ecef;
            color: #333;
            text-decoration: none;
        }
        
        .btn-success-action {
            background: #28a745;
            color: white;
        }
        
        .btn-success-action:hover {
            background: #218838;
            color: white;
            text-decoration: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-subtitle {
            color: #999;
            margin-bottom: 30px;
        }
        
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 40px;
        }
        
        .pagination .page-link {
            color: #667eea;
            border: 1px solid #e9ecef;
            padding: 10px 15px;
        }
        
        .pagination .page-link:hover {
            background-color: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        /* Modal Styles */
        .modal-header {
            background: linear-gradient(to bottom right, #6C803F, #879D60, #879D60);
            color: white;
            border-bottom: none;
        }
        
        .modal-header .close {
            color: white;
            opacity: 0.8;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .order-detail-section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            color: #333;
            font-weight: 500;
        }
        
        .tracking-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .tracking-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            padding: 15px 0;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 20px;
            width: 12px;
            height: 12px;
            background: #28a745;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #28a745;
        }
        
        .timeline-item.pending::before {
            background: #ffc107;
            box-shadow: 0 0 0 2px #ffc107;
        }
        
        .timeline-item.inactive::before {
            background: #e9ecef;
            box-shadow: 0 0 0 2px #e9ecef;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-left: 15px;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .timeline-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .timeline-date {
            color: #999;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .orders-container {
                padding: 20px 0;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .order-card {
                padding: 20px;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-status {
                align-items: flex-start;
                flex-direction: row;
                justify-content: space-between;
                width: 100%;
            }
            
            .order-actions {
                flex-direction: column;
            }
            
            .btn-action {
                justify-content: center;
            }
        }
        
        .return-order-card {
            border-left: 4px solid #ffc107 !important;
            background: linear-gradient(135deg, #fff9e6 0%, #ffffff 100%) !important;
        }
        
        .return-indicator .alert {
            border-radius: 8px;
            border: 1px solid #ffeaa7;
        }
        
        .return-awb {
            font-size: 0.85rem;
        }
        
        .return-order-card .order-amount small {
            display: block;
            font-size: 0.8rem;
            margin-top: 2px;
        }
        
        /* Additional styling for return status timeline */
        .timeline-item.return-completed::before {
            background: #28a745;
            box-shadow: 0 0 0 2px #28a745;
        }
        
        .timeline-item.return-pending::before {
            background: #ffc107;
            box-shadow: 0 0 0 2px #ffc107;
        }
        
        .timeline-item.return-rejected::before {
            background: #dc3545;
            box-shadow: 0 0 0 2px #dc3545;
        }
        
        /* Modern unified tracking timeline */
        .flipkart-tracking-timeline {
            position: relative;
            padding: 20px;
            max-width: 100%;
            overflow: hidden;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .flipkart-timeline-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
            position: relative;
            padding-left: 0;
        }
        
        .flipkart-timeline-item:last-child {
            margin-bottom: 0;
        }
        
        /* Connecting line between items */
        .flipkart-timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 19px;
            top: 40px;
            width: 2px;
            height: calc(100% + 5px);
            background: #e0e0e0;
            z-index: 0;
            transition: background 0.5s ease;
        }
        
        /* Active line animation */
        .flipkart-timeline-item.completed:not(:last-child)::before {
            background: #26a541;
        }
        
        .flipkart-timeline-item.active:not(:last-child)::before {
            background: linear-gradient(to bottom, #26a541 0%, #ff9f00 50%, #e0e0e0 100%);
            animation: flowDown 2s ease-in-out infinite;
        }
        
        @keyframes flowDown {
            0% { background: linear-gradient(to bottom, #26a541 0%, #e0e0e0 0%, #e0e0e0 100%); }
            50% { background: linear-gradient(to bottom, #26a541 50%, #ff9f00 50%, #e0e0e0 100%); }
            100% { background: linear-gradient(to bottom, #26a541 100%, #26a541 100%, #ff9f00 100%); }
        }
        
        .timeline-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            position: relative;
            z-index: 2;
            font-size: 14px;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        /* Status-based styling */
        .flipkart-timeline-item.completed .timeline-icon {
            background: #26a541;
            color: white;
            border: 3px solid #26a541;
            box-shadow: 0 0 0 4px rgba(38, 165, 65, 0.2);
        }
        
        .flipkart-timeline-item.active .timeline-icon {
            background: #ff9f00;
            color: white;
            border: 3px solid #ff9f00;
            animation: activePulse 2s infinite;
            box-shadow: 0 0 0 4px rgba(255, 159, 0, 0.3);
        }
        
        .flipkart-timeline-item.pending .timeline-icon {
            background: #f5f5f5;
            color: #999;
            border: 3px solid #e0e0e0;
        }
        
        .flipkart-timeline-item.rejected .timeline-icon {
            background: #d32f2f;
            color: white;
            border: 3px solid #d32f2f;
            box-shadow: 0 0 0 4px rgba(211, 47, 47, 0.2);
        }
        
        @keyframes activePulse {
            0% { 
                transform: scale(1);
                box-shadow: 0 0 0 4px rgba(255, 159, 0, 0.3);
            }
            50% { 
                transform: scale(1.1);
                box-shadow: 0 0 0 8px rgba(255, 159, 0, 0.1);
            }
            100% { 
                transform: scale(1);
                box-shadow: 0 0 0 4px rgba(255, 159, 0, 0.3);
            }
        }
        
        .timeline-content-modern {
            flex: 1;
            padding-top: 4px;
            min-width: 0; /* Prevents overflow */
        }
        
        .timeline-step {
            font-size: 15px;
            font-weight: 600;
            color: #212121;
            margin-bottom: 4px;
            word-wrap: break-word;
        }
        
        .flipkart-timeline-item.completed .timeline-step {
            color: #26a541;
        }
        
        .flipkart-timeline-item.active .timeline-step {
            color: #ff9f00;
        }
        
        .flipkart-timeline-item.rejected .timeline-step {
            color: #d32f2f;
        }
        
        .timeline-desc {
            font-size: 13px;
            color: #666;
            line-height: 1.4;
            margin-bottom: 6px;
            word-wrap: break-word;
        }
        
        .flipkart-timeline-item.rejected .timeline-desc {
            color: #d32f2f;
            font-weight: 500;
        }
        
        .timeline-timestamp {
            font-size: 11px;
            color: #999;
            font-weight: 500;
        }
        
        /* Container fixes */
        .tracking-timeline {
            max-width: 100%;
            overflow: hidden;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .flipkart-tracking-timeline {
                padding: 15px;
                margin: 15px 0;
            }
            
            .flipkart-timeline-item {
                margin-bottom: 20px;
            }
            
            .timeline-icon {
                width: 32px;
                height: 32px;
                margin-right: 12px;
                font-size: 12px;
            }
            
            .flipkart-timeline-item:not(:last-child)::before {
                left: 15px;
                top: 32px;
            }
            
            .timeline-step {
                font-size: 14px;
            }
            
            .timeline-desc {
                font-size: 12px;
            }
        }
        
        .status-rejected { 
            background: #f8d7da; 
            color: #721c24; 
        }
        
    </style>
</head>
<body>
    <div class="container orders-container p-3 ">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <p class="page-subtitle">Track and manage your orders</p>
            
            <?php if ($totalOrders > 0): ?>
            <div class="orders-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= $totalOrders ?></span>
                    <span class="stat-label">Total Orders</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $currentPage ?></span>
                    <span class="stat-label">Current Page</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $totalPages ?></span>
                    <span class="stat-label">Total Pages</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($associationMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($associationMessage) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>
        
        <?php if ($guestLookupError): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($guestLookupError) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Back to Account Link -->
        <div class="mb-4">
            <a href="../index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Home
            </a>
        </div>
    
        <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($orders)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <h3 class="empty-title">No Orders Yet</h3>
            <p class="empty-subtitle">You haven't placed any orders yet. Start shopping to see your orders here!</p>
            <a href="../shop/category.php" class="btn btn-primary btn-lg">
                <i class="fas fa-shopping-cart mr-2"></i>Start Shopping
            </a>
        </div>
        <?php else: ?>
        
        <!-- Orders List -->
       <?php foreach ($orders as $order): 
        if (!isset($order['id']) || !is_numeric($order['id']) || $order['id'] <= 0) {
            error_log("ERROR: Invalid order data - missing or invalid ID: " . print_r($order, true));
            continue;
        }
        
        $orderId = (int)$order['id'];
        $orderItems = getOrderItems($orderId);
        $shippingAddress = json_decode($order['shipping_address'] ?? '{}', true);
        $orderDate = new DateTime($order['created_at']);
        
        // Check if this order has a return request
        $returnData = getOrderReturnStatus($orderId);
        $isReturnOrder = ($returnData !== null);
        
        ?>
        <div class="order-card <?= $isReturnOrder ? 'return-order-card' : '' ?>" data-order-id="<?= $orderId ?>">
            <?php if ($isReturnOrder): ?>
            <!-- Return Order Header -->
            <div class="return-indicator mb-3">
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-undo mr-2"></i>
                    <strong>Return Request:</strong> This order has been requested for return
                    <span class="float-right">
                        <span class="badge badge-warning"><?= ucfirst(str_replace('_', ' ', $returnData['return_status'])) ?></span>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="order-header">
                <div class="order-info">
                    <div class="order-number">#<?= htmlspecialchars($order['order_number']) ?></div>
                    <div class="order-date">
                        <i class="fas fa-calendar mr-1"></i>
                        Ordered on <?= $orderDate->format('M d, Y') ?> at <?= $orderDate->format('h:i A') ?>
                    </div>
                    <?php if ($isReturnOrder && $returnData['return_awb']): ?>
                    <div class="return-awb mt-1">
                        <i class="fas fa-barcode mr-1"></i>
                        <small class="text-muted">Return AWB: <?= htmlspecialchars($returnData['return_awb']) ?></small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="order-status">
                    <?php if ($isReturnOrder): ?>
                    <!-- Show Return Status Instead of Order Status -->
                    <span class="status-badge badge <?= getReturnStatusBadgeClass($returnData['return_status']) ?>">
                        Return: <?= ucfirst(str_replace('_', ' ', $returnData['return_status'])) ?>
                    </span>
                    <span class="status-badge badge <?= getPaymentStatusBadgeClass($order['payment_status']) ?>">
                        Payment: <?= ucfirst(htmlspecialchars($order['payment_status'])) ?>
                    </span>
                    <?php else: ?>
                    <!-- Normal Order Status -->
                    <span class="status-badge badge <?= getStatusBadgeClass($order['status']) ?>">
                        Order: <?= ucfirst(htmlspecialchars($order['status'])) ?>
                    </span>
                    <span class="status-badge badge <?= getPaymentStatusBadgeClass($order['payment_status']) ?>">
                        Payment: <?= ucfirst(htmlspecialchars($order['payment_status'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Items Preview -->
            <?php if (!empty($orderItems)): ?>
            <div class="order-amount">
                <?= htmlspecialchars($currencySymbol) ?><?= number_format($order['final_amount'], 2) ?>
                <?php if ($isReturnOrder): ?>
                    <?php 
                    $statusMessages = [
                        'requested' => '(Return requested)',
                        'pickup_scheduled' => '(Pickup initiated)', 
                        'collected' => '(Returned - collected)',
                        'received' => '(Return received)',
                        'processed' => '(Return processed)',
                        'rejected' => '(Return rejected)'
                    ];
                    $currentStatus = $returnData['return_status'] ?? 'requested';
                    $statusMessage = $statusMessages[$currentStatus] ?? '(Return in progress)';
                    ?>
                    <small class="text-muted"><?= $statusMessage ?></small>
                <?php endif; ?>
            </div>
            <div class="order-items-preview">
                <?php 
                $itemsShown = 0;
                foreach ($orderItems as $item): 
                    if ($itemsShown >= 3) break;
                    $itemsShown++;
                ?>
                <div class="item-preview">
                    <?= htmlspecialchars($item['product_name']) ?>
                    <?php if (!empty($item['size'])): ?>(<?= htmlspecialchars($item['size']) ?>)<?php endif; ?>
                    × <?= $item['quantity'] ?>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($orderItems) > 3): ?>
                <div class="item-preview" style="background: #e9ecef; color: #666;">
                    +<?= count($orderItems) - 3 ?> more items
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Shipping Info -->
            <?php if ($shippingAddress && is_array($shippingAddress)): ?>
            <div class="mt-2 mb-3">
                <small class="text-muted">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    <?= $isReturnOrder ? 'Return from:' : 'Delivering to:' ?>
                    <?= htmlspecialchars($shippingAddress['city'] ?? '') ?>, 
                    <?= htmlspecialchars($shippingAddress['state'] ?? '') ?> 
                    <?= htmlspecialchars($shippingAddress['pincode'] ?? '') ?>
                </small>
            </div>
            <?php endif; ?>
            
            <!-- Order Actions -->
            <div class="order-actions">
                <!-- View Details Button -->
                <button class="btn-action btn-primary-action" onclick="viewOrderDetails(<?= $orderId ?>)">
                    <i class="fas fa-eye"></i>View Details
                </button>
                
                <!-- Track Button - Different text for returns -->
                <button class="btn-action text-light" style="background: linear-gradient(to bottom right, #6C803F, #879D60, #879D60);" onclick="trackOrder(<?= $orderId ?>)">
                    <i class="fas fa-<?= $isReturnOrder ? 'undo' : 'truck' ?>"></i>
                    <?php if ($isReturnOrder): ?>
                        Track Return
                    <?php else: ?>
                        <?php if ($order['status'] === 'pending' && $order['payment_status'] === 'pending'): ?>
                            Order Status
                        <?php else: ?>
                            Track Package
                        <?php endif; ?>
                    <?php endif; ?>
                </button>
                
                <?php if (file_exists('../invoice.php')): ?>
                <button class="btn-action btn-secondary-action" onclick="downloadInvoice(<?= $orderId ?>)">
                    <i class="fas fa-file-invoice"></i>Download Invoice
                </button>
                <?php endif; ?>
                
                <?php if (strtolower($order['status']) === 'delivered' && !$isReturnOrder): ?>
                <a href="../shop/category.php" class="btn-action btn-secondary-action">
                    <i class="fas fa-redo"></i>Order Again
                </a>
                <?php endif; ?>
                
                <?php if (!$isReturnOrder && canOrderBeReturned($order)): ?>
                <button class="btn-action btn-warning text-white" onclick="initiateReturn(<?= $orderId ?>)">
                    <i class="fas fa-undo"></i>Return Order
                </button>
                <?php elseif ($isReturnOrder): ?>
                <span class="btn-action btn-info" style="cursor: default;">
                    <i class="fas fa-clock"></i>Return In Progress
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <nav aria-label="Orders pagination">
                <ul class="pagination">
                    <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    
    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading order details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tracking Modal -->
    <div class="modal fade" id="trackingModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Package Tracking</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="trackingContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading tracking information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    
    
    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const CURRENCY_SYMBOL = '<?= addslashes($currencySymbol) ?>';
        
        // ENHANCED: View Order Details with better error handling
        function viewOrderDetails(orderId) {
            console.log('Viewing order details for order ID:', orderId, 'Type:', typeof orderId);
            
            // Validate order ID on frontend
            if (!orderId || isNaN(orderId) || orderId <= 0) {
                console.error('Invalid order ID provided:', orderId);
                alert('Invalid order ID. Please refresh the page and try again.');
                return;
            }
            
            // Convert to integer for safety
            orderId = parseInt(orderId);
            
            $('#orderDetailsModal').modal('show');
            
            $.ajax({
                url: 'orders.php',
                method: 'GET',
                data: {
                    action: 'get_order_details',
                    order_id: orderId
                },
                dataType: 'json',
                timeout: 30000, // 30 second timeout
                beforeSend: function() {
                    console.log('Sending request for order ID:', orderId);
                },
                success: function(response) {
                    console.log('Order details response:', response);
                    if (response.success) {
                        displayOrderDetails(response.order, response.items, response.shipping_address);
                    } else {
                        $('#orderDetailsContent').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                ${response.message || 'Failed to load order details'}
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        orderId: orderId
                    });
                    
                    let errorMessage = 'Failed to load order details. ';
                    if (status === 'timeout') {
                        errorMessage += 'Request timed out. Please try again.';
                    } else if (xhr.status === 404) {
                        errorMessage += 'Order not found.';
                    } else if (xhr.status === 500) {
                        errorMessage += 'Server error. Please contact support.';
                    } else {
                        errorMessage += `Error: ${error} (${xhr.status})`;
                    }
                    
                    $('#orderDetailsContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            ${errorMessage}
                            <br><small>Order ID: ${orderId}, Status: ${status}</small>
                        </div>
                    `);
                }
            });
        }

        // Display Order Details in Modal
        function displayOrderDetails(order, items, shippingAddress) {
            console.log('Displaying order details:', order, items, shippingAddress);
            
            const orderDate = new Date(order.created_at);
            const updatedDate = new Date(order.updated_at);
            
            let itemsHtml = '';
            let totalItems = 0;
            
            if (items && items.length > 0) {
                items.forEach(item => {
                    totalItems += parseInt(item.quantity);
                    itemsHtml += `
                        <tr>
                            <td>
                                <div class="font-weight-bold">${escapeHtml(item.product_name)}</div>
                                ${item.size ? `<small class="text-muted">Size: ${escapeHtml(item.size)}</small>` : ''}
                            </td>
                            <td class="text-center">${item.quantity}</td>
                            <td class="text-right">${CURRENCY_SYMBOL}${parseFloat(item.product_price).toFixed(2)}</td>
                            <td class="text-right font-weight-bold">${CURRENCY_SYMBOL}${parseFloat(item.total_price).toFixed(2)}</td>
                        </tr>
                    `;
                });
            } else {
                itemsHtml = '<tr><td colspan="4" class="text-center">No items found</td></tr>';
            }

            const content = `
                <!-- Order Summary -->
                <div class="order-detail-section">
                    <h6 class="section-title">
                        <i class="fas fa-info-circle mr-2"></i>Order Information
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item">
                                <span class="detail-label">Order Number:</span>
                                <span class="detail-value font-weight-bold">#${escapeHtml(order.order_number)}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Order Date:</span>
                                <span class="detail-value">${orderDate.toLocaleDateString()} ${orderDate.toLocaleTimeString()}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Last Updated:</span>
                                <span class="detail-value">${updatedDate.toLocaleDateString()} ${updatedDate.toLocaleTimeString()}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item">
                                <span class="detail-label">Order Status:</span>
                                <span class="detail-value">
                                    <span class="badge ${getStatusBadgeClassJS(order.status)}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Payment Status:</span>
                                <span class="detail-value">
                                    <span class="badge ${getPaymentStatusBadgeClassJS(order.payment_status)}">${order.payment_status.charAt(0).toUpperCase() + order.payment_status.slice(1)}</span>
                                </span>
                            </div>
                            ${order.tracking_number ? `
                            <div class="detail-item">
                                <span class="detail-label">Tracking Number:</span>
                                <span class="detail-value font-weight-bold">${escapeHtml(order.tracking_number)}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="order-detail-section">
                    <h6 class="section-title">
                        <i class="fas fa-shopping-bag mr-2"></i>Order Items (${totalItems} items)
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Order Totals -->
                <div class="order-detail-section">
                    <h6 class="section-title">
                        <i class="fas fa-calculator mr-2"></i>Order Totals
                    </h6>
                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <div class="detail-item">
                                <span class="detail-label">Subtotal:</span>
                                <span class="detail-value">${CURRENCY_SYMBOL}${parseFloat(order.total_amount || 0).toFixed(2)}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Tax:</span>
                                <span class="detail-value">${CURRENCY_SYMBOL}${parseFloat(order.tax_amount || 0).toFixed(2)}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Shipping:</span>
                                <span class="detail-value">
                                    ${parseFloat(order.shipping_amount || 0) > 0 ? CURRENCY_SYMBOL + parseFloat(order.shipping_amount).toFixed(2) : 'FREE'}
                                </span>
                            </div>
                            ${parseFloat(order.wallet_points_used || 0) > 0 ? `
                            <div class="detail-item">
                                <span class="detail-label">Wallet Points Used:</span>
                                <span class="detail-value text-success">-${CURRENCY_SYMBOL}${parseFloat(order.wallet_points_used).toFixed(2)}</span>
                            </div>
                            ` : ''}
                            <div class="detail-item border-top pt-2">
                                <span class="detail-label font-weight-bold">Final Amount:</span>
                                <span class="detail-value font-weight-bold text-success h5">${CURRENCY_SYMBOL}${parseFloat(order.final_amount || 0).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shipping Address -->
                ${shippingAddress ? `
                <div class="order-detail-section">
                    <h6 class="section-title">
                        <i class="fas fa-map-marker-alt mr-2"></i>Shipping Address
                    </h6>
                    <div class="bg-light p-3 rounded">
                        <strong>${escapeHtml(shippingAddress.first_name || '')} ${escapeHtml(shippingAddress.last_name || '')}</strong><br>
                        ${escapeHtml(shippingAddress.address || '')}<br>
                        ${shippingAddress.apartment ? escapeHtml(shippingAddress.apartment) + '<br>' : ''}
                        ${escapeHtml(shippingAddress.city || '')}, ${escapeHtml(shippingAddress.state || '')} ${escapeHtml(shippingAddress.pincode || '')}<br>
                        ${escapeHtml(shippingAddress.country || '')}<br>
                        ${shippingAddress.phone ? `<i class="fas fa-phone mr-1"></i>${escapeHtml(shippingAddress.phone)}<br>` : ''}
                        ${shippingAddress.email ? `<i class="fas fa-envelope mr-1"></i>${escapeHtml(shippingAddress.email)}` : ''}
                    </div>
                </div>
                ` : ''}

                <!-- Action Buttons -->
                <div class="text-center mt-4">
                    ${order.tracking_number || order.shiprocket_shipment_id ? `
                    <button class="btn btn-succes mr-2 mb-3 mb-md-0" onclick="trackOrder(${order.id})">
                        <i class="fas fa-truck mr-1"></i>Track Package
                    </button>
                    ` : ''}
                    <a href="../invoice.php?order_id=${order.id}" target="_blank" class="btn btn-outline-primary mr-2">
                        <i class="fas fa-file-invoice mr-1"></i>Download Invoice
                    </a>
                    <button class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Close
                    </button>
                </div>
            `;
            
            $('#orderDetailsContent').html(content);
        }

        // ENHANCED: Track Order with better validation
        function trackOrder(orderId) {
            console.log('Tracking order ID:', orderId, 'Type:', typeof orderId);
            
            if (!orderId || isNaN(orderId) || orderId <= 0) {
                console.error('Invalid order ID provided for tracking:', orderId);
                alert('Invalid order ID. Please refresh the page and try again.');
                return;
            }
            
            orderId = parseInt(orderId);
            
            // Check if this order has a return request
            const orderCard = document.querySelector(`[data-order-id="${orderId}"]`);
            const isReturnRequested = orderCard && orderCard.classList.contains('return-order-card');
            
            $('#trackingModal').modal('show');
            
            // Determine which tracking endpoint to use
            const action = isReturnRequested ? 'track_return' : 'track_order';
            const modalTitle = isReturnRequested ? 'Return Tracking' : 'Package Tracking';
            
            // Update modal title
            $('#trackingModal .modal-title').text(modalTitle);
            
            $.ajax({
                url: 'orders.php',
                method: 'GET',
                data: {
                    action: action,
                    order_id: orderId
                },
                dataType: 'json',
                timeout: 30000,
                beforeSend: function() {
                    console.log(`Sending ${action} request for order ID:`, orderId);
                },
                success: function(response) {
                    console.log(`${action} response:`, response);
                    if (response.success && response.tracking_data) {
                        if (isReturnRequested) {
                            displayReturnTrackingInfo(response.tracking_data);
                        } else {
                            displayTrackingInfo(response.tracking_data);
                        }
                    } else {
                        displayTrackingError(response.message || 'Tracking information not available');
                    }
                },
                error: function(xhr, status, error) {
                    console.error(`${action} AJAX Error:`, {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        orderId: orderId
                    });
                    
                    let errorMessage = `Failed to load ${isReturnRequested ? 'return' : 'package'} tracking information. `;
                    if (status === 'timeout') {
                        errorMessage += 'Request timed out. Please try again.';
                    } else {
                        errorMessage += `Error: ${error}`;
                    }
                    
                    displayTrackingError(errorMessage);
                }
            });
        }

        // Update the return tracking function to use the unified display
        function displayReturnTrackingInfo(trackingData) {
            displayTrackingInfo(trackingData);
        }
                
        function createReturnTimeline(returnStatus) {
            const stages = [
                {
                    title: 'Return Requested',
                    description: 'Your return request has been submitted',
                    icon: 'fa-paper-plane',
                    status: 'completed',
                    date: ''
                }
            ];
        
            if (returnStatus === 'rejected') {
                stages.push({
                    title: 'Request Rejected',
                    description: 'Your return request has been rejected after review',
                    icon: 'fa-times-circle',
                    status: 'rejected',
                    date: ''
                });
            } else {
                // Normal progression stages
                const progressStages = [
                    {
                        title: 'Admin Review',
                        description: 'Your request is being reviewed by our team',
                        icon: 'fa-search',
                        status: returnStatus === 'requested' ? 'active' : 'completed'
                    },
                    {
                        title: 'Pickup Scheduled',
                        description: 'Pickup has been scheduled with courier partner',
                        icon: 'fa-calendar-check',
                        status: ['pickup_scheduled', 'collected', 'received', 'processed'].includes(returnStatus) ? 'completed' : 'pending'
                    },
                    {
                        title: 'Package Collected',
                        description: 'Your return package has been collected',
                        icon: 'fa-truck',
                        status: ['collected', 'received', 'processed'].includes(returnStatus) ? 'completed' : 'pending'
                    },
                    {
                        title: 'Refund Processing',
                        description: 'Refund is being processed',
                        icon: 'fa-credit-card',
                        status: returnStatus === 'processed' ? 'completed' : 'pending'
                    }
                ];
                
                stages.push(...progressStages);
            }
        
            return stages;
        }
        

        // Unified function for both order and return tracking
        function displayTrackingInfo(trackingData) {
            const isReturn = trackingData.tracking_data?.is_return || false;
            const returnStatus = trackingData.tracking_data?.return_status || '';
            
            let timelineHtml = '';
            
            if (isReturn) {
                // Return tracking - use provided timeline
                const timeline = trackingData.tracking_data?.shipment_track || [];
                timeline.forEach((track, index) => {
                    const status = track.status || 'pending';
                    timelineHtml += createTimelineItem(track, status);
                });
            } else {
                // Order tracking - create order timeline
                const timeline = createOrderTimeline(trackingData.tracking_data);
                timeline.forEach((track, index) => {
                    timelineHtml += createTimelineItem(track, track.status);
                });
            }
        
            const title = isReturn ? 'Return Tracking Timeline' : 'Order Tracking Timeline';
            const icon = isReturn ? 'fa-undo' : 'fa-route';
            
            const content = `
                <div class="order-detail-section">
                    <h6 class="section-title">
                        <i class="fas ${icon} mr-2"></i>${title}
                    </h6>
                    
                    ${isReturn && returnStatus === 'rejected' ? `
                    <div class="alert alert-danger mb-3">
                        <div class="row">
                            <div class="col-12">
                                <strong><i class="fas fa-times-circle mr-2"></i>Return Request Rejected</strong><br>
                                <small>Your return request has been reviewed and rejected. An email with details has been sent to you.</small>
                            </div>
                        </div>
                    </div>
                    ` : trackingData.tracking_data ? `
                    <div class="alert alert-info mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>${isReturn ? 'Return AWB:' : 'Tracking Number:'}</strong> ${escapeHtml(trackingData.tracking_data.awb_code || 'Pending')}<br>
                                <strong>Courier:</strong> ${escapeHtml(trackingData.tracking_data.courier_name || 'N/A')}
                            </div>
                            <div class="col-md-6">
                                <strong>Status:</strong> ${escapeHtml(trackingData.tracking_data.track_status || 'N/A')}<br>
                                <strong>Expected ${isReturn ? 'Completion:' : 'Delivery:'}</strong> ${escapeHtml(trackingData.tracking_data.edd || 'N/A')}
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <div class="flipkart-tracking-timeline">
                        ${timelineHtml}
                    </div>
                </div>
        
                <div class="text-center mt-4">
                    ${!isReturn && trackingData.tracking_data?.awb_code ? `
                    <a href="https://shiprocket.co/tracking/${trackingData.tracking_data.awb_code}" target="_blank" class="btn btn-primary mr-2">
                        <i class="fas fa-external-link-alt mr-1"></i>Track on Shiprocket
                    </a>
                    ` : ''}
                    ${isReturn && returnStatus !== 'rejected' && trackingData.tracking_data?.awb_code ? `
                    <a href="https://shiprocket.co/tracking/${trackingData.tracking_data.awb_code}" target="_blank" class="btn btn-warning mr-2">
                        <i class="fas fa-external-link-alt mr-1"></i>Track Return on Shiprocket
                    </a>
                    ` : ''}
                    <button class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Close
                    </button>
                </div>
            `;
            
            $('#trackingContent').html(content);
        }
        
        // Create timeline item HTML
        function createTimelineItem(track, status) {
            const icons = {
                'completed': 'fa-check',
                'active': 'fa-clock',
                'pending': 'fa-circle',
                'rejected': 'fa-times'
            };
            
            const icon = icons[status] || 'fa-circle';
            
            return `
                <div class="flipkart-timeline-item ${status}">
                    <div class="timeline-icon">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="timeline-content-modern">
                        <div class="timeline-step">${escapeHtml(track.current_status || track.title || 'Status Update')}</div>
                        <div class="timeline-desc">${escapeHtml(track.activity || track.description || 'Status updated')}</div>
                        ${track.date ? `<div class="timeline-timestamp">${escapeHtml(new Date(track.date).toLocaleString())}</div>` : ''}
                    </div>
                </div>
            `;
        }
        
        // Create order timeline based on status
        function createOrderTimeline(trackingData) {
            const currentStatus = trackingData?.track_status?.toLowerCase() || 'pending';
            
            const stages = [
                {
                    current_status: 'Order Confirmed',
                    activity: 'Your order has been placed and confirmed',
                    date: '',
                    status: 'completed'
                },
                {
                    current_status: 'Order Processing', 
                    activity: 'Your order is being prepared for shipment',
                    date: '',
                    status: ['pending'].includes(currentStatus) ? 'active' : 'completed'
                },
                {
                    current_status: 'Shipped',
                    activity: 'Your order has been shipped and is on the way',
                    date: '',
                    status: ['shipped', 'delivered'].includes(currentStatus) ? 'completed' : (['processing'].includes(currentStatus) ? 'active' : 'pending')
                },
                {
                    current_status: 'Out For Delivery',
                    activity: 'Your order is out for delivery',
                    date: '',
                    status: currentStatus === 'delivered' ? 'completed' : (currentStatus === 'shipped' ? 'active' : 'pending')
                },
                {
                    current_status: 'Delivered',
                    activity: 'Your order has been delivered successfully',
                    date: '',
                    status: currentStatus === 'delivered' ? 'completed' : 'pending'
                }
            ];
        
            return stages;
        }

        
        // Display Tracking Error
        function displayTrackingError(message) {
            const content = `
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h5>Tracking Not Available</h5>
                    <p class="text-muted">${escapeHtml(message)}</p>
                    <div class="mt-4">
                        <button class="btn btn-outline-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Close
                        </button>
                    </div>
                </div>
            `;
            
            $('#trackingContent').html(content);
        }

        // ENHANCED: Download Invoice with better validation
        function downloadInvoice(orderId) {
            console.log('Downloading invoice for order ID:', orderId, 'Type:', typeof orderId);
            
            // Validate order ID
            if (!orderId || isNaN(orderId) || orderId <= 0) {
                console.error('Invalid order ID for invoice:', orderId);
                alert('Invalid order ID. Please refresh the page and try again.');
                return;
            }
            
            // Convert to integer for safety
            orderId = parseInt(orderId);
            
            // Create download link
            const downloadUrl = `../invoice.php?order_id=${orderId}`;
            
            // Open in new tab
            window.open(downloadUrl, '_blank');
        }

        // Helper functions for badge classes (JavaScript versions)
        function getStatusBadgeClassJS(status) {
            switch (status.toLowerCase()) {
                case 'pending': return 'badge-warning';
                case 'processing': return 'badge-info';
                case 'shipped': return 'badge-primary';
                case 'delivered': return 'badge-success';
                case 'cancelled': return 'badge-danger';
                case 'return_requested': return 'badge-warning';
                case 'return_approved': return 'badge-info';
                case 'return_picked_up': return 'badge-primary';
                case 'refund_processed': return 'badge-success';
                default: return 'badge-secondary';
            }
        }
        
        function getPaymentStatusBadgeClassJS(status) {
            switch (status.toLowerCase()) {
                case 'pending': return 'badge-warning';
                case 'paid':
                case 'completed': return 'badge-success';
                case 'failed': return 'badge-danger';
                case 'refunded': return 'badge-info';
                default: return 'badge-secondary';
            }
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Helper function for return status badge classes (JavaScript version)
        function getReturnStatusBadgeClass(status) {
            switch (status.toLowerCase()) {
                case 'requested': return 'badge-warning';
                case 'pickup_scheduled': return 'badge-info';
                case 'collected': return 'badge-primary';
                case 'received': return 'badge-primary';
                case 'processed': return 'badge-success';
                case 'rejected': return 'badge-danger';
                default: return 'badge-secondary';
            }
        }
        
        // Debug file uploads
        function debugFileUpload() {
            $('#returnPhoto').on('change', function() {
                const file = this.files[0];
                if (file) {
                    console.log('File selected:', {
                        name: file.name,
                        size: file.size,
                        type: file.type
                    });
                    
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File is too large. Maximum size is 5MB.');
                        this.value = '';
                    }
                }
            });
        }
        
        // Call debug function when modal opens
        $('#returnModal').on('shown.bs.modal', function() {
            debugFileUpload();
        });

        // Reset modal content when closed
        $('#orderDetailsModal, #trackingModal').on('hidden.bs.modal', function () {
            $(this).find('.modal-body').html(`
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Loading...</p>
                </div>
            `);
        });

        // Initialize tooltips and add loading states
        $(document).ready(function() {
            console.log('Orders page loaded');
            
            // Initialize tooltips if Bootstrap supports it
            if (typeof $().tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }
            
            // Add loading states to buttons
            $('.btn-action').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.html();
                
                if (!$btn.hasClass('loading') && !$btn.attr('href')) {
                    $btn.addClass('loading').html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading...');
                    
                    setTimeout(() => {
                        if ($btn.hasClass('loading')) {
                            $btn.removeClass('loading').html(originalText);
                        }
                    }, 5000);
                }
            });
            
            // Debug: Log any orders on page
            console.log('Total orders found:', $('.order-card').length);
            
            // Log order IDs for debugging
            $('.order-card').each(function() {
                const orderId = $(this).data('order-id');
                console.log('Order card found with ID:', orderId, 'Type:', typeof orderId);
            });
            checkReturnStatusUpdates();
        });
        
        // Return Order Functions
        function initiateReturn(orderId) {
            if (!orderId || isNaN(orderId) || orderId <= 0) {
                alert('Invalid order ID');
                return;
            }
            
            const reasons = [
                'Quality issue',
                'Wrong item received',
                'Damaged during delivery',
                'Size/fit issue',
                'Not as described',
                'Other'
            ];
            
            let reasonSelect = '<select id="returnReason" class="form-control mb-3">';
            reasons.forEach(reason => {
                reasonSelect += `<option value="${reason}">${reason}</option>`;
            });
            reasonSelect += '</select>';
            
            const confirmHtml = `
                <div class="text-left">
                    <h6>Return Reason:</h6>
                    ${reasonSelect}
                    
                    <h6 class="mt-3">Upload Product Photo: <span class="text-danger">*</span></h6>
                    <input type="file" id="returnPhoto" class="form-control mb-3" accept="image/jpeg,image/png,image/jpg" required>
                    <small class="text-muted">Please upload a clear photo of the product you want to return (JPEG/PNG, max 5MB)</small>
                    
                    <div class="alert alert-info mt-3">
                        <strong>Return Policy:</strong><br>
                        • Returns accepted within 7 days of delivery<br>
                        • Product photo is mandatory for return processing<br>
                        • Pickup will be scheduled automatically<br>
                        • Refund will be processed by admin within 2-3 business days
                    </div>
                </div>
            `;
            
            const modalHtml = `
                <div class="modal fade" id="returnModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Confirm Return Request</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">${confirmHtml}</div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-warning" onclick="validateAndProcessReturn(${orderId})">Confirm Return</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#returnModal').remove();
            $('body').append(modalHtml);
            $('#returnModal').modal('show');
        }
        
        function validateAndProcessReturn(orderId) {
            const photoInput = document.getElementById('returnPhoto');
            
            if (!photoInput.files[0]) {
                alert('Please upload a product photo before proceeding.');
                return;
            }
            
            const file = photoInput.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (file.size > maxSize) {
                alert('Photo size must be less than 5MB. Please choose a smaller image.');
                return;
            }
            
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please upload only JPEG or PNG images.');
                return;
            }
            
            processReturn(orderId);
        }
                
        function processReturn(orderId) {
            let selected = $('#returnReason').val();
        
            // Map frontend reasons → Shiprocket approved reasons
            const reasonMap = {
                "Quality issue": "quality",
                "Wrong item received": "wrong", 
                "Damaged during delivery": "damaged",
                "Size/fit issue": "size",
                "Not as described": "incompatible",
                "Other": "other"
            };
        
            const returnReason = reasonMap[selected] || "other";
        
            const $btn = $('#returnModal .btn-warning');
            const originalText = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);
        
            // Create FormData to handle file upload
            const formData = new FormData();
            formData.append('action', 'create_return');
            formData.append('order_id', orderId);
            formData.append('return_reason', returnReason);
            
            // Get file from input
            const photoInput = document.getElementById('returnPhoto');
            if (photoInput && photoInput.files[0]) {
                formData.append('return_photo', photoInput.files[0]);
            }
        
            $.ajax({
                url: 'orders.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#returnModal').modal('hide');
                        const statusMsg = response.status ? ` (Status: ${response.status})` : '';
                        alert('✅ Return request submitted successfully!' + statusMsg + 
                              '\n\n📦 Pickup will be scheduled soon.\n💰 Refund will be processed by admin within 2-3 business days.');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + (response.message || 'Failed to process return'));
                        $btn.html(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', xhr.responseText);
                    alert('Network error. Please try again.');
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        }
        
        // Add this function to auto-refresh return status
        function checkReturnStatusUpdates() {
            // Only check if user has return orders visible
            const returnCards = document.querySelectorAll('.return-order-card');
            if (returnCards.length > 0) {
                console.log('Found return orders, enabling auto-refresh for status updates');
                // Check every 30 seconds for status updates
                setInterval(() => {
                    // Only refresh if no modals are open
                    if (!$('.modal').hasClass('show')) {
                        console.log('Auto-refreshing for return status updates...');
                        location.reload();
                    }
                }, 30000);
            }
        }
    
        // Auto-refresh page every 10 minutes to update order statuses (optional)
        setInterval(function() {
            // Only refresh if no modals are open
            if (!$('.modal').hasClass('show')) {
                console.log('Auto-refreshing orders page...');
                location.reload();
            }
        }, 600000); // 10 minutes
        
        
    </script>
    
</body>
</html>