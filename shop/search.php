<?php
// shop/search.php - Complete Dynamic Product Search Results Page
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../auth/session.php';

// Get search parameters
$searchQuery = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$priceMin = floatval($_GET['price_min'] ?? 0);
$priceMax = floatval($_GET['price_max'] ?? 0);
$sortType = $_GET['sort'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = intval($_GET['limit'] ?? 12);
$itemsPerPage = in_array($limit, [12, 24, 48]) ? $limit : 12;

// Redirect if no search query
if (empty($searchQuery)) {
    header('Location: category.php');
    exit;
}

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;
$isLoggedIn  = $isLoggedIn && ($currentUser !== null);

// Get all categories for navigation and filtering
$categories = getAllCategories();

// Get user's cart summary if logged in
$cartSummary = $isLoggedIn ? getCartSummary($currentUser['id']) : ['item_count' => 0];

// Get wallet balance if logged in
$walletBalance = $isLoggedIn ? getWalletBalance($currentUser['id']) : ['points' => 0, 'pending_points' => 0];

// Perform search with pagination
$offset = ($page - 1) * $itemsPerPage;
$searchResults = searchProducts($searchQuery, $itemsPerPage, $offset);

// Get total count for pagination - use a separate search without limit
$allSearchResults = searchProducts($searchQuery, 1000, 0);
$totalResults = count($allSearchResults);

// Apply additional filters
if (!empty($categoryFilter) || $priceMin > 0 || $priceMax > 0) {
    $searchResults = array_filter($searchResults, function($product) use ($categoryFilter, $priceMin, $priceMax) {
        // Category filter
        if ($categoryFilter && isset($product['category_slug']) && $product['category_slug'] !== $categoryFilter) {
            return false;
        }
        
        // Price filter
        if ($priceMin > 0 && $product['price'] < $priceMin) {
            return false;
        }
        
        if ($priceMax > 0 && $product['price'] > $priceMax) {
            return false;
        }
        
        return true;
    });
    
    // Recalculate total after filtering
    $totalResults = count($searchResults);
}

// Apply sorting if specified
if (!empty($sortType)) {
    $searchResults = sortProductsArray($searchResults, $sortType);
}

$totalPages = ceil($totalResults / $itemsPerPage);

// Get search suggestions if no results
$searchSuggestions = [];
if (empty($searchResults)) {
    $similarProducts = getAllProducts(4, 0);
    $searchSuggestions = array_slice($similarProducts, 0, 4);
}

// Get price range for filters
$allProducts = getAllProducts();
$priceRange = [
    'min' => !empty($allProducts) ? min(array_column($allProducts, 'price')) : 0,
    'max' => !empty($allProducts) ? max(array_column($allProducts, 'price')) : 10000
];

// Helper function to build search URLs
function buildSearchUrl($query, $page = 1, $category = '', $minPrice = 0, $maxPrice = 0, $sort = '', $itemLimit = 12) {
    $params = [
        'q' => $query,
        'page' => $page > 1 ? $page : null,
        'category' => $category ?: null,
        'price_min' => $minPrice > 0 ? $minPrice : null,
        'price_max' => $maxPrice > 0 ? $maxPrice : null,
        'sort' => $sort ?: null,
        'limit' => $itemLimit != 12 ? $itemLimit : null
    ];
    
    $params = array_filter($params, function($value) {
        return $value !== null && $value !== '';
    });
    
    return 'search.php?' . http_build_query($params);
}

// Get SEO data
$seoTitle = "Search: " . htmlspecialchars($searchQuery) . " - Velona";
$seoDescription = "Search results for '" . htmlspecialchars($searchQuery) . "' - Find the best products at Velona";
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
    <meta name="description" content="<?= $seoDescription ?>">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
    
    <title><?= $seoTitle ?></title>
    
    <style>
        .search-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0 40px;
            margin-bottom: 40px;
        }
        
        .search-title {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .search-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .search-filters {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filter-section {
            margin-bottom: 25px;
        }
        
        .filter-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .price-inputs {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .price-input {
            flex: 1;
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
        }
        
        .price-input:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .results-count {
            color: #666;
            font-size: 1.1rem;
        }
        
        .results-count strong {
            color: #333;
        }
        
        .clear-filters-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .clear-filters-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .no-results {
            text-align: center;
            padding: 60px 0;
        }
        
        .no-results-icon {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .no-results-title {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: #333;
        }
        
        .no-results-text {
            color: #666;
            margin-bottom: 30px;
        }
        
        .search-suggestions {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-top: 40px;
        }
        
        .suggestions-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #333;
        }
        
        .suggestion-item {
            display: inline-block;
            background: white;
            padding: 8px 16px;
            margin: 5px;
            border-radius: 20px;
            text-decoration: none;
            color: #667eea;
            border: 2px solid #667eea;
            transition: all 0.3s;
        }
        
        .suggestion-item:hover {
            background: #667eea;
            color: white;
            text-decoration: none;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-tag {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-tag button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0;
            margin: 0;
        }
        
        .mobile-filters-toggle {
            display: none;
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            margin-bottom: 20px;
            width: 100%;
        }
        
        /* Product Card Styling */
        .product-card {
            position: relative;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .image-container {
            position: relative;
            overflow: hidden;
            height: 250px;
        }
        
        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s ease;
        }
        
        .hover-img {
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
        }
        
        .product-card:hover .hover-img {
            opacity: 1;
        }
        
        .product-card:hover .default-img {
            opacity: 0;
        }
        
        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 15px 15px 10px;
            color: #333;
        }
        
        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
            margin: 0 15px 10px;
        }
        
        .size-label {
            font-size: 0.9rem;
            color: #666;
            margin: 0 15px 10px;
            display: block;
        }
        
        .size-options {
            margin: 10px 15px;
        }
        
        .size-options .btn {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 15px;
        }
        
        .add-to-cart {
            position: absolute;
            bottom: 15px;
            left: 15px;
            right: 15px;
            background: #667eea;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(10px);
        }
        
        .product-card:hover .add-to-cart {
            opacity: 1;
            transform: translateY(0);
        }
        
        .add-to-cart:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        .add-to-cart:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 40px;
            gap: 10px;
        }
        
        .pagination-btn {
            padding: 8px 16px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s;
        }
        
        .pagination-btn:hover {
            background: #667eea;
            color: white;
            text-decoration: none;
        }
        
        .pagination-btn.active {
            background: #667eea;
            color: white;
        }
        
        .pagination-btn:disabled {
            background: #f8f9fa;
            color: #ccc;
            border-color: #ccc;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .search-title {
                font-size: 2rem;
            }
            
            .results-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .mobile-filters-toggle {
                display: block;
            }
            
            .search-filters {
                display: none;
            }
            
            .search-filters.show {
                display: block;
            }
            
            .price-inputs {
                flex-direction: column;
            }
            
            .add-to-cart {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

<!-- Search Header -->
    <div class="search-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb bg-transparent p-0 mb-0">
                            <li class="breadcrumb-item"><a href="../index.php" class="text-white-50">Home</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">Search Results</li>
                        </ol>
                    </nav>
                    
                    <h1 class="search-title">Search Results</h1>
                    <p class="search-subtitle">Showing results for "<strong><?= htmlspecialchars($searchQuery) ?></strong>"</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3">
                <!-- Mobile Filters Toggle -->
                <button class="mobile-filters-toggle" onclick="toggleMobileFilters()">
                    <i class="fas fa-filter mr-2"></i>Filters & Sort
                </button>
                
                <div class="search-filters" id="searchFilters">
                    <!-- Active Filters -->
                    <?php if ($categoryFilter || $priceMin > 0 || $priceMax > 0 || !empty($sortType)): ?>
                        <div class="filter-tags">
                            <?php if ($categoryFilter): ?>
                                <span class="filter-tag">
                                    Category: <?= htmlspecialchars($categoryFilter) ?>
                                    <button onclick="removeFilter('category')">×</button>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($priceMin > 0): ?>
                                <span class="filter-tag">
                                    Min: <?= formatProductPrice($priceMin) ?>
                                    <button onclick="removeFilter('price_min')">×</button>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($priceMax > 0): ?>
                                <span class="filter-tag">
                                    Max: <?= formatProductPrice($priceMax) ?>
                                    <button onclick="removeFilter('price_max')">×</button>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($sortType)): ?>
                                <span class="filter-tag">
                                    Sort: <?= htmlspecialchars($sortType) ?>
                                    <button onclick="removeFilter('sort')">×</button>
                                </span>
                            <?php endif; ?>
                            
                            <button class="clear-filters-btn" onclick="clearAllFilters()">
                                <i class="fas fa-times mr-1"></i>Clear All
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Sort Options -->
                    <div class="filter-section">
                        <h6 class="filter-title">Sort By</h6>
                        <select class="form-control" id="sortSelect" onchange="applySorting()">
                            <option value="">Relevance</option>
                            <option value="name_asc" <?= $sortType === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                            <option value="name_desc" <?= $sortType === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                            <option value="price_low" <?= $sortType === 'price_low' ? 'selected' : '' ?>>Price (Low to High)</option>
                            <option value="price_high" <?= $sortType === 'price_high' ? 'selected' : '' ?>>Price (High to Low)</option>
                            <option value="newest" <?= $sortType === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        </select>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="filter-section">
                        <h6 class="filter-title">Category</h6>
                        <select class="form-control" id="categorySelect" onchange="applyFilters()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['slug']) ?>" <?= $categoryFilter === $category['slug'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Price Range Filter -->
                    <div class="filter-section">
                        <h6 class="filter-title">Price Range</h6>
                        <div class="price-range-container">
                            <div class="price-inputs">
                                <input type="number" class="price-input" id="priceMin" placeholder="Min" 
                                       value="<?= $priceMin > 0 ? $priceMin : '' ?>" min="0" max="<?= $priceRange['max'] ?>">
                                <input type="number" class="price-input" id="priceMax" placeholder="Max" 
                                       value="<?= $priceMax > 0 ? $priceMax : '' ?>" min="0" max="<?= $priceRange['max'] ?>">
                            </div>
                            <button class="btn btn-primary btn-sm mt-3 w-100" onclick="applyPriceFilter()">
                                Apply Price Filter
                            </button>
                        </div>
                    </div>
                    
                    <!-- Items Per Page -->
                    <div class="filter-section">
                        <h6 class="filter-title">Items Per Page</h6>
                        <select class="form-control" id="limitSelect" onchange="changeItemsPerPage()">
                            <option value="12" <?= $itemsPerPage == 12 ? 'selected' : '' ?>>12 per page</option>
                            <option value="24" <?= $itemsPerPage == 24 ? 'selected' : '' ?>>24 per page</option>
                            <option value="48" <?= $itemsPerPage == 48 ? 'selected' : '' ?>>48 per page</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Results Section -->
            <div class="col-lg-9">
                <!-- Results Header -->
                <div class="results-header">
                    <div class="results-count">
                        <strong><?= count($searchResults) ?></strong> of <strong><?= $totalResults ?></strong> results
                        <?php if ($page > 1): ?>
                            (Page <?= $page ?> of <?= $totalPages ?>)
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div class="loading d-none" id="loadingState">
                    <div class="spinner"></div>
                    <p>Searching products...</p>
                </div>
                
                <?php if (!empty($searchResults)): ?>
                    <!-- Products Grid -->
                    <div class="row" id="productsGrid">
                        <?php foreach ($searchResults as $product): ?>
                            <div class="col-12 col-md-6 col-lg-4 mb-4">
                                <div class="product-card">
                                    <a href="product.php?id=<?= $product['id'] ?>" class="text-decoration-none text-dark">
                                        <div class="image-container">
                                            <?php 
                                            $primaryImage = $product['primary_image'] ?: '../assets/images/default-product.jpg';
                                            ?>
                                            <img src="<?= htmlspecialchars($primaryImage) ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>" class="default-img">
                                            <img src="<?= htmlspecialchars($primaryImage) ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>" class="hover-img">
                                        </div>
                                        
                                        <h5 class="product-title"><?= htmlspecialchars($product['name']) ?></h5>
                                        <p class="product-price"><?= formatProductPrice($product['price']) ?></p>
                                        
                                        <!-- Category Badge -->
                                        <span class="badge badge-secondary mb-2" style="margin-left: 15px;"><?= htmlspecialchars($product['category_name']) ?></span>
                                        
                                        <!-- Stock Status -->
                                        <?php if (isset($product['stock_status'])): ?>
                                            <?php if ($product['stock_status'] === 'out_of_stock'): ?>
                                                <span class="badge badge-danger" style="margin-left: 5px;">Out of Stock</span>
                                            <?php elseif ($product['stock_status'] === 'low_stock'): ?>
                                                <span class="badge badge-warning" style="margin-left: 5px;">Low Stock</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </a>
                                    
                                    <?php 
                                    $sizes = getProductSizes($product['id']);
                                    $defaultSize = !empty($sizes) ? $sizes[0] : 'M';
                                    ?>
                                    
                                    <!-- Size Options -->
                                    <?php if (!empty($sizes)): ?>
                                        <div class="size-options">
                                            <small class="size-label">Size:</small>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php foreach (array_slice($sizes, 0, 4) as $size): ?>
                                                    <button type="button" class="btn btn-outline-secondary size-btn" data-size="<?= htmlspecialchars($size) ?>">
                                                        <?= htmlspecialchars($size) ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Add to Cart Button -->
                                    <?php if ($isLoggedIn): ?>
                                        <button class="add-to-cart" 
                                                data-product-id="<?= $product['id'] ?>" 
                                                data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                                data-default-size="<?= htmlspecialchars($defaultSize) ?>"
                                                <?= isset($product['stock_status']) && $product['stock_status'] === 'out_of_stock' ? 'disabled' : '' ?>
                                                onclick="addToCartFromSearch(this)">
                                            <?php if (isset($product['stock_status']) && $product['stock_status'] === 'out_of_stock'): ?>
                                                Out of Stock
                                            <?php else: ?>
                                                <i class="fas fa-shopping-cart mr-2"></i>Add to Cart
                                            <?php endif; ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="add-to-cart" onclick="showLoginPrompt()">
                                            <i class="fas fa-shopping-cart mr-2"></i>Login to Add
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-wrapper">
                            <?php if ($page > 1): ?>
                                <a href="<?= buildSearchUrl($searchQuery, $page - 1, $categoryFilter, $priceMin, $priceMax, $sortType, $itemsPerPage) ?>" 
                                   class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            // Calculate pagination range
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            // Show first page if we're not starting from 1
                            if ($startPage > 1): ?>
                                <a href="<?= buildSearchUrl($searchQuery, 1, $categoryFilter, $priceMin, $priceMax, $sortType, $itemsPerPage) ?>" 
                                   class="pagination-btn">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-btn" style="border: none; background: none; color: #666;">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="<?= buildSearchUrl($searchQuery, $i, $categoryFilter, $priceMin, $priceMax, $sortType, $itemsPerPage) ?>" 
                                   class="pagination-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            
                            <?php
                            // Show last page if we're not ending at the last page
                            if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-btn" style="border: none; background: none; color: #666;">...</span>
                                <?php endif; ?>
                                <a href="<?= buildSearchUrl($searchQuery, $totalPages, $categoryFilter, $priceMin, $priceMax, $sortType, $itemsPerPage) ?>" 
                                   class="pagination-btn"><?= $totalPages ?></a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="<?= buildSearchUrl($searchQuery, $page + 1, $categoryFilter, $priceMin, $priceMax, $sortType, $itemsPerPage) ?>" 
                                   class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- No Results -->
                    <div class="no-results">
                        <div class="no-results-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="no-results-title">No products found</h3>
                        <p class="no-results-text">
                            We couldn't find any products matching "<strong><?= htmlspecialchars($searchQuery) ?></strong>".
                            <br>Try different keywords or browse our categories.
                        </p>
                        
                        <!-- Search Suggestions -->
                        <?php if (!empty($searchSuggestions)): ?>
                            <div class="search-suggestions">
                                <h4 class="suggestions-title">You might also like:</h4>
                                <div class="row">
                                    <?php foreach ($searchSuggestions as $suggestion): ?>
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="card">
                                                <a href="product.php?id=<?= $suggestion['id'] ?>">
                                                    <img src="<?= htmlspecialchars($suggestion['primary_image'] ?: '../assets/images/default-product.jpg') ?>" 
                                                         class="card-img-top" alt="<?= htmlspecialchars($suggestion['name']) ?>" style="height: 150px; object-fit: cover;">
                                                </a>
                                                <div class="card-body p-2">
                                                    <h6 class="card-title mb-1"><?= htmlspecialchars(truncateText($suggestion['name'], 30)) ?></h6>
                                                    <p class="card-text text-primary mb-0"><?= formatProductPrice($suggestion['price']) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Popular Search Terms -->
                        <div class="mt-4">
                            <h5>Popular searches:</h5>
                            <div class="d-flex flex-wrap justify-content-center">
                                <a href="search.php?q=shirt" class="suggestion-item">Shirts</a>
                                <a href="search.php?q=dress" class="suggestion-item">Dresses</a>
                                <a href="search.php?q=jeans" class="suggestion-item">Jeans</a>
                                <a href="search.php?q=jacket" class="suggestion-item">Jackets</a>
                                <a href="search.php?q=shoes" class="suggestion-item">Shoes</a>
                                <a href="category.php" class="suggestion-item">View All Products</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- User Menu Modal (if logged in) -->
    <?php if ($isLoggedIn): ?>
        <div class="modal fade" id="userMenuModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user mr-2"></i><?= htmlspecialchars($currentUser['name']) ?>
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="user-info mb-3">
                            <p><strong>Email:</strong> <?= htmlspecialchars($currentUser['email']) ?></p>
                            <p><strong>Wallet Balance:</strong> <?= formatProductPrice($walletBalance['points'] + $walletBalance['pending_points']) ?></p>
                        </div>
                        <div class="user-actions">
                            <a href="../profile.php" class="btn btn-primary btn-block mb-2">
                                <i class="fas fa-user-edit mr-2"></i>Profile
                            </a>
                            <a href="../orders.php" class="btn btn-info btn-block mb-2">
                                <i class="fas fa-box mr-2"></i>My Orders
                            </a>
                            <a href="../wallet.php" class="btn btn-success btn-block mb-2">
                                <i class="fas fa-wallet mr-2"></i>Wallet
                            </a>
                            <a href="../referral.php" class="btn btn-warning btn-block mb-2">
                                <i class="fas fa-share-alt mr-2"></i>Referrals
                            </a>
                            <a href="../auth/logout.php" class="btn btn-danger btn-block">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Toast Notifications -->
    <div class="toast-container position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
        <div id="cartToast" class="toast" role="alert" data-delay="3000">
            <div class="toast-header">
                <i class="fas fa-shopping-cart text-success mr-2"></i>
                <strong class="mr-auto">Cart Updated</strong>
                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast">
                    <span>&times;</span>
                </button>
            </div>
            <div class="toast-body" id="cartToastBody">
                Product added to cart successfully!
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables
        let isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
        let currentQuery = '<?= htmlspecialchars($searchQuery) ?>';
        
        // Search functionality
        function toggleSearch() {
            $('#searchModal').modal('show');
            setTimeout(() => {
                $('#searchInput').focus();
            }, 500);
        }
        
        function handleSearchKeypress(event) {
            if (event.key === 'Enter') {
                performNewSearch();
            }
        }
        
        function performNewSearch() {
            const query = $('#searchInput').val().trim();
            if (query) {
                window.location.href = `search.php?q=${encodeURIComponent(query)}`;
            }
        }
        
        // Filter functionality
        function toggleMobileFilters() {
            $('#searchFilters').toggleClass('show');
        }
        
        function applySorting() {
            const sort = $('#sortSelect').val();
            updateURL({ sort: sort, page: 1 });
        }
        
        function applyFilters() {
            const category = $('#categorySelect').val();
            updateURL({ category: category, page: 1 });
        }
        
        function applyPriceFilter() {
            const minPrice = $('#priceMin').val();
            const maxPrice = $('#priceMax').val();
            
            if (minPrice && maxPrice && parseFloat(minPrice) > parseFloat(maxPrice)) {
                alert('Minimum price cannot be greater than maximum price');
                return;
            }
            
            updateURL({ 
                price_min: minPrice || null, 
                price_max: maxPrice || null, 
                page: 1 
            });
        }
        
        function changeItemsPerPage() {
            const limit = $('#limitSelect').val();
            updateURL({ limit: limit, page: 1 });
        }
        
        function removeFilter(filterType) {
            const updates = { page: 1 };
            
            switch(filterType) {
                case 'category':
                    updates.category = null;
                    break;
                case 'price_min':
                    updates.price_min = null;
                    break;
                case 'price_max':
                    updates.price_max = null;
                    break;
                case 'sort':
                    updates.sort = null;
                    break;
            }
            
            updateURL(updates);
        }
        
        function clearAllFilters() {
            updateURL({
                category: null,
                price_min: null,
                price_max: null,
                sort: null,
                page: 1
            });
        }
        
        function updateURL(updates) {
            const url = new URL(window.location);
            const params = url.searchParams;
            
            Object.keys(updates).forEach(key => {
                if (updates[key] === null || updates[key] === '') {
                    params.delete(key);
                } else {
                    params.set(key, updates[key]);
                }
            });
            
            window.location.href = url.toString();
        }
        
        // Cart functionality
        function addToCartFromSearch(button) {
            if (!isLoggedIn) {
                showLoginPrompt();
                return;
            }
            
            const productId = button.dataset.productId;
            const productName = button.dataset.productName;
            const defaultSize = button.dataset.defaultSize;
            
            // Get selected size from the product card
            const productCard = button.closest('.product-card');
            const activeSizeBtn = productCard.querySelector('.size-btn.active');
            const selectedSize = activeSizeBtn ? activeSizeBtn.dataset.size : defaultSize;
            
            // Show loading state
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
            button.disabled = true;
            
            // Make AJAX request
            $.ajax({
                url: '../api/cart.php',
                method: 'POST',
                data: {
                    action: 'add',
                    product_id: productId,
                    quantity: 1,
                    size: selectedSize
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update cart badge
                        updateCartBadge();
                        
                        // Show success toast
                        showCartToast(`${productName} added to cart!`, 'success');
                        
                        // Reset button
                        button.innerHTML = '<i class="fas fa-check mr-2"></i>Added!';
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }, 2000);
                    } else {
                        showCartToast(response.message || 'Failed to add to cart', 'error');
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                },
                error: function() {
                    showCartToast('Error adding to cart. Please try again.', 'error');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            });
        }
        
        function updateCartBadge() {
            $.get('../api/cart.php?action=summary', function(response) {
                if (response.success && response.data.item_count > 0) {
                    const badge = $('#cartBadge');
                    if (badge.length) {
                        badge.text(response.data.item_count);
                    } else {
                        $('a[href="cart.php"]').append(
                            `<span class="position-absolute badge badge-danger" style="top: -8px; right: -8px; font-size: 0.7rem;" id="cartBadge">
                                ${response.data.item_count}
                            </span>`
                        );
                    }
                } else {
                    $('#cartBadge').remove();
                }
            });
        }
        
        function showCartToast(message, type = 'success') {
            const toastBody = $('#cartToastBody');
            const toastHeader = $('#cartToast .toast-header');
            
            // Update content
            toastBody.text(message);
            
            // Update style based on type
            if (type === 'error') {
                toastHeader.find('i').removeClass('text-success').addClass('text-danger');
                toastHeader.find('strong').text('Error');
            } else {
                toastHeader.find('i').removeClass('text-danger').addClass('text-success');
                toastHeader.find('strong').text('Cart Updated');
            }
            
            // Show toast
            $('#cartToast').toast('show');
        }
        
        function showLoginPrompt() {
            if (confirm('Please login to add items to cart. Would you like to login now?')) {
                triggerOneTapLogin();
            }
        }
        
        // Size selection
        $(document).on('click', '.size-btn', function() {
            $(this).siblings('.size-btn').removeClass('active');
            $(this).addClass('active');
        });
        
        // User menu functionality
        function toggleUserMenu() {
            $('#userMenuModal').modal('show');
        }
        
        // Google Sign In placeholder
        function initGoogleSignIn() {
            if (typeof triggerOneTapLogin === 'function') {
                triggerOneTapLogin();
            } else {
                // Fallback if One-Tap not available
                alert('Authentication system not ready. Please refresh and try again.');
                window.location.reload();
            }
        }
        
        // Initialize tooltips
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();
            
            // Auto-select first size for each product
            $('.size-options .size-btn').first().addClass('active');
            
            // Initialize search input focus
            $('#searchModal').on('shown.bs.modal', function() {
                $('#searchInput').focus();
            });
        });
        
        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                toggleSearch();
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                $('.modal').modal('hide');
            }
        });
        
        // Infinite scroll (optional enhancement)
        let isLoading = false;
        let hasMoreResults = <?= $page < $totalPages ? 'true' : 'false' ?>;
        
        function loadMoreResults() {
            if (isLoading || !hasMoreResults) return;
            
            isLoading = true;
            const nextPage = <?= $page + 1 ?>;
            
            // Show loading indicator
            $('#loadingState').removeClass('d-none');
            
            // Build URL for next page
            const url = new URL(window.location);
            url.searchParams.set('page', nextPage);
            
            $.get(url.toString())
                .done(function(response) {
                    // This would require AJAX endpoint to return JSON
                    // For now, we'll use regular pagination
                })
                .always(function() {
                    isLoading = false;
                    $('#loadingState').addClass('d-none');
                });
        }
        
        // Scroll to load more (optional)
        $(window).scroll(function() {
            if ($(window).scrollTop() + $(window).height() >= $(document).height() - 1000) {
                // loadMoreResults(); // Uncomment to enable infinite scroll
            }
        });
        
        // Search analytics (optional)
        function trackSearchEvent(query, results) {
            // Track search analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'search', {
                    search_term: query,
                    results_count: results
                });
            }
        }
        
        // Track current search
        if (currentQuery) {
            trackSearchEvent(currentQuery, <?= count($searchResults) ?>);
        }
        
        // Auto-save search preferences to localStorage
        function saveSearchPreferences() {
            const preferences = {
                sort: $('#sortSelect').val(),
                itemsPerPage: $('#limitSelect').val(),
                timestamp: Date.now()
            };
            
            try {
                localStorage.setItem('searchPreferences', JSON.stringify(preferences));
            } catch (e) {
                // Handle localStorage errors
                console.warn('Could not save search preferences:', e);
            }
        }
        
        function loadSearchPreferences() {
            try {
                const saved = localStorage.getItem('searchPreferences');
                if (saved) {
                    const preferences = JSON.parse(saved);
                    
                    // Apply saved preferences if they're recent (less than 7 days old)
                    if (Date.now() - preferences.timestamp < 7 * 24 * 60 * 60 * 1000) {
                        if (preferences.sort && !$('#sortSelect').val()) {
                            $('#sortSelect').val(preferences.sort);
                        }
                        if (preferences.itemsPerPage && $('#limitSelect').val() === '12') {
                            $('#limitSelect').val(preferences.itemsPerPage);
                        }
                    }
                }
            } catch (e) {
                console.warn('Could not load search preferences:', e);
            }
        }
        
        // Save preferences on change
        $('#sortSelect, #limitSelect').on('change', saveSearchPreferences);
        
        // Load preferences on page load
        $(document).ready(function() {
            loadSearchPreferences();
        });
    </script>
    
    <!-- Custom CSS for enhanced interactivity -->
    <style>
        .size-btn.active {
            background-color: #667eea !important;
            color: white !important;
            border-color: #667eea !important;
        }
        
        .toast-container .toast {
            background-color: white;
            border: 1px solid #dee2e6;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
        }
        
        .product-card {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .pagination-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25);
        }
        
        .search-filters {
            transition: all 0.3s ease;
        }
        
        @media (max-width: 991.98px) {
            .search-filters {
                max-height: 0;
                overflow: hidden;
                padding: 0 25px;
                margin-bottom: 0;
            }
            
            .search-filters.show {
                max-height: 1000px;
                padding: 25px;
                margin-bottom: 30px;
            }
        }
        
        .filter-tag {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .no-results {
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <!-- Google Sign-In Script -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <!-- SIMPLIFIED AUTH FOR NON-INDEX PAGES (ADD THIS) -->
    <script>
    (function() {
        'use strict';
        
        // Configuration
        const GOOGLE_CLIENT_ID = "340757900430-i8nl6l45ndveq9jmbvbah7ugquauj803.apps.googleusercontent.com";
        const AUTH_ENDPOINT = "../auth/google-callback.php";
        
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
    </script>
</body>
</html>