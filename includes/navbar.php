<?php
require_once __DIR__ . '/timer.php';
if (!defined('GOOGLE_CLIENT_ID')) require_once __DIR__ . '/../includes/config.php';
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

$basePath = BASE_PATH . '/';

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
           mobile  (collapsed 1-row nav):  48px banners + ~62px nav  = 110px → +42px breathing
           tablet  (768px–991px):          48px banners + ~68px nav  = 116px → +42px breathing
           desktop (≥992px, 2-row nav):    48px banners + ~112px nav = 160px → +36px breathing */
        .nav-align { margin-top: 152px; }
        @media (min-width: 768px)  { .nav-align { margin-top: 158px; } }
        @media (min-width: 992px)  { .nav-align { margin-top: 196px; } }

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
                <a class="navbar-brand mx-auto " href="<?= $basePath ?>index.php">
                    <img src="<?= $basePath ?>assets/images/logo.jpg" class="mb-1  img-responsive" alt="<?= htmlspecialchars($siteName) ?>" >
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
                            <a class="dropdown-item" href="<?= $basePath ?>profile.php">
                                <i class="fas fa-user mr-2"></i>My Profile
                            </a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="showReferralPopup()">
                                <i class="fas fa-share-alt mr-2"></i>My Referrals
                            </a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="showWalletPopup()">
                                <i class="fas fa-wallet mr-2"></i>My Wallet
                            </a>
                            <a class="dropdown-item" href="<?= $basePath ?>account/orders.php">
                                <i class="fas fa-box mr-2"></i>My Orders
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
                                <i class="fas fa-share-alt mr-2"></i>My Referrals
                            </a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="showWalletPopup()">
                                <i class="fas fa-wallet mr-2"></i>My Wallet
                            </a>
                            <a class="dropdown-item" href="<?= $basePath ?>account/orders.php">
                                <i class="fas fa-box mr-2"></i>My Orders
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
                <a href="<?= $basePath ?>shop/cart.php" class="position-relative ">
                    <i class="fa-solid fa-cart-shopping fa-xl" style="color: #000000; "></i>
                    <?php if ($cartSummary['item_count'] > 0): ?>
                        <span id="cartBadge" class="position-absolute badge badge-danger" style="top: -8px; right: -8px; font-size: 0.7rem;">
                            <?= $cartSummary['item_count'] ?>
                        </span>
                    <?php else: ?>
                        <span id="cartBadge" class="position-absolute badge badge-danger d-none" style="top: -8px; right: -8px; font-size: 0.7rem;"></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        
        <!-- Bottom Row - Navigation -->
        <div class="w-100 mt-3">
            <div class="collapse navbar-collapse justify-content-between" id="navbarSupportedContent">
                <ul class="navbar-nav m-auto">
                    <li class="nav-item ">
                        <a class="nav-link nav-link-st" href="<?= $basePath ?>index.php">HOME</a>
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
                               href="<?= $basePath ?>shop/category.php?category=<?= urlencode($category['slug']) ?>">
                                <?= strtoupper(htmlspecialchars($category['name'])) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link nav-link-st" href="<?= $basePath ?>includes/blog.php">BLOG</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link nav-link-st" href="<?= $basePath ?>includes/about.php">ABOUT US</a>
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
                            <a href="<?= $basePath ?>profile.php" class="nav-link">
                                <i class="fa-regular fa-chart-line mr-2"></i>My Profile
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="javascript:void(0)" onclick="showReferralPopup()" class="nav-link">
                                <i class="fas fa-share-alt mr-2"></i>My Referrals
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="javascript:void(0)" onclick="showWalletPopup()" class="nav-link">
                                <i class="fas fa-wallet mr-2"></i>My Wallet
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="<?= $basePath ?>account/orders.php" class="nav-link">
                                <i class="fas fa-box mr-2"></i>My Orders
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="javascript:void(0)" onclick="logoutUser()" class="nav-link">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
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
                                <i class="fas fa-share-alt mr-2"></i>My Referrals
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="javascript:void(0)" onclick="showWalletPopup()" class="nav-link">
                                <i class="fas fa-wallet mr-2"></i>My Wallet
                            </a>
                        </li>
                        <li class="nav-item d-lg-none d-block">
                            <a href="<?= $basePath ?>account/orders.php" class="nav-link">
                                <i class="fas fa-box mr-2"></i>My Orders
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

<!-- Login / Sign Up Modal — Modern Design -->
<div class="lm-modal" id="traditional-login-popup">

    <button class="lm-close" onclick="closeAllPopups()" aria-label="Close">
        <i class="fas fa-times"></i>
    </button>

    <!-- ── Step 1: Phone ── -->
    <div id="login-step-phone" class="lm-step">
        <div class="lm-brand-mark">B</div>
        <h2 class="lm-title">Login or Sign up</h2>
        <p class="lm-sub">Enter your mobile number to continue</p>

        <div class="lm-phone-wrap">
            <span class="lm-country">&#127470;&#127475; +91</span>
            <input type="tel" id="login-phone" name="phone" class="lm-phone-input"
                   placeholder="10-digit mobile number" maxlength="10"
                   autocomplete="tel"
                   oninput="this.value=this.value.replace(/\D/g,'')"
                   onkeydown="if(event.key==='Enter')loginSendOTP()">
        </div>

        <button class="lm-btn" id="login-send-btn" onclick="loginSendOTP()">
            Continue <i class="fas fa-arrow-right ml-2"></i>
        </button>

        <div class="lm-or"><span>OR</span></div>
        <div id="google-btn-container" class="lm-google-wrap"></div>

        <p class="lm-terms">
            By continuing, you agree to our
            <a href="#">Terms of Use</a> &amp; <a href="#">Privacy Policy</a>
        </p>
    </div>

    <!-- ── Step 2: OTP ── -->
    <div id="login-step-otp" class="lm-step" style="display:none;">
        <button class="lm-back" onclick="loginGoBack()">
            <i class="fas fa-arrow-left"></i>
        </button>

        <div class="lm-otp-icon"><i class="fas fa-mobile-alt"></i></div>
        <h2 class="lm-title">Verify your number</h2>
        <p class="lm-sub" id="otp-sent-msg">OTP sent to your number</p>

        <div class="otp-boxes" id="otp-boxes">
            <input type="tel" class="otp-box" maxlength="1" oninput="otpBoxInput(this,0)" onkeydown="otpBoxKey(event,0)" autocomplete="one-time-code">
            <input type="tel" class="otp-box" maxlength="1" oninput="otpBoxInput(this,1)" onkeydown="otpBoxKey(event,1)">
            <input type="tel" class="otp-box" maxlength="1" oninput="otpBoxInput(this,2)" onkeydown="otpBoxKey(event,2)">
            <input type="tel" class="otp-box" maxlength="1" oninput="otpBoxInput(this,3)" onkeydown="otpBoxKey(event,3)">
            <input type="tel" class="otp-box" maxlength="1" oninput="otpBoxInput(this,4)" onkeydown="otpBoxKey(event,4)">
            <input type="tel" class="otp-box" maxlength="1" oninput="otpBoxInput(this,5)" onkeydown="otpBoxKey(event,5)">
        </div>

        <div class="lm-timer-row">
            <span id="otp-timer-text"></span>
            <button id="otp-resend-btn" class="lm-resend" style="display:none;" onclick="loginSendOTP(true)">
                Resend OTP
            </button>
        </div>

        <button class="lm-btn" id="login-verify-btn" onclick="loginVerifyOTP()">
            Verify &amp; Login
        </button>
    </div>

    <!-- ── Step 3: Name (new users) ── -->
    <div id="login-step-name" class="lm-step" style="display:none;">
        <div class="lm-welcome-emoji">&#127881;</div>
        <h2 class="lm-title">You're in!</h2>
        <p class="lm-sub">What should we call you?</p>

        <input type="text" id="login-name" class="lm-text-input"
               placeholder="Enter your name" maxlength="50"
               onkeydown="if(event.key==='Enter')loginCompleteName()">

        <button class="lm-btn" onclick="loginCompleteName()">
            Let's Go <i class="fas fa-arrow-right ml-2"></i>
        </button>
    </div>

</div>

<!-- Google GIS script -->
<script src="https://accounts.google.com/gsi/client" async defer></script>

<!-- ═══════════════════════════════════════════════════════════
     SEARCH OVERLAY  (full-width slide-down panel)
═══════════════════════════════════════════════════════════ -->
<div id="searchOverlay" class="bf-search-overlay" role="search" aria-label="Site search">
    <div class="bf-search-backdrop" onclick="toggleSearch()"></div>
    <div class="bf-search-panel">
        <div class="bf-search-input-row">
            <i class="fas fa-search bf-search-icon-left" aria-hidden="true"></i>
            <input type="text" id="searchInput" class="bf-search-input"
                   placeholder="Search products…" autocomplete="off"
                   aria-label="Search products" spellcheck="false">
            <button class="bf-search-clear" id="searchClearBtn" onclick="clearSearchInput()" aria-label="Clear" style="display:none;">
                <i class="fas fa-times"></i>
            </button>
            <button class="bf-search-close-btn" onclick="toggleSearch()" aria-label="Close search">
                <span>Close</span> <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="searchSuggestions" class="bf-suggestions-panel" style="display:none;"></div>
        <div id="searchDefault" class="bf-search-default">
            <div id="recentSearchesSection" style="display:none;">
                <p class="bf-section-label">Recent Searches</p>
                <div id="recentSearchesList" class="bf-recent-list"></div>
            </div>
            <p class="bf-section-label mt-2">Try searching for…</p>
            <div class="bf-hint-chips">
                <span class="bf-hint-chip" onclick="applyHint(this)">T-Shirts</span>
                <span class="bf-hint-chip" onclick="applyHint(this)">Oversized</span>
                <span class="bf-hint-chip" onclick="applyHint(this)">Cotton</span>
                <span class="bf-hint-chip" onclick="applyHint(this)">Hoodie</span>
                <span class="bf-hint-chip" onclick="applyHint(this)">New Arrivals</span>
            </div>
        </div>
    </div>
</div>

<style>
.bf-search-overlay {
    position: fixed;
    top: 0; right: 0; bottom: 0; left: 0;
    z-index: 1600;
    display: none;
}
.bf-search-overlay.open {
    display: block;
}
.bf-search-backdrop {
    position: absolute;
    top: 0; right: 0; bottom: 0; left: 0;
    background: rgba(0,0,0,.35);
}
.bf-search-panel {
    position: absolute;
    top: 0; left: 0; right: 0;
    background: #fff;
    padding: 1.2rem 1rem .8rem;
    box-shadow: 0 6px 24px rgba(0,0,0,.12);
    max-height: 85vh;
    overflow-y: auto;
    animation: bfSlideDown .28s cubic-bezier(.4,0,.2,1) both;
}
@keyframes bfSlideDown {
    from { transform: translateY(-100%); }
    to   { transform: translateY(0); }
}
.bf-search-input-row {
    display: flex;
    align-items: center;
    border-bottom: 2px solid #111;
    padding-bottom: .6rem;
    margin: 0 auto .75rem;
    max-width: 760px;
}
.bf-search-icon-left { color: #555; font-size: 1rem; margin-right: .75rem; flex-shrink: 0; }
.bf-search-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 1.2rem;
    font-family: 'Poppins', sans-serif;
    background: transparent;
    color: #111;
    min-width: 0;
}
.bf-search-input::placeholder { color: #aaa; }
.bf-search-clear { background: none; border: none; color: #999; font-size: .9rem; cursor: pointer; padding: 4px 8px; margin-right: 4px; }
.bf-search-clear:hover { color: #333; }
.bf-search-close-btn {
    background: none;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 4px 14px;
    font-size: .78rem;
    color: #444;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background .2s;
}
.bf-search-close-btn:hover { background: #f5f5f5; }
.bf-search-close-btn i { margin-left: 4px; }
.bf-suggestions-panel { max-width: 760px; margin: 0 auto; }
.bf-suggestion-item {
    display: flex;
    align-items: center;
    padding: 9px 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: background .15s;
    text-decoration: none;
    color: inherit;
}
.bf-suggestion-item:hover { background: #f6f6f6; }
.bf-suggestion-img { width: 48px; height: 56px; object-fit: cover; border-radius: 6px; flex-shrink: 0; background: #f0f0f0; }
.bf-suggestion-img-placeholder {
    width: 48px; height: 56px; border-radius: 6px; flex-shrink: 0;
    background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 1.1rem;
}
.bf-suggestion-info { flex: 1; margin-left: 12px; min-width: 0; }
.bf-suggestion-name { font-size: .88rem; font-weight: 500; color: #222; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; }
.bf-suggestion-price { font-size: .82rem; color: #666; margin: 2px 0 0; }
.bf-suggestion-arrow { color: #bbb; font-size: .8rem; flex-shrink: 0; margin-left: 8px; }
.bf-suggestion-divider { border: none; border-top: 1px solid #f0f0f0; margin: 2px 0; }
.bf-suggestions-loading { display: flex; align-items: center; justify-content: center; padding: 1.5rem; color: #888; font-size: .9rem; gap: 8px; }
.bf-suggestions-empty { text-align: center; padding: 1.5rem; color: #888; font-size: .9rem; }
.bf-suggestions-header { font-size: .72rem; font-weight: 600; letter-spacing: .05em; color: #999; text-transform: uppercase; padding: 6px 8px 4px; }
.bf-view-all-link {
    display: block; text-align: center; padding: 10px; font-size: .85rem; color: #333;
    border-top: 1px solid #f0f0f0; margin-top: 4px; text-decoration: none; font-weight: 500; transition: background .15s;
}
.bf-view-all-link:hover { background: #f8f8f8; color: #111; }
.bf-search-default { max-width: 760px; margin: 0 auto; padding: .5rem 0 1rem; }
.bf-section-label { font-size: .72rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #999; margin: 0 0 .6rem; }
.bf-hint-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
.bf-hint-chip {
    background: #f5f5f5; border: 1px solid #e8e8e8; border-radius: 20px; padding: 5px 14px;
    font-size: .82rem; color: #333; cursor: pointer; transition: background .2s;
}
.bf-hint-chip:hover { background: #eee; }
.bf-recent-list { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .75rem; }
.bf-recent-item {
    background: #fff; border: 1px solid #e0e0e0; border-radius: 20px; padding: 4px 12px;
    font-size: .82rem; color: #444; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: background .2s;
}
.bf-recent-item:hover { background: #f5f5f5; }
.bf-recent-item i { font-size: .75rem; color: #bbb; }

/* ── Login Modal — Modern Redesign ── */
.lm-modal {
    display: none;
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    z-index: 2500;
    background: #fff;
    padding: 0;
    border-radius: 22px;
    min-width: 0;
    max-width: 420px;
    width: min(420px, 92vw);
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,.18);
}
.lm-modal.show { display: block; }
.lm-close {
    position: absolute;
    top: 14px; right: 14px;
    width: 34px; height: 34px;
    background: #f3f3f3;
    border: none;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    color: #666;
    font-size: .8rem;
    z-index: 10;
    transition: background .15s, color .15s;
}
.lm-close:hover { background: #e5e5e5; color: #111; }

.lm-step {
    padding: 2rem 1.75rem 1.75rem;
    position: relative;
    animation: lmIn .22s ease both;
}
@keyframes lmIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.lm-brand-mark {
    width: 46px; height: 46px;
    background: #111;
    border-radius: 13px;
    color: #fff;
    font-size: 1.3rem;
    font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 1.25rem;
    letter-spacing: -1px;
    font-family: 'Poppins', sans-serif;
}
.lm-title {
    font-size: 1.45rem;
    font-weight: 700;
    color: #111;
    margin: 0 0 .3rem;
    letter-spacing: -.3px;
}
.lm-sub {
    font-size: .88rem;
    color: #999;
    margin: 0 0 1.4rem;
    line-height: 1.5;
}

/* Phone field */
.lm-phone-wrap {
    display: flex;
    align-items: center;
    border: 1.5px solid #e4e4e4;
    border-radius: 13px;
    height: 58px;
    padding: 0 16px;
    background: #fafafa;
    margin-bottom: 1rem;
    transition: border-color .2s, box-shadow .2s;
}
.lm-phone-wrap:focus-within {
    border-color: #111;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(0,0,0,.06);
}
.lm-country {
    font-size: .92rem;
    font-weight: 600;
    color: #333;
    white-space: nowrap;
    padding-right: 13px;
    border-right: 1.5px solid #e4e4e4;
    margin-right: 13px;
    user-select: none;
}
.lm-phone-input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 1.05rem;
    font-weight: 500;
    color: #111;
    letter-spacing: .8px;
}
.lm-phone-input::placeholder { color: #c5c5c5; font-weight: 400; letter-spacing: 0; }

/* CTA button */
.lm-btn {
    width: 100%;
    height: 52px;
    background: #111;
    color: #fff;
    border: none;
    border-radius: 13px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    letter-spacing: .1px;
    transition: background .15s, transform .1s;
    margin-bottom: .5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.lm-btn:hover  { background: #2a2a2a; }
.lm-btn:active { transform: scale(.98); }
.lm-btn:disabled { background: #d0d0d0; cursor: not-allowed; }

/* Divider */
.lm-or {
    display: flex;
    align-items: center;
    color: #ccc;
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin: 1rem 0;
}
.lm-or::before, .lm-or::after {
    content: '';
    flex: 1;
    border-bottom: 1px solid #efefef;
}
.lm-or span { padding: 0 12px; }

.lm-google-wrap {
    display: flex;
    justify-content: center;
    min-height: 44px;
    margin-bottom: .75rem;
}

.lm-terms {
    font-size: .73rem;
    color: #bbb;
    text-align: center;
    margin: .5rem 0 0;
    line-height: 1.7;
}
.lm-terms a { color: #999; text-decoration: underline; }

/* OTP step */
.lm-back {
    background: none;
    border: none;
    padding: 0;
    color: #666;
    font-size: 1rem;
    cursor: pointer;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color .15s;
}
.lm-back:hover { color: #111; }

.lm-otp-icon {
    width: 52px; height: 52px;
    background: #f4f4f4;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    color: #444;
    margin-bottom: 1rem;
}

/* OTP digit boxes */
.otp-boxes {
    display: flex;
    gap: 9px;
    justify-content: center;
    margin: .5rem 0 1rem;
}
.otp-box {
    width: 46px; height: 54px;
    text-align: center;
    font-size: 1.45rem;
    font-weight: 700;
    border: 1.5px solid #e4e4e4;
    border-radius: 12px;
    outline: none;
    background: #fafafa;
    color: #111;
    transition: border-color .15s, background .15s, box-shadow .15s;
    -webkit-appearance: none;
    caret-color: transparent;
}
.otp-box:focus {
    border-color: #111;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(0,0,0,.07);
}
.otp-box.filled { border-color: #444; background: #fff; }

.lm-timer-row {
    text-align: center;
    min-height: 26px;
    margin-bottom: .75rem;
}
#otp-timer-text { font-size: .84rem; color: #bbb; }
.lm-resend {
    background: none;
    border: none;
    color: #111;
    font-size: .84rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
}

/* Name step */
.lm-welcome-emoji {
    font-size: 2.6rem;
    margin-bottom: .7rem;
    line-height: 1;
}
.lm-text-input {
    width: 100%;
    height: 58px;
    border: 1.5px solid #e4e4e4;
    border-radius: 13px;
    padding: 0 16px;
    font-size: 1rem;
    font-weight: 500;
    color: #111;
    background: #fafafa;
    outline: none;
    margin-bottom: 1rem;
    transition: border-color .2s, box-shadow .2s;
    display: block;
}
.lm-text-input:focus {
    border-color: #111;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(0,0,0,.06);
}

/* Mobile: bottom sheet */
@media (max-width: 767px) {
    .lm-modal {
        top: auto;
        bottom: 0;
        left: 0;
        right: 0;
        transform: none;
        width: 100%;
        max-width: 100%;
        border-radius: 24px 24px 0 0;
        max-height: 92vh;
        overflow-y: auto;
    }
}
/* Desktop / large tablet: floating card slightly below and left of centre */
@media (min-width: 768px) {
    .lm-modal {
        top: 55%;
        left: 47%;
    }
}

/* ── Toast Notifications ─────────────────────────────────────────────────── */
#bf-toast-container {
    position: fixed;
    bottom: 24px; right: 24px;
    z-index: 9999;
    display: flex; flex-direction: column; gap: 10px;
    pointer-events: none;
}
@media (max-width: 767px) {
    #bf-toast-container { bottom: 16px; right: 12px; left: 12px; }
}
.bf-toast {
    display: flex; align-items: flex-start; gap: 10px;
    background: #1a1a1a; color: #fff;
    padding: 13px 16px;
    border-radius: 12px;
    font-size: .875rem; font-weight: 500; line-height: 1.4;
    min-width: 260px; max-width: 380px;
    box-shadow: 0 8px 24px rgba(0,0,0,.28);
    pointer-events: all;
    position: relative; overflow: hidden;
    animation: bfTIn .25s cubic-bezier(.4,0,.2,1) both;
}
.bf-toast.leaving { animation: bfTOut .22s cubic-bezier(.4,0,.2,1) both; }
@keyframes bfTIn  { from{opacity:0;transform:translateX(18px)} to{opacity:1;transform:translateX(0)} }
@keyframes bfTOut { from{opacity:1;transform:translateX(0)} to{opacity:0;transform:translateX(18px)} }
@media (max-width:767px) {
    @keyframes bfTIn  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    @keyframes bfTOut { from{opacity:1;transform:translateY(0)} to{opacity:0;transform:translateY(10px)} }
}
.bf-toast-icon { flex-shrink:0; margin-top:1px; }
.bf-toast-body { flex:1; }
.bf-toast-x {
    flex-shrink:0; background:none; border:none;
    color:rgba(255,255,255,.45); font-size:.85rem;
    cursor:pointer; padding:0; line-height:1; margin-top:1px;
}
.bf-toast-x:hover { color:#fff; }
.bf-toast-bar {
    position:absolute; bottom:0; left:0; height:3px;
    animation: bfTBar var(--dur,3.5s) linear both;
}
@keyframes bfTBar { from{width:100%} to{width:0%} }
.bf-toast.success .bf-toast-bar,.bf-toast.success .bf-toast-icon { color:#22c55e; }
.bf-toast.success .bf-toast-bar { background:#22c55e; }
.bf-toast.error   .bf-toast-bar,.bf-toast.error   .bf-toast-icon { color:#ef4444; }
.bf-toast.error   .bf-toast-bar { background:#ef4444; }
.bf-toast.info    .bf-toast-bar,.bf-toast.info    .bf-toast-icon { color:#60a5fa; }
.bf-toast.info    .bf-toast-bar { background:#60a5fa; }
.bf-toast.warning .bf-toast-bar,.bf-toast.warning .bf-toast-icon { color:#f59e0b; }
.bf-toast.warning .bf-toast-bar { background:#f59e0b; }

/* ── Button loading spinner (shared) ─────────────────────────────────────── */
@keyframes bfSpin { to { transform: rotate(360deg); } }
.bf-spin {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,.35);
    border-top-color: #fff;
    border-radius: 50%;
    animation: bfSpin .7s linear infinite;
    vertical-align: middle; margin-right: 6px;
}
</style>

<script>
// ── Toast System ─────────────────────────────────────────────────────────────
(function () {
    var ICONS = {
        success: '<i class="fas fa-check-circle"></i>',
        error:   '<i class="fas fa-times-circle"></i>',
        info:    '<i class="fas fa-info-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>'
    };
    function container() {
        var c = document.getElementById('bf-toast-container');
        if (!c) { c = document.createElement('div'); c.id = 'bf-toast-container'; document.body.appendChild(c); }
        return c;
    }
    window.showToast = function (msg, type, dur) {
        type = type || 'info'; dur = dur || 3500;
        var t = document.createElement('div');
        t.className = 'bf-toast ' + type;
        t.style.setProperty('--dur', dur + 'ms');
        t.innerHTML = '<span class="bf-toast-icon">' + (ICONS[type] || ICONS.info) + '</span>'
                    + '<span class="bf-toast-body">' + msg + '</span>'
                    + '<button class="bf-toast-x" onclick="this.parentNode.remove()">&times;</button>'
                    + '<div class="bf-toast-bar"></div>';
        container().appendChild(t);
        setTimeout(function () {
            t.classList.add('leaving');
            t.addEventListener('animationend', function () { t.remove(); }, { once: true });
        }, dur);
    };
})();

// ── Button loading helper ─────────────────────────────────────────────────────
// Returns a restore function. Call restore() to re-enable the button.
function _btnLoad(btn, label) {
    if (!btn) return function () {};
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="bf-spin"></span>' + (label || 'Please wait…');
    return function () { btn.disabled = false; btn.innerHTML = orig; };
}

// ── Search Overlay ────────────────────────────────────────────────────────────
const _searchApiBase = '<?= $basePath ?>shop/api/search.php';
const _shopBase      = '<?= $basePath ?>shop/';
const _imgBase       = '<?= BASE_PATH ?>';

let _searchDebounceTimer = null;
let _searchIsOpen        = false;
let _lastQuery           = '';

function toggleSearch() { _searchIsOpen ? _closeSearch() : _openSearch(); }

function _openSearch() {
    _searchIsOpen = true;
    document.getElementById('searchOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    _renderRecentSearches();
    setTimeout(() => { const i = document.getElementById('searchInput'); if (i) i.focus(); }, 320);
}

function _closeSearch() {
    _searchIsOpen = false;
    var overlay = document.getElementById('searchOverlay');
    var panel   = overlay ? overlay.querySelector('.bf-search-panel') : null;
    if (panel) {
        panel.style.animation = 'bfSlideUp .22s cubic-bezier(.4,0,.2,1) both';
        panel.addEventListener('animationend', function onEnd() {
            panel.removeEventListener('animationend', onEnd);
            panel.style.animation = '';
            overlay.classList.remove('open');
        });
    } else if (overlay) {
        overlay.classList.remove('open');
    }
    document.body.style.overflow = '';
    // also add bfSlideUp keyframe if not present
    if (!document.getElementById('_bfSlideUpKf')) {
        var s = document.createElement('style');
        s.id = '_bfSlideUpKf';
        s.textContent = '@keyframes bfSlideUp { from{transform:translateY(0)} to{transform:translateY(-100%)} }';
        document.head.appendChild(s);
    }
}

function clearSearchInput() {
    const inp = document.getElementById('searchInput');
    if (inp) { inp.value = ''; inp.focus(); }
    document.getElementById('searchClearBtn').style.display = 'none';
    document.getElementById('searchSuggestions').style.display = 'none';
    document.getElementById('searchDefault').style.display = 'block';
    _lastQuery = '';
}

function applyHint(el) {
    const inp = document.getElementById('searchInput');
    if (!inp) return;
    inp.value = el.textContent.trim();
    inp.focus();
    _triggerSearch(inp.value);
}

function _triggerSearch(value) {
    const clearBtn  = document.getElementById('searchClearBtn');
    const defaultEl = document.getElementById('searchDefault');
    const suggestEl = document.getElementById('searchSuggestions');
    clearBtn.style.display = value.length ? 'inline-block' : 'none';
    if (!value || value.length < 2) {
        suggestEl.style.display = 'none';
        defaultEl.style.display = 'block';
        _lastQuery = '';
        return;
    }
    if (value === _lastQuery) return;
    _lastQuery = value;
    defaultEl.style.display = 'none';
    suggestEl.innerHTML = '<div class="bf-suggestions-loading"><div class="spinner-border spinner-border-sm text-secondary"></div>&nbsp;Searching…</div>';
    suggestEl.style.display = 'block';
    clearTimeout(_searchDebounceTimer);
    _searchDebounceTimer = setTimeout(async () => {
        try {
            const res = await fetch(_searchApiBase + '?action=suggestions&q=' + encodeURIComponent(value) + '&limit=8');
            if (!res.ok) throw new Error('failed');
            const data = await res.json();
            _renderSuggestions(data.suggestions || [], value);
        } catch(e) {
            suggestEl.innerHTML = '<div class="bf-suggestions-empty">Could not load suggestions.</div>';
        }
    }, 220);
}

function _renderSuggestions(suggestions, query) {
    const el = document.getElementById('searchSuggestions');
    if (!el) return;
    const products = suggestions.filter(s => s.type === 'product');
    if (!products.length) {
        el.innerHTML = '<div class="bf-suggestions-empty">No results for &ldquo;<strong>' + _esc(query) + '</strong>&rdquo;. Press Enter to search.</div>';
        return;
    }
    let html = '<div class="bf-suggestions-header">Products</div>';
    products.forEach(function(s) {
        var imgSrc = s.image ? (_imgBase + s.image) : '';
        var imgEl  = imgSrc
            ? '<img class="bf-suggestion-img" src="' + imgSrc + '" alt="' + _esc(s.text) + '" onerror="this.style.display=\'none\'">'
            : '<div class="bf-suggestion-img-placeholder"><i class="fas fa-image"></i></div>';
        var pid = (s.url || '').split('id=')[1] || '';
        html += '<a class="bf-suggestion-item" href="' + _shopBase + 'product.php?id=' + pid + '" onclick="_saveSearch(\'' + _esc(s.text).replace(/'/g,'\\\'') + '\')">'
              + imgEl
              + '<div class="bf-suggestion-info">'
              + '<p class="bf-suggestion-name">' + _esc(s.text) + '</p>'
              + '<p class="bf-suggestion-price">' + (s.price || '') + '</p>'
              + '</div>'
              + '<i class="fas fa-chevron-right bf-suggestion-arrow"></i>'
              + '</a>'
              + '<hr class="bf-suggestion-divider">';
    });
    var searchUrl = _shopBase + 'search.php?q=' + encodeURIComponent(query);
    html += '<a class="bf-view-all-link" href="' + searchUrl + '" onclick="_saveSearch(\'' + _esc(query).replace(/'/g,'\\\'') + '\')">View all results for &ldquo;<strong>' + _esc(query) + '</strong>&rdquo; &nbsp;<i class="fas fa-arrow-right"></i></a>';
    el.innerHTML = html;
    el.style.display = 'block';
}

var _RECENT_KEY = 'bf_recent_searches';

function _saveSearch(term) {
    try {
        var list = JSON.parse(localStorage.getItem(_RECENT_KEY) || '[]');
        list = [term].concat(list.filter(function(t){ return t !== term; })).slice(0, 6);
        localStorage.setItem(_RECENT_KEY, JSON.stringify(list));
    } catch(e) {}
}

function _renderRecentSearches() {
    var section = document.getElementById('recentSearchesSection');
    var list    = document.getElementById('recentSearchesList');
    if (!section || !list) return;
    try {
        var recent = JSON.parse(localStorage.getItem(_RECENT_KEY) || '[]');
        if (!recent.length) { section.style.display = 'none'; return; }
        section.style.display = 'block';
        list.innerHTML = recent.map(function(t){ return '<span class="bf-recent-item" onclick="applyHint(this)"><i class="fas fa-clock"></i>' + _esc(t) + '</span>'; }).join('');
    } catch(e) { section.style.display = 'none'; }
}

function _esc(str) {
    return String(str).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
}

function goToProduct(url) { window.location.href = url; }
function handleSearchKeypress(e) { if (e.key === 'Enter') performSearch(); }
function onSearchInput(v) { _triggerSearch(v); }
function performSearch() {
    var q = (document.getElementById('searchInput') ? document.getElementById('searchInput').value : '').trim();
    if (q.length >= 2) { _saveSearch(q); window.location.href = _shopBase + 'search.php?q=' + encodeURIComponent(q); }
}

document.addEventListener('DOMContentLoaded', function() {
    var inp = document.getElementById('searchInput');
    if (!inp) return;
    inp.addEventListener('input', function() { _triggerSearch(this.value.trim()); });
    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { var q = this.value.trim(); if (q.length >= 2) { _saveSearch(q); window.location.href = _shopBase + 'search.php?q=' + encodeURIComponent(q); } }
        if (e.key === 'Escape') _closeSearch();
    });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && _searchIsOpen) _closeSearch(); });
});

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
    fetch('<?= $basePath ?>auth/logout.php', {
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

// ============================================================================
// OTP LOGIN
// ============================================================================
var _loginPhone = '';
var _loginTimerInterval = null;
var _loginBasePath = '<?= $basePath ?>';

function loginSendOTP(isResend) {
    var phone = isResend ? _loginPhone : (document.getElementById('login-phone').value || '').trim();
    if (!/^[6-9]\d{9}$/.test(phone)) {
        showToast('Enter a valid 10-digit Indian mobile number', 'warning');
        return;
    }
    _loginPhone = phone;

    var btn     = document.getElementById('login-send-btn');
    var restore = _btnLoad(btn, 'Sending OTP…');

    fetch(_loginBasePath + 'auth/send-otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({phone: phone})
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        restore();
        if (data.success) {
            _lmShowStep('login-step-otp', 'login-step-phone');
            document.getElementById('otp-sent-msg').textContent = 'OTP sent to +91 ' + phone;
            var boxes = document.querySelectorAll('.otp-box');
            boxes.forEach(function(b){ b.value = ''; b.classList.remove('filled'); });
            if (boxes.length) boxes[0].focus();
            loginStartTimer(600);
        } else {
            showToast(data.message || 'Failed to send OTP. Please try again.', 'error');
        }
    })
    .catch(function() {
        restore();
        showToast('Network error. Please check your connection.', 'error');
    });
}

function otpBoxInput(el, idx) {
    el.value = el.value.replace(/\D/g, '').slice(-1);
    el.classList.toggle('filled', !!el.value);
    var boxes = document.querySelectorAll('.otp-box');
    if (el.value && idx < 5) boxes[idx + 1].focus();
    if (idx === 5 && el.value) loginVerifyOTP();
}

function otpBoxKey(e, idx) {
    var boxes = document.querySelectorAll('.otp-box');
    if (e.key === 'Backspace' && !boxes[idx].value && idx > 0) boxes[idx - 1].focus();
    if (e.key === 'ArrowLeft' && idx > 0) boxes[idx - 1].focus();
    if (e.key === 'ArrowRight' && idx < 5) boxes[idx + 1].focus();
}

function _lmShowStep(showId, hideId) {
    var hide = document.getElementById(hideId);
    var show = document.getElementById(showId);
    if (hide) hide.style.display = 'none';
    if (show) {
        show.style.display = '';
        // Re-trigger animation
        show.style.animation = 'none';
        show.offsetHeight; // reflow
        show.style.animation = '';
    }
}

function loginGoBack() {
    clearInterval(_loginTimerInterval);
    _lmShowStep('login-step-phone', 'login-step-otp');
    var phoneInput = document.getElementById('login-phone');
    if (phoneInput) { phoneInput.value = _loginPhone; phoneInput.focus(); }
}

function loginStartTimer(seconds) {
    clearInterval(_loginTimerInterval);
    var timerEl   = document.getElementById('otp-timer-text');
    var resendBtn = document.getElementById('otp-resend-btn');
    if (resendBtn) resendBtn.style.display = 'none';

    _loginTimerInterval = setInterval(function() {
        seconds--;
        if (timerEl) {
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            timerEl.textContent = 'Resend in ' + m + ':' + (s < 10 ? '0' : '') + s;
        }
        if (seconds <= 0) {
            clearInterval(_loginTimerInterval);
            if (timerEl) timerEl.textContent = '';
            if (resendBtn) resendBtn.style.display = '';
        }
    }, 1000);
}

function loginVerifyOTP() {
    var boxes = document.querySelectorAll('.otp-box');
    var otp   = Array.prototype.map.call(boxes, function(b){ return b.value; }).join('');
    if (otp.length !== 6) { showToast('Please enter the complete 6-digit OTP', 'warning'); return; }

    var btn     = document.getElementById('login-verify-btn');
    var restore = _btnLoad(btn, 'Verifying…');

    fetch(_loginBasePath + 'auth/verify-otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({phone: _loginPhone, otp: otp})
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        restore();
        if (data.success) {
            clearInterval(_loginTimerInterval);
            if (data.new_user) {
                _lmShowStep('login-step-name', 'login-step-otp');
                var nameInput = document.getElementById('login-name');
                if (nameInput) nameInput.focus();
            } else {
                showToast('Welcome back! Logging you in…', 'success');
                setTimeout(function() { closeAllPopups(); window.location.reload(); }, 800);
            }
        } else {
            showToast(data.message || 'Verification failed. Please try again.', 'error');
        }
    })
    .catch(function() {
        restore();
        showToast('Network error. Please try again.', 'error');
    });
}

function loginCompleteName() {
    var name = (document.getElementById('login-name').value || '').trim();
    if (!name) { showToast('Please enter your name', 'warning'); return; }

    fetch(_loginBasePath + 'auth/update-name.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({name: name})
    })
    .finally(function() {
        closeAllPopups();
        window.location.reload();
    });
}

// ============================================================================
// GOOGLE ONE TAP
// ============================================================================
var _googleClientId = '<?= defined('GOOGLE_CLIENT_ID') ? htmlspecialchars(GOOGLE_CLIENT_ID, ENT_QUOTES) : '' ?>';

function _initGoogleSignIn() {
    if (!_googleClientId || !window.google || !window.google.accounts) return;
    google.accounts.id.initialize({
        client_id: _googleClientId,
        callback: _handleGoogleCredential,
        auto_select: false,
        cancel_on_tap_outside: true
    });
    var container = document.getElementById('google-btn-container');
    if (container) {
        google.accounts.id.renderButton(container, {
            theme: 'outline',
            size: 'large',
            width: 280,
            text: 'continue_with',
            shape: 'rectangular'
        });
    }
}

function _handleGoogleCredential(response) {
    fetch(_loginBasePath + 'auth/google-callback.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id_token: response.credential})
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        if (data.success) {
            closeAllPopups();
            window.location.reload();
        } else {
            showToast(data.message || 'Google login failed. Please try again.', 'error');
        }
    })
    .catch(function() {
        showToast('Google login failed. Please try phone OTP instead.', 'error');
    });
}

// Run once GIS library is loaded (async script)
document.addEventListener('DOMContentLoaded', function() {
    if (window.google && window.google.accounts) {
        _initGoogleSignIn();
    } else {
        // GIS loads asynchronously — poll briefly
        var _gsi_attempts = 0;
        var _gsi_poll = setInterval(function() {
            if (window.google && window.google.accounts) {
                clearInterval(_gsi_poll);
                _initGoogleSignIn();
            }
            if (++_gsi_attempts > 20) clearInterval(_gsi_poll);
        }, 250);
    }
});
</script>