<?php
// admin/api/manage-returns.php - Returns Management API
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Check admin authentication (add your admin check here)
// if (!isAdmin()) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'get_returns':
            handleGetReturns($conn);
            break;
            
        case 'get_return':
            handleGetReturn($conn);
            break;
            
        case 'get_stats':
            handleGetStats($conn);
            break;
            
        case 'update_status':
            handleUpdateStatus($conn);
            break;
            
        case 'process_return':
            handleProcessReturn($conn);
            break;
            
        case 'export_returns':
            handleExportReturns($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Returns API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

function handleGetReturns($conn) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, min(100, intval($_GET['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;
    
    // Build WHERE clause with filters
    $whereConditions = [];
    $params = [];
    
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $whereConditions[] = "(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    if (!empty($_GET['status'])) {
        $whereConditions[] = "r.return_status = ?";
        $params[] = $_GET['status'];
    }
    
    if (!empty($_GET['date_range'])) {
        switch ($_GET['date_range']) {
            case 'today':
                $whereConditions[] = "DATE(r.created_at) = CURDATE()";
                break;
            case 'week':
                $whereConditions[] = "r.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $whereConditions[] = "r.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
    }
    
    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Count total returns
    $countSql = "
        SELECT COUNT(*) as total
        FROM order_returns r
        LEFT JOIN orders o ON r.order_id = o.id
        LEFT JOIN users u ON o.user_id = u.id
        $whereClause
    ";
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalReturns = $countStmt->fetch()['total'];
    
    // Get returns with pagination - FIX: Cast to int to avoid quotes
    $sql = "
        SELECT 
            r.id,
            r.return_status,
            r.return_reason,
            r.return_awb,
            r.shiprocket_return_id,
            r.photo_path,
            r.created_at,
            r.updated_at,
            o.order_number,
            o.final_amount as order_amount,
            u.name as customer_name,
            u.email as customer_email,
            u.profile_image as customer_profile_image
        FROM order_returns r
        LEFT JOIN orders o ON r.order_id = o.id
        LEFT JOIN users u ON o.user_id = u.id
        $whereClause
        ORDER BY r.created_at DESC
        LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get items for each return
    foreach ($returns as &$return) {
        $itemsStmt = $conn->prepare("
            SELECT oi.product_name, oi.size, oi.quantity, oi.product_price 
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            JOIN order_returns r ON r.order_id = o.id 
            WHERE r.id = ?
        ");
        $itemsStmt->execute([$return['id']]);
        $return['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'returns' => $returns,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalReturns,
            'total_pages' => ceil($totalReturns / $perPage)
        ]
    ]);
}

function handleGetReturn($conn) {
    $returnId = intval($_GET['id'] ?? 0);
    
    if ($returnId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid return ID']);
        return;
    }
    
    $sql = "
        SELECT 
            r.*,
            o.order_number,
            o.final_amount as order_amount,
            o.shipping_address,
            u.name as customer_name,
            u.email as customer_email,
            u.profile_image as customer_profile_image
        FROM order_returns r
        LEFT JOIN orders o ON r.order_id = o.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE r.id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$returnId]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$return) {
        echo json_encode(['success' => false, 'message' => 'Return not found']);
        return;
    }
    
    // Get return items
    $itemsStmt = $conn->prepare("
        SELECT oi.product_name, oi.size, oi.quantity, oi.product_price 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.id = (SELECT order_id FROM order_returns WHERE id = ?)
    ");
    $itemsStmt->execute([$returnId]);
    $return['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse shipping address
    if ($return['shipping_address']) {
        $return['shipping_address'] = json_decode($return['shipping_address'], true);
    }
    
    echo json_encode([
        'success' => true,
        'return' => $return
    ]);
}

function handleGetStats($conn) {
    $stats = [];
    
    // Total returns
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM order_returns");
    $stmt->execute();
    $stats['total_returns'] = $stmt->fetch()['total'];
    
    // Pending returns (requested + pickup_scheduled)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM order_returns WHERE return_status IN ('requested', 'pickup_scheduled')");
    $stmt->execute();
    $stats['pending_returns'] = $stmt->fetch()['total'];
    
    // Completed returns
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM order_returns WHERE return_status = 'processed'");
    $stmt->execute();
    $stats['completed_returns'] = $stmt->fetch()['total'];
    
    // Rejected returns
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM order_returns WHERE return_status = 'rejected'");
    $stmt->execute();
    $stats['rejected_returns'] = $stmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

function handleUpdateStatus($conn) {
    $returnId = intval($_POST['return_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($returnId <= 0 || empty($newStatus)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        return;
    }
    
    // Validate status
    $validStatuses = ['requested', 'pickup_scheduled', 'collected', 'received', 'processed'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    $conn->beginTransaction();
    
    try {
        // Update return status
        $stmt = $conn->prepare("
            UPDATE order_returns 
            SET return_status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $returnId]);
        
        // Log status change if you have an admin logs table
        if ($notes) {
            // You can add logging here if needed
            error_log("Return #$returnId status updated to $newStatus. Notes: $notes");
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Return status updated successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function handleProcessReturn($conn) {
    $returnId = intval($_POST['return_id'] ?? 0);
    $action = $_POST['process_action'] ?? '';
    
    error_log("Processing return: ID=$returnId, Action=$action");
    
    if ($returnId <= 0 || !in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        return;
    }
    
    // Get complete return and order details
    $stmt = $conn->prepare("
        SELECT r.*, o.order_number, o.shiprocket_order_id, u.email as customer_email, u.name as customer_name
        FROM order_returns r 
        JOIN orders o ON r.order_id = o.id 
        LEFT JOIN users u ON o.user_id = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$returnId]);
    $returnData = $stmt->fetch();
    
    if (!$returnData) {
        echo json_encode(['success' => false, 'message' => 'Return not found']);
        return;
    }
    
    error_log("Return data found: " . json_encode($returnData));
    
    $conn->beginTransaction();
    
    try {
        if ($action === 'approve') {
            // APPROVE: Accept customer's return request - Cancel held points immediately
            
            error_log("Starting approval process for return ID: $returnId");
            
            // Update return status to pickup_scheduled
            $stmt = $conn->prepare("
                UPDATE order_returns 
                SET return_status = 'pickup_scheduled', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$returnId]);
            
            // CRITICAL: Cancel held points when approving the return
            $cancelResult = cancelHeldPointsForReturn($returnData['order_number']);
            error_log("Points cancellation result (APPROVE): " . json_encode($cancelResult));
            
            // Send approval notification to customer (pickup will be scheduled)
            if ($returnData['customer_email']) {
                error_log("Sending approval email to: " . $returnData['customer_email']);
                // You can create a sendReturnApprovalNotification function similar to rejection
                // For now, we'll log it
                error_log("Return approved - pickup will be scheduled for order: " . $returnData['order_number']);
            }
            
            error_log("Return approved: ID=$returnId, Held points canceled");
            $message = "Return approved successfully. Pickup will be scheduled and referral points have been canceled.";
            
        } else if ($action === 'reject') {
            // REJECT: Deny customer's return request - Keep held points, cancel return process
            
            error_log("Starting rejection process for return ID: $returnId");
            
            $shiprocketCancelled = false;
            $shiprocketMessage = '';
            
            // Try to cancel in Shiprocket if we have a return ID
            if (!empty($returnData['shiprocket_return_id'])) {
                error_log("Attempting Shiprocket cancellation for return: " . $returnData['shiprocket_return_id']);
                $shiprocketResult = cancelShiprocketReturn($returnData['shiprocket_return_id']);
                $shiprocketCancelled = $shiprocketResult['success'];
                $shiprocketMessage = $shiprocketResult['message'];
                
                error_log("Shiprocket cancellation result: " . json_encode($shiprocketResult));
            } else {
                error_log("No Shiprocket return ID found, skipping API cancellation");
            }
            
            // Update return status to rejected
            $stmt = $conn->prepare("
                UPDATE order_returns 
                SET return_status = 'rejected', updated_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$returnId]);
            
            error_log("Database update result: " . ($result ? 'SUCCESS' : 'FAILED'));
            error_log("Affected rows: " . $stmt->rowCount());
            
            // Revert original order status back to delivered
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = 'delivered' 
                WHERE id = ?
            ");
            $orderUpdateResult = $stmt->execute([$returnData['order_id']]);
            
            error_log("Order status revert result: " . ($orderUpdateResult ? 'SUCCESS' : 'FAILED'));
            
            // Send rejection notification to customer
            if ($returnData['customer_email']) {
                error_log("Sending rejection email to: " . $returnData['customer_email']);
                $emailResult = sendReturnRejectionNotification(
                    $returnData['order_id'],
                    $returnData['customer_email'],
                    $returnData['customer_name'] ?: 'Customer',
                    $returnData['order_number']
                );
                error_log("Email notification result: " . json_encode($emailResult));
            } else {
                error_log("No customer email found, skipping notification");
            }
            
            // DO NOT cancel held points when rejecting - customer doesn't get refund, referrer keeps points
            error_log("Return rejected: ID=$returnId, Held points preserved");
            
            $message = $shiprocketCancelled 
                ? "Return rejected successfully. Shiprocket return cancelled and customer notified. Referral points preserved."
                : "Return rejected successfully. Customer notified. Referral points preserved. Note: Shiprocket cancellation may have failed - check manually.";
        }
        
        $conn->commit();
        error_log("Transaction committed successfully");
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'shiprocket_cancelled' => $shiprocketCancelled ?? false
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Process return error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
    }
}


function handleExportReturns($conn) {
    // Build WHERE clause with filters (same as get_returns)
    $whereConditions = [];
    $params = [];
    
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $whereConditions[] = "(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    if (!empty($_GET['status'])) {
        $whereConditions[] = "r.return_status = ?";
        $params[] = $_GET['status'];
    }
    
    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "
        SELECT 
            r.id as 'Return ID',
            o.order_number as 'Order Number',
            u.name as 'Customer Name',
            u.email as 'Customer Email',
            r.return_reason as 'Return Reason',
            r.return_status as 'Status',
            r.return_awb as 'AWB Code',
            DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i:%s') as 'Created Date'
        FROM order_returns r
        LEFT JOIN orders o ON r.order_id = o.id
        LEFT JOIN users u ON o.user_id = u.id
        $whereClause
        ORDER BY r.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="returns_export_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    // Add headers
    if (!empty($returns)) {
        fputcsv($output, array_keys($returns[0]));
        
        // Add data
        foreach ($returns as $return) {
            fputcsv($output, $return);
        }
    }
    
    fclose($output);
    exit;
}
?>