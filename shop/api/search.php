<?php
// shop/api/search.php - Complete Search API for product search functionality
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../auth/session.php';

// Set JSON content type
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if user is logged in (optional for search)
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;
$userId = $isLoggedIn ? $currentUser['id'] : null;

// Get request parameters
$query = trim($_GET['q'] ?? $_POST['q'] ?? '');
$category = trim($_GET['category'] ?? $_POST['category'] ?? '');
$priceMin = floatval($_GET['price_min'] ?? $_POST['price_min'] ?? 0);
$priceMax = floatval($_GET['price_max'] ?? $_POST['price_max'] ?? 0);
$sortBy = trim($_GET['sort'] ?? $_POST['sort'] ?? '');
$limit = intval($_GET['limit'] ?? $_POST['limit'] ?? 20);
$offset = intval($_GET['offset'] ?? $_POST['offset'] ?? 0);
$page = max(1, intval($_GET['page'] ?? $_POST['page'] ?? 1));
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'search');

// Validate and sanitize inputs
$query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
$category = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
$sortBy = htmlspecialchars($sortBy, ENT_QUOTES, 'UTF-8');
$limit = min(max($limit, 1), 100); // Limit between 1 and 100
$offset = max($offset, 0);

// Calculate offset from page if not provided
if ($page > 1 && $offset === 0) {
    $offset = ($page - 1) * $limit;
}

// Main API router
try {
    switch ($action) {
        case 'search':
            handleProductSearch();
            break;
            
        case 'suggestions':
        case 'autocomplete':
            handleSearchSuggestions();
            break;
            
        case 'quick_search':
            handleQuickSearch();
            break;
            
        case 'popular_searches':
            handlePopularSearches();
            break;
            
        case 'categories':
            handleGetCategories();
            break;
            
        case 'filters':
            handleGetFilters();
            break;
            
        case 'trending':
            handleTrendingProducts();
            break;
            
        case 'related':
            handleRelatedProducts();
            break;
            
        default:
            handleProductSearch(); // Default to search
            break;
    }
} catch (Exception $e) {
    error_log("Search API Error: " . $e->getMessage());
    sendErrorResponse('Internal server error', 500);
}

// ============================================================================
// SEARCH ACTION HANDLERS
// ============================================================================

/**
 * Handle main product search
 */
function handleProductSearch() {
    global $query, $category, $priceMin, $priceMax, $sortBy, $limit, $offset, $page;
    
    // Validate search query
    if (empty($query) || strlen($query) < 2) {
        sendErrorResponse('Search query must be at least 2 characters long');
        return;
    }
    
    // Perform search
    $searchResults = searchProducts($query, $limit, $offset);
    
    // Apply additional filters
    $filteredResults = applySearchFilters($searchResults, $category, $priceMin, $priceMax);
    
    // Apply sorting
    if (!empty($sortBy)) {
        $filteredResults = sortProductsArray($filteredResults, $sortBy);
    }
    
    // Get total count for pagination (without limit)
    $allResults = searchProducts($query, 1000, 0);
    $allFilteredResults = applySearchFilters($allResults, $category, $priceMin, $priceMax);
    $totalResults = count($allFilteredResults);
    
    // Calculate pagination info
    $totalPages = ceil($totalResults / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    // Enhance product data
    $enhancedResults = [];
    foreach ($filteredResults as $product) {
        $enhancedProduct = enhanceProductData($product);
        $enhancedResults[] = $enhancedProduct;
    }
    
    // Prepare response
    $response = [
        'products' => $enhancedResults,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_results' => $totalResults,
            'results_per_page' => $limit,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage,
            'showing_from' => $offset + 1,
            'showing_to' => min($offset + $limit, $totalResults)
        ],
        'filters' => [
            'query' => $query,
            'category' => $category,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'sort_by' => $sortBy
        ],
        'search_metadata' => [
            'search_time' => microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"],
            'suggestions' => getSearchSuggestions($query),
            'related_categories' => getRelatedCategories($query)
        ]
    ];
    
    // Log search activity
    logSearchActivity($query, $totalResults);
    
    sendSuccessResponse('Search completed successfully', $response);
}

/**
 * Handle search suggestions/autocomplete
 */
function handleSearchSuggestions() {
    global $query, $limit;
    
    if (empty($query) || strlen($query) < 2) {
        sendSuccessResponse('Suggestions retrieved', ['suggestions' => []]);
        return;
    }
    
    $suggestions = [];
    $limit = min($limit, 10); // Max 10 suggestions
    
    try {
        $conn = getConnection();
        
        // Get product name suggestions
        $stmt = $conn->prepare("
            SELECT DISTINCT name, id, price,
                   (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = TRUE LIMIT 1) as primary_image
            FROM products p
            WHERE p.status = 'active' 
            AND p.name LIKE ?
            ORDER BY p.featured DESC, p.name ASC
            LIMIT ?
        ");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $limit]);
        $productSuggestions = $stmt->fetchAll();
        
        foreach ($productSuggestions as $product) {
            $suggestions[] = [
                'type' => 'product',
                'text' => $product['name'],
                'url' => "product.php?id={$product['id']}",
                'image' => $product['primary_image'],
                'price' => formatProductPrice($product['price']),
                'category' => 'product'
            ];
        }
        
        // Get category suggestions
        $stmt = $conn->prepare("
            SELECT name, slug 
            FROM categories 
            WHERE status = 'active' 
            AND name LIKE ?
            ORDER BY name ASC
            LIMIT 3
        ");
        $stmt->execute([$searchTerm]);
        $categorySuggestions = $stmt->fetchAll();
        
        foreach ($categorySuggestions as $category) {
            $suggestions[] = [
                'type' => 'category',
                'text' => $category['name'],
                'url' => "category.php?category={$category['slug']}",
                'category' => 'category'
            ];
        }
        
        // Add popular search terms if we have few results
        if (count($suggestions) < 5) {
            $popularTerms = getPopularSearchTerms($query, 5 - count($suggestions));
            foreach ($popularTerms as $term) {
                $suggestions[] = [
                    'type' => 'search',
                    'text' => $term,
                    'url' => "search.php?q=" . urlencode($term),
                    'category' => 'popular'
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Search suggestions error: " . $e->getMessage());
    }
    
    sendSuccessResponse('Suggestions retrieved successfully', [
        'suggestions' => $suggestions,
        'query' => $query,
        'count' => count($suggestions)
    ]);
}

/**
 * Handle quick search for modal/dropdown
 */
function handleQuickSearch() {
    global $query;
    
    if (empty($query) || strlen($query) < 2) {
        sendSuccessResponse('Quick search results', ['products' => []]);
        return;
    }
    
    // Get quick results (limited to 6 items)
    $quickResults = searchProducts($query, 6, 0);
    
    $enhancedResults = [];
    foreach ($quickResults as $product) {
        $enhancedResults[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => formatProductPrice($product['price']),
            'image' => $product['primary_image'] ?? '../assets/images/default-product.jpg',
            'url' => "product.php?id={$product['id']}",
            'category' => $product['category_name'] ?? '',
            'in_stock' => ($product['stock_quantity'] ?? 0) > 0
        ];
    }
    
    sendSuccessResponse('Quick search completed', [
        'products' => $enhancedResults,
        'query' => $query,
        'total_found' => count($enhancedResults),
        'view_all_url' => "search.php?q=" . urlencode($query)
    ]);
}

/**
 * Handle popular searches
 */
function handlePopularSearches() {
    $popularSearches = [
        'shirt', 'dress', 'jeans', 'jacket', 'shoes', 
        'hoodie', 'pants', 'skirt', 'blazer', 'sweater'
    ];
    
    // You can implement database tracking for actual popular searches
    try {
        $conn = getConnection();
        
        // Get actual popular searches from search logs if implemented
        $stmt = $conn->prepare("
            SELECT search_term, COUNT(*) as search_count
            FROM search_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY search_term
            ORDER BY search_count DESC
            LIMIT 10
        ");
        
        if ($stmt->execute()) {
            $dbPopularSearches = $stmt->fetchAll();
            if (!empty($dbPopularSearches)) {
                $popularSearches = array_column($dbPopularSearches, 'search_term');
            }
        }
    } catch (Exception $e) {
        // Use default popular searches if database query fails
        error_log("Popular searches query failed: " . $e->getMessage());
    }
    
    sendSuccessResponse('Popular searches retrieved', [
        'popular_searches' => $popularSearches,
        'count' => count($popularSearches)
    ]);
}

/**
 * Handle get categories for filters
 */
function handleGetCategories() {
    $categories = getAllCategories('active');
    
    $formattedCategories = [];
    foreach ($categories as $category) {
        $formattedCategories[] = [
            'id' => $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'product_count' => $category['product_count'] ?? 0,
            'url' => "category.php?category={$category['slug']}"
        ];
    }
    
    sendSuccessResponse('Categories retrieved successfully', [
        'categories' => $formattedCategories,
        'count' => count($formattedCategories)
    ]);
}

/**
 * Handle get filter options
 */
function handleGetFilters() {
    global $query, $category;
    
    $filters = [
        'price_ranges' => [
            ['min' => 0, 'max' => 500, 'label' => 'Under ₹500'],
            ['min' => 500, 'max' => 1000, 'label' => '₹500 - ₹1,000'],
            ['min' => 1000, 'max' => 2000, 'label' => '₹1,000 - ₹2,000'],
            ['min' => 2000, 'max' => 5000, 'label' => '₹2,000 - ₹5,000'],
            ['min' => 5000, 'max' => 0, 'label' => 'Above ₹5,000']
        ],
        'sort_options' => [
            ['value' => '', 'label' => 'Relevance'],
            ['value' => 'name_asc', 'label' => 'Name (A-Z)'],
            ['value' => 'name_desc', 'label' => 'Name (Z-A)'],
            ['value' => 'price_low', 'label' => 'Price (Low to High)'],
            ['value' => 'price_high', 'label' => 'Price (High to Low)'],
            ['value' => 'newest', 'label' => 'Newest First'],
            ['value' => 'featured', 'label' => 'Featured']
        ],
        'categories' => []
    ];
    
    // Get categories
    $categories = getAllCategories('active');
    foreach ($categories as $cat) {
        $filters['categories'][] = [
            'slug' => $cat['slug'],
            'name' => $cat['name'],
            'count' => $cat['product_count'] ?? 0
        ];
    }
    
    // Get dynamic price range from actual products
    try {
        $conn = getConnection();
        $stmt = $conn->query("
            SELECT MIN(price) as min_price, MAX(price) as max_price 
            FROM products 
            WHERE status = 'active'
        ");
        $priceRange = $stmt->fetch();
        
        if ($priceRange) {
            $filters['price_range'] = [
                'min' => floatval($priceRange['min_price']),
                'max' => floatval($priceRange['max_price'])
            ];
        }
    } catch (Exception $e) {
        error_log("Price range query error: " . $e->getMessage());
        $filters['price_range'] = ['min' => 0, 'max' => 10000];
    }
    
    sendSuccessResponse('Filters retrieved successfully', $filters);
}

/**
 * Handle trending products
 */
function handleTrendingProducts() {
    global $limit;
    
    $limit = min($limit, 20);
    
    // Get trending products (you can implement your own trending logic)
    $trendingProducts = getBestSellers($limit);
    
    if (empty($trendingProducts)) {
        // Fallback to featured products
        $trendingProducts = getFeaturedProducts($limit);
    }
    
    $enhancedProducts = [];
    foreach ($trendingProducts as $product) {
        $enhancedProducts[] = enhanceProductData($product);
    }
    
    sendSuccessResponse('Trending products retrieved', [
        'products' => $enhancedProducts,
        'count' => count($enhancedProducts)
    ]);
}

/**
 * Handle related products
 */
function handleRelatedProducts() {
    global $limit;
    
    $productId = intval($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
    $limit = min($limit, 12);
    
    if ($productId <= 0) {
        sendErrorResponse('Valid product ID is required');
        return;
    }
    
    $relatedProducts = getRelatedProducts($productId, $limit);
    
    $enhancedProducts = [];
    foreach ($relatedProducts as $product) {
        $enhancedProducts[] = enhanceProductData($product);
    }
    
    sendSuccessResponse('Related products retrieved', [
        'products' => $enhancedProducts,
        'count' => count($enhancedProducts),
        'product_id' => $productId
    ]);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Apply search filters to results
 */
function applySearchFilters($products, $category, $priceMin, $priceMax) {
    if (empty($category) && $priceMin <= 0 && $priceMax <= 0) {
        return $products;
    }
    
    return array_filter($products, function($product) use ($category, $priceMin, $priceMax) {
        // Category filter
        if (!empty($category)) {
            $productCategory = $product['category_slug'] ?? '';
            if ($productCategory !== $category) {
                return false;
            }
        }
        
        // Price filter
        $price = floatval($product['price'] ?? 0);
        
        if ($priceMin > 0 && $price < $priceMin) {
            return false;
        }
        
        if ($priceMax > 0 && $price > $priceMax) {
            return false;
        }
        
        return true;
    });
}

/**
 * Enhance product data for API response
 */
function enhanceProductData($product) {
    // Get additional product information
    $enhanced = [
        'id' => intval($product['id']),
        'name' => $product['name'],
        'slug' => $product['slug'] ?? '',
        'price' => floatval($product['price']),
        'formatted_price' => formatProductPrice($product['price']),
        'description' => $product['description'] ?? '',
        'category_id' => intval($product['category_id'] ?? 0),
        'category_name' => $product['category_name'] ?? '',
        'category_slug' => $product['category_slug'] ?? '',
        'primary_image' => $product['primary_image'] ?? '../assets/images/default-product.jpg',
        'stock_quantity' => intval($product['stock_quantity'] ?? 0),
        'stock_status' => $product['stock_status'] ?? 'in_stock',
        'featured' => boolval($product['featured'] ?? false),
        'created_at' => $product['created_at'] ?? '',
        'updated_at' => $product['updated_at'] ?? '',
        'url' => "product.php?id={$product['id']}",
        'add_to_cart_url' => "../api/cart.php",
        'in_stock' => ($product['stock_quantity'] ?? 0) > 0,
        'sizes' => []
    ];
    
    // Get product sizes
    if (isset($product['sizes']) && !empty($product['sizes'])) {
        $sizes = json_decode($product['sizes'], true);
        if (is_array($sizes)) {
            $enhanced['sizes'] = $sizes;
        }
    }
    
    if (empty($enhanced['sizes'])) {
        $enhanced['sizes'] = getProductSizes($product['id']);
    }
    
    // Calculate discount if applicable
    if (isset($product['original_price']) && $product['original_price'] > $product['price']) {
        $enhanced['original_price'] = floatval($product['original_price']);
        $enhanced['discount_percentage'] = calculateDiscountPercentage(
            $product['original_price'], 
            $product['price']
        );
        $enhanced['on_sale'] = true;
    } else {
        $enhanced['on_sale'] = false;
    }
    
    // Add rating if available
    if (function_exists('getProductRating')) {
        $rating = getProductRating($product['id']);
        $enhanced['rating'] = [
            'average' => $rating['average'],
            'count' => $rating['count']
        ];
    }
    
    return $enhanced;
}

/**
 * Get search suggestions based on query
 */
function getSearchSuggestions($query) {
    $suggestions = [];
    
    try {
        $conn = getConnection();
        
        // Get related product names
        $stmt = $conn->prepare("
            SELECT DISTINCT name 
            FROM products 
            WHERE status = 'active' 
            AND name LIKE ? 
            AND name != ?
            ORDER BY featured DESC, name ASC
            LIMIT 5
        ");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $query]);
        
        while ($row = $stmt->fetch()) {
            $suggestions[] = $row['name'];
        }
        
    } catch (Exception $e) {
        error_log("Search suggestions error: " . $e->getMessage());
    }
    
    return $suggestions;
}

/**
 * Get related categories based on search query
 */
function getRelatedCategories($query) {
    $categories = [];
    
    try {
        $conn = getConnection();
        
        // Find categories that have products matching the search
        $stmt = $conn->prepare("
            SELECT DISTINCT c.name, c.slug, COUNT(p.id) as product_count
            FROM categories c
            JOIN products p ON c.id = p.category_id
            WHERE c.status = 'active' 
            AND p.status = 'active'
            AND (p.name LIKE ? OR c.name LIKE ?)
            GROUP BY c.id, c.name, c.slug
            ORDER BY product_count DESC, c.name ASC
            LIMIT 5
        ");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        
        while ($row = $stmt->fetch()) {
            $categories[] = [
                'name' => $row['name'],
                'slug' => $row['slug'],
                'product_count' => intval($row['product_count']),
                'url' => "category.php?category={$row['slug']}"
            ];
        }
        
    } catch (Exception $e) {
        error_log("Related categories error: " . $e->getMessage());
    }
    
    return $categories;
}

/**
 * Get popular search terms
 */
function getPopularSearchTerms($currentQuery, $limit = 5) {
    // Default popular terms
    $popularTerms = ['shirt', 'dress', 'jeans', 'jacket', 'shoes'];
    
    try {
        $conn = getConnection();
        
        // Try to get from search logs if table exists
        $stmt = $conn->prepare("
            SELECT search_term, COUNT(*) as count
            FROM search_logs 
            WHERE search_term LIKE ? 
            AND search_term != ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY search_term
            ORDER BY count DESC
            LIMIT ?
        ");
        
        $searchTerm = '%' . $currentQuery . '%';
        if ($stmt->execute([$searchTerm, $currentQuery, $limit])) {
            $results = $stmt->fetchAll();
            if (!empty($results)) {
                $popularTerms = array_column($results, 'search_term');
            }
        }
        
    } catch (Exception $e) {
        // Table might not exist, use defaults
        error_log("Popular terms query error: " . $e->getMessage());
    }
    
    return array_slice($popularTerms, 0, $limit);
}

/**
 * Log search activity
 */
function logSearchActivity($query, $resultCount) {
    global $userId;
    
    try {
        $conn = getConnection();
        
        // Try to insert into search_logs table
        $stmt = $conn->prepare("
            INSERT INTO search_logs 
            (user_id, search_term, result_count, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $query,
            $resultCount,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
    } catch (Exception $e) {
        // Log to error log if database logging fails
        error_log("Search activity log: Query='$query', Results=$resultCount, User=" . ($userId ?? 'guest'));
    }
}

/**
 * Rate limiting for search API
 */
function checkSearchRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "search_api_rate_limit_" . $ip;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $now = time();
    $windowSize = 60; // 1 minute
    $maxRequests = 60; // Max 60 search requests per minute
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Remove old timestamps
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $windowSize) {
        return ($now - $timestamp) < $windowSize;
    });
    
    // Check if limit exceeded
    if (count($_SESSION[$key]) >= $maxRequests) {
        sendErrorResponse('Search rate limit exceeded. Please try again later.', 429);
        return false;
    }
    
    // Add current timestamp
    $_SESSION[$key][] = $now;
    
    return true;
}

// ============================================================================
// RESPONSE HELPER FUNCTIONS
// ============================================================================

/**
 * Send success response
 */
function sendSuccessResponse($message, $data = []) {
    $response = [
        'success' => true,
        'message' => $message,
        'timestamp' => time()
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 */
function sendErrorResponse($message, $code = 400) {
    $response = [
        'success' => false,
        'message' => $message,
        'timestamp' => time()
    ];
    
    http_response_code($code);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// INITIALIZATION
// ============================================================================

// Apply rate limiting
checkSearchRateLimit();

// Initialize search logs table if it doesn't exist
try {
    $conn = getConnection();
    $conn->exec("
        CREATE TABLE IF NOT EXISTS search_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            search_term VARCHAR(255) NOT NULL,
            result_count INT DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_search_term (search_term),
            INDEX idx_created_at (created_at),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // Continue without search logging if table creation fails
    error_log("Search logs table creation failed: " . $e->getMessage());
}
?>