<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);     

// Set content type first thing
header('Content-Type: application/json; charset=utf-8');

// admin/api/manage-categories.php - COMPLETE Category Management API
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../admin-session.php';

// Add this after the require_once statements  
global $pdo;
if (!$pdo) {
    $pdo = getConnection();
}

checkAdminAuth();

// Set content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
        case 'get_categories':
            handleGetCategories();
            break;
            
        case 'get_category':
            handleGetCategory();
            break;
            
        case 'add_category':
            handleAddCategory();
            break;
            
        case 'update_category':
            handleUpdateCategory();
            break;
            
        case 'delete_category':
            handleDeleteCategory();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Admin Categories API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

// ================================
// GET ALL CATEGORIES
// ================================
function handleGetCategories() {
    try {
        $conn = getConnection();
        
        $stmt = $conn->query("
            SELECT 
                c.*,
                COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
            GROUP BY c.id 
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        
        $categories = $stmt->fetchAll();
        
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
// GET SINGLE CATEGORY
// ================================
function handleGetCategory() {
    try {
        $categoryId = intval($_GET['id'] ?? 0);
        
        if ($categoryId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
            return;
        }
        
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Category not found']);
            return;
        }
        
        $category = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'category' => $category
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetCategory: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load category']);
    }
}

// ================================
// ADD NEW CATEGORY
// ================================
function handleAddCategory() {
    try {
        if (empty($_POST['name'])) {
            echo json_encode(['success' => false, 'message' => 'Category name is required']);
            return;
        }
        
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Generate slug
        $slug = generateCategorySlug($_POST['name']);
        
        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleCategoryImageUpload($_FILES['image']);
            if ($uploadResult['success']) {
                $imagePath = $uploadResult['image_url'];
            } else {
                echo json_encode(['success' => false, 'message' => 'Image upload failed: ' . $uploadResult['message']]);
                return;
            }
        }
        
        // Collect inputs
        $hsn_code   = isset($_POST['hsn_code']) ? trim($_POST['hsn_code']) : null;
        $description = $_POST['description'] ?? '';
        $status     = $_POST['status'] ?? 'active';
        $sort_order = $_POST['sort_order'] ?? 0;

        // Insert category (only once!)
        $stmt = $conn->prepare("
            INSERT INTO categories 
            (name, slug, description, hsn_code, image, status, sort_order, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_POST['name'],
            $slug,
            $description,
            $hsn_code,
            $imagePath,
            $status,
            $sort_order
        ]);
        
        $categoryId = $conn->lastInsertId();
        
        if (!$categoryId) {
            throw new Exception("Failed to create category");
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Category added successfully',
            'category_id' => $categoryId
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log("Error in handleAddCategory: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}


// ================================
// UPDATE CATEGORY
// ================================
function handleUpdateCategory() {
    try {
        $categoryId = intval($_POST['category_id'] ?? 0);
        
        if ($categoryId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
            return;
        }
        
        if (empty($_POST['name'])) {
            echo json_encode(['success' => false, 'message' => 'Category name is required']);
            return;
        }
        
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Check if category exists
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Category not found']);
            return;
        }
        
        $currentCategory = $stmt->fetch();
        
        // Generate new slug if name changed
        $slug = ($_POST['name'] !== $currentCategory['name']) 
               ? generateCategorySlug($_POST['name'], $categoryId) 
               : $currentCategory['slug'];
        
        // Handle image upload
        $imagePath = $currentCategory['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleCategoryImageUpload($_FILES['image']);
            if ($uploadResult['success']) {
                // Delete old image if exists
                if ($currentCategory['image']) {
                    $oldImagePath = "../../uploads/categories/" . basename($currentCategory['image']);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $imagePath = $uploadResult['image_url'];
            } else {
                echo json_encode(['success' => false, 'message' => 'Image upload failed: ' . $uploadResult['message']]);
                return;
            }
        }
        
        // Collect input values
        $description = $_POST['description'] ?? '';
        $hsn_code   = $_POST['hsn_code'] ?? null;
        $status     = $_POST['status'] ?? 'active';
        $sort_order = $_POST['sort_order'] ?? 0;

        // Update category (with hsn_code added)
        $stmt = $conn->prepare("
            UPDATE categories 
            SET name = ?, slug = ?, description = ?, hsn_code = ?, image = ?, status = ?, sort_order = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['name'],
            $slug,
            $description,
            $hsn_code,
            $imagePath,
            $status,
            $sort_order,
            $categoryId
        ]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Category updated successfully'
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log("Error in handleUpdateCategory: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}


// ================================
// DELETE CATEGORY
// ================================
function handleDeleteCategory() {
    try {
        $categoryId = intval($_POST['category_id'] ?? 0);
        
        if ($categoryId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
            return;
        }
        
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Check if category exists
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Category not found']);
            return;
        }
        
        $category = $stmt->fetch();
        
        // Check if category has products
        $stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $productCount = $stmt->fetch()['product_count'];
        
        if ($productCount > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot delete category that has products. Please move or delete products first.'
            ]);
            return;
        }
        
        // Delete category
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        
        $conn->commit();
        
        // Delete image file if exists
        if ($category['image']) {
            $imagePath = "../../uploads/categories/" . basename($category['image']);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log("Error in handleDeleteCategory: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ================================
// CATEGORY IMAGE UPLOAD HANDLER (LOCAL TO THIS FILE ONLY)
// ================================
function handleCategoryImageUpload($file) {
    try {
        $uploadDir = "../../uploads/categories/";
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create upload directory'];
            }
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        
        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and WebP are allowed.'];
        }
        
        // Validate file size
        if ($file['size'] > $maxFileSize) {
            return ['success' => false, 'message' => 'File too large. Maximum size is 2MB.'];
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'category_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Upload file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $imageUrl = BASE_PATH . '/uploads/categories/' . $filename;
            
            return [
                'success' => true,
                'message' => 'Image uploaded successfully',
                'image_url' => $imageUrl
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to upload image'];
        }
        
    } catch (Exception $e) {
        error_log("Error in handleCategoryImageUpload: " . $e->getMessage());
        return ['success' => false, 'message' => 'Image upload failed: ' . $e->getMessage()];
    }
}

/**
 * Log admin category action
 * @param string $action Action performed
 * @param int $categoryId Category ID
 * @param string $details Action details
 */
function logAdminCategoryAction($action, $categoryId, $details = '') {
    try {
        $adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
        $timestamp = date('Y-m-d H:i:s');
        
        error_log("ADMIN CATEGORY ACTION - Admin ID: {$adminId}, Action: {$action}, Category ID: {$categoryId}, Details: {$details}, Time: {$timestamp}");
        
        // You can enhance this to write to a dedicated admin_logs table
        
    } catch (Exception $e) {
        error_log("Error logging admin category action: " . $e->getMessage());
    }
}

/**
 * Sanitize category filename
 * @param string $filename Original filename
 * @return string Sanitized filename
 */
function sanitizeCategoryFilename($filename) {
    // Remove any character that is not alphanumeric, dash, underscore, or dot
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Remove multiple consecutive dots
    $filename = preg_replace('/\.+/', '.', $filename);
    
    // Ensure filename is not empty and has reasonable length
    if (empty($filename) || strlen($filename) > 255) {
        $filename = 'category_image_' . time() . '.jpg';
    }
    
    return $filename;
}

/**
 * Check if category name exists
 * @param string $name Category name
 * @param int|null $excludeId Category ID to exclude from check
 * @return bool Name exists
 */
function categoryNameExists($name, $excludeId = null) {
    try {
        $conn = getConnection();
        
        $sql = "SELECT id FROM categories WHERE name = ?";
        $params = [$name];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
        
    } catch (Exception $e) {
        error_log("Error checking category name existence: " . $e->getMessage());
        return false;
    }
}

?>