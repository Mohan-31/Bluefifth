<?php
// admin/api/manage-coupons.php - API for coupon management
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../admin-session.php';

// Set JSON header
header('Content-Type: application/json');

// ONLY authentication check for admin access
requireAdminAuth();

$action = $_REQUEST['action'] ?? '';

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'get_coupons':
            getCoupons($conn);
            break;
            
        case 'get_coupon':
            getCoupon($conn);
            break;
            
        case 'create_coupon':
            createCoupon($conn);
            break;
            
        case 'update_coupon':
            updateCoupon($conn);
            break;
            
        case 'delete_coupon':
            deleteCoupon($conn);
            break;
            
        case 'get_stats':
            getCouponStats($conn);
            break;
            
        case 'validate_coupon':
            validateCoupon($conn);
            break;
            
        case 'export_coupons':
            exportCoupons($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Coupon API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// Get coupons with pagination and filters
function getCoupons($conn) {
    $page = intval($_GET['page'] ?? 1);
    $perPage = intval($_GET['per_page'] ?? 25);
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $perPage;
    
    // Build WHERE clause
    $whereClauses = [];
    $params = [];
    
    if (!empty($search)) {
        $whereClauses[] = "(code LIKE ? OR description LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($status)) {
        if ($status === 'active') {
            $whereClauses[] = "is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())";
        } elseif ($status === 'inactive') {
            $whereClauses[] = "is_active = 0";
        } elseif ($status === 'expired') {
            $whereClauses[] = "expires_at IS NOT NULL AND expires_at <= NOW()";
        }
    }
    
    $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
    // Get total count
    $countSQL = "SELECT COUNT(*) FROM coupons {$whereSQL}";
    $countStmt = $conn->prepare($countSQL);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn();
    
    // Get coupons
    $sql = "SELECT * FROM coupons {$whereSQL} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination
    $totalPages = ceil($totalCount / $perPage);
    
    echo json_encode([
        'success' => true,
        'coupons' => $coupons,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'per_page' => $perPage
        ]
    ]);
}

// Get single coupon
function getCoupon($conn) {
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Coupon ID is required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([$id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Coupon not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'coupon' => $coupon]);
}

// Create new coupon
function createCoupon($conn) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discountPercentage = floatval($_POST['discount_percentage'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $usageLimit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $isActive = intval($_POST['is_active'] ?? 1);
    
    // Validation
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Coupon code is required']);
        return;
    }
    
    if ($discountPercentage <= 0 || $discountPercentage >= 100) {
        echo json_encode(['success' => false, 'message' => 'Discount percentage must be between 1 and 99']);
        return;
    }
    
    // Check if code already exists
    $checkStmt = $conn->prepare("SELECT id FROM coupons WHERE code = ?");
    $checkStmt->execute([$code]);
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Coupon code already exists']);
        return;
    }
    
    // Insert coupon
    $sql = "INSERT INTO coupons (code, discount_percentage, description, usage_limit, expires_at, is_active) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$code, $discountPercentage, $description, $usageLimit, $expiresAt, $isActive]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Coupon created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create coupon']);
    }
}

// Update coupon
function updateCoupon($conn) {
    $id = intval($_POST['id'] ?? 0);
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discountPercentage = floatval($_POST['discount_percentage'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $usageLimit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $isActive = intval($_POST['is_active'] ?? 1);
    
    // Validation
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Coupon ID is required']);
        return;
    }
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Coupon code is required']);
        return;
    }
    
    if ($discountPercentage <= 0 || $discountPercentage >= 100) {
        echo json_encode(['success' => false, 'message' => 'Discount percentage must be between 1 and 99']);
        return;
    }
    
    // Check if code already exists for different coupon
    $checkStmt = $conn->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
    $checkStmt->execute([$code, $id]);
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Coupon code already exists']);
        return;
    }
    
    // Update coupon
    $sql = "UPDATE coupons SET code = ?, discount_percentage = ?, description = ?, 
            usage_limit = ?, expires_at = ?, is_active = ?, updated_at = NOW() 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$code, $discountPercentage, $description, $usageLimit, $expiresAt, $isActive, $id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Coupon updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update coupon']);
    }
}

// Delete coupon
function deleteCoupon($conn) {
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Coupon ID is required']);
        return;
    }
    
    // Check if coupon has been used in orders
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE coupon_code = (SELECT code FROM coupons WHERE id = ?)");
    $checkStmt->execute([$id]);
    $usageCount = $checkStmt->fetchColumn();
    
    if ($usageCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete coupon that has been used in orders']);
        return;
    }
    
    // Delete coupon
    $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Coupon deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete coupon']);
    }
}

// Get coupon statistics
function getCouponStats($conn) {
    // Total coupons
    $totalStmt = $conn->prepare("SELECT COUNT(*) FROM coupons");
    $totalStmt->execute();
    $totalCoupons = $totalStmt->fetchColumn();
    
    // Active coupons
    $activeStmt = $conn->prepare("SELECT COUNT(*) FROM coupons WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
    $activeStmt->execute();
    $activeCoupons = $activeStmt->fetchColumn();
    
    // Total usage
    $usageStmt = $conn->prepare("SELECT SUM(used_count) FROM coupons");
    $usageStmt->execute();
    $totalUsage = $usageStmt->fetchColumn() ?: 0;
    
    // Average discount
    $avgStmt = $conn->prepare("SELECT AVG(discount_percentage) FROM coupons WHERE is_active = 1");
    $avgStmt->execute();
    $avgDiscount = round($avgStmt->fetchColumn() ?: 0, 1);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_coupons' => $totalCoupons,
            'active_coupons' => $activeCoupons,
            'total_usage' => $totalUsage,
            'avg_discount' => $avgDiscount
        ]
    ]);
}

// Validate coupon (for checkout)
function validateCoupon($conn) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $orderAmount = floatval($_POST['order_amount'] ?? 0);
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Coupon code is required']);
        return;
    }
    
    // Get coupon details
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ?");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid coupon code']);
        return;
    }
    
    // Check if active
    if (!$coupon['is_active']) {
        echo json_encode(['success' => false, 'message' => 'This coupon is no longer active']);
        return;
    }
    
    // Check if expired
    if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'This coupon has expired']);
        return;
    }
    
    // Check usage limit
    if ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'This coupon has reached its usage limit']);
        return;
    }
    
    // Calculate discount
    $discountAmount = ($orderAmount * $coupon['discount_percentage']) / 100;
    $finalAmount = $orderAmount - $discountAmount;
    
    echo json_encode([
        'success' => true,
        'coupon' => [
            'code' => $coupon['code'],
            'discount_percentage' => $coupon['discount_percentage'],
            'discount_amount' => round($discountAmount, 2),
            'final_amount' => round($finalAmount, 2)
        ]
    ]);
}

// Export coupons
function exportCoupons($conn) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    // Build WHERE clause
    $whereClauses = [];
    $params = [];
    
    if (!empty($search)) {
        $whereClauses[] = "(code LIKE ? OR description LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($status)) {
        if ($status === 'active') {
            $whereClauses[] = "is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())";
        } elseif ($status === 'inactive') {
            $whereClauses[] = "is_active = 0";
        } elseif ($status === 'expired') {
            $whereClauses[] = "expires_at IS NOT NULL AND expires_at <= NOW()";
        }
    }
    
    $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
    // Get coupons
    $sql = "SELECT * FROM coupons {$whereSQL} ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="coupons_' . date('Y-m-d') . '.csv"');
    
    // Create CSV
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID',
        'Code',
        'Discount %',
        'Description',
        'Usage Limit',
        'Used Count',
        'Active',
        'Expires At',
        'Created At',
        'Updated At'
    ]);
    
    // CSV data
    foreach ($coupons as $coupon) {
        fputcsv($output, [
            $coupon['id'],
            $coupon['code'],
            $coupon['discount_percentage'],
            $coupon['description'],
            $coupon['usage_limit'] ?: 'Unlimited',
            $coupon['used_count'],
            $coupon['is_active'] ? 'Yes' : 'No',
            $coupon['expires_at'] ?: 'Never',
            $coupon['created_at'],
            $coupon['updated_at']
        ]);
    }
    
    fclose($output);
    exit;
}

// Function to increment coupon usage (to be called from checkout)
function incrementCouponUsage($conn, $couponCode) {
    $stmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?");
    return $stmt->execute([$couponCode]);
}
?>