<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);     

// Set content type first thing
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// admin/api/manage-products.php - BULLETPROOF Product Management API
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../admin-session.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check admin authentication for all requests
checkAdminAuth();

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_products':
            handleGetProducts();
            break;
            
        case 'get_product':
            handleGetProduct();
            break;
            
        case 'add_product':
            handleAddProduct();
            break;
            
        case 'update_product':
            handleUpdateProduct();
            break;
            
        case 'delete_product':
            handleDeleteProduct();
            break;
            
        case 'update_stock':
            handleUpdateStock();
            break;
            
        case 'get_categories':
            handleGetCategories();
            break;
            
        case 'get_stats':
            handleGetStats();
            break;
            
        case 'bulk_update':
            handleBulkUpdate();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Admin Products API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

// ================================
// GET PRODUCTS WITH PAGINATION
// ================================
function handleGetProducts() {
    try {
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = max(1, min(100, intval($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        
        // Build filters
        $filters = [];
        $params = [];
        
        if (!empty($_GET['search'])) {
            $filters[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($_GET['category_id'])) {
            $filters[] = "p.category_id = ?";
            $params[] = $_GET['category_id'];
        }
        
        if (!empty($_GET['status'])) {
            $filters[] = "p.status = ?";
            $params[] = $_GET['status'];
        }
        
        $whereClause = empty($filters) ? '' : 'WHERE ' . implode(' AND ', $filters);
        
        $conn = getConnection();
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) as total 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            {$whereClause}
        ";
        $stmt = $conn->prepare($countSql);
        $stmt->execute($params);
        $totalItems = $stmt->fetch()['total'];
        
        // Get products - FIX: Use proper integer binding for LIMIT/OFFSET
        $sql = "
            SELECT 
                p.*,
                c.name as category_name,
                c.slug as category_slug,
                (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = TRUE LIMIT 1) as primary_image,
                (SELECT COUNT(*) FROM product_images pi WHERE pi.product_id = p.id) as image_count,
                CASE 
                    WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
                    WHEN p.stock_quantity <= p.low_stock_threshold THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status
            FROM products p
            JOIN categories c ON p.category_id = c.id
            {$whereClause}
            ORDER BY p.featured DESC, p.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Process products
        foreach ($products as &$product) {
            $product['sizes'] = $product['sizes'] ? json_decode($product['sizes'], true) : [];
            $product['price'] = floatval($product['price']);
            $product['stock_quantity'] = intval($product['stock_quantity']);
            $product['featured'] = boolval($product['featured']);
        }
        
        // Pagination info
        $totalPages = ceil($totalItems / $perPage);
        
        echo json_encode([
            'success' => true,
            'products' => $products,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => intval($totalItems),
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetProducts: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load products']);
    }
}

// ================================
// GET SINGLE PRODUCT
// ================================
function handleGetProduct() {
    try {
        $productId = intval($_GET['id'] ?? 0);
        
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            return;
        }
        
        $product = getProductById($productId, true); // Use the function from functions.php
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'product' => $product
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetProduct: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load product']);
    }
}

// ================================
// ADD NEW PRODUCT
// ================================
function handleAddProduct() {
    try {
        // Validate input using function from functions.php
        $validation = validateProductData($_POST, false);
        if (!empty($validation)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $validation)]);
            return;
        }
        
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Generate slug using function from functions.php
        $slug = generateProductSlug($_POST['name']);
        
        // Prepare sizes
        $sizes = isset($_POST['sizes']) && is_array($_POST['sizes']) ? $_POST['sizes'] : [];
        $sizesJson = json_encode($sizes);
        
        // Insert product
        $stmt = $conn->prepare("
            INSERT INTO products 
            (category_id, name, slug, description, care_instructions, price, stock_quantity, 
             low_stock_threshold, sizes, status, featured) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['category_id'],
            $_POST['name'],
            $slug,
            $_POST['description'] ?? '',
            $_POST['care_instructions'] ?? '',
            $_POST['price'],
            $_POST['stock_quantity'] ?? 0,
            $_POST['low_stock_threshold'] ?? 10,
            $sizesJson,
            $_POST['status'] ?? 'active',
            isset($_POST['featured']) ? 1 : 0
        ]);
        
        $productId = $conn->lastInsertId();
        
        if (!$productId) {
            throw new Exception("Failed to create product");
        }
        
        // Handle image uploads
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $uploadResult = handleProductImages($productId, $_FILES['images']);
            if (!$uploadResult['success']) {
                throw new Exception("Product created but image upload failed: " . $uploadResult['message']);
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product added successfully',
            'product_id' => $productId
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log("Error in handleAddProduct: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ================================
// UPDATE PRODUCT
// ================================
function handleUpdateProduct() {
    try {
        $productId = intval($_POST['product_id'] ?? 0);
        
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            return;
        }
        
        // Validate input using function from functions.php
        $validation = validateProductData($_POST, true);
        if (!empty($validation)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $validation)]);
            return;
        }
        
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Check if product exists
        $stmt = $conn->prepare("SELECT id, name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        
        // Generate new slug if name changed using function from functions.php
        $currentProduct = $stmt->fetch();
        $slug = ($_POST['name'] !== $currentProduct['name']) 
               ? generateProductSlug($_POST['name'], $productId) 
               : null;
        
        // Prepare update data
        $updateFields = [];
        $updateParams = [];
        
        foreach (['category_id', 'name', 'description', 'care_instructions', 'price', 'stock_quantity', 'low_stock_threshold', 'status'] as $field) {
            if (isset($_POST[$field])) {
                $updateFields[] = "{$field} = ?";
                $updateParams[] = $_POST[$field];
            }
        }
        
        if ($slug) {
            $updateFields[] = "slug = ?";
            $updateParams[] = $slug;
        }
        
        // Handle sizes
        if (isset($_POST['sizes'])) {
            $sizes = is_array($_POST['sizes']) ? $_POST['sizes'] : [];
            $updateFields[] = "sizes = ?";
            $updateParams[] = json_encode($sizes);
        }
        
        // Handle featured
        $updateFields[] = "featured = ?";
        $updateParams[] = isset($_POST['featured']) ? 1 : 0;
        
        // Update timestamp
        $updateFields[] = "updated_at = NOW()";
        
        $updateParams[] = $productId;
        
        // Execute update
        $sql = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($updateParams);
        
        // Handle new image uploads
        if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
            $uploadResult = handleProductImages($productId, $_FILES['new_images']);
            if (!$uploadResult['success']) {
                throw new Exception("Product updated but new image upload failed: " . $uploadResult['message']);
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully'
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log("Error in handleUpdateProduct: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ================================
// DELETE PRODUCT
// ================================
function handleDeleteProduct() {
    try {
        $productId = intval($_POST['product_id'] ?? 0);
        
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            return;
        }
        
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Check if product exists
        $stmt = $conn->prepare("SELECT id, name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        
        $product = $stmt->fetch();
        
        // Check if product has orders (prevent deletion if so)
        $stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM order_items WHERE product_id = ?");
        $stmt->execute([$productId]);
        $orderCount = $stmt->fetch()['order_count'];
        
        if ($orderCount > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot delete product that has been ordered. Consider marking it as inactive instead.'
            ]);
            return;
        }
        
        // Get product images for deletion
        $stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
        $stmt->execute([$productId]);
        $images = $stmt->fetchAll();
        
        // Delete product (cascade will handle images)
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        
        $conn->commit();
        
        // Delete physical image files
        foreach ($images as $image) {
            $imagePath = "../../uploads/products/" . basename($image['image_url']);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log("Error in handleDeleteProduct: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ================================
// UPDATE STOCK ONLY
// ================================
function handleUpdateStock() {
    try {
        $productId = intval($_POST['product_id'] ?? 0);
        $stockQuantity = intval($_POST['stock_quantity'] ?? 0);
        
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            return;
        }
        
        if ($stockQuantity < 0) {
            echo json_encode(['success' => false, 'message' => 'Stock quantity cannot be negative']);
            return;
        }
        
        $conn = getConnection();
        
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$stockQuantity, $productId]);
        
        if ($stmt->rowCount() > 0) {
           echo json_encode(['success' => true, 'message' => 'Stock updated successfully']);
       } else {
           echo json_encode(['success' => false, 'message' => 'Product not found']);
       }
       
   } catch (Exception $e) {
       error_log("Error in handleUpdateStock: " . $e->getMessage());
       echo json_encode(['success' => false, 'message' => 'Failed to update stock']);
   }
}

// ================================
// GET CATEGORIES
// ================================
function handleGetCategories() {
   try {
       $categories = getAllCategories('active'); // This function exists in functions.php
       
       echo json_encode([
           'success' => true,
           'categories' => $categories
       ]);
       
   } catch (Exception $e) {
       error_log("Error in handleGetCategories: " . $e->getMessage());
       echo json_encode(['success' => false, 'message' => 'Failed to load categories']);
   }
}

// ================================
// GET STATISTICS
// ================================
function handleGetStats() {
   try {
       $conn = getConnection();
       
       // Get product statistics
       $stmt = $conn->query("
           SELECT 
               COUNT(*) as total_products,
               SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
               SUM(CASE WHEN stock_quantity <= low_stock_threshold AND status = 'active' THEN 1 ELSE 0 END) as low_stock_products,
               SUM(CASE WHEN status = 'out_of_stock' OR stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_products,
               SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured_products
           FROM products
       ");
       
       $stats = $stmt->fetch();
       
       // Ensure all values are integers
       foreach ($stats as $key => $value) {
           $stats[$key] = intval($value);
       }
       
       echo json_encode([
           'success' => true,
           'stats' => $stats
       ]);
       
   } catch (Exception $e) {
       error_log("Error in handleGetStats: " . $e->getMessage());
       echo json_encode(['success' => false, 'message' => 'Failed to load statistics']);
   }
}

// ================================
// BULK OPERATIONS
// ================================
function handleBulkUpdate() {
   try {
       $action = $_POST['bulk_action'] ?? '';
       $productIds = $_POST['product_ids'] ?? [];
       
       if (empty($action) || empty($productIds) || !is_array($productIds)) {
           echo json_encode(['success' => false, 'message' => 'Invalid bulk action parameters']);
           return;
       }
       
       $conn = getConnection();
       $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
       
       switch ($action) {
           case 'activate':
               $stmt = $conn->prepare("UPDATE products SET status = 'active' WHERE id IN ({$placeholders})");
               $stmt->execute($productIds);
               $message = 'Products activated successfully';
               break;
               
           case 'deactivate':
               $stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id IN ({$placeholders})");
               $stmt->execute($productIds);
               $message = 'Products deactivated successfully';
               break;
               
           case 'feature':
               $stmt = $conn->prepare("UPDATE products SET featured = 1 WHERE id IN ({$placeholders})");
               $stmt->execute($productIds);
               $message = 'Products marked as featured successfully';
               break;
               
           case 'unfeature':
               $stmt = $conn->prepare("UPDATE products SET featured = 0 WHERE id IN ({$placeholders})");
               $stmt->execute($productIds);
               $message = 'Products unmarked as featured successfully';
               break;
               
           default:
               echo json_encode(['success' => false, 'message' => 'Invalid bulk action']);
               return;
       }
       
       echo json_encode([
           'success' => true,
           'message' => $message,
           'affected_count' => $stmt->rowCount()
       ]);
       
   } catch (Exception $e) {
       error_log("Error in handleBulkUpdate: " . $e->getMessage());
       echo json_encode(['success' => false, 'message' => 'Failed to perform bulk operation']);
   }
}

// ================================
// IMAGE UPLOAD HANDLER
// ================================
function handleProductImages($productId, $files) {
   try {
       $uploadDir = "../../uploads/products/";
       
       // Create upload directory if it doesn't exist
       if (!is_dir($uploadDir)) {
           if (!mkdir($uploadDir, 0755, true)) {
               return ['success' => false, 'message' => 'Failed to create upload directory'];
           }
       }
       
       $conn = getConnection();
       $uploadedImages = [];
       $maxImages = 5;
       $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
       $maxFileSize = 5 * 1024 * 1024; // 5MB
       
       // Check current image count
       $stmt = $conn->prepare("SELECT COUNT(*) as count FROM product_images WHERE product_id = ?");
       $stmt->execute([$productId]);
       $currentCount = $stmt->fetch()['count'];
       
       $filesToUpload = min($maxImages - $currentCount, count($files['name']));
       
       if ($filesToUpload <= 0) {
           return ['success' => false, 'message' => 'Maximum images limit reached'];
       }
       
       for ($i = 0; $i < $filesToUpload; $i++) {
           if ($files['error'][$i] !== UPLOAD_ERR_OK) {
               continue; // Skip files with upload errors
           }
           
           // Validate file type
           $fileType = $files['type'][$i];
           if (!in_array($fileType, $allowedTypes)) {
               continue; // Skip invalid file types
           }
           
           // Validate file size
           if ($files['size'][$i] > $maxFileSize) {
               continue; // Skip files that are too large
           }
           
           // Generate unique filename
           $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
           $filename = 'product_' . $productId . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
           $filepath = $uploadDir . $filename;
           
           // Upload file
           if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
               // Insert into database
               $imageUrl = '/uploads/products/' . $filename;
               $isPrimary = ($currentCount == 0 && $i == 0) ? 1 : 0; // First image is primary
               
               $stmt = $conn->prepare("
                   INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary) 
                   VALUES (?, ?, ?, ?, ?)
               ");
               $stmt->execute([
                   $productId,
                   $imageUrl,
                   "Product image " . ($currentCount + $i + 1),
                   $currentCount + $i + 1,
                   $isPrimary
               ]);
               
               $uploadedImages[] = [
                   'id' => $conn->lastInsertId(),
                   'url' => $imageUrl,
                   'is_primary' => $isPrimary
               ];
           }
       }
       
       if (empty($uploadedImages)) {
           return ['success' => false, 'message' => 'No images were uploaded successfully'];
       }
       
       return [
           'success' => true,
           'message' => count($uploadedImages) . ' images uploaded successfully',
           'images' => $uploadedImages
       ];
       
   } catch (Exception $e) {
       error_log("Error in handleProductImages: " . $e->getMessage());
       return ['success' => false, 'message' => 'Image upload failed: ' . $e->getMessage()];
   }
}

// ================================
// ADDITIONAL UTILITY FUNCTIONS
// ================================
function sanitizeFilename($filename) {
   // Remove any character that is not alphanumeric, dash, underscore, or dot
   $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
   
   // Remove multiple consecutive dots
   $filename = preg_replace('/\.+/', '.', $filename);
   
   // Ensure filename is not empty and has reasonable length
   if (empty($filename) || strlen($filename) > 255) {
       $filename = 'product_image_' . time() . '.jpg';
   }
   
   return $filename;
}

function logAdminProductAction($action, $productId, $details = '') {
   try {
       $adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
       $timestamp = date('Y-m-d H:i:s');
       
       error_log("ADMIN PRODUCT ACTION - Admin ID: {$adminId}, Action: {$action}, Product ID: {$productId}, Details: {$details}, Time: {$timestamp}");
       
       // You can enhance this to write to a dedicated admin_logs table
       
   } catch (Exception $e) {
       error_log("Error logging admin action: " . $e->getMessage());
   }
}

?>