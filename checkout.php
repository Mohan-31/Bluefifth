<?php
session_start();
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Clean up stale coupon sessions if needed
if (isset($_SESSION['applied_coupon']) && (!isset($_POST['apply_coupon']) && !isset($_POST['remove_coupon']))) {
    // Verify coupon is still valid
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
        $stmt->execute([$_SESSION['applied_coupon']['code']]);
        if ($stmt->rowCount() == 0) {
            // Coupon no longer valid, clear it
            unset($_SESSION['applied_coupon']);
        }
    } catch (Exception $e) {
        error_log("COUPON VALIDATION ERROR: " . $e->getMessage());
    }
}

// ========================================
// DYNAMIC SETTINGS LOADING
// ========================================
$razorpayKeyId = getSetting('razorpay_key_id', 'YOUR_RAZORPAY_KEY_ID_HERE');
$razorpayKeySecret = getSetting('razorpay_key_secret', 'YOUR_RAZORPAY_KEY_SECRET_HERE');
$showRazorpayCheckout = false;
$razorpayOrderData    = null;
$razorpayCustomerId   = $_SESSION['razorpay_customer_id'] ?? null;
$taxRate = (float) getSetting('tax_rate', 18.0);
$shippingCharge = (float) getSetting('shipping_charge', 50.0);
$freeShippingThreshold = (float) getSetting('free_shipping_threshold', 500.0);
$minOrderAmount = (float) getSetting('min_order_amount', 100.0);
$siteName = getSetting('site_name', 'VELONA');
$currency = getSetting('currency', 'INR');
$currencySymbol = getSetting('currency_symbol', '₹');
$firstMonthRate = (float) getSetting('first_month_rate', 10.0);
$otherMonthsRate = (float) getSetting('other_months_rate', 5.0);
$minPointsToClaim = (int) getSetting('min_points_to_claim', 100);
$enableReferrals = (bool) getSetting('enable_referrals', true);
$emailNotifications = (bool) getSetting('email_notifications', true);
$smtpHost = getSetting('smtp_host', 'smtp.gmail.com');
$smtpPort = (int) getSetting('smtp_port', 587);
$fromEmail = getSetting('from_email', 'info@bluefifth.in');
$fromName = getSetting('from_name', 'bluefifth Team');

// User authentication
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;
$currentUser = $isLoggedIn ? getUserById($userId) : null;

// ========================================
// CART DATA PROCESSING - FIXED ORDER
// ========================================
if (!$isLoggedIn) {
    $cartItems = getSessionCartItems();
    $regularCartSummary = getSessionCartSummary();
    $balance = ['points' => 0, 'pending_points' => 0];
    $totalWalletPoints = 0;
    $userInfo = null;
} else {
    $cartItems = getCartItems($userId);
    $regularCartSummary = getCartSummary($userId);
    $balance = getWalletBalance($userId);
    $totalWalletPoints = ($balance['points'] ?? 0) + ($balance['pending_points'] ?? 0);
    $userInfo = getUserById($userId);
}

// Load saved default delivery address for auto-fill
$savedDefaultAddress = null;
if ($isLoggedIn && $userId) {
    try {
        $addrConn = getConnection();
        $addrStmt = $addrConn->prepare("SELECT * FROM customer_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1");
        $addrStmt->execute([$userId]);
        $savedDefaultAddress = $addrStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) { /* non-fatal */ }
}

// Form auto-fill: prefer saved address > users table
$fillName  = ($savedDefaultAddress['full_name']    ?? '') ?: ($userInfo['name']    ?? '');
$fillEmail = ($savedDefaultAddress['email']        ?? '') ?: ($userInfo['email']   ?? '');
$fillAddr1 = ($savedDefaultAddress['address_line'] ?? '') ?: ($userInfo['address'] ?? '');
$fillApt   = $savedDefaultAddress['apartment'] ?? '';
$fillCity  = ($savedDefaultAddress['city']    ?? '') ?: ($userInfo['city']    ?? '');
$fillState = ($savedDefaultAddress['state']   ?? '') ?: ($userInfo['state']   ?? 'TN');
$fillPin   = ($savedDefaultAddress['pincode'] ?? '') ?: ($userInfo['pincode'] ?? '');
$fillPhone = $userInfo['phone'] ?? '';
$fillFirst = $fillName ? explode(' ', trim($fillName))[0] : '';
$fillLast  = $fillName ? implode(' ', array_slice(explode(' ', trim($fillName)), 1)) : '';

$totalAmount = $regularCartSummary['total_amount'];
$cartSummary  = $regularCartSummary;

// ========================================
// COUPON HANDLING - AFTER TOTAL AMOUNT IS DEFINED
// ========================================
$appliedCoupon = null;
$couponDiscount = 0;
$couponCode = '';

// Handle coupon application via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    header('Content-Type: application/json');
    
    $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $response = ['success' => false, 'message' => ''];
    
    if (!empty($couponCode)) {
        try {
            $conn = getConnection();
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ?");
            $stmt->execute([$couponCode]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coupon) {
                $response['message'] = 'Invalid coupon code';
            } elseif (!$coupon['is_active']) {
                $response['message'] = 'This coupon is no longer active';
            } elseif ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
                $response['message'] = 'This coupon has expired';
            } elseif ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
                $response['message'] = 'This coupon has reached its usage limit';
            } else {
                $_SESSION['applied_coupon'] = $coupon;
                $couponDiscount = ($totalAmount * $coupon['discount_percentage']) / 100;
                $response['success'] = true;
                $response['coupon'] = $coupon;
                $response['discount_amount'] = $couponDiscount;
                $response['message'] = 'Coupon applied successfully!';
            }
        } catch (Exception $e) {
            error_log("Coupon error: " . $e->getMessage());
            $response['message'] = 'Error validating coupon';
        }
    } else {
        $response['message'] = 'Please enter a coupon code';
    }
    
    echo json_encode($response);
    exit;
}

// Handle coupon removal via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_coupon'])) {
    header('Content-Type: application/json');
    unset($_SESSION['applied_coupon']);
    echo json_encode(['success' => true]);
    exit;
}

/// Restore coupon from session with debugging
if (isset($_SESSION['applied_coupon'])) {
    $appliedCoupon = $_SESSION['applied_coupon'];
    $couponCode = $appliedCoupon['code'];
    $couponDiscount = ($totalAmount * $appliedCoupon['discount_percentage']) / 100;
} else {
    $appliedCoupon = null;
    $couponDiscount = 0;
    $couponCode = '';
}

// ========================================
// FINAL CALCULATIONS - SAFE DIVISION
// ========================================
$totalAmountAfterCoupon = $totalAmount;
if ($couponDiscount > 0) {
    $totalAmountAfterCoupon = $totalAmount - $couponDiscount;
}

// Store coupon data in session for invoice and order processing
if ($appliedCoupon) {
    $_SESSION['checkout_coupon_data'] = [
        'code' => $appliedCoupon['code'],
        'discount_percentage' => $appliedCoupon['discount_percentage'],
        'discount_amount' => $couponDiscount,
        'original_total' => $totalAmount,
        'discounted_total' => $totalAmountAfterCoupon
    ];
} else {
    // Clear coupon data if no coupon applied
    unset($_SESSION['checkout_coupon_data']);
}

$shippingCost = 0;
if ($totalAmountAfterCoupon < $freeShippingThreshold) {
    $shippingCost = $shippingCharge;
}

// Safe tax calculation - prevent division by zero
$grossTotal = $totalAmountAfterCoupon + $shippingCost;
if ($taxRate > -100) {
    $taxAmount = ($grossTotal * $taxRate) / (100 + $taxRate);
} else {
    $taxAmount = 0;
    error_log("Invalid tax rate: $taxRate");
}

$netSubtotal = $totalAmountAfterCoupon - $taxAmount;
$subtotal = $netSubtotal;
$finalTotalBeforePoints = $totalAmountAfterCoupon;

// ========================================
// VALIDATION CHECKS
// ========================================
if (empty($cartItems)) {
    header('Location: shop/cart.php');
    exit;
}

if ($totalAmount < $minOrderAmount) {
    header('Location: shop/cart.php?error=minimum_order&required=' . $minOrderAmount);
    exit;
}

// Validate critical variables
if (!isset($totalAmount) || $totalAmount <= 0) {
    error_log("Invalid total amount: $totalAmount");
    $error = 'Cart calculation error. Please refresh and try again.';
}

// ========================================
// ORDER PROCESSING - MISSING SECTION ADDED
// ========================================
$orderProcessed = false;
$error = null;
$pointsUsed = 0;
$finalPrice = $finalTotalBeforePoints;
$orderId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle coupon operations first
    if (isset($_POST['apply_coupon']) && !isset($_POST['checkout_submit'])) {
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    if (isset($_POST['remove_coupon']) && !isset($_POST['checkout_submit'])) {
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Process payment if it's checkout submission
    if (isset($_POST['checkout_submit']) || isset($_POST['razorpay_payment_id'])) {
        $paymentMethod = $_POST['payment_method'] ?? 'razorpay';
        
        if ($paymentMethod === 'cod') {
            $orderProcessed = processCODOrder($_POST);
        } else {
            if (isset($_POST['razorpay_payment_id'])) {
                $razorpayPaymentId = $_POST['razorpay_payment_id'];
                $razorpayOrderId = $_POST['razorpay_order_id'];
                $razorpaySignature = $_POST['razorpay_signature'];
                
                // Verify payment signature
                $generated_signature = hash_hmac('sha256', $razorpayOrderId . "|" . $razorpayPaymentId, $razorpayKeySecret);
                
                if (hash_equals($generated_signature, $razorpaySignature)) {
                    $orderProcessed = processVerifiedOrder($_POST);
                } else {
                    $error = 'Payment verification failed. Please try again.';
                }
            } else {
                createRazorpayOrder($_POST);
            }
        }
    }
}

// ========================================
// FUNCTION DEFINITIONS - WITH PROPER GLOBAL DECLARATIONS
// ========================================
function processCODOrder($formData) {
    global $userId, $cartItems, $subtotal, $taxAmount, $shippingCost, $finalPrice, $netSubtotal;
    global $orderProcessed, $orderId, $orderNumber, $totalAmount;
    global $isLoggedIn, $finalTotalBeforePoints, $totalWalletPoints;
    global $appliedCoupon, $couponDiscount, $totalAmountAfterCoupon, $error;

    $pointsToUse = intval($formData['points_to_use'] ?? 0);
    $referralCodeFromForm = trim($formData['referral_code'] ?? '');
    
    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'state', 'pincode'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty(trim($formData[$field] ?? ''))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        $error = 'Please fill in all required fields: ' . implode(', ', $missingFields);
        return false;
    }
    
    // Validate points
    if ($pointsToUse > $totalWalletPoints) {
        $error = 'Not enough points available';
        return false;
    }
    
    // Calculate final price with proper fallbacks
    $baseAmount = isset($totalAmountAfterCoupon) && $totalAmountAfterCoupon > 0 ? $totalAmountAfterCoupon : $totalAmount;
    $discountAmount = min($pointsToUse, $baseAmount);
    $finalPrice = max(0, $baseAmount - $discountAmount);
    
    // Store data for processing
    $_SESSION['checkout_data'] = $formData;
    $_SESSION['points_to_use'] = $pointsToUse;
    $_SESSION['referral_code'] = $referralCodeFromForm;
    
    return processVerifiedOrder([
        'payment_method' => 'cod',
        'cod_order' => true
    ]);
}

/**
 * Create or fetch a Razorpay Customer for Magic Checkout recognition.
 * Returns the Razorpay customer_id string, or null on failure.
 */
function getRazorpayCustomerId(string $name, string $email, string $phone,
                                string $keyId, string $keySecret): ?string {
    if (empty($phone)) return null;

    // Razorpay expects 10-digit number; strip non-digits
    $contact = preg_replace('/\D/', '', $phone);
    if (strlen($contact) === 10) {
        $contact = '91' . $contact; // add country code
    }

    $payload = json_encode([
        'name'    => $name    ?: 'Customer',
        'email'   => $email   ?: '',
        'contact' => $contact,
        'fail_existing' => '0', // return existing customer if phone already registered
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/customers');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 || $code === 201) {
        $data = json_decode($resp, true);
        return $data['id'] ?? null;
    }
    return null;
}

function createRazorpayOrder($formData) {
    global $razorpayKeyId, $razorpayKeySecret, $finalPrice, $userId, $currencySymbol;
    global $totalWalletPoints, $totalAmountAfterCoupon, $totalAmount, $error;
    global $showRazorpayCheckout, $razorpayOrderData, $razorpayCustomerId;

    // Validate and calculate final price
    $pointsToUse = intval($formData['points_to_use'] ?? 0);
    $baseAmount = isset($totalAmountAfterCoupon) && $totalAmountAfterCoupon > 0 ? $totalAmountAfterCoupon : $totalAmount;
    $discountAmount = min($pointsToUse, $baseAmount);
    $finalPrice = max(0, $baseAmount - $discountAmount);

    if ($pointsToUse > $totalWalletPoints) {
        $error = 'Not enough points available';
        return;
    }

    // Store form data
    $_SESSION['checkout_data'] = $formData;
    $_SESSION['points_to_use'] = $pointsToUse;
    $_SESSION['referral_code'] = trim($formData['referral_code'] ?? '');

    // Guard: Razorpay keys must be configured in admin settings
    if (empty($razorpayKeyId) || $razorpayKeyId === 'YOUR_RAZORPAY_KEY_ID_HERE' ||
        empty($razorpayKeySecret) || $razorpayKeySecret === 'YOUR_RAZORPAY_KEY_SECRET_HERE') {
        $error = 'Payment gateway is not configured. Please use Cash on Delivery or contact support.';
        return;
    }

    // --- Razorpay Magic Checkout: create/fetch customer for saved-payment recognition ---
    $custName  = trim(($formData['first_name'] ?? '') . ' ' . ($formData['last_name'] ?? ''));
    $custEmail = trim($formData['email']  ?? '');
    $custPhone = trim($formData['phone']  ?? '');
    $rzpCustId = getRazorpayCustomerId($custName, $custEmail, $custPhone, $razorpayKeyId, $razorpayKeySecret);
    if ($rzpCustId) {
        $_SESSION['razorpay_customer_id'] = $rzpCustId;
    }
    $razorpayCustomerId = $rzpCustId;
    // ---------------------------------------------------------------------------------

    // Create Razorpay order — attach customer_id so Magic Checkout can surface saved cards/UPI
    $orderData = [
        'amount'          => round($finalPrice * 100),
        'currency'        => 'INR',
        'receipt'         => 'order_' . time(),
        'payment_capture' => 1,
    ];
    if ($rzpCustId) {
        $orderData['customer_id'] = $rzpCustId;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $razorpayKeyId . ':' . $razorpayKeySecret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200) {
        $razorpayOrder = json_decode($response, true);
        $_SESSION['razorpay_order_id'] = $razorpayOrder['id'];
        $showRazorpayCheckout = true;
        $razorpayOrderData    = $razorpayOrder;
    } else {
        $apiError = json_decode($response, true);
        $errorMsg = $apiError['error']['description'] ?? ($curlError ?: 'Unknown error');
        error_log("Razorpay order creation failed (HTTP $httpCode): $errorMsg");
        $error = 'Payment gateway error. Please use Cash on Delivery or try again later.';
    }
}

// [processVerifiedOrder function remains the same but with proper global declarations]
function processVerifiedOrder($paymentData) {
    global $userId, $conn, $cartItems, $subtotal, $taxAmount, $shippingCost, $finalPrice, $netSubtotal;
    global $orderProcessed, $orderId, $orderNumber, $totalAmount;
    global $appliedCoupon, $couponDiscount, $isLoggedIn, $finalTotalBeforePoints, $totalWalletPoints;
    global $totalAmountAfterCoupon, $error;

    // Get stored checkout data
    $formData = $_SESSION['checkout_data'] ?? [];
    $pointsToUse = $_SESSION['points_to_use'] ?? 0;
    $referralCodeFromForm = $_SESSION['referral_code'] ?? '';

    // Handle COD orders
    $isCODOrder = isset($paymentData['cod_order']) && $paymentData['cod_order'] === true;
    $paymentMethod = $isCODOrder ? 'cod' : 'razorpay';
    $paymentStatus = $isCODOrder ? 'pending' : 'paid';

    $finalCartTotal = $totalAmount;

    // Phone-first guest identity: find/create user by phone before the transaction.
    // This ensures every order is tied to a persistent customer profile keyed on phone.
    if (!$isLoggedIn || $userId === null) {
        $prePhone = trim($formData['phone'] ?? '');
        if (!empty($prePhone)) {
            $preUser = findOrCreateUserByPhone($prePhone);
            if ($preUser) {
                // Merge any session-cart items into the user's DB cart
                mergeGuestCartWithUserCart((int)$preUser['id']);
                $userId     = (int)$preUser['id'];
                $isLoggedIn = true;
                loginUser($userId);
            }
        }
    }

    // Get cart items
    global $cartItems, $taxRate, $shippingCost;
    $cartItems = $isLoggedIn ? getCartItems($userId) : getSessionCartItems();

    // Calculate amounts using tax-inclusive method
    $cartTotal  = $finalCartTotal;
    $grossTotal = $cartTotal + $shippingCost;
    $taxAmount  = ($grossTotal * $taxRate) / (100 + $taxRate);
    $netSubtotal = $cartTotal;

    // Use coupon-adjusted amount as base for points calculation
    $baseAmount = isset($totalAmountAfterCoupon) ? $totalAmountAfterCoupon : $totalAmount;
    $discountAmount = min($pointsToUse, $baseAmount);
    $finalPrice = max(0, $baseAmount - $discountAmount);
        
    $shippingAddress = [
        'first_name' => trim($formData['first_name'] ?? ''),
        'last_name' => trim($formData['last_name'] ?? ''),
        'email' => trim($formData['email'] ?? ''),
        'phone' => trim($formData['phone'] ?? ''),
        'address' => trim($formData['address'] ?? ''),
        'apartment' => trim($formData['apartment'] ?? ''),
        'city' => trim($formData['city'] ?? ''),
        'state' => trim($formData['state'] ?? ''),
        'country' => trim($formData['country'] ?? 'IN'),
        'pincode' => trim($formData['pincode'] ?? '')
    ];
    
    try {
        $conn = getConnection();
        
        // Test connection
        if (!$conn) {
            throw new Exception('Failed to get database connection');
        }
        
        // Set PDO attributes for better error handling
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);
        
        // Start transaction
        $conn->beginTransaction();
        
        // Update the user profile with the name/email from the checkout form
        // (phone-first identity was already resolved above, before the transaction)
        if ($userId) {
            try {
                $profileName  = trim(($formData['first_name'] ?? '') . ' ' . ($formData['last_name'] ?? ''));
                $profileEmail = trim($formData['email'] ?? '');
                $conn->prepare("
                    UPDATE users
                    SET name    = COALESCE(NULLIF(?, ''), name),
                        email   = COALESCE(NULLIF(?, ''), email),
                        address = COALESCE(NULLIF(?, ''), address),
                        city    = COALESCE(NULLIF(?, ''), city),
                        state   = COALESCE(NULLIF(?, ''), state),
                        pincode = COALESCE(NULLIF(?, ''), pincode)
                    WHERE id = ?
                ")->execute([
                    $profileName,
                    $profileEmail,
                    trim($formData['address'] ?? ''),
                    trim($formData['city']    ?? ''),
                    trim($formData['state']   ?? ''),
                    trim($formData['pincode'] ?? ''),
                    $userId,
                ]);
            } catch (Exception $e) {
                error_log('Profile update error (non-fatal): ' . $e->getMessage());
            }
        }
        
        $orderNumber = 'VLN' . time() . rand(100, 999);
        
        $orderSql = "
            INSERT INTO orders
            (order_number, user_id, total_amount, tax_amount, shipping_amount, wallet_points_used, final_amount,
             shipping_address, billing_address, referral_code, coupon_code, coupon_discount_percentage, coupon_discount_amount,
             payment_method, status, payment_status, razorpay_payment_id, razorpay_order_id,
             created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $orderParams = [
            $orderNumber,
            $userId,
            $finalCartTotal,
            $taxAmount,
            $shippingCost,
            $pointsToUse,
            $finalPrice,
            json_encode($shippingAddress),
            json_encode($shippingAddress),
            $referralCodeFromForm ?: null,
            $appliedCoupon['code'] ?? null,
            $appliedCoupon['discount_percentage'] ?? null,
            $couponDiscount ?? null,
            $paymentMethod,
            'pending',
            $paymentStatus,
            $isCODOrder ? null : $paymentData['razorpay_payment_id'],
            $isCODOrder ? null : $paymentData['razorpay_order_id'],
        ];

        $orderStmt = $conn->prepare($orderSql);
        $orderInsertResult = $orderStmt->execute($orderParams);

        if (!$orderInsertResult) {
            $errorInfo = $orderStmt->errorInfo();
            throw new Exception('Failed to insert order: ' . implode(', ', $errorInfo));
        }

        // Get the inserted order ID
        $orderDbId = $conn->lastInsertId();

        // Fallback: look up by order_number if lastInsertId returns 0
        if (!$orderDbId || $orderDbId <= 0) {
            $verifyStmt = $conn->prepare("SELECT id FROM orders WHERE order_number = ? ORDER BY created_at DESC LIMIT 1");
            $verifyStmt->execute([$orderNumber]);
            $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);

            if ($verifyResult && isset($verifyResult['id'])) {
                $orderDbId = (int)$verifyResult['id'];
            } else {
                error_log("Verification query failed - no order found with number: " . $orderNumber);
                
                // Try one more check - get the latest order for this user
                $latestStmt = $conn->prepare("SELECT id, order_number FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                $latestStmt->execute([$userId]);
                $latestResult = $latestStmt->fetch(PDO::FETCH_ASSOC);
                error_log("Latest order for user: " . json_encode($latestResult));
                
                throw new Exception('Failed to retrieve order ID after insertion. Order number: ' . $orderNumber);
            }
        }
        
        // Validate we have a valid order ID
        if (!is_numeric($orderDbId) || $orderDbId <= 0) {
            throw new Exception('Invalid order ID retrieved: ' . $orderDbId);
        }
        
        error_log("✅ ORDER CREATION SUCCESS: Order ID = " . $orderDbId . ", Order Number = " . $orderNumber);
        
        unset($_SESSION['preserved_checkout_data']);

        
        // Verify order exists in database
        $doubleCheckStmt = $conn->prepare("SELECT id, order_number, user_id, final_amount FROM orders WHERE id = ?");
        $doubleCheckStmt->execute([$orderDbId]);
        $doubleCheckResult = $doubleCheckStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doubleCheckResult) {
            throw new Exception('Order verification failed - order not found after creation');
        }
        
        error_log("✅ ORDER VERIFICATION: " . json_encode($doubleCheckResult));
        
        // Increment coupon usage after successful order
        if ($appliedCoupon && !empty($appliedCoupon['code'])) {
            try {
                $stmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?");
                $stmt->execute([$appliedCoupon['code']]);
                error_log("Coupon usage incremented for: " . $appliedCoupon['code']);
                
                // Store coupon data for invoice BEFORE clearing session
                $_SESSION['order_coupon_data'] = [
                    'code' => $appliedCoupon['code'],
                    'discount_percentage' => $appliedCoupon['discount_percentage'],
                    'discount_amount' => $couponDiscount ?? 0,
                    'original_total' => $totalAmount,
                    'discounted_total' => $totalAmountAfterCoupon ?? $totalAmount
                ];
                
                // Clear coupon from checkout session after successful order
                unset($_SESSION['applied_coupon']);
                unset($_SESSION['checkout_coupon_data']);
                
            } catch (Exception $e) {
                error_log("Failed to increment coupon usage: " . $e->getMessage());
            }
        }
        
        // Create order items
        foreach ($cartItems as $index => $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                error_log("❌ CART ITEM ERROR: Invalid item at index $index: " . json_encode($item));
                continue;
            }
            
            $itemSql = "
                INSERT INTO order_items 
                (order_id, product_id, product_name, product_price, quantity, size, total_price, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $itemStmt = $conn->prepare($itemSql);
            $itemParams = [
                $orderDbId,
                $item['product_id'],
                $item['product_name'] ?? 'Unknown Product',
                $item['product_price'] ?? 0,
                $item['quantity'],
                $item['size'] ?? null,
                $item['total_price'] ?? ($item['product_price'] * $item['quantity'])
            ];
            
            $itemResult = $itemStmt->execute($itemParams);
            
            if (!$itemResult) {
                error_log("❌ ORDER ITEM ERROR: Failed to insert item: " . json_encode($item));
                error_log("Item Error Info: " . json_encode($itemStmt->errorInfo()));
                throw new Exception('Failed to insert order item');
            }
            
            error_log("✅ ORDER ITEM: Inserted item for product_id: " . $item['product_id']);
        }
        
        // Process wallet points (if any)
        if ($pointsToUse > 0) {
            error_log("Processing wallet points: " . $pointsToUse);
            
            $balance = getWalletBalance($userId);
            $walletId = ensureUserWallet($userId);
            
            if (!$walletId) {
                throw new Exception('Failed to get or create wallet for user: ' . $userId);
            }
            
            $pointsFromRegular = min($pointsToUse, $balance['points']);
            $pointsFromPending = $pointsToUse - $pointsFromRegular;
            
            if ($pointsFromRegular > 0) {
                $updateWalletStmt = $conn->prepare("UPDATE wallet SET points = points - ? WHERE user_id = ?");
                $walletResult = $updateWalletStmt->execute([$pointsFromRegular, $userId]);
                
                if (!$walletResult) {
                    throw new Exception('Failed to update wallet points');
                }
                error_log("✅ WALLET: Updated regular points: " . $pointsFromRegular);
            }
            
            if ($pointsFromPending > 0) {
                $updatePendingStmt = $conn->prepare("UPDATE wallet SET pending_points = pending_points - ? WHERE user_id = ?");
                $pendingResult = $updatePendingStmt->execute([$pointsFromPending, $userId]);
                
                if (!$pendingResult) {
                    throw new Exception('Failed to update pending wallet points');
                }
                error_log("✅ WALLET: Updated pending points: " . $pointsFromPending);
            }
            
            // Record transaction
            $transactionStmt = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, points, transaction_type, description, created_at) VALUES (?, ?, 'used', ?, NOW())");
            $transactionResult = $transactionStmt->execute([$walletId, -$pointsToUse, "Used for order {$orderNumber}"]);
            
            if ($transactionResult) {
                error_log("✅ WALLET TRANSACTION: Recorded successfully");
            } else {
                error_log("❌ WALLET TRANSACTION: Failed to record");
            }
        }
        
        // Shiprocket integration
        try {
            error_log("Attempting Shiprocket integration...");
            $shiprocketResult = autoCreateShiprocketOrder($orderDbId, $orderNumber, $shippingAddress, $cartItems);

            if ($shiprocketResult['success']) {
                $updateShiprocketStmt = $conn->prepare("
                    UPDATE orders 
                    SET shiprocket_order_id = ?, shiprocket_shipment_id = ?, tracking_number = ?, status = 'processing'
                    WHERE id = ?
                ");
                $updateShiprocketStmt->execute([
                    $shiprocketResult['order_id'],
                    $shiprocketResult['shipment_id'],
                    $shiprocketResult['tracking_number'],
                    $orderDbId
                ]);
                
                error_log("✅ SHIPROCKET: Order created successfully");
            } else {
                error_log("❌ SHIPROCKET: Failed - " . ($shiprocketResult['message'] ?? 'Unknown error'));
            }
        } catch (Exception $shipError) {
            error_log("❌ SHIPROCKET ERROR: " . $shipError->getMessage());
        }

        // Clear cart
        clearCart($userId);
        error_log("✅ CART: Cleared successfully");

        // Persist delivery address for one-click repeat checkout
        if ($userId && !empty($shippingAddress['address'])) {
            try {
                saveCustomerAddress($userId, [
                    'full_name'    => trim(($shippingAddress['first_name'] ?? '') . ' ' . ($shippingAddress['last_name'] ?? '')),
                    'phone'        => $shippingAddress['phone']     ?? '',
                    'email'        => $shippingAddress['email']     ?? '',
                    'address_line' => $shippingAddress['address']   ?? '',
                    'apartment'    => $shippingAddress['apartment'] ?? '',
                    'city'         => $shippingAddress['city']      ?? '',
                    'state'        => $shippingAddress['state']     ?? '',
                    'pincode'      => $shippingAddress['pincode']   ?? '',
                ]);
            } catch (Exception $addrEx) {
                error_log('saveCustomerAddress error: ' . $addrEx->getMessage());
            }
        }
        
        // Clean up session data
        unset($_SESSION['checkout_data'], $_SESSION['points_to_use'], $_SESSION['referral_code'], $_SESSION['razorpay_order_id']);
        
        // Final verification
        $finalVerifyStmt = $conn->prepare("SELECT id, order_number, user_id, final_amount FROM orders WHERE id = ?");
        $finalVerifyStmt->execute([$orderDbId]);
        $finalOrder = $finalVerifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$finalOrder) {
            throw new Exception('Final order verification failed');
        }
        
        error_log("✅ FINAL VERIFICATION: " . json_encode($finalOrder));
        
        // Process referral purchase - EXISTING LOGIC WITH HOLD SYSTEM ADDED
        if (isset($_SESSION['referral_code']) || isset($_COOKIE['referral_code'])) {
            $referralCode = $_SESSION['referral_code'] ?? $_COOKIE['referral_code'];
            
            if ($referralCode) {
                try {
                    error_log("Processing referral purchase for code: " . $referralCode);
                    
                    $stmt = $conn->prepare("SELECT id, user_id, created_at FROM referrals WHERE code = ?");
                    $stmt->execute([$referralCode]);
                    
                    if ($stmt->rowCount() > 0) {
                        $referral = $stmt->fetch();
                        $referralId = $referral['id'];
                        $referrerId = $referral['user_id'];
                        
                        // Don't process if customer is the referrer themselves
                        if ($referrerId != $userId) {
                            // Calculate purchase month (months since referral creation)
                            $referralCreated = new DateTime($referral['created_at']);
                            $now = new DateTime();
                            $diff = $referralCreated->diff($now);
                            $purchaseMonth = ($diff->y * 12) + $diff->m + 1;
                            
                            // Get month-wise referral rates from dynamic settings
                            $firstMonthRate = (float) getSetting('first_month_rate', 10.0);
                            $otherMonthsRate = (float) getSetting('other_months_rate', 5.0);
                            
                            // Apply month-wise rate logic
                            $earningRate = ($purchaseMonth == 1) ? $firstMonthRate : $otherMonthsRate;
                            $points = floor(($finalPrice * $earningRate) / 100);
                            
                            if ($points > 0) {
                                // HOLD SYSTEM: Calculate hold until date (7 days from now)
                                $holdUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
                                
                                // Record the referral purchase with HOLD STATUS
                                $stmt = $conn->prepare("
                                    INSERT INTO referral_purchases 
                                    (referral_id, order_id, amount, points_earned, purchase_month, earning_rate, status, hold_until, hold_status) 
                                    VALUES (?, ?, ?, ?, ?, ?, 'credited', ?, 'hold')
                                ");
                                $stmt->execute([
                                    $referralId, 
                                    $orderNumber,
                                    $finalPrice, 
                                    $points, 
                                    $purchaseMonth, 
                                    $earningRate,
                                    $holdUntil,
                                ]);
                                $purchaseId = $conn->lastInsertId();
                                
                                // HOLD SYSTEM: DO NOT ADD POINTS TO WALLET YET - They are on hold
                                // Just ensure wallet exists and log the transaction for tracking purposes
                                $walletCheck = $conn->prepare("SELECT id FROM wallet WHERE user_id = ?");
                                $walletCheck->execute([$referrerId]);
                                
                                if ($walletCheck->rowCount() > 0) {
                                    $walletRow = $walletCheck->fetch();
                                    $walletId = $walletRow['id'];
                                } else {
                                    // Create new wallet entry
                                    $walletCreate = $conn->prepare("
                                        INSERT INTO wallet (user_id, points, pending_points, total_earned) 
                                        VALUES (?, 0, 0, 0)
                                    ");
                                    $walletCreate->execute([$referrerId]);
                                    $walletId = $conn->lastInsertId();
                                }
                                
                                // Log the held transaction for tracking purposes
                                try {
                                    $stmt = $conn->prepare("
                                        INSERT INTO wallet_transactions 
                                        (wallet_id, points, transaction_type, reference_id, description, created_at) 
                                        VALUES (?, ?, 'held', ?, ?, NOW())
                                    ");
                                    $stmt->execute([
                                        $walletId, 
                                        $points, 
                                        $purchaseId, 
                                        "Referral points on hold from order {$orderNumber} (Month {$purchaseMonth} - {$earningRate}%) - Release on {$holdUntil}"
                                    ]);
                                } catch (Exception $e) {
                                    // Continue if wallet_transactions fails (table might have different structure)
                                    error_log("Wallet transaction logging failed: " . $e->getMessage());
                                }
                                
                                error_log("HOLD SYSTEM: Referral processed with HOLD - Order {$orderNumber}, Referrer ID: {$referrerId}, Points: {$points}, Month: {$purchaseMonth}, Rate: {$earningRate}%, Release on {$holdUntil}");
                                error_log("WALLET: Points are on hold - will be credited after 7 days if no return is initiated");
                                
                            } else {
                                error_log("REFERRAL: Order amount too small for points. Final price: {$finalPrice}");
                            }
                        } else {
                            error_log("REFERRAL: Skipped - customer is the referrer themselves");
                        }
                        
                    } else {
                        error_log("REFERRAL: Invalid referral code: {$referralCode}");
                    }
                } catch (Exception $e) {
                    error_log("REFERRAL ERROR: " . $e->getMessage());
                }
                
                // Clear referral codes after processing
                unset($_SESSION['referral_code']);
                if (isset($_COOKIE['referral_code'])) {
                    setcookie('referral_code', '', time() - 3600, '/');
                }
            } else {
                error_log("REFERRAL: No referral code found in session/cookie");
            }
        } else {
            error_log("REFERRAL: No referral session or cookie detected");
        }
        
        // Commit transaction
        $conn->commit();
        error_log("✅ TRANSACTION: Committed successfully");

        // Set success variables
        $orderProcessed = true;
        $orderId = $orderDbId;
        
        return true;

    } catch (Exception $e) {
        if ($conn && $conn->inTransaction()) {
            $conn->rollBack();
        }

        global $error;
        $error = 'Order processing failed: ' . $e->getMessage();
        error_log("Order creation error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        return false;
    }
}

// If order processed successfully, show thank you page
if ($orderProcessed) {
    // Get the shipping address from session for email
    $shippingAddress = $_SESSION['checkout_data'] ?? [];
    
    // If session was cleared, reconstruct customer data from what we stored during order processing
    if (empty($shippingAddress) && $orderProcessed) {
        // Try to get customer data from the order that was just created
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT shipping_address FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $orderShippingData = $stmt->fetchColumn();
            if ($orderShippingData) {
                $shippingAddress = json_decode($orderShippingData, true) ?: [];
            }
        } catch (Exception $e) {
            error_log("Could not retrieve shipping address for email: " . $e->getMessage());
        }
    }
    
    $pointsToUse = $_SESSION['points_to_use'] ?? 0;
    
    // Send order confirmation email using your existing SendinblueMailer
    try {
        require_once 'includes/sendinblue-mailer.php';
        
        // Get API key from settings
        $sendinblueApiKey = getSetting('sendinblue_api_key');
        $sendinblueFromEmail = getSetting('sendinblue_from_email', 'info@bluefifth.in');
        $sendinblueFromName = getSetting('sendinblue_from_name', 'Bluefifth Team');
        
        if ($sendinblueApiKey && $sendinblueApiKey !== 'YOUR_API_KEY_HERE') {
            // Initialize mailer
            $mailer = new SendinblueMailer($sendinblueApiKey, $sendinblueFromEmail, $sendinblueFromName);
            
            // Prepare customer data - FIXED FOR BOTH LOGGED-IN AND GUEST USERS
            $customerName = ($shippingAddress['first_name'] ?? 'Customer') . ' ' . ($shippingAddress['last_name'] ?? '');
            $customerEmail = $shippingAddress['email'] ?? ($userInfo['email'] ?? 'customer@example.com');
            
            // Ensure we have valid email for guests - get from form data if shipping address is empty
            if (empty($customerEmail) || $customerEmail === 'customer@example.com') {
                $formData = $_SESSION['checkout_data'] ?? [];
                $customerEmail = $formData['email'] ?? 'customer@example.com';
                
                // Also update customer name if needed
                if ($customerName === 'Customer ' || empty(trim($customerName))) {
                    $customerName = ($formData['first_name'] ?? 'Customer') . ' ' . ($formData['last_name'] ?? '');
                }
            }
            
            // Create order confirmation email content
            $subject = "Order Confirmed - {$orderNumber} | " . getSetting('site_name', 'Velona');
            
            // Create items HTML for email
            $itemsHtml = '';
            $totalItems = 0;
            foreach ($cartItems as $item) {
                $totalItems += $item['quantity'];
                $itemsHtml .= "
                    <tr style='border-bottom: 1px solid #eee;'>
                        <td style='padding: 15px; vertical-align: top;'>
                            <div style='font-weight: 600; color: #333; margin-bottom: 5px;'>{$item['product_name']}</div>
                            " . ($item['size'] ? "<div style='font-size: 12px; color: #666;'>Size: {$item['size']}</div>" : "") . "
                        </td>
                        <td style='padding: 15px; text-align: center; color: #666;'>{$item['quantity']}</td>
                        <td style='padding: 15px; text-align: right; color: #333; font-weight: 500;'>{$currencySymbol}" . number_format($item['product_price'], 2) . "</td>
                        <td style='padding: 15px; text-align: right; color: #333; font-weight: 600;'>{$currencySymbol}" . number_format($item['total_price'], 2) . "</td>
                    </tr>
                ";
            }
            
            // Create order confirmation HTML email
            $emailHtml = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Order Confirmation</title>
            </head>
            <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f4f4f4;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff;'>
                    <!-- Header -->
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;'>
                        <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;'>Thank You for Your Order!</h1>
                        <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;'>Your order has been confirmed and is being processed</p>
                    </div>
                    
                    <!-- Order Summary -->
                    <div style='padding: 30px;'>
                        <div style='text-align: center; margin-bottom: 30px;'>
                            <div style='display: inline-block; background: #f8f9fa; padding: 20px 30px; border-radius: 8px; border: 2px solid #e9ecef;'>
                                <div style='color: #666; font-size: 14px; margin-bottom: 5px;'>Order Number</div>
                                <div style='color: #667eea; font-size: 24px; font-weight: 700;'>{$orderNumber}</div>
                            </div>
                        </div>
                        
                        <div style='background: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 30px;'>
                            <h2 style='color: #333; margin: 0 0 20px 0; font-size: 18px;'>💰 Order Summary</h2>
                            
                            <!-- Hidden Subtotal (before tax) -->
                            <div class='d-none' style='display: none;'>
                                <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                    <span style='color: #666;'>Subtotal (before tax):</span>
                                    <span style='color: #333; font-weight: 500;'>{$currencySymbol}" . number_format($totalAmount - $taxAmount, 2) . "</span>
                                </div>
                            </div>
                            
                            " . (isset($appliedCoupon) && $appliedCoupon && $couponDiscount > 0 ? "
                            <!-- Coupon Discount in Email -->
                            <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                <span style='color: #dc3545; font-weight: 600;'>💰 Coupon Discount (" . htmlspecialchars($appliedCoupon['code']) . " - " . $appliedCoupon['discount_percentage'] . "%):</span>
                                <span style='color: #dc3545; font-weight: 600;'>-{$currencySymbol}" . number_format($couponDiscount, 2) . "</span>
                            </div>
                            " : "") . "
                            
                            <!-- Hidden Tax -->
                            <div class='d-none' style='display: none;'>
                                " . ($taxAmount > 0 ? "
                                <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                    <span style='color: #666;'>Tax ({$taxRate}%):</span>
                                    <span style='color: #333; font-weight: 500;'>{$currencySymbol}" . number_format($taxAmount, 2) . "</span>
                                </div>
                                " : "") . "
                            </div>
                            
                            " . ($shippingCost > 0 ? "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                <span style='color: #666;'>Shipping:</span>
                                <span style='color: #333; font-weight: 500;'>{$currencySymbol}" . number_format($shippingCost, 2) . "</span>
                            </div>
                            " : "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                <span style='color: #666;'>Shipping:</span>
                                <span style='color: #28a745; font-weight: 500;'>FREE</span>
                            </div>
                            ") . "
                            
                            " . ($pointsToUse > 0 ? "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                <span style='color: #666;'>💰 Wallet Points Used:</span>
                                <span style='color: #28a745; font-weight: 500;'>-{$currencySymbol}" . number_format($pointsToUse, 2) . "</span>
                            </div>
                            " : "") . "
                            
                            <hr style='border: none; border-top: 1px solid #dee2e6; margin: 15px 0;'>
                            <div style='display: flex; justify-content: space-between; font-size: 18px;'>
                                <span style='color: #333; font-weight: 600;'>Total Paid:</span>
                                <span style='color: #28a745; font-weight: 700;'>{$currencySymbol}" . number_format($finalPrice, 2) . "</span>
                            </div>
                        </div>
                    </div>
                        
                        <!-- Order Items -->
                        <div style='margin-bottom: 30px;'>
                            <h2 style='color: #333; margin: 0 0 20px 0; font-size: 18px;'>📦 Order Items</h2>
                            <table style='width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #eee; border-radius: 8px; overflow: hidden;'>
                                <thead>
                                    <tr style='background: #f8f9fa;'>
                                        <th style='padding: 15px; text-align: left; color: #333; font-weight: 600; border-bottom: 1px solid #eee;'>Product</th>
                                        <th style='padding: 15px; text-align: center; color: #333; font-weight: 600; border-bottom: 1px solid #eee;'>Qty</th>
                                        <th style='padding: 15px; text-align: right; color: #333; font-weight: 600; border-bottom: 1px solid #eee;'>Price</th>
                                        <th style='padding: 15px; text-align: right; color: #333; font-weight: 600; border-bottom: 1px solid #eee;'>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$itemsHtml}
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Shipping Address -->
                        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                            <h3 style='color: #333; margin-bottom: 15px; font-size: 16px;'>📦 Shipping Address</h3>
                            <div style='color: #555; line-height: 1.6;'>
                                <strong>" . ($shippingAddress['first_name'] ?? 'Customer') . " " . ($shippingAddress['last_name'] ?? '') . "</strong><br>
                                " . ($shippingAddress['address'] ?? 'Address not provided') . "<br>
                                " . (!empty($shippingAddress['apartment']) ? "{$shippingAddress['apartment']}<br>" : "") . "
                                " . ($shippingAddress['city'] ?? 'City') . ", " . ($shippingAddress['state'] ?? 'State') . " " . ($shippingAddress['pincode'] ?? 'PIN') . "<br>
                                " . ($shippingAddress['country'] ?? 'IN') . "<br>
                                📞 " . ($shippingAddress['phone'] ?? 'Phone not provided') . "
                            </div>
                        </div>
                        
                        <!-- What's Next -->
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; margin: 30px 0;'>
                            <h3 style='margin: 0 0 15px 0; font-size: 18px;'>🚀 What's Next?</h3>
                            <ul style='margin: 0; padding-left: 20px; line-height: 1.8;'>
                                <li>We'll prepare your order within 24 hours</li>
                                <li>You'll receive a tracking number once shipped</li>
                                <li>Estimated delivery: 3-7 business days</li>
                                <li>Free shipping on orders above {$currencySymbol}500</li>
                            </ul>
                        </div>
                        
                        <!-- Action Button -->
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='" . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}/invoice.php?order_id={$orderId}' 
                               style='display: inline-block; background: #28a745; color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: 600; margin: 10px;'>
                                📄 Download Invoice
                            </a>
                            <a href='" . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}/account/orders.php' 
                               style='display: inline-block; background: #667eea; color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: 600; margin: 10px;'>
                                🚛 Track Order
                            </a>
                        </div>
                        
                        <!-- Contact Info -->
                        <div style='text-align: center; padding: 20px 0; border-top: 1px solid #eee; margin-top: 30px;'>
                            <p style='color: #666; margin: 0 0 10px 0; font-size: 14px;'>Need help with your order?</p>
                            <p style='color: #333; margin: 0; font-weight: 500;'>
                                📧 <a href='mailto:" . getSetting('contact_email', 'contact@velona.com') . "' style='color: #667eea; text-decoration: none;'>" . getSetting('contact_email', 'contact@velona.com') . "</a> | 
                                📞 " . getSetting('contact_phone', '+91 9876543210') . "
                            </p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #eee;'>
                        <p style='color: #666; margin: 0; font-size: 14px;'>
                            © " . date('Y') . " " . getSetting('site_name', 'Velona') . ". All rights reserved.<br>
                            Thank you for shopping with us!
                        </p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // Send the email
            $emailSent = $mailer->sendEmail($customerEmail, $customerName, $subject, $emailHtml);
            
            if ($emailSent) {
                error_log("Order confirmation email sent successfully to {$customerEmail}");
            } else {
                error_log("Failed to send order confirmation email to {$customerEmail}");
            }
        }
    } catch (Exception $e) {
        error_log("Order confirmation email error: " . $e->getMessage());
    }
    
// Ensure all email variables are properly set
$emailSubtotal = $totalAmount;
$emailTaxExclusiveSubtotal = $emailSubtotal - $taxAmount;
    ?>

    <!doctype html>
    <html lang="en">
    <head>
      <link rel="stylesheet" href="assets/css/style.css">
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
      <title>Order Confirmed - <?= htmlspecialchars($siteName) ?></title>
      
      <style>
        body {
          font-family: 'Poppins', sans-serif;
          background: linear-gradient(135deg, #f0f4ff 0%, #fff5fb 100%);
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 20px 0;
        }

        .ty-card {
          background: white;
          border-radius: 28px;
          padding: 3rem 2.5rem;
          text-align: center;
          box-shadow: 0 24px 64px rgba(0,0,0,0.12);
          position: relative;
          overflow: visible;
          max-width: 520px;
          width: 100%;
          margin: 0 auto;
        }

        /* ── Animated SVG checkmark ── */
        .success-icon-wrap {
          width: 110px;
          height: 110px;
          margin: 0 auto 1.6rem;
          position: relative;
        }

        .check-svg {
          width: 110px;
          height: 110px;
          filter: drop-shadow(0 6px 18px rgba(40,167,69,0.35));
        }

        .check-circle-bg {
          fill: #e8f5e9;
          stroke: none;
        }

        .check-ring {
          fill: none;
          stroke: #28a745;
          stroke-width: 5;
          stroke-linecap: round;
          stroke-dasharray: 314;
          stroke-dashoffset: 314;
          transform-origin: 55px 55px;
          transform: rotate(-90deg);
          animation: draw-ring 0.65s cubic-bezier(0.4,0,0.2,1) 0.15s forwards;
        }

        .check-tick {
          fill: none;
          stroke: #28a745;
          stroke-width: 5.5;
          stroke-linecap: round;
          stroke-linejoin: round;
          stroke-dasharray: 55;
          stroke-dashoffset: 55;
          animation: draw-tick 0.35s ease 0.75s forwards;
        }

        @keyframes draw-ring {
          to { stroke-dashoffset: 0; }
        }
        @keyframes draw-tick {
          to { stroke-dashoffset: 0; }
        }

        /* ── Pop-scale on arrival ── */
        .ty-card {
          animation: card-pop 0.5s cubic-bezier(0.34,1.56,0.64,1) forwards;
          transform: scale(0.9);
          opacity: 0;
        }
        @keyframes card-pop {
          to { transform: scale(1); opacity: 1; }
        }

        /* ── Typography ── */
        .ty-title {
          font-size: 2.2rem;
          font-weight: 700;
          color: #1a1a2e;
          margin-bottom: 0.4rem;
          letter-spacing: -0.5px;
        }

        .ty-sub {
          font-size: 1rem;
          color: #6c757d;
          margin-bottom: 1.6rem;
        }

        /* ── Order summary card ── */
        .order-card {
          background: linear-gradient(135deg, #e8f5e9 0%, #f0fff4 100%);
          border: 1.5px solid #c8e6c9;
          border-radius: 18px;
          padding: 1.5rem 1.2rem;
          margin: 0 0 1.6rem;
        }

        .order-num {
          font-size: 1rem;
          font-weight: 600;
          color: #004AAD;
          margin-bottom: 0.4rem;
          letter-spacing: 0.3px;
        }

        .order-amt {
          font-size: 2.2rem;
          font-weight: 700;
          color: #1e7e34;
          line-height: 1.1;
        }

        .order-meta {
          font-size: 0.82rem;
          color: #888;
          margin-top: 0.6rem;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 0.4rem;
        }

        .order-meta i { color: #28a745; }

        /* ── Action buttons ── */
        .ty-actions {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 0.7rem;
          margin-bottom: 1.6rem;
        }

        .ty-btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          gap: 0.45rem;
          padding: 0.75rem 1rem;
          border-radius: 50px;
          font-size: 0.88rem;
          font-weight: 600;
          text-decoration: none;
          transition: transform 0.2s ease, box-shadow 0.2s ease;
          border: none;
          cursor: pointer;
          white-space: nowrap;
        }

        .ty-btn:hover { transform: translateY(-3px); text-decoration: none; }

        .ty-btn-home {
          grid-column: 1 / -1;
          background: linear-gradient(135deg, #004AAD, #0066cc);
          color: white;
        }
        .ty-btn-home:hover  { box-shadow: 0 10px 24px rgba(0,74,173,0.35); color: white; }

        .ty-btn-shop {
          background: #f5f5f5;
          color: #333;
          border: 1.5px solid #e0e0e0;
        }
        .ty-btn-shop:hover  { background: #ebebeb; color: #333; }

        .ty-btn-track {
          background: #f5f5f5;
          color: #333;
          border: 1.5px solid #e0e0e0;
        }
        .ty-btn-track:hover { background: #ebebeb; color: #333; }

        .ty-btn-invoice {
          grid-column: 1 / -1;
          background: linear-gradient(135deg, #28a745, #20c997);
          color: white;
        }
        .ty-btn-invoice:hover { box-shadow: 0 10px 24px rgba(40,167,69,0.35); color: white; }

        /* ── What's next ── */
        .whats-next {
          border-top: 1px solid #f0f0f0;
          padding-top: 1.4rem;
        }

        .whats-next-title {
          font-size: 0.85rem;
          font-weight: 700;
          color: #333;
          text-transform: uppercase;
          letter-spacing: 0.8px;
          margin-bottom: 0.8rem;
        }

        .next-list {
          list-style: none;
          padding: 0;
          margin: 0;
          display: inline-block;
          text-align: left;
        }

        .next-list li {
          font-size: 0.83rem;
          color: #666;
          padding: 0.28rem 0;
          display: flex;
          align-items: center;
          gap: 0.55rem;
        }

        .next-list li i { color: #28a745; width: 14px; flex-shrink: 0; }

        @media (max-width: 500px) {
          .ty-card    { padding: 2rem 1.1rem; border-radius: 20px; }
          .ty-title   { font-size: 1.7rem; }
          .ty-actions { grid-template-columns: 1fr; }
          .ty-btn-home, .ty-btn-invoice { grid-column: 1; }
          .order-amt  { font-size: 1.8rem; }
        }
      </style>
    </head>
    <body>
      <!-- confetti renders on its own canvas — no extra DOM needed -->

      <div class="container">
        <div class="row justify-content-center">
          <div class="col-12 px-3">
            <div class="ty-card">

              <!-- Animated SVG checkmark -->
              <div class="success-icon-wrap">
                <svg class="check-svg" viewBox="0 0 110 110" xmlns="http://www.w3.org/2000/svg">
                  <circle class="check-circle-bg" cx="55" cy="55" r="50"/>
                  <circle class="check-ring" cx="55" cy="55" r="50"/>
                  <polyline class="check-tick" points="32,57 47,72 78,38"/>
                </svg>
              </div>

              <h1 class="ty-title">Thank You!</h1>
              <p class="ty-sub">Your order has been successfully placed and confirmed.</p>

              <!-- Order card -->
              <div class="order-card">
                <div class="order-num">Order #<?= htmlspecialchars($orderNumber) ?></div>
                <div class="order-amt">₹<?= number_format($finalPrice, 2) ?></div>
                <?php if ($pointsToUse > 0): ?>
                <div class="order-meta">
                  <i class="fas fa-wallet"></i>
                  Wallet points used: ₹<?= number_format($pointsToUse, 2) ?>
                </div>
                <?php endif; ?>
                <div class="order-meta">
                  <i class="fas fa-clock"></i>
                  Confirmation email sent with tracking details
                </div>
              </div>

              <!-- Action buttons -->
              <div class="ty-actions">
                <a href="index.php" class="ty-btn ty-btn-home">
                  <i class="fas fa-home"></i> Back to Home
                </a>
                <a href="shop/category.php" class="ty-btn ty-btn-shop">
                  <i class="fas fa-shopping-bag"></i> Shop More
                </a>
                <a href="account/orders.php" class="ty-btn ty-btn-track">
                  <i class="fas fa-truck"></i> Track Order
                </a>
                <a href="invoice.php?order_id=<?= $orderId ?>" target="_blank" class="ty-btn ty-btn-invoice">
                  <i class="fas fa-file-invoice"></i> Download Invoice
                </a>
              </div>

              <!-- What's next -->
              <div class="whats-next">
                <p class="whats-next-title">What happens next?</p>
                <ul class="next-list">
                  <li><i class="fas fa-envelope"></i> Order confirmation email sent</li>
                  <li><i class="fas fa-box"></i> We'll prepare your order within 24 hours</li>
                  <li><i class="fas fa-shipping-fast"></i> Free shipping on orders above <?= htmlspecialchars($currencySymbol) ?><?= number_format($freeShippingThreshold, 0) ?></li>
                  <li><i class="fas fa-headset"></i> Need help? Contact info@bluefifth.in</li>
                </ul>
              </div>

            </div><!-- /.ty-card -->
          </div>
        </div>
      </div>

      <!-- canvas-confetti CDN -->
      <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          var COLORS = ['#004AAD','#28a745','#FFD700','#ff6b6b','#4ecdc4','#a29bfe','#fd79a8','#f9ca24'];

          function fire(opts) {
            confetti(Object.assign({ zIndex: 9999, colors: COLORS }, opts));
          }

          // Initial big burst — fires from both lower corners (Flipkart/Amazon style)
          setTimeout(function () {
            fire({ particleCount: 60, angle: 60,  spread: 55, origin: { x: 0,   y: 0.75 } });
            fire({ particleCount: 60, angle: 120, spread: 55, origin: { x: 1,   y: 0.75 } });
            fire({ particleCount: 80, spread: 80, origin: { x: 0.5, y: 0.65 }, startVelocity: 40 });
          }, 550);

          // Gold coin burst
          setTimeout(function () {
            confetti({
              particleCount: 70,
              spread: 90,
              origin: { x: 0.5, y: 0.55 },
              colors: ['#FFD700','#FFA500','#FF8C00','#FFEC8B'],
              shapes: ['circle'],
              scalar: 1.4,
              gravity: 0.7,
              zIndex: 9999
            });
          }, 900);

          // Second side-burst for extra drama
          setTimeout(function () {
            fire({ particleCount: 40, angle: 60,  spread: 45, origin: { x: 0,   y: 0.7 } });
            fire({ particleCount: 40, angle: 120, spread: 45, origin: { x: 1,   y: 0.7 } });
          }, 1500);
        });
      </script>
    </body>
    </html>
    <?php
    exit;
}
?>

<!doctype html>
<html lang="en">
<head>
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css"
    integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <title>Checkout - <?= htmlspecialchars($siteName) ?></title>
  <style>
    .checkout-container {
      padding: 30px 0;
    }
    .checkout-title {
      font-size: 28px;
      font-weight: 500;
      margin-bottom: 30px;
    }
    .section-title {
      font-size: 20px;
      font-weight: 500;
      margin-bottom: 20px;
      color: #171717;
    }
    .form-control {
      padding: 10px 12px;
      border-radius: 4px;
      border: 1px solid #ddd;
      margin-bottom: 15px;
    }
    .form-group label {
      margin-bottom: 8px;
      font-weight: 500;
    }
    .checkout-sidebar {
      background-color: #f8f8f8;
      padding: 20px;
      border-radius: 8px;
    }
    .order-summary-title {
      font-size: 18px;
      font-weight: 500;
      margin-bottom: 20px;
    }
    .order-summary-line {
      display: flex;
      justify-content: space-between;
      margin-bottom: 15px;
    }
    .order-summary-total {
      font-weight: 600;
      font-size: 18px;
      margin-top: 10px;
      padding-top: 15px;
      border-top: 1px solid #ddd;
    }
    .checkout-product {
      display: flex;
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid #ddd;
    }
    .checkout-product-img {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 4px;
      margin-right: 15px;
    }
    .checkout-product-details {
      flex: 1;
    }
    .checkout-product-title {
      font-weight: 500;
      margin-bottom: 5px;
      font-size: 14px;
    }
    .checkout-product-variant {
      font-size: 12px;
      color: #777;
      margin-bottom: 0;
    }
    .checkout-product-price {
      font-weight: 500;
      font-size: 14px;
    }
    .item-quantity-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background-color: #879D60;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
    }
    
    /* Wallet Points Section Styling */
    .wallet-section {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
    }
    
    .wallet-section h3 {
      margin-bottom: 15px;
      font-size: 18px;
    }
    
    .wallet-balance {
      background: rgba(255,255,255,0.1);
      border-radius: 6px;
      padding: 15px;
      margin: 15px 0;
    }
    
    .wallet-balance .balance-amount {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .wallet-balance .balance-note {
      font-size: 14px;
      opacity: 0.9;
    }
    
    .wallet-slider-container {
      margin: 20px 0;
    }
    
    .wallet-slider-container label {
      display: block;
      margin-bottom: 10px;
      font-weight: 500;
    }
    
    .wallet-slider {
      width: 100%;
      height: 8px;
      border-radius: 5px;
      background: rgba(255,255,255,0.3);
      outline: none;
      -webkit-appearance: none;
    }
    
    .wallet-slider::-webkit-slider-thumb {
      -webkit-appearance: none;
      appearance: none;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: white;
      cursor: pointer;
    }
    
    .wallet-slider::-moz-range-thumb {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: white;
      cursor: pointer;
      border: none;
    }
    
    .wallet-discount-preview {
      background: rgba(255,255,255,0.1);
      border-radius: 6px;
      padding: 15px;
      margin-top: 15px;
    }
    
    .discount-line {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
    }
    
    .discount-line.total {
      font-weight: 600;
      font-size: 16px;
      padding-top: 10px;
      border-top: 1px solid rgba(255,255,255,0.3);
    }
    
    /* Referral Section */
    .referral-section {
      background-color: #f8f9fa;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      border-left: 4px solid #28a745;
    }
    
    .referral-section h4 {
      color: #28a745;
      margin-bottom: 15px;
      font-size: 16px;
    }
    
    .referral-input {
      text-transform: uppercase;
    }
    
    .referral-help {
      font-size: 13px;
      color: #6c757d;
      margin-top: 5px;
    }
    
    .pay-now-btn {
      background: #004AAD;
      color: white;
      border: none;
      padding: 15px;
      width: 100%;
      font-weight: 500;
      border-radius: 4px;
      margin-top: 20px;
      font-size: 16px;
      transition: all 0.3s ease;
    }
    
    .pay-now-btn:hover {
        background: #0C2D71;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
      color: white;
    }
    
    .error-message {
      background: #f8d7da;
      color: #721c24;
      padding: 1.5rem;
      border-radius: 8px;
      margin: 1rem 0;
      text-align: center;
      border: 1px solid #f5c6cb;
    }
    
    @media (max-width: 767px) {
      .checkout-sidebar {
        margin-top: 30px;
      }
    }
    /* Responsive Fixes */
@media (max-width: 991px) {
  .checkout-container {
    padding: 20px 10px;
  }

  /* Stack form and sidebar neatly */
  .checkout-sidebar {
    margin-top: 30px;
  }

  .checkout-title {
    font-size: 22px;
    margin-bottom: 20px;
  }

  .section-title {
    font-size: 18px;
    margin-bottom: 15px;
  }

  .form-control {
    font-size: 14px;
    padding: 8px 10px;
  }

  .checkout-product {
    flex-direction: row;
    align-items: flex-start;
  }

  .checkout-product-img {
    width: 50px;
    height: 50px;
    margin-right: 10px;
  }

  .checkout-product-title {
    font-size: 13px;
  }

  .checkout-product-price {
    font-size: 13px;
    white-space: nowrap;
  }

  .order-summary-title {
    font-size: 16px;
  }

  .pay-now-btn {
    font-size: 15px;
    padding: 12px;
  }
}

@media (max-width: 576px) {
  /* Reduce spacing further for small screens */
  .checkout-container {
    padding: 15px 5px;
  }

  .checkout-product {
    flex-direction: row;
    align-items: center;
  }

  .checkout-product-details {
    font-size: 12px;
  }

  .checkout-product-title {
    font-size: 12px;
  }

  .checkout-product-price {
    font-size: 12px;
  }

  .wallet-section h3 {
    font-size: 16px;
  }

  .balance-amount {
    font-size: 20px;
  }

  .wallet-slider-container label {
    font-size: 13px;
  }

  .discount-line,
  .order-summary-line {
    font-size: 13px;
  }

  .order-summary-total {
    font-size: 15px;
  }

  .pay-now-btn {
    font-size: 14px;
    padding: 10px;
  }
}
/* Prevent horizontal scroll issues */
html, body {
  max-width: 100%;
  overflow-x: hidden;
}

/* ===== Phone Gate (Razorpay Magic Checkout pre-fill) ===== */
.phone-gate-wrap { padding: 32px 16px 24px; text-align: center; max-width: 480px; margin: 0 auto; }
.phone-gate-icon { font-size: 48px; margin-bottom: 10px; }
.phone-gate-title { font-size: 22px; font-weight: 700; color: #1a1a2e; margin-bottom: 6px; }
.phone-gate-sub { color: #666; font-size: 14px; margin-bottom: 26px; line-height: 1.5; }
.phone-row { display: flex; align-items: center; gap: 8px; max-width: 420px; margin: 0 auto 10px; }
.phone-flag { font-size: 14px; white-space: nowrap; color: #444; padding: 11px 12px; background: #f0f0f0; border-radius: 8px; border: 1.5px solid #ddd; }
.phone-field { flex: 1; min-width: 0; padding: 11px 14px; font-size: 16px; border: 1.5px solid #ccc; border-radius: 8px; outline: none; transition: border-color .2s; }
.phone-field:focus { border-color: #6C803F; box-shadow: 0 0 0 3px rgba(108,128,63,.12); }
.phone-primary-btn { padding: 11px 22px; background: #6C803F; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: background .2s, transform .15s; }
.phone-primary-btn:hover { background: #5a6e33; transform: translateY(-1px); }
.phone-primary-btn:disabled { background: #aaa; cursor: not-allowed; transform: none; }
.phone-error-msg { color: #dc3545; font-size: 13px; margin-top: 6px; text-align: center; }
.phone-welcome-msg { background: linear-gradient(135deg,#d4edda,#c3e6cb); border-radius: 10px; padding: 12px 16px; margin-top: 16px; color: #155724; font-size: 14px; text-align: left; border: 1px solid #b1dfbb; }
.phone-loading-msg { color: #666; font-size: 14px; padding: 12px; text-align: center; }
@media (max-width: 480px) {
  .phone-row { flex-wrap: wrap; }
  .phone-primary-btn { width: 100%; margin-top: 6px; }
}

  </style>
</head>
<body>

  <div class="container checkout-container">
    
    <?php if ($error): ?>
      <div class="error-message">
        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

        <div class="container text-center ml-4 ml-md-0">
            <a href="index.php" class="mb-4 d-inline-block">
              <img src="./assets/images/logo2.jpg" alt="<?= htmlspecialchars($siteName) ?>" width="160">
            </a>
          </div>

    <div class="row">
      <div class="col-lg-7 p-4" style="background-color:#f8f8f8; border-radius: 8px; ">

        <!-- Phone Gate — collect phone for returning-customer lookup -->
        <div id="phone-gate" <?= $isLoggedIn ? 'style="display:none"' : '' ?>>
          <div class="phone-gate-wrap">
            <div class="phone-gate-icon">📱</div>
            <h2 class="phone-gate-title">Enter your mobile number</h2>
            <p class="phone-gate-sub">
              Returning customers get instant auto-fill.<br>
              No password needed — Razorpay verifies you during payment.
            </p>
            <div class="phone-row">
              <span class="phone-flag">🇮🇳 +91</span>
              <input type="tel" id="phone-input" class="phone-field"
                     placeholder="10-digit mobile number"
                     maxlength="10" inputmode="numeric" autocomplete="tel">
              <button type="button" id="phone-continue-btn"
                      class="phone-primary-btn"
                      onclick="handlePhoneContinue()">Continue →</button>
            </div>
            <div id="phone-error" class="phone-error-msg" style="display:none"></div>
            <div id="phone-loading" style="display:none" class="phone-loading-msg">
              ⏳ Checking your details…
            </div>
            <div id="phone-welcome" style="display:none"></div>
          </div>
        </div><!-- /#phone-gate -->

        <!-- Checkout Gate — shown after phone entry (or immediately if already logged in) -->
        <div id="checkout-gate" <?= !$isLoggedIn ? 'style="display:none"' : '' ?>>

        <!-- Logged In User Checkout Form -->
        <form method="POST" id="checkout-form" >
          
          <!-- Wallet Points Section -->
          <?php if ($totalWalletPoints > 0): ?>
          <div class="wallet-section">
            <h3>💰 Use Wallet Points</h3>
            <div class="wallet-balance">
              <div class="balance-amount"><?= htmlspecialchars($currencySymbol) ?><?= number_format($totalWalletPoints, 2) ?> Available</div>
              <div class="balance-note">Each point = <?= htmlspecialchars($currencySymbol) ?>1 (Use as direct money!)</div>
            </div>
            
            <div class="wallet-slider-container">
              <label>Points to Use: <span id="points-display">0</span> (<?= htmlspecialchars($currencySymbol) ?><span id="points-value-display">0</span>)</label>
              <input type="range" 
                     class="wallet-slider" 
                     id="points-slider" 
                     name="points_to_use" 
                     min="0" 
                     max="<?= min($totalWalletPoints, $finalTotalBeforePoints) ?>" 
                     value="0" 
                     oninput="updatePriceCalculation()">
            </div>
            
            <div class="wallet-discount-preview">
              <div class="discount-line">
                <span>Subtotal:</span>
                <span><?= htmlspecialchars($currencySymbol) ?><?= number_format($subtotal, 2) ?></span>
              </div>
              <?php if ($shippingCost > 0): ?>
              <div class="discount-line">
                <span>Shipping:</span>
                <span><?= htmlspecialchars($currencySymbol) ?><?= number_format($shippingCost, 2) ?></span>
              </div>
              <?php else: ?>
              <div class="discount-line">
                <span>Shipping:</span>
                <span>FREE</span>
              </div>
              <?php endif; ?>
              <div class="discount-line">
                <span>Tax (<?= $taxRate ?>%):</span>
                <span><?= htmlspecialchars($currencySymbol) ?><?= number_format($taxAmount, 2) ?></span>
              </div>
              <div class="discount-line">
                <span>Wallet Discount:</span>
                <span id="discount-display"><?= htmlspecialchars($currencySymbol) ?>0.00</span>
              </div>
              <div class="discount-line total">
                <span>Final Total:</span>
                <span id="final-price-display"><?= htmlspecialchars($currencySymbol) ?><?= number_format($finalTotalBeforePoints, 2) ?></span>
              </div>
            </div>
          </div>
          <?php endif; ?>
        
          <!-- Referral Code Section (Only show if referrals enabled) -->
          <?php if ($enableReferrals): ?>
          <div class="referral-section d-none">
            <h4>🎁 Referral Code (Optional)</h4>
            <input type="text" name="referral_code" class="form-control referral-input" placeholder="Enter referral code..." maxlength="10">
            <div class="referral-help">Enter a friend's referral code to help them earn points!</div>
          </div>
          <?php endif; ?>

          <!-- Contact Section -->
        <section>
          <h2 class="section-title">E-mail</h2>
          <div class="form-group">
            <input type="email" name="email" class="form-control" placeholder="Email"
              value="<?= htmlspecialchars($fillEmail) ?>" required>
          </div>
        </section>
        
        <!-- Delivery Section -->
        <section class="mt-4">
          <h2 class="section-title">Delivery</h2>
          
          <div class="form-group">
            <label for="country">Country/Region</label>
            <select class="form-control" name="country" id="country">
              <option value="IN" selected>India</option>
            </select>
          </div>
        
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="firstName">First name *</label>
                <input type="text" name="first_name" class="form-control" id="firstName"
                     value="<?= htmlspecialchars($fillFirst) ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="lastName">Last name *</label>
                <input type="text" name="last_name" class="form-control" id="lastName"
                     value="<?= htmlspecialchars($fillLast) ?>" required>
              </div>
            </div>
          </div>
        
          <div class="form-group">
            <label for="address">Address *</label>
            <input type="text" name="address" class="form-control" id="address"
                   value="<?= htmlspecialchars($fillAddr1) ?>" required>
          </div>
        
          <div class="form-group">
            <label for="apartment">Apartment, suite, etc. (optional)</label>
            <input type="text" name="apartment" class="form-control" id="apartment"
                 value="<?= htmlspecialchars($fillApt) ?>">
          </div>
        
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label for="city">City *</label>
                <input type="text" name="city" class="form-control" id="city"
                     value="<?= htmlspecialchars($fillCity) ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="state">State *</label>
                <select class="form-control" name="state" id="state" required>
                  <option value="TN" <?= ($fillState ?: 'TN') === 'TN' ? 'selected' : '' ?>>Tamil Nadu</option>
                  <option value="KA" <?= $fillState === 'KA' ? 'selected' : '' ?>>Karnataka</option>
                  <option value="MH" <?= $fillState === 'MH' ? 'selected' : '' ?>>Maharashtra</option>
                  <option value="DL" <?= $fillState === 'DL' ? 'selected' : '' ?>>Delhi</option>
                  <option value="UP" <?= $fillState === 'UP' ? 'selected' : '' ?>>Uttar Pradesh</option>
                  <option value="GJ" <?= $fillState === 'GJ' ? 'selected' : '' ?>>Gujarat</option>
                  <option value="RJ" <?= $fillState === 'RJ' ? 'selected' : '' ?>>Rajasthan</option>
                  <option value="PB" <?= $fillState === 'PB' ? 'selected' : '' ?>>Punjab</option>
                  <option value="WB" <?= $fillState === 'WB' ? 'selected' : '' ?>>West Bengal</option>
                  <option value="AP" <?= $fillState === 'AP' ? 'selected' : '' ?>>Andhra Pradesh</option>
                  <option value="TG" <?= $fillState === 'TG' ? 'selected' : '' ?>>Telangana</option>
                  <option value="KL" <?= $fillState === 'KL' ? 'selected' : '' ?>>Kerala</option>
                  <option value="OR" <?= $fillState === 'OR' ? 'selected' : '' ?>>Odisha</option>
                  <option value="JH" <?= $fillState === 'JH' ? 'selected' : '' ?>>Jharkhand</option>
                  <option value="AS" <?= $fillState === 'AS' ? 'selected' : '' ?>>Assam</option>
                  <option value="BR" <?= $fillState === 'BR' ? 'selected' : '' ?>>Bihar</option>
                  <option value="CG" <?= $fillState === 'CG' ? 'selected' : '' ?>>Chhattisgarh</option>
                  <option value="GA" <?= $fillState === 'GA' ? 'selected' : '' ?>>Goa</option>
                  <option value="HR" <?= $fillState === 'HR' ? 'selected' : '' ?>>Haryana</option>
                  <option value="HP" <?= $fillState === 'HP' ? 'selected' : '' ?>>Himachal Pradesh</option>
                  <option value="JK" <?= $fillState === 'JK' ? 'selected' : '' ?>>Jammu and Kashmir</option>
                  <option value="MP" <?= $fillState === 'MP' ? 'selected' : '' ?>>Madhya Pradesh</option>
                  <option value="MN" <?= $fillState === 'MN' ? 'selected' : '' ?>>Manipur</option>
                  <option value="ML" <?= $fillState === 'ML' ? 'selected' : '' ?>>Meghalaya</option>
                  <option value="MZ" <?= $fillState === 'MZ' ? 'selected' : '' ?>>Mizoram</option>
                  <option value="NL" <?= $fillState === 'NL' ? 'selected' : '' ?>>Nagaland</option>
                  <option value="SK" <?= $fillState === 'SK' ? 'selected' : '' ?>>Sikkim</option>
                  <option value="TR" <?= $fillState === 'TR' ? 'selected' : '' ?>>Tripura</option>
                  <option value="UT" <?= $fillState === 'UT' ? 'selected' : '' ?>>Uttarakhand</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="pincode">PIN code *</label>
                <input type="text" name="pincode" class="form-control" id="pincode"
                     value="<?= htmlspecialchars($fillPin) ?>"
                     pattern="[0-9]{6}" maxlength="6" required>
              </div>
            </div>
          </div>
        
          <div class="form-group">
            <label for="phone">Phone *</label>
            <input type="tel" name="phone" class="form-control" id="phone"
                 value="<?= htmlspecialchars($fillPhone) ?>"
                 pattern="[0-9]{10}" maxlength="10" required>
          </div>
        </section>
        
        <!-- Coupon Code Section -->
        <section class="mt-4">
          <h2 class="section-title">Coupon Code (Optional)</h2>
          
          <div id="coupon-applied" style="display: none; background-color: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div style="font-weight: 500; color: #155724; margin-bottom: 5px;">
                  Coupon Applied: <span id="applied-coupon-code"></span>
                </div>
                <small class="text-muted">
                  <span id="coupon-discount-info"></span>
                </small>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeCoupon()">Remove</button>
            </div>
          </div>
          
          <div id="coupon-input-section">
            <div class="row">
              <div class="col-md-8">
                <div class="form-group">
                  <input type="text" 
                         id="coupon-code-input"
                         class="form-control" 
                         placeholder="Enter coupon code" 
                         style="text-transform: uppercase;"
                         maxlength="50">
                </div>
              </div>
              <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100" onclick="applyCoupon()">Apply Coupon</button>
              </div>
            </div>
            
            <div id="coupon-error" class="alert alert-danger mt-3" style="display: none; padding: 10px;"></div>
          </div>
        </section>
        
        <!-- Payment Section (Desktop) -->
        <section class="mt-4 d-none d-lg-block">
          <h2 class="section-title">Payment</h2>
          <p class="mb-2">All transactions are secure and encrypted.</p>
          
          <!-- Online Payment Option -->
          <div style="background-color: #f8f8f8; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
            <div style="display: flex; align-items: center; margin-bottom: 5px;">
              <input type="radio" id="razorpay" name="payment_method" value="razorpay" checked>
              <label for="razorpay" style="margin-left: 10px; margin-bottom: 0;">Online Payment (UPI, Cards, Wallets, NetBanking)</label>
            </div>
            <small class="text-muted">
              <i class="fas fa-info-circle mr-1"></i>
              Secure payment gateway - Pay online instantly
            </small>
          </div>
          
          <!-- COD Option -->
          <div style="background-color: #f8f8f8; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; margin-bottom: 5px;">
              <input type="radio" id="cod" name="payment_method" value="cod">
              <label for="cod" style="margin-left: 10px; margin-bottom: 0;">Cash on Delivery (COD)</label>
            </div>
            <small class="text-muted">
              <i class="fas fa-money-bill-wave mr-1"></i>
              Pay with cash when your order is delivered
            </small>
          </div>
        </section>
        
        <!-- Payment Section (Mobile) -->
        <section class="mt-4 d-block d-lg-none">
          <h2 class="section-title">Payment</h2>
          <p class="mb-2">All transactions are secure and encrypted.</p>
          
          <!-- Online Payment Option -->
          <div style="background-color: #f8f8f8; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
            <div style="display: flex; align-items: center; margin-bottom: 5px;">
              <input type="radio" id="razorpay-mobile" name="payment_method" value="razorpay" checked>
              <label for="razorpay-mobile" style="margin-left: 10px; margin-bottom: 0;">Online Payment (UPI, Cards, Wallets, NetBanking)</label>
            </div>
            <small class="text-muted">
              <i class="fas fa-info-circle mr-1"></i>
              Secure payment gateway - Pay online instantly
            </small>
          </div>
          
          <!-- COD Option -->
          <div style="background-color: #f8f8f8; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; margin-bottom: 5px;">
              <input type="radio" id="cod-mobile" name="payment_method" value="cod">
              <label for="cod-mobile" style="margin-left: 10px; margin-bottom: 0;">Cash on Delivery (COD)</label>
            </div>
            <small class="text-muted">
              <i class="fas fa-money-bill-wave mr-1"></i>
              Pay with cash when your order is delivered
            </small>
          </div>
        </section>
        
        <!-- Submit Button -->
        <button type="button" class="pay-now-btn d-none d-lg-block" onclick="handleMagicCheckout()">
          🔐 Complete Purchase - ₹<span id="pay-button-amount"><?= number_format($finalTotalBeforePoints, 2) ?></span>
        </button>
        
        </form>
        </div><!-- /#checkout-gate -->
        </div>

       <!-- Order Summary Sidebar -->
        <div class="col-lg-5">
        <div class="checkout-sidebar">
          <h3 class="order-summary-title">Order Summary</h3>
          
          <?php foreach ($cartItems as $item): ?>
          <div class="checkout-product">
            <div class="position-relative">
              <img src="<?= htmlspecialchars($item['product_image'] ?? 'https://via.placeholder.com/60x60?text=Product') ?>" 
                   alt="<?= htmlspecialchars($item['product_name']) ?>" 
                   class="checkout-product-img">
              <span class="item-quantity-badge"><?= $item['quantity'] ?></span>
            </div>
            <div class="checkout-product-details">
              <h5 class="checkout-product-title"><?= htmlspecialchars($item['product_name']) ?></h5>
              <?php if ($item['size']): ?>
                <p class="checkout-product-variant">Size: <?= htmlspecialchars($item['size']) ?></p>
              <?php endif; ?>
            </div>
            <div class="checkout-product-price"><?= htmlspecialchars($currencySymbol) ?><?= number_format($item['total_price'], 2) ?></div>
          </div>
          <?php endforeach; ?>
          
          <!-- Subtotal - Hidden per client request -->
          <div class="order-summary-line mt-4 d-none">
            <span>Subtotal</span>
            <span><?= htmlspecialchars($currencySymbol) ?><?= number_format($subtotal, 2) ?></span>
          </div>
          
          <!-- Coupon Discount Line (dynamically shown/hidden) -->
          <div class="order-summary-line" id="coupon-discount-line" style="display: <?= $appliedCoupon ? 'flex' : 'none' ?>; color: #dc3545;">
            <span>💰 Coupon Discount (<span id="sidebar-coupon-code"><?= htmlspecialchars($appliedCoupon['code'] ?? '') ?></span>)</span>
            <span id="sidebar-coupon-discount">-<?= htmlspecialchars($currencySymbol) ?><?= number_format($couponDiscount, 2) ?></span>
          </div>
          
          <!-- Shipping Cost (dynamically updated) -->
          <div class="order-summary-line" data-shipping="true">
           <span>Shipping</span>
           <span id="shipping-cost-display">
             <?php if ($totalAmountAfterCoupon < $freeShippingThreshold): ?>
                <?= htmlspecialchars($currencySymbol) ?><?= number_format($shippingCharge, 2) ?>
             <?php else: ?>
                <span style="color: #28a745;">Free</span>
             <?php endif; ?>
           </span>
          </div>
        
          <!-- Tax - Hidden per client request -->
          <div class="order-summary-line d-none">
            <span>Tax (<?= $taxRate ?>%)</span>
            <span><?= htmlspecialchars($currencySymbol) ?><?= number_format($taxAmount, 2) ?></span>
          </div>
        
          <!-- Wallet Points Discount (shown when applicable) -->
          <?php if ($totalWalletPoints > 0): ?>
          <div class="order-summary-line" id="wallet-discount-line" style="display: none; color: #17a2b8;">
            <span>Wallet Discount</span>
            <span id="sidebar-discount-display"><?= htmlspecialchars($currencySymbol) ?>0.00</span>
          </div>
          <?php endif; ?>
        
          <!-- Final Total -->
          <div class="order-summary-total">
            <div class="d-flex justify-content-between align-items-center">
              <span>Total</span>
              <div class="text-right">
                <div class="d-flex align-items-center">
                  <small class="mr-2"><?= htmlspecialchars($currency) ?></small>
                  <span id="sidebar-total-display"><?= htmlspecialchars($currencySymbol) ?><?= number_format($finalTotalBeforePoints, 2) ?></span>
                </div>
                <small class="text-muted">Including all discounts</small>
              </div>
            </div>
          </div>
          
          <!-- Mobile Payment Button -->
          <button type="button" class="pay-now-btn d-block d-lg-none" onclick="handleMagicCheckout()">
            🔐 Complete Purchase - ₹<span id="pay-button-amount-mobile"><?= number_format($finalTotalBeforePoints, 2) ?></span>
          </button>
        </div>
        </div>

  <!-- Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous"></script>

  <script>
      
    const currentUser = <?= $isLoggedIn ? 'true' : 'false' ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>  
      
    // Update price calculation with coupon support
    let currentCoupon = <?= json_encode($appliedCoupon) ?>;
    let couponDiscount = <?= $couponDiscount ?>;
    let baseTotal = <?= $totalAmount ?>;
    let currentShippingCost = <?= $shippingCost ?>;
    let taxRate = <?= $taxRate ?>;
    let freeShippingThreshold = <?= $freeShippingThreshold ?>;
    let shippingCharge = <?= $shippingCharge ?>;
    
    
    function updateAllCalculations() {
        // Calculate coupon-adjusted total
        let totalAfterCoupon = baseTotal;
        if (currentCoupon && couponDiscount > 0) {
            totalAfterCoupon = baseTotal - couponDiscount;
        }
        
        // Calculate shipping based on coupon-adjusted total
        let newShippingCost = 0;
        if (totalAfterCoupon < freeShippingThreshold) {
            newShippingCost = shippingCharge;
        }
        currentShippingCost = newShippingCost;
        
        // Update shipping display in sidebar
        const shippingDisplay = document.querySelector('[data-shipping="true"] span:last-child');
        if (shippingDisplay) {
            if (currentShippingCost > 0) {
                shippingDisplay.innerHTML = '<?= htmlspecialchars($currencySymbol) ?>' + currentShippingCost.toFixed(2);
                shippingDisplay.style.color = '#333';
            } else {
                shippingDisplay.innerHTML = '<span style="color: #28a745;">Free</span>';
            }
        }
        
        // Update wallet points calculation if points slider exists
        const slider = document.getElementById('points-slider');
        if (slider) {
            slider.max = Math.min(<?= $totalWalletPoints ?>, totalAfterCoupon);
            if (parseInt(slider.value) > totalAfterCoupon) {
                slider.value = totalAfterCoupon;
            }
            updatePriceCalculation();
        } else {
            updateSidebarTotal(totalAfterCoupon);
        }
        
        // Update pay button amount
        const payButtonAmount = document.getElementById('pay-button-amount');
        if (payButtonAmount) {
            payButtonAmount.textContent = totalAfterCoupon.toFixed(2);
        }
        
        updateCouponDisplayInSidebar();
    }
    
    function updateSidebarTotal(finalAmount) {
        const sidebarTotalDisplay = document.getElementById('sidebar-total-display');
        if (sidebarTotalDisplay) {
            sidebarTotalDisplay.textContent = '<?= htmlspecialchars($currencySymbol) ?>' + finalAmount.toFixed(2);
        }
    }

    function updateCouponDisplayInSidebar() {
        const couponDiscountLine = document.getElementById('coupon-discount-line');
        const sidebarCouponCode = document.getElementById('sidebar-coupon-code');
        const sidebarCouponDiscount = document.getElementById('sidebar-coupon-discount');
        
        if (currentCoupon && couponDiscount > 0) {
            if (sidebarCouponCode) sidebarCouponCode.textContent = currentCoupon.code;
            if (sidebarCouponDiscount) sidebarCouponDiscount.textContent = '-<?= htmlspecialchars($currencySymbol) ?>' + couponDiscount.toFixed(2);
            if (couponDiscountLine) couponDiscountLine.style.display = 'flex';
        } else {
            if (couponDiscountLine) couponDiscountLine.style.display = 'none';
        }
    }
    
    function updatePriceCalculation() {
        const slider = document.getElementById('points-slider');
        if (!slider) return;
        
        const pointsToUse = parseInt(slider.value);
        
        let baseAmount = <?= $totalAmount ?>;
        if (currentCoupon && couponDiscount > 0) {
            baseAmount = baseAmount - couponDiscount;
        }
        
        const discountAmount = pointsToUse;
        const finalPrice = Math.max(0, baseAmount - discountAmount);
        
        // Update wallet section displays
        const pointsDisplay = document.getElementById('points-display');
        const pointsValueDisplay = document.getElementById('points-value-display');
        const discountDisplay = document.getElementById('discount-display');
        const finalPriceDisplay = document.getElementById('final-price-display');
        
        if (pointsDisplay) pointsDisplay.textContent = pointsToUse;
        if (pointsValueDisplay) pointsValueDisplay.textContent = pointsToUse;
        if (discountDisplay) discountDisplay.textContent = '<?= htmlspecialchars($currencySymbol) ?>' + discountAmount.toFixed(2);
        if (finalPriceDisplay) finalPriceDisplay.textContent = '<?= htmlspecialchars($currencySymbol) ?>' + finalPrice.toFixed(2);
        
        // Update sidebar displays
        const sidebarDiscountDisplay = document.getElementById('sidebar-discount-display');
        const sidebarTotalDisplay = document.getElementById('sidebar-total-display');
        const walletDiscountLine = document.getElementById('wallet-discount-line');
        const payButtonAmount = document.getElementById('pay-button-amount');
        
        if (sidebarDiscountDisplay) sidebarDiscountDisplay.textContent = '<?= htmlspecialchars($currencySymbol) ?>' + discountAmount.toFixed(2);
        if (sidebarTotalDisplay) sidebarTotalDisplay.textContent = '<?= htmlspecialchars($currencySymbol) ?>' + finalPrice.toFixed(2);
        if (payButtonAmount) payButtonAmount.textContent = finalPrice.toFixed(2);
        
        // Show/hide wallet discount line
        if (walletDiscountLine) {
            walletDiscountLine.style.display = pointsToUse > 0 ? 'flex' : 'none';
        }
        
        // Update coupon display in sidebar
        const couponDiscountLine = document.getElementById('coupon-discount-line');
        const sidebarCouponCode = document.getElementById('sidebar-coupon-code');
        const sidebarCouponDiscount = document.getElementById('sidebar-coupon-discount');
        
        if (currentCoupon && couponDiscount > 0) {
            if (sidebarCouponCode) sidebarCouponCode.textContent = currentCoupon.code;
            if (sidebarCouponDiscount) sidebarCouponDiscount.textContent = '-<?= htmlspecialchars($currencySymbol) ?>' + couponDiscount.toFixed(2);
            if (couponDiscountLine) couponDiscountLine.style.display = 'flex';
        } else {
            if (couponDiscountLine) couponDiscountLine.style.display = 'none';
        }
    }
    
    // Coupon functionality
    function applyCoupon() {
        const couponCode = document.getElementById('coupon-code-input').value.trim().toUpperCase();
        
        if (!couponCode) {
            showCouponError('Please enter a coupon code');
            return;
        }
        
        // Show loading state
        const applyBtn = document.querySelector('button[onclick="applyCoupon()"]');
        const originalText = applyBtn.textContent;
        applyBtn.disabled = true;
        applyBtn.textContent = 'Applying...';
        
        const formData = new FormData();
        formData.append('apply_coupon', '1');
        formData.append('coupon_code', couponCode);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            applyBtn.disabled = false;
            applyBtn.textContent = originalText;
            
            if (data.success) {
                currentCoupon = data.coupon;
                couponDiscount = data.discount_amount;
                showAppliedCoupon(data.coupon);
                updateAllCalculations(); // NEW: This updates everything
                hideCouponError();
                showSuccessMessage('Coupon applied successfully!');
            } else {
                showCouponError(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            applyBtn.disabled = false;
            applyBtn.textContent = originalText;
            showCouponError('Error applying coupon');
        });
    }
    
    function removeCoupon() {
    // Show loading state
        const removeBtn = document.querySelector('button[onclick="removeCoupon()"]');
        const originalText = removeBtn.textContent;
        removeBtn.disabled = true;
        removeBtn.textContent = 'Removing...';
        
        const formData = new FormData();
        formData.append('remove_coupon', '1');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            removeBtn.disabled = false;
            removeBtn.textContent = originalText;
            
            if (data.success) {
                currentCoupon = null;
                couponDiscount = 0;
                hideAppliedCoupon();
                updateAllCalculations(); // NEW: This updates everything
                showSuccessMessage('Coupon removed successfully!');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            removeBtn.disabled = false;
            removeBtn.textContent = originalText;
        });
    }
    
    function showAppliedCoupon(coupon) {
        document.getElementById('applied-coupon-code').textContent = coupon.code;
        document.getElementById('coupon-discount-info').textContent = 
            `${coupon.discount_percentage}% discount - You save <?= htmlspecialchars($currencySymbol) ?>${couponDiscount.toFixed(2)}`;
        document.getElementById('coupon-applied').style.display = 'block';
        document.getElementById('coupon-input-section').style.display = 'none';
    }
    
    function hideAppliedCoupon() {
        document.getElementById('coupon-applied').style.display = 'none';
        document.getElementById('coupon-input-section').style.display = 'block';
        document.getElementById('coupon-code-input').value = '';
    }
    
    function showCouponError(message) {
        const errorDiv = document.getElementById('coupon-error');
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            hideCouponError();
        }, 5000);
    }    
    
    function hideCouponError() {
        document.getElementById('coupon-error').style.display = 'none';
    }
    
    function showSuccessMessage(message) {
        // Create temporary success message
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success mt-3';
        successDiv.style.padding = '10px';
        successDiv.style.marginTop = '10px';
        successDiv.textContent = message;
        
        // Insert after coupon section
        const couponSection = document.querySelector('section:has(#coupon-applied)');
        if (couponSection) {
            couponSection.appendChild(successDiv);
            
            // Remove after 3 seconds
            setTimeout(() => {
                successDiv.remove();
            }, 3000);
        }
    }
    
    // Convert referral code input to uppercase - PRESERVED
    document.addEventListener('DOMContentLoaded', function() {
      const referralInputs = document.querySelectorAll('input[type="text"]');
      referralInputs.forEach(input => {
        if (input.placeholder.includes('referral code') || input.name === 'referral_code') {
          input.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
          });
        }
      });
      
      // Form validation
      const form = document.getElementById('checkout-form');
      if (form) {
        form.addEventListener('submit', function(e) {
          const requiredFields = form.querySelectorAll('input[required], select[required]');
          let isValid = true;
          
          requiredFields.forEach(field => {
            if (!field.value.trim()) {
              field.style.borderColor = '#dc3545';
              isValid = false;
            } else {
              field.style.borderColor = '#ddd';
            }
          });
          
          if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
          }
        });
      }
      
      // Check for referral code from URL (after login redirect) - PRESERVED
      const urlParams = new URLSearchParams(window.location.search);
      const referralFromUrl = urlParams.get('referral_code');
      if (referralFromUrl) {
        const referralInput = document.querySelector('input[name="referral_code"]');
        if (referralInput) {
          referralInput.value = referralFromUrl;
        }
      }
      
      // ADD THIS DEBUG CODE HERE:
      // Debug coupon state
      console.log('Coupon Debug - currentCoupon:', currentCoupon);
      console.log('Coupon Debug - couponDiscount:', couponDiscount);
      
      // If there's a mismatch, clear the JavaScript variables
      if (!currentCoupon && couponDiscount > 0) {
        console.log('Clearing stale coupon discount');
        couponDiscount = 0;
        updateAllCalculations();
      }
      
      // Initialize coupon display and calculations
      if (currentCoupon) {
          showAppliedCoupon(currentCoupon);
      }
      updateAllCalculations(); // NEW: Initialize all calculations
    
      // Add data attributes to help with element selection
      const shippingLine = Array.from(document.querySelectorAll('.order-summary-line')).find(line => 
          line.textContent.includes('Shipping')
      );
      if (shippingLine) {
          shippingLine.setAttribute('data-shipping', 'true');
      }
    });    
        
    // Initialize global variables
    let showRazorpayCheckout = <?= isset($showRazorpayCheckout) && $showRazorpayCheckout ? 'true' : 'false' ?>;
    let razorpayOrderData = <?= isset($razorpayOrderData) ? json_encode($razorpayOrderData) : 'null' ?>;

    // Form submission handling
    document.getElementById('checkout-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Disable submit button to prevent double submission
        const submitBtn = document.querySelector('.pay-now-btn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        
        // Submit form for Razorpay order creation
        this.submit();
        
        // Re-enable button after 3 seconds as fallback
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 3000);
    });

    // Razorpay Integration (with Magic Checkout / saved customer recognition)
    <?php if (isset($showRazorpayCheckout) && $showRazorpayCheckout): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const options = {
            key: '<?= $razorpayKeyId ?>',
            amount: <?= $razorpayOrderData['amount'] ?>,
            currency: '<?= $razorpayOrderData['currency'] ?>',
            name: '<?= htmlspecialchars($siteName) ?>',
            description: 'Order Payment',
            order_id: '<?= $razorpayOrderData['id'] ?>',
            <?php if (!empty($razorpayCustomerId)): ?>
            // Magic Checkout: pass Razorpay customer_id so the checkout surfaces
            // saved cards/UPI/addresses from the customer's Razorpay wallet.
            customer_id: '<?= htmlspecialchars($razorpayCustomerId) ?>',
            remember_customer: true,
            <?php endif; ?>
            handler: function(response) {
                const form = document.getElementById('checkout-form');

                const addHidden = (name, value) => {
                    const el = document.createElement('input');
                    el.type = 'hidden'; el.name = name; el.value = value;
                    form.appendChild(el);
                };
                addHidden('razorpay_payment_id', response.razorpay_payment_id);
                addHidden('razorpay_order_id',   response.razorpay_order_id);
                addHidden('razorpay_signature',  response.razorpay_signature);
                form.submit();
            },
            prefill: {
                name:    '<?= htmlspecialchars(trim(($_SESSION['checkout_data']['first_name'] ?? '') . ' ' . ($_SESSION['checkout_data']['last_name'] ?? ''))) ?>',
                email:   '<?= htmlspecialchars($_SESSION['checkout_data']['email']   ?? '') ?>',
                contact: '<?= htmlspecialchars($_SESSION['checkout_data']['phone']   ?? $fillPhone ?? '') ?>'
            },
            theme: { color: '#004AAD' },
            modal: { confirm_close: true }
        };

        const rzp = new Razorpay(options);
        rzp.open();

        rzp.on('payment.failed', function(response) {
            alert('Payment failed: ' + response.error.description);
            window.location.reload();
        });
    });
    <?php endif; ?>

    // Payment method change handler
    document.addEventListener('DOMContentLoaded', function() {
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        const payButton = document.querySelector('.pay-now-btn');
        
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                updatePayButtonText();
            });
        });
        
        function updatePayButtonText() {
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
            const payButton = document.querySelector('.pay-now-btn');
            const payButtonAmount = document.getElementById('pay-button-amount');
            
            if (!selectedPayment || !payButton || !payButtonAmount) return;
            
            if (selectedPayment.value === 'cod') {
                payButton.innerHTML = `📦 Place COD Order - ₹<span id="pay-button-amount">${payButtonAmount.textContent}</span>`;
            } else {
                payButton.innerHTML = `🔐 Complete Purchase - ₹<span id="pay-button-amount">${payButtonAmount.textContent}</span>`;
            }
        }        
        // Initial call
        updatePayButtonText();
    });
    
    // Update handleMagicCheckout function
    function handleMagicCheckout() {
        const submitBtn = document.querySelector('.pay-now-btn');
        const originalText = submitBtn.innerHTML;
        
        // Validate form fields
        const form = document.getElementById('checkout-form');
        const requiredFields = form.querySelectorAll('input[required], select[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#dc3545';
                isValid = false;
            } else {
                field.style.borderColor = '#ddd';
            }
        });
        
        if (!isValid) {
            alert('Please fill in all required fields.');
            return;
        }
        
        const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
        
        // Add a hidden field to indicate this is a checkout submission, not coupon
        const checkoutIndicator = document.createElement('input');
        checkoutIndicator.type = 'hidden';
        checkoutIndicator.name = 'checkout_submit';
        checkoutIndicator.value = '1';
        form.appendChild(checkoutIndicator);
        
        if (selectedPayment && selectedPayment.value === 'cod') {
            // COD order - submit directly
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Placing COD Order...';
            document.getElementById('checkout-form').submit();
        } else {
            // Online payment - proceed with Razorpay
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing Payment...';
            document.getElementById('checkout-form').submit();
        }
        
        // Re-enable button after 3 seconds as fallback
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 3000);
    }
    
    // Handle coupon form submission separately
    function applyCouponCode(event) {
        event.preventDefault();
        
        const couponForm = document.getElementById('coupon-form');
        const applyBtn = document.getElementById('apply-coupon-btn');
        const checkoutForm = document.getElementById('checkout-form');
        
        // Show loading state
        const originalText = applyBtn.innerHTML;
        applyBtn.disabled = true;
        applyBtn.innerHTML = 'Applying...';
        
        // Collect all checkout form data
        const checkoutFormData = new FormData(checkoutForm);
        const checkoutDataObj = {};
        for (let [key, value] of checkoutFormData.entries()) {
            if (key !== 'coupon_code' && key !== 'apply_coupon') {
                checkoutDataObj[key] = value;
            }
        }
        
        // Create new FormData with coupon data AND preserved checkout data
        const submitData = new FormData(couponForm);
        submitData.append('checkout_form_data', JSON.stringify(checkoutDataObj));
        
        // Submit the coupon application
        fetch(window.location.href, {
            method: 'POST',
            body: submitData
        })
        .then(response => response.text())
        .then(html => {
            // Reload the page to show updated coupon state
            window.location.reload();
        })
        .catch(error => {
            console.error('Error applying coupon:', error);
            applyBtn.disabled = false;
            applyBtn.innerHTML = originalText;
            alert('Error applying coupon. Please try again.');
        });
        
        return false;
    }

    /* ===== Phone Gate JS — Razorpay Magic Checkout pre-fill ===== */
    (function () {
        window.handlePhoneContinue = function () {
            var phoneInput = document.getElementById('phone-input');
            var phone      = phoneInput.value.trim().replace(/\D/g, '');
            var errEl      = document.getElementById('phone-error');
            var btn        = document.getElementById('phone-continue-btn');
            var loadingEl  = document.getElementById('phone-loading');
            errEl.style.display = 'none';

            if (!/^[6-9]\d{9}$/.test(phone)) {
                errEl.textContent = 'Please enter a valid 10-digit Indian mobile number.';
                errEl.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Checking…';
            loadingEl.style.display = 'block';

            fetch('shop/api/customer-lookup.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({phone: phone})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = 'Continue →';
                loadingEl.style.display = 'none';

                if (!data.success) {
                    errEl.textContent = data.message || 'Unable to process. Please try again.';
                    errEl.style.display = 'block';
                    return;
                }

                if (data.found && data.user) {
                    autoFillCheckoutForm(data.user);
                    var firstName = (data.user.full_name || data.user.name || '').trim().split(/\s+/)[0];
                    if (firstName) {
                        var welcomeEl = document.getElementById('phone-welcome');
                        if (welcomeEl) {
                            welcomeEl.innerHTML =
                                '<div class="phone-welcome-msg">' +
                                '👋 Welcome back, <strong>' + firstName + '</strong>! ' +
                                'Your saved details have been filled in.' +
                                '</div>';
                            welcomeEl.style.display = 'block';
                        }
                    }
                }

                var guestCount = parseInt(data.guest_cart_count) || 0;
                if (guestCount > 0 && data.found) {
                    showCartMergeDialog(guestCount, function () { proceedToCheckout(phone); });
                } else {
                    // Brief delay so returning users can see the welcome message
                    setTimeout(function () { proceedToCheckout(phone); }, data.found ? 700 : 0);
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = 'Continue →';
                loadingEl.style.display = 'none';
                errEl.textContent = 'Network error. Please try again.';
                errEl.style.display = 'block';
            });
        };

        function autoFillCheckoutForm(user) {
            if (!user) return;
            var name  = (user.full_name || user.name || '').trim();
            var parts = name.split(/\s+/);
            function setVal(id, val) { var el = document.getElementById(id); if (el && val) el.value = val; }
            setVal('firstName', parts[0] || '');
            setVal('lastName',  parts.slice(1).join(' ') || '');
            setVal('address',   user.address);
            setVal('apartment', user.apartment);
            setVal('city',      user.city);
            setVal('pincode',   user.pincode);
            setVal('phone',     user.phone);
            var emailEl = document.querySelector('#checkout-form input[name="email"]');
            if (emailEl && user.email) emailEl.value = user.email;
            if (user.state) { var st = document.getElementById('state'); if (st) st.value = user.state; }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var phoneInput = document.getElementById('phone-input');
            if (phoneInput) {
                phoneInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); handlePhoneContinue(); }
                });
                // Only allow digits
                phoneInput.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').slice(0, 10);
                });
            }
        });
    }());

    // ── Phone gate → checkout gate transition ────────────────────────────
    function proceedToCheckout(phone) {
        document.getElementById('phone-gate').style.display = 'none';
        document.getElementById('checkout-gate').style.display = 'block';
        // Transfer entered phone to the checkout form phone field
        if (phone) {
            var phoneField = document.getElementById('phone');
            if (phoneField && !phoneField.value) phoneField.value = phone;
        }
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    // ── Cart Merge dialog helpers ─────────────────────────────────────────

    var _mergeDialogCb = null;

    function showCartMergeDialog(guestCount, callback) {
        _mergeDialogCb = callback;
        var noun = guestCount === 1 ? '1 item' : guestCount + ' items';
        document.getElementById('merge-dialog-count').textContent = noun;
        document.getElementById('cart-merge-overlay').style.display = 'flex';
    }

    window.handleCartMerge = function(shouldMerge) {
        document.getElementById('cart-merge-overlay').style.display = 'none';
        var action = shouldMerge ? 'merge_guest' : 'clear_guest';
        fetch('shop/api/cart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: action})
        }).then(function(r){ return r.json(); }).catch(function(){ return {}; }).then(function() {
            if (_mergeDialogCb) _mergeDialogCb(shouldMerge);
        });
    };

  </script>

  <!-- Razorpay Checkout Script -->
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

  <!-- Cart Merge Dialog -->
  <div id="cart-merge-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:10000;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:20px;padding:32px 24px;max-width:380px;width:100%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.22);animation:mergeDialogPop .3s cubic-bezier(.34,1.56,.64,1) both;">
      <div style="width:64px;height:64px;background:#f0f4ff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:30px;">🛒</div>
      <h3 style="font-size:18px;font-weight:700;color:#1a1a2e;margin:0 0 10px;">Items waiting in your cart</h3>
      <p style="color:#666;font-size:14px;line-height:1.5;margin:0 0 24px;">You had <strong id="merge-dialog-count">items</strong> before signing in. Want to keep them?</p>
      <div style="display:flex;gap:12px;">
        <button onclick="handleCartMerge(false)" style="flex:1;padding:13px 8px;border:2px solid #e0e0e0;background:#fff;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;color:#555;transition:all .2s;" onmouseover="this.style.borderColor='#aaa'" onmouseout="this.style.borderColor='#e0e0e0'">Start Fresh</button>
        <button onclick="handleCartMerge(true)" style="flex:1;padding:13px 8px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;color:#fff;box-shadow:0 4px 14px rgba(102,126,234,.35);">Add All Items ✓</button>
      </div>
    </div>
  </div>
  <style>
    @keyframes mergeDialogPop {
      from { transform: scale(.85); opacity: 0; }
      to   { transform: scale(1);   opacity: 1; }
    }
  </style>

</body>
</html>