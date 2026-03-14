<?php
session_start();
require_once "./includes/database.php"; 
require_once "./includes/functions.php"; 
require_once "./auth/session.php"; // Add this line


// Check if user is logged in
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;


$user_id = $_SESSION['user_id'];


// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch claim history
$claims = $conn->prepare("SELECT * FROM claims WHERE user_id = ? ORDER BY created_at DESC");
$claims->execute([$user_id]);

// Fetch cart history
$cart = $conn->prepare("SELECT c.*, p.name AS product_name 
                        FROM cart c 
                        JOIN products p ON c.product_id = p.id 
                        WHERE c.user_id = ? ORDER BY c.created_at DESC");
$cart->execute([$user_id]);

// check if KYC is already uploaded
$kycCompleted = !empty($user['aadhar_front']) && !empty($user['aadhar_back']) 
                && !empty($user['pan_front']) && !empty($user['pan_back']);


// ---- File Upload Handling ----
if (isset($_POST['save_profile'])) {
    $uploadDir = "uploads/ids/";
    $fullDir = __DIR__ . "/" . $uploadDir;
    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0777, true);
    }

    $fields = ['aadhar_front', 'aadhar_back', 'pan_front', 'pan_back'];
    $uploadedFiles = [];

    foreach ($fields as $field) {
        if (!empty($_FILES[$field]['name'])) {
            $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
            $fileName = $field . "_" . $user_id . "_" . time() . "." . strtolower($ext);
            $targetPath = $fullDir . $fileName;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $targetPath)) {
                $uploadedFiles[$field] = "/" . $uploadDir . $fileName; // web path
            }
        }
    }

    if (!empty($uploadedFiles)) {
        $set = [];
        foreach ($uploadedFiles as $key => $val) {
            $set[] = "$key = :$key";
        }
        $sql = "UPDATE users SET " . implode(", ", $set) . " WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $uploadedFiles['id'] = $user_id;
        $stmt->execute($uploadedFiles);
        header("Location: profile.php"); // reload to show updated images
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Bluefifth</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
   
    html, body {
  overflow-x: hidden;
}

    .profile-jumbotron {
      background: linear-gradient(45deg, #007bff, #6610f2);
      color: white;
      padding: 3rem 2rem;
      border-radius: 1rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .profile-jumbotron h1 {
      font-weight: 700;
    }
    .card {
      border-radius: 1rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      margin-bottom: 1.5rem;
    }
    .card h4 {
      color: #007bff;
      font-weight: 600;
    }
    .list-group-item {
      font-size: 0.95rem;
    }
    .btn-success {
      font-weight: 600;
      padding: 0.6rem 1.2rem;
    }
    .doc-img {
      width: 220px;
      border: 2px solid #e1e1e1;
      border-radius: 6px;
      margin: 5px 0;
      padding: 4px;
      background: #fff;
      box-shadow: 0 1px 6px rgba(0,0,0,0.1);
      transition: transform 0.2s;
    }
    .doc-img:hover {
      transform: scale(1.05);
    }
    .table thead th {
      background-color: #343a40;
      color: #fff;
      font-weight: 600;
    }
    .table tbody td {
      vertical-align: middle;
    }
    .card-header h4 {
      margin: 0;
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
        .alert {
            font-weight: 500;
            border-radius: 8px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
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
            margin-top:160px;
        }
                /* Tablet View (min-width: 768px) */
        @media (min-width: 768px) {
        .img-responsive{
            width:120px;
        }
        .nav-align{
            margin-top:200px;
        }
        }

        /* Laptop/Desktop View (min-width: 1024px or 1200px) */
        @media (min-width: 1024px) {
        .img-responsive{
            width:200px;
            margin-left:110px;
        }
        .nav-align{
            margin-top:230px;
        }
        }
        .doc-img {
          max-height: 180px;
          border: 2px solid #ddd;
          border-radius: 6px;
          padding: 4px;
          background: #fff;
          box-shadow: 0 2px 6px rgba(0,0,0,0.1);
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
    
<?php include './includes/timer.php'; ?>

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
                    <li class="nav-item active">
                        <a class="nav-link nav-link-st" href="index.php">HOME</a>
                    </li>
                    
                    <?php
                        // To fix the issue, add this code to fetch categories from the database.
                        // Assuming your categories are in a 'categories' table with 'name' and 'slug' columns.
                        $categoriesQuery = $conn->prepare("SELECT name, slug FROM categories ORDER BY name ASC");
                        $categoriesQuery->execute();
                        $categories = $categoriesQuery->fetchAll(PDO::FETCH_ASSOC);
                        $currentCategory = $_GET['category'] ?? '';
                    ?>
                    <?php foreach ($categories as $category): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-st" href="shop/category.php?category=<?= urlencode($category['slug']) ?>">
                                <?= strtoupper(htmlspecialchars($category['name'])) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link nav-link-st" href="../includes/blog.php">BLOG</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link nav-link-st" href="../includes/about.php">ABOUT US</a>
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
                            <a  href="../profile.php" class="nav-link">
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
                            <a href="../account/orders.php" class="nav-link">
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
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</div>

<!-- Popup Modal Overlay -->
<div class="modal-overlay" id="modal-overlay" onclick="closeAllPopups()"></div>

<!-- My Profile Popup -->
<div class="popup-modal m-auto " style="margin:auto;" id="profile-popup">
    <button class="popup-close" onclick="closeAllPopups()">&times;</button>
    <div class="popup-header">
        <h3><i class="fas fa-chart-line mr-2"></i>My Profile</h3>
    </div>
</div>

<!-- My Referrals Popup -->
<div class="popup-modal m-auto" style="margin:auto;" id="referral-popup">
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
<div class="popup-modal m-auto" style="margin:auto;" id="wallet-popup">
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

<div class="container mt-5">
  <!-- Header -->
  <div class="jumbotron profile-jumbotron text-center nav-align">
    <h1 class="display-4">👋 Welcome, <?= htmlspecialchars($user['name']); ?>!</h1>
    <p class="lead">This is your personal profile dashboard.</p>
  </div>

  <div class="row">
    <!-- User Details -->
    <div class="col-md-12 mb-4">
      <div class="card">
        <div class="card-body">
          <h4><i class="fas fa-user-circle"></i> My Details</h4>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><strong>Name:</strong> <?= htmlspecialchars($user['name']); ?></li>
            <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?> </li>
            <li class="list-group-item"><strong>Address</strong> <?= htmlspecialchars($user['address']); ?>  <?= htmlspecialchars($user['city']); ?></li>
          </ul>
        </div>
      </div>
      <div class="card mt-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">KYC Status</h5>
        </div>
        <div class="card-body">
            <?php if ($user['kyc_status'] === 'verified'): ?>
                <span class="badge badge-success">✅ Verified</span>
            <?php else: ?>
                <span class="badge badge-warning">⏳ Not Verified</span>
            <?php endif; ?>
        </div>
    </div>

    </div>
    
    

    <!-- Uploaded Docs Preview -->
    <div class="col-md-12 mb-4">
      <div class="card">
        <div class="card-body">
          <h4><i class="fas fa-id-card"></i> KYC Documents</h4>
          <?php if ($user['aadhar_front']) echo "<p>Aadhar Front:<br><img src='{$user['aadhar_front']}' class='doc-img'></p>"; ?>
          <?php if ($user['aadhar_back']) echo "<p>Aadhar Back:<br><img src='{$user['aadhar_back']}' class='doc-img'></p>"; ?>
          <?php if ($user['pan_front']) echo "<p>PAN Front:<br><img src='{$user['pan_front']}' class='doc-img'></p>"; ?>
          <?php if ($user['pan_back']) echo "<p>Bank passbook:<br><img src='{$user['pan_back']}' class='doc-img'></p>"; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Upload Form -->
<?php if ($kycCompleted): ?>
    <div class="card mt-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-id-card"></i> KYC Documents Uploaded</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>Aadhar Front:</strong><br>
                    <img src="<?= htmlspecialchars($user['aadhar_front']); ?>" class="img-fluid doc-img">
                </div>
                <div class="col-md-6">
                    <strong>Aadhar Back:</strong><br>
                    <img src="<?= htmlspecialchars($user['aadhar_back']); ?>" class="img-fluid doc-img">
                </div>
                <div class="col-md-6 mt-3">
                    <strong>PAN Front:</strong><br>
                    <img src="<?= htmlspecialchars($user['pan_front']); ?>" class="img-fluid doc-img">
                </div>
                <div class="col-md-6 mt-3">
                    <strong>Bank passbook:</strong><br>
                    <img src="<?= htmlspecialchars($user['pan_back']); ?>" class="img-fluid doc-img">
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <form action="profile.php" method="POST" enctype="multipart/form-data" class="mt-4">
        <h5>Upload Aadhar Card</h5>
        <div class="form-group">
            <label>Aadhar Front</label>
            <input type="file" name="aadhar_front" accept="image/*" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Aadhar Back</label>
            <input type="file" name="aadhar_back" accept="image/*" class="form-control" required>
        </div>

        <h5>Upload PAN Card</h5>
        <div class="form-group">
            <label>PAN Front</label>
            <input type="file" name="pan_front" accept="image/*" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Bank passbook</label>
            <input type="file" name="pan_back" accept="image/*" class="form-control" required>
        </div>

        <button type="submit" name="save_profile" class="btn btn-primary">Save</button>
    </form>
<?php endif; ?>

<?php
// check if KYC form details exist
$formCompleted = !empty($user['kyc_name']) && !empty($user['kyc_aadhar_number']) 
                 && !empty($user['kyc_pan_number']) && !empty($user['kyc_bank_account']) 
                 && !empty($user['kyc_ifsc']);

if (isset($_POST['save_kyc_form'])) {
    $stmt = $conn->prepare("UPDATE users 
        SET kyc_name = ?, kyc_aadhar_number = ?, kyc_pan_number = ?, 
            kyc_bank_account = ?, kyc_ifsc = ? WHERE id = ?");
    $stmt->execute([
        $_POST['kyc_name'],
        $_POST['kyc_aadhar_number'],
        $_POST['kyc_pan_number'],
        $_POST['kyc_bank_account'],
        $_POST['kyc_ifsc'],
        $user_id
    ]);
    header("Location: profile.php?success=1");
    exit;
}
?>

<?php if ($formCompleted): ?>
    <div class="card mt-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">✅ Submitted Forms</h5>
        </div>
        <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($user['kyc_name']); ?></p>
            <p><strong>Aadhar Number:</strong> <?= htmlspecialchars($user['kyc_aadhar_number']); ?></p>
            <p><strong>PAN Number:</strong> <?= htmlspecialchars($user['kyc_pan_number']); ?></p>
            <p><strong>Bank Account:</strong> <?= htmlspecialchars($user['kyc_bank_account']); ?></p>
            <p><strong>IFSC Code:</strong> <?= htmlspecialchars($user['kyc_ifsc']); ?></p>
        </div>
    </div>
<?php else: ?>
    <form action="profile.php" method="POST" class="card mt-4 p-3">
        <h5>KYC Information Form</h5>
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="kyc_name" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Aadhar Number</label>
            <input type="text" name="kyc_aadhar_number" class="form-control" maxlength="12" required>
        </div>
        <div class="form-group">
            <label>PAN Number</label>
            <input type="text" name="kyc_pan_number" class="form-control" maxlength="10" required>
        </div>
        <div class="form-group">
            <label>Bank Account Number</label>
            <input type="text" name="kyc_bank_account" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Bank IFSC Code</label>
            <input type="text" name="kyc_ifsc" class="form-control" maxlength="11" required>
        </div>
        <button type="submit" name="save_kyc_form" class="btn btn-primary">Submit</button>
    </form>
<?php endif; ?>
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ Your details have been saved successfully.
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>



</div>
<!-- Google Sign-In Script -->
<script src="https://accounts.google.com/gsi/client" async defer></script>

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- INTEGRATED AUTHENTICATION SCRIPT -->
<script>
let currentUser = <?= $isLoggedIn ? 'true' : 'false' ?>;
let isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

// PRODUCTION-LEVEL SILENT AUTHENTICATION SYSTEM - OPTION A
(function() {
    'use strict';
    
    // ========================================
    // CONFIGURATION & STATE MANAGEMENT
    // ========================================
    const CONFIG = {
        GOOGLE_CLIENT_ID: "340757900430-i8nl6l45ndveq9jmbvbah7ugquauj803.apps.googleusercontent.com",
        AUTH_ENDPOINT: "auth/google-callback.php",
        SESSION_TIMEOUT: 3000,
        MAX_INIT_ATTEMPTS: 50,
        INIT_CHECK_INTERVAL: 100,
        SESSION_KEY: 'velona_auth_attempted',
        FALLBACK_KEY: 'velona_fallback_tried'
    };
    
    let authState = {
        isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
        googleInitialized: false,
        authAttempted: false,
        fallbackUsed: false,
        sessionAttempted: sessionStorage.getItem(CONFIG.SESSION_KEY) === 'true'
    };
    
    // ========================================
    // UTILITY FUNCTIONS
    // ========================================
    function log(message, data = '') {
        console.log(`[🔐 Silent Auth] ${message}`, data);
    }
    
    function showLoading() {
        const loader = document.getElementById('auth-loading');
        if (loader && !authState.isLoggedIn) {
            loader.classList.add('show');
            log("⏳ Loading indicator shown");
        }
    }
    
    function hideLoading() {
        const loader = document.getElementById('auth-loading');
        if (loader) {
            loader.classList.remove('show');
            log("✅ Loading indicator hidden");
        }
    }
    
    function markSessionAttempted() {
        sessionStorage.setItem(CONFIG.SESSION_KEY, 'true');
        authState.sessionAttempted = true;
    }
    
    function resetSessionState() {
        sessionStorage.removeItem(CONFIG.SESSION_KEY);
        sessionStorage.removeItem(CONFIG.FALLBACK_KEY);
        authState.sessionAttempted = false;
        authState.fallbackUsed = false;
    }
    
    // ========================================
    // GOOGLE API INITIALIZATION
    // ========================================
    function initializeGoogle() {
        if (authState.googleInitialized || typeof google === 'undefined' || !google.accounts?.id) {
            return false;
        }
        
        try {
            google.accounts.id.initialize({
                client_id: CONFIG.GOOGLE_CLIENT_ID,
                callback: handleAuthResponse,
                auto_select: true,
                cancel_on_tap_outside: false,
                use_fedcm_for_prompt: true,
                ux_mode: 'popup',
                context: 'signin'
            });
            
            authState.googleInitialized = true;
            log("✅ Google Sign-In initialized successfully");
            return true;
        } catch (error) {
            log("❌ Google initialization failed:", error);
            return false;
        }
    }
    
    // ========================================
    // AUTHENTICATION RESPONSE HANDLER
    // ========================================
    function handleAuthResponse(response) {
        if (!response.credential) {
            log("❌ No credential in response");
            hideLoading();
            return;
        }
        
        log("📤 Processing authentication response...");
        
        fetch(CONFIG.AUTH_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                id_token: response.credential,
                silent_auth: true,
                referral_auth: true
            }),
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            closeAllPopups();
            
            if (data.success) {
                log("✅ Silent authentication successful");
                authState.isLoggedIn = true;
                resetSessionState();
                
                // Reload page to update UI
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                log("❌ Backend authentication failed:", data.message);
                markSessionAttempted();
            }
        })
        .catch(error => {
            log("❌ Authentication request failed:", error);
            hideLoading();
            markSessionAttempted();
        });
    }
    
    // ========================================
    // ANTI-SUPPRESSION TECHNIQUES
    // ========================================
    function clearGoogleSuppression() {
        try {
            if (!google.accounts?.id) return false;
            
            // Method 1: Cancel existing prompts
            google.accounts.id.cancel();
            
            // Method 2: Disable auto-select temporarily
            google.accounts.id.disableAutoSelect();
            
            // Method 3: Re-initialize with different settings
            setTimeout(() => {
                google.accounts.id.initialize({
                    client_id: CONFIG.GOOGLE_CLIENT_ID,
                    callback: handleAuthResponse,
                    auto_select: false,
                    cancel_on_tap_outside: false,
                    use_fedcm_for_prompt: false,
                    context: 'signup' // Different context
                });
                
                log("🔄 Google state reset for suppression bypass");
            }, 100);
            
            return true;
        } catch (error) {
            log("⚠️ Could not clear suppression:", error);
            return false;
        }
    }
    
    function tryFallbackMethods() {
        if (authState.fallbackUsed || sessionStorage.getItem(CONFIG.FALLBACK_KEY) === 'true') {
            log("⏭️ Fallback methods already attempted");
            hideLoading();
            return;
        }
        
        log("🔄 Attempting fallback authentication methods...");
        sessionStorage.setItem(CONFIG.FALLBACK_KEY, 'true');
        authState.fallbackUsed = true;
        
        // Fallback 1: Clear suppression and retry
        if (clearGoogleSuppression()) {
            setTimeout(() => {
                if (authState.googleInitialized) {
                    google.accounts.id.prompt((notification) => {
                        if (!notification.isDisplayed()) {
                            log("❌ Fallback method 1 failed");
                            trySecondaryFallback();
                        } else {
                            log("✅ Fallback method 1 successful");
                        }
                    });
                }
            }, 300);
        } else {
            trySecondaryFallback();
        }
    }
    
    function trySecondaryFallback() {
        log("Trying secondary fallback...");
        
        // Try with completely fresh initialization
        try {
            google.accounts.id.initialize({
                client_id: CONFIG.GOOGLE_CLIENT_ID,
                callback: handleAuthResponse,
                auto_select: false,
                cancel_on_tap_outside: true,
                use_fedcm_for_prompt: false,
                context: 'use'
            });
            
            // Give it one more shot
            google.accounts.id.prompt((notification) => {
                if (!notification.isDisplayed()) {
                    log("All fallback methods exhausted - traditional login will be available on manual trigger");
                    hideLoading();
                    markSessionAttempted();
                } else {
                    log("Secondary fallback successful");
                }
            });
        } catch (error) {
            log("Secondary fallback error:", error);
            hideLoading();
            markSessionAttempted();
        }
    }
    
    // ========================================
    // MAIN SILENT AUTHENTICATION LOGIC
    // ========================================
    function attemptSilentAuth() {
        // Skip if already logged in or already attempted this session
        if (authState.isLoggedIn) {
            log("👤 User already authenticated");
            hideLoading();
            return;
        }
        
        if (authState.sessionAttempted) {
            log("⏭️ Authentication already attempted this session");
            hideLoading();
            return;
        }
        
        if (!authState.googleInitialized && !initializeGoogle()) {
            log("❌ Google API not available");
            hideLoading();
            markSessionAttempted();
            return;
        }
        
        log("🚀 Starting silent authentication attempt...");
        authState.authAttempted = true;
        
        try {
            google.accounts.id.prompt((notification) => {
                const isDisplayed = notification.isDisplayed();
                const reason = notification.getNotDisplayedReason();
                
                log(`Primary attempt result: displayed=${isDisplayed}, reason=${reason}`);
                
                if (isDisplayed) {
                    log("✅ Silent authentication prompt displayed");
                    // Success - user will interact with the prompt
                } else {
                    log(`❌ Silent auth blocked: ${reason}`);
                    
                    // Handle specific suppression cases
                    if (reason === 'suppressed_by_user' || reason === 'user_cancel') {
                        tryFallbackMethods();
                    } else {
                        hideLoading();
                        markSessionAttempted();
                    }
                }
            });
            
            // Safety timeout
            setTimeout(() => {
                if (!authState.isLoggedIn) {
                    hideLoading();
                    markSessionAttempted();
                }
            }, CONFIG.SESSION_TIMEOUT);
            
        } catch (error) {
            log("❌ Silent authentication error:", error);
            tryFallbackMethods();
        }
    }
    
    // ========================================
    // INITIALIZATION SEQUENCE
    // ========================================
    function waitForGoogleAndStartAuth() {
        if (authState.isLoggedIn) {
            log("👤 User already authenticated, skipping silent auth");
            hideLoading();
            return;
        }
        
        showLoading();
        log("⏳ Waiting for Google Sign-In library...");
        
        let attempts = 0;
        const checkInterval = setInterval(() => {
            attempts++;
            
            if (typeof google !== 'undefined' && google.accounts?.id) {
                clearInterval(checkInterval);
                log("✅ Google library loaded successfully");
                
                // Small delay to ensure library is fully ready
                setTimeout(attemptSilentAuth, 200);
            } else if (attempts >= CONFIG.MAX_INIT_ATTEMPTS) {
                clearInterval(checkInterval);
                log("❌ Google library failed to load after maximum attempts");
                hideLoading();
                markSessionAttempted();
            }
        }, CONFIG.INIT_CHECK_INTERVAL);
    }
    
    // ========================================
    // PUBLIC API FOR MANUAL LOGIN
    // ========================================
    // Traditional Login Functions
    window.showTraditionalLoginPopup = function() {
        log("Traditional login popup requested");
        
        document.getElementById('modal-overlay').classList.add('show');
        document.getElementById('traditional-login-popup').classList.add('show');
        
        // Initialize traditional Google Sign-In button
        initializeTraditionalGoogleButton();
    };
    
    function initializeTraditionalGoogleButton() {
        if (!authState.googleInitialized && !initializeGoogle()) {
            document.getElementById('traditional-login-content').innerHTML = `
                <div class="alert alert-warning">
                    Google Sign-In is not available. Please try again later.
                </div>
            `;
            return;
        }
        
        try {
            // Create traditional sign-in button
            google.accounts.id.renderButton(
                document.getElementById("google-signin-button"),
                {
                    theme: "outline",
                    size: "large",
                    width: "300",
                    text: "signin_with",
                    shape: "rectangular",
                    logo_alignment: "left"
                }
            );
            
            log("Traditional Google button rendered");
            
        } catch (error) {
            log("Error rendering traditional button:", error);
            document.getElementById('traditional-login-content').innerHTML = `
                <div class="alert alert-danger">
                    Failed to load Google Sign-In. Please refresh and try again.
                </div>
            `;
        }
    }
    
    // REPLACE your existing triggerOneTapLogin function with this:
    window.triggerOneTapLogin = function() {
        if (authState.isLoggedIn) {
            log("User already logged in");
            return;
        }
        
        log("One-tap login triggered as fallback");
        
        if (!authState.googleInitialized && !initializeGoogle()) {
            log("One-tap fallback: Google Sign-In not available, showing traditional login");
            showTraditionalLoginPopup();
            return;
        }
        
        try {
            resetSessionState();
            
            google.accounts.id.prompt((notification) => {
                log('One-tap fallback result:', notification);
                
                if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                    log('One-tap fallback failed, showing traditional login');
                    showTraditionalLoginPopup();
                }
            });
        } catch (error) {
            log("One-tap fallback failed:", error);
            showTraditionalLoginPopup();
        }
    };
    
    // ========================================
    // STARTUP SEQUENCE
    // ========================================
    function initializeAuthSystem() {
        log("🚀 Silent authentication system initialized");
        
        // Only attempt silent auth on index page
        const isIndexPage = window.location.pathname === '/' || 
                           window.location.pathname === '/index.php' || 
                           window.location.pathname.endsWith('/');
        
        if (isIndexPage) {
            log("📍 Index page detected - starting silent auth");
            waitForGoogleAndStartAuth();
        } else {
            log("📍 Non-index page - skipping silent auth");
            hideLoading();
        }
    }
    
    // Start the system when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAuthSystem);
    } else {
        initializeAuthSystem();
    }
    
    log("🎯 Production-level silent authentication system loaded");

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
        <div class="guest-login-section m">
            <h3>🔗 My Referrals</h3>
            <p style="margin-bottom: 1.5rem; color: #666;">Tap to login to view your referral data, links and codes!</p>
            
            <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                <strong>💰 Earn with referrals:</strong><br>
                • 10% commission on first month purchases<br>
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
                displayWalletData(data);
            } else {document.getElementById('wallet-content').innerHTML = 
                    '<div class="alert alert-danger">Failed to load wallet data: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error loading wallet data:', error);
            document.getElementById('wallet-content').innerHTML = 
                '<div class="alert alert-danger">Error loading wallet data. Please try again.</div>';
        });
}

// Display wallet data - FIXED: Proper status display
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

// Newsletter subscription
async function subscribeNewsletter(event) {
    event.preventDefault();
    
    const email = document.getElementById('newsletterEmail').value;
    
    try {
        const formData = new FormData();
        formData.append('action', 'subscribe');
        formData.append('email', email);

        const response = await fetch('api/newsletter.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Successfully subscribed to newsletter!', 'success');
            document.getElementById('newsletterForm').reset();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Newsletter subscription error:', error);
        showNotification('Subscription failed. Please try again.', 'error');
    }
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
</body>
</html>
