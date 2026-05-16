<?php
require_once __DIR__ . '/timer.php';
// navbar.php - Unified Navigation Component
// This file should be created in your includes/ folder

// Required variables that should be set before including this file:
// $isLoggedIn, $currentUser, $categories, $cartSummary, $walletBalance, $siteName

// Set default values if not provided
$isLoggedIn = $isLoggedIn ?? false;
$currentUser = $currentUser ?? null;
$categories = $categories ?? [];
$cartSummary = $cartSummary ?? ['item_count' => 0];
$walletBalance = $walletBalance ?? ['points' => 0, 'pending_points' => 0];
$siteName = $siteName ?? 'Bluefifth';

$basePath = '/ecommerce-project/';

?>
<style>
        .img-responsive{
            width:120px;
            margin-left:20px;
        }
        .img-align{
        }
        /* Tablet View (min-width: 768px) */
        @media (min-width: 768px) {
        .img-responsive{
            width:120px;
        }
        }

        /* Laptop/Desktop View (min-width: 1024px or 1200px) */
        @media (min-width: 1024px) {
        .img-responsive{
            width:200px;
            margin-left:110px;
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
        /* Scroll-hide / scroll-reveal animation */
        #main-navbar {
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: transform;
        }
        #main-navbar.navbar-hidden {
            transform: translateY(-100%);
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

        /* ── Popup overlay & modals (used from every page) ── */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            display: none;
        }
        .modal-overlay.show { display: block; }

        .popup-modal {
            position: fixed;
            top: 50%; left: 50%;
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
        .popup-modal.show { display: block; }

        @media (max-width: 768px) {
            .popup-modal { min-width: 95vw; margin: 1rem; }
        }

        .popup-close {
            position: absolute;
            top: 15px; right: 20px;
            background: none; border: none;
            font-size: 24px; cursor: pointer;
            color: #999;
            width: 30px; height: 30px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        .popup-close:hover { color: #333; background: #f8f9fa; }

        .popup-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .popup-header h3 { margin: 0; color: #333; font-weight: 600; }

        /* ── Auth loading spinner ── */
        .auth-loading {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255,255,255,0.95);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 3000;
            text-align: center;
            display: none;
        }
        .auth-loading.show { display: block; }

        .spinner {
            width: 40px; height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #000;
            border-radius: 50%;
            animation: navbarSpin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes navbarSpin {
            0%   { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ── Body offset for fixed navbar ──
           mobile / tablet (collapsed 1-row nav): 48px banners + ~62px nav  = 110px
           desktop (≥992px, 2-row expanded nav):  48px banners + ~112px nav = 160px  */
        .nav-align { margin-top: 130px; }
        @media (min-width: 992px) { .nav-align { margin-top: 185px; } }

</style>
<!-- Silent Authentication Loading -->
<div class="auth-loading" id="auth-loading">
    <div class="spinner"></div>
    <p>Checking authentication...</p>
</div>

<!-- Navigation Bar - UNIFIED Shopping + Referral -->
<div id="main-navbar" class="container-fluid shadow fixed-top" style="background:#FFFFFF; margin-top:48px;">
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
                <a class="navbar-brand mx-auto " href="/ecommerce-project/index.php">
                    <img src="/ecommerce-project/assets/images/logo.jpg" class="mb-1  img-responsive" alt="<?= htmlspecialchars($siteName) ?>" >
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
                                <strong><?= htmlspecialchars($currentUser['name'] ?? '') ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($currentUser['email'] ?? '') ?></small>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="/ecommerce-project/profile.php">
                                <i class="fas fa-user mr-2"></i>My Profile
                            </a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="showReferralPopup()">
                                <i class="fas fa-chart-line mr-2"></i>My Referrals
                            </a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="showWalletPopup()">
                                <i class="fas fa-wallet mr-2"></i>My Wallet
                            </a>
                            <a class="dropdown-item" href="/ecommerce-project/account/orders.php">
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
                            <a class="dropdown-item" href="/ecommerce-project/account/orders.php">
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
                <a href="/ecommerce-project/shop/cart.php" class="position-relative ">
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
                    <li class="nav-item ">
                        <a class="nav-link nav-link-st" href="/ecommerce-project/index.php">HOME</a>
                    </li>
                    
                    <!-- Dynamic Categories -->
                    <?php
                    $current_page = basename($_SERVER['PHP_SELF']);
                    $current_category = isset($_GET['category']) ? $_GET['category'] : '';
                    ?>
                    
                    <?php foreach ($categories as $category): ?>
                        <?php 
                            // check if we are on category.php AND the slug matches
                            $isActive = ($current_page == 'category.php' && $current_category == $category['slug']);
                        ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-st <?= $isActive ? 'active' : '' ?>"
                               href="/ecommerce-project/shop/category.php?category=<?= urlencode($category['slug']) ?>">
                                <?= strtoupper(htmlspecialchars($category['name'])) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link nav-link-st" href="/ecommerce-project/includes/blog.php">BLOG</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link nav-link-st" href="/ecommerce-project/includes/about.php">ABOUT US</a>
                    </li>
                    
                    <!-- Mobile User Menu -->
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item mt-4 mb-4 d-lg-none d-block">
                            <div class="nav-link">
                                <strong><?= htmlspecialchars($currentUser['name'] ?? '') ?></strong>
                                <br><small>Wallet: ₹<?= number_format($walletBalance['points'] + $walletBalance['pending_points']) ?></small>
                            </div>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="/ecommerce-project/profile.php" class="nav-link">
                                <i class="fa-regular fa-chart-line mr-2"></i>My Profile
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
                            <a href="/ecommerce-project/account/orders.php" class="nav-link">
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
                            <a href="/ecommerce-project/account/orders.php" class="nav-link">
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
    <button class="popup-close" onclick="closeAllPopups()">&times;</button>
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

<!-- Traditional Login / OTP Info Popup (shown to guests from any page) -->
<div class="popup-modal m-auto" id="traditional-login-popup">
    <button class="popup-close" onclick="closeAllPopups()">&times;</button>
    <div class="popup-header">
        <h3><i class="fas fa-sign-in-alt mr-2"></i>Login to Your Account</h3>
    </div>
    <div id="traditional-login-content" class="text-center" style="padding: 2rem;">
        <div style="font-size: 48px; margin-bottom: 12px;">🛍️</div>
        <h4 style="font-weight:700; color:#333; margin-bottom:8px;">No login needed to browse!</h4>
        <p class="text-muted mb-4">Just add products to your cart and we'll verify your phone at checkout — fast and secure.</p>
        <a href="/ecommerce-project/shop/category.php" class="btn btn-dark btn-block mb-3" onclick="closeAllPopups()">Continue Shopping</a>
        <a href="/ecommerce-project/checkout.php" class="btn btn-outline-secondary btn-block" onclick="closeAllPopups()">Go to Checkout</a>
        <div class="mt-4 pt-3 border-top">
            <small class="text-muted">Your identity is verified via OTP at checkout</small>
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
        
        apiPath = '/ecommerce-project/shop/api/search.php';
        
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

    const productBasePath = '/ecommerce-project/shop/';
    const imageBasePath = '/ecommerce-project/';

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
        
        apiPath = '/ecommerce-project/api/newsletter.php';
        
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

// ============================================================================
// POPUP & AUTH FUNCTIONS — fallback implementations for every page.
// Pages that need richer behaviour (index.php, profile.php) override these
// by re-declaring the same function names in their own <script> block.
// ============================================================================

// Hide auth-loading spinner as soon as DOM is ready
(function () {
    function _hideAuthLoading() {
        var el = document.getElementById('auth-loading');
        if (el) el.classList.remove('show');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _hideAuthLoading);
    } else {
        _hideAuthLoading();
    }
}());

function closeAllPopups() {
    ['modal-overlay','referral-popup','wallet-popup','traditional-login-popup'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('show');
    });
}

function showTraditionalLoginPopup() {
    var overlay = document.getElementById('modal-overlay');
    var popup   = document.getElementById('traditional-login-popup');
    if (overlay) overlay.classList.add('show');
    if (popup)   popup.classList.add('show');
}

function triggerOneTapLogin() {
    showTraditionalLoginPopup();
}

function showReferralPopup() {
    var overlay = document.getElementById('modal-overlay');
    var popup   = document.getElementById('referral-popup');
    if (overlay) overlay.classList.add('show');
    if (popup)   popup.classList.add('show');
    // Default guest content — pages that load real data override this function
    var content = document.getElementById('referral-content');
    if (content && content.querySelector('.spinner')) {
        content.innerHTML = '<div style="text-align:center;padding:2rem;color:#666;">Login via checkout to view your referrals.</div>';
    }
}

function showWalletPopup() {
    var overlay = document.getElementById('modal-overlay');
    var popup   = document.getElementById('wallet-popup');
    if (overlay) overlay.classList.add('show');
    if (popup)   popup.classList.add('show');
    // Default guest content — pages that load real data override this function
    var content = document.getElementById('wallet-content');
    if (content && content.querySelector('.spinner')) {
        content.innerHTML = '<div style="text-align:center;padding:2rem;color:#666;">Login via checkout to view your wallet.</div>';
    }
}

function logoutUser() {
    if (!confirm('Are you sure you want to logout?')) return;
    fetch('/ecommerce-project/auth/logout.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'}
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        if (typeof showNotification === 'function') {
            showNotification(data.cart_preserved
                ? 'Logged out. ' + (data.cart_item_count || 0) + ' cart items preserved.'
                : 'Logged out successfully.', 'success');
        }
        setTimeout(function(){ window.location.reload(); }, 800);
    })
    .catch(function() {
        window.location.reload();
    });
}

// ============================================================================
// SCROLL-HIDE / SCROLL-REVEAL NAVBAR
// ============================================================================
(function () {
    var navbar   = document.getElementById('main-navbar');
    var lastY    = 0;
    var ticking  = false;

    function update() {
        var y = window.scrollY || window.pageYOffset;

        if (y < 80) {
            // Near top — always visible
            navbar.classList.remove('navbar-hidden');
        } else if (y > lastY) {
            // Scrolling down → hide
            navbar.classList.add('navbar-hidden');
        } else {
            // Scrolling up → show
            navbar.classList.remove('navbar-hidden');
        }

        lastY    = y;
        ticking  = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            requestAnimationFrame(update);
            ticking = true;
        }
    }, { passive: true });

    // Always reveal navbar when mobile menu opens
    document.addEventListener('DOMContentLoaded', function () {
        var collapseEl = document.getElementById('navbarSupportedContent');
        if (collapseEl) {
            $(collapseEl).on('show.bs.collapse', function () {
                navbar.classList.remove('navbar-hidden');
            });
        }
    });
}());
</script>