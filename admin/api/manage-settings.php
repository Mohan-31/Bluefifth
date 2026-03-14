<?php
// Clean error handling - single implementation
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Set JSON response header
header('Content-Type: application/json');

// Single error handler to return proper JSON
set_exception_handler(function ($e) {
    error_log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()]);
    exit;
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "PHP Error: $errstr"]);
    exit;
});

// admin/api/manage-settings.php - Enhanced Settings Management API with Shiprocket Integration
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../admin-session.php';

// CRITICAL: Admin authentication check
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

function handleVerifyDatabase() {
    try {
        $success = verifySettingsTable();
        // Also ensure Shiprocket columns exist
        ensureShiprocketColumns();
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Database schema verified successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to verify database schema'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleVerifyDatabase: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database verification failed']);
    }
}

/**
 * Check and add missing Shiprocket columns to orders table
 * @return bool Success status
 */
function ensureShiprocketColumns() {
    try {
        $conn = getConnection();
        
        // Check existing columns
        $stmt = $conn->query("DESCRIBE orders");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Define required Shiprocket columns
        $requiredColumns = [
            'shiprocket_shipment_id' => 'VARCHAR(100) NULL',
            'shiprocket_order_id' => 'VARCHAR(100) NULL',
            'tracking_number' => 'VARCHAR(100) NULL',
            'courier_partner' => 'VARCHAR(100) NULL',
            'shipped_at' => 'DATETIME NULL',
            'delivered_at' => 'DATETIME NULL'
        ];
        
        // Add missing columns
        foreach ($requiredColumns as $column => $definition) {
            if (!in_array($column, $existingColumns)) {
                try {
                    $conn->exec("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
                    error_log("Added column {$column} to orders table");
                } catch (Exception $e) {
                    error_log("Column {$column} may already exist or error: " . $e->getMessage());
                }
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error ensuring Shiprocket columns: " . $e->getMessage());
        return false;
    }
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        // Core Settings
        case 'get_all_settings':
            handleGetAllSettings();
            break;
            
        case 'save_settings':
            handleSaveSettings();
            break;
            
        case 'reset_settings':
            handleResetSettings();
            break;
            
        case 'toggle_maintenance_mode':
            handleToggleMaintenanceMode();
            break;
            
        // Email Actions (Preserved)
        case 'get_email_stats':
            handleGetEmailStats();
            break;
            
        case 'send_test_email':
            handleSendTestEmail();
            break;
            
        case 'send_bulk_email':
            handleSendBulkEmail();
            break;
            
        // Shiprocket Integration Actions
        case 'test_shiprocket_connection':
            handleTestShiprocketConnection();
            break;
            
        case 'get_shiprocket_couriers':
            handleGetShiprocketCouriers();
            break;
            
        case 'calculate_shipping_rates':
            handleCalculateShippingRates();
            break;
            
        case 'sync_tracking_data':
            handleSyncTrackingData();
            break;
            
        case 'get_shipping_stats':
            handleGetShippingStats();
            break;
            
        case 'check_serviceability':
            handleCheckServiceability();
            break;
            
        case 'generate_shipping_label':
            handleGenerateShippingLabel();
            break;
            
        // New Shiprocket Functions
        case 'get_pickup_locations':
            handleGetPickupLocations();
            break;
            
        case 'cancel_shipment':
            handleCancelShipment();
            break;
            
        case 'generate_awb':
            handleGenerateAWB();
            break;
            
        case 'update_pickup_address':
            handleUpdatePickupAddress();
            break;

        // Order Management
        case 'get_order_details':
            handleGetOrderDetails();
            break;
        
        case 'track_order':
            handleTrackOrder();
            break;
            
        // System Management
        case 'verify_database':
            handleVerifyDatabase();
            break;
            
        case 'initialize_defaults':
            $success = initializeCompleteDefaultSettings();
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Default settings initialized successfully' : 'Failed to initialize defaults'
            ]);
            break;
            
        case 'export_settings':
            handleExportSettings();
            break;
            
        case 'import_settings':
            handleImportSettings();
            break;
            
        case 'validate_settings':
            handleValidateSettings();
            break;
            
        case 'get_settings_info':
            handleGetSettingsInfo();
            break;

        // Maintenance Operations
        case 'system_maintenance':
            handleSystemMaintenance();
            break;
            
        case 'system_health_check':
            handleSystemHealthCheck();
            break;
            
        case 'create_backup':
            handleCreateBackup();
            break;
            
        case 'repair_settings':
            handleRepairSettings();
            break;
            
        case 'clean_sensitive_data':
            handleCleanSensitiveData();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
    
} catch (Exception $e) {
    error_log("Admin Settings API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

// ================================
// EXISTING FUNCTIONS (PRESERVED)
// ================================

function handleGetAllSettings() {
    try {
        $settings = getAllSettings();
        
        // Add Sendinblue settings from email-config.php if they exist
        $emailConfigPath = '../../includes/email-config.php';
        if (file_exists($emailConfigPath)) {
            $emailConfig = include $emailConfigPath;
            
            if (!isset($settings['sendinblue_api_key']) && isset($emailConfig['sendinblue']['api_key'])) {
                $settings['sendinblue_api_key'] = $emailConfig['sendinblue']['api_key'];
            }
            if (!isset($settings['sendinblue_from_email']) && isset($emailConfig['sendinblue']['from_email'])) {
                $settings['sendinblue_from_email'] = $emailConfig['sendinblue']['from_email'];
            }
            if (!isset($settings['sendinblue_from_name']) && isset($emailConfig['sendinblue']['from_name'])) {
                $settings['sendinblue_from_name'] = $emailConfig['sendinblue']['from_name'];
            }
            if (!isset($settings['email_test_mode']) && isset($emailConfig['settings']['test_mode'])) {
                $settings['email_test_mode'] = $emailConfig['settings']['test_mode'] ? 'true' : 'false';
            }
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetAllSettings: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load settings']);
    }
}

function handleSaveSettings() {
    try {
        $settingsJson = $_POST['settings'] ?? '';
        
        if (empty($settingsJson)) {
            echo json_encode(['success' => false, 'message' => 'No settings data provided']);
            return;
        }
        
        // FIXED: Properly decode JSON
        $settings = json_decode($settingsJson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonError = json_last_error_msg();
            error_log("JSON Decode Error in handleSaveSettings: " . $jsonError);
            echo json_encode(['success' => false, 'message' => 'Invalid settings data format: ' . $jsonError]);
            return;
        }
        
        // ✅ ADDED: Log the original maintenance_mode value before validation
        if (isset($settings['maintenance_mode'])) {
            error_log("handleSaveSettings: Original maintenance_mode = " . ($settings['maintenance_mode'] ? 'true' : 'false'));
        }
        
        // ✅ ADDED: Validate ALL settings categories (not just shipping)
        $settings = validateAllSettings($settings);
        
        // ✅ ADDED: Log the maintenance_mode value after validation
        if (isset($settings['maintenance_mode'])) {
            error_log("handleSaveSettings: After validation maintenance_mode = " . ($settings['maintenance_mode'] ? 'true' : 'false'));
        }
        
        // ✅ ADDED: Check for validation errors
        if (isset($settings['_all_validation_errors'])) {
            echo json_encode([
                'success' => false, 
                'message' => 'Validation failed',
                'errors' => $settings['_all_validation_errors']
            ]);
            return;
        }
        
        $conn = getConnection();
        
        // ✅ ADDED: Transaction safety - all or nothing approach
        $conn->beginTransaction();
        
        try {
            $savedCount = 0;
            $errors = [];
            
            // Special handling for email and Shiprocket settings
            $updateEmailConfig = false;
            $emailConfigData = [];
            
            foreach ($settings as $key => $value) {
                // ✅ ADDED: Skip internal validation markers
                if (strpos($key, '_validation_') === 0) {
                    continue;
                }
                
                try {
                    // ✅ FIXED: Special handling for maintenance_mode
                    if ($key === 'maintenance_mode') {
                        $type = 'boolean';
                        // Convert to actual boolean for setSetting
                        $boolValue = ($value === true || $value === 'true' || $value === 1 || $value === '1');
                        error_log("handleSaveSettings: Processing maintenance_mode - original: " . var_export($value, true) . ", converted: " . ($boolValue ? 'true' : 'false'));
                        
                        if (setSetting($key, $boolValue, $type)) {
                            $savedCount++;
                            error_log("handleSaveSettings: Successfully saved maintenance_mode = " . ($boolValue ? 'true' : 'false'));
                            
                            // Verify it was saved correctly
                            $verifyValue = getSetting('maintenance_mode');
                            error_log("handleSaveSettings: Verification - maintenance_mode now = " . ($verifyValue ? 'true' : 'false'));
                        } else {
                            $errors[] = "Failed to save setting: {$key}";
                            error_log("handleSaveSettings: Failed to save maintenance_mode");
                        }
                        continue;
                    }
                    
                    // ✅ ORIGINAL: Standard type determination for other settings
                    $type = 'string';
                    if (is_bool($value)) {
                        $type = 'boolean';
                        $value = $value ? 'true' : 'false';
                    } elseif (is_numeric($value)) {
                        $type = 'number';
                    } elseif (is_array($value)) {
                        $type = 'json';
                        $value = json_encode($value);
                    }
                    
                    // ✅ FIXED: Track email settings for config file update (added missing 'email_notifications')
                    if (in_array($key, ['sendinblue_api_key', 'sendinblue_from_email', 'sendinblue_from_name', 'email_test_mode', 'email_notifications', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'])) {
                        $updateEmailConfig = true;
                        $emailConfigData[$key] = $value;
                    }
                    
                    // Save setting to database
                    if (setSetting($key, $value, $type)) {
                        $savedCount++;
                    } else {
                        $errors[] = "Failed to save setting: {$key}";
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error saving {$key}: " . $e->getMessage();
                }
            }
            
            // ✅ ADDED: Only commit if we have successful saves and no critical errors
            if ($savedCount > 0 && empty($errors)) {
                $conn->commit();
                
                // Update email-config.php if needed (only after successful database commit)
                if ($updateEmailConfig) {
                    try {
                        updateEmailConfigFile($emailConfigData);
                    } catch (Exception $e) {
                        $errors[] = "Warning: Email config file update failed: " . $e->getMessage();
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => "Successfully saved {$savedCount} settings",
                    'saved_count' => $savedCount,
                    'errors' => $errors
                ]);
                
            } else {
                // ✅ ADDED: Rollback if no settings saved or critical errors
                $conn->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'No settings were saved or critical errors occurred',
                    'errors' => $errors
                ]);
            }
            
        } catch (Exception $e) {
            // ✅ ADDED: Rollback on any database error
            $conn->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Error in handleSaveSettings: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $e->getMessage()]);
    }
}

/**
 * ✅ REQUIRED: Add these specific validation functions:
 */
function validateGeneralSettings($settings) {
    $errors = [];
    
    // Validate required fields
    if (empty($settings['site_name'])) {
        $errors[] = 'Site name is required';
    }
    
    if (!empty($settings['contact_email']) && !filter_var($settings['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format for contact email';
    }
    
    if (!empty($settings['contact_phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $settings['contact_phone']);
        if (strlen($phone) < 10) {
            $errors[] = 'Contact phone must be at least 10 digits';
        }
    }
    
    return $errors;
}

function validatePaymentSettings($settings) {
    $errors = [];
    
    // Validate numeric fields
    $numericFields = ['tax_rate', 'shipping_charge', 'free_shipping_threshold', 'min_order_amount', 'cod_charges'];
    
    foreach ($numericFields as $field) {
        if (isset($settings[$field]) && $settings[$field] !== '') {
            if (!is_numeric($settings[$field]) || floatval($settings[$field]) < 0) {
                $errors[] = "Field {$field} must be a positive number";
            }
        }
    }
    
    // Validate tax rate range
    if (isset($settings['tax_rate']) && is_numeric($settings['tax_rate'])) {
        if (floatval($settings['tax_rate']) > 100) {
            $errors[] = 'Tax rate cannot exceed 100%';
        }
    }
    
    // Validate Razorpay key format
    if (!empty($settings['razorpay_key_id'])) {
        if (!preg_match('/^rzp_(test_|live_)[a-zA-Z0-9]{14}$/', $settings['razorpay_key_id'])) {
            $errors[] = 'Invalid Razorpay Key ID format';
        }
    }
    
    return $errors;
}

function validateSystemSettings($settings) {
    $errors = [];
    
    // Validate integer constraints
    $integerFields = [
        'items_per_page' => ['min' => 5, 'max' => 100],
        'low_stock_threshold' => ['min' => 0, 'max' => 1000],
        'featured_products_limit' => ['min' => 1, 'max' => 50],
        'related_products_limit' => ['min' => 1, 'max' => 20],
        'session_timeout' => ['min' => 15, 'max' => 1440]
    ];
    
    foreach ($integerFields as $field => $constraints) {
        if (isset($settings[$field]) && $settings[$field] !== '') {
            $value = intval($settings[$field]);
            if ($value < $constraints['min'] || $value > $constraints['max']) {
                $errors[] = "Field {$field} must be between {$constraints['min']} and {$constraints['max']}";
            }
        }
    }
    
    return $errors;
}

function validateReferralSettings($settings) {
    $errors = [];
    
    // Validate rate percentages
    $rateFields = ['first_month_rate', 'other_months_rate'];
    
    foreach ($rateFields as $field) {
        if (isset($settings[$field]) && $settings[$field] !== '') {
            $value = floatval($settings[$field]);
            if ($value < 0 || $value > 100) {
                $errors[] = "Field {$field} must be between 0 and 100";
            }
        }
    }
    
    // Validate minimum points to claim
    if (isset($settings['min_points_to_claim']) && $settings['min_points_to_claim'] !== '') {
        $value = intval($settings['min_points_to_claim']);
        if ($value < 1) {
            $errors[] = 'Minimum points to claim must be at least 1';
        }
    }
    
    // Validate referral code length
    if (isset($settings['referral_code_length']) && $settings['referral_code_length'] !== '') {
        $value = intval($settings['referral_code_length']);
        if ($value < 4 || $value > 10) {
            $errors[] = 'Referral code length must be between 4 and 10';
        }
    }
    
    return $errors;
}

/**
 * Validate and process shipping-specific settings
 * @param array $settings Settings array
 * @return array Processed settings
 */
function processShippingSettings($settings) {
    $processedSettings = $settings;
    
    // Validate Shiprocket settings
    if (isset($settings['shiprocket_enabled']) && $settings['shiprocket_enabled'] === true) {
        // Ensure required Shiprocket fields are present
        $requiredFields = ['shiprocket_email', 'shiprocket_password'];
        foreach ($requiredFields as $field) {
            if (empty($settings[$field])) {
                error_log("Warning: Shiprocket enabled but {$field} is missing");
            }
        }
        
        // Test connection if credentials provided
        if (!empty($settings['shiprocket_email']) && !empty($settings['shiprocket_password'])) {
            $token = refreshShiprocketToken($settings['shiprocket_email'], $settings['shiprocket_password']);
            if ($token) {
                $processedSettings['shiprocket_api_token'] = $token;
                $processedSettings['shiprocket_token_expiry'] = date('Y-m-d H:i:s', time() + (10 * 24 * 60 * 60));
                error_log("Shiprocket token refreshed successfully during settings save");
            }
        }
    }
    
    // Set default shipping values if missing
    $defaults = [
        'default_shipping_method' => 'standard',
        'estimated_delivery_days' => '3-7'
    ];
    
    foreach ($defaults as $key => $defaultValue) {
        if (!isset($processedSettings[$key])) {
            $processedSettings[$key] = $defaultValue;
        }
    }
    
    return $processedSettings;
}

/**
 * Safe courier handler - PREVENTS 500 ERRORS
 */
function handleGetShiprocketCouriers() {
    try {
        error_log("=== HANDLING GET SHIPROCKET COURIERS ===");
        
        $token = getSetting('shiprocket_api_token');
        
        if (empty($token)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Shiprocket not connected. Please test connection first.',
                'couriers' => [],
                'needs_connection' => true
            ]);
            return;
        }
        
        // Check token expiry
        $tokenExpiry = getSetting('shiprocket_token_expiry');
        if ($tokenExpiry && strtotime($tokenExpiry) < time()) {
            $email = getSetting('shiprocket_email');
            $password = getSetting('shiprocket_password');
            
            if ($email && $password && function_exists('refreshShiprocketToken')) {
                $newToken = refreshShiprocketToken($email, $password);
                if ($newToken) {
                    $token = $newToken;
                    error_log("Token refreshed successfully");
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Token expired and refresh failed. Please reconnect.',
                        'couriers' => [],
                        'needs_connection' => true
                    ]);
                    return;
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Token expired. Please reconnect.',
                    'couriers' => [],
                    'needs_connection' => true
                ]);
                return;
            }
        }
        
        // Try to get couriers
        $couriers = getShiprocketCouriers($token);
        
        // If primary method returns empty, try fallback
        if (empty($couriers)) {
            error_log("Primary method returned no couriers, trying fallback");
            $couriers = getShiprocketCouriersViaServiceability($token);
        }
        
        if (!empty($couriers) && is_array($couriers)) {
            echo json_encode([
                'success' => true,
                'couriers' => $couriers,
                'total_count' => count($couriers)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No couriers available. API may be down or account needs configuration.',
                'couriers' => [],
                'api_error' => true
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Exception in handleGetShiprocketCouriers: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Server error while fetching couriers',
            'couriers' => [],
            'api_error' => true
        ]);
    } catch (Error $e) {
        error_log("Fatal error in handleGetShiprocketCouriers: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Server error while fetching couriers',
            'couriers' => [],
            'api_error' => true
        ]);
    }
}

/**
 * Get shipping statistics
 */
function handleGetShippingStats() {
    try {
        $conn = getConnection();
        
        // Safe query that works with basic orders table
        $statsQuery = "
            SELECT 
                COUNT(*) as total_shipments,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_shipments,
                SUM(CASE WHEN status IN ('shipped', 'processing') THEN 1 ELSE 0 END) as transit_shipments,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_shipments
            FROM orders 
            WHERE status IN ('delivered', 'shipped', 'processing', 'pending')
        ";
        
        $stmt = $conn->prepare($statsQuery);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get recent shipments safely
        $recentShipments = 0;
        try {
            $metricsQuery = "
                SELECT COUNT(*) as shipments_last_30_days
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND status IN ('delivered', 'shipped', 'processing', 'pending')
            ";
            $stmt = $conn->prepare($metricsQuery);
            $stmt->execute();
            $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
            $recentShipments = intval($metrics['shipments_last_30_days'] ?? 0);
        } catch (Exception $e) {
            error_log("Recent shipments query failed: " . $e->getMessage());
        }
        
        // Always return valid JSON
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_shipments' => intval($stats['total_shipments'] ?? 0),
                'delivered_shipments' => intval($stats['delivered_shipments'] ?? 0),
                'transit_shipments' => intval($stats['transit_shipments'] ?? 0),
                'pending_shipments' => intval($stats['pending_shipments'] ?? 0),
                'avg_delivery_days' => 3.5,
                'recent_shipments' => $recentShipments
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetShippingStats: " . $e->getMessage());
        // Return valid JSON with default values
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_shipments' => 0,
                'delivered_shipments' => 0,
                'transit_shipments' => 0,
                'pending_shipments' => 0,
                'avg_delivery_days' => 0,
                'recent_shipments' => 0
            ]
        ]);
    }
}

/**
 * Check pincode serviceability
 */
function handleCheckServiceability() {
    try {
        $pincode = $_GET['pincode'] ?? '';
        
        if (empty($pincode)) {
            echo json_encode(['success' => false, 'message' => 'Pincode required']);
            return;
        }
        
        $token = getSetting('shiprocket_api_token');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Shiprocket not connected']);
            return;
        }
        
        $pickupPincode = getSetting('pickup_pincode', '400001');
        $testWeight = 1.0; // Default test weight
        
        $serviceability = checkPincodeServiceability($token, $pickupPincode, $pincode, $testWeight);
        
        if ($serviceability) {
            echo json_encode([
                'success' => true,
                'serviceable' => $serviceability['serviceable'] ?? false,
                'available_couriers' => $serviceability['couriers'] ?? [],
                'estimated_delivery_days' => $serviceability['estimated_days'] ?? 'N/A'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'serviceable' => false,
                'message' => 'Unable to check serviceability'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleCheckServiceability: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'serviceable' => false,
            'message' => 'Error checking serviceability'
        ]);
    }
}

/**
 * Generate shipping label (placeholder for future implementation)
 */
function handleGenerateShippingLabel() {
    try {
        $orderId = intval($_POST['order_id'] ?? 0);
        
        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            return;
        }
        
        $token = getSetting('shiprocket_api_token');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Shiprocket not connected']);
            return;
        }
        
        // For now, return a placeholder response
        // In full implementation, this would create actual Shiprocket shipment
        echo json_encode([
            'success' => true,
            'message' => 'Label generation feature coming soon',
            'awb_code' => 'TEST' . time(),
            'shipment_id' => 'SHIP' . time(),
            'label_url' => '#'
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGenerateShippingLabel: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error generating shipping label'
        ]);
    }
}

/**
 * Fixed getShiprocketCouriers function - USING CORRECT ENDPOINTS
 */
function getShiprocketCouriers($token) {
    try {
        error_log("=== DEBUGGING SHIPROCKET COURIERS API ===");
        error_log("Token (first 20 chars): " . substr($token, 0, 20) . "...");
        
        // Try the correct Shiprocket API endpoints (based on official documentation)
        $endpoints = [
            "https://apiv2.shiprocket.in/v1/external/courier/courierListWithCounts",
            "https://apiv2.shiprocket.in/v1/external/courier/list", 
            "https://apiv2.shiprocket.in/v1/external/courier/courierList"
        ];
        
        foreach ($endpoints as $index => $url) {
            error_log("Trying endpoint " . ($index + 1) . ": " . $url);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            error_log("Endpoint " . ($index + 1) . " - HTTP Code: " . $httpCode);
            error_log("Endpoint " . ($index + 1) . " - Response (first 200 chars): " . substr($response, 0, 200));
            
            if ($error) {
                error_log("Endpoint " . ($index + 1) . " - cURL error: " . $error);
                continue;
            }
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("Endpoint " . ($index + 1) . " - JSON decode error: " . json_last_error_msg());
                    continue;
                }
                
                error_log("Endpoint " . ($index + 1) . " - SUCCESS! Response keys: " . implode(', ', array_keys($result)));
                
                // Extract couriers from different possible response structures
                $couriers = [];
                
                if (isset($result['data']) && is_array($result['data'])) {
                    $couriers = $result['data'];
                } elseif (isset($result['courier_data']) && is_array($result['courier_data'])) {
                    $couriers = $result['courier_data'];
                } elseif (isset($result['couriers']) && is_array($result['couriers'])) {
                    $couriers = $result['couriers'];
                } elseif (is_array($result) && isset($result[0])) {
                    // Direct array of couriers
                    $couriers = $result;
                }
                
                if (!empty($couriers)) {
                    error_log("Found " . count($couriers) . " couriers in response");
                    
                    if (isset($couriers[0])) {
                        error_log("Sample courier keys: " . implode(', ', array_keys($couriers[0])));
                    }
                    
                    $formattedCouriers = [];
                    
                    foreach ($couriers as $courier) {
                        if (!is_array($courier)) continue;
                        
                        $formattedCouriers[] = [
                            'id' => $courier['id'] ?? $courier['courier_id'] ?? $courier['courier_company_id'] ?? null,
                            'courier_name' => $courier['courier_name'] ?? $courier['name'] ?? $courier['company_name'] ?? 'Unknown Courier',
                            'courier_type' => $courier['courier_type'] ?? $courier['type'] ?? 'Standard',
                            'is_surface' => $courier['is_surface'] ?? false,
                            'is_air' => $courier['is_air'] ?? true,
                            'pickup_available' => $courier['pickup_available'] ?? true,
                            'delivery_available' => $courier['delivery_available'] ?? true,
                            'cod_available' => $courier['cod_available'] ?? $courier['cod'] ?? false,
                            'prepaid_available' => $courier['prepaid_available'] ?? true,
                            'rating' => $courier['rating'] ?? 0,
                            'description' => $courier['description'] ?? ''
                        ];
                    }
                    
                    error_log("Successfully formatted " . count($formattedCouriers) . " couriers");
                    return $formattedCouriers;
                } else {
                    error_log("Endpoint " . ($index + 1) . " - Response has no courier data");
                }
                
            } else {
                error_log("Endpoint " . ($index + 1) . " - Failed with HTTP " . $httpCode);
                if ($httpCode === 401) {
                    error_log("Authentication failed - stopping attempts");
                    return [];
                }
            }
        }
        
        error_log("All courier endpoints failed");
        return [];
        
    } catch (Exception $e) {
        error_log("Exception in getShiprocketCouriers: " . $e->getMessage());
        return [];
    }
}

/**
 * Fixed serviceability function - CORRECT PARAMETERS
 */
function getShiprocketCouriersViaServiceability($token) {
    try {
        error_log("=== TRYING SERVICEABILITY FALLBACK ===");
        
        // Get pickup location details (required for serviceability check)
        $pickupPincode = getSetting('pickup_pincode', '400001');
        $testDeliveryPincode = '110001';
        
        // Serviceability API needs order details
        $url = "https://apiv2.shiprocket.in/v1/external/courier/serviceability/";
        
        $params = http_build_query([
            'pickup_postcode' => $pickupPincode,
            'delivery_postcode' => $testDeliveryPincode,
            'weight' => 1,  // 1 kg
            'cod' => 0,     // 0 for prepaid, 1 for COD
            'declared_value' => 1000  // Order value
        ]);
        
        $fullUrl = $url . '?' . $params;
        error_log("Serviceability URL: " . $fullUrl);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("Serviceability HTTP Code: " . $httpCode);
        error_log("Serviceability Response (first 200 chars): " . substr($response, 0, 200));
        
        if ($error) {
            error_log("Serviceability cURL error: " . $error);
            return [];
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Serviceability JSON error: " . json_last_error_msg());
                return [];
            }
            
            if (isset($result['data']['available_courier_companies'])) {
                $courierData = $result['data']['available_courier_companies'];
                error_log("Serviceability found " . count($courierData) . " couriers");
                
                $couriers = [];
                foreach ($courierData as $courier) {
                    if (!is_array($courier)) continue;
                    
                    $couriers[] = [
                        'id' => $courier['courier_company_id'] ?? $courier['id'],
                        'courier_name' => $courier['courier_name'] ?? $courier['name'] ?? 'Unknown',
                        'courier_type' => $courier['courier_type'] ?? 'Standard',
                        'is_surface' => $courier['is_surface'] ?? false,
                        'is_air' => !($courier['is_surface'] ?? false),
                        'pickup_available' => true,
                        'delivery_available' => true,
                        'cod_available' => $courier['cod'] ?? false,
                        'prepaid_available' => true,
                        'rating' => $courier['rating'] ?? 0,
                        'description' => $courier['description'] ?? ''
                    ];
                }
                
                return $couriers;
            } else {
                error_log("No available_courier_companies in serviceability response");
            }
        } else {
            error_log("Serviceability failed with HTTP " . $httpCode);
        }
        
        return [];
        
    } catch (Exception $e) {
        error_log("Serviceability exception: " . $e->getMessage());
        return [];
    }
}

/**
 * Check pincode serviceability
 */
function checkPincodeServiceability($token, $fromPincode, $toPincode, $weight = 1.0) {
    try {
        $url = "https://apiv2.shiprocket.in/v1/external/courier/serviceability/";
        
        $params = http_build_query([
            'pickup_postcode' => $fromPincode,
            'delivery_postcode' => $toPincode,
            'weight' => $weight,
            'cod' => 0
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            if (isset($result['data']['available_courier_companies'])) {
                return [
                    'serviceable' => count($result['data']['available_courier_companies']) > 0,
                    'couriers' => $result['data']['available_courier_companies'],
                    'estimated_days' => $result['data']['delivery_estimate'] ?? 'N/A'
                ];
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error checking serviceability: " . $e->getMessage());
        return null;
    }
}

/**
 * Send tracking SMS (placeholder - requires SMS gateway integration)
 */
function sendTrackingSMS($phone, $orderNumber, $trackingNumber, $status) {
    // This is a placeholder. You would integrate with SMS gateways like:
    // - Twilio
    // - TextLocal
    // - MSG91
    // - AWS SNS
    
    try {
        // Example message format
        $message = "Order #{$orderNumber} status: {$status}. Track: {$trackingNumber}. - Velona";
        
        // Log the SMS for now (replace with actual SMS API)
        error_log("SMS would be sent to {$phone}: {$message}");
        
        // Return true for now (implement actual SMS sending)
        return true;
        
    } catch (Exception $e) {
        error_log("SMS sending error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send tracking email (uses existing email system)
 */
function sendTrackingEmail($email, $customerName, $orderNumber, $trackingNumber, $status) {
    try {
        $subject = "Order #{$orderNumber} Update - {$status}";
        
        $message = "
        <h2>Order Status Update</h2>
        <p>Dear {$customerName},</p>
        <p>Your order <strong>#{$orderNumber}</strong> status has been updated:</p>
        <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <h3 style='color: #28a745;'>Status: {$status}</h3>
            <p><strong>Tracking Number:</strong> {$trackingNumber}</p>
        </div>
        <p>You can track your order using the tracking number above.</p>
        <p>Thank you for shopping with us!</p>
        <p>Best regards,<br>Velona Team</p>
        ";
        
        return sendEmailNotification($email, $subject, strip_tags($message), 'tracking_update', $message, $customerName);
        
    } catch (Exception $e) {
        error_log("Tracking email error: " . $e->getMessage());
        return false;
    }
}


function createBasicTrackingHistory($order, $additionalData = []) {
    $history = [];
    
    // Order placed
    if ($order['created_at']) {
        $history[] = [
            'date' => $order['created_at'],
            'status' => 'Order Placed',
            'location' => 'Online',
            'description' => 'Order has been placed successfully'
        ];
    }
    
    // Order shipped
    if (!empty($additionalData['shipped_at'])) {
        $history[] = [
            'date' => $additionalData['shipped_at'],
            'status' => 'Shipped',
            'location' => 'Warehouse',
            'description' => 'Order has been shipped'
        ];
    }
    
    // Order delivered
    if (!empty($additionalData['delivered_at'])) {
        $history[] = [
            'date' => $additionalData['delivered_at'],
            'status' => 'Delivered',
            'location' => 'Destination',
            'description' => 'Order has been delivered successfully'
        ];
    }
    
    return $history;
}

/**
 * Validate and process general settings
 * @param array $settings Settings array
 * @return array Processed settings with validation
 */
function processGeneralSettings($settings) {
    $errors = [];
    $processedSettings = $settings;
    
    // Validate required fields
    $requiredFields = ['site_name', 'contact_email'];
    foreach ($requiredFields as $field) {
        if (empty($settings[$field])) {
            $errors[] = "Field {$field} is required";
        }
    }
    
    // Validate email format
    if (!empty($settings['contact_email']) && !filter_var($settings['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format for contact_email";
    }
    
    // Validate phone number
    if (!empty($settings['contact_phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $settings['contact_phone']);
        if (strlen($phone) < 10) {
            $errors[] = "Invalid phone number format";
        }
    }
    
    // Set defaults for missing values
    $defaults = [
        'site_name' => 'Velona',
        'site_description' => 'Premium clothing with sustainable fashion',
        'currency' => 'INR',
        'currency_symbol' => '₹'
    ];
    
    foreach ($defaults as $key => $defaultValue) {
        if (empty($processedSettings[$key])) {
            $processedSettings[$key] = $defaultValue;
        }
    }
    
    // Add validation errors to processed settings
    if (!empty($errors)) {
        $processedSettings['_validation_errors'] = $errors;
    }
    
    return $processedSettings;
}

/**
 * Validate and process payment settings
 * @param array $settings Settings array
 * @return array Processed settings
 */
function processPaymentSettings($settings) {
    $errors = [];
    $processedSettings = $settings;
    
    // Validate numeric fields
    $numericFields = ['tax_rate', 'shipping_charge', 'free_shipping_threshold', 'min_order_amount'];
    foreach ($numericFields as $field) {
        if (isset($settings[$field]) && !is_numeric($settings[$field])) {
            $errors[] = "Field {$field} must be a valid number";
        }
    }
    
    // Validate Razorpay credentials format
    if (!empty($settings['razorpay_key_id'])) {
        if (!preg_match('/^rzp_(test_|live_)[a-zA-Z0-9]{14}$/', $settings['razorpay_key_id'])) {
            $errors[] = "Invalid Razorpay Key ID format";
        }
    }
    
    // Set payment defaults
    $defaults = [
        'tax_rate' => '18.0',
        'shipping_charge' => '50.0',
        'free_shipping_threshold' => '500.0',
        'min_order_amount' => '100.0'
    ];
    
    foreach ($defaults as $key => $defaultValue) {
        if (!isset($processedSettings[$key]) || $processedSettings[$key] === '') {
            $processedSettings[$key] = $defaultValue;
        }
    }
    
    if (!empty($errors)) {
        $processedSettings['_validation_errors'] = $errors;
    }
    
    return $processedSettings;
}

/**
 * Validate and process system settings
 * @param array $settings Settings array
 * @return array Processed settings
 */
function processSystemSettings($settings) {
    $errors = [];
    $processedSettings = $settings;
    
    // Validate numeric constraints
    $numericConstraints = [
        'items_per_page' => ['min' => 5, 'max' => 100],
        'low_stock_threshold' => ['min' => 0, 'max' => 1000],
        'featured_products_limit' => ['min' => 1, 'max' => 50],
        'related_products_limit' => ['min' => 1, 'max' => 20]
    ];
    
    foreach ($numericConstraints as $field => $constraints) {
        if (isset($settings[$field])) {
            $value = intval($settings[$field]);
            if ($value < $constraints['min'] || $value > $constraints['max']) {
                $errors[] = "Field {$field} must be between {$constraints['min']} and {$constraints['max']}";
            }
        }
    }
    
    // Set system defaults - BUT DON'T OVERRIDE USER VALUES
    $defaults = [
        'items_per_page' => '20',
        'low_stock_threshold' => '10',
        'featured_products_limit' => '8',
        'related_products_limit' => '4',
        'enable_reviews' => false,
        'enable_wishlist' => true,
        // ✅ REMOVED: Don't set maintenance_mode default here
        // 'maintenance_mode' => false
    ];
    
    foreach ($defaults as $key => $defaultValue) {
        // ✅ FIXED: Only set if not already exists AND not provided by user
        if (!isset($processedSettings[$key]) || $processedSettings[$key] === '') {
            $processedSettings[$key] = $defaultValue;
        }
    }
    
    // ✅ ADDED: Special handling for maintenance_mode - never override if explicitly set
    if (isset($settings['maintenance_mode'])) {
        $processedSettings['maintenance_mode'] = $settings['maintenance_mode'];
        error_log("processSystemSettings: Preserving maintenance_mode = " . ($settings['maintenance_mode'] ? 'true' : 'false'));
    }
    
    if (!empty($errors)) {
        $processedSettings['_validation_errors'] = $errors;
    }
    
    return $processedSettings;
}

/**
 * Validate all settings categories
 * @param array $settings Complete settings array
 * @return array Validated and processed settings
 */
function validateAllSettings($settings) {
    $allErrors = [];
    
    // ✅ FIXED: Better detection of partial vs full updates
    $isPartialUpdate = count($settings) < 10; // If less than 10 settings, it's probably partial
    
    // ✅ FIXED: Special handling for maintenance mode only updates
    $isMaintenanceModeOnly = count($settings) === 1 && isset($settings['maintenance_mode']);
    
    // ✅ FIXED: Skip full validation for maintenance mode only changes
    if ($isMaintenanceModeOnly) {
        error_log("Maintenance mode only update detected - skipping full validation");
        return $settings; // Just return as-is, no processing needed
    }
    
    // ✅ ADDED: Preserve maintenance_mode value during full validation
    $originalMaintenanceMode = null;
    if (isset($settings['maintenance_mode'])) {
        $originalMaintenanceMode = $settings['maintenance_mode'];
        error_log("Preserving maintenance_mode value: " . ($originalMaintenanceMode ? 'true' : 'false'));
    }
    
    if (!$isPartialUpdate) {
        // Only do full validation for complete saves
        $settings = processGeneralSettings($settings);
        if (isset($settings['_validation_errors'])) {
            $allErrors = array_merge($allErrors, $settings['_validation_errors']);
            unset($settings['_validation_errors']);
        }
        
        $settings = processPaymentSettings($settings);
        if (isset($settings['_validation_errors'])) {
            $allErrors = array_merge($allErrors, $settings['_validation_errors']);
            unset($settings['_validation_errors']);
        }
        
        $settings = processSystemSettings($settings);
        if (isset($settings['_validation_errors'])) {
            $allErrors = array_merge($allErrors, $settings['_validation_errors']);
            unset($settings['_validation_errors']);
        }
        
        $referralErrors = validateReferralSettings($settings);
        if (!empty($referralErrors)) {
            $allErrors = array_merge($allErrors, $referralErrors);
        }
        
        // ✅ ADDED: Restore original maintenance_mode value if it was changed during processing
        if ($originalMaintenanceMode !== null) {
            $settings['maintenance_mode'] = $originalMaintenanceMode;
            error_log("Restored maintenance_mode to original value: " . ($originalMaintenanceMode ? 'true' : 'false'));
        }
    }
    
    $settings = processShippingSettings($settings);
    
    // ✅ ADDED: Final check - ensure maintenance_mode wasn't overridden
    if ($originalMaintenanceMode !== null && isset($settings['maintenance_mode']) && $settings['maintenance_mode'] !== $originalMaintenanceMode) {
        $settings['maintenance_mode'] = $originalMaintenanceMode;
        error_log("Final restore: maintenance_mode set back to: " . ($originalMaintenanceMode ? 'true' : 'false'));
    }
    
    // Add overall validation results
    if (!empty($allErrors)) {
        $settings['_all_validation_errors'] = $allErrors;
    }
    
    return $settings;
}

function handleResetSettings() {
    try {
        $success = initializeCompleteDefaultSettings();
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Settings reset to default values successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to reset settings'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleResetSettings: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to reset settings']);
    }
}

/**
 * Handle maintenance mode toggle specifically 
 */
function handleToggleMaintenanceMode() {
    try {
        $enabled = isset($_POST['enabled']) ? $_POST['enabled'] : 'false';
        
        error_log("handleToggleMaintenanceMode: Received enabled = " . var_export($enabled, true));
        
        // Convert to boolean
        $maintenanceMode = ($enabled === 'true' || $enabled === true || $enabled === '1' || $enabled === 1);
        
        error_log("handleToggleMaintenanceMode: Converted to boolean = " . ($maintenanceMode ? 'true' : 'false'));
        
        // Save setting
        $success = setSetting('maintenance_mode', $maintenanceMode, 'boolean');
        
        if ($success) {
            // Verify it was saved correctly
            $verifyValue = getSetting('maintenance_mode');
            $isMaintenanceActive = isMaintenanceMode();
            
            error_log("handleToggleMaintenanceMode: Saved successfully");
            error_log("handleToggleMaintenanceMode: Database value = " . var_export($verifyValue, true));
            error_log("handleToggleMaintenanceMode: isMaintenanceMode() = " . ($isMaintenanceActive ? 'true' : 'false'));
            
            echo json_encode([
                'success' => true,
                'message' => 'Maintenance mode updated successfully',
                'maintenance_mode' => $maintenanceMode ? 'true' : 'false',
                'database_value' => $verifyValue,
                'is_active' => $isMaintenanceActive,
                'debug_info' => [
                    'original_input' => $enabled,
                    'converted_boolean' => $maintenanceMode,
                    'database_raw' => $verifyValue,
                    'final_check' => $isMaintenanceActive
                ]
            ]);
        } else {
            error_log("handleToggleMaintenanceMode: setSetting failed");
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update maintenance mode - setSetting returned false'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleToggleMaintenanceMode: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error updating maintenance mode: ' . $e->getMessage()
        ]);
    }
}

// ================================
// EMAIL FUNCTIONS (PRESERVED)
// ================================

function handleGetEmailStats() {
    try {
        $conn = getConnection();
        
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as emails_sent_today,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as emails_delivered_today,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as emails_failed_today
            FROM email_notifications 
            WHERE DATE(sent_at) = CURDATE()
        ");
        $todayStats = $stmt->fetch();
        
        $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE email IS NOT NULL AND email != ''");
        $customerStats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'emails_sent' => intval($todayStats['emails_sent_today'] ?? 0),
                'emails_delivered' => intval($todayStats['emails_delivered_today'] ?? 0),
                'emails_failed' => intval($todayStats['emails_failed_today'] ?? 0),
                'total_customers' => intval($customerStats['total'] ?? 0)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetEmailStats: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to load email statistics',
            'stats' => [
                'emails_sent' => 0,
                'emails_delivered' => 0,
                'emails_failed' => 0,
                'total_customers' => 0
            ]
        ]);
    }
}

function handleSendTestEmail() {
    try {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        if (empty($subject) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
            return;
        }
        
        $adminEmail = $_SESSION['admin_email'] ?? getSetting('contact_email', 'admin@velona.com');
        
        $testSubject = replacePlaceholders($subject, [
            'customer_name' => 'Admin (Test)',
            'site_name' => getSetting('site_name', 'Velona')
        ]);
        
        $testMessage = replacePlaceholders($message, [
            'customer_name' => 'Admin (Test)',
            'site_name' => getSetting('site_name', 'Velona'),
            'unsubscribe_link' => '#'
        ]);
        
        $emailSent = sendBulkEmailToRecipient($adminEmail, 'Admin Test', $testSubject, $testMessage);
        
        if ($emailSent) {
            echo json_encode(['success' => true, 'message' => "Test email sent successfully to {$adminEmail}"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send test email. Please check your email configuration.']);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleSendTestEmail: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error sending test email: ' . $e->getMessage()]);
    }
}

function handleSendBulkEmail() {
    try {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $recipients = json_decode($_POST['recipients'] ?? '[]', true);
        
        if (empty($subject) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
            return;
        }
        
        if (empty($recipients)) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one recipient group']);
            return;
        }
        
        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $attachmentPath = handleEmailAttachment($_FILES['attachment']);
            if (!$attachmentPath) {
                echo json_encode(['success' => false, 'message' => 'Failed to process attachment']);
                return;
            }
        }
        
        $customerList = getBulkEmailRecipients($recipients);
        
        if (empty($customerList)) {
            echo json_encode(['success' => false, 'message' => 'No customers found matching the selected criteria']);
            return;
        }
        
        $successCount = 0;
        $failCount = 0;
        $batchSize = 50;
        
        for ($i = 0; $i < count($customerList); $i += $batchSize) {
            $batch = array_slice($customerList, $i, $batchSize);
            
            foreach ($batch as $customer) {
                try {
                    $personalizedSubject = replacePlaceholders($subject, [
                        'customer_name' => $customer['name'],
                        'site_name' => getSetting('site_name', 'Velona')
                    ]);
                    
                    $personalizedMessage = replacePlaceholders($message, [
                        'customer_name' => $customer['name'],
                        'site_name' => getSetting('site_name', 'Velona'),
                        'unsubscribe_link' => generateUnsubscribeLink($customer['email'])
                    ]);
                    
                    $emailSent = sendBulkEmailToRecipient(
                        $customer['email'], 
                        $customer['name'], 
                        $personalizedSubject, 
                        $personalizedMessage,
                        $attachmentPath
                    );
                    
                    if ($emailSent) {
                        $successCount++;
                        logBulkEmail($customer['id'], $personalizedSubject, 'sent');
                    } else {
                        $failCount++;
                        logBulkEmail($customer['id'], $personalizedSubject, 'failed');
                    }
                    
                    usleep(100000);
                    
                } catch (Exception $e) {
                    $failCount++;
                    error_log("Bulk email error for customer {$customer['email']}: " . $e->getMessage());
                    logBulkEmail($customer['id'], $personalizedSubject, 'failed');
                }
            }
            
            if ($i + $batchSize < count($customerList)) {
                sleep(2);
            }
        }
        
        if ($attachmentPath && file_exists($attachmentPath)) {
            unlink($attachmentPath);
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Bulk email completed! Successfully sent: {$successCount}, Failed: {$failCount}",
            'recipients_count' => $successCount,
            'success_count' => $successCount,
            'fail_count' => $failCount
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleSendBulkEmail: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error sending bulk email: ' . $e->getMessage()]);
    }
}

// ================================
// NEW: SHIPROCKET INTEGRATION FUNCTIONS
// ================================

function handleTestShiprocketConnection() {
    try {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Email and password are required']);
            return;
        }
        
        $result = testShiprocketAPI($email, $password);
        
        if ($result['success']) {
            setSetting('shiprocket_email', $email);
            setSetting('shiprocket_password', $password);
            setSetting('shiprocket_api_token', $result['token']);
            setSetting('shiprocket_token_expiry', $result['expiry']);
            setSetting('shiprocket_enabled', true, 'boolean');
            
            echo json_encode([
                'success' => true,
                'message' => 'Shiprocket connection successful',
                'token' => $result['token'],
                'expiry' => $result['expiry']
            ]);
        } else {
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        error_log("Shiprocket connection error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Connection test failed: ' . $e->getMessage()
        ]);
    }
}

function handleCalculateShippingRates() {
    try {
        $testData = json_decode($_POST['test_data'] ?? '{}', true);
        $token = getSetting('shiprocket_api_token');
        
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Shiprocket not connected']);
            return;
        }
        
        $rates = calculateShiprocketRates($token, $testData);
        
        if ($rates) {
            echo json_encode([
                'success' => true,
                'rates' => $rates
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to calculate rates']);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleCalculateShippingRates: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error calculating rates: ' . $e->getMessage()]);
    }
}

function handleSyncTrackingData() {
    try {
        $token = getSetting('shiprocket_api_token');
        
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Shiprocket not connected']);
            return;
        }
        
        $updatedCount = syncAllTrackingData($token);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tracking data synced successfully',
            'updated_count' => $updatedCount
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleSyncTrackingData: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error syncing tracking data: ' . $e->getMessage()]);
    }
}

// ================================
// SHIPROCKET API HELPER FUNCTIONS
// ================================

function validateShiprocketCredentials($email, $password) {
    if (empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Email and password are required'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    
    return ['success' => true];
}

function calculateShiprocketRates($token, $data) {
    try {
        $url = "https://apiv2.shiprocket.in/v1/external/courier/serviceability/";
        
        $params = http_build_query([
            'pickup_postcode' => $data['pickup_pincode'],
            'delivery_postcode' => $data['delivery_pincode'],
            'weight' => $data['weight'],
            'cod' => 0
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            if (isset($result['data']['available_courier_companies'])) {
                return $result['data']['available_courier_companies'];
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error calculating Shiprocket rates: " . $e->getMessage());
        return null;
    }
}

function syncAllTrackingData($token) {
    try {
        $conn = getConnection();
        
        // Build query that works with basic orders table structure
        $sql = "SELECT id, order_number, status";
        
        // Check if additional columns exist
        try {
            $checkColumns = $conn->query("DESCRIBE orders");
            $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('shiprocket_shipment_id', $columns)) {
                $sql .= ", shiprocket_shipment_id";
            }
            if (in_array('tracking_number', $columns)) {
                $sql .= ", tracking_number";
            }
        } catch (Exception $e) {
            // Continue with basic columns only
        }
        
        $sql .= " FROM orders 
                WHERE status IN ('processing', 'shipped')
                ORDER BY created_at DESC
                LIMIT 100";
        
        $stmt = $conn->query($sql);
        $orders = $stmt->fetchAll();
        $updatedCount = 0;
        
        foreach ($orders as $order) {
            try {
                // Only sync if we have Shiprocket shipment ID
                if (!empty($order['shiprocket_shipment_id'])) {
                    $trackingData = getShiprocketTrackingData($token, $order['shiprocket_shipment_id']);
                    
                    if ($trackingData && isset($trackingData['tracking_data'])) {
                        $currentStatus = $trackingData['tracking_data']['track_status'] ?? '';
                        $awbCode = $trackingData['tracking_data']['awb_code'] ?? '';
                        
                        // Update tracking number if column exists
                        if (in_array('tracking_number', $columns) && $awbCode) {
                            $updateStmt = $conn->prepare("
                                UPDATE orders 
                                SET tracking_number = ?, updated_at = NOW() 
                                WHERE id = ?
                            ");
                            $updateStmt->execute([$awbCode, $order['id']]);
                        }
                        
                        // Update order status based on tracking status
                        $newStatus = mapShiprocketStatusToOrderStatus($currentStatus);
                        if ($newStatus) {
                            $statusStmt = $conn->prepare("
                                UPDATE orders 
                                SET status = ?, updated_at = NOW() 
                                WHERE id = ?
                            ");
                            $statusStmt->execute([$newStatus, $order['id']]);
                        }
                        
                        $updatedCount++;
                    }
                }
                
                // Rate limiting
                usleep(500000); // 0.5 second delay
                
            } catch (Exception $e) {
                error_log("Error syncing tracking for order {$order['id']}: " . $e->getMessage());
                continue;
            }
        }
        
        return $updatedCount;
        
    } catch (Exception $e) {
        error_log("Error in syncAllTrackingData: " . $e->getMessage());
        return 0;
    }
}

function mapShiprocketStatusToOrderStatus($shiprocketStatus) {
    $statusMap = [
        'DELIVERED' => 'delivered',
        'OUT FOR DELIVERY' => 'shipped',
        'IN TRANSIT' => 'shipped',
        'PICKED UP' => 'processing',
        'DISPATCHED' => 'shipped',
        'RTO DELIVERED' => 'cancelled',
        'CANCELLED' => 'cancelled'
    ];
    
    return $statusMap[strtoupper($shiprocketStatus)] ?? null;
}

/**
 * Get Shiprocket pickup locations
 * @param string $token Shiprocket API token
 * @return array Pickup locations
 */
function getShiprocketPickupLocations($token) {
    try {
        $url = "https://apiv2.shiprocket.in/v1/external/settings/company/pickup";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return $result['data']['shipping_address'] ?? [];
        }
        
        return [];
        
    } catch (Exception $e) {
        error_log("Error getting pickup locations: " . $e->getMessage());
        return [];
    }
}

/**
 * Cancel Shiprocket shipment
 * @param string $token Shiprocket API token
 * @param string $awbCode AWB code
 * @return string Result message
 */
function cancelShiprocketShipment($token, $awbCode) {
    try {
        $url = "https://apiv2.shiprocket.in/v1/external/orders/cancel";
        
        $data = ['awbs' => [$awbCode]];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return $result['message'] ?? 'Shipment cancelled successfully';
        }
        
        return 'Failed to cancel shipment';
        
    } catch (Exception $e) {
        error_log("Error cancelling shipment: " . $e->getMessage());
        return 'Error cancelling shipment';
    }
}

/**
 * Generate Shiprocket AWB
 * @param string $token Shiprocket API token  
 * @param array $orderData Order data
 * @return array Result
 */
function generateShiprocketAWB($token, $orderData) {
    try {
        $url = "https://apiv2.shiprocket.in/v1/external/orders/create/adhoc";
        
        // Prepare shipment data with safe defaults
        $shipmentData = [
            "order_id" => $orderData['order_number'] ?? 'TEST' . time(),
            "order_date" => date('Y-m-d H:i'),
            "pickup_location" => "Primary",
            "billing_customer_name" => $orderData['customer_name'] ?? 'Test Customer',
            "billing_last_name" => "",
            "billing_address" => $orderData['billing_address']['street'] ?? 'Test Street',
            "billing_city" => $orderData['billing_address']['city'] ?? 'Mumbai',
            "billing_pincode" => $orderData['billing_address']['pincode'] ?? '400001',
            "billing_state" => $orderData['billing_address']['state'] ?? 'Maharashtra',
            "billing_country" => "India",
            "billing_email" => $orderData['customer_email'] ?? 'test@example.com',
            "billing_phone" => $orderData['customer_phone'] ?? '9999999999',
            "shipping_is_billing" => true,
            "order_items" => !empty($orderData['items']) ? $orderData['items'] : [
                [
                    "name" => "Test Product",
                    "sku" => "TEST001",
                    "units" => 1,
                    "selling_price" => 100,
                    "discount" => 0,
                    "tax" => 18,
                    "hsn" => ""
                ]
            ],
            "payment_method" => $orderData['payment_method'] ?? 'Prepaid',
            "shipping_charges" => floatval($orderData['shipping_charge'] ?? 0),
            "giftwrap_charges" => 0,
            "transaction_charges" => 0,
            "total_discount" => floatval($orderData['discount_amount'] ?? 0),
            "sub_total" => floatval($orderData['subtotal'] ?? 100),
            "length" => 10,
            "breadth" => 10,
            "height" => 10,
            "weight" => 0.5
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($shipmentData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            if (isset($result['order_id'])) {
                return [
                    'success' => true,
                    'order_id' => $result['order_id'],
                    'shipment_id' => $result['shipment_id'] ?? '',
                    'awb_code' => $result['awb_code'] ?? '',
                    'courier_id' => $result['courier_id'] ?? '',
                    'courier_name' => $result['courier_name'] ?? ''
                ];
            }
        }
        
        $errorResult = json_decode($response, true);
        return [
            'success' => false,
            'message' => $errorResult['message'] ?? 'Failed to generate AWB'
        ];
        
    } catch (Exception $e) {
        error_log("Error generating Shiprocket AWB: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error generating AWB: ' . $e->getMessage()
        ];
    }
}

// ================================
// HELPER FUNCTIONS (PRESERVED FROM BULK EMAIL)
// ================================

function updateEmailConfigFile($emailConfigData) {
    $emailConfigPath = '../../includes/email-config.php';
    
    // Load existing config or create default structure
    $existingConfig = [
        'settings' => [
            'enabled' => true,
            'test_mode' => false
        ],
        'sendinblue' => [
            'api_key' => '',
            'from_email' => '',
            'from_name' => 'Velona Team'
        ],
        'smtp' => [
            'host' => '',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls'
        ]
    ];
    
    if (file_exists($emailConfigPath)) {
        try {
            $loadedConfig = include $emailConfigPath;
            if (is_array($loadedConfig)) {
                $existingConfig = array_merge_recursive($existingConfig, $loadedConfig);
            }
        } catch (Exception $e) {
            error_log("Warning: Could not load existing email config: " . $e->getMessage());
        }
    }
    
    // Update with new data
    foreach ($emailConfigData as $key => $value) {
        switch ($key) {
            case 'email_notifications':
                $existingConfig['settings']['enabled'] = ($value === 'true' || $value === true);
                break;
            case 'email_test_mode':
                $existingConfig['settings']['test_mode'] = ($value === 'true' || $value === true);
                break;
            case 'sendinblue_api_key':
                $existingConfig['sendinblue']['api_key'] = $value;
                break;
            case 'sendinblue_from_email':
                $existingConfig['sendinblue']['from_email'] = $value;
                break;
            case 'sendinblue_from_name':
                $existingConfig['sendinblue']['from_name'] = $value;
                break;
            case 'smtp_host':
                $existingConfig['smtp']['host'] = $value;
                break;
            case 'smtp_port':
                $existingConfig['smtp']['port'] = intval($value);
                break;
            case 'smtp_username':
                $existingConfig['smtp']['username'] = $value;
                break;
            case 'smtp_password':
                $existingConfig['smtp']['password'] = $value;
                break;
        }
    }
    
    // Generate PHP config file content
    $configContent = "<?php\n";
    $configContent .= "// includes/email-config.php - Email configuration\n";
    $configContent .= "// Auto-generated on " . date('Y-m-d H:i:s') . "\n\n";
    $configContent .= "return " . var_export($existingConfig, true) . ";\n";
    $configContent .= "?>";
    
    // Ensure directory exists
    $configDir = dirname($emailConfigPath);
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }
    
    // Write file with proper permissions
    if (!file_put_contents($emailConfigPath, $configContent, LOCK_EX)) {
        throw new Exception("Could not write to email-config.php. Check file permissions.");
    }
    
    // Set proper file permissions
    chmod($emailConfigPath, 0644);
    
    error_log("Email config file updated successfully");
}

function getBulkEmailRecipients($groups) {
    $conn = getConnection();
    $recipients = [];
    
    foreach ($groups as $group) {
        switch ($group) {
            case 'all':
                $stmt = $conn->query("
                    SELECT id, name, email 
                    FROM users 
                    WHERE email IS NOT NULL AND email != '' 
                    ORDER BY name ASC
                ");
                $recipients = array_merge($recipients, $stmt->fetchAll());
                break;
                
            case 'recent':
                $stmt = $conn->query("
                    SELECT DISTINCT u.id, u.name, u.email 
                    FROM users u
                    JOIN orders o ON u.id = o.user_id
                    WHERE u.email IS NOT NULL AND u.email != '' 
                    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ORDER BY u.name ASC
                ");
                $recipients = array_merge($recipients, $stmt->fetchAll());
                break;
                
            case 'high_value':
                $stmt = $conn->query("
                    SELECT u.id, u.name, u.email, SUM(o.final_amount) as total_spent
                    FROM users u
                    JOIN orders o ON u.id = o.user_id
                    WHERE u.email IS NOT NULL AND u.email != '' 
                    AND o.payment_status = 'paid'
                    GROUP BY u.id
                    HAVING total_spent >= 1000
                    ORDER BY total_spent DESC
                ");
                $recipients = array_merge($recipients, $stmt->fetchAll());
                break;
        }
    }
    
    // Remove duplicates
    $uniqueRecipients = [];
    $seenEmails = [];
    
    foreach ($recipients as $recipient) {
        if (!in_array($recipient['email'], $seenEmails)) {
            $uniqueRecipients[] = $recipient;
            $seenEmails[] = $recipient['email'];
        }
    }
    
    return $uniqueRecipients;
}

function sendBulkEmailToRecipient($email, $name, $subject, $message, $attachmentPath = null) {
    try {
        $emailConfigPath = '../../includes/email-config.php';
        if (!file_exists($emailConfigPath)) {
            throw new Exception("Email configuration not found");
        }
        
        $emailConfig = include $emailConfigPath;
        
        if (!$emailConfig['settings']['enabled']) {
            throw new Exception("Email system is disabled");
        }
        
        if ($emailConfig['settings']['test_mode']) {
            error_log("TEST MODE: Would send email to {$email} with subject: {$subject}");
            return true;
        }
        
        $sendinblueMailerPath = '../../includes/sendinblue-mailer.php';
        if (!file_exists($sendinblueMailerPath)) {
            return sendEmailNotification($email, $subject, strip_tags($message), 'bulk_email', $message, $name);
        }
        
        require_once $sendinblueMailerPath;
        
        $mailer = new SendinblueMailer(
            $emailConfig['sendinblue']['api_key'],
            $emailConfig['sendinblue']['from_email'],
            $emailConfig['sendinblue']['from_name']
        );
        
        return $mailer->sendEmail($email, $name, $subject, $message, strip_tags($message));
        
    } catch (Exception $e) {
        error_log("Bulk email sending error: " . $e->getMessage());
        return false;
    }
}

function replacePlaceholders($content, $placeholders) {
    foreach ($placeholders as $placeholder => $value) {
        $content = str_replace('{' . $placeholder . '}', $value, $content);
    }
    return $content;
}

function generateUnsubscribeLink($email) {
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $token = base64_encode($email . '|' . time());
    return $baseUrl . '/unsubscribe.php?token=' . urlencode($token);
}

function handleEmailAttachment($file) {
    try {
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            throw new Exception("File too large. Maximum size is 5MB.");
        }
        
        $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'txt'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedTypes)) {
            throw new Exception("File type not allowed. Allowed types: " . implode(', ', $allowedTypes));
        }
        
        $uploadDir = '../../uploads/email-attachments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = 'attachment_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return $uploadPath;
        } else {
            throw new Exception("Failed to upload file");
        }
        
    } catch (Exception $e) {
        error_log("Email attachment error: " . $e->getMessage());
        return false;
    }
}

function logBulkEmail($userId, $subject, $status) {
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO email_notifications 
            (user_id, email_type, subject, message, sent_at, status) 
            VALUES (?, 'bulk_email', ?, 'Bulk email campaign', NOW(), ?)
        ");
        
        $stmt->execute([$userId, $subject, $status]);
        
    } catch (Exception $e) {
        error_log("Error logging bulk email: " . $e->getMessage());
    }
}

function handleGetOrderDetails() {
    try {
        $orderId = intval($_GET['order_id'] ?? 0);
        $userId = intval($_GET['user_id'] ?? 0);
        
        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            return;
        }
        
        $conn = getConnection();
        
        // Get order details with user verification
        $sql = "
            SELECT 
                o.*,
                u.name as customer_name,
                u.email as customer_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id  
            WHERE o.id = ?
        ";
        
        $params = [$orderId];
        
        // Add user restriction if provided (for customer-facing API)
        if ($userId) {
            $sql .= " AND o.user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Parse JSON fields
        $order['shipping_address'] = json_decode($order['shipping_address'] ?? '{}', true);
        $order['billing_address'] = json_decode($order['billing_address'] ?? '{}', true);
        
        // Get order items
        $itemsStmt = $conn->prepare("
            SELECT * FROM order_items 
            WHERE order_id = ? 
            ORDER BY id ASC
        ");
        $itemsStmt->execute([$orderId]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'order' => $order,
            'items' => $order['items'],
            'shipping_address' => $order['shipping_address']
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetOrderDetails: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error retrieving order details']);
    }
}

function handleTrackOrder() {
    try {
        $orderId = intval($_GET['order_id'] ?? 0);
        $userId = intval($_GET['user_id'] ?? 0);
        
        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            return;
        }
        
        $conn = getConnection();
        
        // Get order tracking details
        $sql = "
            SELECT 
                order_number,
                status,
                shiprocket_shipment_id, 
                tracking_number,
                courier_partner
            FROM orders 
            WHERE id = ?
        ";
        
        $params = [$orderId];
        
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get comprehensive tracking data
        $trackingData = getComprehensiveTrackingData($orderId, $order['order_number'], $order['status']);
        
        if ($trackingData) {
            echo json_encode([
                'success' => true,
                'tracking_data' => $trackingData
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Tracking information not available'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleTrackOrder: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error retrieving tracking data']);
    }
}

/**
 * Verify and create settings table if needed
 * @return bool Success status
 */
function verifySettingsTable() {
    try {
        $conn = getConnection();
        
        // Check if settings table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'settings'");
        
        if ($stmt->rowCount() == 0) {
            // Create settings table
            $createTableSQL = "
                CREATE TABLE `settings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `setting_key` varchar(100) NOT NULL,
                    `setting_value` text DEFAULT NULL,
                    `setting_type` varchar(20) DEFAULT 'string',
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `setting_key` (`setting_key`),
                    KEY `setting_type` (`setting_type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $conn->exec($createTableSQL);
            error_log("Settings table created successfully");
            
            // Initialize with default settings
            initializeDefaultSettings();
            
            return true;
        }
        
        // Verify table structure
        $stmt = $conn->query("DESCRIBE settings");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['setting_key', 'setting_value', 'setting_type', 'created_at', 'updated_at'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (!empty($missingColumns)) {
            error_log("Settings table missing columns: " . implode(', ', $missingColumns));
            
            // Add missing columns
            foreach ($missingColumns as $column) {
                switch ($column) {
                    case 'setting_type':
                        $conn->exec("ALTER TABLE settings ADD COLUMN setting_type varchar(20) DEFAULT 'string'");
                        break;
                     case 'created_at':
                        $conn->exec("ALTER TABLE settings ADD COLUMN created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
                        break;
                    case 'updated_at':
                        $conn->exec("ALTER TABLE settings ADD COLUMN updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                        break;
                }
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error verifying settings table: " . $e->getMessage());
        return false;
    }
}

function initializeCompleteDefaultSettings() {
    $defaultSettings = [
        // General Settings
        'site_name' => 'Velona',
        'site_description' => 'Premium clothing with sustainable fashion',
        'contact_email' => 'contact@velona.com',
        'contact_phone' => '+91 9876543210',
        'currency' => 'INR',
        'currency_symbol' => '₹',
        'default_timezone' => 'Asia/Kolkata',
        'date_format' => 'Y-m-d',
        'session_timeout' => '60',
        
        // Payment Settings
        'tax_rate' => '18.0',
        'shipping_charge' => '50.0',
        'free_shipping_threshold' => '500.0',
        'min_order_amount' => '100.0',
        'razorpay_key_id' => '',
        'razorpay_key_secret' => '',
        'razorpay_mode' => 'test',
        'enable_cod' => 'true',
        'cod_charges' => '0.00',
        
        // Email Settings
        'email_notifications' => 'true',
        'sendinblue_api_key' => '',
        'sendinblue_from_email' => '',
        'sendinblue_from_name' => 'Velona Team',
        'email_test_mode' => 'false',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_username' => '',
        'smtp_password' => '',
        
        // Shipping Settings
        'default_shipping_method' => 'standard',
        'estimated_delivery_days' => '3-7',
        'shiprocket_enabled' => 'false',
        'shiprocket_email' => '',
        'shiprocket_password' => '',
        'shiprocket_api_token' => '',
        'shiprocket_token_expiry' => '',
        
        // Referral Settings
        'enable_referrals' => 'true',
        'first_month_rate' => '10.0',
        'other_months_rate' => '5.0',
        'min_points_to_claim' => '100',
        'referral_code_length' => '6',
        'auto_approve_claims' => 'false',
        
        // System Settings
        'items_per_page' => '20',
        'low_stock_threshold' => '10',
        'featured_products_limit' => '8',
        'related_products_limit' => '4',
        'new_arrivals_days' => '30',
        'max_cart_items' => '20',
        'enable_reviews' => 'true',
        'enable_wishlist' => 'true',
        'maintenance_mode' => 'false'
    ];
    
    try {
        $savedCount = 0;
        foreach ($defaultSettings as $key => $value) {
            // Determine type
            $type = 'string';
            if (in_array($value, ['true', 'false'])) {
                $type = 'boolean';
            } elseif (is_numeric($value)) {
                $type = 'number';
            }
            
            // Only set if not already exists
            $existingValue = getSetting($key, null);
            if ($existingValue === null) {
                if (setSetting($key, $value, $type)) {
                    $savedCount++;
                }
            }
        }
        
        error_log("Initialized {$savedCount} default settings");
        return true;
        
    } catch (Exception $e) {
        error_log("Error initializing default settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Settings export functionality
 */
function handleExportSettings() {
    try {
        $settings = getAllSettings();
        
        // Remove sensitive data from export
        $sensitiveKeys = [
            'razorpay_key_secret',
            'sendinblue_api_key', 
            'smtp_password',
            'shiprocket_password',
            'shiprocket_api_token'
        ];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = '***HIDDEN***';
            }
        }
        
        $exportData = [
            'exported_at' => date('Y-m-d H:i:s'),
            'site_name' => $settings['site_name'] ?? 'Velona',
            'version' => '1.0',
            'settings' => $settings
        ];
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="velona_settings_' . date('Y-m-d_H-i-s') . '.json"');
        echo json_encode($exportData, JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        error_log("Error in handleExportSettings: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Export failed']);
    }
}

function handleImportSettings() {
    try {
        if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
            return;
        }
        
        $fileContent = file_get_contents($_FILES['settings_file']['tmp_name']);
        $importData = json_decode($fileContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON file']);
            return;
        }
        
        if (!isset($importData['settings'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid settings file format']);
            return;
        }
        
        $settings = $importData['settings'];
        $importedCount = 0;
        $skippedCount = 0;
        
        // Skip sensitive settings during import
        $skipKeys = [
            'razorpay_key_secret',
            'sendinblue_api_key',
            'smtp_password', 
            'shiprocket_password',
            'shiprocket_api_token'
        ];
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $skipKeys) || $value === '***HIDDEN***') {
                $skippedCount++;
                continue;
            }
            
            // Determine type
            $type = 'string';
            if (is_bool($value)) {
                $type = 'boolean';
                $value = $value ? 'true' : 'false';
            } elseif (is_numeric($value)) {
                $type = 'number';
            }
            
            if (setSetting($key, $value, $type)) {
                $importedCount++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Imported {$importedCount} settings, skipped {$skippedCount} sensitive settings",
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleImportSettings: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
    }
}

function handleValidateSettings() {
    try {
        $settingsJson = $_POST['settings'] ?? '';
        
        if (empty($settingsJson)) {
            echo json_encode(['success' => false, 'message' => 'No settings data provided']);
            return;
        }
        
        $settings = json_decode($settingsJson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Invalid settings data format']);
            return;
        }
        
        // Validate settings without saving
        $validatedSettings = validateAllSettings($settings);
        
        if (isset($validatedSettings['_all_validation_errors'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validatedSettings['_all_validation_errors']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'All settings are valid',
                'validated_settings' => $validatedSettings
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleValidateSettings: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Validation failed: ' . $e->getMessage()]);
    }
}

function handleGetSettingsInfo() {
    try {
        $conn = getConnection();
        
        // Get settings table info
        $stmt = $conn->query("SELECT COUNT(*) as total_settings FROM settings");
        $settingsCount = $stmt->fetch()['total_settings'];
        
        // Get recent updates
        $stmt = $conn->query("
            SELECT setting_key, updated_at 
            FROM settings 
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY updated_at DESC 
            LIMIT 10
        ");
        $recentUpdates = $stmt->fetchAll();
        
        // Get system info
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'database_type' => 'MySQL/MariaDB',
            'settings_table_exists' => true,
            'total_settings' => intval($settingsCount),
            'email_config_exists' => file_exists('../../includes/email-config.php'),
            'functions_loaded' => function_exists('getSetting'),
            'recent_updates' => $recentUpdates
        ];
        
        echo json_encode([
            'success' => true,
            'system_info' => $systemInfo
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetSettingsInfo: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to get settings info']);
    }
}

// ================================
// COMPREHENSIVE MAINTENANCE SYSTEM
// ================================

/**
 * Perform complete system maintenance
 */
function handleSystemMaintenance() {
    try {
        $results = [
            'success' => true,
            'tasks_completed' => 0,
            'tasks_failed' => 0,
            'details' => [],
            'started_at' => date('Y-m-d H:i:s')
        ];
        
        $conn = getConnection();
        
        // Task 1: Clean old email notifications
        try {
            $stmt = $conn->prepare("DELETE FROM email_notifications WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            $cleanedEmails = $stmt->rowCount();
            $stmt = null; // Close properly
            $results['details']['email_cleanup'] = "Cleaned {$cleanedEmails} old email records";
            $results['tasks_completed']++;
        } catch (Exception $e) {
            $results['details']['email_cleanup'] = "Failed: " . $e->getMessage();
            $results['tasks_failed']++;
        }
        
        // Task 2: Clean old visitor tracking (if table exists)
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE 'referral_visits'");
            $stmt->execute();
            $tableExists = $stmt->fetch();
            $stmt = null; // Close properly
            
            if ($tableExists) {
                $stmt = $conn->prepare("DELETE FROM referral_visits WHERE visited_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stmt->execute();
                $cleanedVisits = $stmt->rowCount();
                $stmt = null; // Close properly
                $results['details']['visitor_cleanup'] = "Cleaned {$cleanedVisits} old visitor records";
            } else {
                $results['details']['visitor_cleanup'] = "Referral visits table not found - skipped";
            }
            $results['tasks_completed']++;
        } catch (Exception $e) {
            $results['details']['visitor_cleanup'] = "Failed: " . $e->getMessage();
            $results['tasks_failed']++;
        }
        
        // Task 3: Clean old activity logs (if table exists)
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE 'activity_logs'");
            $stmt->execute();
            $tableExists = $stmt->fetch();
            $stmt = null; // Close properly
            
            if ($tableExists) {
                $stmt = $conn->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
                $stmt->execute();
                $cleanedActivity = $stmt->rowCount();
                $stmt = null; // Close properly
                $results['details']['activity_cleanup'] = "Cleaned {$cleanedActivity} old activity records";
            } else {
                $results['details']['activity_cleanup'] = "Activity logs table not found - skipped";
            }
            $results['tasks_completed']++;
        } catch (Exception $e) {
            $results['details']['activity_cleanup'] = "Failed: " . $e->getMessage();
            $results['tasks_failed']++;
        }
        
        // Task 4: Optimize database tables
        try {
            $tables = ['settings', 'users', 'orders', 'order_items', 'products', 'categories', 'wallet', 'referrals'];
            $optimizedTables = 0;
            foreach ($tables as $table) {
                try {
                    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
                    $stmt->execute([$table]);
                    $tableExists = $stmt->fetch();
                    $stmt = null; // Close properly
                    
                    if ($tableExists) {
                        $stmt = $conn->prepare("OPTIMIZE TABLE `{$table}`");
                        $stmt->execute();
                        $stmt = null; // Close properly
                        $optimizedTables++;
                    }
                } catch (Exception $e) {
                    error_log("Failed to optimize table {$table}: " . $e->getMessage());
                }
            }
            $results['details']['table_optimization'] = "Optimized {$optimizedTables} database tables";
            $results['tasks_completed']++;
        } catch (Exception $e) {
            $results['details']['table_optimization'] = "Failed: " . $e->getMessage();
            $results['tasks_failed']++;
        }
        
        // Task 5: Verify settings integrity - FIXED
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM settings");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $settingsCount = $result['count'];
            $stmt = null; // Close properly
            
            if ($settingsCount < 10) {
                // Reinitialize if too few settings
                initializeCompleteDefaultSettings();
                $results['details']['settings_verification'] = "Settings reinitialized - had only {$settingsCount} settings";
            } else {
                $results['details']['settings_verification'] = "Settings integrity verified - {$settingsCount} settings found";
            }
            $results['tasks_completed']++;
        } catch (Exception $e) {
            $results['details']['settings_verification'] = "Failed: " . $e->getMessage();
            $results['tasks_failed']++;
        }
        
        // Task 6: Clean temporary files
        try {
            $tempDirs = ['../../uploads/email-attachments/', '../../temp/', '../../cache/'];
            $cleanedFiles = 0;
            
            foreach ($tempDirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file) && filemtime($file) < (time() - 86400)) { // 24 hours old
                            unlink($file);
                            $cleanedFiles++;
                        }
                    }
                }
            }
            $results['details']['temp_cleanup'] = "Cleaned {$cleanedFiles} temporary files";
            $results['tasks_completed']++;
        } catch (Exception $e) {
            $results['details']['temp_cleanup'] = "Failed: " . $e->getMessage();
            $results['tasks_failed']++;
        }
        
        // Task 7: Update system health status
        try {
            $healthData = getSystemHealthData();
            setSetting('last_maintenance_run', date('Y-m-d H:i:s'));
            setSetting('system_health_status', json_encode($healthData), 'json');
            $results['details']['health_update'] = "System health status updated";
            $results['tasks_completed']++;
        } catch (Exception $e) {
            $results['details']['health_update'] = "Failed: " . $e->getMessage();
            $results['tasks_failed']++;
        }
        
        $results['completed_at'] = date('Y-m-d H:i:s');
        
        echo json_encode($results);
        
    } catch (Exception $e) {
        error_log("System maintenance error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Maintenance failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get comprehensive system health data
 */
function getSystemHealthData() {
    try {
        $conn = getConnection();
        
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'stats' => [],
            'warnings' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Database connectivity
        try {
            $stmt = $conn->prepare("SELECT 1");
            $stmt->execute();
            $stmt->fetch();
            $stmt = null; // Close properly
            $health['checks']['database'] = 'Connected';
        } catch (Exception $e) {
            $health['checks']['database'] = 'Error: ' . $e->getMessage();
            $health['status'] = 'warning';
        }
        
        // Settings table integrity
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM settings");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $settingsCount = $result['count'];
            $stmt = null; // Close properly
            
            $health['stats']['total_settings'] = $settingsCount;
            
            if ($settingsCount < 10) {
                $health['warnings'][] = "Low settings count: {$settingsCount}";
                $health['status'] = 'warning';
            }
        } catch (Exception $e) {
            $health['warnings'][] = "Cannot read settings table";
            $health['status'] = 'warning';
        }
        
        // Email configuration
        $emailConfigPath = '../../includes/email-config.php';
        if (file_exists($emailConfigPath)) {
            $health['checks']['email_config'] = 'Present';
            try {
                $emailConfig = include $emailConfigPath;
                $health['checks']['email_enabled'] = $emailConfig['settings']['enabled'] ? 'Enabled' : 'Disabled';
            } catch (Exception $e) {
                $health['warnings'][] = "Email config corrupted";
            }
        } else {
            $health['checks']['email_config'] = 'Missing';
            $health['warnings'][] = "Email configuration file not found";
        }
        
        // File permissions
        $criticalPaths = [
            '../../includes/email-config.php' => 'Email Config',
            '../../uploads/' => 'Uploads Directory',
            '../' => 'Admin Directory'
        ];
        
        foreach ($criticalPaths as $path => $name) {
            if (file_exists($path)) {
                $health['checks']['permissions_' . strtolower(str_replace(' ', '_', $name))] = is_writable($path) ? 'Writable' : 'Read-only';
            }
        }
        
        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $health['stats']['memory_usage'] = round($memoryUsage / 1024 / 1024, 2) . 'MB';
        
        // Disk space (if available)
        if (function_exists('disk_free_space')) {
            $freeSpace = disk_free_space(__DIR__);
            if ($freeSpace !== false) {
                $health['stats']['disk_space'] = round($freeSpace / 1024 / 1024 / 1024, 2) . 'GB free';
            }
        }
        
        // Recent activity - FIXED to avoid buffering issues
        try {
            $stmt = $conn->prepare("SELECT MAX(updated_at) as last_update FROM settings");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $lastUpdate = $result['last_update'];
            $stmt = null; // Close properly
            
            $health['stats']['last_settings_update'] = $lastUpdate ?: 'Unknown';
        } catch (Exception $e) {
            $health['warnings'][] = "Cannot check recent activity";
        }
        
        return $health;
        
    } catch (Exception $e) {
        error_log("System health check error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Handle system health check
 */
function handleSystemHealthCheck() {
    try {
        $healthData = getSystemHealthData();
        
        echo json_encode([
            'success' => true,
            'health' => $healthData
        ]);
        
    } catch (Exception $e) {
        error_log("Health check error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Health check failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Backup critical settings data
 */
function handleCreateBackup() {
    try {
        $conn = getConnection();
        
        // Create backup data
        $backupData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'settings' => getAllSettings(),
            'system_info' => getSystemHealthData()
        ];
        
        // Count important tables
        $tables = ['users', 'orders', 'products', 'referrals'];
        foreach ($tables as $table) {
            try {
                $stmt = $conn->query("SELECT COUNT(*) as count FROM {$table}");
                $backupData['table_counts'][$table] = $stmt->fetch()['count'];
            } catch (Exception $e) {
                $backupData['table_counts'][$table] = 'Error: ' . $e->getMessage();
            }
        }
        
        // Save backup to database
        try {
            $stmt = $conn->prepare("
                INSERT INTO system_backups 
                (backup_data, created_at) 
                VALUES (?, NOW())
            ");
            $stmt->execute([json_encode($backupData)]);
            $backupId = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Backup created successfully',
                'backup_id' => $backupId,
                'settings_count' => count($backupData['settings'])
            ]);
            
        } catch (Exception $e) {
            // If backup table doesn't exist, just return the data
            echo json_encode([
                'success' => true,
                'message' => 'Backup data generated (backup table not available)',
                'backup_data' => $backupData
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Backup creation error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Backup failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Repair corrupted settings
 */
function handleRepairSettings() {
    try {
        $conn = getConnection();
        $repairedCount = 0;
        $issues = [];
        $checkedItems = [];
        
        // Check for missing critical settings
        $criticalSettings = [
            'site_name' => 'Velona',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'tax_rate' => '18.0',
            'min_order_amount' => '100.0',
            'items_per_page' => '20',
            'low_stock_threshold' => '10'
        ];
        
        $checkedItems[] = "Checked " . count($criticalSettings) . " critical settings";
        
        foreach ($criticalSettings as $key => $defaultValue) {
            $currentValue = getSetting($key);
            if (empty($currentValue)) {
                setSetting($key, $defaultValue);
                $repairedCount++;
                $issues[] = "Restored missing setting: {$key} = {$defaultValue}";
            }
        }
        
        // Check for corrupted JSON settings
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_type = 'json'");
        $stmt->execute();
        $jsonSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;
        
        $jsonCount = count($jsonSettings);
        $checkedItems[] = "Checked {$jsonCount} JSON settings for corruption";
        
        foreach ($jsonSettings as $setting) {
            $decoded = json_decode($setting['setting_value'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Reset corrupted JSON to empty array
                setSetting($setting['setting_key'], '[]', 'json');
                $repairedCount++;
                $issues[] = "Repaired corrupted JSON setting: {$setting['setting_key']}";
            }
        }
        
        // Check for invalid numeric settings
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_type = 'number'");
        $stmt->execute();
        $numericSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;
        
        $numericCount = count($numericSettings);
        $checkedItems[] = "Checked {$numericCount} numeric settings for validity";
        
        foreach ($numericSettings as $setting) {
            if (!is_numeric($setting['setting_value'])) {
                setSetting($setting['setting_key'], '0', 'number');
                $repairedCount++;
                $issues[] = "Repaired invalid numeric setting: {$setting['setting_key']} (was: '{$setting['setting_value']}', now: '0')";
            }
        }
        
        // Check for boolean settings with invalid values
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_type = 'boolean'");
        $stmt->execute();
        $booleanSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;
        
        $booleanCount = count($booleanSettings);
        $checkedItems[] = "Checked {$booleanCount} boolean settings for validity";
        
        foreach ($booleanSettings as $setting) {
            $value = $setting['setting_value'];
            if (!in_array($value, ['true', 'false', '1', '0', 'on', 'off'])) {
                setSetting($setting['setting_key'], 'false', 'boolean');
                $repairedCount++;
                $issues[] = "Repaired invalid boolean setting: {$setting['setting_key']} (was: '{$value}', now: 'false')";
            }
        }
        
        // Check database table integrity
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM settings");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalSettings = $result['count'];
            $stmt = null;
            
            $checkedItems[] = "Verified settings table integrity ({$totalSettings} total settings)";
            
            if ($totalSettings < 5) {
                $issues[] = "Warning: Very low settings count ({$totalSettings}), consider running initialization";
            }
        } catch (Exception $e) {
            $issues[] = "Database integrity check failed: " . $e->getMessage();
            $repairedCount++; // Count this as something that needed attention
        }
        
        // Check file permissions
        $criticalFiles = [
            '../../includes/email-config.php' => 'Email configuration file',
            '../../uploads/' => 'Uploads directory'
        ];
        
        foreach ($criticalFiles as $path => $description) {
            if (file_exists($path)) {
                if (!is_writable($path)) {
                    $issues[] = "Warning: {$description} is not writable ({$path})";
                }
                $checkedItems[] = "Checked permissions for {$description}";
            } else {
                $checkedItems[] = "Note: {$description} does not exist ({$path})";
            }
        }
        
        // Generate response
        if ($repairedCount > 0) {
            echo json_encode([
                'success' => true,
                'message' => "Settings repair completed. Fixed {$repairedCount} issues.",
                'repaired_count' => $repairedCount,
                'issues_found' => $issues,
                'checked_items' => $checkedItems
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => "Settings repair completed. No issues found - all settings are healthy!",
                'repaired_count' => 0,
                'issues_found' => ["✅ All settings are properly configured"],
                'checked_items' => $checkedItems,
                'status' => 'all_good'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Settings repair error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Repair failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Clean sensitive data from logs/exports
 */
function handleCleanSensitiveData() {
    try {
        $cleanedItems = 0;
        
        // Clear sensitive settings from database (reset to empty, don't delete)
        $sensitiveSettings = [
            'razorpay_key_secret',
            'sendinblue_api_key',
            'smtp_password',
            'shiprocket_password',
            'shiprocket_api_token'
        ];
        
        foreach ($sensitiveSettings as $setting) {
            setSetting($setting, '');
            $cleanedItems++;
        }
        
        // Clean old temporary files
        $tempDirs = ['../../uploads/email-attachments/', '../../temp/'];
        foreach ($tempDirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                        $cleanedItems++;
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Cleaned {$cleanedItems} sensitive items",
            'cleaned_count' => $cleanedItems
        ]);
        
    } catch (Exception $e) {
        error_log("Sensitive data cleanup error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Cleanup failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get Shiprocket pickup locations
 */
function handleGetPickupLocations() {
    try {
        $token = getSetting('shiprocket_api_token');
        
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Shiprocket not connected']);
            return;
        }
        
        $locations = getShiprocketPickupLocations($token);
        
        echo json_encode([
            'success' => true,
            'locations' => $locations
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleGetPickupLocations: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error fetching pickup locations']);
    }
}

function handleCancelShipment() {
    try {
        $awbCode = $_POST['awb_code'] ?? '';
        
        if (empty($awbCode)) {
            echo json_encode(['success' => false, 'message' => 'AWB code required']);
            return;
        }
        
        $token = getSetting('shiprocket_api_token');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Shiprocket not connected']);
            return;
        }
        
        $result = cancelShiprocketShipment($token, $awbCode);
        
        echo json_encode([
            'success' => true,
            'message' => $result
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleCancelShipment: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error cancelling shipment']);
    }
}

function handleGenerateAWB() {
    try {
        $orderData = json_decode($_POST['order_data'] ?? '{}', true);
        
        if (empty($orderData)) {
            echo json_encode(['success' => false, 'message' => 'Order data required']);
            return;
        }
        
        $token = getSetting('shiprocket_api_token');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Shiprocket not connected']);
            return;
        }
        
        $result = generateShiprocketAWB($token, $orderData);
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        error_log("Error in handleGenerateAWB: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error generating AWB']);
    }
}

function handleUpdatePickupAddress() {
    try {
        $addressData = json_decode($_POST['address_data'] ?? '{}', true);
        
        if (empty($addressData)) {
            echo json_encode(['success' => false, 'message' => 'Address data required']);
            return;
        }
        
        // Save pickup address settings
        $addressFields = [
            'pickup_company_name', 'pickup_contact_person', 'pickup_phone',
            'pickup_email', 'pickup_address', 'pickup_city', 'pickup_state', 'pickup_pincode'
        ];
        
        foreach ($addressFields as $field) {
            if (isset($addressData[$field])) {
                setSetting($field, $addressData[$field]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Pickup address updated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Error in handleUpdatePickupAddress: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating pickup address']);
    }
}

/**
 * Ensure clean JSON output
 */
function ensureCleanOutput() {
    // Clean any output that might have been generated
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Ensure we're sending JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
}

// Call this before any JSON output
?>