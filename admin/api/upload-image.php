<?php
// admin/api/upload-image.php - Universal Image Upload API
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../admin-session.php';

// CRITICAL: Admin authentication check
checkAdminAuth();

// Set content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check admin authentication
checkAdminAuth();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $uploadType = $_POST['type'] ?? 'general';
    
    switch ($uploadType) {
        case 'product':
            handleProductImageUpload();
            break;
            
        case 'category':
            handleCategoryImageUpload();
            break;
            
        case 'general':
        default:
            handleGeneralImageUpload();
            break;
    }
    
} catch (Exception $e) {
    error_log("Image Upload API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
}

// ================================
// PRODUCT IMAGE UPLOAD
// ================================
function handleProductImageUpload() {
    try {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No image file uploaded or upload error']);
            return;
        }
        
        $uploadResult = uploadImage($_FILES['image'], 'products');
        echo json_encode($uploadResult);
        
    } catch (Exception $e) {
        error_log("Error in handleProductImageUpload: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Product image upload failed']);
    }
}

// ================================
// CATEGORY IMAGE UPLOAD
// ================================
function handleCategoryImageUpload() {
    try {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No image file uploaded or upload error']);
            return;
        }
        
        $uploadResult = uploadImage($_FILES['image'], 'categories');
        echo json_encode($uploadResult);
        
    } catch (Exception $e) {
        error_log("Error in handleCategoryImageUpload: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Category image upload failed']);
    }
}

// ================================
// GENERAL IMAGE UPLOAD
// ================================
function handleGeneralImageUpload() {
    try {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No image file uploaded or upload error']);
            return;
        }
        
        $uploadResult = uploadImage($_FILES['image'], 'general');
        echo json_encode($uploadResult);
        
    } catch (Exception $e) {
        error_log("Error in handleGeneralImageUpload: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'General image upload failed']);
    }
}

// ================================
// UNIVERSAL IMAGE UPLOAD FUNCTION
// ================================
function uploadImage($file, $folder = 'general') {
    try {
        $uploadDir = "../../uploads/{$folder}/";
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create upload directory'];
            }
        }
        
        // Validate file
        $validation = validateImageFile($file);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Generate unique filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $folder . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Upload file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Create thumbnail if needed
            $thumbnailPath = null;
            if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                $thumbnailPath = createThumbnail($filepath, $uploadDir . 'thumb_' . $filename);
            }
            
            $imageUrl = "/uploads/{$folder}/" . $filename;
            $thumbnailUrl = $thumbnailPath ? "/uploads/{$folder}/thumb_" . $filename : null;
            
            return [
                'success' => true,
                'message' => 'Image uploaded successfully',
                'image_url' => $imageUrl,
                'thumbnail_url' => $thumbnailUrl,
                'filename' => $filename,
                'file_size' => $file['size'],
                'dimensions' => getImageDimensions($filepath)
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
        
    } catch (Exception $e) {
        error_log("Error in uploadImage: " . $e->getMessage());
        return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
    }
}

// ================================
// IMAGE VALIDATION
// ================================
function validateImageFile($file) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $maxWidth = 3000;
    $maxHeight = 3000;
    
    // Check file type
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.'];
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'message' => 'Invalid file extension.'];
    }
    
    // Check file size
    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }
    
    // Check image dimensions
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'Invalid image file.'];
    }
    
    if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
        return ['success' => false, 'message' => "Image too large. Maximum dimensions: {$maxWidth}x{$maxHeight}px"];
    }
    
    return ['success' => true];
}

// ================================
// CREATE THUMBNAIL
// ================================
function createThumbnail($sourcePath, $thumbnailPath, $width = 300, $height = 300) {
    try {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $sourceType = $imageInfo[2];
        
        // Create source image
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        // Calculate thumbnail dimensions (maintain aspect ratio)
        $aspectRatio = $sourceWidth / $sourceHeight;
        
        if ($aspectRatio > 1) {
            // Landscape
            $thumbWidth = $width;
            $thumbHeight = $width / $aspectRatio;
        } else {
            // Portrait
            $thumbHeight = $height;
            $thumbWidth = $height * $aspectRatio;
        }
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Preserve transparency for PNG and GIF
        if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $thumbnail, $sourceImage,
            0, 0, 0, 0,
            $thumbWidth, $thumbHeight,
            $sourceWidth, $sourceHeight
        );
        
        // Save thumbnail
        $result = false;
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($thumbnail, $thumbnailPath, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($thumbnail, $thumbnailPath, 8);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($thumbnail, $thumbnailPath, 85);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($thumbnail, $thumbnailPath);
                break;
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error creating thumbnail: " . $e->getMessage());
        return false;
    }
}

// ================================
// GET IMAGE DIMENSIONS
// ================================
function getImageDimensions($imagePath) {
    try {
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo) {
            return [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'type' => $imageInfo['mime']
            ];
        }
        return null;
    } catch (Exception $e) {
        error_log("Error getting image dimensions: " . $e->getMessage());
        return null;
    }
}

// ================================
// DELETE IMAGE
// ================================
function deleteImage($imagePath) {
    try {
        $fullPath = "../../" . ltrim($imagePath, '/');
        
        if (file_exists($fullPath)) {
            unlink($fullPath);
            
            // Also delete thumbnail if exists
            $dir = dirname($fullPath);
            $filename = basename($fullPath);
            $thumbnailPath = $dir . '/thumb_' . $filename;
            
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error deleting image: " . $e->getMessage());
        return false;
    }
}

?>