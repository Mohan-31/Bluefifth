<?php
// index.php - UNIFIED Shopping + Referral System with Fixed Navigation
session_start();
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'auth/session.php';


// ========================================
// MAINTENANCE MODE CHECK
// ========================================
checkMaintenanceModeAndRedirect();

// Check maintenance mode (but allow admins)
if (getSetting('maintenance_mode') === 'true') {
    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    if (!$isAdmin) {
        // Show maintenance page to non-admin users
        include 'maintenance.html';
        exit;
    }
}

// Handle referral code from URL (same as old index.php)
$referralCode = $_GET['ref'] ?? '';
$visitTracked = false;
if ($referralCode && isValidReferralCode($referralCode)) {
    $_SESSION['referral_code'] = $referralCode;
    setcookie('referral_code', $referralCode, time() + (30 * 24 * 60 * 60), '/');
    
    // Track the visit
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id, user_id FROM referrals WHERE code = ?");
    $stmt->execute([$referralCode]);
    
    if ($stmt->rowCount() > 0) {
        $referral = $stmt->fetch();
        $referralId = $referral['id'];
        
        // Get visitor IP
        $visitorIp = getClientIP();
        
        // Check if this IP has visited via this referral today
        $stmt = $conn->prepare("SELECT id FROM referral_visits WHERE referral_id = ? AND visitor_ip = ? AND DATE(visited_at) = CURDATE()");
        $stmt->execute([$referralId, $visitorIp]);
        
        if ($stmt->rowCount() == 0) {
            // Record new visit
            $stmt = $conn->prepare("INSERT INTO referral_visits (referral_id, visitor_ip) VALUES (?, ?)");
            $stmt->execute([$referralId, $visitorIp]);
            $visitTracked = true;
        }
    }
}

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;
$isLoggedIn  = $isLoggedIn && ($currentUser !== null);

// Get featured products
$featuredProducts = getFeaturedProducts(8);

// Get all categories for navigation
$categories = getAllCategories();

// Get system settings
$siteName = getSystemSetting('site_name', 'Bluefifth');
$siteDescription = getSystemSetting('site_description', 'Premium clothing with sustainable fashion');

// Get user's cart summary if logged in
$cartSummary = $isLoggedIn ? getCartSummary($currentUser['id']) : ['item_count' => 0, 'total_amount' => 0];

// Get wallet balance if logged in
$walletBalance = $isLoggedIn ? getWalletBalance($currentUser['id']) : ['points' => 0, 'pending_points' => 0];
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
    <title><?= htmlspecialchars($siteName) ?> - <?= htmlspecialchars($siteDescription) ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($siteDescription) ?>">
    <meta name="keywords" content="fashion, clothing, sustainable, referral, shopping">
    <meta property="og:title" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($siteDescription) ?>">
    <meta property="og:type" content="website">

    
    <style>
    body{
        margin: 0;
        padding: 0;
    }
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
            width:120px;
            margin-left:20px;
        }
        .nav-align{
            margin-top:110px;
        }
        /* Tablet View (min-width: 768px) */
        @media (min-width: 768px) {
        .img-responsive{
            width:120px;
        }
        .nav-align{
            margin-top:0px;
        }
        }

        /* Laptop/Desktop View (min-width: 1024px or 1200px) */
        @media (min-width: 1024px) {
        .img-responsive{
            width:200px;
            margin-left:110px;
        }
        .nav-align{
            margin-top:160px;
        }
        }
        

        .nav-link{
            color: #212121 !important;
        }
        .nav-link:hover{
            color: grey !important;
        }
         .nav-link.active {
            color: grey !important;
            font-weight: 550 !important;
        }
        
        /* Default (mobile first) */
        .banner-bg {
            background-image: url('banner-head-mob.png');
            width: 100vw;
            height: auto;
            background-size: cover;
        }
        .bg-align{
            height: 490px;
            width: 100vw;
        }
        
        /* Tablet and above (≥768px) */
        @media (min-width: 768px) {
            .banner-bg {
                background-image: url('banner-head2.png');
                background-repeat: no-repeat;
                background-position: center;
                background-size: contain;
                height: 120vh;
            }
            .bg-align{
                height: 180vh;
                width: 100vw;
             }
        }
        
         .title-1{
            letter-spacing: 4px;
            font-weight:400;
            font-size: 24px;
        }
        /* Tablet and above (≥768px) */
        @media (min-width: 768px) {
            .title-1{
            font-size: 48px;
            }
        }
        
        .collection-title {
          font-size: 20px;
          letter-spacing: 5px;
          font-weight: 300;
          text-align: left;
          margin: 40px 0 20px;
          padding: 20px;
        }
        
        @media (min-width: 768px) {
            .collection-title{
            font-size: 2rem;
            }
        }
        
        .add-to-cart {
          background-color: #343A40 !important; 
          color: #ffffff !important;           
          width: 100%;
          margin-top: 10px;
          padding: 10px;
          font-weight: 600;
          border: none;
          transition: background-color 0.3s ease-in-out;
          opacity: 1 !important;              
        }
        
        .add-to-cart i,
        .add-to-cart span {
          color: #ffffff !important;          
        }
        
        .add-to-cart:hover {
          background-color: #000000 !important; 
          color: #ffffff !important;
        }
        
        /* Handle disabled state */
        .add-to-cart:disabled,
        .add-to-cart[disabled] {
          background-color: #343A40 !important; 
          color: #ffffff !important;            
          opacity: 0.7;                         
          cursor: not-allowed;                  
        }
        
        /* Mobile Menu Scrollable */
        @media (max-width: 991.98px) { /* applies to devices < lg */
            .navbar-collapse {
                max-height: 70vh;   /* restrict menu height */
                overflow-y: auto;   /* allow vertical scrolling */
                -webkit-overflow-scrolling: touch; /* smooth scroll on iOS */
            }
        }
        .navbar-collapse::-webkit-scrollbar {
            width: 6px;
            display: none;
        }
        .navbar-collapse::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
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

<?php include './includes/timer.php'; ?>

<?php include './includes/navbar.php'; ?>

<!-- Referral Alert (if user came via referral) -->
<?php if ($referralCode): ?>
    <div class="container mt-3">
        <div class="alert alert-info alert-dismissible fade show d-none" role="alert">
            <?php if ($visitTracked): ?>
                🎉 <strong>You've been referred by a friend!</strong> This visit has been tracked.
            <?php else: ?>
                👋 <strong>Welcome back!</strong> You've already visited through this referral today.
            <?php endif; ?>
            <br><small>Referral Code: <strong><?= htmlspecialchars($referralCode) ?></strong></small>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- Hero Banner -->
<div class="container-fluid banner-bg nav-align" >
    <div class="row">
        <div class=" bg-align d-flex justify-content-center align-items-end align-items-md-center  col-12 text-center" >
            <div class="mb-5">
                <div class="">
                    <h1 class="banner-h1 d-none d-lg-block text-light " style="font-size: 54px;">COMFORT WEAR COLLECTION OUT NOW</h1>
                    <h1 class="banner-h1 d-lg-none d-block text-light">COMFORT WEAR COLLECTION OUT NOW</h1>
                </div>
                <div class="mt-4">
                    <a href="shop/category.php"><button class="btn-banner text-light" style="background-color:#343A40;">SHOP NOW</button></a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Featured Products Section -->
<div class="container mt-5 mb-2 ">
    <div class="row">
        <div class="col-12 text-center ">
            <h1 class="title-1" style="color:#1E3A8A;">MONTHLY DROPS</h1>
        </div>
    </div>
</div>

<!-- Featured Products Slider -->
<div class="container">
    <div class="row">
        <div class="col-12 slider-container">
            <div class="arrow arrow-left" onclick="scrollSlider(-1)">&#10094;</div>
            <div class="slider" id="productSlider">
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="product-card" data-product-id="<?= $product['id'] ?>">
                        <a href="shop/product.php?id=<?= $product['id'] ?>" class="text-decoration-none">
                            <div class="image-container2 rounded-0">
                                <img src="<?= htmlspecialchars($product['primary_image'] ?: '/assets/images/default-product.jpg') ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>" class="default-img rounded-0">
                                <img src="<?= htmlspecialchars($product['primary_image'] ?: '/assets/images/default-product.jpg') ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>" class="hover-img rounded-0">
                            </div>
                            <h5 class="product-title text-left pl-4 text-dark"><?= htmlspecialchars($product['name']) ?></h5>
                            <p class="product-price text-left pl-4 text-dark">₹<?= number_format($product['price'], 2) ?></p>
                            
                            <?php 
                            $sizes = $product['sizes'] ? json_decode($product['sizes'], true) : [];
                            $defaultSize = !empty($sizes) ? $sizes[0] : 'M';
                            ?>
                            
                            <span class="size-label text-left pl-4 text-dark d-none">Size: <strong class="selected-size"><?= $defaultSize ?></strong></span>
                            
                            <?php if (!empty($sizes)): ?>
                                <div class="btn-group btn-group-toggle size-options d-none" data-toggle="buttons">
                                    <?php foreach ($sizes as $index => $size): ?>
                                        <label class="btn btn-outline-dark <?= $index === 0 ? 'active' : '' ?>">
                                            <input type="radio" name="size_<?= $product['id'] ?>" value="<?= $size ?>" <?= $index === 0 ? 'checked' : '' ?>> <?= $size ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <button class="add-to-cart " onclick="addToCartFromSlider(event, <?= $product['id'] ?>)">
                                <i class="fas fa-shopping-cart"></i> Add To Cart
                            </button>
                        </a>
                        </div>
                <?php endforeach; ?>
            </div>
            <div class="arrow arrow-right" onclick="scrollSlider(1)">&#10095;</div>
        </div>
    </div>
    <div class="text-center m-4">
        <a href="shop/category.php"><button class="add-to-cart w-50">View All</button></a>
    </div>
</div>

<!-- Video Section -->
<div class="container-fluid banner-bg-2 d-none">
    <div class="row">
        <video autoplay muted loop playsinline class="video-bg w-100">
            <source src="https://cdn.shopify.com/videos/c/o/v/328020ee3bf2486b936e8da85ffea3cd.mp4" type="video/mp4">
            Your browser does not support HTML5 video.
        </video>
    </div>
</div>


<!-- Free Shipping Banner -->
<div class="container-fluid mt-5 mb-5">
    <div class="row">
        <div class="w-100">
            <div class="logos" data-aos="fade-left">
                <div class="logos-slide">
                    <h1>FREE SHIPPING & EXCHANGES ACROSS INDIA</h1>
                </div>
                <div class="logos-slide">
                    <h1>FREE SHIPPING & EXCHANGES ACROSS INDIA</h1>
                </div>
                <div class="logos-slide">
                    <h1>FREE SHIPPING & EXCHANGES ACROSS INDIA</h1>
                </div>
                <div class="logos-slide">
                    <h1>FREE SHIPPING & EXCHANGES ACROSS INDIA</h1>
                </div>
                <div class="logos-slide">
                    <h1>FREE SHIPPING & EXCHANGES ACROSS INDIA</h1>
                </div>
                <div class="logos-slide">
                    <h1>FREE SHIPPING & EXCHANGES ACROSS INDIA</h1>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- About Section -->
<div class="container mt-5 pt-5">
    <div class="row"> 
        <div class="col-12 col-lg-6">
            <img src="assets/images/index-image.jpeg" 
                 style="border-radius: 12px;" width="100%" alt="about">
        </div>
        <div class="col-12 col-lg-6 d-flex align-items-center">
            <div>
                <h1 class="about-h1 mt-4 mt-lg-0" style="color:#1E3A8A;">
                    STYLE WITH PURPOSE. FASHION WITH IMPACT
                </h1>
                <p class="about-p">
                    At Bluefifth, we believe fashion is more than just clothing—it’s a statement of responsibility. Every piece we create is designed to be stylish, comfortable, and planet-friendly. By choosing our  eco-conscious fabrics, you’re not just upgrading your wardrobe, you’re taking a step towards a greener future.
                </p>
                <p class="about-p">
                    Our promise is simple: timeless designs, premium quality, and a positive impact on both you and the planet. Dress better, live better, and join us in shaping a sustainable tomorrow.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Collection Gallery -->
<div class="container mt-3 mt-md-5 pt-0 pt-md-3">
    <div class="row">
        <!-- Left Column -->
        <div class="col-md-4">
            <div class="collection-title mb-0 mb-md-4" style="color:#1E3A8A;" >SHOP BY COLLECTION</div>
            
            <!-- Mobile Collection Card -->
            <div class="collection-card d-md-none d-block">
                <a href="shop/category.php">
                    <img src="./assets/images/collection-banner.jpg" alt="Featured Collection">
                </a>
            </div>
            
            <!-- Desktop Collection Cards -->
            <div class="collection-card">
                <img src="https://commnsens.com/cdn/shop/files/gempages_523126627069068376-ea8a7deb-b71a-4ad5-9265-7cf040380a0f.png?v=12845835609366065749" alt="Dreamy Flow">
            </div>
            <div class="collection-card">
                <img src="https://commnsens.com/cdn/shop/files/gempages_523126627069068376-6d15295f-0805-4442-9989-7e7a8e669187.png?v=931527186455049582" alt="Diffusion">
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-md-8">
            <!-- Featured Collection -->
            <div class="collection-card d-none d-md-block">
                <a href="shop/category.php">
                    <img src="./assets/images/collection-banner.jpg" alt="Featured Collection">
                </a>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="collection-card">
                        <a href="shop/category.php?category=basics">
                            <img src="https://commnsens.com/cdn/shop/files/gempages_523126627069068376-f50cbec3-d137-4251-baf4-4efe1e24fed5.png?v=11089955678911056430" alt="Basics Collection">
                        </a>
                        <a href="shop/category.php?category=premium">
                            <img class="mt-3" src="https://cdn.shopify.com/s/files/1/0643/5245/2848/files/gempages_523126627069068376-7b890183-1ba2-4159-89f0-845a432685a7.png?v=1721423873" alt="Premium Collection">
                        </a>
                    </div>
                </div>
                <div class="col-md-6 flex-column">
                    <div class="text-right mt-0 mt-md-5 d-none d-md-block">
                        <h3 class="text-right" style="font-weight: 600; letter-spacing: 3px; color:#1E3A8A; ">DISCOVER<br>MORE</h3>
                        <div class="text-center mt-3 mb-4 mb-md-5">
                            <a href="shop/category.php"><button class="btn-gallery text-light" style="background:#004AAD;">COLLECTIONS</button></a>
                        </div>
                    </div>
                    <div class="collection-card">
                        <a href="shop/category.php?category=seasonal">
                            <img src="https://commnsens.com/cdn/shop/files/gempages_523126627069068376-7c9dd6f7-cc3e-4850-9ca0-ac065816ed87.png?v=3890653826190371144" alt="Seasonal Collection">
                        </a>
                        <a href="shop/category.php?category=limited-edition">
                            <img class="mt-3" src="https://commnsens.com/cdn/shop/files/gempages_523126627069068376-1596c702-69b9-4b61-b118-8806f96982e8.png?v=2808246028600262366" alt="Limited Edition">
                        </a>
                    </div>
                    <div class="text-right mt-4 d-md-none d-block">
                        <h3 class="text-center" style="font-weight: 600; letter-spacing: 3px; color:#1E3A8A; font-size:22px;">DISCOVER MORE</h3>
                        <div class="text-center mt-3 mb-4 mb-md-5">
                            <a href="shop/category.php"><button class="btn-gallery text-light" style="background:#004AAD;">COLLECTIONS</button></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Exclusive Products -->
<div class="container mt-5 pt-0 pt-lg-5">
    <div>
        <h1 class="exclusive-h1 d-none d-md-block" style="color:#1E3A8A;">EXCLUSIVE HIGHLIGHTS</h1>
        <h1 class="exclusive-h1-2 d-md-none d-block" style="color:#1E3A8A;">EXCLUSIVE HIGHLIGHTS</h1>
    </div>
    
    <!-- Load more featured products for exclusive section -->
    <div class="row">
        <div class="col-12 slider-container">
            <div class="arrow arrow-left" onclick="scrollExclusiveSlider(-1)">&#10094;</div>
            <div class="slider" id="exclusiveProductSlider">
                <?php 
                // Get different set of products for exclusive section
                $exclusiveProducts = getAllProducts(8, 0, null, 'active');
                foreach ($exclusiveProducts as $product): 
                ?>
                    <div class="product-card" data-product-id="<?= $product['id'] ?>">
                        <a href="shop/product.php?id=<?= $product['id'] ?>" class="text-decoration-none">
                            <div class="image-container2 rounded-0">
                                <img src="<?= htmlspecialchars($product['primary_image'] ?: '/assets/images/default-product.jpg') ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>" class="default-img">
                                <img src="<?= htmlspecialchars($product['primary_image'] ?: '/assets/images/default-product.jpg') ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>" class="hover-img">
                            </div>
                            <h5 class="product-title text-dark text-left pl-4"><?= htmlspecialchars($product['name']) ?></h5>
                            <p class="product-price text-dark text-left pl-4">₹<?= number_format($product['price'], 2) ?></p>
                            
                            <?php 
                            $sizes = $product['sizes'] ? json_decode($product['sizes'], true) : [];
                            $defaultSize = !empty($sizes) ? $sizes[0] : 'M';
                            ?>
                            
                            <span class="size-label text-dark text-left pl-4 d-none">Size: <strong class="selected-size"><?= $defaultSize ?></strong></span>
                            
                            <?php if (!empty($sizes)): ?>
                                <div class="btn-group btn-group-toggle size-options  d-none" data-toggle="buttons">
                                    <?php foreach ($sizes as $index => $size): ?>
                                        <label class="btn btn-outline-dark <?= $index === 0 ? 'active' : '' ?>">
                                            <input type="radio" name="exclusive_size_<?= $product['id'] ?>" value="<?= $size ?>" <?= $index === 0 ? 'checked' : '' ?>> <?= $size ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <button class="add-to-cart" onclick="addToCartFromSlider(event, <?= $product['id'] ?>)">
                                <i class="fas fa-shopping-cart"></i> Add To Cart
                            </button>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="arrow arrow-right" onclick="scrollExclusiveSlider(1)">&#10095;</div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="container-fluid py-5">
    <div class="row">
        <div class="col-md-3 col-12 feature-box">
            <i class="fas fa-shipping-fast" style="color:#004AAD;"></i>
            <h5>Fast Shipping</h5>
            <p>Fast shipping on all orders. Ships within 3 Days.</p>
        </div>
        <div class="col-md-3 col-12 feature-box">
            <i class="fa-solid fa-indian-rupee-sign" style="color:#004AAD;"></i>
            <h5>Get 10% off</h5>
            <p>Earn 10% commission on your referrals’ first order. From their second purchase onwards, you’ll receive 2–5% commission.</p>
        </div>
        <div class="col-md-3 col-12 feature-box">
            <i class="fa-solid fa-arrow-right-arrow-left" style="color:#004AAD;"></i>
            <h5>Easy Exchanges<sup>*</sup></h5>
            <p>We accept exchanges within 7 days of delivery if the product doesn’t meet your expectations.</p>
        </div>
        <div class="col-md-3 col-12 feature-box">
            <i class="fa-solid fa-headset" style="color:#004AAD;"></i>
            <h5>Fast Customer Support</h5>
            <p>Quick Help, Expert Care: Hassle-free support, only a click away.</p>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="container-fluid pb-3 p-5">
        <div class="row">
            <div class="col-6 col-md-2" style="letter-spacing: 2px; text-transform: uppercase;">
                <div class="footer-logo">
                    <img class="" src="./assets/images/logo3.png" width="160px" alt="Velona">
                </div>
                <a href="/ecommerce-project/index.php" class="mt-5">HOME</a>
                <a href="/ecommerce-project/includes/about.php">ABOUT US</a>
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
                    <img src="/ecommerce-project/assets/images/payment-methods.png" alt="payment">
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

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- APPLICATION SCRIPT -->
<script>
let currentUser = <?= $isLoggedIn ? 'true' : 'false' ?>;
let isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

// Authentication: phone-OTP at checkout (Google Sign-In removed)
(function() {
    'use strict';

    let authState = {
        isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>
    };
    
    function hideLoading() {
        const loader = document.getElementById('auth-loading');
        if (loader) loader.classList.remove('show');
    }

    // Show info popup (no login required — auth happens at checkout)
    window.showTraditionalLoginPopup = function() {
        document.getElementById('modal-overlay').classList.add('show');
        document.getElementById('traditional-login-popup').classList.add('show');
    };

    window.triggerOneTapLogin = function() {
        if (authState.isLoggedIn) return;
        showTraditionalLoginPopup();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideLoading);
    } else {
        hideLoading();
    }

})();

// MAIN APPLICATION FUNCTIONS
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

// Show guest referral content - UPDATED with one-tap login
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

// Show guest wallet content - UPDATED with one-tap login
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
    document.getElementById('traditional-login-popup').classList.remove('show');
}

// ADD THESE NEW FUNCTIONS HERE:
function showGuestProfile() {
    const email = prompt("Enter your email to view/edit your profile:");
    if (email && email.includes('@')) {
        fetchAndShowGuestProfile(email);
    }
}

async function fetchAndShowGuestProfile(email) {
    try {
        const formData = new FormData();
        formData.append('action', 'get_guest_profile');
        formData.append('email', email);
        
        const response = await fetch('shop/api/guest-profile.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const user = data.user;
            const profileHtml = `
                <div class="guest-login-section">
                    <h3>My Profile</h3>
                    <div style="text-align: left; padding: 1rem;">
                        <p><strong>Name:</strong> ${user.name || 'Not set'}</p>
                        <p><strong>Email:</strong> ${user.email || 'Not set'}</p>
                        <p><strong>Phone:</strong> ${user.phone || 'Not set'}</p>
                        <p><strong>Address:</strong> ${user.address || 'Not set'}</p>
                        <p><strong>City:</strong> ${user.city || 'Not set'}</p>
                        <p><strong>State:</strong> ${user.state || 'Not set'}</p>
                        <p><strong>Pincode:</strong> ${user.pincode || 'Not set'}</p>
                    </div>
                    <p class="text-muted">Profile details are saved from your checkout information.</p>
                </div>
            `;
            
            document.getElementById('referral-content').innerHTML = profileHtml;
            document.getElementById('modal-overlay').classList.add('show');
            document.getElementById('referral-popup').classList.add('show');
        } else {
            showNotification('No profile found for this email', 'info');
        }
    } catch (error) {
        console.error('Error fetching profile:', error);
        showNotification('Error loading profile', 'error');
    }
}

// Load referral data
function loadReferralData() {
    fetch('wallet/get-balance.php')
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

// Load wallet data
function loadWalletData() {
    fetch('wallet/get-balance.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add user details to the data object before displaying
                data.user_details = data.user_details || {};
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

// Updated displayWalletData function for your index.php
// Replace the existing function with this enhanced version

function displayWalletData(data) {
    const balance = data.balance || {};
    const availablePoints = balance.points || 0;
    const pendingPoints = balance.pending_points || 0;
    const heldPoints = balance.held_points || 0;
    const projectedTotal = balance.projected_total || 0;
    const totalPoints = availablePoints + pendingPoints;
    
    const heldDetails = data.held_details || [];
    const heldSummary = data.held_points_summary || {};
    
    // Check claim eligibility
    const currentDay = new Date().getDate();
    const isClaimDate = currentDay === 30 || currentDay === 31;
    const hasEnoughPoints = totalPoints >= 100;
    const canClaim = isClaimDate && hasEnoughPoints;
    
    let heldPointsSection = '';
    
    // Enhanced held points section with return risk information
    if (heldPoints > 0) {
        // MOVE ALL VARIABLE DECLARATIONS TO TOP
        const atRiskPoints = heldSummary.at_risk_points || 0;
        const safeHeldPoints = heldPoints - atRiskPoints;
        
        // BUILD heldItemsHtml FIRST
        let heldItemsHtml = '';
        
        if (heldDetails.length > 0) {
            heldItemsHtml = heldDetails.map(item => {
                const daysRemaining = parseInt(item.days_remaining);
                const hasReturnRisk = parseInt(item.has_return_risk) === 1;
                const releaseStatus = item.release_status;
                
                let statusIcon, statusText, statusClass, borderColor;
                
                if (hasReturnRisk) {
                    statusIcon = '⚠️';
                    statusText = 'Return Pending - At Risk';
                    statusClass = 'warning';
                    borderColor = '#dc3545';
                } else if (releaseStatus === 'ready_to_release') {
                    statusIcon = '✅';
                    statusText = 'Ready to Release';
                    statusClass = 'success';
                    borderColor = '#28a745';
                } else if (releaseStatus === 'releasing_soon') {
                    statusIcon = '⏰';
                    statusText = `${daysRemaining} day${daysRemaining !== 1 ? 's' : ''} left`;
                    statusClass = 'info';
                    borderColor = '#17a2b8';
                } else {
                    statusIcon = '⏳';
                    statusText = `${daysRemaining} days remaining`;
                    statusClass = 'secondary';
                    borderColor = '#6c757d';
                }
                
                return `
                    <div class="held-point-item" style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 8px 0; border-left: 4px solid ${borderColor};">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>₹${parseFloat(item.points_earned).toFixed(0)}</strong> from Order #${item.order_id}
                                <br><small class="text-muted">
                                    Earned ${new Date(item.created_at).toLocaleDateString()} • 
                                    ${item.earning_rate}% commission (Month ${item.purchase_month})
                                </small>
                            </div>
                            <div class="text-right">
                                <span class="badge badge-${statusClass}" style="font-size: 0.75rem;">
                                    ${statusIcon} ${statusText}
                                </span>
                                ${releaseStatus === 'ready_to_release' ? 
                                    '<br><small class="text-success mt-1">Will be released by cron job</small>' : 
                                    `<br><small class="text-muted">Release: ${new Date(item.hold_until).toLocaleDateString()}</small>`
                                }
                            </div>
                        </div>
                        ${hasReturnRisk ? `
                            <div class="mt-2 p-2" style="background: rgba(220, 53, 69, 0.1); border-radius: 4px;">
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <strong>Risk Alert:</strong> Customer requested return - these points may be canceled if admin approves the return
                                </small>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        } else {
            heldItemsHtml = `
                <div class="held-point-item" style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 8px 0;">
                    <div class="text-center text-muted">
                        <i class="fas fa-clock mr-2"></i>₹${heldPoints} in held points
                        <br><small>Waiting for 7-day release period</small>
                    </div>
                </div>
            `;
        }
        
        // NOW BUILD heldPointsSection (after heldItemsHtml is defined)
        heldPointsSection = `
            <div class="held-points-section" style="border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px 0; background: linear-gradient(135deg, #fff9e6 0%, #ffffff 100%);">
                <h6 style="color: #856404; margin-bottom: 15px;">
                    <i class="fas fa-hourglass-half mr-2"></i>Points on Hold (7-Day Protection Period)
                </h6>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="alert alert-info mb-2" style="padding: 10px;">
                            <strong>₹${heldPoints}</strong> total held points
                            <br><small>${heldSummary.orders_on_hold || 0} orders • Earned from referrals</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        ${atRiskPoints > 0 ? `
                        <div class="alert alert-warning mb-2" style="padding: 10px;">
                            <strong>₹${atRiskPoints}</strong> at risk
                            <br><small>From orders with return requests</small>
                        </div>
                        ` : `
                        <div class="alert alert-success mb-2" style="padding: 10px;">
                            <strong>₹${safeHeldPoints}</strong> safe
                            <br><small>No return requests detected</small>
                        </div>
                        `}
                    </div>
                </div>
                
                <div class="held-points-breakdown">
                    ${heldItemsHtml}
                </div>
                
                <div class="held-points-info mt-3" style="background: rgba(23, 162, 184, 0.1); padding: 10px; border-radius: 4px;">
                    <h6 style="color: #0c5460; margin-bottom: 8px;">7-Day Hold System</h6>
                    <small style="color: #0c5460;">
                        <strong>How it works:</strong><br>
                        • Referral points are held for 7 days after purchase to protect against returns<br>
                        • If referred customer returns product → admin approval cancels these points<br>
                        • If no return (or return rejected) → points automatically release to your wallet<br>
                        • You'll receive notifications when points are released or canceled<br>
                        ${heldSummary.earliest_release ? `• Next release: ${new Date(heldSummary.earliest_release).toLocaleDateString()}` : ''}
                    </small>
                </div>
            </div>
        `;
    }
    
    // Continue with the rest of your function...
    const content = `
        <div class="stats-grid">
            <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                <span class="stat-number">₹${availablePoints.toFixed(0)}</span>
                <span class="stat-label">💰 Available Points</span>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                <span class="stat-number">₹${pendingPoints.toFixed(0)}</span>
                <span class="stat-label">⏳ Pending Points</span>
            </div>
            ${heldPoints > 0 ? `
            <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                <span class="stat-number">₹${heldPoints.toFixed(0)}</span>
                <span class="stat-label">🛡️ Held Points</span>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                <span class="stat-number">₹${projectedTotal.toFixed(0)}</span>
                <span class="stat-label">💎 Projected Total</span>
            </div>
            ` : `
            <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                <span class="stat-number">₹${totalPoints.toFixed(0)}</span>
                <span class="stat-label">💎 Total Balance</span>
            </div>
            `}
        </div>
        
        ${heldPointsSection}
        
        <div class="mt-4">
            <button id="claim-btn" class="popup-btn ${!canClaim ? 'disabled' : ''}" 
                    onclick="claimPoints()" ${!canClaim ? 'disabled' : ''}>
                🎁 Claim ₹${totalPoints.toFixed(0)} as Money
            </button>
            
            <div class="mt-3">
                ${!isClaimDate ? 
                    '<div class="alert-custom alert-warning">⏰ Claims available on 30th & 31st only</div>' : 
                    !hasEnoughPoints ? 
                        '<div class="alert-custom alert-warning">❌ Minimum ₹100 required to claim</div>' : 
                        '<div class="alert-custom alert-success">✅ Ready to claim! Today is claim date.</div>'
                }
            </div>
            
            ${heldPoints > 0 ? `
            <div class="alert alert-info mt-3">
                <strong>Future Earnings Preview:</strong> When your held points (₹${heldPoints}) are released, your claimable balance will increase to ₹${projectedTotal.toFixed(0)}
                ${(heldSummary.at_risk_points || 0) > 0 ? `<br><small class="text-warning">⚠️ ${(heldSummary.at_risk_points || 0).toFixed(0)} points at risk due to return requests</small>` : ''}
            </div>
            ` : ''}
        </div>
        
        <!-- Payment Details Form -->
        <div class="mt-4" style="border-top: 1px solid #eee; padding-top: 1.5rem;">
            <h5>💳 Payment Details</h5>
            <div id="payment-details-form">
                <form id="update-payment-details-form" onsubmit="updatePaymentDetails(event)">
                    <div class="form-group mb-3">
                        <label for="mobile_number"><strong>Mobile Number:</strong></label>
                        <input type="tel" 
                               id="mobile_number" 
                               name="mobile_number" 
                               class="form-control" 
                               placeholder="Enter your mobile number"
                               pattern="[0-9]{10}"
                               maxlength="10"
                               value="${data.user_details?.mobile_number || ''}"
                               required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="upi_id"><strong>UPI ID:</strong></label>
                        <input type="text" 
                               id="upi_id" 
                               name="upi_id" 
                               class="form-control" 
                               placeholder="example@paytm"
                               value="${data.user_details?.upi_id || ''}"
                               required>
                    </div>
                    
                    <button type="submit" class="popup-btn" id="save-payment-btn">
                        💾 Save Payment Details
                    </button>
                </form>
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
                            
                            // First, handle null/undefined/empty cases before toLowerCase()
                            const transactionType = (transaction.transaction_type || '').toLowerCase().trim();
                            
                            switch (transactionType) {
                                case 'earned':
                                    statusHtml = '<span class="status-badge status-earned">✅ Completed</span>';
                                    typeDisplay = 'Referral Earned';
                                    break;
                                case 'held':
                                    statusHtml = '<span class="status-badge" style="background: #fff3cd; color: #856404;">🛡️ On Hold</span>';
                                    typeDisplay = 'Referral (Held)';
                                    break;
                                case 'released':
                                    statusHtml = '<span class="status-badge status-earned">✅ Released</span>';
                                    typeDisplay = 'Hold Released';
                                    break;
                                case 'return_canceled':
                                    statusHtml = '<span class="status-badge" style="background: #f8d7da; color: #721c24;">❌ Return Canceled</span>';
                                    typeDisplay = 'Points Canceled';
                                    break;
                                case 'claimed':
                                    statusHtml = '<span class="status-badge status-claimed">⏳ Pending Admin</span>';
                                    typeDisplay = 'Claim Requested';
                                    break;
                                case 'processed':
                                    statusHtml = '<span class="status-badge status-processed">💰 Paid</span>';
                                    typeDisplay = 'Claim Paid';
                                    break;
                                case 'rejected':
                                    statusHtml = '<span class="status-badge status-rejected">❌ Claim Rejected</span>';
                                    typeDisplay = 'Claim Rejected';
                                    break;
                                case 'approved':
                                    statusHtml = '<span class="status-badge status-approved">✅ Claim Approved</span>';
                                    typeDisplay = 'Claim Approved';
                                    break;
                                case '':
                                    // Handle completely empty transaction_type - likely a held transaction
                                    if (transaction.points > 0 && transaction.description && transaction.description.includes('hold')) {
                                        statusHtml = '<span class="status-badge" style="background: #fff3cd; color: #856404;">🛡️ On Hold</span>';
                                        typeDisplay = 'Referral (Held)';
                                    } else {
                                        statusHtml = '<span class="status-badge" style="background: #e9ecef; color: #495057;">❓ Unknown</span>';
                                        typeDisplay = 'Unknown Transaction';
                                    }
                                    break;
                                default:
                                    // Handle any unexpected transaction types
                                    const displayType = transaction.transaction_type || 'Unknown';
                                    statusHtml = '<span class="status-badge" style="background: #e9ecef; color: #495057;">' + displayType + '</span>';
                                    typeDisplay = displayType.charAt(0).toUpperCase() + displayType.slice(1);
                                    break;
                            }                            
                            const amount = Math.abs(transaction.points);
                            const isNegative = transaction.points < 0;
                            
                            return `
                                <tr>
                                    <td style="font-size: 0.85rem;">${new Date(transaction.created_at).toLocaleDateString()}</td>
                                    <td style="font-size: 0.85rem;">${typeDisplay}</td>
                                    <td style="font-size: 0.85rem;">
                                        ${isNegative ? '-' : ''}₹${amount.toFixed(0)}
                                    </td>
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

function updatePaymentDetails(event) {
    event.preventDefault();
    console.log('Form submitted'); // Debug line
    
    const formData = new FormData(event.target);
    console.log('Mobile:', formData.get('mobile_number')); // Debug line
    console.log('UPI:', formData.get('upi_id')); // Debug line
    
    const saveBtn = document.getElementById('save-payment-btn');
    const originalText = saveBtn.textContent;
    
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    
    fetch('wallet/update-payment-details.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status); // Debug line
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data); // Debug line
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
        
        if (data.success) {
            showNotification('Payment details saved successfully!', 'success');
        } else {
            showNotification(data.message || 'Failed to save details', 'error');
        }
    })
    .catch(error => {
        console.error('Error saving payment details:', error);
        showNotification('Network error occurred', 'error');
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
    });
}

// Claim points function
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

    fetch('referral/claim-points.php', {
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

// Copy referral code
function copyReferralCode() {
    const codeField = document.getElementById('referral-code');
    if (codeField) {
        codeField.select();
        document.execCommand('copy');
        showNotification('Referral code copied! 📋', 'success');
    }
}

// Copy referral link
function copyReferralLink() {
    const linkField = document.getElementById('referral-link');
    if (linkField) {
        linkField.select();
        document.execCommand('copy');
        showNotification('Referral link copied! 🔗', 'success');
    }
}

// Fixed logout function - stay on same page
function logoutUser() {
    if (!confirm('Are you sure you want to logout?')) return;
    
    fetch('auth/logout.php', { 
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

// Product slider functionality
function scrollSlider(direction) {
    const slider = document.getElementById('productSlider');
    const cards = slider.querySelectorAll('.product-card');
    const visibleWidth = slider.clientWidth;
    const cardWidth = cards[0] ? cards[0].offsetWidth : 300;
    const currentScroll = slider.scrollLeft;

    // Clone cards at end for infinite scroll
    if (direction === 1 && currentScroll + visibleWidth >= slider.scrollWidth - cardWidth) {
        cards.forEach(card => {
            slider.appendChild(card.cloneNode(true));
        });
    }

    // Scroll to next/previous card
    const nextScroll = currentScroll + direction * cardWidth;
    slider.scrollTo({
        left: nextScroll,
        behavior: 'smooth'
    });
}

// Exclusive slider functionality
function scrollExclusiveSlider(direction) {
    const slider = document.getElementById('exclusiveProductSlider');
    const cards = slider.querySelectorAll('.product-card');
    const visibleWidth = slider.clientWidth;
    const cardWidth = cards[0] ? cards[0].offsetWidth : 300;
    const currentScroll = slider.scrollLeft;

    // Clone cards at end for infinite scroll
    if (direction === 1 && currentScroll + visibleWidth >= slider.scrollWidth - cardWidth) {
        cards.forEach(card => {
            slider.appendChild(card.cloneNode(true));
        });
    }

    // Scroll to next/previous card
    const nextScroll = currentScroll + direction * cardWidth;
    slider.scrollTo({
        left: nextScroll,
        behavior: 'smooth'
    });
}

// Auto scroll functionality
let autoScrollInterval;

function startAutoScroll() {
    autoScrollInterval = setInterval(() => {
        scrollSlider(1);
    }, 4000);
}

function stopAutoScroll() {
    clearInterval(autoScrollInterval);
}

// Size selection handling
document.addEventListener('click', function(e) {
    if (e.target.closest('label') && e.target.type === 'radio') {
        const card = e.target.closest('.product-card');
        const allLabels = card.querySelectorAll('.size-options .btn');
        allLabels.forEach(l => l.classList.remove('active'));
        e.target.closest('label').classList.add('active');

        // Update size text label
        const selectedSize = e.target.closest('label').textContent.trim();
        const sizeLabel = card.querySelector('.size-label strong');
        if (sizeLabel) {
            sizeLabel.textContent = selectedSize;
        }
    }
});

// Add to cart functionality
async function addToCartFromSlider(event, productId) {
    event.preventDefault();
    event.stopPropagation();
    
    // REMOVED LOGIN REQUIREMENT - GUESTS CAN ADD TO CART
    
    const card = event.target.closest('.product-card');
    const selectedSizeInput = card.querySelector('.size-options input:checked');
    const selectedSize = selectedSizeInput ? selectedSizeInput.value : null;
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    button.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', 1);
        if (selectedSize) {
            formData.append('size', selectedSize);
        }

        const response = await fetch('shop/api/cart.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Product added to cart!', 'success');
            updateCartCount(data.cart_summary?.item_count || 0);
            
            button.innerHTML = '<i class="fas fa-check"></i> Added!';
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
        } else {
            showNotification(data.message || 'Failed to add to cart', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showNotification('Error adding product to cart', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Search functionality
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
        const response = await fetch(`shop/api/search.php?q=${encodeURIComponent(searchTerm)}`);
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

    let html = '<div class="row">';
    products.forEach(product => {
        html += `
            <div class="col-md-4 mb-3">
                <div class="card">
                    <img src="${product.primary_image || '/assets/images/default-product.jpg'}" 
                         class="card-img-top" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h6 class="card-title">${product.name}</h6>
                        <p class="card-text">₹${parseFloat(product.price).toFixed(2)}</p>
                        <a href="shop/product.php?id=${product.id}" class="btn btn-primary btn-sm">View Product</a>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    resultsContainer.innerHTML = html;
}



// Utility functions
function updateCartCount(count) {
    const cartBadge = document.getElementById('cartBadge');
    if (count > 0) {
        if (cartBadge) {
            cartBadge.textContent = count;
            cartBadge.style.display = 'inline';
        } else {
            // Create cart badge if it doesn't exist
            const cartIcon = document.querySelector('.fa-bag-shopping');
            if (cartIcon) {
                const badge = document.createElement('span');
                badge.id = 'cartBadge';
                badge.className = 'position-absolute badge badge-danger';
                badge.style.cssText = 'top: -8px; right: -8px; font-size: 0.7rem;';
                badge.textContent = count;
                cartIcon.parentElement.appendChild(badge);
            }
        }
    } else {
        if (cartBadge) {
            cartBadge.style.display = 'none';
        }
    }
}

function showNotification(message, type) {
    // Remove existing notifications
    const existingAlerts = document.querySelectorAll('.velona-notification');
    existingAlerts.forEach(alert => alert.remove());
    
    const typeClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';
    
    const notification = document.createElement('div');
    notification.className = `alert ${typeClass} alert-dismissible fade show velona-notification`;
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
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

// Handle escape key to close modals
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAllPopups();
    }
});

// Initialize page on DOM content loaded
document.addEventListener('DOMContentLoaded', function() {
    // Start auto scroll
    startAutoScroll();
    
    // Pause on hover
    const sliders = document.querySelectorAll('.slider');
    sliders.forEach(slider => {
        slider.addEventListener('mouseenter', stopAutoScroll);
        slider.addEventListener('mouseleave', startAutoScroll);
    });

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

// Auto-refresh user data every 30 seconds if logged in
if (currentUser) {
    setInterval(() => {
        // Refresh wallet balance in navbar
        fetch('wallet/get-balance.php')
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
    $(document).ready(function () {
        // Get URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const modalParam = urlParams.get('modal');

        if (modalParam) {
            // Match parameter to modal ID
            let modalId = '';
            switch (modalParam) {
                case 'privacy':
                    modalId = '#privacyPolicyModal';
                    break;
                case 'affiliate':
                    modalId = '#affiliateTermsModal';
                    break;
                case 'shipping':
                    modalId = '#shippingReturnsModal';
                    break;
                case 'terms':
                    modalId = '#termsAndConditionsModal';
                    break;
            }

            if (modalId) {
                $(modalId).modal('show');
            }
        }
    });
</script>


</body>
</html>