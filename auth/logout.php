<?php
// auth/logout.php - Fixed cart preservation with proper session handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DEBUG: Add this section to see what's happening
error_log("=== LOGOUT DEBUG START ===");
error_log("User ID: " . ($_SESSION['user_id'] ?? 'none'));
error_log("Session ID before: " . session_id());
error_log("Existing guest cart: " . print_r($_SESSION['guest_cart'] ?? [], true));

// Preserve cart during logout
$cartToPreserve = [];
$shouldPreserveCart = false;

// Check if user is logged in and has items in cart
if (isset($_SESSION['user_id'])) {
    require_once '../includes/database.php';
    require_once '../includes/functions.php';
    
    $userId = $_SESSION['user_id'];
    
    try {
        // Get user's current cart from database
        $userCartItems = getCartItems($userId);
        
        if (!empty($userCartItems)) {
            $shouldPreserveCart = true;
            
            // Convert database cart to guest cart format
            foreach ($userCartItems as $item) {
                $cartKey = $item['product_id'] . '_' . ($item['size'] ?: 'no_size') . '_' . time();
                $cartToPreserve[$cartKey] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'size' => $item['size'],
                    'added_at' => time()
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error preserving cart during logout: " . $e->getMessage());
        // Continue with logout even if cart preservation fails
    }
}

// Also preserve any existing guest cart (shouldn't normally exist when logged in, but just in case)
if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
    $shouldPreserveCart = true;
    
    // Merge with existing guest cart if any
    foreach ($_SESSION['guest_cart'] as $key => $item) {
        if (!isset($cartToPreserve[$key])) {
            $cartToPreserve[$key] = $item;
        }
    }
}

error_log("Cart to preserve: " . print_r($cartToPreserve, true));
error_log("Should preserve: " . ($shouldPreserveCart ? 'YES' : 'NO'));

// FIXED SESSION HANDLING - Don't destroy session completely
// Just clear user-related data but keep the same session
unset($_SESSION['user_id']);
unset($_SESSION['user_email']);
unset($_SESSION['user_name']);
unset($_SESSION['user_google_id']);
unset($_SESSION['logged_in']);

// Clear any other user-specific session data
$keysToRemove = [];
foreach ($_SESSION as $key => $value) {
    if (strpos($key, 'user_') === 0 || $key === 'logged_in') {
        $keysToRemove[] = $key;
    }
}

foreach ($keysToRemove as $key) {
    unset($_SESSION[$key]);
}

// Set the preserved cart in the SAME session
if ($shouldPreserveCart && !empty($cartToPreserve)) {
    $_SESSION['guest_cart'] = $cartToPreserve;
}

error_log("Session ID after: " . session_id());
error_log("Final guest cart in session: " . print_r($_SESSION['guest_cart'] ?? [], true));
error_log("=== LOGOUT DEBUG END ===");

header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'message' => 'Logged out successfully',
    'cart_preserved' => $shouldPreserveCart,
    'cart_item_count' => count($cartToPreserve),
    'session_id' => session_id()  // DEBUG: Track session ID
]);
?>