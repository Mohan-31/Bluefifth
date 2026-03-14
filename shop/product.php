<?php
// shop/product.php - Dynamic Product Detail Page (FIXED & SYNCHRONIZED)
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';
require_once '../includes/referral-tracker.php';

// Check maintenance mode (but allow admins)
if (getSetting('maintenance_mode') === 'true') {
    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    if (!$isAdmin) {
        // Show maintenance page to non-admin users
        include 'maintenance.html';
        exit;
    }
}

//Define base path for authentication
$basePath = '../';

// Get product ID from URL
$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    header('Location: category.php');
    exit;
}

// Get product details
$product = getProductById($productId, true);

if (!$product || $product['status'] !== 'active') {
    header('Location: category.php');
    exit;
}

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;

// Get related products (FIXED - using correct function)
$relatedProducts = getRelatedProducts($productId, 4);

// Get all categories for navigation
$categories = getAllCategories();

// Get user's cart summary if logged in
$cartSummary = $isLoggedIn ? getCartSummary($currentUser['id']) : ['item_count' => 0];

// Get wallet balance if logged in
$walletBalance = $isLoggedIn ? getWalletBalance($currentUser['id']) : ['points' => 0, 'pending_points' => 0];

// Prepare product images
$productImages = $product['images'] ?? [];
$primaryImage = !empty($productImages) ? $productImages[0]['image_url'] : '../assets/images/default-product.jpg';

// Prepare sizes
$availableSizes = $product['sizes'] ?? [];

// Get product rating and reviews
$productRating = getProductRating($productId);
$productReviews = getProductReviews($productId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/css/style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
    <title><?= htmlspecialchars($product['name']) ?> - Bluefifth</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars(substr($product['description'] ?? '', 0, 160)) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($product['name']) ?>, fashion, clothing, <?= htmlspecialchars($product['category_name']) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($product['name']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(substr($product['description'] ?? '', 0, 160)) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($primaryImage) ?>">
    <meta property="og:type" content="product">
    <meta property="product:price:amount" content="<?= $product['price'] ?>">
    <meta property="product:price:currency" content="INR">
    
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700&display=swap");
        body {
            font-family: "Poppins", sans-serif;
            color: #333;
        }
        
        .product-images-container {
            position: relative;
        }
        
        .product-main-image {
            width: 100%;
            height: auto;
            max-height: 700px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .zoom-icon {
            position: absolute;
            top: 20px;
            left: 20px;
            background-color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .thumbnail-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            cursor: pointer;
            border: 1px solid #ddd;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .thumbnail.active {
            border: 2px solid #333;
        }
        
        .thumbnail:hover {
            transform: scale(1.05);
        }
        
        .carousel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
        }
        
        .carousel-control.prev {
            left: 0;
        }
        
        .carousel-control.next {
            right: 0;
        }
        
        .product-title-detail {
            font-size: 2.5rem;
            font-weight: 400;
            margin-bottom: 10px;
            color: #171717;
        }
        
        .product-price-detail {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
        }
        
        .size-chart-link {
            background-color: #343A40;      
            color: #fff !important;      
            padding: 6px 16px;           
            border-radius: 50px;         
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none !important;  
            font-size: 14px;
            font-weight: 500;
            margin-left: 10px;           
            height: 36px;
            width: auto;
            transition: background-color 0.3s;
        }
        
        .size-chart-link:hover {
            background-color:  #000000;      
            color: #fff !important;
        }

        .promo-banner {
            background-color: #D4EDDA;
            color: #21421e;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .size-btn {
            width: 60px;
            height: 40px;
            border: 1px solid #ddd;
            background-color: #fff;
            border-radius: 30px;
            margin-right: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .size-btn.active {
            background-color: #000;
            color: #fff;
        }
        
        .size-btn.disabled {
            background-color: #f5f5f5;
            color: #ccc;
            cursor: not-allowed;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 150px;
            overflow: hidden;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
        }
        
        .quantity-input {
            width: 70px;
            height: 40px;
            border: none;
            text-align: center;
            font-size: 1rem;
        }
        
        .add-to-cart-btn {
            width: 100%;
            padding: 15px;
            background-color: #343A40 !important; 
            color: #ffffff !important;            
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 10px;
            transition: background-color 0.3s ease-in-out;
            opacity: 1 !important;                
        }
        
        .add-to-cart-btn:hover {
            background-color: #000000 !important; 
            color: #ffffff !important;
        }
        
        .add-to-cart-btn:disabled,
        .add-to-cart-btn[disabled] {
            background-color: #343A40 !important; 
            color: #ffffff !important;            
            opacity: 0.6;                         
            cursor: not-allowed;
        }
        
        .buy-now-btn {
            width: 100%;
            padding: 15px;
            background-color: #004AAD;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 1rem;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .buy-now-btn:hover {
            background-color: #002655;
        }
        
        .buy-now-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .feature-tags {
            display: flex;
            overflow-x: auto;
            margin-bottom: 20px;
            background-color: #5881F6;
            border-radius: 5px;
            padding: 15px;
        }
        
        .feature-tag {
            white-space: nowrap;
            margin-right: 20px;
            color: white;
        }
        
        .product-detail-section {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        
        .section-content {
            padding-top: 15px;
            display: none;
        }
        
        .share-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #007bff;  /* choose same as Add to Cart */
            color: #fff;
            text-decoration: none;
            padding: 10px 18px;  /* same padding as your Add to Cart button */
            border-radius: 6px;  /* match corner radius */
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        
        .share-btn:hover {
            background-color: #0056b3;  /* hover effect */
            color: #fff;
        }
        
        .stock-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .stock-in-stock {
            background-color: #d4edda;
            color: #155724;
        }
        
        .stock-low-stock {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .stock-out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .loading {
            display: none;
        }
        
        .loading.show {
            display: inline-block;
        }
        
        .related-products {
            margin: 50px 0;
        }
        
        .related-products-title {
            font-size: 1.5rem;
            margin-bottom: 30px;
        }
        
        .related-product-item {
            margin-bottom: 30px;
        }
        
        .related-product-img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            margin-bottom: 10px;
            border-radius: 8px;
        }
        
        .related-product-title {
            font-size: 1rem;
            margin-bottom: 5px;
        }
        
        .related-product-price {
            font-size: 1rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .title-h4 {
            letter-spacing: 2px;
            font-weight: 300;
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item a {
            color: #666;
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: #333;
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: #5881F6;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .rating-stars {
            color: #ffc107;
            margin-bottom: 10px;
        }
        
        .review-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        
        .review-rating {
            color: #ffc107;
            font-size: 0.9rem;
        }
        
        .review-author {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .review-date {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .product-title-detail {
                font-size: 2rem;
            }
            
            .thumbnail-container {
                justify-content: center;
            }
        }

        <style>
        /* Additional styles for popup modals */
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
            margin-top:100px;
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
            margin-top:200px;
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

        .modal-body ul{
            font-size: 12px;
        }
        .modal-body h3{
            font-size: 16px;
        }
        .modal-body p{
            font-size: 14px;
        }


    </style>
</head>
<body>
    <?php include '../includes/timer.php'; ?>
    <!-- Navigation -->
    <?php include '../includes/navbar.php'; ?>

    <!-- Breadcrumb Navigation -->
    <div class="container nav-align">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="category.php?category=<?= urlencode($product['category_slug']) ?>"><?= htmlspecialchars($product['category_name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
            </ol>
        </nav>
    </div>

    <div class="container mt-3">
        <div class="row">
            <!-- Product Images Column -->
            <div class="col-lg-6 mb-4">
                <div class="product-images-container">
                    <?php if ($product['featured']): ?>
                        <div class="product-badge">Featured</div>
                    <?php endif; ?>
                    
                    <img src="<?= htmlspecialchars($primaryImage) ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="product-main-image" 
                         id="mainImage">
                    
                    <div class="zoom-icon" onclick="openImageModal()">
                        <i class="fas fa-search-plus"></i>
                    </div>
                </div>
                
                <?php if (count($productImages) > 1): ?>
                    <div class="thumbnail-container mt-3" style="overflow-x: auto;">
                        <div class="m-auto pr-2 pl-2 d-flex"  id="thumbnailContainer">
                            <?php foreach ($productImages as $index => $image): ?>
                                <img src="<?= htmlspecialchars($image['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($image['alt_text'] ?? $product['name']) ?>" 
                                     class="mr-1 mr-lg-2 ml-1 ml-lg-2 thumbnail <?= $index === 0 ? 'active' : '' ?>" 
                                     data-src="<?= htmlspecialchars($image['image_url']) ?>"
                                     onclick="changeMainImage('<?= htmlspecialchars($image['image_url']) ?>', this)">
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Details Column -->
            <div class="col-lg-6">
                <h1 class="product-title-detail"><?= htmlspecialchars($product['name']) ?></h1>
                
                <!-- Product Rating -->
                <?php if ($productRating['count'] > 0): ?>
                    <div class="rating-stars mb-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= $productRating['average'] ? '' : 'text-muted' ?>"></i>
                        <?php endfor; ?>
                        <span class="ml-2">(<?= $productRating['count'] ?> reviews)</span>
                    </div>
                <?php endif; ?>
                
                <h2 class="product-price-detail">₹<?= number_format($product['price'], 2) ?></h2>
                <p class="text-muted">Taxes included. <?php if ($product['stock_quantity'] > 10): ?>Free shipping on orders above ₹850<?php endif; ?></p>
                
                <!-- Stock Status -->
                <?php
                if ($product['stock_quantity'] <= 0) {
                    $stockClass = 'stock-out-of-stock';
                    $stockText = 'Out of Stock';
                } elseif ($product['stock_quantity'] <= $product['low_stock_threshold']) {
                    $stockClass = 'stock-low-stock';
                    $stockText = 'Only ' . $product['stock_quantity'] . ' left in stock';
                } else {
                    $stockClass = 'stock-in-stock';
                    $stockText = 'In Stock';
                }
                ?>
                <div class="stock-status <?= $stockClass ?>">
                    <?= $stockText ?>
                </div>
                
                <!-- Size Chart Link -->
                <a class="size-chart-link" data-toggle="modal" data-target="#sizeChartModal">
                    <i class="fas fa-ruler mr-2"></i>
                    Size Chart
                </a>

                <!-- Promo Banner -->
                <div class="promo-banner mt-4">
                    <strong>COMBO OFFERS</strong>
                    <div>BUY ANY <strong>3</strong> PRODUCTS <strong>@ 1199</strong></div>
                    <div>BUY ANY <strong>5</strong> PRODUCTS <strong>@ 1699</strong> </div>
                </div>
                
                <!-- Product Form -->
                <form id="productForm" onsubmit="handleAddToCart(event)">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    
                    <!-- Size Selection -->
                    <?php if (!empty($availableSizes)): ?>
                        <div class="size-section mt-4">
                            <p><strong>Size</strong></p>
                            <div class="size-buttons">
                                <?php foreach ($availableSizes as $index => $size): ?>
                                    <button type="button" 
                                            class="size-btn <?= $index === 0 ? 'active' : '' ?>" 
                                            data-size="<?= htmlspecialchars($size) ?>"
                                            onclick="selectSize(this, '<?= htmlspecialchars($size) ?>')">
                                        <?= htmlspecialchars($size) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="size" value="<?= htmlspecialchars($availableSizes[0]) ?>">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Quantity Selection -->
                    <div class="quantity-section mt-4">
                        <p><strong>Quantity</strong></p>
                        <div class="quantity-selector">
                            <button type="button" class="quantity-btn decrement" onclick="changeQuantity(-1)">−</button>
                            <input type="number" class="quantity-input" name="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>" readonly>
                            <button type="button" class="quantity-btn increment" onclick="changeQuantity(1)">+</button>
                        </div>
                    </div>
                    
                    <!-- Feature Tags -->
                    <div class="feature-tags mt-4">
                        <marquee class="text-light" behavior="" direction="left">
                            Soft & breathable &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Hypoallergenic &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Moisture wicking &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Relaxed fit
                        </marquee>
                    </div>
                    
                    <!-- Action Buttons -->
                    <button type="submit" 
                            class="add-to-cart-btn mt-4 text-dark" 
                            <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>
                        <span class="loading"><i class="fas fa-spinner fa-spin"></i></span>
                        <span class="btn-text">
                            <?= $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to cart' ?>
                        </span>
                    </button>
                    
                    <button type="button" 
                            class="buy-now-btn" 
                            onclick="buyNow()"
                            <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>
                        <?= $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Buy it now' ?>
                    </button>
                </form>
                
                <!-- Product Description -->
                <?php if ($product['description']): ?>
                    <div class="mt-4">
                        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Product Details Accordion -->
                <div class="product-detail-section mt-4 d-none">
                    <div class="section-header" onclick="toggleSection(this)">
                        <div>Product Details :</div>
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="section-content">
                        <p>Made from high-quality plant-based fabrics</p>
                        <p>Premium quality with excellent craftsmanship</p>
                        <p>Soft and breathable material</p>
                        <p>Available in multiple sizes</p>
                        <?php if ($product['description']): ?>
                            <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Care Instructions -->
                <?php if ($product['care_instructions']): ?>
                    <div class="product-detail-section mt-3 d-none">
                        <div class="section-header" onclick="toggleSection(this)">
                            <div>Garment Care :</div>
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="section-content">
                            <?= nl2br(htmlspecialchars($product['care_instructions'])) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="product-detail-section mt-3">
                        <div class="section-header" onclick="toggleSection(this)">
                            <div>Garment Care :</div>
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="section-content">
                            <p>Machine wash cold with like colors</p>
                            <p>Gentle cycle recommended</p>
                            <p>Do not bleach</p>
                            <p>Line dry or tumble dry low</p>
                            <p>Cool iron if needed</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Share Button -->
                <a href="#" class="share-btn mt-4" onclick="shareProduct()">
                    <i class="fas fa-share-alt mr-2"></i>
                    Share
                </a>
            </div>
        </div>
        
        <!-- Product Reviews Section -->
        <?php if (!empty($productReviews)): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h3>Customer Reviews</h3>
                    <div class="rating-summary mb-4">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= $productRating['average'] ? '' : 'text-muted' ?>"></i>
                            <?php endfor; ?>
                            <span class="ml-2"><?= $productRating['average'] ?> out of 5 (<?= $productRating['count'] ?> reviews)</span>
                        </div>
                    </div>
                    
                    <div class="reviews-list">
                        <?php foreach ($productReviews as $review): ?>
                            <div class="review-item">
                                <div class="review-author"><?= htmlspecialchars($review['customer_name']) ?></div>
                                <div class="review-rating mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $review['rating'] ? '' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <div class="review-date"><?= date('F j, Y', strtotime($review['created_at'])) ?></div>
                                <div class="review-content"><?= nl2br(htmlspecialchars($review['review_text'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <hr>
        
        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="related-products-title mt-3">Related products</h3>
                </div>
            </div>
            
            <div class="container mb-5 p-0 pb-5">
                <div class="row">
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                        <div class="product-card col-6 col-md-6 col-lg-3">
                            <a class="text-decoration-none" href="product.php?id=<?= $relatedProduct['id'] ?>">
                                <div class="image-container rounded-0">
                                    <img src="<?= htmlspecialchars($relatedProduct['primary_image'] ?: '../assets/images/default-product.jpg') ?>" 
                                         alt="<?= htmlspecialchars($relatedProduct['name']) ?>" class="default-img rounded-0">
                                    <img src="<?= htmlspecialchars($relatedProduct['primary_image'] ?: '../assets/images/default-product.jpg') ?>" 
                                         alt="<?= htmlspecialchars($relatedProduct['name']) ?>" class="hover-img h-100 rounded-0">
                                </div>
                                <h5 class="product-title text-left text-dark"  ><?= htmlspecialchars($relatedProduct['name']) ?></h5>
                                <p class="product-price text-left">₹<?= number_format($relatedProduct['price'], 2) ?></p>
                                
                                <?php 
                                $relatedSizes = $relatedProduct['sizes'] ? json_decode($relatedProduct['sizes'], true) : [];
                                if (!empty($relatedSizes)): 
                                ?>
                                    <span class="size-label text-left text-dark">Size: <strong><?= $relatedSizes[0] ?></strong></span>
                                    <div class="btn-group btn-group-toggle size-options" data-toggle="buttons">
                                        <?php foreach ($relatedSizes as $idx => $size): ?>
                                            <label style="width:26px;" class="pl-0 pr-0 btn btn-outline-dark <?= $idx === 0 ? 'active' : '' ?>">
                                                <input type="radio" name="size_<?= $relatedProduct['id'] ?>" <?= $idx === 0 ? 'checked' : '' ?>> <?= $size ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <button class="add-to-cart" onclick="addRelatedToCart(event, <?= $relatedProduct['id'] ?>)">
                                    <i class="fas fa-shopping-bag"></i> Add To Cart
                                </button>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Size Chart Modal -->
    <div class="modal fade" id="sizeChartModal" tabindex="-1" aria-labelledby="sizeChartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sizeChartModalLabel">Size Chart</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img src="../assets/images/size-chart.png" class="img-fluid" alt="Size Chart">
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= htmlspecialchars($product['name']) ?></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img src="<?= htmlspecialchars($primaryImage) ?>" class="img-fluid" id="modalImage">
                </div>
            </div>
        </div>
    </div>


    <!-- FOOTER -->
   
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

    <!-- ===== NAVBAR JAVASCRIPT (Add before closing </body> tag) ===== -->
    <script>
    // SIMPLIFIED AUTH FOR NON-INDEX PAGES
    (function() {
        'use strict';
        
        // Configuration
        const GOOGLE_CLIENT_ID = "340757900430-i8nl6l45ndveq9jmbvbah7ugquauj803.apps.googleusercontent.com";
        const AUTH_ENDPOINT = "<?= $basePath ?>auth/google-callback.php";
        
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
                    auto_select: false,  // NO AUTO SELECT
                    cancel_on_tap_outside: false,
                    use_fedcm_for_prompt: false
                });
                
                googleInitialized = true;
                return true;
            } catch (error) {
                return false;
            }
        }
        
        // Handle authentication response
        function handleAuthResponse(response) {
            if (!response.credential) return;
            
            fetch(AUTH_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    id_token: response.credential,
                    silent_auth: false,
                    manual_auth: true
                }),
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
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
                        showNotification('Login successful!', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                }
            })
            .catch(error => {
                console.error('Auth error:', error);
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
            if (currentUser) return;
            
            if (!googleInitialized && !initializeGoogle()) {
                // Fallback - show alert and reload
                alert('Authentication system not ready. Please refresh the page and try again.');
                window.location.reload();
                return;
            }
            
            try {
                google.accounts.id.prompt((notification) => {
                    if (notification.isNotDisplayed()) {
                        // One-Tap not available - show alert and reload
                        alert('Login not available at the moment. Please refresh the page and try again.');
                        window.location.reload();
                    }
                });
            } catch (error) {
                console.error('One-Tap login error:', error);
                // Fallback - show alert and reload
                alert('Login system error. Please refresh the page and try again.');
                window.location.reload();
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

// Display referral data - FIXED: No recent activity, grey colors
function displayReferralData(data) {
    const referral = data.referral || {};
    const balance = data.balance || {};
    const totalPoints = (balance.points || 0) + (balance.pending_points || 0);
    const heldPoints = balance.held_points || 0;
    const projectedTotal = balance.projected_total || 0;
    
    // NEW: Build held points section if exists
    let heldPointsSection = '';
    if (heldPoints > 0) {
        const heldDetails = data.held_details || [];
        const heldSummary = data.held_points_summary || {};
        
        let heldItemsHtml = '';
        if (heldDetails.length > 0) {
            heldItemsHtml = heldDetails.map(item => {
                const daysRemaining = parseInt(item.days_remaining);
                const hasReturnRisk = parseInt(item.has_return_risk) === 1;
                
                let statusIcon, statusText, statusClass;
                if (hasReturnRisk) {
                    statusIcon = '⚠️';
                    statusText = 'Return Risk';
                    statusClass = 'warning';
                } else if (item.release_status === 'ready_to_release') {
                    statusIcon = '✅';
                    statusText = 'Ready to Release';
                    statusClass = 'success';
                } else {
                    statusIcon = '⏳';
                    statusText = `${daysRemaining} days left`;
                    statusClass = 'info';
                }
                
                return `
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin: 8px 0; border-left: 3px solid #ffc107;">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>₹${parseFloat(item.points_earned).toFixed(0)}</strong> from Order #${item.order_id}
                                <br><small class="text-muted">${item.earning_rate}% commission</small>
                            </div>
                            <span class="badge badge-${statusClass}">${statusIcon} ${statusText}</span>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        heldPointsSection = `
            <div class="mt-4" style="border: 2px solid #ffc107; border-radius: 8px; padding: 15px; background: #fff9e6;">
                <h6 style="color: #856404;">🛡️ Points on Hold (7-Day Protection)</h6>
                <p style="color: #856404; font-size: 0.9rem;">₹${heldPoints} waiting for 7-day release period</p>
                ${heldItemsHtml}
                <div style="background: rgba(23, 162, 184, 0.1); padding: 8px; border-radius: 4px; margin-top: 10px;">
                    <small style="color: #0c5460;">
                        <strong>How it works:</strong> Points are held for 7 days to protect against returns. 
                        If no return is made, points automatically release to your wallet.
                    </small>
                </div>
            </div>
        `;
    }
    
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
                <span class="stat-label">💰 Available Points</span>
            </div>
            ${heldPoints > 0 ? `
            <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                <span class="stat-number">₹${heldPoints}</span>
                <span class="stat-label">🛡️ Held Points</span>
            </div>
            ` : ''}
        </div>
        
        ${heldPointsSection}
        
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
                        <button class="rounded-right-0 border-right border-light w-100 w-md-0" onclick="copyReferralLink()"><i class="fa-solid fa-copy"></i></button>
                        <button class="w-100 w-md-0 rounded-left-0" onclick="shareLinkViaWebShare()"><i class="fa-solid fa-share"></i></button>
                    </div>
                    <button class="rounded-0 border-right border-light d-none d-lg-block" onclick="copyReferralLink()"><i class="fa-solid fa-copy"></i></button>
                    <button class="d-none d-lg-block" onclick="shareLinkViaWebShare()"><i class="fa-solid fa-share"></i></button>
                </div>
            </div>
            
            <label><strong>Read our affiliate terms and conditions:</strong></label>

            <div class="alert-custom alert-success">
            <div class="modal-body p-4">
                <p>Welcome to the Bluefifth Affiliate Program! These Terms govern your participation in our Affiliate Program. By signing up, you agree to follow the rules below.</p>
                <hr>
                <h3>1. Eligibility</h3>
                <ul class="pl-3 text-left">
                    <li>The program is open to individuals, influencers, and businesses with a valid bank account and PAN card (for Indian residents).</li>
                    <li>Bluefifth reserves the right to accept or reject any affiliate application.</li>
                </ul>
                <hr>
                <h3>2. Affiliate Commission</h3>
                <ul class="pl-3 text-left">
                    <li>Affiliates earn a commission on every successful purchase made through their unique referral link or code.</li>
                    <li>The commission rate will be communicated at the time of joining and may vary by product or campaign.</li>
                    <li>Commissions are only paid on completed, non-returned, and fully paid orders.</li>
                    <li>Any cancelled, refunded, or fraudulent transactions will not qualify for commission.</li>
                </ul>
                <hr>
                <h3>3. Payouts</h3>
                <ul class="pl-3 text-left">
                    <li>Payouts are processed on a monthly basis once the minimum payout threshold of **₹1000** is reached.</li>
                    <li>Payments will be made via bank transfer/UPI.</li>
                    <li>Affiliates are responsible for providing accurate payment details. Bluefifth is not liable for failed payments due to incorrect information.</li>
                </ul>
                <hr>
                <h3>4. Fair Use Policy</h3>
                <ul class="pl-3 text-left">
                    <li>Affiliates must promote Bluefifth honestly and ethically.</li>
                    <li>Misleading claims, spam, coupon misuse, self-purchases, or fake referrals are strictly prohibited.</li>
                    <li>Any violation may result in termination from the program and forfeiture of unpaid commissions.</li>
                </ul>
                <hr>
                <h3>5. Content & Branding</h3>
                <ul class="pl-3 text-left">
                    <li>Affiliates may use official Bluefifth logos, banners, and creatives provided by us.</li>
                    <li>You may not alter, misuse, or create false advertising that harms the Bluefifth brand.</li>
                    <li>Paid ads (Google, Meta, etc.) using Bluefifth’s brand name without written approval are not allowed.</li>
                </ul>
                <hr>
                <h3>6. Taxes</h3>
                <ul class="pl-3 text-left">
                    <li>Affiliates are responsible for complying with applicable tax laws.</li>
                    <li>TDS (Tax Deducted at Source) will be applied as per Indian government regulations.</li>
                </ul>
                <hr>
                <h3>7. Program Changes</h3>
                <ul class="pl-3 text-left">
                    <li>Bluefifth reserves the right to modify, suspend, or terminate the affiliate program at any time without prior notice.</li>
                    <li>Commission rates and payment terms may be updated. Affiliates will be notified of any major changes.</li>
                </ul>
                <hr>
                <h3>8. Limitation of Liability</h3>
                <ul class="pl-3 text-left">
                    <li>Bluefifth is not responsible for any indirect, incidental, or loss of income arising from participation in the affiliate program.</li>
                    <li>Our maximum liability is limited to the commission earned by the affiliate.</li>
                </ul>
                <hr>
                <h3>9. Governing Law</h3>
                <ul class="pl-3 text-left">
                    <li>These terms are governed by the laws of India.</li>
                    <li>Any disputes will be subject to the jurisdiction of the courts in Coimbatore, Tamil Nadu.</li>
                </ul>
                <hr>
                <h3>10. Contact Us</h3>
                <p>For affiliate-related queries: 📧 info@bluefifth.in</p>
            </div>
            </div>
            
            ${heldPoints > 0 ? `
            <div class="alert-custom alert-warning">
                <strong>📊 Total Potential:</strong> ₹${projectedTotal} (includes ₹${heldPoints} held points)
                <br><small>Held points will be released after 7 days if no returns occur</small>
            </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('referral-content').innerHTML = content;
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
        // Product functionality
        let selectedSize = '<?= !empty($availableSizes) ? $availableSizes[0] : '' ?>';
        let currentQuantity = 1;
        const maxStock = <?= $product['stock_quantity'] ?>;

        // Image handling
        function changeMainImage(imageSrc, thumbnail) {
            document.getElementById('mainImage').src = imageSrc;
            document.getElementById('modalImage').src = imageSrc;
            
            // Update thumbnail active state
            document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
            thumbnail.classList.add('active');
        }

        function openImageModal() {
            $('#imageModal').modal('show');
        }

        function scrollThumbnails(direction) {
            const container = document.getElementById('thumbnailContainer');
            const scrollAmount = 100;
            container.scrollLeft += direction * scrollAmount;
        }

        // Size selection
        function selectSize(button, size) {
            // Remove active class from all size buttons
            document.querySelectorAll('.size-btn').forEach(btn => btn.classList.remove('active'));
            
            // Add active class to selected button
            button.classList.add('active');
            
            // Update hidden input and selected size
            document.querySelector('input[name="size"]').value = size;
            selectedSize = size;
        }

        // Quantity handling
        function changeQuantity(change) {
            const quantityInput = document.querySelector('.quantity-input');
            let newQuantity = parseInt(quantityInput.value) + change;
            
            if (newQuantity < 1) newQuantity = 1;
            if (newQuantity > maxStock) newQuantity = maxStock;
            
            quantityInput.value = newQuantity;
            currentQuantity = newQuantity;
        }

        // Add to cart functionality
        async function handleAddToCart(event) {
            event.preventDefault();
            
            const form = event.target;
            const submitBtn = form.querySelector('.add-to-cart-btn');
            const loading = submitBtn.querySelector('.loading');
            const btnText = submitBtn.querySelector('.btn-text');
            
            // Show loading state
            loading.classList.add('show');
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData(form);
                formData.append('action', 'add');  // ← CHANGED FROM 'add_to_cart' TO 'add'
                
                const response = await fetch('api/cart.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Product added to cart!', 'success');
                    updateCartCount(data.cart_summary?.item_count || 0);
                    
                    // Update button text temporarily
                    btnText.textContent = 'Added to Cart!';
                    setTimeout(() => {
                        btnText.textContent = 'Add to cart';
                    }, 2000);
                } else {
                    showNotification(data.message || 'Failed to add to cart', 'error');
                }
                
            } catch (error) {
                console.error('Error adding to cart:', error);
                showNotification('Error adding product to cart', 'error');
            } finally {
                loading.classList.remove('show');
                submitBtn.disabled = false;
            }
        }

        // IMPROVED Buy Now - Goes DIRECTLY to checkout
        async function buyNow() {
            const selectedSizeInput = document.querySelector('input[name="size"]');
            const selectedSize = selectedSizeInput ? selectedSizeInput.value : null;
            const quantity = parseInt(document.querySelector('.quantity-input').value || 1);
            
            const buyNowBtn = document.querySelector('.buy-now-btn');
            if (buyNowBtn) {
                const originalText = buyNowBtn.innerHTML;
                buyNowBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                buyNowBtn.disabled = true;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('product_id', <?= $product['id'] ?>);
                formData.append('quantity', quantity);
                if (selectedSize) {
                    formData.append('size', selectedSize);
                }
        
                const response = await fetch('api/cart.php', {
                    method: 'POST',
                    body: formData
                });
        
                const data = await response.json();
        
                if (data.success) {
                    // DIRECT TO CHECKOUT - NO LOGIN REQUIRED
                    showNotification('Redirecting to checkout...', 'success');
                    setTimeout(() => {
                        window.location.href = '../checkout.php';
                    }, 800);
                } else {
                    showNotification(data.message || 'Failed to process', 'error');
                    if (buyNowBtn) {
                        buyNowBtn.innerHTML = originalText;
                        buyNowBtn.disabled = false;
                    }
                }
            } catch (error) {
                console.error('Buy now error:', error);
                showNotification('Error processing request', 'error');
                if (buyNowBtn) {
                    buyNowBtn.innerHTML = originalText;
                    buyNowBtn.disabled = false;
                }
            }
        }

        // Related products add to cart
        async function addRelatedToCart(event, productId) {
            event.preventDefault();
            event.stopPropagation();
            
            // REMOVED: Guest login requirement - guests can add to cart
            // <?php if (!$isLoggedIn): ?>
            //     triggerOneTapLogin();
            //     return;
            // <?php endif; ?>

            const card = event.target.closest('.product-card');
            const selectedSizeInput = card.querySelector('.size-options input:checked');
            const selectedSize = selectedSizeInput ? selectedSizeInput.closest('label').textContent.trim() : null;
            
            try {
                const formData = new FormData();
                formData.append('action', 'add');  // ← CHANGED FROM 'add_to_cart' TO 'add'
                formData.append('product_id', productId);
                formData.append('quantity', 1);
                if (selectedSize) {
                    formData.append('size', selectedSize);
                }

                const response = await fetch('api/cart.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Product added to cart!', 'success');
                    updateCartCount(data.cart_summary?.item_count || 0);
                } else {
                    showNotification(data.message || 'Failed to add to cart', 'error');
                }
            } catch (error) {
                console.error('Error adding to cart:', error);
                showNotification('Error adding product to cart', 'error');
            }
        }

        // Section toggle
        function toggleSection(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('i');
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.classList.remove('fa-minus');
                icon.classList.add('fa-plus');
            } else {
                content.style.display = 'block';
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-minus');
            }
        }

        // Share product
        function shareProduct() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= htmlspecialchars($product['name']) ?>',
                    text: 'I just discovered Bluefifth – a new-age garment brand bringing you stylish, super-comfy essentials at honest prices. Use my referral link [link with code] to shop and experience premium quality you’ll love!',
                    url: window.location.href
                });
            } else {
                // Fallback - copy to clipboard
                navigator.clipboard.writeText(window.location.href);
                showNotification('Link copied to clipboard!', 'success');
            }
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


        // User menu toggle
        function toggleUserMenu() {
            window.location.href = '../referral/dashboard.php';
        }

        // Utility functions
        function updateCartCount(count) {
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
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

            // Size selection for related products
            document.addEventListener('click', function(e) {
                if (e.target.closest('label') && e.target.type === 'radio') {
                    const card = e.target.closest('.product-card');
                    const allLabels = card.querySelectorAll('.size-options .btn');
                    allLabels.forEach(l => l.classList.remove('active'));
                    e.target.closest('label').classList.add('active');

                    const selectedSize = e.target.closest('label').textContent.trim();
                    const sizeLabel = card.querySelector('.size-label strong');
                    if (sizeLabel) {
                        sizeLabel.textContent = selectedSize;
                    }
                }
            });
    </script>

</body>
</html>