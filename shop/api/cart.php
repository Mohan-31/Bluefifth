<?php
// shop/api/cart.php - Complete Cart API for both authenticated and guest users
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../auth/session.php';

// Set JSON content type
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;
$userId = $isLoggedIn ? $currentUser['id'] : null;

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = '';

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $action = $input['action'] ?? '';
    } else {
        $action = $_POST['action'] ?? '';
    }
}

// Main API router
try {
    switch ($action) {
        case 'add':
            $productId = intval($_POST['product_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);
            $size = $_POST['size'] ?? null;
            
            if ($productId <= 0 || $quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product or quantity']);
                exit;
            }
            
            // Check if user is logged in
            if (isLoggedIn()) {
                // Logged in user - add to database cart
                $currentUser = getCurrentUser();
                $result = addToCart($currentUser['id'], $productId, $quantity, $size);
                $cartSummary = getCartSummary($currentUser['id']);
            } else {
                // Guest user - add to session cart (function now in functions.php)
                $result = addToSessionCart($productId, $quantity, $size);
                $cartSummary = getSessionCartSummary();
            }
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'cart_summary' => $cartSummary
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            break;
            
        case 'update_cart':
            handleUpdateCart();
            break;
            
        case 'remove_from_cart':
            handleRemoveFromCart();
            break;
            
        case 'clear_cart':
            handleClearCart();
            break;
            
        case 'get_cart_items':
            handleGetCartItems();
            break;
            
        case 'get_cart_summary':
        case 'summary':
            handleGetCartSummary();
            break;
            
        case 'merge_guest_cart':
            handleMergeGuestCart();
            break;
            
        case 'validate_cart':
            handleValidateCart();
            break;
            
        case 'apply_coupon':
            handleApplyCoupon();
            break;
            
        case 'remove_coupon':
            handleRemoveCoupon();
            break;
            
        default:
            sendErrorResponse('Invalid action specified', 400);
            break;
    }
} catch (Exception $e) {
    error_log("Cart API Error: " . $e->getMessage());
    sendErrorResponse('Internal server error', 500);
}

// ============================================================================
// CART ACTION HANDLERS
// ============================================================================

/**
 * Handle add to cart requests
 */
function handleAddToCart() {
    global $isLoggedIn, $userId;
    
    $productId = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $size = trim($_POST['size'] ?? '');
    
    // Validate inputs
    if ($productId <= 0) {
        sendErrorResponse('Invalid product ID');
        return;
    }
    
    if ($quantity <= 0 || $quantity > 50) {
        sendErrorResponse('Invalid quantity. Must be between 1 and 50');
        return;
    }
    
    // Verify product exists and is available
    $product = getProductById($productId, false);
    if (!$product) {
        sendErrorResponse('Product not found');
        return;
    }
    
    if ($product['status'] !== 'active') {
        sendErrorResponse('Product is not available');
        return;
    }
    
    // Check stock
    if ($product['stock_quantity'] < $quantity) {
        sendErrorResponse("Only {$product['stock_quantity']} items available in stock");
        return;
    }
    
    // Validate size if provided
    if (!empty($size)) {
        $availableSizes = getProductSizes($productId);
        if (!in_array($size, $availableSizes)) {
            sendErrorResponse('Invalid size selected');
            return;
        }
    }
    
    if ($isLoggedIn) {
        // Add to database cart
        $result = addToCart($userId, $productId, $quantity, $size ?: null);
        
        if ($result['success']) {
            $cartSummary = getCartSummary($userId);
            sendSuccessResponse($result['message'], [
                'cart_summary' => $cartSummary,
                'action_taken' => $result['action'] ?? 'added'
            ]);
        } else {
            sendErrorResponse($result['message']);
        }
    } else {
        // Add to session cart
        $result = addToSessionCart($productId, $quantity, $size ?: null);
        
        if ($result['success']) {
            $cartSummary = getSessionCartSummary();
            sendSuccessResponse($result['message'], [
                'cart_summary' => $cartSummary,
                'action_taken' => 'added'
            ]);
        } else {
            sendErrorResponse($result['message']);
        }
    }
}

/**
 * Handle update cart quantity
 */
function handleUpdateCart() {
    global $isLoggedIn, $userId;
    
    if ($isLoggedIn) {
        $cartItemId = intval($_POST['cart_item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($cartItemId <= 0) {
            sendErrorResponse('Invalid cart item ID');
            return;
        }
        
        if ($quantity <= 0) {
            // Remove item if quantity is 0
            $result = removeFromCart($userId, $cartItemId);
        } else {
            // Update quantity
            $result = updateCartItem($userId, $cartItemId, $quantity);
        }
        
        if ($result['success']) {
            $cartSummary = getCartSummary($userId);
            sendSuccessResponse($result['message'], [
                'cart_summary' => $cartSummary,
                'action_taken' => $result['action'] ?? 'updated'
            ]);
        } else {
            sendErrorResponse($result['message']);
        }
    } else {
        // Handle guest cart update
        $cartKey = $_POST['cart_key'] ?? '';
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if (empty($cartKey)) {
            sendErrorResponse('Invalid cart key');
            return;
        }
        
        $result = updateSessionCartItem($cartKey, $quantity);
        
        if ($result['success']) {
            $cartSummary = getSessionCartSummary();
            sendSuccessResponse($result['message'], [
                'cart_summary' => $cartSummary,
                'action_taken' => $result['action'] ?? 'updated'
            ]);
        } else {
            sendErrorResponse($result['message']);
        }
    }
}

/**
 * Handle remove from cart
 */
function handleRemoveFromCart() {
    global $isLoggedIn, $userId;
    
    if ($isLoggedIn) {
        $cartItemId = intval($_POST['cart_item_id'] ?? 0);
        
        if ($cartItemId <= 0) {
            sendErrorResponse('Invalid cart item ID');
            return;
        }
        
        $result = removeFromCart($userId, $cartItemId);
        
        if ($result['success']) {
            $cartSummary = getCartSummary($userId);
            sendSuccessResponse($result['message'], [
                'cart_summary' => $cartSummary
            ]);
        } else {
            sendErrorResponse($result['message']);
        }
    } else {
        // Handle guest cart removal
        $cartKey = $_POST['cart_key'] ?? '';
        
        if (empty($cartKey)) {
            sendErrorResponse('Invalid cart key');
            return;
        }
        
        $result = removeFromSessionCart($cartKey);
        
        if ($result['success']) {
            $cartSummary = getSessionCartSummary();
            sendSuccessResponse($result['message'], [
                'cart_summary' => $cartSummary
            ]);
        } else {
            sendErrorResponse($result['message']);
        }
    }
}

/**
 * Handle clear entire cart
 */
function handleClearCart() {
    global $isLoggedIn, $userId;
    
    if ($isLoggedIn) {
        $result = clearCart($userId);
        
        if ($result['success']) {
            sendSuccessResponse($result['message'], [
                'cart_summary' => ['item_count' => 0, 'total_quantity' => 0, 'total_amount' => 0]
            ]);
        } else {
            sendErrorResponse($result['message']);
        }
    } else {
        // Clear session cart
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION['guest_cart']);
        
        sendSuccessResponse('Cart cleared successfully', [
            'cart_summary' => ['item_count' => 0, 'total_quantity' => 0, 'total_amount' => 0]
        ]);
    }
}

/**
 * Handle get cart items
 */
function handleGetCartItems() {
    global $isLoggedIn, $userId;
    
    if ($isLoggedIn) {
        $cartItems = getCartItems($userId);
    } else {
        $cartItems = getSessionCartItems();
    }
    
    sendSuccessResponse('Cart items retrieved successfully', [
        'cart_items' => $cartItems,
        'item_count' => count($cartItems)
    ]);
}

/**
 * Handle get cart summary
 */
function handleGetCartSummary() {
    global $isLoggedIn, $userId;
    
    if ($isLoggedIn) {
        $cartSummary = getCartSummaryWithCombo($userId);
    } else {
        $cartSummary = getSessionCartSummaryWithCombo();
    }
    
    // Calculate shipping based on combo price
    $shippingCost = 0;
    $freeShippingThreshold = 1000;
    if ($cartSummary['total'] > 0 && $cartSummary['total'] < $freeShippingThreshold) {
        $shippingCost = 50;
    }
    
    $cartSummary['shipping_cost'] = $shippingCost;
    $cartSummary['free_shipping_threshold'] = $freeShippingThreshold;
    $cartSummary['final_total'] = $cartSummary['total'] + $shippingCost;
    $cartSummary['free_shipping_eligible'] = $cartSummary['total'] >= $freeShippingThreshold;
    $cartSummary['free_shipping_remaining'] = max(0, $freeShippingThreshold - $cartSummary['total']);
    
    sendSuccessResponse('Cart summary retrieved successfully', [
        'cart_summary' => $cartSummary
    ]);
}

/**
 * Handle merge guest cart with user cart after login
 */
function handleMergeGuestCart() {
    global $isLoggedIn, $userId;
    
    if (!$isLoggedIn) {
        sendErrorResponse('User must be logged in to merge cart');
        return;
    }
    
    $result = mergeGuestCartWithUserCart($userId);
    
    if ($result) {
        $cartSummary = getCartSummary($userId);
        sendSuccessResponse('Guest cart merged successfully', [
            'cart_summary' => $cartSummary
        ]);
    } else {
        sendErrorResponse('Failed to merge guest cart');
    }
}

/**
 * Handle validate cart (check stock, prices, availability)
 */
function handleValidateCart() {
    global $isLoggedIn, $userId;
    
    $issues = [];
    $cartItems = $isLoggedIn ? getCartItems($userId) : getSessionCartItems();
    
    foreach ($cartItems as $item) {
        $product = getProductById($item['product_id'], false);
        
        if (!$product) {
            $issues[] = [
                'type' => 'product_not_found',
                'item' => $item,
                'message' => "Product '{$item['product_name']}' no longer exists"
            ];
            continue;
        }
        
        if ($product['status'] !== 'active') {
            $issues[] = [
                'type' => 'product_inactive',
                'item' => $item,
                'message' => "Product '{$item['product_name']}' is no longer available"
            ];
            continue;
        }
        
        if ($product['stock_quantity'] < $item['quantity']) {
            $issues[] = [
                'type' => 'insufficient_stock',
                'item' => $item,
                'message' => "Only {$product['stock_quantity']} of '{$item['product_name']}' available",
                'available_quantity' => $product['stock_quantity']
            ];
            continue;
        }
        
        if ($product['price'] != $item['product_price']) {
            $issues[] = [
                'type' => 'price_changed',
                'item' => $item,
                'message' => "Price of '{$item['product_name']}' has changed",
                'old_price' => $item['product_price'],
                'new_price' => $product['price']
            ];
        }
    }
    
    sendSuccessResponse('Cart validation completed', [
        'valid' => empty($issues),
        'issues' => $issues,
        'issue_count' => count($issues)
    ]);
}

/**
 * Handle apply coupon code
 */
function handleApplyCoupon() {
    global $isLoggedIn, $userId;
    
    $couponCode = trim($_POST['coupon_code'] ?? '');
    
    if (empty($couponCode)) {
        sendErrorResponse('Coupon code is required');
        return;
    }
    
    // Get cart total
    $cartSummary = $isLoggedIn ? getCartSummary($userId) : getSessionCartSummary();
    
    if ($cartSummary['total_amount'] <= 0) {
        sendErrorResponse('Cart is empty');
        return;
    }
    
    // Validate coupon (implement this function in functions.php if needed)
    $couponResult = validateCoupon($couponCode, $cartSummary['total_amount']);
    
    if ($couponResult['valid']) {
        // Store coupon in session
        $_SESSION['applied_coupon'] = [
            'code' => $couponCode,
            'discount_amount' => $couponResult['discount_amount'],
            'discount_type' => $couponResult['discount_type'],
            'applied_at' => time()
        ];
        
        sendSuccessResponse('Coupon applied successfully', [
            'coupon' => $_SESSION['applied_coupon'],
            'new_total' => max(0, $cartSummary['total_amount'] - $couponResult['discount_amount'])
        ]);
    } else {
        sendErrorResponse($couponResult['message'] ?? 'Invalid coupon code');
    }
}

/**
 * Handle remove coupon
 */
function handleRemoveCoupon() {
    unset($_SESSION['applied_coupon']);
    sendSuccessResponse('Coupon removed successfully');
}

// ============================================================================
// SESSION CART HELPER FUNCTIONS
// ============================================================================

/**
 * Update session cart item
 */
function updateSessionCartItem($cartKey, $quantity) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['guest_cart'][$cartKey])) {
        return ['success' => false, 'message' => 'Cart item not found'];
    }
    
    if ($quantity <= 0) {
        // Remove item
        unset($_SESSION['guest_cart'][$cartKey]);
        return ['success' => true, 'message' => 'Item removed from cart', 'action' => 'removed'];
    } else {
        // Update quantity
        $_SESSION['guest_cart'][$cartKey]['quantity'] = $quantity;
        return ['success' => true, 'message' => 'Cart updated successfully', 'action' => 'updated'];
    }
}

/**
 * Remove item from session cart
 */
function removeFromSessionCart($cartKey) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['guest_cart'][$cartKey])) {
        return ['success' => false, 'message' => 'Cart item not found'];
    }
    
    unset($_SESSION['guest_cart'][$cartKey]);
    return ['success' => true, 'message' => 'Item removed from cart'];
}

/**
 * Basic coupon validation (extend this as needed)
 */
function validateCoupon($couponCode, $cartTotal) {
    // Basic coupon validation - extend this based on your coupon system
    $validCoupons = [
        'WELCOME10' => ['type' => 'percentage', 'value' => 10, 'min_amount' => 500],
        'SAVE50' => ['type' => 'fixed', 'value' => 50, 'min_amount' => 200],
        'FREESHIP' => ['type' => 'shipping', 'value' => 0, 'min_amount' => 0]
    ];
    
    $couponCode = strtoupper($couponCode);
    
    if (!isset($validCoupons[$couponCode])) {
        return ['valid' => false, 'message' => 'Invalid coupon code'];
    }
    
    $coupon = $validCoupons[$couponCode];
    
    if ($cartTotal < $coupon['min_amount']) {
        return [
            'valid' => false, 
            'message' => "Minimum order amount of ₹{$coupon['min_amount']} required"
        ];
    }
    
    $discountAmount = 0;
    
    switch ($coupon['type']) {
        case 'percentage':
            $discountAmount = ($cartTotal * $coupon['value']) / 100;
            break;
        case 'fixed':
            $discountAmount = $coupon['value'];
            break;
        case 'shipping':
            $discountAmount = 50; // Free shipping value
            break;
    }
    
    return [
        'valid' => true,
        'discount_amount' => $discountAmount,
        'discount_type' => $coupon['type'],
        'message' => 'Coupon applied successfully'
    ];
}

// ============================================================================
// RESPONSE HELPER FUNCTIONS
// ============================================================================

/**
 * Send success response
 */
function sendSuccessResponse($message, $data = []) {
    $response = [
        'success' => true,
        'message' => $message,
        'timestamp' => time()
    ];
    
    if (!empty($data)) {
        $response['data'] = $data;
        // Also add data at root level for backward compatibility
        $response = array_merge($response, $data);
    }
    
    http_response_code(200);
    echo json_encode($response);
    exit;
}

/**
 * Send error response
 */
function sendErrorResponse($message, $code = 400) {
    $response = [
        'success' => false,
        'message' => $message,
        'timestamp' => time()
    ];
    
    http_response_code($code);
    echo json_encode($response);
    exit;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Validate and sanitize input
 */
function validateInput($data, $type = 'string') {
    switch ($type) {
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT);
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT);
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL);
        case 'string':
        default:
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Log API activity
 */
function logApiActivity($action, $details = '') {
    global $isLoggedIn, $userId;
    
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'user_id' => $userId ?? 'guest',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    error_log("Cart API Activity: " . json_encode($logData));
}

/**
 * Rate limiting (basic implementation)
 */
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "cart_api_rate_limit_" . $ip;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $now = time();
    $windowSize = 60; // 1 minute
    $maxRequests = 100; // Max 100 requests per minute
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Remove old timestamps
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $windowSize) {
        return ($now - $timestamp) < $windowSize;
    });
    
    // Check if limit exceeded
    if (count($_SESSION[$key]) >= $maxRequests) {
        sendErrorResponse('Rate limit exceeded. Please try again later.', 429);
        return false;
    }
    
    // Add current timestamp
    $_SESSION[$key][] = $now;
    
    return true;
}

// Apply rate limiting
checkRateLimit();

// Log the API call
if (!empty($action)) {
    logApiActivity($action, json_encode($_POST ?? $_GET ?? []));
}
?>