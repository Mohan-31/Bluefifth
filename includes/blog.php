<?php

require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';
// Check if user is logged in
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;

// Get all categories for navigation
$categories = getAllCategories();

// Get user's cart summary if logged in
$cartSummary = $isLoggedIn ? getCartSummary($currentUser['id']) : ['item_count' => 0];

// Get wallet balance if logged in
$walletBalance = $isLoggedIn ? getWalletBalance($currentUser['id']) : ['points' => 0, 'pending_points' => 0];

// Set site name for navbar
$siteName = 'Bluefifth';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog | Bluefifth</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
  <style>
    :root {
      --primary: #1E3A8A;
      --secondary: #222927;
      --accent: #2563EB;
      --bg: #ffffff;
      --font: 'Poppins', sans-serif;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: var(--font);
    }

    body {
      background: var(--bg);
      color: var(--secondary);
      line-height: 1.7;
    }

    header {
      background: var(--secondary);
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: #fff;
    }

    header h1 {
      font-size: 1.5rem;
      color: var(--primary);
      letter-spacing: 1px;
    }

    .hero {
      padding: 5rem 2rem;
      text-align: center;
      background: linear-gradient(to bottom right, var(--primary), var(--accent));
      color: var(--secondary);
      border-radius: 0 0 0px 0px;
    }

    .hero h2 {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      font-weight: 700;
      color: white;
    }

    .hero p {
      font-size: 1.2rem;
      max-width: 700px;
      margin: auto;
      color: white;
      opacity: 0.9;
    }

    .blog-section {
      max-width: 1000px;
      margin: 3rem auto;
      padding: 2rem;
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    .blog-section h3 {
      font-size: 1.8rem;
      margin: 2rem 0 1rem;
      color: var(--accent);
    }

    .blog-section p {
      margin-bottom: 1rem;
      text-align: left;
      font-size: 1rem;
    }

    .highlight {
      font-weight: 600;
      color: var(--secondary);
      margin: 0.5rem 0;
      display: block;
    }

    .quote {
      background: var(--primary);
      padding: 1rem;
      border-left: 5px solid var(--accent);
      margin: 1.5rem 0;
      font-style: italic;
      border-radius: 10px;
    }

    footer {
      background: var(--secondary);
      color: #fff;
      text-align: center;
      padding: 1.5rem;
      margin-top: 3rem;
    }

    footer p {
      margin: 0.5rem 0;
    }

    footer a {
      color: var(--primary);
      text-decoration: none;
    }

    footer a:hover {
      text-decoration: underline;
    }

    /* Scroll animation */
    .fade-in {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }

    .fade-in.show {
      opacity: 1;
      transform: translateY(0);
    }

    @media (max-width: 768px) {
      .hero h2 {
        font-size: 2rem;
      }
      .blog-section {
        margin: 2rem 1rem;
        padding: 1.5rem;
      }
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
            width:150px;
        }
        /* Tablet View (min-width: 768px) */
        @media (min-width: 768px) {
        .img-responsive{
            width:150px;
        }
        }

        /* Laptop/Desktop View (min-width: 1024px or 1200px) */
        @media (min-width: 1024px) {
        .img-responsive{
            width:200px;
            margin-left:110px;
        }
        }
        .product-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px; /* space between cards */
        }
        .product-card {
            margin-bottom: 20px; /* vertical gap */
            border-top-left-radius: 20px;   /* round top corners */
            border-top-right-radius: 20px;
            border-bottom-left-radius: 20px; /* round bottom corners */
            border-bottom-right-radius: 20px;
        }
        .product-card:not(:last-child) {
            margin-right: 20px; /* horizontal gap, but won’t affect last card in row */
        }
        .add-to-cart{
            border-bottom-left-radius: 20px; /* round bottom corners */
            border-bottom-right-radius: 20px;
        }
        /* Default: Mobile First (small screens) */
        .img-responsive{
            width:120px;
        }
        .nav-align{
            margin-top:80px;
        }
                /* Tablet View (min-width: 768px) */
        @media (min-width: 768px) {
        .img-responsive{
            width:120px;
        }
        .nav-align{
            margin-top:80px;
        }
        }

        /* Laptop/Desktop View (min-width: 1024px or 1200px) */
        @media (min-width: 1024px) {
        .img-responsive{
            width:200px;
            margin-left:110px;
        }
        .nav-align{
            margin-top:165px;
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
  </style>
</head>
<body>
    
    <?php include '../includes/timer.php'; ?>


<!-- Silent Authentication Loading -->
<div class="auth-loading" id="auth-loading">
    <div class="spinner"></div>
    <p>Checking authentication...</p>
</div>

<!-- Navigation Bar - UNIFIED Shopping + Referral -->
<div class="container-fluid shadow fixed-top" style="background:#FFFFFF; margin-top:48px;">
    <nav class="container navbar navbar-expand-lg navbar-light nav-bg-light pt-3 pt-lg-4 flex-column sticky-top">
        <!-- Top Row -->
        <div class="w-100 d-flex justify-content-between align-items-center">
            <button class="navbar-toggler border-menu" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
                    aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars fa-xl" style="color: #000000;"></i>
            </button>
            
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-magnifying-glass fa-xl d-none d-lg-block" style="color: #000000; cursor: pointer;" onclick="toggleSearch()"></i>
            </div>

            <div class="text-center ">
                <a class="navbar-brand mx-auto " href="../index.php">
                    <img src="../assets/images/logo.jpg" class="mb-1  img-responsive" alt="<?= htmlspecialchars($siteName) ?>" >
                </a>
            </div>

            <!-- Fixed navbar icons with proper spacing -->
            <div class="d-flex align-items-center d-flex ">
                <?php if ($isLoggedIn): ?>
                    <!-- Wallet Points Display -->
                    <div class="d-none d-lg-block text-center" style="margin-right: 20px;">
                        <small class="text-muted d-block">Wallet</small>
                        <span class="font-weight-bold text-success">₹<?= number_format($walletBalance['points'] + $walletBalance['pending_points']) ?></span>
                    </div>
                    
                    <!-- User Profile Dropdown -->
                    <div class="dropdown" style="margin-right: 15px;">
                        <i class="fa-regular fa-user fa-xl dropdown-toggle d-none d-md-block" style="color: #000000; cursor: pointer;" 
                           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></i>
                        <div class="dropdown-menu dropdown-menu-right">
                            <div class="dropdown-header">
                                <strong><?= htmlspecialchars($currentUser['name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($currentUser['email']) ?></small>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../profile.php" onclick="">
                                <i class="fas fa-user mr-2"></i>My Profile
                            </a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="showReferralPopup()">
                                <i class="fas fa-chart-line mr-2"></i>My Referrals
                            </a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="showWalletPopup()">
                                <i class="fas fa-wallet mr-2"></i>My Wallet
                            </a>
                            <a class="dropdown-item" href="../account/orders.php">
                                <i class="fas fa-shopping-bag mr-2"></i>My Orders
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="logoutUser()">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login Button for Guests -->
                    <div class="dropdown" style="margin-right: 15px;">
                        <i class="fa-regular fa-user fa-xl dropdown-toggle d-none d-md-block" style="color: #000000; cursor: pointer;" 
                           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></i>
                        <div class="dropdown-menu dropdown-menu-right">
                            <div class="dropdown-header">
                                <strong>Guest User</strong>
                                <br><small class="text-muted">Login to access features</small>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="showReferralPopup()">
                                <i class="fas fa-chart-line mr-2"></i>My Referrals
                            </a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="showWalletPopup()">
                                <i class="fas fa-wallet mr-2"></i>My Wallet
                            </a>
                            <a class="dropdown-item" href="../account/orders.php">
                                <i class="fas fa-shopping-bag mr-2"></i>My Orders
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="triggerOneTapLogin()">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                
            <div class="d-flex align-items-center d-lg-none d-block ml-0 mr-2">
                <i class="fa-solid fa-magnifying-glass fa-xl" style="color: #000000; cursor: pointer;" onclick="toggleSearch()"></i>
            </div>
                <!-- Shopping Cart -->
                <a href="../shop/cart.php" class="position-relative ">
                    <i class="fa-solid fa-cart-shopping fa-xl" style="color: #000000; "></i>
                    <?php if ($cartSummary['item_count'] > 0): ?>
                        <span class="position-absolute badge badge-danger" style="top: -8px; right: -8px; font-size: 0.7rem;">
                            <?= $cartSummary['item_count'] ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <!-- Bottom Row - Navigation -->
        <div class="w-100 mt-3">
            <div class="collapse navbar-collapse justify-content-between" id="navbarSupportedContent">
                <ul class="navbar-nav m-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-link-st" href="../index.php">HOME</a>
                    </li>
                    
                    <!-- Dynamic Categories -->
                    <?php foreach ($categories as $category): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-st" href="../shop/category.php?category=<?= urlencode($category['slug']) ?>">
                                <?= strtoupper(htmlspecialchars($category['name'])) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link nav-link-st active" href="../includes/blog.php">BLOG</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link nav-link-st " href="../includes/about.php">ABOUT US</a>
                    </li>
                    
                    <!-- Mobile User Menu -->
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item mt-4 mb-4 d-lg-none d-block">
                            <div class="nav-link">
                                <strong><?= htmlspecialchars($currentUser['name']) ?></strong>
                                <br><small>Wallet: ₹<?= number_format($walletBalance['points'] + $walletBalance['pending_points']) ?></small>
                            </div>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="../profile.php" class="nav-link">
                                <i class="fa-regular fa-user mr-2"></i>My Profile
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="javascript:void(0)" onclick="showReferralPopup()" class="nav-link">
                                <i class="fa-regular fa-chart-line mr-2"></i>My Referrals
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="javascript:void(0)" onclick="showWalletPopup()" class="nav-link">
                                <i class="fa-regular fa-wallet mr-2"></i>My Wallet
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="orders/my-orders.php" class="nav-link">
                                <i class="fa-regular fa-shopping-bag mr-2"></i>My Orders
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="javascript:void(0)" onclick="logoutUser()" class="nav-link">
                                <i class="fa-regular fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item active mt-4 mb-4 d-lg-none d-block">
                            <a href="javascript:void(0)" class="nav-link" onclick="triggerOneTapLogin()">
                                <i class="fa-regular fa-user fa-xl mr-3"></i>
                                <span class="nav-log">Log in</span>
                            </a>
                        </li>
                         <li class="nav-item d-lg-none d-block">
                            <a href="javascript:void(0)" onclick="showReferralPopup()" class="nav-link">
                                <i class="fa-regular fa-chart-line mr-2"></i>My Referrals
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="javascript:void(0)" onclick="showWalletPopup()" class="nav-link">
                                <i class="fa-regular fa-wallet mr-2"></i>My Wallet
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="../account/orders.php" class="nav-link">
                                <i class="fa-regular fa-shopping-bag mr-2"></i>My Orders
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</div>

<!-- Popup Modal Overlay -->
<div class="modal-overlay" id="modal-overlay" onclick="closeAllPopups()"></div>

<!-- My Profile Popup -->
<div class="popup-modal m-auto" id="profile-popup">
    <button class="popup-close" href="../profile.php">&times;</button>
    <div class="popup-header">
        <h3><i class="fas fa-chart-line mr-2"></i>My Profile</h3>
    </div>
</div>

<!-- My Referrals Popup -->
<div class="popup-modal m-auto" id="referral-popup">
    <button class="popup-close" onclick="closeAllPopups()">&times;</button>
    <div class="popup-header">
        <h3><i class="fas fa-chart-line mr-2"></i>My Referrals</h3>
    </div>
    
    <div id="referral-content">
        <!-- Content will be loaded here -->
        <div class="text-center">
            <div class="spinner"></div>
            <p>Loading profile data...</p>
        </div>
    </div>
</div>

<!-- My Wallet Popup -->
<div class="popup-modal m-auto" id="wallet-popup">
    <button class="popup-close" onclick="closeAllPopups()">&times;</button>
    <div class="popup-header">
        <h3><i class="fas fa-wallet mr-2"></i>My Wallet</h3>
    </div>
    
    <div id="wallet-content">
        <!-- Content will be loaded here -->
        <div class="text-center">
            <div class="spinner"></div>
            <p>Loading wallet data...</p>
        </div>
    </div>
</div>

<!-- Search Modal -->
<div class="modal fade" id="searchModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Search Products</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search for products...">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button" onclick="performSearch()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div id="searchResults"></div>
            </div>
        </div>
    </div>
</div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============================================================================
// SEARCH FUNCTIONALITY - Missing from navbar.php
// ============================================================================

// Search functionality
function toggleSearch() {
    $('#searchModal').modal('show');
    setTimeout(() => {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.focus();
        }
    }, 500);
}

function handleSearchKeypress(event) {
    if (event.key === 'Enter') {
        performSearch();
    }
}

async function performSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.trim();
    
    if (searchTerm.length < 2) {
        if (typeof showNotification === 'function') {
            showNotification('Please enter at least 2 characters', 'warning');
        }
        return;
    }

    try {
        // FIXED: Simple and reliable path detection
        const currentUrl = window.location.href;
        let apiPath;
        
        if (currentUrl.includes('/shop/')) {
            // We're in a shop page (category.php, cart.php, product.php)
            apiPath = 'api/search.php';
        } else {
            // We're in root (index.php, about.php, etc.)
            apiPath = 'shop/api/search.php';
        }
        
        console.log('🔍 Search from:', currentUrl);
        console.log('🔍 Using API path:', apiPath);
        
        const response = await fetch(`${apiPath}?q=${encodeURIComponent(searchTerm)}`);
        
        // Debug the response
        console.log('🔍 Response status:', response.status);
        console.log('🔍 Response URL:', response.url);
        
        if (!response.ok) {
            throw new Error(`API not found: ${response.status} - ${response.url}`);
        }
        
        const data = await response.json();
        console.log('🔍 Search results:', data);

        if (data.success && data.products) {
            displaySearchResults(data.products);
        } else {
            document.getElementById('searchResults').innerHTML = '<p class="text-muted">No products found.</p>';
        }
    } catch (error) {
        console.error('🚨 Search error:', error);
        document.getElementById('searchResults').innerHTML = `
            <div class="alert alert-danger">
                <strong>Search Error:</strong> ${error.message}<br>
                <small>URL: ${window.location.href}</small><br>
                <small>Tried API: ${apiPath || 'undefined'}</small>
            </div>
        `;
    }
}

function displaySearchResults(products) {
    const resultsContainer = document.getElementById('searchResults');
    
    if (!resultsContainer) return;
    
    if (products.length === 0) {
        resultsContainer.innerHTML = '<p class="text-muted">No products found.</p>';
        return;
    }

    // FIXED: Correct path detection for product links
    const currentPath = window.location.pathname;
    let productBasePath;
    let imageBasePath;
    
    if (currentPath === '/' || currentPath.endsWith('/index.php') || currentPath.endsWith('/')) {
        // For index.php (root)
        productBasePath = 'shop/';
        imageBasePath = '';
    } else if (currentPath.includes('/shop/')) {
        // For pages inside /shop/ folder
        productBasePath = '';
        imageBasePath = '../';
    } else {
        // For other pages
        productBasePath = 'shop/';
        imageBasePath = '';
    }

    let html = '<div class="row">';
    products.forEach(product => {
        // Fix image path
        let imagePath = product.primary_image;
        if (imagePath && !imagePath.startsWith('http')) {
            if (!imagePath.startsWith('../') && !imagePath.startsWith('/')) {
                imagePath = imageBasePath + imagePath;
            }
        }
        if (!imagePath) {
            imagePath = `${imageBasePath}assets/images/default-product.jpg`;
        }
        
        html += `
            <div class="col-md-4 mb-3">
                <div class="card">
                    <img src="${imagePath}" 
                         class="card-img-top" style="height: 200px; object-fit: cover;" 
                         alt="${product.name}"
                         onerror="this.src='${imageBasePath}assets/images/default-product.jpg'">
                    <div class="card-body">
                        <h6 class="card-title">${product.name}</h6>
                        <p class="card-text">₹${parseFloat(product.price).toFixed(2)}</p>
                        <a href="${productBasePath}product.php?id=${product.id}" class="btn btn-primary btn-sm">View Product</a>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    resultsContainer.innerHTML = html;
}

// ============================================================================
// NEWSLETTER FUNCTIONALITY - Missing from navbar.php
// ============================================================================

async function subscribeNewsletter(event) {
    event.preventDefault();
    const email = document.getElementById('newsletterEmail').value;
    
    if (!email) {
        if (typeof showNotification === 'function') {
            showNotification('Please enter your email address', 'warning');
        }
        return;
    }
    
    try {
        // FIXED: Same path logic as search
        const currentUrl = window.location.href;
        let apiPath;
        
        if (currentUrl.includes('/shop/')) {
            apiPath = '../api/newsletter.php';
        } else {
            apiPath = 'api/newsletter.php';
        }
        
        const response = await fetch(apiPath, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({email: email})
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (typeof showNotification === 'function') {
                showNotification('Successfully subscribed to newsletter!', 'success');
            }
            document.getElementById('newsletterEmail').value = '';
        } else {
            if (typeof showNotification === 'function') {
                showNotification(data.message || 'Subscription failed', 'error');
            }
        }
    } catch (error) {
        console.error('Newsletter subscription error:', error);
        if (typeof showNotification === 'function') {
            showNotification('Subscription failed', 'error');
        }
    }
}

// ============================================================================
// NOTIFICATION SYSTEM - Missing from navbar.php
// ============================================================================

function showNotification(message, type = 'info') {
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

// ============================================================================
// INITIALIZE SEARCH FUNCTIONALITY
// ============================================================================

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
    
    // Focus search input when modal opens
    $('#searchModal').on('shown.bs.modal', function () {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.focus();
        }
    });

    // Clear search results when modal closes
    $('#searchModal').on('hidden.bs.modal', function () {
        const searchResults = document.getElementById('searchResults');
        const searchInput = document.getElementById('searchInput');
        
        if (searchResults) searchResults.innerHTML = '';
        if (searchInput) searchInput.value = '';
    });
});

console.log('🔍 Navbar search functionality loaded');
</script>     

      

  <!-- Hero -->
  <section class="hero nav-align">
    <h2>Blog</h2>
    <p>"One Wear. A Thousand Lives. A Greener Tomorrow."</p>
  </section>

  <!-- Blog Content -->
  <section class="blog-section fade-in">
    <p>When was the last time a wear meant more than just a piece of clothing?</p>
    <p>At Bluefifth, we believe fashion can be a force for good—uplifting lives, preserving nature, and shaping the future. Every time you choose a Bluefifth wear, you’re not just investing in soft, luxurious fabric—you’re investing in people, purpose, and the planet.</p>

    <h3>Behind Every Stitch, A Story</h3>
    <p>From the sunlit cotton fields of India to the precision of our AI-driven production lines, over 1,000 individuals are part of your wear's journey.</p>
    <span class="highlight">The farmer</span>
    <p>who tends the cotton crop with care</p>
    <span class="highlight">The tailor</span>
    <p>who crafts each seam with skill</p>
    <span class="highlight">The dyeing unit worker</span>
    <p>ensuring eco-safe colors</p>
    <span class="highlight">The driver</span>
    <p>who keeps the supply chain moving</p>
    <span class="highlight">The creative team</span>
    <p>designing your online experience</p>
    <div class="quote text-light">Your choice supports not just a product, but livelihoods, education, healthcare, and hope.</div>

    <h3>Purpose in Every Thread</h3>
    <p>Sustainability isn’t a trend here—it’s a responsibility.</p>
    <p>Our factories run on renewable energy, aligning with India’s goal of 500 GW non-fossil fuel energy capacity by 2030.</p>
    <p>AI integration allows us to reduce waste, optimize resources, and maintain exceptional quality.</p>
    <p>We embrace circular fashion, closing the loop from fiber to fashion in the most ethical and eco-conscious way possible.</p>

    <h3>Be the Change. Wear the Change.</h3>
    <p>When you wear Bluefifth, you’re telling the world that quality matters. That lives matter. That the planet matters.</p>
    <p>You’re not just making a fashion choice—you’re making a life choice.<br>
    A choice for dignity, sustainability, and a greener tomorrow.</p>
    <div class="quote text-light">Bluefifth. Wear Purpose. Change Lives.</div>
  </section>

 <footer class="footer">
    <div class="container-fluid pb-3 p-5">
        <div class="row">
            <div class="col-6 col-md-2 text-left" style="letter-spacing: 2px; text-transform: uppercase;">
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
            <div class="col-6 col-md-2 mt-5 text-left" style="letter-spacing: 2px; text-transform: uppercase;">
                <h5>&nbsp;</h5>
                <a href="#" data-toggle="modal" data-target="#privacyPolicyModal">Privacy & Policy</a>
                <a href="#" data-toggle="modal" data-target="#affiliateTermsModal">Affiliate Terms and conditions</a>
                <a href="#" data-toggle="modal" data-target="#shippingReturnsModal">Shipping & Returns</a>
                <a href="#" data-toggle="modal" data-target="#termsAndConditionsModal">Terms & Conditions</a>
            </div>
            <div class="col-md-4 text-left">
                <h5 style="letter-spacing: 2px; text-transform: uppercase;">About BLUEFIFTH</h5>
                <p class="mt-4" style="font-size: 14px; font-weight: 300; line-height: 26px;">
                    At Bluefifth, we create high-quality knitwear that blends comfort, style, and affordability. Our pieces are designed for everyday life—soft, breathable, and effortlessly modern.
                </p>
            </div>
            <div class="col-md-4 text-left">
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


  <!-- JS for fade-in effect -->
  <script>
    const fadeElems = document.querySelectorAll('.fade-in');
    window.addEventListener('scroll', () => {
      fadeElems.forEach(elem => {
        const pos = elem.getBoundingClientRect().top;
        if (pos < window.innerHeight - 100) {
          elem.classList.add('show');
        }
      });
    });
  </script>
  <script>

let currentUser = <?= $isLoggedIn ? 'true' : 'false' ?>;
let isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;


// SIMPLIFIED AUTH FOR NON-INDEX PAGES
(function() {
    'use strict';
    
    // Configuration
    const GOOGLE_CLIENT_ID = "340757900430-i8nl6l45ndveq9jmbvbah7ugquauj803.apps.googleusercontent.com";
    const AUTH_ENDPOINT = '../auth/google-callback.php';  

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
                window.location.reload();
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
            alert('Authentication system not ready. Please refresh the page and try again.');
            window.location.reload();
            return;
        }
        
        try {
            google.accounts.id.prompt((notification) => {
                if (notification.isNotDisplayed()) {
                    alert('Login not available at the moment. Please refresh the page and try again.');
                    window.location.reload();
                }
            });
        } catch (error) {
            console.error('One-Tap login error:', error);
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
function showProfilePopup() {
    document.getElementById('modal-overlay').classList.add('show');
    document.getElementById('referral-popup').classList.add('show');
    
    // Use the PHP variable directly instead of JavaScript variable
    <?php if ($isLoggedIn): ?>
        loadProfileData();
    <?php else: ?>
        showGuestProfileContent();
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
        <div class="guest-login-section ">
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
    fetch(basePath + '../wallet/get-balance.php')
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
function displayProfileData(data) {
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
            <h5>🔗 Your Profile Links</h5>
            
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
                    <button class="rounded-0 border-right border-light" onclick="copyReferralLink()" ><i class="fa-solid fa-copy"></i></button>
                    <button onclick="shareLinkViaWebShare()"><i class="fa-solid fa-share"></i></button>
                </div>
            </div>

            <div class="alert-custom alert-success">
                <strong>💰 Earning Structure:</strong><br>
                • 10% commission on first purchases<br>
                • 5% commission on subsequent purchases<br>
                • Claims available on 30th & 31st of every month
            </div>
        </div>
    `;
    
    document.getElementById('referral-content').innerHTML = content;
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

// Load wallet data - EXACT COPY FROM INDEX.PHP
function loadWalletData() {
    const basePath = '<?= $basePath ?>';
    fetch(basePath + '../wallet/get-balance.php')
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
    
    // Set focus to search input when modal opens
    $('#searchModal').on('shown.bs.modal', function () {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.focus();
        }
    });

    // Clear search results when modal closes
    $('#searchModal').on('hidden.bs.modal', function () {
        const searchResults = document.getElementById('searchResults');
        const searchInput = document.getElementById('searchInput');
        
        if (searchResults) searchResults.innerHTML = '';
        if (searchInput) searchInput.value = '';
    });
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

    <!-- Google Sign-In Script -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <!-- JAVASCRIPT -->
    <script>
        // Size selection functionality from original HTML
        document.addEventListener('click', function (e) {
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

        async function addToCartFromGrid(event, productId) {
            event.preventDefault();
            event.stopPropagation();
            
            // Ensure currentUser is defined
            if (typeof currentUser === 'undefined') {
                currentUser = <?= $isLoggedIn ? 'true' : 'false' ?>;
            }
            
            // REMOVED: Guest login requirement - guests can add to cart
            // if (!currentUser) {
            //     triggerOneTapLogin();
            //     return;
            // }

            const card = event.target.closest('.product-card');
            const selectedSizeInput = card?.querySelector('.size-options input:checked');
            const selectedSize = selectedSizeInput ? selectedSizeInput.closest('label').textContent.trim() : null;
            
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            button.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'add');  // CHANGED: Use 'add' not 'add_to_cart'
                formData.append('product_id', productId);
                formData.append('quantity', 1);
                if (selectedSize) {
                    formData.append('size', selectedSize);
                }

                const response = await fetch('api/cart.php', {  // CORRECT PATH for category.php
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Product added to cart!', 'success');
                    updateCartCount(data.cart_summary?.item_count || 0);
                    
                    // Show success state
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
                
                // Reset button
                button.innerHTML = originalText;
                button.disabled = false;
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

        // Sorting and filtering functions
        function applySorting() {
            const sortValue = document.getElementById('sortSelect').value;
            updateURL({ sort: sortValue, page: 1 });
        }

        function changeItemsPerPage() {
            const limitValue = document.getElementById('limitSelect').value;
            updateURL({ limit: limitValue, page: 1 });
        }

        function updateURL(params) {
            const url = new URL(window.location);
            
            // Update URL parameters
            Object.keys(params).forEach(key => {
                if (params[key]) {
                    url.searchParams.set(key, params[key]);
                } else {
                    url.searchParams.delete(key);
                }
            });

            // Reload page with new parameters
            window.location.href = url.toString();
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

        // Notification system
        function showNotification(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 5000);
        }

        // ============================================================================
        // MISSING SEARCH FUNCTIONS - ADD THESE
        // ============================================================================

        // Search functionality
        function toggleSearch() {
            $('#searchModal').modal('show');
            setTimeout(() => {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.focus();
                }
            }, 500);
        }

        async function performSearch() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) {
                console.error('Search input not found');
                return;
            }
            
            const searchTerm = searchInput.value.trim();
            
            if (searchTerm.length < 2) {
                showNotification('Please enter at least 2 characters', 'warning');
                return;
            }

            try {
                console.log('🔍 Category search - using ../shop/api/search.php');
                console.log('🔍 Search term:', searchTerm);
                
                const response = await fetch(`../shop/api/search.php?q=${encodeURIComponent(searchTerm)}`);
                console.log('🔍 Response status:', response.status);
                console.log('🔍 Response URL:', response.url);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('🔍 Search data:', data);

                if (data.success && data.products) {
                    displaySearchResults(data.products);
                } else {
                    document.getElementById('searchResults').innerHTML = '<p class="text-muted">No products found.</p>';
                }
            } catch (error) {
                console.error('Search error:', error);
                document.getElementById('searchResults').innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Search Error:</strong> ${error.message}<br>
                        <small>URL: ${window.location.href}</small><br>
                        <small>Tried: ../shop/api/search.php</small>
                    </div>
                `;
            }
        }

        function displaySearchResults(products) {
            const resultsContainer = document.getElementById('searchResults');
            
            if (!resultsContainer) {
                console.error('Search results container not found');
                return;
            }
            
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
                                alt="${product.name}"
                                onerror="this.src='../assets/images/default-product.jpg'">
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
            console.log('🔍 Search results displayed:', products.length, 'products');
        }

        // Update cart count in navigation
        function updateCartCount(count) {
            const cartBadge = document.getElementById('cartBadge');
            if (count > 0) {
                if (cartBadge) {
                    cartBadge.textContent = count;
                } else {
                    // Create badge if it doesn't exist
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

        // User menu toggle
        function toggleUserMenu() {
            // Simple redirect to profile or dashboard
            window.location.href = '../referral/dashboard.php';
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set focus to search input when modal opens
            $('#searchModal').on('shown.bs.modal', function () {
                $('#searchInput').focus();
            });

            // Clear search results when modal closes
            $('#searchModal').on('hidden.bs.modal', function () {
                document.getElementById('searchResults').innerHTML = '';
                document.getElementById('searchInput').value = '';
            });

            // Initialize tooltips if any
            if (typeof $().tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
    </script>
</body>
</html>
