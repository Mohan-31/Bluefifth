<?php
// shop/cart.php - Dynamic Shopping Cart Page
// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';
require_once '../includes/referral-tracker.php';

error_log("=== CART PAGE DEBUG ===");
error_log("Is logged in: " . (isLoggedIn() ? 'YES' : 'NO'));
error_log("Session guest cart: " . print_r($_SESSION['guest_cart'] ?? [], true));

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;

// Get cart items
$cartItems = [];
$cartSummary = ['item_count' => 0, 'total_quantity' => 0, 'total_amount' => 0];

if ($isLoggedIn) {
    // Get user cart from database
    error_log("Loading cart for logged in user ID: " . $currentUser['id']);
    $cartItems = getCartItems($currentUser['id']);
    $cartSummary = getCartSummary($currentUser['id']);
} else {
    // Get guest cart from session
    error_log("Loading guest cart from session");
    $cartItems = getSessionCartItems();
    $cartSummary = getSessionCartSummary();
}

error_log("Final cart items in cart.php: " . print_r($cartItems, true));
error_log("Final cart summary: " . print_r($cartSummary, true));

// Get all categories for navigation
$categories = getAllCategories();

// Get wallet balance if logged in (for display only, not usage)
$walletBalance = $isLoggedIn ? getWalletBalance($currentUser['id']) : ['points' => 0, 'pending_points' => 0];

// Get cart summary with combo pricing
if ($isLoggedIn) {
    $cartSummary = getCartSummaryWithCombo($currentUser['id']);
} else {
    $cartSummary = getSessionCartSummaryWithCombo();
}

// Calculate shipping based on combo price
$shippingCost = 0;
$freeShippingThreshold = getSetting('free_shipping_threshold', 1000);
if ($cartSummary['total'] > 0 && $cartSummary['total'] < $freeShippingThreshold) {
    $shippingCost = 50;
}

$finalTotal = $cartSummary['total'] + $shippingCost;
?>
<!doctype html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
    <title>Your Cart - Bluefifth</title>
    <style>
        /* Cart Card Layout */
        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .cart-card {
            display: flex;
            align-items: flex-start;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: relative;
        }
        
        .cart-card-left {
            flex-shrink: 0;
        }
        
        .cart-card-img {
            width: 100px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .cart-card-body {
            flex: 1;
            margin-left: 15px;
        }
        
        .cart-card-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .cart-card-variant {
            font-size: 14px;
            color: #777;
        }
        
        .cart-card-price {
            font-size: 15px;
            font-weight: 500;
            margin: 5px 0;
        }
        
        .cart-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .cart-card-total {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .cart-remove-btn {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
            position: absolute;
            top: 10px;
            right: 10px;
            transition: color 0.3s;
        }
        .cart-remove-btn:hover {
            color: #dc3545;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .cart-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .cart-card-body {
                margin-left: 0;
                margin-top: 10px;
            }
            .cart-card-footer {
                flex-direction: column;
                gap: 10px;
            }
        }

        .product-img {
            width: 100px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        .product-details {
            padding-left: 20px;
        }
        .product-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .product-variant {
            font-size: 14px;
            color: #777;
            margin-bottom: 0;
        }
        .quantity-selector {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            width: 120px;
            border-radius: 4px;
        }
        .quantity-btn {
            width: 30px;
            height: 30px;
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
        }
        .quantity-input {
            width: 60px;
            height: 30px;
            border: none;
            text-align: center;
        }
        .remove-btn {
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
            transition: color 0.3s;
        }
        .remove-btn:hover {
            color: #dc3545;
        }
        .continue-shopping {
            color: #333;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .continue-shopping:hover {
            text-decoration: underline;
            color: #333;
        }
        .cart-summary {
            background-color: #f8f8f8;
            padding: 20px;
            border-radius: 8px;
        }
        .summary-title {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .summary-total {
            font-weight: 600;
            font-size: 18px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .checkout-btn {
            background-color: #004AAD;
            color: white;
            border: none;
            padding: 12px;
            width: 100%;
            font-weight: 500;
            border-radius: 4px;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        .checkout-btn:hover {
            background-color: #011D3F;
            color: white;
            text-decoration: none;
            width: 100%;
        }
        .checkout-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            width: 100%;
        }
        .note {
            font-size: 14px;
            color: #777;
            margin-top: 10px;
        }
        .empty-cart {
            text-align: center;
            padding: 50px 0;
        }
        .empty-cart-icon {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        .loading {
            display: none;
        }
        .loading.show {
            display: inline-block;
        }
        .alert-custom {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        }
        .promo-section {
            background-color: #2664EB;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .free-shipping-progress {
            background-color: #e9ecef;
            height: 10px;
            border-radius: 5px;
            margin-top: 10px;
            overflow: hidden;
        }
        .free-shipping-bar {
            background-color: #1E3B8A;
            height: 100%;
            transition: width 0.3s ease;
        }
        @media (max-width: 767px) {
            .cart-table thead {
                display: none;
            }
            .cart-table td {
                display: block;
                text-align: right;
                position: relative;
                padding: 10px 0;
            }
            .cart-table td:before {
                content: attr(data-label);
                float: left;
                font-weight: 500;
            }
            .cart-table tr {
                display: block;
                margin-bottom: 20px;
                border-bottom: 1px solid #e5e5e5;
            }
            .cart-table td:last-child {
                border-bottom: none;
            }
            .product-img {
                width: 80px;
                height: 100px;
            }
            .product-details {
                padding-left: 0;
                text-align: left;
                margin-bottom: 10px;
            }
            .quantity-selector {
                margin-left: auto;
            }
            .remove-btn {
                margin-left: auto;
            }
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            display: none;
        }

        .modal-overlay.show {
            display: block;
        }

        .popup-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            z-index: 2500;
            min-width: 600px;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            display: none;
        }

        .popup-modal.show {
            display: block;
        }

        .popup-close {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .popup-close:hover {
            color: #333;
            background: #f8f9fa;
        }

        .popup-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .popup-header h3 {
            margin: 0;
            color: #333;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .stat-card {
            background: linear-gradient(135deg, #484E47 0%, #6c757d 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .copy-input {
            display: flex;
            margin: 1rem 0;
        }

        .copy-input input {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 10px 0 0 10px;
            font-family: monospace;
        }

        .copy-input button {
            padding: 0.75rem 1rem;
            background: #000;
            color: white;
            border: none;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .copy-input button:hover {
            background: #333;
        }

        .table-responsive {
            margin: 1rem 0;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            border-top: none;
        }

        .popup-btn {
            background: #000;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .popup-btn:hover {
            background: #333;
            transform: translateY(-2px);
        }

        .popup-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .alert-custom {
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Auth loading spinner */
        .auth-loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 3000;
            text-align: center;
            display: none;
        }

        .auth-loading.show {
            display: block;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #000;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Fixed navbar icons spacing */
        .navbar .d-flex.align-items-center > * {
            margin-left: 15px;
        }

        .navbar .d-flex.align-items-center > *:first-child {
            margin-left: 0;
        }

        /* Guest login section */
        .guest-login-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            border: 2px solid #ddd;
        }

        .guest-login-section h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-earned { background: #d4edda; color: #155724; }
        .status-claimed { background: #cce5ff; color: #004085; }
        .status-processed { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-approved { background: #d1ecf1; color: #0c5460; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .popup-modal {
                min-width: 95vw;
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .copy-input {
                flex-direction: column;
            }
            
            .copy-input input {
                border-radius: 10px;
                margin-bottom: 0.5rem;
            }
            
            .copy-input button {
                border-radius: 10px;
            }
        }

        /* Default: Mobile First (small screens) */
        .img-responsive{
    width:150px;
    }
    .nav-align{
        margin-top:150px;
    }
    /* Tablet View (min-width: 768px) */
    @media (min-width: 768px) {
    .img-responsive{
        width:150px;
    }
    .nav-align{
        margin-top:100px;
    }
    }
    
    /* Laptop/Desktop View (min-width: 1024px or 1200px) */
    @media (min-width: 1024px) {
    .img-responsive{
        width:200px;
    }
    .nav-align{
        margin-top:250px;
    }
    }

        .modal-body {
            max-height: 300px; /* adjust height */
            overflow-y: scroll;  /* still scrollable */
            scrollbar-width: none; /* for Firefox */
            -ms-overflow-style: none; /* for Internet Explorer & Edge */
        }
        .modal-body::-webkit-scrollbar {
            display: none; /* for Chrome, Safari, Opera */
        }
        .modal-body::-webkit-scrollbar-thumb {
            background: #aaa;
            border-radius: 10px;
        }
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #666;
        }




    
    </style>
</head>
<body>
    <?php include '../includes/timer.php'; ?>
    <!-- Navigation -->
    <?php include '../includes/navbar.php'; ?>

    <div class="container cart-container nav-align">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
            </ol>
        </nav>

        <h1 class="cart-title" style="color:#2563EB;">Your cart</h1>
        
        <?php if (empty($cartItems)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <i class="fas fa-shopping-bag empty-cart-icon"></i>
                <h3>Your cart is empty</h3>
                <p class="text-muted">Looks like you haven't added any items to your cart yet.</p>
                <a href="../index.php" class="btn btn-primary mt-3">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8">
                    <!-- Free Shipping Progress -->
                    <?php if ($cartSummary['total_amount'] < $freeShippingThreshold): ?>
                        <div class="promo-section">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas fa-truck mr-2"></i>
                                    Add ₹<?= number_format($freeShippingThreshold - $cartSummary['total_amount']) ?> more for FREE shipping
                                </span>
                            </div>
                            <div class="free-shipping-progress">
                                <div class="free-shipping-bar" style="width: <?= min(100, ($cartSummary['total_amount'] / $freeShippingThreshold) * 100) ?>%"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="promo-section">
                            <i class="fas fa-check-circle mr-2"></i>
                            Congratulations! You qualify for FREE shipping
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isComboApplied): ?>
                      <!-- Combo Offer Applied -->
                      <div class="mb-3 p-3" style="background: linear-gradient(135deg, #28a745, #20c997); color: white; border-radius: 8px;">
                        <div class="d-flex justify-content-between align-items-center">
                          <div>
                            <h6 class="mb-1">🎉 Combo Offer Applied!</h6>
                            <?php if ($cartSummary['combo_type'] == '3_for_1199'): ?>
                              <small>3 Products for ₹1199</small>
                            <?php else: ?>
                              <small>5 Products for ₹1699</small>
                            <?php endif; ?>
                          </div>
                          <div class="text-right">
                            <div style="text-decoration: line-through; font-size: 12px;">₹<?= number_format($cartSummary['regular_total'], 2) ?></div>
                            <div style="font-weight: 600;">You saved ₹<?= number_format($comboSavings, 2) ?>!</div>
                          </div>
                        </div>
                      </div>
                      <?php endif; ?>
                    
                    <!-- Combo Offer Section -->
                    <?php if ($cartSummary['is_combo'] || in_array($cartSummary['item_count'], [1, 2, 4])): ?>
                        <div class="combo-offer-section mb-4">
                            <?php if ($cartSummary['is_combo']): ?>
                                <!-- Active Combo Offer -->
                                <div class="alert alert-success">
                                    <h5 class="mb-2">🎉 Combo Offer Applied!</h5>
                                    <?php if ($cartSummary['combo_type'] == '3_for_1199'): ?>
                                        <p class="mb-1">3 Products for ₹1199</p>
                                    <?php else: ?>
                                        <p class="mb-1">5 Products for ₹1699</p>
                                    <?php endif; ?>
                                    <small class="text-success">You saved ₹<?= number_format($cartSummary['savings'], 2) ?>!</small>
                                </div>
                            <?php else: ?>
                                <!-- Combo Offer Reminder -->
                                <div class="alert alert-warning">
                                    <h6 class="mb-2">💡 Special Combo Offers Available!</h6>
                                    <ul class="mb-2" style="padding-left: 20px;">
                                        <li>Buy 3 products for ₹1199</li>
                                        <li>Buy 5 products for ₹1699</li>
                                    </ul>
                                    <?php 
                                    $reminderMsg = getComboReminderMessage($cartSummary['item_count']);
                                    if ($reminderMsg): 
                                    ?>
                                        <small class="text-warning"><?= $reminderMsg ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-card" data-cart-id="<?= $isLoggedIn ? $item['id'] : $item['cart_key'] ?>" data-product-id="<?= $item['product_id'] ?>">
                            <div class="cart-card-left">
                                <img src="<?= htmlspecialchars($item['product_image'] ?: '../assets/images/default-product.jpg') ?>" 
                                     alt="<?= htmlspecialchars($item['product_name']) ?>" class="cart-card-img">
                            </div>
                            <div class="cart-card-body">
                                <h5 class="cart-card-title">
                                    <a href="product.php?id=<?= $item['product_id'] ?>" class="text-dark text-decoration-none">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </a>
                                </h5>
                                <?php if (!empty($item['size'])): ?>
                                    <p class="cart-card-variant">Size: <?= htmlspecialchars($item['size']) ?></p>
                                <?php endif; ?>
                                <p class="cart-card-price">₹<?= number_format($item['product_price'], 2) ?></p>
                
                                <div class="cart-card-footer">
                                    <div class="quantity-selector">
                                        <button class="quantity-btn" onclick="updateQuantity('<?= $isLoggedIn ? $item['id'] : $item['cart_key'] ?>', -1, <?= $item['stock_quantity'] ?>)">−</button>
                                        <input type="number" class="quantity-input" value="<?= $item['quantity'] ?>" 
                                               min="1" max="<?= $item['stock_quantity'] ?>" readonly>
                                        <button class="quantity-btn" onclick="updateQuantity('<?= $isLoggedIn ? $item['id'] : $item['cart_key'] ?>', 1, <?= $item['stock_quantity'] ?>)">+</button>
                                    </div>
                                    <span class="cart-card-total d-none">₹<?= number_format($item['total_price'], 2) ?></span>
                                </div>
                            </div>
                            <button class="cart-remove-btn" onclick="removeFromCart('<?= $isLoggedIn ? $item['id'] : $item['cart_key'] ?>')" title="Remove item">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                    
                    <a href="../index.php" class="continue-shopping mb-3">
                        <i class="fas fa-arrow-left mr-2"></i> Continue shopping
                    </a>
                </div>
                
                <div class="col-lg-4 mt-4 mt-lg-0">
                    <div class="cart-summary">
                        <h5 class="summary-title">Order summary</h5>
                        
                        <div class="order-summary-line mt-4">
                        <span>Subtotal (<?= array_sum(array_column($cartItems, 'quantity')) ?> items)</span>
                        <span>
                          <?php if ($isComboApplied): ?>
                            <span style="text-decoration: line-through; color: #999; font-size: 12px;">₹<?= number_format($cartSummary['regular_total'], 2) ?></span><br>
                            <span style="color: #28a745; font-weight: 600;">₹<?= number_format($totalAmount, 2) ?></span>
                          <?php else: ?>
                            ₹<?= number_format($totalAmount, 2) ?>
                          <?php endif; ?>
                        </span>
                      </div>
                        
                        <div class="summary-line">
                            <span>Shipping</span>
                            <span id="shippingAmount">
                                <?= $shippingCost > 0 ? '₹' . number_format($shippingCost, 2) : 'FREE' ?>
                            </span>
                        </div>
                        
                        <div class="summary-line summary-total">
                            <span>Total</span>
                            <span id="totalAmount">₹<?= number_format($finalTotal, 2) ?> INR</span>
                        </div>
                        
                        <p class="note">Taxes included. Discounts calculated at checkout.</p>
                        
                        <?php if ($isLoggedIn): ?>
                        <div class="w-100">
                            <?php if (in_array($cartSummary['item_count'], [1, 2, 4])): ?>
                                <button class="checkout-btn text-decoration-none w-100" onclick="showComboReminderPopup()">Check out</button>
                            <?php else: ?>
                                <a href="../checkout.php"><button class="checkout-btn text-decoration-none w-100">Check out</button></a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                            <?php if (in_array($cartSummary['item_count'], [1, 2, 4])): ?>
                                <button class="checkout-btn w-100" onclick="showComboReminderPopup()">Check out</button>
                            <?php else: ?>
                                <button class="checkout-btn w-100" onclick="proceedToCheckout()">Check out</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Clear Cart Button -->
                        <button class="btn btn-outline-secondary btn-sm mt-3 w-100" onclick="clearCart()">
                            <i class="fas fa-trash mr-2"></i>Clear Cart
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
    <div class="container-fluid pb-3 p-5">
        <div class="row">
            <div class="col-6 col-md-2" style="letter-spacing: 2px; text-transform: uppercase;">
                <div class="footer-logo">
                    <img class="" src="../assets/images/logo3.png" width="160px" alt="Velona">
                </div>
                <a href="../index.php" class="mt-5">HOME</a>
                <a href="../includes/about.php">ABOUT US</a>
                <a href="#" data-toggle="modal" data-target="#contactUsModal">CONTACT US</a>
            </div>
            <style>
                .promotion:hover {
                    color: yellow;
                    text-decoration: none !important;
                }
            </style>
            <div class="col-6 col-md-2 mt-5" style="letter-spacing: 2px; text-transform: uppercase;">
                <h5>&nbsp;</h5>
                <a href="#" data-toggle="modal" data-target="#privacyPolicyModal">Privacy & Policy</a>
                <a href="#" data-toggle="modal" data-target="#affiliateTermsModal">Affiliate Terms and conditions</a>
                <a href="#" data-toggle="modal" data-target="#shippingReturnsModal">Shipping & Returns</a>
                <a href="#" data-toggle="modal" data-target="#termsAndConditionsModal">Terms & Conditions</a>
            </div>
            <div class="col-md-4">
                <h5 style="letter-spacing: 2px; text-transform: uppercase;">About BLUEFIFTH</h5>
                <p class="mt-4" style="font-size: 14px; font-weight: 300; line-height: 26px;">
                    At Bluefifth, we create high-quality knitwear that blends comfort, style, and affordability. Our pieces are designed for everyday life—soft, breathable, and effortlessly modern.
                </p>
            </div>
            <div class="col-md-4">
                <h5>Newsletter</h5>
                <p>Subscribe to receive updates, access to exclusive deals, and more.</p>
                <form onsubmit="subscribeNewsletter(event)">
                    <input type="email" id="newsletterEmail" placeholder="Enter your email address" required />
                    <button type="submit" class="subscribe-btn">Subscribe</button>
                </form>
                <div class="payments mt-4">
                    <img src="../assets/images/payment-methods.png" alt="payment">
                </div>
            </div>
        </div>
    </div>
</footer>

<div class="modal fade" id="shippingReturnsModal" tabindex="-1" role="dialog" aria-labelledby="shippingReturnsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shippingReturnsModalLabel">Shipping & Returns</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <h3>Shipping Policy</h3>
                <ul class="p-3">
                    <li><strong>Order Processing Time:</strong> All orders are processed within 1–3 business days.</li>
                    <li><strong>Delivery Time:</strong> Once shipped, orders are typically delivered within 4–7 business days across India. (Remote areas may take longer.)</li>
                    <li><strong>Shipping Charges:</strong>
                        <ul class="pl-3">
                            <li>Free shipping on all prepaid orders above 2 pieces.</li>
                            <li>A standard shipping fee of ₹150 applies for orders below 2 Pieces.</li>
                        </ul>
                    </li>
                    <li><strong>Order Tracking:</strong> You will receive a tracking link via email once your order has been shipped.</li>
                </ul>
                <hr>
                <h3>Return & Exchange Policy</h3>
                <ul class="p-3">
                    <li><strong>Eligibility:</strong> Returns and exchanges are accepted within 7 days of delivery. Items must be unused, unwashed, and in their original packaging with tags.</li>
                    <li><strong>Return Process:</strong>
                        <ol class="pl-3">
                            <li>Enable Return Request.</li>
                            <li>Our team will arrange a reverse pickup (where available).</li>
                            <li>Once approved, you can choose between an exchange or store credit/refund.</li>
                        </ol>
                    </li>
                    <li><strong>Refund Timeline:</strong> Refunds are processed within 5–7 working days.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="contactUsModal" tabindex="-1" role="dialog" aria-labelledby="contactUsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactUsModalLabel">Contact Us</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <p><strong>Chanra & Velman Ventures Private Limited</strong></p>
                <p>Email: info@bluefifth.in</p>
                <p>Address - 160, Housing Unit, Dharapuram, Tamil Nadu - 638656</p>

                <hr>
                <form id="contactForm" onsubmit="openMailApp(event)">
                    <div class="form-group">
                        <label for="contactName">Name</label>
                        <input type="text" class="form-control" id="contactName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="contactEmail">Email ID</label>
                        <input type="email" class="form-control" id="contactEmail" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="contactOrder">Order ID (Optional)</label>
                        <input type="text" class="form-control" id="contactOrder" name="order_id">
                    </div>
                    <div class="form-group">
                        <label for="contactMessage">Message</label>
                        <textarea class="form-control" id="contactMessage" name="message" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Submit</button>
                </form>
                <script>
                    function openMailApp(event) {
                        event.preventDefault(); // stop normal form submission
                    
                        // Get form values
                        let name = document.getElementById("contactName").value;
                        let email = document.getElementById("contactEmail").value;
                        let order = document.getElementById("contactOrder").value;
                        let message = document.getElementById("contactMessage").value;
                    
                        // Prepare subject and body
                        let subject = encodeURIComponent("Contact Us - Bluefifth");
                        let body = encodeURIComponent(
                            "Name: " + name + "\n" +
                            "Email: " + email + "\n" +
                            "Order ID: " + (order || "N/A") + "\n\n" +
                            "Message:\n" + message
                        );
                    
                        // Open external mail app with pre-filled data
                        window.location.href = "mailto:info@bluefifth.in?subject=" + subject + "&body=" + body;
                    }
                </script>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="termsAndConditionsModal" tabindex="-1" role="dialog" aria-labelledby="termsAndConditionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsAndConditionsModalLabel">Terms & Conditions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <p>These terms and conditions govern the use of the Bluefifth website and services. By using our website, you agree to be bound by these terms.</p>
                <hr>
                <h3>1. General</h3>
                <ul class="pl-3">
                    <li>This website is operated by Bluefifth.</li>
                    <li>By using our services, you agree to be bound by these Terms.</li>
                    <li>We may update these Terms from time to time. Your continued use of the website means you accept the updated version.</li>
                </ul>
                <hr>
                <h3>2. Products & Orders</h3>
                <ul class="pl-3">
                    <li>We do our best to show product details and colors accurately. However, slight variations may occur due to screen settings or fabric nature.</li>
                    <li>Placing an order means you are making an offer to purchase. We reserve the right to accept or reject any order.</li>
                    <li>In rare cases (stock unavailability, incorrect pricing, or payment issues), we may cancel your order and notify you immediately.</li>
                </ul>
                <hr>
                <h3>3. Pricing & Payments</h3>
                <ul class="pl-3">
                    <li>All prices are listed in **Indian Rupees (INR)** and include taxes unless otherwise stated.</li>
                    <li>We accept payments via secure gateways (UPI, cards, wallets, net banking, etc.).</li>
                    <li>If there’s a pricing error, we may cancel the order or request additional payment before dispatch.</li>
                </ul>
                <hr>
                <h3>4. Shipping & Delivery</h3>
                <ul class="pl-3">
                    <li>Orders are usually processed within 1–3 business days.</li>
                    <li>Delivery timelines are 4–7 business days across most of India (remote areas may take longer).</li>
                    <li>Once shipped, you will receive tracking details by email.</li>
                    <li>We are not responsible for delays caused by courier services or unforeseen events.</li>
                </ul>
                <p>(For more details, check our Shipping & Returns Policy.)</p>
                <hr>
                <h3>5. Returns & Refunds</h3>
                <ul class="pl-3">
                    <li>We want you to be happy with your purchase! Returns and exchanges are accepted within 7 days of delivery, subject to our Return Policy.</li>
                    <li>Returned items must be unused, unwashed, and in original condition with tags.</li>
                    <li>Certain items like innerwear, sale items, and gift cards are not eligible for return.</li>
                    <li>Refunds are processed within 5–7 working days after quality checks.</li>
                </ul>
                <hr>
                <h3>6. Use of Website</h3>
                <ul class="pl-3">
                    <li>You agree not to misuse the website (e.g., hacking, spamming, or misrepresenting information).</li>
                    <li>You must provide accurate and up-to-date information while placing an order.</li>
                </ul>
                <hr>
                <h3>7. Intellectual Property</h3>
                <ul class="pl-3">
                    <li>All logos, designs, images, text, and other content on this site belong to Bluefifth.</li>
                    <li>You cannot copy, reproduce, or use our content for commercial purposes without written permission.</li>
                </ul>
                <hr>
                <h3>8. Limitation of Liability</h3>
                <ul class="pl-3">
                    <li>While we ensure top quality, Bluefifth will not be liable for indirect or incidental damages arising from the use of our products or services.</li>
                    <li>Our maximum liability is limited to the value of the product you purchased.</li>
                </ul>
                <hr>
                <h3>9. Privacy</h3>
                <ul class="pl-3">
                    <li>Your privacy matters to us. Personal information is collected and used only in line with our Privacy Policy.</li>
                </ul>
                <hr>
                <h3>10. Referral Program Terms</h3>
                <ul class="pl-3">
                    <li>We love it when our customers spread the word! If you take part in our Referral Program, here’s how it works:
                        <ul class="pl-3 mt-2">
                            <li><strong>Eligibility:</strong> The program is open to all customers with a valid Bluefifth account.</li>
                            <li><strong>Earning Rewards:</strong> You’ll receive referral bonus, discounts, or commissions only when your referred customer makes a successful purchase (conditions may vary by campaign).</li>
                            <li><strong>Fair Use:</strong> Self-referrals or creating multiple fake accounts to earn rewards are not allowed. Any misuse may lead to cancellation of rewards and suspension from the program.</li>
                            <li><strong>Reward Usage:</strong> Referral rewards are non-transferable and cannot be exchanged for cash unless stated otherwise.</li>
                            <li><strong>Program Changes:</strong> Bluefifth reserves the right to modify, pause, or stop the referral program at any time without prior notice.</li>
                            <li><strong>Final Decision:</strong> In case of disputes, Bluefifth’s decision will be final.</li>
                        </ul>
                    </li>
                </ul>
                <hr>
                <h3>11. Governing Law</h3>
                <ul class="pl-3">
                    <li>These Terms are governed by the laws of India.</li>
                    <li>Any disputes will be subject to the jurisdiction of the courts in Coimbatore, Tamil Nadu.</li>
                </ul>
                <hr>
                <h3>12. Contact Us</h3>
                <p>Have questions about these Terms? We’re here to help! 📧 info@bluefifth.in</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="affiliateTermsModal" tabindex="-1" role="dialog" aria-labelledby="affiliateTermsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="affiliateTermsModalLabel">Affiliate Program – Terms & Conditions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <p>Welcome to the Bluefifth Affiliate Program! These Terms govern your participation in our Affiliate Program. By signing up, you agree to follow the rules below.</p>
                <hr>
                <h3>1. Eligibility</h3>
                <ul class="pl-3">
                    <li>The program is open to individuals, influencers, and businesses with a valid bank account and PAN card (for Indian residents).</li>
                    <li>Bluefifth reserves the right to accept or reject any affiliate application.</li>
                </ul>
                <hr>
                <h3>2. Affiliate Commission</h3>
                <ul class="pl-3">
                    <li>Affiliates earn a commission on every successful purchase made through their unique referral link or code.</li>
                    <li>The commission rate will be communicated at the time of joining and may vary by product or campaign.</li>
                    <li>Commissions are only paid on completed, non-returned, and fully paid orders.</li>
                    <li>Any cancelled, refunded, or fraudulent transactions will not qualify for commission.</li>
                </ul>
                <hr>
                <h3>3. Payouts</h3>
                <ul class="pl-3">
                    <li>Payouts are processed on a monthly basis once the minimum payout threshold of **₹1000** is reached.</li>
                    <li>Payments will be made via bank transfer/UPI.</li>
                    <li>Affiliates are responsible for providing accurate payment details. Bluefifth is not liable for failed payments due to incorrect information.</li>
                </ul>
                <hr>
                <h3>4. Fair Use Policy</h3>
                <ul class="pl-3">
                    <li>Affiliates must promote Bluefifth honestly and ethically.</li>
                    <li>Misleading claims, spam, coupon misuse, self-purchases, or fake referrals are strictly prohibited.</li>
                    <li>Any violation may result in termination from the program and forfeiture of unpaid commissions.</li>
                </ul>
                <hr>
                <h3>5. Content & Branding</h3>
                <ul class="pl-3">
                    <li>Affiliates may use official Bluefifth logos, banners, and creatives provided by us.</li>
                    <li>You may not alter, misuse, or create false advertising that harms the Bluefifth brand.</li>
                    <li>Paid ads (Google, Meta, etc.) using Bluefifth’s brand name without written approval are not allowed.</li>
                </ul>
                <hr>
                <h3>6. Taxes</h3>
                <ul class="pl-3">
                    <li>Affiliates are responsible for complying with applicable tax laws.</li>
                    <li>TDS (Tax Deducted at Source) will be applied as per Indian government regulations.</li>
                </ul>
                <hr>
                <h3>7. Program Changes</h3>
                <ul class="pl-3">
                    <li>Bluefifth reserves the right to modify, suspend, or terminate the affiliate program at any time without prior notice.</li>
                    <li>Commission rates and payment terms may be updated. Affiliates will be notified of any major changes.</li>
                </ul>
                <hr>
                <h3>8. Limitation of Liability</h3>
                <ul class="pl-3">
                    <li>Bluefifth is not responsible for any indirect, incidental, or loss of income arising from participation in the affiliate program.</li>
                    <li>Our maximum liability is limited to the commission earned by the affiliate.</li>
                </ul>
                <hr>
                <h3>9. Governing Law</h3>
                <ul class="pl-3">
                    <li>These terms are governed by the laws of India.</li>
                    <li>Any disputes will be subject to the jurisdiction of the courts in Coimbatore, Tamil Nadu.</li>
                </ul>
                <hr>
                <h3>10. Contact Us</h3>
                <p>For affiliate-related queries: 📧 info@bluefifth.in</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="privacyPolicyModal" tabindex="-1" role="dialog" aria-labelledby="privacyPolicyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyPolicyModalLabel">Privacy Policy</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <p>At Bluefifth, your trust means everything to us. This Privacy Policy explains how we collect, use, and protect your personal information when you shop with us or use our services.</p>
                <hr>
                <h3>1. Information We Collect</h3>
                <ul class="pl-3">
                    <li><strong>Personal details:</strong> name, email, phone number, shipping & billing address.</li>
                    <li><strong>Payment details:</strong> (processed securely by our payment partners – we do not store card/UPI details).</li>
                    <li><strong>Order history & preferences:</strong> products purchased, returns, wishlists.</li>
                    <li><strong>Device & browsing data:</strong> cookies, IP address, browser type, to improve your shopping experience.</li>
                </ul>
                <hr>
                <h3>2. How We Use Your Information</h3>
                <ul class="pl-3">
                    <li>Process and deliver your orders smoothly.</li>
                    <li>Send order updates, offers, and customer support messages.</li>
                    <li>Improve our website, products, and customer experience.</li>
                    <li>Prevent fraud, unauthorized transactions, and ensure secure payments.</li>
                </ul>
                <p>We never sell or rent your personal information to third parties.</p>
                <hr>
                <h3>3. Cookies</h3>
                <ul class="pl-3">
                    <li>Our website uses cookies to enhance browsing, remember your preferences, and serve personalized recommendations.</li>
                    <li>You can disable cookies anytime in your browser, but some features may not work properly.</li>
                </ul>
                <hr>
                <h3>4. Sharing of Information</h3>
                <ul class="pl-3">
                    <li>We may share limited data with:
                        <ul class="pl-3 mt-2">
                            <li>Trusted service providers (couriers, payment gateways, IT partners) strictly for order fulfillment.</li>
                            <li>Legal authorities if required by law.</li>
                        </ul>
                    </li>
                </ul>
                <p>No third party will ever receive your data for advertising without your consent.</p>
                <hr>
                <h3>5. Payments & Security</h3>
                <ul class="pl-3">
                    <li>All online payments are handled by secure, payment gateways.</li>
                    <li>Bluefifth does not store your full credit card, debit card, or UPI details.</li>
                    <li>We use encryption and strict security measures to keep your data safe.</li>
                </ul>
                <hr>
                <h3>6. Your Rights</h3>
                <ul class="pl-3">
                    <li>You have the right to:
                        <ul class="pl-3 mt-2">
                            <li>Access, update, or delete your personal information.</li>
                            <li>Opt-out of promotional emails anytime.</li>
                            <li>Request details of what data we hold about you.</li>
                        </ul>
                    </li>
                </ul>
                <p>For any privacy-related requests, write to privacy@bluefifth.in.</p>
                <hr>
                <h3>7. Children’s Privacy</h3>
                <p>Our website is not intended for children under 13. We do not knowingly collect information from minors.</p>
                <hr>
                <h3>8. Changes to Policy</h3>
                <p>We may update this Privacy Policy from time to time. Any changes will be posted on this page with an updated date.</p>
                <hr>
                <h3>9. Contact Us</h3>
                <p>For questions about this Privacy Policy: 📧 info@bluefifth.in</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>



    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <script>
    // SIMPLIFIED AUTH FOR NON-INDEX PAGES
        (function() {
            'use strict';
            
            // Configuration
            const GOOGLE_CLIENT_ID = "340757900430-i8nl6l45ndveq9jmbvbah7ugquauj803.apps.googleusercontent.com";
            const AUTH_ENDPOINT = "../auth/google-callback.php";  // FIXED PATH
            
            let currentUser = <?= $isLoggedIn ? 'true' : 'false' ?>;
            let googleInitialized = false;
            
            // Initialize Google Sign-In API (WITHOUT silent auth)
            function initializeGoogle() {
                if (googleInitialized || typeof google === 'undefined' || !google.accounts?.id) {
                    return false;
                }
                
                try {
                    google.accounts.id.initialize({
                        client_id: GOOGLE_CLIENT_ID,
                        callback: handleAuthResponse,
                        auto_select: false,
                        cancel_on_tap_outside: false,
                        use_fedcm_for_prompt: false,
                        itp_support: true  // ADD THIS
                    });
                    
                    googleInitialized = true;
                    return true;
                } catch (error) {
                    console.error('Google initialization error:', error);
                    return false;
                }
            }
            
            // Handle authentication response
            function handleAuthResponse(response) {
                if (!response.credential) {
                    console.error('No credential in response');
                    return;
                }
                
                console.log('Processing login...');
                
                fetch(AUTH_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        id_token: response.credential,
                        silent_auth: false,
                        manual_auth: true,
                        checkout_auth: true
                    }),
                    credentials: 'same-origin'
                })
                .then(response => {
                    console.log('Raw response status:', response.status);
                    
                    // Get the raw text first to see what's actually returned
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response text:', text);
                    
                    // Try to parse as JSON
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed auth response:', data);
                        
                        if (data.success) {
                            currentUser = true;
                            
                            // ✅ CHECK FOR CHECKOUT INTENT
                            if (sessionStorage.getItem('checkout_after_login') === 'true') {
                                sessionStorage.removeItem('checkout_after_login');
                                showNotification('Login successful! Redirecting to checkout...', 'success');
                                setTimeout(() => {
                                    window.location.href = '../checkout.php';
                                }, 1000);
                            } else {
                                showNotification('Login successful! Redirecting...', 'success');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            }
                        } else {
                            showNotification('Login failed: ' + (data.message || 'Unknown error'), 'error');
                        }
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Response was not JSON:', text.substring(0, 500));
                        showNotification('Server error during login. Please try again.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Auth fetch error:', error);
                    showNotification('Network error during login. Please try again.', 'error');
                });
            }
            
            // Initialize Google (no silent auth attempt)
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeGoogle);
            } else {
                initializeGoogle();
            }
            
            // MANUAL LOGIN FUNCTION ONLY
            window.triggerOneTapLogin = function() {
                if (currentUser) {
                    console.log('User already logged in');
                    return;
                }
                
                if (!googleInitialized && !initializeGoogle()) {
                    showNotification('Authentication system not ready. Please refresh and try again.', 'error');
                    return;
                }
                
                try {
                    google.accounts.id.prompt((notification) => {
                        if (notification.isNotDisplayed()) {
                            showNotification('Login popup blocked. Please refresh and try again.', 'warning');
                        }
                    });
                } catch (error) {
                    console.error('One-Tap error:', error);
                    showNotification('Login system error. Please refresh and try again.', 'error');
                }
            };

        })();

    // MAIN APPLICATION FUNCTIONS - EXACT COPY FROM INDEX.PHP
    let userWalletData = null;

    function showReferralPopup() {
        document.getElementById('modal-overlay').classList.add('show');
        document.getElementById('referral-popup').classList.add('show');
        
        // Use the PHP variable directly instead of JavaScript variable
        <?php if ($isLoggedIn): ?>
            loadReferralData();
        <?php else: ?>
            showGuestReferralContent();
        <?php endif; ?>
    }

    function showWalletPopup() {
        document.getElementById('modal-overlay').classList.add('show');
        document.getElementById('wallet-popup').classList.add('show');
        
        // Use the PHP variable directly instead of JavaScript variable
        <?php if ($isLoggedIn): ?>
            loadWalletData();
        <?php else: ?>
            showGuestWalletContent();
        <?php endif; ?>
    }

    // Show guest referral content - EXACT COPY FROM INDEX.PHP
    function showGuestReferralContent() {
        const content = `
            <div class="guest-login-section">
                <h3>🔗 My Referrals</h3>
                <p style="margin-bottom: 1.5rem; color: #666;">Tap to login to view your referral data, links and codes!</p>
                
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                    <strong>💰 Earn with referrals:</strong><br>
                    • 10% commission on first purchases<br>
                    • 5% commission on subsequent purchases<br>
                    • Claims available on 30th & 31st of every month
                </div>
                
                <button class="popup-btn" onclick="triggerOneTapLogin()">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login to View Data
                </button>
            </div>
        `;
        
        document.getElementById('referral-content').innerHTML = content;
    }

    // Show guest wallet content - EXACT COPY FROM INDEX.PHP
    function showGuestWalletContent() {
        const content = `
            <div class="guest-login-section">
                <h3>💰 My Wallet</h3>
                <p style="margin-bottom: 1.5rem; color: #666;">Tap to login to view your wallet balance and transaction history!</p>
                
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                    <strong>💎 Wallet Features:</strong><br>
                    • Track your earnings from referrals<br>
                    • View detailed transaction history<br>
                    • Claim your points as real money<br>
                    • Monitor pending and available balance
                </div>
                
                <button class="popup-btn" onclick="triggerOneTapLogin()">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login to View Wallet
                </button>
            </div>
        `;
        
        document.getElementById('wallet-content').innerHTML = content;
    }

    // Close all popups
    function closeAllPopups() {
        document.getElementById('modal-overlay').classList.remove('show');
        document.getElementById('referral-popup').classList.remove('show');
        document.getElementById('wallet-popup').classList.remove('show');
    }

    // Load referral data - EXACT COPY FROM INDEX.PHP
    function loadReferralData() {
        const basePath = '<?= $basePath ?>';
        fetch(basePath + 'wallet/get-balance.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReferralData(data);
                } else {
                    document.getElementById('referral-content').innerHTML = 
                        '<div class="alert alert-danger">Failed to load referral data: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading referral data:', error);
                document.getElementById('referral-content').innerHTML = 
                    '<div class="alert alert-danger">Error loading referral data. Please try again.</div>';
            });
    }

    // Display referral data - EXACT COPY FROM INDEX.PHP
    function displayReferralData(data) {
        const referral = data.referral || {};
        const totalPoints = (data.balance.points || 0) + (data.balance.pending_points || 0);
        
        const content = `
            <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number">${referral.visit_count || 0}</span>
                <span class="stat-label">👥 Total Visits</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">${referral.purchase_count || 0}</span>
                <span class="stat-label">🛒 Total Purchases</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">₹${totalPoints}</span>
                <span class="stat-label">💰 Points Earned</span>
            </div>
        </div>
            
            <div class="mt-4">
                <h5>🔗 Your Referral Links</h5>
                
                <div style="margin: 1.5rem 0;">
                    <label><strong>Referral Code:</strong></label>
                    <div class="copy-input">
                        <input type="text" id="referral-code" value="${referral.code || ''}" readonly>
                        <button onclick="copyReferralCode()">Copy</button>
                    </div>
                </div>

                <div style="margin: 1.5rem 0;">
                    <label><strong>Referral Link:</strong></label>
                    <div class="copy-input">
                    <input type="text" id="referral-link" value="${referral.link || ''}" readonly>
                    <div class="d-flex justify-content-center w-100 d-block d-lg-none">
                        <button class="rounded-right-0 border-right border-light w-100 w-md-0" onclick="copyReferralLink()" ><i class="fa-solid fa-copy"></i></button>
                        <button class="w-100 w-md-0 rounded-left-0" onclick="shareLinkViaWebShare()"><i class="fa-solid fa-share"></i></button>
                    </div>
                   
                    <button class="rounded-0 border-right border-light d-none d-lg-block" onclick="copyReferralLink()" ><i class="fa-solid fa-copy"></i></button>
                    <button class=" d-none d-lg-block" onclick="shareLinkViaWebShare()"><i class="fa-solid fa-share"></i></button>
                    
                </div>
                </div>

                
            
            </div>
        `;
        
        document.getElementById('referral-content').innerHTML = content;
    }
    // Add this function to your existing script block
function shareLinkViaWebShare() {
    const referralLink = document.getElementById('referral-link').value;
    const shareData = {
        title: 'Check out this awesome store!',
        text: 'I found this great store and wanted to share my referral link with you. You can get discounts and I can earn points!',
        url: referralLink
    };

    // Check if the Web Share API is supported by the browser
    if (navigator.share) {
        navigator.share(shareData)
            .then(() => {
                console.log('Link shared successfully');
                showNotification('Link shared successfully!', 'success');
            })
            .catch((error) => {
                console.error('Error sharing:', error);
                showNotification('Could not share link. Please try again or copy the link manually.', 'error');
            });
    } else {
        // Fallback for browsers that do not support the Web Share API
        // This will attempt to open a new window to WhatsApp Web
        const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(shareData.text + ' ' + shareData.url)}`;
        window.open(whatsappUrl, '_blank');
        showNotification('Web Share API not supported. Redirecting to WhatsApp...', 'info');
    }
}

    // Load wallet data - EXACT COPY FROM INDEX.PHP
    function loadWalletData() {
        const basePath = '<?= $basePath ?>';
        fetch(basePath + 'wallet/get-balance.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayWalletData(data);
                } else {
                    document.getElementById('wallet-content').innerHTML = 
                        '<div class="alert alert-danger">Failed to load wallet data: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading wallet data:', error);
                document.getElementById('wallet-content').innerHTML = 
                    '<div class="alert alert-danger">Error loading wallet data. Please try again.</div>';
            });
    }

    // Display wallet data - EXACT COPY FROM INDEX.PHP
    function displayWalletData(data) {
        const balance = data.balance || {};
        const availablePoints = balance.points || 0;
        const pendingPoints = balance.pending_points || 0;
        const totalPoints = availablePoints + pendingPoints;
        
        // Check claim eligibility
        const currentDay = new Date().getDate();
        const isClaimDate = currentDay === 30 || currentDay === 31;
        const hasEnoughPoints = totalPoints >= 100;
        const canClaim = isClaimDate && hasEnoughPoints;
        
        const content = `
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number">₹${availablePoints}</span>
                    <span class="stat-label">💰 Available Points</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">₹${pendingPoints}</span>
                    <span class="stat-label">⏳ Pending Points</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">₹${totalPoints}</span>
                    <span class="stat-label">💎 Total Balance</span>
                </div>
            </div>
            
            <div class="mt-4">
                <button id="claim-btn" class="popup-btn ${!canClaim ? 'disabled' : ''}" 
                        onclick="claimPoints()" ${!canClaim ? 'disabled' : ''}>
                    🎁 Claim ₹${totalPoints} as Money
                </button>
                
                <div class="mt-3">
                    ${!isClaimDate ? 
                        '<div class="alert-custom alert-warning">⏰ Claims available on 30th & 31st only</div>' : 
                        !hasEnoughPoints ? 
                            '<div class="alert-custom alert-warning">❌ Minimum ₹100 required to claim</div>' : 
                            '<div class="alert-custom alert-success">✅ Ready to claim! Today is claim date.</div>'
                    }
                </div>
            </div>
            
            <h5 class="mt-4">💳 Transaction History</h5>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(data.transactions || []).length > 0 ? 
                            data.transactions.map(transaction => {
                                let statusHtml = '';
                                let typeDisplay = transaction.transaction_type;
                                
                                switch (transaction.transaction_type.toLowerCase()) {
                                    case 'earned':
                                        statusHtml = '<span class="status-badge status-earned">✅ Completed</span>';
                                        typeDisplay = 'Earned';
                                        break;
                                    case 'claimed':
                                        statusHtml = '<span class="status-badge status-claimed">⏳ Pending Admin</span>';
                                        typeDisplay = 'Claimed';
                                        break;
                                    case 'processed':
                                        statusHtml = '<span class="status-badge status-processed">💰 Paid</span>';
                                        typeDisplay = 'Processed';
                                        break;
                                    case 'rejected':
                                        statusHtml = '<span class="status-badge status-rejected">❌ Rejected</span>';
                                        typeDisplay = 'Rejected';
                                        break;
                                    case 'approved':
                                        statusHtml = '<span class="status-badge status-approved">✅ Approved</span>';
                                        typeDisplay = 'Approved';
                                        break;
                                    default:
                                        statusHtml = '<span class="status-badge">' + transaction.transaction_type + '</span>';
                                        break;
                                }
                                
                                return `
                                    <tr>
                                        <td>${new Date(transaction.created_at).toLocaleDateString()}</td>
                                        <td>${typeDisplay}</td>
                                        <td>₹${Math.abs(transaction.points)}</td>
                                        <td>${statusHtml}</td>
                                    </tr>
                                `;
                            }).join('') : 
                            '<tr><td colspan="4" class="text-center text-muted">No transactions yet</td></tr>'
                        }
                    </tbody>
                </table>
            </div>
        `;
        
        document.getElementById('wallet-content').innerHTML = content;
    }

    // Claim points function - EXACT COPY FROM INDEX.PHP
    function claimPoints() {
        if (!currentUser) return;
        
        if (!confirm('Are you sure you want to claim your points? This will submit your claim to admin for processing.')) {
            return;
        }

        // Disable button during processing
        const claimBtn = document.getElementById('claim-btn');
        const originalText = claimBtn.textContent;
        claimBtn.disabled = true;
        claimBtn.textContent = '⏳ Processing...';

        const basePath = '<?= $basePath ?>';
        fetch(basePath + 'referral/claim-points.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            // Re-enable button
            claimBtn.disabled = false;
            claimBtn.textContent = originalText;
            
            if (data.success) {
                showNotification(`✅ Claim submitted successfully for ₹${data.points_claimed}! Will be processed within 24 hours.`, 'success');
                // Reload wallet data
                loadWalletData();
            } else {
                showNotification('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error claiming points:', error);
            showNotification('❌ An error occurred while claiming points. Please try again.', 'error');
            
            // Re-enable button
            claimBtn.disabled = false;
            claimBtn.textContent = originalText;
        });
    }

    // Copy referral code - EXACT COPY FROM INDEX.PHP
    function copyReferralCode() {
        const codeField = document.getElementById('referral-code');
        if (codeField) {
            codeField.select();
            document.execCommand('copy');
            showNotification('Referral code copied! 📋', 'success');
        }
    }

    // Copy referral link - EXACT COPY FROM INDEX.PHP
    function copyReferralLink() {
        const linkField = document.getElementById('referral-link');
        if (linkField) {
            linkField.select();
            document.execCommand('copy');
            showNotification('Referral link copied! 🔗', 'success');
        }
    }

    // Fixed logout function - EXACT COPY FROM INDEX.PHP
    function logoutUser() {
        if (!confirm('Are you sure you want to logout?')) return;
        
        // Fixed basePath - remove PHP variable that might be undefined
        const basePath = '../';  // This works from all shop pages
        fetch(basePath + 'auth/logout.php', { 
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear Google sign-in state
                if (typeof google !== 'undefined' && google.accounts) {
                    google.accounts.id.disableAutoSelect();
                }
                
                // Set current user to false
                currentUser = false;
                
                // Show appropriate message based on cart preservation
                const message = data.cart_preserved ? 
                    `Logged out successfully. ${data.cart_item_count} cart items preserved.` : 
                    'Logged out successfully.';
                
                showNotification(message, 'success');
                
                // Reload page to show guest state
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification('Logged out.', 'info');
                currentUser = false;
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Logout error:', error);
            // Still reload page even if error occurs
            currentUser = false;
            showNotification('Logged out.', 'info');
            window.location.reload();
        });
    }

    // Search functionality - EXACT COPY FROM INDEX.PHP
    function toggleSearch() {
        $('#searchModal').modal('show');
        document.getElementById('searchInput').focus();
    }

    async function performSearch() {
        const searchTerm = document.getElementById('searchInput').value.trim();
        
        if (searchTerm.length < 2) {
            return;
        }

        try {
            const basePath = '<?= $basePath ?>';
            const response = await fetch(`${basePath}shop/api/search.php?q=${encodeURIComponent(searchTerm)}`);
            const data = await response.json();

            if (data.success) {
                displaySearchResults(data.products);
            } else {
                document.getElementById('searchResults').innerHTML = '<p class="text-muted">No products found.</p>';
            }
        } catch (error) {
            console.error('Search error:', error);
            document.getElementById('searchResults').innerHTML = '<p class="text-danger">Search failed. Please try again.</p>';
        }
    }

    function displaySearchResults(products) {
        const resultsContainer = document.getElementById('searchResults');
        
        if (products.length === 0) {
            resultsContainer.innerHTML = '<p class="text-muted">No products found.</p>';
            return;
        }

        const basePath = '<?= $basePath ?>';
        let html = '<div class="row">';
        products.forEach(product => {
            html += `
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <img src="${product.primary_image || basePath + 'assets/images/default-product.jpg'}" 
                            class="card-img-top" style="height: 200px; object-fit: cover;">
                        <div class="card-body">
                            <h6 class="card-title">${product.name}</h6>
                            <p class="card-text">₹${parseFloat(product.price).toFixed(2)}</p>
                            <a href="${basePath}shop/product.php?id=${product.id}" class="btn btn-primary btn-sm">View Product</a>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        resultsContainer.innerHTML = html;
    }

    // Utility functions - EXACT COPY FROM INDEX.PHP
    function showNotification(message, type) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="close" onclick="this.parentElement.remove()">
                <span>&times;</span>
            </button>
        `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Handle escape key to close modals - EXACT COPY FROM INDEX.PHP
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllPopups();
        }
    });

    // Initialize page on DOM content loaded - EXACT COPY FROM INDEX.PHP
    document.addEventListener('DOMContentLoaded', function() {
        // Search on Enter key
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
        }
    });

    // Auto-refresh user data every 30 seconds if logged in - EXACT COPY FROM INDEX.PHP
    if (currentUser) {
        setInterval(() => {
            // Refresh wallet balance in navbar
            const basePath = '<?= $basePath ?>';
            fetch(basePath + 'wallet/get-balance.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const totalPoints = (data.balance.points || 0) + (data.balance.pending_points || 0);
                        const walletDisplay = document.querySelector('.text-success');
                        if (walletDisplay) {
                            walletDisplay.textContent = '₹' + totalPoints.toLocaleString();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing wallet balance:', error);
                });
        }, 30000);
    }

    console.log('🎉 Complete integrated authentication and shopping system loaded');
    </script>                        

    <script>
        // Cart management functions
        async function updateQuantity(cartId, change, maxStock) {
            const row = document.querySelector(`[data-cart-id="${cartId}"]`);
            const quantityInput = row.querySelector('.quantity-input');
            const currentQuantity = parseInt(quantityInput.value);
            let newQuantity = currentQuantity + change;
            
            // Validate quantity
            if (newQuantity < 1) newQuantity = 1;
            if (newQuantity > maxStock) {
                showNotification(`Only ${maxStock} items available in stock`, 'warning');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_cart');
                <?php if ($isLoggedIn): ?>
                    formData.append('cart_item_id', cartId);
                <?php else: ?>
                    formData.append('cart_key', cartId);
                <?php endif; ?>
                formData.append('quantity', newQuantity);
                
                const response = await fetch('api/cart.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update quantity input
                    quantityInput.value = newQuantity;
                    
                    // Recalculate totals
                    await refreshCartTotals();
                    
                    showNotification('Cart updated successfully', 'success');
                } else {
                    showNotification(data.message || 'Failed to update cart', 'error');
                }
                
            } catch (error) {
                console.error('Error updating cart:', error);
                showNotification('Error updating cart', 'error');
            }
        }

        async function removeFromCart(cartId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'remove_from_cart');
                <?php if ($isLoggedIn): ?>
                    formData.append('cart_item_id', cartId);
                <?php else: ?>
                    formData.append('cart_key', cartId);
                <?php endif; ?>
                
                const response = await fetch('api/cart.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove row from table
                    const row = document.querySelector(`[data-cart-id="${cartId}"]`);
                    row.remove();
                    
                    // Check if cart is empty
                    const remainingItems = document.querySelectorAll('#cartTableBody tr').length;
                    if (remainingItems === 0) {
                        location.reload(); // Reload to show empty cart message
                        return;
                    }
                    
                    // Update cart summary
                    await refreshCartTotals();
                    updateCartBadge(data.cart_summary?.item_count || 0);
                    
                    showNotification('Item removed from cart', 'success');
                } else {
                    showNotification(data.message || 'Failed to remove item', 'error');
                }
                
            } catch (error) {
                console.error('Error removing item:', error);
                showNotification('Error removing item from cart', 'error');
            }
        }

        async function clearCart() {
            if (!confirm('Are you sure you want to clear your entire cart?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'clear_cart');
                
                const response = await fetch('api/cart.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload(); // Reload to show empty cart
                } else {
                    showNotification(data.message || 'Failed to clear cart', 'error');
                }
                
            } catch (error) {
                console.error('Error clearing cart:', error);
                showNotification('Error clearing cart', 'error');
            }
        }

        async function refreshCartTotals() {
            try {
                const response = await fetch('api/cart.php?action=get_cart_summary');
                const data = await response.json();
                
                if (data.success) {
                    const summary = data.cart_summary;
                    const shippingCost = summary.total_amount >= 1000 ? 0 : 50;
                    const finalTotal = summary.total_amount + shippingCost;
                    
                    // Update summary display
                    document.getElementById('subtotalAmount').textContent = `₹${summary.total_amount.toFixed(2)}`;
                    document.getElementById('shippingAmount').textContent = shippingCost > 0 ? `₹${shippingCost.toFixed(2)}` : 'FREE';
                    document.getElementById('totalAmount').textContent = `₹${finalTotal.toFixed(2)} INR`;
                    
                    // Update item count in summary
                    document.querySelector('.summary-line:first-child span:first-child').textContent = `Subtotal (${summary.item_count} items)`;
                    
                    // Update free shipping progress
                    updateFreeShippingProgress(summary.total_amount);
                    
                    // Update individual item totals
                    const rows = document.querySelectorAll('#cartTableBody tr');
                    rows.forEach(row => {
                        const productId = row.dataset.productId;
                        const quantity = parseInt(row.querySelector('.quantity-input').value);
                        const priceText = row.querySelector('.product-variant:last-child').textContent;
                        const price = parseFloat(priceText.replace('₹', '').replace(',', ''));
                        const itemTotal = quantity * price;
                        row.querySelector('.item-total').textContent = `₹${itemTotal.toFixed(2)}`;
                    });
                }
            } catch (error) {
                console.error('Error refreshing cart totals:', error);
            }
        }

        function updateFreeShippingProgress(totalAmount) {
            const freeShippingThreshold = 1000;
            const progressBar = document.querySelector('.free-shipping-bar');
            const promoSection = document.querySelector('.promo-section');
            
            if (progressBar && promoSection) {
                const progress = Math.min(100, (totalAmount / freeShippingThreshold) * 100);
                progressBar.style.width = progress + '%';
                
                if (totalAmount >= freeShippingThreshold) {
                    promoSection.innerHTML = `
                        <i class="fas fa-check-circle mr-2"></i>
                        Congratulations! You qualify for FREE shipping
                    `;
                } else {
                    const remaining = freeShippingThreshold - totalAmount;
                    promoSection.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-truck mr-2"></i>
                                Add ₹${remaining.toFixed(2)} more for FREE shipping
                            </span>
                        </div>
                        <div class="free-shipping-progress">
                            <div class="free-shipping-bar" style="width: ${progress}%"></div>
                        </div>
                    `;
                }
            }
        }

        function proceedToCheckout() {
            // DIRECT CHECKOUT - NO LOGIN POPUP
            window.location.href = '../checkout.php';
        }

        // Search functionality
        function toggleSearch() {
            $('#searchModal').modal('show');
            setTimeout(() => document.getElementById('searchInput').focus(), 500);
        }

        function handleSearchKeypress(event) {
            if (event.key === 'Enter') {
                performSearch();
            }
        }

        async function performSearch() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            
            if (searchTerm.length < 2) {
                showNotification('Please enter at least 2 characters', 'warning');
                return;
            }

            try {
                const response = await fetch(`api/search.php?q=${encodeURIComponent(searchTerm)}`);
                const data = await response.json();

                if (data.success && data.products) {
                    displaySearchResults(data.products);
                } else {
                    document.getElementById('searchResults').innerHTML = '<p class="text-muted">No products found.</p>';
                }
            } catch (error) {
                console.error('Search error:', error);
                document.getElementById('searchResults').innerHTML = '<p class="text-danger">Search failed. Please try again.</p>';
            }
        }

        function displaySearchResults(products) {
            const resultsContainer = document.getElementById('searchResults');
            
            if (products.length === 0) {
                resultsContainer.innerHTML = '<p class="text-muted">No products found.</p>';
                return;
            }

            let html = '<div class="row">';
            products.forEach(product => {
                html += `
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <img src="${product.primary_image || '../assets/images/default-product.jpg'}" 
                                 class="card-img-top" style="height: 200px; object-fit: cover;" 
                                 alt="${product.name}">
                            <div class="card-body">
                                <h6 class="card-title">${product.name}</h6>
                                <p class="card-text">₹${parseFloat(product.price).toFixed(2)}</p>
                                <a href="product.php?id=${product.id}" class="btn btn-primary btn-sm">View Product</a>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            resultsContainer.innerHTML = html;
        }

        // Newsletter subscription
        async function subscribeNewsletter(event) {
            event.preventDefault();
            const email = document.getElementById('newsletterEmail').value;
            
            try {
                const response = await fetch('../api/newsletter.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({email: email})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Successfully subscribed to newsletter!', 'success');
                    document.getElementById('newsletterEmail').value = '';
                } else {
                    showNotification(data.message || 'Subscription failed', 'error');
                }
            } catch (error) {
                showNotification('Subscription failed', 'error');
            }
        }

        // User menu toggle
        function toggleUserMenu() {
            window.location.href = '../referral/dashboard.php';
        }

        // Utility functions
        function updateCartBadge(count) {
            const cartBadge = document.getElementById('cartBadge');
            if (count > 0) {
                if (cartBadge) {
                    cartBadge.textContent = count;
                } else {
                    const cartIcon = document.querySelector('.fa-bag-shopping');
                    const badge = document.createElement('span');
                    badge.id = 'cartBadge';
                    badge.className = 'position-absolute badge badge-danger';
                    badge.style.cssText = 'top: -8px; right: -8px; font-size: 0.7rem;';
                    badge.textContent = count;
                    cartIcon.parentElement.appendChild(badge);
                }
            } else {
                if (cartBadge) {
                    cartBadge.remove();
                }
            }
        }

        function showNotification(message, type) {
            // Remove existing notifications
            const existingAlerts = document.querySelectorAll('.alert-custom');
            existingAlerts.forEach(alert => alert.remove());
            
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'} alert-dismissible fade show alert-custom`;
            notification.innerHTML = `
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Save cart to localStorage for guests (backup)
        function saveGuestCartToStorage() {
            <?php if (!$isLoggedIn): ?>
                const cartData = {
                    items: [],
                    timestamp: Date.now()
                };
                
                // Get current cart items
                const rows = document.querySelectorAll('#cartTableBody tr');
                rows.forEach(row => {
                    const cartId = row.dataset.cartId;
                    const productId = row.dataset.productId;
                    const quantity = parseInt(row.querySelector('.quantity-input').value);
                    const sizeElements = row.querySelectorAll('.product-variant');
                    let size = '';
                    sizeElements.forEach(el => {
                        if (el.textContent.includes('Size:')) {
                            size = el.textContent.replace('Size: ', '').trim();
                        }
                    });
                    
                    cartData.items.push({
                        cart_key: cartId,
                        product_id: productId,
                        quantity: quantity,
                        size: size
                    });
                });
                
                localStorage.setItem('guest_cart_backup', JSON.stringify(cartData));
            <?php endif; ?>
        }

        // Load cart from localStorage for guests (if session lost)
        function loadGuestCartFromStorage() {
            <?php if (!$isLoggedIn): ?>
                const savedCart = localStorage.getItem('guest_cart_backup');
                if (savedCart) {
                    try {
                        const cartData = JSON.parse(savedCart);
                        // Check if cart data is recent (within 24 hours)
                        const hoursSinceBackup = (Date.now() - cartData.timestamp) / (1000 * 60 * 60);
                        
                        if (hoursSinceBackup > 24) {
                            localStorage.removeItem('guest_cart_backup');
                        }
                    } catch (error) {
                        console.error('Error loading guest cart from storage:', error);
                    }
                }
            <?php endif; ?>
        }

        // Auto-save cart changes for guests
        function autoSaveGuestCart() {
            <?php if (!$isLoggedIn): ?>
                // Save cart state every 30 seconds
                setInterval(saveGuestCartToStorage, 30000);
                
                // Save on page unload
                window.addEventListener('beforeunload', saveGuestCartToStorage);
            <?php endif; ?>
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize guest cart backup system for guests
            <?php if (!$isLoggedIn): ?>
                loadGuestCartFromStorage();
                autoSaveGuestCart();
            <?php endif; ?>

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+Enter or Cmd+Enter to checkout
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    <?php if ($isLoggedIn && !empty($cartItems)): ?>
                        window.location.href = '../checkout.php';
                    <?php elseif (!empty($cartItems)): ?>
                        proceedToCheckout();
                    <?php endif; ?>
                }
                
                // Escape to close modals
                if (e.key === 'Escape') {
                    $('.modal').modal('hide');
                }
            });

            // Add loading states to buttons
            document.querySelectorAll('.quantity-btn, .remove-btn').forEach(button => {
                button.addEventListener('click', function() {
                    this.style.opacity = '0.6';
                    this.style.pointerEvents = 'none';
                    
                    setTimeout(() => {
                        this.style.opacity = '1';
                        this.style.pointerEvents = 'auto';
                    }, 1000);
                });
            });

            // Periodic cart sync for logged-in users
            <?php if ($isLoggedIn): ?>
                setInterval(async function() {
                    try {
                        const response = await fetch('api/cart.php?action=get_cart_summary');
                        const data = await response.json();
                        if (data.success) {
                            updateCartBadge(data.cart_summary.item_count);
                        }
                    } catch (error) {
                        console.error('Cart sync error:', error);
                    }
                }, 60000); // Sync every minute
            <?php endif; ?>

            // Highlight recently updated items
            window.highlightCartItem = function(cartId) {
                const row = document.querySelector(`[data-cart-id="${cartId}"]`);
                if (row) {
                    row.style.backgroundColor = '#f8f9fa';
                    setTimeout(() => {
                        row.style.backgroundColor = '';
                    }, 2000);
                }
            };

            // Smooth scroll to top when cart updates
            window.scrollToTop = function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            };
        });

        // Heartbeat for session management
        <?php if ($isLoggedIn): ?>
            setInterval(function() {
                fetch('../auth/check-session.php')
                    .then(response => response.json())
                    .then(data => {
                        if (!data.logged_in) {
                            // Set user as logged out
                            currentUser = false;
                            
                            // Show notification and reload
                            showNotification('Your session has expired. The page will refresh.', 'warning');
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        }
                    })
                    .catch(error => console.error('Session check error:', error));
            }, 300000); // Check every 5 minutes
        <?php endif; ?>
        
        // Combo offer handling
        let comboReminderDismissed = false;
        
        // Combo offer handling with persistent dismissal state
        function showComboReminderPopup() {
            // Check if user has already dismissed the popup for this session
            const dismissalKey = 'combo_reminder_dismissed_' + <?= $cartSummary['item_count'] ?>;
            const isDismissed = sessionStorage.getItem(dismissalKey) === 'true';
            
            if (isDismissed) {
                // If user already dismissed the popup, proceed to checkout directly
                proceedToCheckoutDirectly();
                return;
            }
            
            const itemCount = <?= $cartSummary['item_count'] ?>;
            const message = "<?= addslashes(getComboReminderMessage($cartSummary['item_count'])) ?>";
            
            document.getElementById('comboReminderMessage').textContent = message;
            $('#comboReminderModal').modal('show');
        }
        
        function setComboReminderDismissed() {
            // Store dismissal state in sessionStorage with item count specific key
            const dismissalKey = 'combo_reminder_dismissed_' + <?= $cartSummary['item_count'] ?>;
            sessionStorage.setItem(dismissalKey, 'true');
            $('#comboReminderModal').modal('hide');
        }
        
        function continueShopping() {
            $('#comboReminderModal').modal('hide');
            window.location.href = '../index.php';
        }
        
        function continueCheckout() {
            // Set dismissal state and proceed to checkout
            const dismissalKey = 'combo_reminder_dismissed_' + <?= $cartSummary['item_count'] ?>;
            sessionStorage.setItem(dismissalKey, 'true');
            $('#comboReminderModal').modal('hide');
            
            proceedToCheckoutDirectly();
        }
        
        function proceedToCheckoutDirectly() {
            if (<?= $isLoggedIn ? 'true' : 'false' ?>) {
                window.location.href = '../checkout.php';
            } else {
                // For guests, trigger the existing proceedToCheckout function
                proceedToCheckout();
            }
        }
        
        // Clear dismissal state when cart items change (optional - for better UX)
        function clearComboReminderState() {
            // Clear all combo reminder dismissal states
            Object.keys(sessionStorage).forEach(key => {
                if (key.startsWith('combo_reminder_dismissed_')) {
                    sessionStorage.removeItem(key);
                }
            });
        }
        // Update refreshCartTotals function to handle combo pricing
        async function refreshCartTotals() {
            try {
                const response = await fetch('api/cart.php?action=get_cart_summary');
                const data = await response.json();
                
                if (data.success) {
                    const summary = data.cart_summary;
                    
                    // Apply combo pricing calculation
                    const comboResult = calculateComboPrice(summary.item_count, summary.total_amount);
                    const finalAmount = comboResult.total;
                    
                    const shippingCost = finalAmount >= 1000 ? 0 : 50;
                    const finalTotal = finalAmount + shippingCost;
                    
                    // Update summary display with combo pricing
                    const subtotalElement = document.getElementById('subtotalAmount');
                    if (comboResult.is_combo) {
                        subtotalElement.innerHTML = `
                            <span style="text-decoration: line-through; color: #999; font-size: 12px;">₹${summary.total_amount.toFixed(2)}</span><br>
                            <span style="color: #28a745; font-weight: 600;">₹${finalAmount.toFixed(2)}</span>
                            <small style="color: #28a745; display: block; font-size: 11px;">Combo Price!</small>
                        `;
                    } else {
                        subtotalElement.textContent = `₹${finalAmount.toFixed(2)}`;
                    }
                    
                    document.getElementById('shippingAmount').textContent = shippingCost > 0 ? `₹${shippingCost.toFixed(2)}` : 'FREE';
                    document.getElementById('totalAmount').textContent = `₹${finalTotal.toFixed(2)} INR`;
                    
                    // Update item count
                    document.querySelector('.summary-line:first-child span:first-child').textContent = `Subtotal (${summary.item_count} items)`;
                    
                    updateFreeShippingProgress(finalAmount);
                }
            } catch (error) {
                console.error('Error refreshing cart totals:', error);
            }
        }
        
        // Client-side combo calculation helper
        function calculateComboPrice(itemCount, regularTotal) {
            const combo3Price = 1199;
            const combo5Price = 1699;
            
            if (itemCount === 3) {
                return {
                    total: combo3Price,
                    is_combo: true,
                    combo_type: '3_for_1199',
                    savings: Math.max(0, regularTotal - combo3Price),
                    regular_total: regularTotal
                };
            } else if (itemCount === 5) {
                return {
                    total: combo5Price,
                    is_combo: true,
                    combo_type: '5_for_1699',
                    savings: Math.max(0, regularTotal - combo5Price),
                    regular_total: regularTotal
                };
            }
            
            return {
                total: regularTotal,
                is_combo: false,
                combo_type: null,
                savings: 0,
                regular_total: regularTotal
            };
        }
    </script>
    
    <!-- Combo Reminder Popup -->
<div class="modal" id="comboReminderModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🎁 Don't Miss Our Combo Offers!</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="setComboReminderDismissed()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <div class="combo-offers mb-3">
                    <div class="offer-item p-3 border rounded mb-2">
                        <h6 class="text-success">3 Products = ₹1199</h6>
                        <small class="text-muted">Save up to ₹500!</small>
                    </div>
                    <div class="offer-item p-3 border rounded">
                        <h6 class="text-success">5 Products = ₹1699</h6>
                        <small class="text-muted">Save up to ₹800!</small>
                    </div>
                </div>
                <p id="comboReminderMessage" class="text-warning font-weight-bold"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="continueShopping()">Continue Shopping</button>
                <button type="button" class="btn btn-secondary" onclick="continueCheckout()">Continue Checkout</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php
