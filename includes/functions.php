<?php
// BULLETPROOF: Complete error handling + ALL existing functions preserved + E-commerce integration


require_once 'database.php';

// ============================================================================
// DATABASE CONNECTION AND CORE UTILITIES
// ============================================================================

/**
 * Sanitize input data
 * @param mixed $data Input data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Complete validateProductData function
 * @param array $data Product data
 * @param bool $isUpdate Is this an update operation
 * @return array Validation errors
 */
function validateProductData($data, $isUpdate = false) {
    $errors = [];
    
    // Required fields for new products
    if (!$isUpdate) {
        if (empty($data['name'])) {
            $errors[] = 'Product name is required';
        }
        
        if (empty($data['category_id']) || !is_numeric($data['category_id'])) {
            $errors[] = 'Valid category is required';
        }
        
        if (empty($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
            $errors[] = 'Valid price is required';
        }
    } else {
        // For updates, only validate if fields are provided
        if (isset($data['name']) && empty($data['name'])) {
            $errors[] = 'Product name cannot be empty';
        }
        
        if (isset($data['category_id']) && (!is_numeric($data['category_id']) || $data['category_id'] <= 0)) {
            $errors[] = 'Valid category is required';
        }
        
        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] <= 0)) {
            $errors[] = 'Valid price is required';
        }
    }
    
    // Common validations
    if (isset($data['stock_quantity']) && (!is_numeric($data['stock_quantity']) || $data['stock_quantity'] < 0)) {
        $errors[] = 'Stock quantity must be a non-negative number';
    }
    
    if (isset($data['low_stock_threshold']) && (!is_numeric($data['low_stock_threshold']) || $data['low_stock_threshold'] < 0)) {
        $errors[] = 'Low stock threshold must be a non-negative number';
    }
    
    if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'out_of_stock'])) {
        $errors[] = 'Invalid status';
    }
    
    // Validate product name length
    if (isset($data['name']) && strlen($data['name']) > 200) {
        $errors[] = 'Product name cannot exceed 200 characters';
    }
    
    // Validate description length
    if (isset($data['description']) && strlen($data['description']) > 2000) {
        $errors[] = 'Description cannot exceed 2000 characters';
    }
    
    // Validate care instructions length
    if (isset($data['care_instructions']) && strlen($data['care_instructions']) > 1000) {
        $errors[] = 'Care instructions cannot exceed 1000 characters';
    }
    
    // Validate sizes if provided
    if (isset($data['sizes']) && is_array($data['sizes'])) {
        $validSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $invalidSizes = array_diff($data['sizes'], $validSizes);
        if (!empty($invalidSizes)) {
            $errors[] = 'Invalid sizes: ' . implode(', ', $invalidSizes);
        }
    }
    
    return $errors;
}

/**
 * Generate secure random string
 * @param int $length Length of the string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Get visitor's IP address (PRESERVED EXACTLY from original)
 * @return string Client IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// ============================================================================
// USER MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Get user by ID
 * @param int $userId User ID
 * @return array|null User data or null if not found
 */
function getUserById($userId) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        return null;
    }
    
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        return $stmt->rowCount() > 0 ? $stmt->fetch() : null;
        
    } catch (Exception $e) {
        error_log("Database error in getUserById: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by email
 * @param string $email User email
 * @return array|null User data or null if not found
 */
function getUserByEmail($email) {
    if (empty($email)) {
        return null;
    }
    
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        return $stmt->rowCount() > 0 ? $stmt->fetch() : null;
        
    } catch (Exception $e) {
        error_log("Database error in getUserByEmail: " . $e->getMessage());
        return null;
    }
}

/**
 * Create new user
 * @param array $userData User data
 * @return array Result with success status and user ID
 */
function createUser($userData) {
    $required = ['name', 'email'];
    foreach ($required as $field) {
        if (empty($userData[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }
    
    try {
        $conn = getConnection();
        
        // Check if email already exists
        $existing = getUserByEmail($userData['email']);
        if ($existing) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, created_at) 
            VALUES (?, ?, NOW())
        ");
        
        $stmt->execute([
            $userData['name'],
            $userData['email']
        ]);
        
        $userId = $conn->lastInsertId();
        
        return [
            'success' => true, 
            'message' => 'User created successfully',
            'user_id' => $userId
        ];
        
    } catch (Exception $e) {
        error_log("Database error in createUser: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create user'];
    }
}

// ============================================================================
// PHONE-FIRST CUSTOMER IDENTITY
// ============================================================================

/**
 * Find an existing user by phone, or create a new one.
 * Phone number is the sole identity key in the OTP-checkout system.
 *
 * For new users: creates users row + referral record + wallet.
 * For returning users: returns existing row intact (wallet/referrals preserved).
 *
 * @param  string $phone  10-digit Indian mobile number (digits only, no country code)
 * @return array|null     Full users row, or null on DB failure
 */
function findOrCreateUserByPhone(string $phone): ?array
{
    // Normalise: strip non-digits and leading 91
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
        $phone = substr($phone, 2);
    }

    if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
        error_log("findOrCreateUserByPhone: invalid phone '{$phone}'");
        return null;
    }

    try {
        $conn = getConnection();

        // Look up existing user
        $stmt = $conn->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update last_login and ensure wallet + referral exist
            $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$existing['id']]);
            ensureUserWallet($existing['id']);
            return $existing;
        }

        // New customer — create minimal profile
        $conn->prepare("
            INSERT INTO users (phone, user_type, status, created_at, last_login)
            VALUES (?, 'registered', 'active', NOW(), NOW())
        ")->execute([$phone]);

        $userId = (int)$conn->lastInsertId();

        // Create referral entry
        $code = generateReferralCode();
        $link = generateReferralLink($code);
        $conn->prepare("INSERT INTO referrals (user_id, code, link) VALUES (?, ?, ?)")
             ->execute([$userId, $code, $link]);

        // Initialise wallet
        ensureUserWallet($userId);

        // Return the freshly created row
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    } catch (Exception $e) {
        error_log("findOrCreateUserByPhone error: " . $e->getMessage());
        return null;
    }
}

/**
 * Save or update the default delivery address for a customer.
 * Called after a successful order so the next checkout is pre-filled.
 *
 * @param  int    $userId
 * @param  array  $addr  Keys: full_name, phone, email, address_line,
 *                             apartment, city, state, pincode
 * @return bool
 */
function saveCustomerAddress(int $userId, array $addr): bool
{
    try {
        $conn = getConnection();

        // Check if a default already exists
        $stmt = $conn->prepare("SELECT id FROM customer_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $conn->prepare("
                UPDATE customer_addresses
                SET full_name    = ?,
                    phone        = ?,
                    email        = ?,
                    address_line = ?,
                    apartment    = ?,
                    city         = ?,
                    state        = ?,
                    pincode      = ?,
                    updated_at   = NOW()
                WHERE id = ?
            ")->execute([
                $addr['full_name']    ?? '',
                $addr['phone']        ?? '',
                $addr['email']        ?? '',
                $addr['address_line'] ?? '',
                $addr['apartment']    ?? '',
                $addr['city']         ?? '',
                $addr['state']        ?? '',
                $addr['pincode']      ?? '',
                $existing['id'],
            ]);
        } else {
            $conn->prepare("
                INSERT INTO customer_addresses
                    (user_id, label, full_name, phone, email,
                     address_line, apartment, city, state, pincode, is_default)
                VALUES (?, 'Home', ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                $userId,
                $addr['full_name']    ?? '',
                $addr['phone']        ?? '',
                $addr['email']        ?? '',
                $addr['address_line'] ?? '',
                $addr['apartment']    ?? '',
                $addr['city']         ?? '',
                $addr['state']        ?? '',
                $addr['pincode']      ?? '',
            ]);
        }

        // Also keep users table in sync for backward-compat
        $conn->prepare("
            UPDATE users
            SET name    = COALESCE(NULLIF(?, ''), name),
                email   = COALESCE(NULLIF(?, ''), email),
                address = COALESCE(NULLIF(?, ''), address),
                city    = COALESCE(NULLIF(?, ''), city),
                state   = COALESCE(NULLIF(?, ''), state),
                pincode = COALESCE(NULLIF(?, ''), pincode)
            WHERE id = ?
        ")->execute([
            $addr['full_name']    ?? '',
            $addr['email']        ?? '',
            $addr['address_line'] ?? '',
            $addr['city']         ?? '',
            $addr['state']        ?? '',
            $addr['pincode']      ?? '',
            $userId,
        ]);

        return true;

    } catch (Exception $e) {
        error_log("saveCustomerAddress error: " . $e->getMessage());
        return false;
    }
}

// ============================================================================
// ENHANCED WALLET SYSTEM FUNCTIONS (FROM ORIGINAL - SUPERIOR VERSION)
// ============================================================================

/**
 * BULLETPROOF: Create or update wallet for user with complete error handling (ENHANCED VERSION)
 * @param int $userId User ID
 * @return int|false Wallet ID or false on failure
 */
function ensureUserWallet($userId) {
    // Validate input
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        error_log("Invalid user ID provided to ensureUserWallet: " . var_export($userId, true));
        return false;
    }
    
    try {
        $conn = getConnection();
        
        // CRITICAL: First verify user exists in database
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() == 0) {
            error_log("User ID {$userId} does not exist in users table");
            // Clear invalid session if user is logged in with non-existent ID
            if (function_exists('isLoggedIn') && isLoggedIn() && function_exists('getCurrentUserId') && getCurrentUserId() == $userId) {
                session_destroy();
                header('Location: index.php?error=user_not_found');
                exit;
            }
            return false;
        }
        
        // Check if wallet exists
        $stmt = $conn->prepare("SELECT id FROM wallet WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() == 0) {
            // Create new wallet with all required columns
            $stmt = $conn->prepare("INSERT INTO wallet (user_id, points, pending_points, total_earned, total_claimed) VALUES (?, 0, 0, 0, 0)");
            $stmt->execute([$userId]);
            $walletId = $conn->lastInsertId();
            
            if (!$walletId) {
                error_log("Failed to create wallet for user {$userId}");
                return false;
            }
            
            return $walletId;
        } else {
            $wallet = $stmt->fetch();
            return $wallet['id'];
        }
        
    } catch (Exception $e) {
        error_log("Database error in ensureUserWallet for user {$userId}: " . $e->getMessage());
        return false;
    }
}

/**
 * BULLETPROOF: Get user's wallet balance with complete error handling (ENHANCED VERSION)
 * @param int $userId User ID
 * @return array Wallet balance information
 */
function getWalletBalance($userId) {
    // Validate input
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        error_log("Invalid user ID in getWalletBalance: " . var_export($userId, true));
        return ['points' => 0, 'pending_points' => 0, 'total_earned' => 0, 'total_claimed' => 0];
    }
    
    try {
        $conn = getConnection();
        
        // CRITICAL: First verify user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() == 0) {
            error_log("User ID {$userId} does not exist when getting wallet balance");
            // Clear invalid session
            if (function_exists('isLoggedIn') && isLoggedIn() && function_exists('getCurrentUserId') && getCurrentUserId() == $userId) {
                session_destroy();
            }
            return ['points' => 0, 'pending_points' => 0, 'total_earned' => 0, 'total_claimed' => 0];
        }
        
        // Get wallet balance
        $stmt = $conn->prepare("SELECT points, pending_points, total_earned, total_claimed FROM wallet WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            $balance = $stmt->fetch();
            
            // Ensure all values are numeric
            return [
                'points' => floatval($balance['points'] ?? 0),
                'pending_points' => floatval($balance['pending_points'] ?? 0),
                'total_earned' => floatval($balance['total_earned'] ?? 0),
                'total_claimed' => floatval($balance['total_claimed'] ?? 0)
            ];
        } else {
            // Create wallet if it doesn't exist
            $walletId = ensureUserWallet($userId);
            if ($walletId) {
                return ['points' => 0, 'pending_points' => 0, 'total_earned' => 0, 'total_claimed' => 0];
            } else {
                error_log("Failed to create wallet for user {$userId}");
                return ['points' => 0, 'pending_points' => 0, 'total_earned' => 0, 'total_claimed' => 0];
            }
        }
        
    } catch (Exception $e) {
        error_log("Database error in getWalletBalance for user {$userId}: " . $e->getMessage());
        return ['points' => 0, 'pending_points' => 0, 'total_earned' => 0, 'total_claimed' => 0];
    }
}

/**
 * BULLETPROOF: Add points to user's wallet with complete error handling (ENHANCED VERSION)
 * @param int $userId User ID
 * @param float $points Points to add
 * @param string $description Transaction description
 * @param string $type Transaction type (earned, bonus, etc.)
 * @param int|null $referralPurchaseId Reference ID for referral purchase
 * @return array Result with success status
 */
function addWalletPoints($userId, $points, $description = '', $type = 'earned', $referralPurchaseId = null) {
    // Validate inputs
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        error_log("Invalid user ID in addWalletPoints: " . var_export($userId, true));
        return ['success' => false, 'message' => 'Invalid user ID'];
    }
    
    if (!is_numeric($points) || $points <= 0) {
        error_log("Invalid points amount in addWalletPoints: " . var_export($points, true));
        return ['success' => false, 'message' => 'Invalid points amount'];
    }
    
    try {
        $conn = getConnection();
        
        // Ensure user has a wallet
        $walletId = ensureUserWallet($userId);
        if (!$walletId) {
            error_log("Could not ensure wallet for user {$userId}");
            return ['success' => false, 'message' => 'Failed to create wallet'];
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Update wallet with points
            $stmt = $conn->prepare("UPDATE wallet SET points = points + ?, total_earned = total_earned + ? WHERE id = ?");
            $stmt->execute([$points, $points, $walletId]);
            
            if ($stmt->rowCount() == 0) {
                throw new Exception("Failed to update wallet points for wallet ID {$walletId}");
            }
            
            // Add transaction record
            $stmt = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, points, transaction_type, reference_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$walletId, $points, $type, $referralPurchaseId, $description ?: "Points added"]);
            
            // Commit transaction
            $conn->commit();
            
            return ['success' => true, 'message' => 'Points added successfully'];
            
        } catch (Exception $e) {
            // Roll back transaction on error
            $conn->rollBack();
            error_log("Transaction error in addWalletPoints: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add points'];
        }
        
    } catch (Exception $e) {
        error_log("Database error in addWalletPoints: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add points'];
    }
}

/**
 * BULLETPROOF: Add points to user's wallet (LEGACY COMPATIBILITY)
 * @param int $userId User ID
 * @param float $points Points to add
 * @param int $referralPurchaseId Reference ID for referral purchase
 * @return bool Success status
 */
function addPointsToWallet($userId, $points, $referralPurchaseId) {
    $result = addWalletPoints($userId, $points, "Referral earning from purchase", 'earned', $referralPurchaseId);
    return $result['success'];
}

/**
 * Deduct wallet points
 * @param int $userId User ID
 * @param float $pointsToDeduct Points to deduct
 * @param string $description Transaction description
 * @return array Result with success status
 */
function deductWalletPoints($userId, $pointsToDeduct, $description = '') {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        return ['success' => false, 'message' => 'Invalid user ID'];
    }
    
    if (!is_numeric($pointsToDeduct) || $pointsToDeduct <= 0) {
        return ['success' => false, 'message' => 'Invalid points amount'];
    }
    
    try {
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Get wallet info
        $balance = getWalletBalance($userId);
        $availablePoints = $balance['points'] + $balance['pending_points'];
        
        if ($pointsToDeduct > $availablePoints) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Insufficient points'];
        }
        
        $walletId = ensureUserWallet($userId);
        if (!$walletId) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Wallet not found'];
        }
        
        // Deduct from regular points first, then pending (PRESERVED LOGIC FROM CHECKOUT.PHP)
        $pointsFromRegular = min($pointsToDeduct, $balance['points']);
        $pointsFromPending = $pointsToDeduct - $pointsFromRegular;
        
        if ($pointsFromRegular > 0) {
            $stmt = $conn->prepare("UPDATE wallet SET points = points - ? WHERE user_id = ?");
            $stmt->execute([$pointsFromRegular, $userId]);
        }
        
        if ($pointsFromPending > 0) {
            $stmt = $conn->prepare("UPDATE wallet SET pending_points = pending_points - ? WHERE user_id = ?");
            $stmt->execute([$pointsFromPending, $userId]);
        }
        
        // Record transaction
        $stmt = $conn->prepare("
            INSERT INTO wallet_transactions 
            (wallet_id, points, transaction_type, description) 
            VALUES (?, ?, 'used', ?)
        ");
        $stmt->execute([$walletId, -$pointsToDeduct, $description ?: "Points used in transaction"]);
        
        $conn->commit();
        
        return ['success' => true, 'message' => 'Points deducted successfully'];
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Database error in deductWalletPoints: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to deduct points'];
    }
}

// ============================================================================
// REFERRAL SYSTEM FUNCTIONS (PRESERVED FROM ORIGINAL - ENHANCED VERSION)
// ============================================================================

/**
 * Generate a unique referral code (PRESERVED EXACTLY from original)
 * @param int $length Length of the code
 * @return string Unique referral code
 */
function generateReferralCode($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    // Check if code already exists (PRESERVED)
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id FROM referrals WHERE code = ?");
        $stmt->execute([$code]);
        
        if ($stmt->rowCount() > 0) {
            // Code exists, generate a new one (PRESERVED)
            return generateReferralCode($length);
        }
    } catch (Exception $e) {
        error_log("Error checking referral code uniqueness: " . $e->getMessage());
        // Continue with current code if database check fails
    }
    
    return $code;
}

/**
 * Generate a referral link (UPDATED FOR PRODUCTION DOMAIN)
 * @param string $code Referral code
 * @return string Complete referral link
 */
function generateReferralLink($code) {
    // Production domain (change this to your client's domain)
    $baseUrl = "https://bluefifth.in/";
    return $baseUrl . "?ref=" . $code;
}

/**
 * Generate unique referral code for user (E-COMMERCE VERSION)
 * @param int $userId User ID
 * @return string|null Referral code or null on failure
 */
function generateReferralCodeForUser($userId) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        return null;
    }
    
    try {
        $conn = getConnection();
        
        // Check if user already has a referral code
        $stmt = $conn->prepare("SELECT code FROM referrals WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            $existing = $stmt->fetch();
            return $existing['code'];
        }
        
        // Generate unique code
        $maxAttempts = 10;
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $code = generateReferralCode(6);
            
            // Check if code is unique
            $stmt = $conn->prepare("SELECT id FROM referrals WHERE code = ?");
            $stmt->execute([$code]);
            
            if ($stmt->rowCount() == 0) {
                // Create referral record
                $stmt = $conn->prepare("
                    INSERT INTO referrals 
                    (user_id, code, purchase_count, total_earnings, created_at) 
                    VALUES (?, ?, 0, 0, NOW())
                ");
                $stmt->execute([$userId, $code]);
                
                return $code;
            }
            
            $attempts++;
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Database error in generateReferralCodeForUser: " . $e->getMessage());
        return null;
    }
}

/**
 * Get referral by code
 * @param string $code Referral code
 * @return array|null Referral data or null if not found
 */
function getReferralByCode($code) {
    if (empty($code)) {
        return null;
    }
    
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("
            SELECT r.*, u.name as user_name, u.email as user_email
            FROM referrals r
            JOIN users u ON r.user_id = u.id
            WHERE r.code = ?
        ");
        $stmt->execute([$code]);
        
        return $stmt->rowCount() > 0 ? $stmt->fetch() : null;
        
    } catch (Exception $e) {
        error_log("Database error in getReferralByCode: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if the current date is month end (PRESERVED EXACTLY from original)
 * @return bool True if today is 30th or 31st
 */
function isMonthEnd() {
    $currentDay = date('j'); // Day of month without leading zeros (1-31)
    $daysInMonth = date('t'); // Total days in current month (28-31)
    
    // Allow claims on 30th and 31st (if month has that day)
    return ($currentDay == 30 || $currentDay == 31) && $currentDay <= $daysInMonth;
}

/**
 * Calculate earning rate based on months since referral creation (PRESERVED EXACTLY from original)
 * @param string $referralCreatedAt Referral creation date
 * @return float Earning rate percentage
 */
function calculateEarningRate($referralCreatedAt) {
    try {
        $referralDate = new DateTime($referralCreatedAt);
        $currentDate = new DateTime();
        $interval = $referralDate->diff($currentDate);
        $monthsSinceCreation = ($interval->y * 12) + $interval->m + 1;
        
        return ($monthsSinceCreation == 1) ? 10.0 : 5.0;
    } catch (Exception $e) {
        error_log("Error calculating earning rate: " . $e->getMessage());
        return 5.0; // Default to 5% if calculation fails
    }
}

/**
 * Get months since referral creation (PRESERVED EXACTLY from original)
 * @param string $referralCreatedAt Referral creation date
 * @return int Number of months since creation
 */
function getMonthsSinceReferral($referralCreatedAt) {
    try {
        $referralDate = new DateTime($referralCreatedAt);
        $currentDate = new DateTime();
        $interval = $referralDate->diff($currentDate);
        
        return ($interval->y * 12) + $interval->m + 1;
    } catch (Exception $e) {
        error_log("Error getting months since referral: " . $e->getMessage());
        return 1; // Default to month 1 if calculation fails
    }
}

/**
 * Process order referral with 7-day hold system - REPLACE YOUR EXISTING FUNCTION
 * @param int $orderId Order ID
 * @param string $orderNumber Order number
 * @param float $finalAmount Final order amount
 * @param string $referralCode Referral code
 * @param int $buyerUserId Buyer user ID
 * @return array Result with success status
 */
function processOrderReferral($orderId, $orderNumber, $finalAmount, $referralCode, $buyerUserId) {
    if (empty($referralCode) || $finalAmount <= 0) {
        return ['success' => false, 'message' => 'Invalid referral parameters'];
    }
    
    try {
        $conn = getConnection();
        
        // Start transaction for data consistency
        $conn->beginTransaction();
        
        // Find the referral with created_at date
        $stmt = $conn->prepare("SELECT id, user_id, created_at FROM referrals WHERE code = ?");
        $stmt->execute([$referralCode]);
        
        if ($stmt->rowCount() == 0) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Invalid referral code'];
        }
        
        $referral = $stmt->fetch();
        
        // Don't give points if user is buying from their own referral
        if ($referral['user_id'] == $buyerUserId) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Cannot use own referral code'];
        }
        
        // Check if this order was already processed (prevent duplicates)
        $stmt = $conn->prepare("SELECT id FROM referral_purchases WHERE order_id = ?");
        $stmt->execute([$orderNumber]);
        if ($stmt->rowCount() > 0) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Order already processed for referral'];
        }
        
        // Calculate months since referral creation
        $referralCreatedAt = new DateTime($referral['created_at']);
        $purchaseDate = new DateTime();
        $interval = $referralCreatedAt->diff($purchaseDate);
        $monthsSinceCreation = ($interval->y * 12) + $interval->m + 1;
        
        // Calculate earning rate based on month
        if ($monthsSinceCreation == 1) {
            $earningRate = 10.0; // First month: 10%
        } else {
            $earningRate = 5.0;  // Other months: 5%
        }
        
        // Calculate points
        $referralPoints = floor(($finalAmount * $earningRate) / 100);
        
        if ($referralPoints <= 0) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Order amount too small for points'];
        }
        
        // HOLD SYSTEM: Calculate hold until date (7 days from now)
        $holdUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Record referral purchase with HOLD STATUS
        $stmt = $conn->prepare("
            INSERT INTO referral_purchases 
            (referral_id, order_id, amount, points_earned, status, purchase_month, earning_rate, created_at, hold_until, hold_status) 
            VALUES (?, ?, ?, ?, 'credited', ?, ?, NOW(), ?, 'hold')
        ");
        $stmt->execute([
            $referral['id'], 
            $orderNumber, 
            $finalAmount, 
            $referralPoints, 
            $monthsSinceCreation,
            $earningRate,
            $holdUntil
        ]);
        $purchaseId = $conn->lastInsertId();
        
        // Ensure user has a wallet
        $walletId = ensureUserWallet($referral['user_id']);
        if (!$walletId) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Failed to ensure referrer wallet'];
        }
        
        // DO NOT ADD POINTS TO WALLET YET - They are on hold
        // Just log the transaction for tracking purposes
        try {
            $stmt = $conn->prepare("
                INSERT INTO wallet_transactions 
                (wallet_id, points, transaction_type, reference_id, description, created_at) 
                VALUES (?, ?, 'held', ?, ?, NOW())
            ");
            $stmt->execute([
                $walletId, 
                $referralPoints, 
                $purchaseId, 
                "Referral points on hold from order {$orderNumber} (Month {$monthsSinceCreation} - {$earningRate}%) - Release on {$holdUntil}"
            ]);
        } catch (Exception $e) {
            // Continue if wallet_transactions fails (table might have different structure)
            error_log("Wallet transaction logging failed: " . $e->getMessage());
        }
        
        // Update referral stats (but don't add to total_earnings yet)
        $stmt = $conn->prepare("
            UPDATE referrals 
            SET purchase_count = purchase_count + 1
            WHERE id = ?
        ");
        $stmt->execute([$referral['id']]);
        
        $conn->commit();
        
        error_log("HOLD SYSTEM: Referral processed with HOLD - Order {$orderNumber}, Points {$referralPoints}, Rate {$earningRate}%, Month {$monthsSinceCreation}, Release on {$holdUntil}");
        
        return [
            'success' => true,
            'message' => 'Referral processed - points will be credited after 7 days if no return is initiated',
            'points_earned' => $referralPoints,
            'earning_rate' => $earningRate,
            'month' => $monthsSinceCreation,
            'referrer_id' => $referral['user_id'],
            'hold_until' => $holdUntil,
            'hold_status' => 'hold'
        ];
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log("Database error in processOrderReferral: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to process referral: ' . $e->getMessage()];
    }
}

/**
 * Enhanced function to cancel held points when return is approved
 * Add this to your functions.php file or update the existing function
 * 
 * @param string $orderNumber Order number
 * @return array Result with detailed information
 */
function cancelHeldPointsForReturn($orderNumber) {
    try {
        $conn = getConnection();
        
        // Check if we're already in a transaction to avoid nested transaction issues
        $inTransaction = $conn->inTransaction();
        
        if (!$inTransaction) {
            $conn->beginTransaction();
        }
        
        // Find held points for this order
        $stmt = $conn->prepare("
            SELECT 
                rp.id, 
                rp.points_earned, 
                rp.created_at,
                rp.hold_until,
                r.user_id as referrer_id,
                r.code as referral_code,
                u.name as referrer_name,
                u.email as referrer_email
            FROM referral_purchases rp
            JOIN referrals r ON rp.referral_id COLLATE utf8mb4_unicode_ci = r.id COLLATE utf8mb4_unicode_ci
            LEFT JOIN users u ON r.user_id = u.id
            WHERE rp.order_id = ? 
            AND rp.hold_status = 'hold'
            AND rp.status = 'credited'
        ");
        $stmt->execute([$orderNumber]);
        
        $heldPurchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($heldPurchases)) {
            if (!$inTransaction) {
                $conn->commit();
            }
            return [
                'success' => true, 
                'message' => 'No held points found for this order',
                'canceled_points' => 0,
                'affected_purchases' => 0,
                'referrers_affected' => []
            ];
        }
        
        $totalCanceledPoints = 0;
        $referrersAffected = [];
        
        foreach ($heldPurchases as $purchase) {
            // FIXED: Remove updated_at column reference
            $stmt = $conn->prepare("
                UPDATE referral_purchases 
                SET hold_status = 'canceled'
                WHERE id = ?
            ");
            $stmt->execute([$purchase['id']]);
            
            $totalCanceledPoints += $purchase['points_earned'];
            
            // Track referrer info for notifications
            $referrersAffected[] = [
                'referrer_id' => $purchase['referrer_id'],
                'referrer_name' => $purchase['referrer_name'],
                'referrer_email' => $purchase['referrer_email'],
                'referral_code' => $purchase['referral_code'],
                'canceled_points' => $purchase['points_earned'],
                'order_number' => $orderNumber
            ];
            
            // Log wallet transaction for cancellation (for transparency)
            $walletId = ensureUserWallet($purchase['referrer_id']);
            if ($walletId) {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO wallet_transactions 
                        (wallet_id, points, transaction_type, reference_id, description, created_at)
                        VALUES (?, ?, 'return_canceled', ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $walletId,
                        -$purchase['points_earned'], // Negative to show cancellation
                        $purchase['id'],
                        "Held referral points canceled - Order {$orderNumber} returned and approved by admin"
                    ]);
                } catch (Exception $e) {
                    // Continue if wallet_transactions fails (table might have different structure)
                    error_log("Wallet transaction logging failed during cancellation: " . $e->getMessage());
                }
            }
            
            error_log("CANCELED: {$purchase['points_earned']} held points from purchase ID {$purchase['id']} for order {$orderNumber}");
        }
        
        // Only commit if we started the transaction
        if (!$inTransaction) {
            $conn->commit();
        }
        
        // Send notifications to affected referrers (outside of transaction)
        foreach ($referrersAffected as $referrer) {
            if ($referrer['referrer_email']) {
                try {
                    sendReferralPointsCancelledNotification($referrer);
                } catch (Exception $e) {
                    error_log("Failed to send cancellation notification: " . $e->getMessage());
                }
            }
        }
        
        error_log("HELD POINTS CANCELLATION COMPLETE: Order {$orderNumber}, Total canceled: {$totalCanceledPoints} points, Referrers affected: " . count($referrersAffected));
        
        return [
            'success' => true,
            'message' => "Canceled {$totalCanceledPoints} held points from " . count($heldPurchases) . " purchases",
            'canceled_points' => $totalCanceledPoints,
            'affected_purchases' => count($heldPurchases),
            'referrers_affected' => $referrersAffected
        ];
        
    } catch (Exception $e) {
        // Only rollback if we started the transaction
        if (isset($conn) && $conn->inTransaction() && !$inTransaction) {
            $conn->rollBack();
        }
        
        error_log("Error canceling held points for order {$orderNumber}: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        return [
            'success' => false,
            'message' => 'Failed to cancel held points: ' . $e->getMessage(),
            'canceled_points' => 0,
            'affected_purchases' => 0,
            'referrers_affected' => []
        ];
    }
}

/**
 * Send notification to referrer when their held points are canceled due to return
 * 
 * @param array $referrerData Referrer information
 * @return bool Success status
 */
function sendReferralPointsCancelledNotification($referrerData) {
    try {
        // Get email settings
        $siteName = getSetting('site_name', 'Bluefifth');
        
        $subject = "Referral Points Canceled - Return Approved | {$siteName}";
        
        $emailHtml = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .points-info { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 14px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0; color:#856404;'>⚠️ Referral Points Update</h2>
                </div>
                
                <p>Hello " . htmlspecialchars($referrerData['referrer_name'] ?: 'Valued Customer') . ",</p>
                
                <div class='alert-warning'>
                    <strong>Referral Points Canceled</strong><br>
                    We wanted to inform you that referral points from a recent purchase have been canceled due to a return approval.
                </div>
                
                <div class='points-info'>
                    <strong>Cancellation Details:</strong><br>
                    • <strong>Order Number:</strong> {$referrerData['order_number']}<br>
                    • <strong>Referral Code:</strong> {$referrerData['referral_code']}<br>
                    • <strong>Canceled Points:</strong> ₹{$referrerData['canceled_points']}<br>
                    • <strong>Reason:</strong> Customer return approved by admin
                </div>
                
                <p><strong>What This Means:</strong></p>
                <ul>
                    <li>The customer you referred has returned their purchase</li>
                    <li>Our admin team has approved their return request</li>
                    <li>As per our referral policy, points from returned orders are not eligible for rewards</li>
                    <li>These points were on hold and have now been canceled (not deducted from your wallet)</li>
                </ul>
                
                <p><strong>Your Referral Program:</strong></p>
                <ul>
                    <li>Continue sharing your referral code to earn more points</li>
                    <li>Points from non-returned orders will be credited to your wallet after 7 days</li>
                    <li>You can track all your referrals in your account dashboard</li>
                </ul>
                
                <p>Keep referring friends and family to earn great rewards! 🎁</p>
                
                <div class='footer'>
                    <p>Questions? Contact us at <a href='mailto:info@bluefifth.in'>info@bluefifth.in</a></p>
                    <p>Best regards,<br>The {$siteName} Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send email using your existing mailer system
        if (function_exists('mail')) {
            $headers = [
                'MIME-Version' => '1.0',
                'Content-type' => 'text/html; charset=UTF-8',
                'From' => "info@bluefifth.in",
                'Reply-To' => "info@bluefifth.in",
                'X-Mailer' => 'PHP/' . phpversion()
            ];
            
            $result = mail($referrerData['referrer_email'], $subject, $emailHtml, $headers);
            
            error_log("Referral cancellation email " . ($result ? 'sent successfully' : 'failed') . " to {$referrerData['referrer_email']}");
            return $result;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error sending referral cancellation notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get wallet summary including held points - ADD THIS NEW FUNCTION
 * @param int $userId User ID
 * @return array Wallet summary
 */
function getWalletSummary($userId) {
    try {
        $conn = getConnection();
        
        // Get regular wallet data
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(points, 0) as available_points,
                COALESCE(pending_points, 0) as pending_points,
                COALESCE(total_earned, 0) as total_earned,
                COALESCE(total_claimed, 0) as total_claimed,
                COALESCE(total_tax_paid, 0) as total_tax_paid
            FROM wallet 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();
        
        if (!$wallet) {
            // Create wallet if doesn't exist
            $stmt = $conn->prepare("INSERT INTO wallet (user_id, points, pending_points, total_earned, total_claimed, total_tax_paid) VALUES (?, 0, 0, 0, 0, 0)");
            $stmt->execute([$userId]);
            
            $wallet = [
                'available_points' => 0,
                'pending_points' => 0,
                'total_earned' => 0,
                'total_claimed' => 0,
                'total_tax_paid' => 0
            ];
        }
        
        // Get held points (points waiting for 7-day period)
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(rp.points_earned), 0) as held_points,
                COUNT(*) as held_orders,
                MIN(rp.hold_until) as earliest_release
            FROM referral_purchases rp
            JOIN referrals r ON rp.referral_id = r.id
            WHERE r.user_id = ? AND rp.hold_status = 'hold'
        ");
        $stmt->execute([$userId]);
        $heldData = $stmt->fetch();
        
        return [
            'available_points' => floatval($wallet['available_points']),
            'pending_points' => floatval($wallet['pending_points']),
            'held_points' => floatval($heldData['held_points'] ?? 0),
            'total_earned' => floatval($wallet['total_earned']),
            'total_claimed' => floatval($wallet['total_claimed']),
            'total_tax_paid' => floatval($wallet['total_tax_paid'] ?? 0),
            'held_orders_count' => intval($heldData['held_orders'] ?? 0),
            'earliest_release' => $heldData['earliest_release'] ?? null
        ];
        
    } catch (Exception $e) {
        error_log("Error getting wallet summary: " . $e->getMessage());
        return [
            'available_points' => 0,
            'pending_points' => 0,
            'held_points' => 0,
            'total_earned' => 0,
            'total_claimed' => 0,
            'total_tax_paid' => 0,
            'held_orders_count' => 0,
            'earliest_release' => null
        ];
    }
}

// ============================================================================
// ENHANCED EMAIL SYSTEM FUNCTIONS (FROM ORIGINAL - SUPERIOR VERSION)
// ============================================================================

/**
 * BULLETPROOF: Enhanced email notification with complete error handling (FROM ORIGINAL)
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $type Email type
 * @param string|null $htmlContent HTML content
 * @param string|null $toName Recipient name
 * @return bool Success status
 */
function sendEmailNotification($to, $subject, $message, $type = 'general', $htmlContent = null, $toName = null) {
    // Validate inputs
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address in sendEmailNotification: " . var_export($to, true));
        return false;
    }
    
    if (empty($subject) || empty($message)) {
        error_log("Empty subject or message in sendEmailNotification");
        return false;
    }
    
    // Load email configuration if available
    $emailConfig = null;
    if (file_exists(__DIR__ . '/email-config.php')) {
        try {
            $emailConfig = include __DIR__ . '/email-config.php';
        } catch (Exception $e) {
            error_log("Error loading email config: " . $e->getMessage());
        }
    }
    
    // Extract user name if not provided
    if (!$toName) {
        $toName = explode('@', $to)[0]; // Use part before @ as name
    }
    
    $emailSent = false;
    
    // Log the email attempt
    error_log("SENDING EMAIL:");
    error_log("TO: {$to} ({$toName})");
    error_log("SUBJECT: {$subject}");
    error_log("TYPE: {$type}");
    
    // Try Sendinblue first if configured
    if ($emailConfig && 
        isset($emailConfig['settings']['enabled']) && $emailConfig['settings']['enabled'] && 
        !empty($emailConfig['sendinblue']['api_key']) && 
        $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE' &&
        file_exists(__DIR__ . '/sendinblue-mailer.php')) {
        
        try {
            require_once __DIR__ . '/sendinblue-mailer.php';
            
            $mailer = new SendinblueMailer(
                $emailConfig['sendinblue']['api_key'],
                $emailConfig['sendinblue']['from_email'],
                $emailConfig['sendinblue']['from_name']
            );
            
            // Use HTML content if provided, otherwise convert text to basic HTML
            if (!$htmlContent) {
                $htmlContent = "<html><body style='font-family: Arial, sans-serif; line-height: 1.6;'>" . 
                              "<div style='max-width: 600px; margin: 0 auto; padding: 20px;'>" .
                              "<h2 style='color: #333;'>" . htmlspecialchars($subject) . "</h2>" .
                              "<div style='background: #f9f9f9; padding: 20px; border-radius: 5px;'>" .
                              nl2br(htmlspecialchars($message)) .
                              "</div>" .
                              "<p style='color: #666; margin-top: 20px;'>Best regards,<br>The Bluefifth Team</p>" .
                              "</div></body></html>";
            }
            
            $emailSent = $mailer->sendEmail($to, $toName, $subject, $htmlContent, $message);
            
            if ($emailSent) {
                error_log("✅ Email sent successfully via Sendinblue");
            } else {
                error_log("❌ Sendinblue sending failed");
            }
            
        } catch (Exception $e) {
            error_log("❌ Sendinblue error: " . $e->getMessage());
            $emailSent = false;
        }
    }
    
    // Fallback to PHP mail() if Sendinblue failed
    if (!$emailSent) {
        try {
            $fromEmail = ($emailConfig && isset($emailConfig['sendinblue']['from_email'])) 
                        ? $emailConfig['sendinblue']['from_email'] 
                        : 'noreply@yourdomain.com';
            
            $headers = [
                'From: ' . $fromEmail,
                'Reply-To: ' . $fromEmail,
                'X-Mailer: PHP/' . phpversion(),
                'Content-Type: text/plain; charset=UTF-8'
            ];
            
            $emailSent = mail($to, $subject, $message, implode("\r\n", $headers));
            
            if ($emailSent) {
                error_log("✅ Email sent via PHP mail() fallback");
            } else {
                error_log("❌ Both Sendinblue and PHP mail() failed");
            }
        } catch (Exception $e) {
            error_log("❌ PHP mail() error: " . $e->getMessage());
        }
    }
    
    // Store in database for tracking with error handling
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        try {
            $conn = getConnection();
            
            // First verify user exists
            $userId = getCurrentUserId();
            if ($userId) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                
                if ($stmt->rowCount() > 0) {
                    // User exists, safe to insert
                    $stmt = $conn->prepare("
                        INSERT INTO email_notifications 
                        (user_id, email_type, subject, message, sent_at, status) 
                        VALUES (?, ?, ?, ?, NOW(), ?)
                    ");
                    $status = $emailSent ? 'sent' : 'failed';
                    $stmt->execute([$userId, $type, $subject, $message, $status]);
                } else {
                    error_log("Cannot log email notification: User ID $userId not found");
                }
            }
            
        } catch (Exception $e) {
            error_log("Failed to log email notification: " . $e->getMessage());
        }
    }
    
    return $emailSent;
}

/**
 * BULLETPROOF: Helper function to log professional emails (FROM ORIGINAL)
 * @param string $userEmail User email
 * @param string $subject Email subject
 * @param string $message Email message
 * @return bool Success status
 */
function logProfessionalEmail($userEmail, $subject, $message) {
    if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email in logProfessionalEmail: " . var_export($userEmail, true));
        return false;
    }
    
    try {
        $conn = getConnection();
        
        // Get user ID from email
        $userStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $userStmt->execute([$userEmail]);
        
        if ($userStmt->rowCount() > 0) {
            $user = $userStmt->fetch();
            $stmt = $conn->prepare("
                INSERT INTO email_notifications 
                (user_id, email_type, subject, message, sent_at, status) 
                VALUES (?, ?, ?, ?, NOW(), 'sent')
            ");
            $stmt->execute([$user['id'], 'sendinblue_professional', $subject, $message]);
            return true;
        } else {
            error_log("User not found for email logging: " . $userEmail);
            return false;
        }
    } catch (Exception $e) {
        error_log("Email logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * BULLETPROOF: Send referral points earned email (FROM ORIGINAL - ENHANCED)
 * @param string $userEmail User email
 * @param string $userName User name
 * @param float $points Points earned
 * @param array $monthlyBreakdown Monthly breakdown data
 * @return bool Success status
 */
function sendReferralPointsEarnedEmail($userEmail, $userName, $points, $monthlyBreakdown) {
    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL) || empty($userName) || !is_numeric($points)) {
        error_log("Invalid parameters for sendReferralPointsEarnedEmail");
        return false;
    }
    
    $emailConfig = null;
    if (file_exists(__DIR__ . '/email-config.php')) {
        try {
            $emailConfig = include __DIR__ . '/email-config.php';
        } catch (Exception $e) {
            error_log("Error loading email config: " . $e->getMessage());
            return false;
        }
    }
    
    if ($emailConfig && 
        isset($emailConfig['settings']['enabled']) && $emailConfig['settings']['enabled'] && 
        !empty($emailConfig['sendinblue']['api_key']) && 
        $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE' &&
        file_exists(__DIR__ . '/sendinblue-mailer.php')) {
        
        try {
            require_once __DIR__ . '/sendinblue-mailer.php';
            
            $mailer = new SendinblueMailer(
                $emailConfig['sendinblue']['api_key'],
                $emailConfig['sendinblue']['from_email'],
                $emailConfig['sendinblue']['from_name']
            );
            
            $result = $mailer->sendPointsEarnedEmail($userEmail, $userName, $points, $monthlyBreakdown);
            
            // Log the email in database
            if ($result) {
                logProfessionalEmail($userEmail, "You've Earned ₹{$points} in Referral Points!", "Points earned with monthly breakdown");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error sending points earned email: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Send claim reminder email (FROM ORIGINAL - ENHANCED)
 * @param string $userEmail User email
 * @param string $userName User name
 * @param int $currentDay Current day of month
 * @param float $availablePoints Available points
 * @return bool Success status
 */
function sendClaimReminderEmail($userEmail, $userName, $currentDay, $availablePoints) {
    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL) || empty($userName)) {
        error_log("Invalid parameters for sendClaimReminderEmail");
        return false;
    }
    
    $emailConfig = null;
    if (file_exists(__DIR__ . '/email-config.php')) {
        try {
            $emailConfig = include __DIR__ . '/email-config.php';
        } catch (Exception $e) {
            error_log("Error loading email config: " . $e->getMessage());
            return false;
        }
    }
    
    if ($emailConfig && 
        isset($emailConfig['settings']['enabled']) && $emailConfig['settings']['enabled'] && 
        !empty($emailConfig['sendinblue']['api_key']) && 
        $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE' &&
        file_exists(__DIR__ . '/sendinblue-mailer.php')) {
        
        try {
            require_once __DIR__ . '/sendinblue-mailer.php';
            
            $mailer = new SendinblueMailer(
                $emailConfig['sendinblue']['api_key'],
                $emailConfig['sendinblue']['from_email'],
                $emailConfig['sendinblue']['from_name']
            );
            
            $result = $mailer->sendClaimReminderEmail($userEmail, $userName, $currentDay, $availablePoints);
            
            // Log the email in database
            if ($result) {
                logProfessionalEmail($userEmail, "Referral Claim Reminder - Month End Approaching", "Claim reminder for month-end");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error sending claim reminder email: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Send claim submitted email (FROM ORIGINAL - ENHANCED)
 * @param string $userEmail User email
 * @param string $userName User name
 * @param string $claimId Claim ID
 * @param float $amount Claim amount
 * @param array $breakdown Monthly breakdown
 * @return bool Success status
 */
function sendClaimSubmittedEmail($userEmail, $userName, $claimId, $amount, $breakdown) {
    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL) || empty($userName) || !$claimId || !is_numeric($amount)) {
        error_log("Invalid parameters for sendClaimSubmittedEmail");
        return false;
    }
    
    $emailConfig = null;
    if (file_exists(__DIR__ . '/email-config.php')) {
        try {
            $emailConfig = include __DIR__ . '/email-config.php';
        } catch (Exception $e) {
            error_log("Error loading email config: " . $e->getMessage());
            return false;
        }
    }
    
    if ($emailConfig && 
        isset($emailConfig['settings']['enabled']) && $emailConfig['settings']['enabled'] && 
        !empty($emailConfig['sendinblue']['api_key']) && 
        $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE' &&
        file_exists(__DIR__ . '/sendinblue-mailer.php')) {
        
        try {
            require_once __DIR__ . '/sendinblue-mailer.php';
            
            $mailer = new SendinblueMailer(
                $emailConfig['sendinblue']['api_key'],
                $emailConfig['sendinblue']['from_email'],
                $emailConfig['sendinblue']['from_name']
            );
            
            $result = $mailer->sendClaimSubmittedEmail($userEmail, $userName, $claimId, $amount, $breakdown);
            
            // Log the email in database
            if ($result) {
                logProfessionalEmail($userEmail, "Claim Submitted - ₹{$amount} Processing", "Claim submission confirmation");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error sending claim submitted email: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Send payment processed email (FROM ORIGINAL - ENHANCED)
 * @param string $userEmail User email
 * @param string $userName User name
 * @param float $amount Payment amount
 * @param array $breakdown Monthly breakdown
 * @return bool Success status
 */
function sendPaymentProcessedEmail($userEmail, $userName, $amount, $breakdown) {
    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL) || empty($userName) || !is_numeric($amount)) {
        error_log("Invalid parameters for sendPaymentProcessedEmail");
        return false;
    }
    
    $emailConfig = null;
    if (file_exists(__DIR__ . '/email-config.php')) {
        try {
            $emailConfig = include __DIR__ . '/email-config.php';
        } catch (Exception $e) {
            error_log("Error loading email config: " . $e->getMessage());
            return false;
        }
    }
    
    if ($emailConfig && 
        isset($emailConfig['settings']['enabled']) && $emailConfig['settings']['enabled'] && 
        !empty($emailConfig['sendinblue']['api_key']) && 
        $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE' &&
        file_exists(__DIR__ . '/sendinblue-mailer.php')) {
        
        try {
            require_once __DIR__ . '/sendinblue-mailer.php';
            
            $mailer = new SendinblueMailer(
                $emailConfig['sendinblue']['api_key'],
                $emailConfig['sendinblue']['from_email'],
                $emailConfig['sendinblue']['from_name']
            );
            
            $result = $mailer->sendPaymentProcessedEmail($userEmail, $userName, $amount, $breakdown);
            
            // Log the email in database
            if ($result) {
                logProfessionalEmail($userEmail, "Payment Processed - ₹{$amount} Transferred!", "Payment confirmation");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error sending payment processed email: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

// ============================================================================
// ADMIN DASHBOARD FUNCTIONS (FROM ORIGINAL - ENHANCED)
// ============================================================================

/**
 * BULLETPROOF: Get all users for admin dashboard (FROM ORIGINAL)
 * @return array Users list with wallet and referral data
 */
function getAllUsersForAdmin() {
    try {
        $conn = getConnection();
        $stmt = $conn->query("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.profile_image,
                u.created_at,
                u.last_login,
                COALESCE(w.points, 0) as points,
                COALESCE(w.total_earned, 0) as total_earned,
                COALESCE(w.total_claimed, 0) as total_claimed,
                r.code as referral_code,
                COALESCE(r.purchase_count, 0) as purchase_count,
                COALESCE(r.total_earnings, 0) as referral_earnings
            FROM users u
            LEFT JOIN wallet w ON u.id = w.user_id
            LEFT JOIN referrals r ON u.id = r.user_id
            ORDER BY u.created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Database error in getAllUsersForAdmin: " . $e->getMessage());
        return [];
    }
}

/**
 * BULLETPROOF: Get system-wide stats (FROM ORIGINAL)
 * @return array System statistics
 */
function getSystemStats() {
    try {
        $conn = getConnection();
        
        $stmt = $conn->query("
            SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT r.id) as total_referrers,
                COUNT(DISTINCT rp.id) as total_purchases,
                COALESCE(SUM(rp.amount), 0) as total_sales,
                COALESCE(SUM(rp.points_earned), 0) as total_points_earned,
                COALESCE(SUM(w.total_claimed), 0) as total_money_paid
            FROM users u
            LEFT JOIN referrals r ON u.id = r.user_id
            LEFT JOIN referral_purchases rp ON r.id = rp.referral_id
            LEFT JOIN wallet w ON u.id = w.user_id
        ");
        
        $stats = $stmt->fetch();
        
        // Ensure all values are numeric
        return [
            'total_users' => intval($stats['total_users'] ?? 0),
            'total_referrers' => intval($stats['total_referrers'] ?? 0),
            'total_purchases' => intval($stats['total_purchases'] ?? 0),
            'total_sales' => floatval($stats['total_sales'] ?? 0),
            'total_points_earned' => floatval($stats['total_points_earned'] ?? 0),
            'total_money_paid' => floatval($stats['total_money_paid'] ?? 0)
        ];
    } catch (Exception $e) {
        error_log("Database error in getSystemStats: " . $e->getMessage());
        return [
            'total_users' => 0,
            'total_referrers' => 0,
            'total_purchases' => 0,
            'total_sales' => 0,
            'total_points_earned' => 0,
            'total_money_paid' => 0
        ];
    }
}

/**
 * BULLETPROOF: Get monthly referral breakdown for user (FROM ORIGINAL)
 * @param int $userId User ID
 * @return array Monthly breakdown data
 */
function getMonthlyReferralBreakdown($userId) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        error_log("Invalid user ID in getMonthlyReferralBreakdown: " . var_export($userId, true));
        return [];
    }
    
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("
            SELECT 
                rp.purchase_month,
                rp.earning_rate,
                COUNT(*) as purchase_count,
                SUM(rp.points_earned) as month_points,
                SUM(rp.amount) as month_sales
            FROM referral_purchases rp
            JOIN referrals r ON rp.referral_id = r.id
            WHERE r.user_id = ?
            GROUP BY rp.purchase_month, rp.earning_rate
            ORDER BY rp.purchase_month
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Database error in getMonthlyReferralBreakdown: " . $e->getMessage());
        return [];
    }
}

/**
 * Get monthly earning breakdown for user (E-COMMERCE COMPATIBILITY)
 * @param int $userId User ID
 * @return array Monthly breakdown
 */
function getMonthlyEarningBreakdown($userId) {
    return getMonthlyReferralBreakdown($userId); // Use the enhanced version from original
}

// ============================================================================
// CLAIM PROCESSING FUNCTIONS (FROM ORIGINAL - ENHANCED)
// ============================================================================

/**
 * NEW: Check if user can claim points (FROM ORIGINAL - API READY)
 * @param int $userId User ID
 * @return array Claim status information
 */
function canUserClaimPoints($userId) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        return [
            'can_claim' => false,
            'reason' => 'Invalid user ID',
            'available_points' => 0,
            'next_claim_date' => null
        ];
    }
    
    try {
        // Check if it's month end
        $isMonthEnd = isMonthEnd();
        
        // Get user's wallet balance
        $balance = getWalletBalance($userId);
        $availablePoints = $balance['points'];
        
        // Check minimum points requirement
        $minPointsRequired = defined('MIN_POINTS_TO_CLAIM') ? MIN_POINTS_TO_CLAIM : 100;
        
        $canClaim = $isMonthEnd && $availablePoints >= $minPointsRequired;
        
        // Calculate next claim date
        $currentDay = date('j');
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        if ($currentDay < 30) {
            $nextClaimDate = date('Y-m-30', mktime(0, 0, 0, $currentMonth, 30, $currentYear));
        } else {
            // Next month's 30th
            $nextMonth = $currentMonth + 1;
            $nextYear = $currentYear;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            $nextClaimDate = date('Y-m-30', mktime(0, 0, 0, $nextMonth, 30, $nextYear));
        }
        
        $reason = '';
        if (!$isMonthEnd) {
            $reason = "Claims only allowed on 30th and 31st of each month";
        } elseif ($availablePoints < $minPointsRequired) {
            $reason = "Minimum ₹{$minPointsRequired} required. You have ₹{$availablePoints}";
        }
        
        return [
            'can_claim' => $canClaim,
            'reason' => $reason,
            'available_points' => $availablePoints,
            'minimum_required' => $minPointsRequired,
            'is_month_end' => $isMonthEnd,
            'current_day' => $currentDay,
            'next_claim_date' => $nextClaimDate
        ];
        
    } catch (Exception $e) {
        error_log("Error in canUserClaimPoints: " . $e->getMessage());
        return [
            'can_claim' => false,
            'reason' => 'System error occurred',
            'available_points' => 0,
            'next_claim_date' => null
        ];
    }
}

/**
 * NEW: Process claim request with full error handling (FROM ORIGINAL)
 * @param int $userId User ID
 * @return array Claim processing result
 */
function processClaimRequest($userId) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        return [
            'success' => false,
            'message' => 'Invalid user ID',
            'claim_id' => null
        ];
    }
    
    try {
        $conn = getConnection();
        
        // Verify user exists
        $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() == 0) {
            return [
                'success' => false,
                'message' => 'User not found',
                'claim_id' => null
            ];
        }
        
        $user = $stmt->fetch();
        
        // Check if user can claim
        $claimStatus = canUserClaimPoints($userId);
        
        if (!$claimStatus['can_claim']) {
            // Send reminder email if available points > 0
            if ($claimStatus['available_points'] > 0) {
                sendClaimReminderEmail($user['email'], $user['name'], $claimStatus['current_day'], $claimStatus['available_points']);
            }
            
            return [
                'success' => false,
                'message' => $claimStatus['reason'],
                'claim_id' => null,
                'email_sent' => $claimStatus['available_points'] > 0
            ];
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Create claim record
            $stmt = $conn->prepare("INSERT INTO claims (user_id, points_claimed, money_value, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->execute([$userId, $claimStatus['available_points'], $claimStatus['available_points']]);
            $claimId = $conn->lastInsertId();
            
            if (!$claimId) {
                throw new Exception("Failed to create claim record");
            }
            
            // Update wallet - reset points and update total_claimed
            $stmt = $conn->prepare("UPDATE wallet SET points = 0, total_claimed = total_claimed + ? WHERE user_id = ?");
            $stmt->execute([$claimStatus['available_points'], $userId]);
            
            if ($stmt->rowCount() == 0) {
                throw new Exception("Failed to update wallet");
            }
            
            // Update referral purchases status to 'claimed'
            $stmt = $conn->prepare("
                UPDATE referral_purchases rp
                JOIN referrals r ON rp.referral_id = r.id
                SET rp.status = 'claimed'
                WHERE r.user_id = ? AND rp.status = 'credited'
            ");
            $stmt->execute([$userId]);
            
            // Add wallet transaction record
            $walletId = ensureUserWallet($userId);
            if ($walletId) {
                $stmt = $conn->prepare("
                    INSERT INTO wallet_transactions 
                    (wallet_id, points, transaction_type, reference_id, description) 
                    VALUES (?, ?, 'claimed', ?, ?)
                ");
                $stmt->execute([$walletId, -$claimStatus['available_points'], $claimId, "Claimed ₹{$claimStatus['available_points']} on " . date('Y-m-d')]);
            }
            
            // Commit transaction
            $conn->commit();
            
            // Get monthly breakdown for email
            $breakdown = getMonthlyReferralBreakdown($userId);
            
            // Send claim submitted email
            sendClaimSubmittedEmail($user['email'], $user['name'], $claimId, $claimStatus['available_points'], $breakdown);
            
            return [
                'success' => true,
                'message' => "Claim submitted successfully! Admin will process your payment of ₹{$claimStatus['available_points']}.",
                'claim_id' => $claimId,
                'amount' => $claimStatus['available_points'],
                'breakdown' => $breakdown,
                'email_sent' => true
            ];
            
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Transaction error in processClaimRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to process claim: ' . $e->getMessage(),
                'claim_id' => null
            ];
        }
        
    } catch (Exception $e) {
        error_log("Database error in processClaimRequest: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'System error occurred while processing claim',
            'claim_id' => null
        ];
    }
}

// ============================================================================
// UTILITY AND VALIDATION FUNCTIONS (FROM ORIGINAL - ENHANCED)
// ============================================================================

/**
 * Format currency (PRESERVED EXACTLY from original)
 * @param float $amount Amount to format
 * @return string Formatted currency
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Format points with currency equivalent (PRESERVED EXACTLY from original)
 * @param float $points Points to format
 * @return string Formatted points with currency
 */
function formatPoints($points) {
    return number_format($points) . ' points (' . formatCurrency($points) . ')';
}

/**
 * Get formatted wallet balance for display
 * @param int|null $userId User ID
 * @return string Formatted balance
 */
function getFormattedWalletBalance($userId) {
    if (!$userId) {
        return '₹0';
    }
    
    $balance = getWalletBalance($userId);
    $totalBalance = $balance['points'] + $balance['pending_points'];
    
    return '₹' . number_format($totalBalance);
}

/**
 * BULLETPROOF: Validate referral code (FROM ORIGINAL)
 * @param string $code Referral code to validate
 * @return bool True if valid
 */
function isValidReferralCode($code) {
    if (empty($code) || strlen($code) < 6) {
        return false;
    }
    
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id FROM referrals WHERE code = ?");
        // includes/functions.php - Part 3 (Continuation)

        $stmt->execute([$code]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Database error in isValidReferralCode: " . $e->getMessage());
        return false;
    }
}

/**
 * BULLETPROOF: Clean old visitor tracking (FROM ORIGINAL)
 * @param int $daysOld Number of days old to clean
 * @return int Number of records cleaned
 */
function cleanOldVisitorTracking($daysOld = 30) {
    if (!is_numeric($daysOld) || $daysOld <= 0) {
        $daysOld = 30; // Default to 30 days
    }
    
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("DELETE FROM referral_visits WHERE visited_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Failed to clean old visitor tracking: " . $e->getMessage());
        return 0;
    }
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool Is valid email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Calculate discount percentage
 * @param float $originalPrice Original price
 * @param float $discountedPrice Discounted price
 * @return float Discount percentage
 */
function calculateDiscountPercentage($originalPrice, $discountedPrice) {
    if ($originalPrice <= 0) {
        return 0;
    }
    
    return round((($originalPrice - $discountedPrice) / $originalPrice) * 100, 2);
}

// ============================================================================
// PRODUCT MANAGEMENT FUNCTIONS (E-COMMERCE SYSTEM)
// ============================================================================

/**
 * Get all products with category info - FIXED SQL
 * @param int|null $limit Limit number of results
 * @param int $offset Offset for pagination
 * @param int|null $categoryId Filter by category ID
 * @param string $status Product status filter
 * @return array Products list
 */
function getAllProducts($limit = null, $offset = 0, $categoryId = null, $status = 'active') {
    try {
        $conn = getConnection();
        
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
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        if ($categoryId) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        
        $sql .= " ORDER BY p.featured DESC, p.created_at DESC";
        
        // FIXED: Use direct integer values instead of parameterized LIMIT/OFFSET
        if ($limit) {
            $limitValue = intval($limit);
            $offsetValue = intval($offset);
            $sql .= " LIMIT {$limitValue} OFFSET {$offsetValue}";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getAllProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get featured products for homepage - FIXED SQL
 * @param int $limit Number of products to fetch
 * @return array Featured products
 */
function getFeaturedProducts($limit = 8) {
    try {
        $conn = getConnection();
        $limitValue = intval($limit);
        
        $stmt = $conn->prepare("
            SELECT 
                p.*,
                c.name as category_name,
                c.slug as category_slug,
                (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = TRUE LIMIT 1) as primary_image,
                CASE 
                    WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
                    WHEN p.stock_quantity <= p.low_stock_threshold THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' AND p.featured = TRUE
            ORDER BY p.created_at DESC
            LIMIT {$limitValue}
        ");
        $stmt->execute();
        
        $products = $stmt->fetchAll();
        
        // If we don't have enough featured products, get regular products
        if (count($products) < $limit) {
            $remaining = $limit - count($products);
            $remainingValue = intval($remaining);
            $featuredIds = array_column($products, 'id');
            
            $sql = "
                SELECT 
                    p.*,
                    c.name as category_name,
                    c.slug as category_slug,
                    (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = TRUE LIMIT 1) as primary_image,
                    CASE 
                        WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
                        WHEN p.stock_quantity <= p.low_stock_threshold THEN 'low_stock'
                        ELSE 'in_stock'
                    END as stock_status
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.status = 'active'
            ";
            
            $params = [];
            if (!empty($featuredIds)) {
                $placeholders = str_repeat('?,', count($featuredIds) - 1) . '?';
                $sql .= " AND p.id NOT IN ($placeholders)";
                $params = $featuredIds;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT {$remainingValue}";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $additionalProducts = $stmt->fetchAll();
            $products = array_merge($products, $additionalProducts);
        }
        
        return $products;
        
    } catch (Exception $e) {
        error_log("Database error in getFeaturedProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Enhanced getAllProducts function with better filtering - FIXED SQL
 * @param int|null $limit Limit number of results
 * @param int $offset Offset for pagination
 * @param int|null $categoryId Filter by category ID
 * @param string $status Product status filter
 * @param string $orderBy Order by clause
 * @return array Products list
 */
function getAllProductsEnhanced($limit = null, $offset = 0, $categoryId = null, $status = 'active', $orderBy = 'created_at DESC') {
    try {
        $conn = getConnection();
        
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
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        if ($categoryId) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        
        // Validate orderBy to prevent SQL injection
        $allowedOrderBy = ['created_at DESC', 'created_at ASC', 'name ASC', 'name DESC', 'price ASC', 'price DESC', 'featured DESC'];
        if (in_array($orderBy, $allowedOrderBy)) {
            $sql .= " ORDER BY p.{$orderBy}";
        } else {
            $sql .= " ORDER BY p.created_at DESC";
        }
        
        if ($limit) {
            $limitValue = intval($limit);
            $offsetValue = intval($offset);
            $sql .= " LIMIT {$limitValue} OFFSET {$offsetValue}";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getAllProductsEnhanced: " . $e->getMessage());
        return [];
    }
}

/**
 * Get new arrivals products - FIXED SQL
 * @param int $limit Number of products to fetch
 * @return array New arrival products
 */
function getNewArrivals($limit = 8) {
    try {
        $conn = getConnection();
        $limitValue = intval($limit);
        
        $stmt = $conn->prepare("
            SELECT 
                p.*,
                c.name as category_name,
                c.slug as category_slug,
                (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = TRUE LIMIT 1) as primary_image,
                CASE 
                    WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
                    WHEN p.stock_quantity <= p.low_stock_threshold THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
            AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY p.created_at DESC
            LIMIT {$limitValue}
        ");
        $stmt->execute();
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getNewArrivals: " . $e->getMessage());
        return [];
    }
}

/**
 * Get best selling products - FIXED SQL
 * @param int $limit Number of products to fetch
 * @return array Best selling products
 */
function getBestSellers($limit = 8) {
    try {
        $conn = getConnection();
        $limitValue = intval($limit);
        
        $stmt = $conn->prepare("
            SELECT 
                p.*,
                c.name as category_name,
                c.slug as category_slug,
                (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = TRUE LIMIT 1) as primary_image,
                COALESCE(SUM(oi.quantity), 0) as total_sold,
                CASE 
                    WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
                    WHEN p.stock_quantity <= p.low_stock_threshold THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status
            FROM products p
            JOIN categories c ON p.category_id = c.id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'paid'
            WHERE p.status = 'active'
            GROUP BY p.id
            ORDER BY total_sold DESC, p.created_at DESC
            LIMIT {$limitValue}
        ");
        $stmt->execute();
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getBestSellers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get related products - FIXED SQL
 * @param int $productId Current product ID
 * @param int $limit Number of related products
 * @return array Related products
 */
function getRelatedProducts($productId, $limit = 4) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return [];
    }
    
    try {
        $conn = getConnection();
        
        // First get the current product's category
        $stmt = $conn->prepare("SELECT category_id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $currentProduct = $stmt->fetch();
        
        if (!$currentProduct) {
            return [];
        }
        
        $limitValue = intval($limit);
        
        // Get related products from same category
        $stmt = $conn->prepare("
            SELECT 
                p.*,
                c.name as category_name,
                c.slug as category_slug,
                (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = TRUE LIMIT 1) as primary_image,
                CASE 
                    WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
                    WHEN p.stock_quantity <= p.low_stock_threshold THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' 
            AND p.category_id = ? 
            AND p.id != ?
            ORDER BY p.featured DESC, p.created_at DESC
            LIMIT {$limitValue}
        ");
        $stmt->execute([$currentProduct['category_id'], $productId]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getRelatedProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get product reviews/ratings (if you plan to add reviews)
 * @param int $productId Product ID
 * @return array Product reviews
 */
function getProductReviews($productId) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return [];
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                r.*,
                u.name as customer_name
            FROM product_reviews r
            JOIN users u ON r.user_id = u.id
            WHERE r.product_id = ? AND r.status = 'approved'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$productId]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getProductReviews: " . $e->getMessage());
        return [];
    }
}

/**
 * Get product average rating
 * @param int $productId Product ID
 * @return array Rating data
 */
function getProductRating($productId) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return ['average' => 0, 'count' => 0];
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                AVG(rating) as average_rating,
                COUNT(*) as review_count
            FROM product_reviews 
            WHERE product_id = ? AND status = 'approved'
        ");
        $stmt->execute([$productId]);
        
        $result = $stmt->fetch();
        
        return [
            'average' => round(floatval($result['average_rating'] ?? 0), 1),
            'count' => intval($result['review_count'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log("Database error in getProductRating: " . $e->getMessage());
        return ['average' => 0, 'count' => 0];
    }
}

/**
 * Enhanced updateProductStock function with operation type
 * @param int $productId Product ID
 * @param int $quantity Quantity
 * @param string $operation Operation type: 'set', 'add', 'subtract'
 * @return array Result
 */
function updateProductStock($productId, $quantity, $operation = 'set') {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return ['success' => false, 'message' => 'Invalid product ID'];
    }
    
    if (!is_numeric($quantity)) {
        return ['success' => false, 'message' => 'Invalid quantity'];
    }
    
    try {
        $conn = getConnection();
        
        switch ($operation) {
            case 'set':
                if ($quantity < 0) {
                    return ['success' => false, 'message' => 'Stock quantity cannot be negative'];
                }
                $sql = "UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?";
                $params = [$quantity, $productId];
                break;
                
            case 'add':
                $sql = "UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ?";
                $params = [$quantity, $productId];
                break;
                
            case 'subtract':
                // Check current stock first
                $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    return ['success' => false, 'message' => 'Product not found'];
                }
                
                if ($product['stock_quantity'] < $quantity) {
                    return ['success' => false, 'message' => 'Insufficient stock'];
                }
                
                $sql = "UPDATE products SET stock_quantity = stock_quantity - ?, updated_at = NOW() WHERE id = ?";
                $params = [$quantity, $productId];
                break;
                
            default:
                return ['success' => false, 'message' => 'Invalid operation'];
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Stock updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Product not found or no changes made'];
        }
        
    } catch (Exception $e) {
        error_log("Database error in updateProductStock: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update stock'];
    }
}
/**
 * Reduce product stock (for orders)
 * @param int $productId Product ID
 * @param int $quantity Quantity to reduce
 * @return array Result
 */
function reduceProductStock($productId, $quantity) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return ['success' => false, 'message' => 'Invalid product ID'];
    }
    
    if (!is_numeric($quantity) || $quantity <= 0) {
        return ['success' => false, 'message' => 'Invalid quantity'];
    }
    
    try {
        $conn = getConnection();
        
        // Check current stock
        $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        if ($product['stock_quantity'] < $quantity) {
            return ['success' => false, 'message' => 'Insufficient stock'];
        }
        
        // Reduce stock
        $stmt = $conn->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$quantity, $productId]);
        
        return ['success' => true, 'message' => 'Stock reduced successfully'];
        
    } catch (Exception $e) {
        error_log("Database error in reduceProductStock: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to reduce stock'];
    }
}

/**
 * Check if product is in stock
 * @param int $productId Product ID
 * @param int $quantity Required quantity
 * @return bool Is in stock
 */
function isProductInStock($productId, $quantity = 1) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return false;
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("
            SELECT stock_quantity 
            FROM products 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$productId]);
        
        $product = $stmt->fetch();
        
        return $product && $product['stock_quantity'] >= $quantity;
        
    } catch (Exception $e) {
        error_log("Database error in isProductInStock: " . $e->getMessage());
        return false;
    }
}

/**
 * Format product price with currency
 * @param float $price Price value
 * @param bool $showCurrency Show currency symbol
 * @return string Formatted price
 */
function formatProductPrice($price, $showCurrency = true) {
    $formatted = number_format($price, 2);
    
    if ($showCurrency) {
        return '₹' . $formatted;
    }
    
    return $formatted;
}

/**
 * Get status badge class for order status - FIXED IMPLEMENTATION
 * @param string $status Order status
 * @return string CSS class for badge
 */
function getStatusBadgeClass($status) {
    $statusMap = [
        'pending' => 'badge-warning',
        'processing' => 'badge-info', 
        'shipped' => 'badge-primary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
        'return_requested' => 'badge-warning',  // Add this line
        'returned' => 'badge-secondary'
    ];
    
    return $statusMap[strtolower($status)] ?? 'badge-secondary';
}

/**
 * Get payment status badge class - FIXED IMPLEMENTATION  
 * @param string $status Payment status
 * @return string CSS class for badge
 */
function getPaymentStatusBadgeClass($status) {
    $statusMap = [
        'pending' => 'badge-warning',
        'paid' => 'badge-success',
        'failed' => 'badge-danger', 
        'refunded' => 'badge-info',
        'processing' => 'badge-info'
    ];
    
    return $statusMap[strtolower($status)] ?? 'badge-secondary';
}

function getReturnStatusBadgeClass($status) {
    $statusMap = [
        'requested' => 'badge-warning',
        'pickup_scheduled' => 'badge-info',
        'collected' => 'badge-primary',
        'received' => 'badge-success',
        'processed' => 'badge-success',
        'rejected' => 'badge-danger'
    ];
    
    return $statusMap[strtolower($status)] ?? 'badge-secondary';
}

function createReturnTrackingData($order, $returnData) {
    $baseTime = strtotime($returnData['created_at']);
    $returnStatus = $returnData['return_status'];
    
    $events = [];
    
    if ($returnStatus === 'rejected') {
        // REJECTED PATH: Show chronological order
        $events[] = [
            'current_status' => 'Return Requested',
            'activity' => 'Your return request has been submitted',
            'date' => date('Y-m-d H:i:s', $baseTime),
            'status' => 'completed'
        ];
        
        $events[] = [
            'current_status' => 'Request Rejected',
            'activity' => 'Your return request has been rejected after review. Check your email for details.',
            'date' => date('Y-m-d H:i:s', $baseTime + 7200),
            'status' => 'rejected'
        ];
        
        $events[] = [
            'current_status' => 'Refund Cancelled',
            'activity' => 'No refund will be processed for this return',
            'date' => date('Y-m-d H:i:s', $baseTime + 7200),
            'status' => 'rejected'
        ];
    } else {
        // NORMAL PATH: Show progression in chronological order
        $events[] = [
            'current_status' => 'Return Requested',
            'activity' => 'Your return request has been submitted and is under review',
            'date' => date('Y-m-d H:i:s', $baseTime),
            'status' => 'completed'
        ];
        
        if (in_array($returnStatus, ['pickup_scheduled', 'collected', 'received', 'processed'])) {
            $events[] = [
                'current_status' => 'Return Initiated',
                'activity' => 'Your return has been approved and initiated',
                'date' => date('Y-m-d H:i:s', $baseTime + 3600),
                'status' => 'completed'
            ];
            
            $events[] = [
                'current_status' => 'Pickup Partner Scheduled',
                'activity' => 'Courier partner has been assigned for pickup',
                'date' => date('Y-m-d H:i:s', $baseTime + 7200),
                'status' => $returnStatus === 'pickup_scheduled' ? 'active' : 'completed'
            ];
        } else {
            $events[] = [
                'current_status' => 'Return Initiated',
                'activity' => 'Your return will be approved and initiated soon',
                'date' => '',
                'status' => $returnStatus === 'requested' ? 'active' : 'pending'
            ];
            
            $events[] = [
                'current_status' => 'Pickup Partner Scheduled',
                'activity' => 'Courier partner will be assigned for pickup',
                'date' => '',
                'status' => 'pending'
            ];
        }
        
        if (in_array($returnStatus, ['collected', 'received', 'processed'])) {
            $events[] = [
                'current_status' => 'Return Picked Up',
                'activity' => 'Your return package has been collected by courier',
                'date' => date('Y-m-d H:i:s', $baseTime + 86400),
                'status' => $returnStatus === 'collected' ? 'active' : 'completed'
            ];
        } else {
            $events[] = [
                'current_status' => 'Return Picked Up',
                'activity' => 'Your return package will be picked up',
                'date' => '',
                'status' => 'pending'
            ];
        }
        
        if ($returnStatus === 'processed') {
            $events[] = [
                'current_status' => 'Refunded',
                'activity' => 'Refund has been processed to your original payment method',
                'date' => date('Y-m-d H:i:s', $baseTime + 432000),
                'status' => 'completed'
            ];
        } else {
            $events[] = [
                'current_status' => 'Refunded',
                'activity' => 'Refund will be processed after inspection',
                'date' => '',
                'status' => 'pending'
            ];
        }
    }
    
    return [
        'tracking_data' => [
            'awb_code' => $returnData['return_awb_code'] ?: 'Return-' . $order['order_number'],
            'courier_name' => $returnStatus === 'rejected' ? 'Request Rejected' : 'Return Courier',
            'track_status' => $returnStatus === 'rejected' ? 'REJECTED' : ucfirst(str_replace('_', ' ', $returnStatus)),
            'edd' => $returnStatus === 'rejected' ? 'N/A' : date('M j, Y', $baseTime + 432000),
            'shipment_track' => $events, // Keep chronological order
            'is_return' => true,
            'return_status' => $returnStatus
        ]
    ];
}

/**
 * Get Shiprocket API token with auto-refresh
 * @return string|null Valid API token or null if unavailable
 */
function getShiprocketToken() {
    $existingToken = getSetting('shiprocket_api_token', '');
    $tokenExpiry = getSetting('shiprocket_token_expiry', '');
    
    // Check if token is still valid (expires in 1 hour buffer)
    if ($existingToken && $tokenExpiry && strtotime($tokenExpiry) > (time() + 3600)) {
        return $existingToken;
    }
    
    // Get new token if credentials available
    $email = getSetting('shiprocket_email', '');
    $password = getSetting('shiprocket_password', '');
    
    if (empty($email) || empty($password)) {
        return null;
    }
    
    return refreshShiprocketToken($email, $password);
}

/**
 * Refresh Shiprocket API token
 * @param string $email Shiprocket email
 * @param string $password Shiprocket password  
 * @return string|null New token or null on failure
 */
function refreshShiprocketToken($email, $password) {
    try {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://apiv2.shiprocket.in/v1/external/auth/login",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'email' => $email,
                'password' => $password
            ]),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "User-Agent: Velona-ECommerce/1.0"
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            error_log("Shiprocket token refresh cURL error: " . $error);
            return null;
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $token = $data['token'] ?? null;
            
            if ($token) {
                // Save new token with 10-day expiry
                setSetting('shiprocket_api_token', $token);
                setSetting('shiprocket_token_expiry', date('Y-m-d H:i:s', time() + (10 * 24 * 60 * 60)));
                return $token;
            }
        }
        
        error_log("Shiprocket token refresh failed: HTTP {$httpCode} - {$response}");
        return null;
        
    } catch (Exception $e) {
        error_log("Shiprocket token refresh exception: " . $e->getMessage());
        return null;
    }
}

/**
 * Get mock tracking data for testing (when Shiprocket credentials not available)
 * @param string $orderNumber Order number
 * @param string $status Current order status
 * @return array Mock tracking data
 */
function getMockTrackingData($orderNumber, $status) {
    $baseDate = time() - (3 * 24 * 60 * 60); // 3 days ago
    
    $mockData = [
        'tracking_data' => [
            'awb_code' => 'MOCK' . $orderNumber,
            'courier_name' => 'Mock Express',
            'track_status' => strtoupper($status),
            'edd' => date('Y-m-d', $baseDate + (2 * 24 * 60 * 60)),
            'shipment_track' => []
        ]
    ];
    
    // Generate mock timeline based on status
    $timeline = [
        ['title' => 'Order Confirmed', 'description' => 'Your order has been confirmed', 'date' => date('Y-m-d H:i:s', $baseDate), 'status' => 'completed'],
        ['title' => 'Processing', 'description' => 'Order is being processed', 'date' => date('Y-m-d H:i:s', $baseDate + 3600), 'status' => 'completed']
    ];
    
    switch (strtolower($status)) {
        case 'shipped':
        case 'delivered':
            $timeline[] = ['title' => 'Shipped', 'description' => 'Package has been shipped', 'date' => date('Y-m-d H:i:s', $baseDate + 7200), 'status' => 'completed'];
            if ($status === 'delivered') {
                $timeline[] = ['title' => 'Delivered', 'description' => 'Package delivered successfully', 'date' => date('Y-m-d H:i:s', $baseDate + 10800), 'status' => 'completed'];
            }
            break;
        case 'cancelled':
            $timeline[] = ['title' => 'Cancelled', 'description' => 'Order has been cancelled', 'date' => date('Y-m-d H:i:s', $baseDate + 1800), 'status' => 'cancelled'];
            break;
    }
    
    $mockData['tracking_data']['shipment_track'] = $timeline;
    return $mockData;
}

/**
 * Get comprehensive tracking data (real API + mock fallback)
 * @param string $orderId Order ID
 * @param string $orderNumber Order number  
 * @param string $status Current order status
 * @return array|null Tracking data or null
 */
function getComprehensiveTrackingData($orderId, $orderNumber, $status) {
    try {
        $conn = getConnection();
        
        // Get order shipping details
        $stmt = $conn->prepare("
            SELECT shiprocket_shipment_id, shiprocket_order_id, tracking_number 
            FROM orders 
            WHERE id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            return null;
        }
        
        // Try real Shiprocket API first if shipment ID exists
        if (!empty($order['shiprocket_shipment_id'])) {
            $token = getShiprocketToken();
            if ($token) {
                $realData = getShiprocketTrackingData($order['shiprocket_shipment_id'], $order['tracking_number']);
                if ($realData) {
                    return $realData;
                }
            }
        }
        
        // Fallback to mock data for demonstration
        return getMockTrackingData($orderNumber, $status);
        
    } catch (Exception $e) {
        error_log("Error in getComprehensiveTrackingData: " . $e->getMessage());
        return getMockTrackingData($orderNumber, $status);
    }
}

/**
 * Get product URL
 * @param int $productId Product ID
 * @param string $slug Product slug (optional)
 * @return string Product URL
 */
function getProductUrl($productId, $slug = '') {
    if (!empty($slug)) {
        return "shop/product.php?id={$productId}&slug=" . urlencode($slug);
    }
    
    return "shop/product.php?id={$productId}";
}

/**
 * Get category products count
 * @param int $categoryId Category ID
 * @return int Product count
 */
function getCategoryProductCount($categoryId) {
    if (!$categoryId || !is_numeric($categoryId) || $categoryId <= 0) {
        return 0;
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM products 
            WHERE category_id = ? AND status = 'active'
        ");
        $stmt->execute([$categoryId]);
        
        $result = $stmt->fetch();
        return intval($result['count'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Database error in getCategoryProductCount: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get single product with all details
 * @param int $productId Product ID
 * @param bool $includeImages Include product images
 * @return array|null Product data or null if not found
 */
function getProductById($productId, $includeImages = true) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        error_log("Invalid product ID in getProductById: " . var_export($productId, true));
        return null;
    }
    
    try {
        $conn = getConnection();
        
        // Get product with category info
        $stmt = $conn->prepare("
            SELECT 
                p.*,
                c.name as category_name,
                c.slug as category_slug,
                CASE 
                    WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
                    WHEN p.stock_quantity <= p.low_stock_threshold THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        
        if ($stmt->rowCount() == 0) {
            return null;
        }
        
        $product = $stmt->fetch();
        
        // Decode sizes JSON
        if ($product['sizes']) {
            $product['sizes'] = json_decode($product['sizes'], true) ?: [];
        } else {
            $product['sizes'] = [];
        }
        
        // Get product images if requested
        if ($includeImages) {
            $stmt = $conn->prepare("
                SELECT image_url, alt_text, is_primary, sort_order 
                FROM product_images 
                WHERE product_id = ? 
                ORDER BY is_primary DESC, sort_order ASC
            ");
            $stmt->execute([$productId]);
            $product['images'] = $stmt->fetchAll();
        }
        
        return $product;
        
    } catch (Exception $e) {
        error_log("Database error in getProductById: " . $e->getMessage());
        return null;
    }
}

/**
 * Get product by ID with admin flag compatibility
 * @param int $productId Product ID
 * @param bool $includeImages Include product images (admin compatibility)
 * @return array|null Product data or null if not found
 */
function getProductByIdAdmin($productId, $includeImages = true) {
    return getProductById($productId, $includeImages);
}

/**
 * Get products by category - FIXED SQL
 * @param string $categorySlug Category slug
 * @param int|null $limit Limit number of results
 * @param int $offset Offset for pagination
 * @return array Products list
 */
function getProductsByCategory($categorySlug, $limit = null, $offset = 0) {
    if (empty($categorySlug)) {
        return [];
    }
    
    try {
        $conn = getConnection();
        
        // First get category ID
        $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ? AND status = 'active'");
        $stmt->execute([$categorySlug]);
        
        if ($stmt->rowCount() == 0) {
            return [];
        }
        
        $category = $stmt->fetch();
        return getAllProducts($limit, $offset, $category['id']);
        
    } catch (Exception $e) {
        error_log("Database error in getProductsByCategory: " . $e->getMessage());
        return [];
    }
}

/**
 * Search products - FIXED SQL
 * @param string $searchTerm Search term
 * @param int $limit Limit number of results
 * @param int $offset Offset for pagination
 * @return array Products list
 */
function searchProducts($searchTerm, $limit = 20, $offset = 0) {
    if (empty($searchTerm)) {
        return [];
    }
    
    try {
        $conn = getConnection();
        $searchTerm = '%' . $searchTerm . '%';
        $limitValue = intval($limit);
        $offsetValue = intval($offset);
        
        $stmt = $conn->prepare("
            SELECT 
                p.*,
                c.name as category_name,
                (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = TRUE LIMIT 1) as primary_image
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' 
            AND (
                p.name LIKE ? 
                OR p.description LIKE ? 
                OR c.name LIKE ?
            )
            ORDER BY p.featured DESC, p.name ASC
            LIMIT {$limitValue} OFFSET {$offsetValue}
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in searchProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Create new product
 * @param array $productData Product data
 * @return array Result with success status and product ID
 */
function createProduct($productData) {
    $required = ['name', 'category_id', 'price'];
    foreach ($required as $field) {
        if (empty($productData[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }
    
    try {
        $conn = getConnection();
        
        // Generate unique slug
        $slug = generateProductSlug($productData['name']);
        
        $stmt = $conn->prepare("
            INSERT INTO products 
            (category_id, name, slug, description, care_instructions, price, stock_quantity, 
             low_stock_threshold, sizes, status, featured, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $productData['category_id'],
            $productData['name'],
            $slug,
            $productData['description'] ?? '',
            $productData['care_instructions'] ?? '',
            $productData['price'],
            $productData['stock_quantity'] ?? 0,
            $productData['low_stock_threshold'] ?? 10,
            json_encode($productData['sizes'] ?? []),
            $productData['status'] ?? 'active',
            $productData['featured'] ?? false
        ]);
        
        $productId = $conn->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Product created successfully',
            'product_id' => $productId,
            'slug' => $slug
        ];
        
    } catch (Exception $e) {
        error_log("Database error in createProduct: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create product'];
    }
}

/**
 * Update product
 * @param int $productId Product ID
 * @param array $productData Product data
 * @return array Result with success status
 */
function updateProduct($productId, $productData) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return ['success' => false, 'message' => 'Invalid product ID'];
    }
    
    try {
        $conn = getConnection();
        
        // Build dynamic update query
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'category_id', 'name', 'description', 'care_instructions', 
            'price', 'stock_quantity', 'low_stock_threshold', 'status', 'featured'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($productData[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $productData[$field];
            }
        }
        
        // Handle sizes separately
        if (isset($productData['sizes'])) {
            $updateFields[] = "sizes = ?";
            $params[] = json_encode($productData['sizes']);
        }
        
        // Update slug if name changed
        if (isset($productData['name'])) {
            $newSlug = generateProductSlug($productData['name'], $productId);
            $updateFields[] = "slug = ?";
            $params[] = $newSlug;
        }
        
        if (empty($updateFields)) {
            return ['success' => false, 'message' => 'No fields to update'];
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $productId;
        
        $sql = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return ['success' => true, 'message' => 'Product updated successfully'];
        
    } catch (Exception $e) {
        error_log("Database error in updateProduct: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update product'];
    }
}

/**
 * Delete product
 * @param int $productId Product ID
 * @return array Result with success status
 */
function deleteProduct($productId) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return ['success' => false, 'message' => 'Invalid product ID'];
    }
    
    try {
        $conn = getConnection();
        
        // Check if product has orders
        $stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM order_items WHERE product_id = ?");
        $stmt->execute([$productId]);
        $result = $stmt->fetch();
        
        if ($result['order_count'] > 0) {
            // Don't delete, just mark as inactive
            $stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$productId]);
            return ['success' => true, 'message' => 'Product marked as inactive (has order history)'];
        }
        
        // Safe to delete
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        
        return ['success' => true, 'message' => 'Product deleted successfully'];
        
    } catch (Exception $e) {
        error_log("Database error in deleteProduct: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete product'];
    }

}

/**
 * Get product sizes as array
 * @param int $productId Product ID
 * @return array Available sizes
 */
function getProductSizes($productId) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return ['XS', 'S', 'M', 'L', 'XL', 'XXL']; // Default sizes
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("SELECT sizes FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        
        if ($stmt->rowCount() > 0) {
            $product = $stmt->fetch();
            $sizes = json_decode($product['sizes'], true);
            
            if (is_array($sizes) && !empty($sizes)) {
                return $sizes;
            }
        }
        
        // Return default sizes if no sizes found
        return ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        
    } catch (Exception $e) {
        error_log("Database error in getProductSizes: " . $e->getMessage());
        return ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    }
}

/**
 * Sort products array by specified criteria
 * @param array $products Products array
 * @param string $sortType Sort type
 * @return array Sorted products
 */
function sortProductsArray($products, $sortType) {
    if (empty($products) || empty($sortType)) {
        return $products;
    }
    
    switch ($sortType) {
        case 'name_asc':
            usort($products, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            break;
            
        case 'name_desc':
            usort($products, function($a, $b) {
                return strcmp($b['name'], $a['name']);
            });
            break;
            
        case 'price_low':
            usort($products, function($a, $b) {
                return $a['price'] <=> $b['price'];
            });
            break;
            
        case 'price_high':
            usort($products, function($a, $b) {
                return $b['price'] <=> $a['price'];
            });
            break;
            
        case 'newest':
            usort($products, function($a, $b) {
                return strtotime($b['created_at']) <=> strtotime($a['created_at']);
            });
            break;
            
        case 'featured':
            usort($products, function($a, $b) {
                // Featured products first, then by creation date
                if ($a['featured'] && !$b['featured']) return -1;
                if (!$a['featured'] && $b['featured']) return 1;
                return strtotime($b['created_at']) <=> strtotime($a['created_at']);
            });
            break;
            
        default:
            // No sorting
            break;
    }
    
    return $products;
}

/**
 * Get total product count for a category
 * @param int|null $categoryId Category ID (null for all products)
 * @return int Total product count
 */
function getTotalProductCount($categoryId = null) {
    try {
        $conn = getConnection();
        
        $sql = "SELECT COUNT(*) as total FROM products WHERE status = 'active'";
        $params = [];
        
        if ($categoryId) {
            $sql .= " AND category_id = ?";
            $params[] = $categoryId;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return intval($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Database error in getTotalProductCount: " . $e->getMessage());
        return 0;
    }
}

/**
 * Enhanced product loading for category pages with sorting and pagination
 * @param string $categorySlug Category slug
 * @param int $page Current page
 * @param int $limit Products per page
 * @param string $sort Sort type
 * @return array Products data with pagination info
 */
function getProductsForCategory($categorySlug, $page = 1, $limit = 12, $sort = '') {
    $page = max(1, $page);
    $limit = in_array($limit, [12, 24, 48]) ? $limit : 12;
    $offset = ($page - 1) * $limit;
    
    try {
        $conn = getConnection();
        
        // If no category slug, get all products
        if (empty($categorySlug)) {
            $products = getAllProducts($limit, $offset);
            $totalProducts = getTotalProductCount();
            $category = null;
        } else {
            // Get category
            $category = getCategoryBySlug($categorySlug);
            if (!$category) {
                return [
                    'products' => [],
                    'total' => 0,
                    'category' => null,
                    'total_pages' => 0,
                    'current_page' => $page
                ];
            }
            
            // Get products for this category
            $products = getProductsByCategory($categorySlug, $limit, $offset);
            $totalProducts = getTotalProductCount($category['id']);
        }
        
        // Apply sorting if specified
        if (!empty($sort) && !empty($products)) {
            $products = sortProductsArray($products, $sort);
        }
        
        $totalPages = ceil($totalProducts / $limit);
        
        return [
            'products' => $products,
            'total' => $totalProducts,
            'category' => $category,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'items_per_page' => $limit
        ];
        
    } catch (Exception $e) {
        error_log("Database error in getProductsForCategory: " . $e->getMessage());
        return [
            'products' => [],
            'total' => 0,
            'category' => null,
            'total_pages' => 0,
            'current_page' => $page
        ];
    }
}


// ============================================================================
// CATEGORY MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Get all categories
 * @param string $status Category status filter
 * @return array Categories list
 */
function getAllCategories($status = 'active') {
    try {
        $conn = getConnection();
        
        $sql = "
            SELECT 
                c.*,
                COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
            WHERE 1=1
        ";
        
        $params = [];
        if ($status) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getAllCategories: " . $e->getMessage());
        return [];
    }
}

/**
 * Get category by slug
 * @param string $slug Category slug
 * @return array|null Category data or null if not found
 */
function getCategoryBySlug($slug) {
    if (empty($slug)) {
        return null;
    }
    
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ? AND status = 'active'");
        $stmt->execute([$slug]);
        
        return $stmt->rowCount() > 0 ? $stmt->fetch() : null;
        
    } catch (Exception $e) {
        error_log("Database error in getCategoryBySlug: " . $e->getMessage());
        return null;
    }
}

/**
 * Get category by ID
 * @param int $categoryId Category ID
 * @return array|null Category data or null if not found
 */
function getCategoryById($categoryId) {
    if (!$categoryId || !is_numeric($categoryId) || $categoryId <= 0) {
        return null;
    }
    
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        
        return $stmt->rowCount() > 0 ? $stmt->fetch() : null;
        
    } catch (Exception $e) {
        error_log("Database error in getCategoryById: " . $e->getMessage());
        return null;
    }
}

/**
 * Create new category
 * @param array $categoryData Category data
 * @return array Result with success status and category ID
 */
function createCategory($categoryData) {
    if (empty($categoryData['name'])) {
        return ['success' => false, 'message' => 'Category name is required'];
    }
    
    try {
        $conn = getConnection();
        
        // Generate unique slug
        $slug = generateCategorySlug($categoryData['name']);
        
        $stmt = $conn->prepare("
            INSERT INTO categories 
            (name, slug, description, image, status, sort_order, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $categoryData['name'],
            $slug,
            $categoryData['description'] ?? '',
            $categoryData['image'] ?? '',
            $categoryData['status'] ?? 'active',
            $categoryData['sort_order'] ?? 0
        ]);
        
        $categoryId = $conn->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Category created successfully',
            'category_id' => $categoryId,
            'slug' => $slug
        ];
        
    } catch (Exception $e) {
        error_log("Database error in createCategory: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create category'];
    }
}

/**
 * Get category navigation data for menu
 * @return array Categories with active status
 */
function getCategoryNavigation($currentCategorySlug = '') {
    try {
        $categories = getAllCategories('active');
        
        // Mark current category as active
        foreach ($categories as &$category) {
            $category['is_active'] = ($category['slug'] === $currentCategorySlug);
        }
        
        return $categories;
        
    } catch (Exception $e) {
        error_log("Error in getCategoryNavigation: " . $e->getMessage());
        return [];
    }
}

/**
 * Get navigation categories with product counts
 * @return array Categories with counts
 */
function getNavigationCategories() {
    try {
        $categories = getAllCategories('active');
        
        foreach ($categories as &$category) {
            $category['product_count'] = getCategoryProductCount($category['id']);
        }
        
        return $categories;
        
    } catch (Exception $e) {
        error_log("Error in getNavigationCategories: " . $e->getMessage());
        return [];
    }
}

/**
 * Initialize default system settings
 * @return bool Success status
 */
function initializeDefaultSettings() {
    $defaultSettings = [
        'site_name' => 'Bluefifth',
        'site_description' => 'Premium clothing with sustainable fashion',
        'contact_email' => 'info@Bluefifth.in',
        'contact_phone' => '+91 9876543210',
        'currency' => 'INR',
        'currency_symbol' => '₹',
        'tax_rate' => '18.0',
        'shipping_charge' => '50.0',
        'free_shipping_threshold' => '500.0',
        'min_order_amount' => '100.0',
        'max_cart_items' => '20',
        'items_per_page' => '12',
        'featured_products_limit' => '8',
        'related_products_limit' => '4',
        'new_arrivals_days' => '30',
        'low_stock_threshold' => '10',
        'enable_reviews' => 'true',
        'enable_wishlist' => 'true',
        'enable_notifications' => 'true',
        'maintenance_mode' => 'false'
    ];
    
    try {
        foreach ($defaultSettings as $key => $value) {
            $type = is_numeric($value) ? 'number' : (in_array($value, ['true', 'false']) ? 'boolean' : 'string');
            setSetting($key, $value, $type);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error in initializeDefaultSettings: " . $e->getMessage());
        return false;
    }
}

/**
 * Format category URL
 * @param string $categorySlug Category slug
 * @return string Category URL
 */
function getCategoryUrl($categorySlug) {
    if (empty($categorySlug)) {
        return 'category.php'; // All products
    }
    
    return 'category.php?category=' . urlencode($categorySlug);
}

/**
 * Generate breadcrumb data for category pages
 * @param array|null $category Category data
 * @return array Breadcrumb items
 */
function getCategoryBreadcrumb($category = null) {
    $breadcrumb = [
        ['name' => 'Home', 'url' => '../index.php', 'active' => false]
    ];
    
    if ($category) {
        $breadcrumb[] = [
            'name' => $category['name'],
            'url' => 'category.php?category=' . urlencode($category['slug']),
            'active' => true
        ];
    } else {
        $breadcrumb[] = [
            'name' => 'All Products',
            'url' => 'category.php',
            'active' => true
        ];
    }
    
    return $breadcrumb;
}

/**
 * Get SEO meta data for category pages
 * @param array|null $category Category data
 * @return array SEO meta data
 */
function getCategorySEOMeta($category = null) {
    if ($category) {
        return [
            'title' => $category['name'] . ' - Bluefifth',
            'description' => $category['description'] ?: "Browse our {$category['name']} collection at Bluefifth",
            'keywords' => $category['name'] . ', fashion, clothing, online shopping, Bluefifth'
        ];
    }
    
    return [
        'title' => 'All Products - Bluefifth',
        'description' => 'Browse our complete collection of fashion and clothing at Bluefifth',
        'keywords' => 'fashion, clothing, online shopping, Bluefifth, all products'
    ];
}

// ============================================================================
// SHOPPING CART FUNCTIONS
// ============================================================================

/**
 * Add item to cart - PERMANENTLY FIXED
 * @param int $userId User ID
 * @param int $productId Product ID
 * @param int $quantity Quantity to add
 * @param string|null $size Product size
 * @return array Result with success status
 */
function addToCart($userId, $productId, $quantity = 1, $size = null) {
    // Validate inputs
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        error_log("Invalid user ID in addToCart: " . var_export($userId, true));
        return ['success' => false, 'message' => 'Invalid user ID'];
    }
    
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        error_log("Invalid product ID in addToCart: " . var_export($productId, true));
        return ['success' => false, 'message' => 'Invalid product ID'];
    }
    
    if (!is_numeric($quantity) || $quantity <= 0) {
        $quantity = 1;
    }
    
    try {
        $conn = getConnection();
        
        // Verify user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() == 0) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify product exists and is available
        $product = getProductById($productId, false);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        if ($product['status'] !== 'active') {
            return ['success' => false, 'message' => 'Product not available'];
        }
        
        if ($product['stock_quantity'] < $quantity) {
            return ['success' => false, 'message' => 'Insufficient stock'];
        }
        
        // FIXED: Handle sizes properly - check if already decoded
        $availableSizes = [];
        if (!empty($product['sizes'])) {
            if (is_array($product['sizes'])) {
                // Already decoded by getProductById()
                $availableSizes = $product['sizes'];
            } elseif (is_string($product['sizes'])) {
                // Still JSON string, decode it
                $availableSizes = json_decode($product['sizes'], true) ?: [];
            }
        }
        
        // Validate size if provided
        if ($size && !empty($availableSizes)) {
            if (!in_array($size, $availableSizes)) {
                return ['success' => false, 'message' => 'Invalid size selected'];
            }
        }
        
        // Check if item already exists in cart
        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND (size = ? OR (size IS NULL AND ? IS NULL))");
        $stmt->execute([$userId, $productId, $size, $size]);
        
        if ($stmt->rowCount() > 0) {
            // Update existing cart item
            $cartItem = $stmt->fetch();
            $newQuantity = $cartItem['quantity'] + $quantity;
            
            // Check total stock
            if ($product['stock_quantity'] < $newQuantity) {
                return ['success' => false, 'message' => 'Cannot add more items - insufficient stock'];
            }
            
            $stmt = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newQuantity, $cartItem['id']]);
            
            return ['success' => true, 'message' => 'Cart updated successfully', 'action' => 'updated'];
        } else {
            // Add new cart item
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, size) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $productId, $quantity, $size]);
            
            return ['success' => true, 'message' => 'Item added to cart', 'action' => 'added'];
        }
        
    } catch (Exception $e) {
        error_log("Database error in addToCart: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add item to cart'];
    }
}

/**
 * Get user's cart items
 * @param int $userId User ID
 * @return array Cart items
 */
function getCartItems($userId) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        error_log("Invalid user ID in getCartItems: " . var_export($userId, true));
        return [];
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                p.name as product_name,
                p.price as product_price,
                p.stock_quantity,
                p.status as product_status,
                (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = TRUE LIMIT 1) as product_image,
                (c.quantity * p.price) as total_price
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ?
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getCartItems: " . $e->getMessage());
        return [];
    }
}

/**
 * Update cart item quantity
 * @param int $userId User ID
 * @param int $cartItemId Cart item ID
 * @param int $quantity New quantity
 * @return array Result with success status
 */
function updateCartItem($userId, $cartItemId, $quantity) {
    // Validate inputs
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        error_log("Invalid user ID in updateCartItem: " . var_export($userId, true));
        return ['success' => false, 'message' => 'Invalid user ID'];
    }
    
    if (!$cartItemId || !is_numeric($cartItemId) || $cartItemId <= 0) {
        error_log("Invalid cart item ID in updateCartItem: " . var_export($cartItemId, true));
        return ['success' => false, 'message' => 'Invalid cart item ID'];
    }
    
    if (!is_numeric($quantity) || $quantity < 0) {
        return ['success' => false, 'message' => 'Invalid quantity'];
    }
    
    try {
        $conn = getConnection();
        
        // Get cart item with product info
        $stmt = $conn->prepare("
            SELECT c.*, p.stock_quantity, p.name as product_name
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$cartItemId, $userId]);
        
        if ($stmt->rowCount() == 0) {
            return ['success' => false, 'message' => 'Cart item not found'];
        }
        
        $cartItem = $stmt->fetch();
        
        // If quantity is 0, remove item
        if ($quantity == 0) {
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cartItemId, $userId]);
            return ['success' => true, 'message' => 'Item removed from cart', 'action' => 'removed'];
        }
        
        // Check stock availability
        if ($quantity > $cartItem['stock_quantity']) {
            return ['success' => false, 'message' => 'Insufficient stock available'];
        }
        
        // Update quantity
        $stmt = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$quantity, $cartItemId, $userId]);
        
        return ['success' => true, 'message' => 'Cart updated successfully', 'action' => 'updated'];
        
    } catch (Exception $e) {
        error_log("Database error in updateCartItem: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update cart item'];
    }
}

/**
 * Remove item from cart
 * @param int $userId User ID
 * @param int $cartItemId Cart item ID
 * @return array Result with success status
 */
function removeFromCart($userId, $cartItemId) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        error_log("Invalid user ID in removeFromCart: " . var_export($userId, true));
        return ['success' => false, 'message' => 'Invalid user ID'];
    }
    
    if (!$cartItemId || !is_numeric($cartItemId) || $cartItemId <= 0) {
        error_log("Invalid cart item ID in removeFromCart: " . var_export($cartItemId, true));
        return ['success' => false, 'message' => 'Invalid cart item ID'];
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cartItemId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Item removed from cart'];
        } else {
            return ['success' => false, 'message' => 'Cart item not found'];
        }
        
    } catch (Exception $e) {
        error_log("Database error in removeFromCart: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to remove item from cart'];
    }
}

/**
 * Clear entire cart
 * @param int $userId User ID
 * @return array Result with success status
 */
function clearCart($userId) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        error_log("Invalid user ID in clearCart: " . var_export($userId, true));
        return ['success' => false, 'message' => 'Invalid user ID'];
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        return ['success' => true, 'message' => 'Cart cleared successfully'];
        
    } catch (Exception $e) {
        error_log("Database error in clearCart: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to clear cart'];
    }
}

/**
 * Get cart summary (totals)
 * @param int $userId User ID
 * @return array Cart summary with totals
 */
function getCartSummary($userId) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        return [
            'item_count' => 0,
            'total_amount' => 0,
            'items' => []
        ];
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as item_count,
                SUM(c.quantity) as total_quantity,
                SUM(c.quantity * p.price) as total_amount
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ? AND p.status = 'active'
        ");
        $stmt->execute([$userId]);
        
        $summary = $stmt->fetch();
        
        return [
            'item_count' => intval($summary['item_count'] ?? 0),
            'total_quantity' => intval($summary['total_quantity'] ?? 0),
            'total_amount' => floatval($summary['total_amount'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log("Database error in getCartSummary: " . $e->getMessage());
        return [
            'item_count' => 0,
            'total_quantity' => 0,
            'total_amount' => 0
        ];
    }
}

/**
 * Get cart count for navigation badge
 * @param int|null $userId User ID
 * @return int Cart item count
 */
function getCartItemCount($userId) {
    if (!$userId) {
        return 0;
    }
    
    $cartSummary = getCartSummary($userId);
    return intval($cartSummary['item_count'] ?? 0);
}

/**
 * AJAX cart response helper
 * @param bool $success Operation success
 * @param string $message Response message
 * @param array $data Additional data
 * @return array Response array
 */
function createCartResponse($success, $message, $data = []) {
    return array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => time()
    ], $data);
}

// ============================================================================
// SLUG GENERATION UTILITY FUNCTIONS
// ============================================================================

/**
 * Generate product slug
 * @param string $name Product name
 * @param int|null $productId Product ID (for updates)
 * @return string Generated slug
 */
function generateProductSlug($name, $productId = null) {
    if (empty($name)) {
        return '';
    }
    
    // Basic slug generation
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    try {
        $conn = getConnection();
        
        // Check if slug exists
        $originalSlug = $slug;
        $counter = 1;
        
        while (true) {
            $sql = "SELECT id FROM products WHERE slug = ?";
            $params = [$slug];
            
            if ($productId) {
                // includes/functions.php - Part 3 (Continued)

            $sql .= " AND id != ?";
            $params[] = $productId;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() == 0) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
    
} catch (Exception $e) {
    error_log("Error generating product slug: " . $e->getMessage());
    return $originalSlug . '-' . time();
}
}

/**
 * Complete generateCategorySlug function
 * @param string $name Category name
 * @param int|null $categoryId Category ID (for updates)
 * @return string Generated slug
 */
function generateCategorySlug($name, $categoryId = null) {
    if (empty($name)) {
        return '';
    }
    
    // Basic slug generation
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    try {
        $conn = getConnection();
        
        // Check if slug exists
        $originalSlug = $slug;
        $counter = 1;
        
        while (true) {
            $sql = "SELECT id FROM categories WHERE slug = ?";
            $params = [$slug];
            
            if ($categoryId) {
                $sql .= " AND id != ?";
                $params[] = $categoryId;
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() == 0) {
                break;
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
        
    } catch (Exception $e) {
        error_log("Error generating category slug: " . $e->getMessage());
        return $originalSlug . '-' . time();
    }
}

/**
 * Build pagination URL for category pages
 * @param int $page Page number
 * @param string $categorySlug Category slug
 * @param string $sort Sort parameter
 * @param int $limit Items per page
 * @return string Complete URL
 */
function buildPaginationUrl($page, $categorySlug, $sort = '', $limit = 12) {
    $params = [];
    
    if (!empty($categorySlug)) {
        $params['category'] = $categorySlug;
    }
    
    if ($page > 1) {
        $params['page'] = $page;
    }
    
    if (!empty($sort)) {
        $params['sort'] = $sort;
    }
    
    if ($limit != 12) {
        $params['limit'] = $limit;
    }
    
    $queryString = !empty($params) ? '?' . http_build_query($params) : '';
    return 'category.php' . $queryString;
}

/**
 * Check if current page is category page
 * @param string $categorySlug Current category slug
 * @return bool Is category page
 */
function isCategoryPage($categorySlug) {
    return !empty($categorySlug);
}

// ============================================================================
// ORDER MANAGEMENT FUNCTIONS
// ============================================================================

/**
* Create order from cart - EXACT REFERRAL INTEGRATION
* @param int $userId User ID
* @param array $shippingAddress Shipping address
* @param array|null $billingAddress Billing address
* @param float $walletPointsUsed Wallet points used
* @param string|null $referralCode Referral code
* @param string $paymentMethod Payment method
* @return array Result with success status and order details
*/
function createOrderFromCart($userId, $shippingAddress, $billingAddress = null, $walletPointsUsed = 0, $referralCode = null, $paymentMethod = 'razorpay') {
// Validate inputs
if (!$userId || !is_numeric($userId) || $userId <= 0) {
    error_log("Invalid user ID in createOrderFromCart: " . var_export($userId, true));
    return ['success' => false, 'message' => 'Invalid user ID'];
}

if (empty($shippingAddress)) {
    return ['success' => false, 'message' => 'Shipping address is required'];
}

try {
    $conn = getConnection();
    $conn->beginTransaction();
    
    // Get cart items
    $cartItems = getCartItems($userId);
    if (empty($cartItems)) {
        $conn->rollBack();
        return ['success' => false, 'message' => 'Cart is empty'];
    }
    
    // Validate all cart items and calculate total
    $totalAmount = 0;
    $validatedItems = [];
    
    foreach ($cartItems as $item) {
        if ($item['product_status'] !== 'active') {
            $conn->rollBack();
            return ['success' => false, 'message' => "Product '{$item['product_name']}' is no longer available"];
        }
        
        if ($item['stock_quantity'] < $item['quantity']) {
            $conn->rollBack();
            return ['success' => false, 'message' => "Insufficient stock for '{$item['product_name']}'"];
        }
        
        $totalAmount += $item['total_price'];
        $validatedItems[] = $item;
    }
    
    // Validate wallet points usage
    if ($walletPointsUsed > 0) {
        $balance = getWalletBalance($userId);
        $availablePoints = $balance['points'] + $balance['pending_points'];
        
        if ($walletPointsUsed > $availablePoints) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Insufficient wallet points'];
        }
        
        if ($walletPointsUsed > $totalAmount) {
            $walletPointsUsed = $totalAmount; // Cap at order total
        }
    }
    
    // Calculate final amount
    $finalAmount = max(0, $totalAmount - $walletPointsUsed);
    
    // Generate unique order number
    $orderNumber = 'VLN' . time() . rand(100, 999);
    
    // Create order record
    $stmt = $conn->prepare("
        INSERT INTO orders 
        (order_number, user_id, total_amount, wallet_points_used, final_amount, 
         shipping_address, billing_address, referral_code, payment_method, status, payment_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
    ");
    $stmt->execute([
        $orderNumber,
        $userId,
        $totalAmount,
        $walletPointsUsed,
        $finalAmount,
        json_encode($shippingAddress),
        json_encode($billingAddress ?: $shippingAddress),
        $referralCode,
        $paymentMethod
    ]);
    
    $orderId = $conn->lastInsertId();
    
    if (!$orderId) {
        $conn->rollBack();
        return ['success' => false, 'message' => 'Failed to create order'];
    }
    
    // Create order items
    foreach ($validatedItems as $item) {
        $stmt = $conn->prepare("
            INSERT INTO order_items 
            (order_id, product_id, product_name, product_price, quantity, size, total_price)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['product_name'],
            $item['product_price'],
            $item['quantity'],
            $item['size'],
            $item['total_price']
        ]);
    }
    
    // Deduct wallet points if used
    if ($walletPointsUsed > 0) {
        $result = deductWalletPoints($userId, $walletPointsUsed, $orderNumber);
        if (!$result['success']) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Failed to deduct wallet points: ' . $result['message']];
        }
    }
    
    // CRITICAL: Process referral tracking using EXACT checkout.php logic
    if ($referralCode) {
        $referralResult = processOrderReferral($orderId, $orderNumber, $finalAmount, $referralCode, $userId);
        if (!$referralResult['success']) {
            error_log("Referral processing failed for order {$orderNumber}: " . $referralResult['message']);
            // Don't fail the order, just log the error
        }
    }
    
    // Clear cart after successful order creation
    clearCart($userId);
    
    $conn->commit();
    
    return [
        'success' => true,
        'message' => 'Order created successfully',
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'total_amount' => $totalAmount,
        'wallet_points_used' => $walletPointsUsed,
        'final_amount' => $finalAmount
    ];
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Database error in createOrderFromCart: " . $e->getMessage());
    return ['success' => false, 'message' => 'Failed to create order: ' . $e->getMessage()];
}
}

/**
* Get order details
* @param int $orderId Order ID
* @param int|null $userId User ID (for security check)
* @return array|null Order data or null if not found
*/
function getOrderById($orderId, $userId = null) {
if (!$orderId || !is_numeric($orderId) || $orderId <= 0) {
    error_log("Invalid order ID in getOrderById: " . var_export($orderId, true));
    return null;
}

try {
    $conn = getConnection();
    
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
    
    if ($userId) {
        $sql .= " AND o.user_id = ?";
        $params[] = $userId;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() == 0) {
        return null;
    }
    
    $order = $stmt->fetch();
    
    // Decode JSON fields
    $order['shipping_address'] = json_decode($order['shipping_address'], true);
    $order['billing_address'] = json_decode($order['billing_address'], true);
    
    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, p.id as current_product_id
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$orderId]);
    $order['items'] = $stmt->fetchAll();
    
    return $order;
    
} catch (Exception $e) {
    error_log("Database error in getOrderById: " . $e->getMessage());
    return null;
}
}

/**
 * Get user orders - FIXED SQL
 * @param int $userId User ID
 * @param int $limit Limit number of results
 * @param int $offset Offset for pagination
 * @return array Orders list
 */
function getUserOrders($userId, $limit = 20, $offset = 0) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        return [];
    }

    try {
        $conn = getConnection();
        $limitValue = intval($limit);
        $offsetValue = intval($offset);
        
        $stmt = $conn->prepare("
            SELECT 
                o.*,
                COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT {$limitValue} OFFSET {$offsetValue}
        ");
        $stmt->execute([$userId]);
        
        $orders = $stmt->fetchAll();
        
        // Decode JSON fields for each order
        foreach ($orders as &$order) {
            $order['shipping_address'] = json_decode($order['shipping_address'], true);
            $order['billing_address'] = json_decode($order['billing_address'], true);
        }
        
        return $orders;
        
    } catch (Exception $e) {
        error_log("Database error in getUserOrders: " . $e->getMessage());
        return [];
    }
}

/**
* Update order status
* @param int $orderId Order ID
* @param string $status New status
* @param string $paymentStatus New payment status
* @return array Result with success status
*/
function updateOrderStatus($orderId, $status = null, $paymentStatus = null) {
if (!$orderId || !is_numeric($orderId) || $orderId <= 0) {
    return ['success' => false, 'message' => 'Invalid order ID'];
}

try {
    $conn = getConnection();
    
    $updateFields = [];
    $params = [];
    
    if ($status) {
        $updateFields[] = "status = ?";
        $params[] = $status;
    }
    
    if ($paymentStatus) {
        $updateFields[] = "payment_status = ?";
        $params[] = $paymentStatus;
    }
    
    if (empty($updateFields)) {
        return ['success' => false, 'message' => 'No fields to update'];
    }
    
    $updateFields[] = "updated_at = NOW()";
    $params[] = $orderId;
    
    $sql = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return ['success' => true, 'message' => 'Order status updated successfully'];
    
} catch (Exception $e) {
    error_log("Database error in updateOrderStatus: " . $e->getMessage());
    return ['success' => false, 'message' => 'Failed to update order status'];
}
}

// ============================================================================
// E-COMMERCE STATISTICS FUNCTIONS
// ============================================================================

/**
* Get e-commerce stats for admin dashboard
* @return array Statistics data
*/
function getEcommerceStats() {
try {
    $conn = getConnection();
    
    // Get basic stats
    $stmt = $conn->query("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT o.user_id) as total_customers,
            COUNT(DISTINCT p.id) as total_products,
            COUNT(DISTINCT c.id) as total_categories,
            COALESCE(SUM(o.final_amount), 0) as total_revenue,
            COALESCE(SUM(o.wallet_points_used), 0) as total_wallet_used,
            COALESCE(AVG(o.final_amount), 0) as average_order_value
        FROM orders o
        CROSS JOIN products p
        CROSS JOIN categories c
        WHERE o.payment_status = 'paid'
    ");
    
    $stats = $stmt->fetch();
    
    // Get recent orders count
    $stmt = $conn->query("
        SELECT COUNT(*) as recent_orders
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $recentStats = $stmt->fetch();
    $stats['recent_orders'] = $recentStats['recent_orders'];
    
    // Get low stock products
    $stmt = $conn->query("
        SELECT COUNT(*) as low_stock_products
        FROM products 
        WHERE stock_quantity <= low_stock_threshold AND status = 'active'
    ");
    $stockStats = $stmt->fetch();
    $stats['low_stock_products'] = $stockStats['low_stock_products'];
    
    // Ensure all values are numeric
    return [
        'total_orders' => intval($stats['total_orders'] ?? 0),
        'total_customers' => intval($stats['total_customers'] ?? 0),
        'total_products' => intval($stats['total_products'] ?? 0),
        'total_categories' => intval($stats['total_categories'] ?? 0),
        'total_revenue' => floatval($stats['total_revenue'] ?? 0),
        'total_wallet_used' => floatval($stats['total_wallet_used'] ?? 0),
        'average_order_value' => floatval($stats['average_order_value'] ?? 0),
        'recent_orders' => intval($stats['recent_orders'] ?? 0),
        'low_stock_products' => intval($stats['low_stock_products'] ?? 0)
    ];
    
} catch (Exception $e) {
    error_log("Database error in getEcommerceStats: " . $e->getMessage());
    return [
        'total_orders' => 0,
        'total_customers' => 0,
        'total_products' => 0,
        'total_categories' => 0,
        'total_revenue' => 0,
        'total_wallet_used' => 0,
        'average_order_value' => 0,
        'recent_orders' => 0,
        'low_stock_products' => 0
    ];
}
}

/**
 * Get recent orders for admin - FIXED SQL
 * @param int $limit Number of orders to fetch
 * @return array Recent orders list
 */
function getRecentOrders($limit = 10) {
    try {
        $conn = getConnection();
        $limitValue = intval($limit);
        
        $stmt = $conn->prepare("
            SELECT 
                o.*,
                u.name as customer_name,
                u.email as customer_email,
                COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT {$limitValue}
        ");
        $stmt->execute();
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getRecentOrders: " . $e->getMessage());
        return [];
    }
}

// ============================================================================
// EMAIL INTEGRATION FUNCTIONS (E-COMMERCE)
// ============================================================================

/**
* Send order confirmation email
* @param array $orderData Order data
* @return bool Success status
*/
function sendOrderConfirmationEmail($orderData) {
if (empty($orderData['customer_email']) || empty($orderData['order_number'])) {
    error_log("Missing email or order number for order confirmation");
    return false;
}

try {
    // Load email configuration
    $emailConfig = null;
    if (file_exists(__DIR__ . '/email-config.php')) {
        $emailConfig = include __DIR__ . '/email-config.php';
    }
    
    if ($emailConfig && 
        isset($emailConfig['settings']['enabled']) && $emailConfig['settings']['enabled'] && 
        !empty($emailConfig['sendinblue']['api_key']) && 
        $emailConfig['sendinblue']['api_key'] !== 'YOUR_SENDINBLUE_API_KEY_HERE' &&
        file_exists(__DIR__ . '/sendinblue-mailer.php')) {
        
        require_once __DIR__ . '/sendinblue-mailer.php';
        
        $mailer = new SendinblueMailer(
            $emailConfig['sendinblue']['api_key'],
            $emailConfig['sendinblue']['from_email'],
            $emailConfig['sendinblue']['from_name']
        );
        
        // Add sendOrderConfirmationEmail method to SendinblueMailer if not exists
        if (method_exists($mailer, 'sendOrderConfirmationEmail')) {
            $result = $mailer->sendOrderConfirmationEmail(
                $orderData['customer_email'],
                $orderData['customer_name'] ?? 'Valued Customer',
                $orderData
            );
        } else {
            // Fallback to generic email
            $subject = "Order Confirmation - {$orderData['order_number']}";
            $message = "Thank you for your order! Your order {$orderData['order_number']} has been confirmed.";
            $result = $mailer->sendEmail(
                $orderData['customer_email'],
                $orderData['customer_name'] ?? 'Valued Customer',
                $subject,
                $message,
                $message
            );
        }
        
        if ($result) {
            logProfessionalEmail($orderData['customer_email'], "Order Confirmation - {$orderData['order_number']}", "Order confirmation for order {$orderData['order_number']}");
        }
        
        return $result;
    }
    
    return false;
    
} catch (Exception $e) {
    error_log("Error sending order confirmation email: " . $e->getMessage());
    return false;
}
}

// ============================================================================
// REFERRAL ANALYTICS FUNCTIONS (ENHANCED FOR E-COMMERCE)
// ============================================================================

/**
* Get referral statistics for user (E-COMMERCE ENHANCED)
* @param int $userId User ID
* @return array Referral statistics
*/
function getUserReferralStats($userId) {
if (!$userId || !is_numeric($userId) || $userId <= 0) {
    return [
        'referral_code' => null,
        'total_purchases' => 0,
        'total_earnings' => 0,
        'current_month_earnings' => 0,
        'total_referred_users' => 0
    ];
}

try {
    $conn = getConnection();
    
    // Get referral code and basic stats
    $stmt = $conn->prepare("
        SELECT code, purchase_count, total_earnings, created_at
        FROM referrals 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() == 0) {
        // Generate referral code if doesn't exist
        $code = generateReferralCodeForUser($userId);
        return [
            'referral_code' => $code,
            'total_purchases' => 0,
            'total_earnings' => 0,
            'current_month_earnings' => 0,
            'total_referred_users' => 0
        ];
    }
    
    $referral = $stmt->fetch();
    
    // Get current month earnings
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(points_earned), 0) as current_month_earnings
        FROM referral_purchases rp
        WHERE rp.referral_id = (SELECT id FROM referrals WHERE user_id = ?)
        AND MONTH(rp.created_at) = MONTH(CURRENT_DATE())
        AND YEAR(rp.created_at) = YEAR(CURRENT_DATE())
        AND rp.status = 'credited'
    ");
    $stmt->execute([$userId]);
    $currentMonth = $stmt->fetch();
    
    // Get total referred users (unique users who made purchases)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.user_id) as total_referred_users
        FROM referral_purchases rp
        JOIN orders o ON rp.order_id = o.order_number
        WHERE rp.referral_id = (SELECT id FROM referrals WHERE user_id = ?)
        AND rp.status = 'credited'
    ");
    $stmt->execute([$userId]);
    $referredUsers = $stmt->fetch();
    
    return [
        'referral_code' => $referral['code'],
        'total_purchases' => intval($referral['purchase_count']),
        'total_earnings' => floatval($referral['total_earnings']),
        'current_month_earnings' => floatval($currentMonth['current_month_earnings'] ?? 0),
        'total_referred_users' => intval($referredUsers['total_referred_users'] ?? 0),
        'referral_created_at' => $referral['created_at']
    ];
    
} catch (Exception $e) {
    error_log("Database error in getUserReferralStats: " . $e->getMessage());
    return [
        'referral_code' => null,
        'total_purchases' => 0,
        'total_earnings' => 0,
        'current_month_earnings' => 0,
        'total_referred_users' => 0
    ];
}
}

/**
 * Get top referrers for admin dashboard - FIXED SQL
 * @param int $limit Number of top referrers to get
 * @return array Top referrers list
 */
function getTopReferrers($limit = 10) {
    try {
        $conn = getConnection();
        $limitValue = intval($limit);
        
        $stmt = $conn->prepare("
            SELECT 
                r.code,
                r.purchase_count,
                r.total_earnings,
                u.name as user_name,
                u.email as user_email,
                COUNT(DISTINCT rp.order_id) as unique_orders,
                MAX(rp.created_at) as last_purchase
            FROM referrals r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN referral_purchases rp ON r.id = rp.referral_id AND rp.status = 'credited'
            WHERE r.total_earnings > 0
            GROUP BY r.id
            ORDER BY r.total_earnings DESC, r.purchase_count DESC
            LIMIT {$limitValue}
        ");
        $stmt->execute();
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getTopReferrers: " . $e->getMessage());
        return [];
    }
}

// ============================================================================
// PRESERVED CONSTANTS (FROM ORIGINAL)
// ============================================================================

if (!defined('MIN_POINTS_TO_CLAIM')) {
define('MIN_POINTS_TO_CLAIM', 100);
}

// ============================================================================
// ADDITIONAL UTILITY FUNCTIONS
// ============================================================================

/**
* Generate order tracking number
* @return string Tracking number
*/
function generateTrackingNumber() {
return 'TRK' . date('Ymd') . rand(1000, 9999);
}

/**
* Log activity
* @param int $userId User ID
* @param string $action Action performed
* @param string $details Action details
* @return bool Success status
*/
function logActivity($userId, $action, $details = '') {
try {
    $conn = getConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO activity_logs 
        (user_id, action, details, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    return $stmt->execute([
        $userId,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
} catch (Exception $e) {
    error_log("Database error in logActivity: " . $e->getMessage());
    return false;
}
}

// includes/functions.php - Part 4 (Final)

// ============================================================================
// CLAIM MANAGEMENT FUNCTIONS (FROM ORIGINAL - ENHANCED VERSION)
// ============================================================================

/**
 * Submit claim for wallet points (E-COMMERCE VERSION)
 * @param int $userId User ID
 * @param float $amount Amount to claim
 * @return array Result with success status and claim ID
 */
function submitClaim($userId, $amount) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        return ['success' => false, 'message' => 'Invalid user ID'];
    }
    
    if (!is_numeric($amount) || $amount < 100) {
        return ['success' => false, 'message' => 'Minimum claim amount is ₹100'];
    }
    
    // Check if today is claim date (30th or 31st)
    $currentDay = date('j');
    if ($currentDay != 30 && $currentDay != 31) {
        return ['success' => false, 'message' => 'Claims are only allowed on 30th and 31st of each month'];
    }
    
    try {
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Check wallet balance
        $balance = getWalletBalance($userId);
        $availablePoints = $balance['points'] + $balance['pending_points'];
        
        if ($amount > $availablePoints) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Insufficient wallet balance'];
        }
        
        // Generate claim ID
        $claimId = 'CLM' . time() . rand(100, 999);
        
        // Create claim record
        $stmt = $conn->prepare("
            INSERT INTO claims 
            (claim_id, user_id, amount, status, submitted_at) 
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$claimId, $userId, $amount]);
        
        $claimDbId = $conn->lastInsertId();
        
        // Deduct points from wallet temporarily (will be restored if rejected)
        $deductResult = deductWalletPoints($userId, $amount, "Claim submitted: {$claimId}");
        if (!$deductResult['success']) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Failed to process claim: ' . $deductResult['message']];
        }
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Claim submitted successfully',
            'claim_id' => $claimId,
            'claim_db_id' => $claimDbId,
            'amount' => $amount
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Database error in submitClaim: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to submit claim'];
    }
}

/**
 * Get user claims - FIXED SQL
 * @param int $userId User ID
 * @param int $limit Limit number of results
 * @param int $offset Offset for pagination
 * @return array Claims list
 */
function getUserClaims($userId, $limit = 20, $offset = 0) {
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        return [];
    }
    
    try {
        $conn = getConnection();
        $limitValue = intval($limit);
        $offsetValue = intval($offset);
        
        $stmt = $conn->prepare("
            SELECT * FROM claims 
            WHERE user_id = ? 
            ORDER BY submitted_at DESC 
            LIMIT {$limitValue} OFFSET {$offsetValue}
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getUserClaims: " . $e->getMessage());
        return [];
    }
}


/**
 * Get all claims for admin - FIXED SQL
 * @param string $status Filter by status
 * @param int $limit Limit number of results
 * @param int $offset Offset for pagination
 * @return array Claims list
 */
function getAllClaims($status = null, $limit = 50, $offset = 0) {
    try {
        $conn = getConnection();
        
        $sql = "
            SELECT 
                c.*,
                u.name as user_name,
                u.email as user_email
            FROM claims c
            JOIN users u ON c.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($status) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }
        
        $limitValue = intval($limit);
        $offsetValue = intval($offset);
        $sql .= " ORDER BY c.submitted_at DESC LIMIT {$limitValue} OFFSET {$offsetValue}";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Database error in getAllClaims: " . $e->getMessage());
        return [];
    }
}

/**
 * Process claim (approve or reject)
 * @param int $claimDbId Claim database ID
 * @param string $action Action to take ('approve' or 'reject')
 * @param string $reason Reason for action
 * @return array Result with success status
 */
function processClaim($claimDbId, $action, $reason = '') {
    if (!$claimDbId || !is_numeric($claimDbId) || $claimDbId <= 0) {
        return ['success' => false, 'message' => 'Invalid claim ID'];
    }
    
    if (!in_array($action, ['approve', 'reject'])) {
        return ['success' => false, 'message' => 'Invalid action'];
    }
    
    try {
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Get claim details
        $stmt = $conn->prepare("
            SELECT c.*, u.name as user_name, u.email as user_email
            FROM claims c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ? AND c.status = 'pending'
        ");
        $stmt->execute([$claimDbId]);
        
        if ($stmt->rowCount() == 0) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Claim not found or already processed'];
        }
        
        $claim = $stmt->fetch();
        
        if ($action === 'approve') {
            // Update claim status to approved
            $stmt = $conn->prepare("
                UPDATE claims 
                SET status = 'approved', processed_at = NOW(), admin_notes = ? 
                WHERE id = ?
            ");
            $stmt->execute([$reason, $claimDbId]);
            
            // Send payment processed email
            if (file_exists(__DIR__ . '/sendinblue-mailer.php')) {
                require_once __DIR__ . '/sendinblue-mailer.php';
                $emailConfig = include __DIR__ . '/email-config.php';
                
                if ($emailConfig['settings']['enabled']) {
                    $mailer = new SendinblueMailer(
                        $emailConfig['sendinblue']['api_key'],
                        $emailConfig['sendinblue']['from_email'],
                        $emailConfig['sendinblue']['from_name']
                    );
                    
                    // Get monthly breakdown for email
                    $breakdown = getMonthlyEarningBreakdown($claim['user_id']);
                    
                    $mailer->sendPaymentProcessedEmail(
                        $claim['user_email'],
                        $claim['user_name'],
                        $claim['amount'],
                        $breakdown
                    );
                }
            }
            
        } else { // reject
            // Update claim status to rejected
            $stmt = $conn->prepare("
                UPDATE claims 
                SET status = 'rejected', processed_at = NOW(), admin_notes = ? 
                WHERE id = ?
            ");
            $stmt->execute([$reason, $claimDbId]);
            
            // Restore points to wallet
            $addResult = addWalletPoints($claim['user_id'], $claim['amount'], "Claim rejected - points restored: {$claim['claim_id']}", 'restored');
            if (!$addResult['success']) {
                $conn->rollBack();
                return ['success' => false, 'message' => 'Failed to restore points: ' . $addResult['message']];
            }
            
            // Send claim rejected email
            if (file_exists(__DIR__ . '/sendinblue-mailer.php')) {
                require_once __DIR__ . '/sendinblue-mailer.php';
                $emailConfig = include __DIR__ . '/email-config.php';
                
                if ($emailConfig['settings']['enabled']) {
                    $mailer = new SendinblueMailer(
                        $emailConfig['sendinblue']['api_key'],
                        $emailConfig['sendinblue']['from_email'],
                        $emailConfig['sendinblue']['from_name']
                    );
                    
                    if (method_exists($mailer, 'sendClaimRejectedEmail')) {
                        $mailer->sendClaimRejectedEmail(
                            $claim['user_email'],
                            $claim['user_name'],
                            $claim['claim_id'],
                            $claim['amount'],
                            $reason
                        );
                    }
                }
            }
        }
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => "Claim {$action}d successfully",
            'action' => $action,
            'claim_id' => $claim['claim_id']
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Database error in processClaim: " . $e->getMessage());
        return ['success' => false, 'message' => "Failed to {$action} claim"];
    }
}

// ============================================================================
// SYSTEM SETTINGS FUNCTIONS
// ============================================================================

/**
 * Get system setting
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value
 */
function getSetting($key, $default = null) {
    global $conn;
    
    try {
        if (!$conn) {
            $conn = getConnection();
        }
        
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetchColumn();
        }
        
        return $default;
    } catch (Exception $e) {
        error_log("Error getting setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Get system setting (alias for getSetting for compatibility)
 * @param string $key Setting key
 * @param mixed $default Default value
 * @return mixed Setting value
 */
function getSystemSetting($key, $default = null) {
    return getSetting($key, $default);
}

/**
 * Enhanced setSetting with proper boolean handling
 */
function setSetting($key, $value, $type = 'string') {
    if (empty($key)) {
        return false;
    }
    
    try {
        $conn = getConnection();
        
        // Special handling for maintenance_mode
        if ($key === 'maintenance_mode') {
            $type = 'boolean';
            
            // Convert to string boolean for database storage
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                $lowerValue = strtolower(trim($value));
                $value = in_array($lowerValue, ['true', '1', 'on', 'yes']) ? 'true' : 'false';
            } elseif (is_numeric($value)) {
                $value = intval($value) === 1 ? 'true' : 'false';
            } else {
                $value = 'false';
            }
            
            error_log("setSetting: maintenance_mode being set to: {$value}");
        } else {
            // Convert value based on type for other settings
            switch ($type) {
                case 'json':
                    $value = json_encode($value);
                    break;
                case 'boolean':
                    $value = $value ? 'true' : 'false';
                    break;
                default:
                    $value = (string) $value;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type, updated_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type),
            updated_at = NOW()
        ");
        
        $result = $stmt->execute([$key, $value, $type]);
        
        if ($result && $key === 'maintenance_mode') {
            // Verify the save
            $verifyStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $verifyStmt->execute(['maintenance_mode']);
            $verifyResult = $verifyStmt->fetch();
            
            if ($verifyResult) {
                error_log("setSetting: maintenance_mode verified in database as: " . $verifyResult['setting_value']);
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Database error in setSetting: " . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced maintenance mode check
 */
function isMaintenanceMode() {
    try {
        $maintenanceMode = getSetting('maintenance_mode');
        
        // Handle different boolean representations
        if (is_bool($maintenanceMode)) {
            return $maintenanceMode;
        }
        
        if (is_string($maintenanceMode)) {
            $maintenanceMode = strtolower(trim($maintenanceMode));
            return in_array($maintenanceMode, ['true', '1', 'on', 'yes']);
        }
        
        if (is_numeric($maintenanceMode)) {
            return intval($maintenanceMode) === 1;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error checking maintenance mode: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if current user is admin
 */
function isCurrentUserAdmin() {
    // Don't call session_start() if session already active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check multiple admin indicators
    $adminChecks = [
        isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true,
        isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']),
        isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin',
        isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true
    ];
    
    return in_array(true, $adminChecks);
}

/**
 * Check maintenance mode and redirect if needed
 */
function checkMaintenanceModeAndRedirect() {
    try {
        // Get maintenance status
        $maintenanceMode = isMaintenanceMode();
        
        // Check if user is admin
        $isAdmin = isCurrentUserAdmin();
        
        
        if ($maintenanceMode && !$isAdmin) {
            // Try different possible paths for maintenance.html
            $possiblePaths = [
                __DIR__ . '/../static/maintenance.html', // primary: static/ folder
                __DIR__ . '/../maintenance.html',        // legacy root fallback
                __DIR__ . '/maintenance.html',
                $_SERVER['DOCUMENT_ROOT'] . '/ecommerce-project/static/maintenance.html',
                $_SERVER['DOCUMENT_ROOT'] . '/maintenance.html',
            ];
            
            $foundPath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $foundPath = $path;
                    break;
                }
            }
            
            if ($foundPath) {
                error_log("Showing maintenance page from: {$foundPath}");
                http_response_code(503);
                header('Retry-After: 3600');
                include $foundPath;
                exit;
            } else {
                // Fallback maintenance message
                error_log("Maintenance file not found, showing fallback message");
                http_response_code(503);
                header('Retry-After: 3600');
                
                echo "<!DOCTYPE html>
                <html>
                <head>
                    <title>Site Under Maintenance</title>
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                        h1 { color: #333; margin-bottom: 20px; }
                        p { color: #666; line-height: 1.6; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h1>🔧 Site Under Maintenance</h1>
                        <p>We're currently performing scheduled maintenance to improve your experience. Please check back in a few minutes.</p>
                        <p>Thank you for your patience!</p>
                    </div>
                </body>
                </html>";
                exit;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error in maintenance check: " . $e->getMessage());
        // Continue loading site if maintenance check fails
    }
}

/**
 * Get all system settings
 * @return array All settings as key-value pairs
 */
function getAllSettings() {
    try {
        $conn = getConnection();
        $stmt = $conn->query("SELECT setting_key, setting_value, setting_type FROM settings");
        
        $settings = [];
        while ($row = $stmt->fetch()) {
            $key = $row['setting_key'];
            $value = $row['setting_value'];
            
            // Convert based on type
            switch ($row['setting_type']) {
                case 'number':
                    $settings[$key] = is_numeric($value) ? floatval($value) : 0;
                    break;
                case 'boolean':
                    $settings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                    $decoded = json_decode($value, true);
                    $settings[$key] = $decoded !== null ? $decoded : [];
                    break;
                default:
                    $settings[$key] = $value;
            }
        }
        
        return $settings;
        
    } catch (Exception $e) {
        error_log("Database error in getAllSettings: " . $e->getMessage());
        return [];
    }
}

// ============================================================================
// MONTHLY REMINDER SYSTEM (FROM ORIGINAL - ENHANCED)
// ============================================================================

/**
 * Send monthly claim reminders to all users
 * @return array Result with statistics
 */
function sendMonthlyReminders() {
    // Check if today is 30th or 31st
    $currentDay = date('j');
    if ($currentDay != 30 && $currentDay != 31) {
        return ['success' => false, 'message' => 'Not a claim date'];
    }
    
    try {
        // Load email system
        if (!file_exists(__DIR__ . '/sendinblue-mailer.php')) {
            return ['success' => false, 'message' => 'Email system not available'];
        }
        
        require_once __DIR__ . '/sendinblue-mailer.php';
        $emailConfig = include __DIR__ . '/email-config.php';
        
        if (!$emailConfig['settings']['enabled']) {
            return ['success' => false, 'message' => 'Email system disabled'];
        }
        
        $mailer = new SendinblueMailer(
            $emailConfig['sendinblue']['api_key'],
            $emailConfig['sendinblue']['from_email'],
            $emailConfig['sendinblue']['from_name']
        );
        
        // Use the batch sender from SendinblueMailer
        if (method_exists($mailer, 'sendMonthlyRemindersToAllUsers')) {
            return $mailer->sendMonthlyRemindersToAllUsers();
        } else {
            // Manual implementation for older SendinblueMailer
            $conn = getConnection();
            $stmt = $conn->query("
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    COALESCE(w.points, 0) as wallet_points
                FROM users u
                LEFT JOIN wallet w ON u.id = w.user_id
                WHERE u.email IS NOT NULL AND u.email != ''
                ORDER BY u.id
            ");
            
            $users = $stmt->fetchAll();
            $results = [
                'success' => true,
                'total_users' => count($users),
                'emails_sent' => 0,
                'emails_failed' => 0
            ];
            
            foreach ($users as $user) {
                $hasEnoughPoints = $user['wallet_points'] >= 100;
                
                try {
                    if ($hasEnoughPoints) {
                        // Send "You can claim now!" email
                        if (method_exists($mailer, 'sendMonthlyClaimAvailableEmail')) {
                            $emailSent = $mailer->sendMonthlyClaimAvailableEmail(
                                $user['email'], 
                                $user['name'], 
                                $currentDay, 
                                $user['wallet_points']
                            );
                        } else {
                            $emailSent = sendEmailNotification(
                                $user['email'],
                                "Time to Claim Your ₹{$user['wallet_points']}!",
                                "Great news! Today is claim date and you have ₹{$user['wallet_points']} available to claim!",
                                'monthly_claim_available',
                                null,
                                $user['name']
                            );
                        }
                    } else {
                        // Send "Start earning!" email
                        if (method_exists($mailer, 'sendMonthlyStartEarningEmail')) {
                            $emailSent = $mailer->sendMonthlyStartEarningEmail(
                                $user['email'], 
                                $user['name'], 
                                $currentDay, 
                                $user['wallet_points']
                            );
                        } else {
                            $needed = 100 - $user['wallet_points'];
                            $emailSent = sendEmailNotification(
                                $user['email'],
                                "Start Earning Referral Points Today!",
                                "Today is claim date! You need ₹{$needed} more to claim. Start referring friends to earn points!",
                                'monthly_start_earning',
                                null,
                                $user['name']
                            );
                        }
                    }
                    
                    if ($emailSent) {
                        $results['emails_sent']++;
                    } else {
                        $results['emails_failed']++;
                    }
                    
                    // Small delay
                    usleep(100000); // 0.1 second
                    
                } catch (Exception $e) {
                    $results['emails_failed']++;
                    error_log("Monthly email error for user {$user['id']}: " . $e->getMessage());
                }
            }
            
            return $results;
        }
        
    } catch (Exception $e) {
        error_log("Error in sendMonthlyReminders: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send reminders: ' . $e->getMessage()];
    }
}

/**
 * Check if user can claim today (FROM ORIGINAL - ENHANCED)
 * @param int $userId User ID
 * @return array Result with can_claim status and details
 */
function canUserClaimToday($userId) {
    return canUserClaimPoints($userId); // Use the enhanced version from Part 2
}

// ============================================================================
// PRODUCT IMAGE MANAGEMENT
// ============================================================================

/**
 * Add product image
 * @param int $productId Product ID
 * @param string $imageUrl Image URL
 * @param string $altText Alt text
 * @param bool $isPrimary Is primary image
 * @param int $sortOrder Sort order
 * @return array Result with success status
 */
function addProductImage($productId, $imageUrl, $altText = '', $isPrimary = false, $sortOrder = 0) {
    if (!$productId || !is_numeric($productId) || $productId <= 0) {
        return ['success' => false, 'message' => 'Invalid product ID'];
    }
    
    if (empty($imageUrl)) {
        return ['success' => false, 'message' => 'Image URL is required'];
    }
    
    try {
        $conn = getConnection();
        $conn->beginTransaction();
        
        // If this is primary, unset other primary images
        if ($isPrimary) {
            $stmt = $conn->prepare("UPDATE product_images SET is_primary = FALSE WHERE product_id = ?");
            $stmt->execute([$productId]);
        }
        
        $stmt = $conn->prepare("
            INSERT INTO product_images 
            (product_id, image_url, alt_text, is_primary, sort_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$productId, $imageUrl, $altText, $isPrimary, $sortOrder]);
        
        $conn->commit();
        
        return ['success' => true, 'message' => 'Product image added successfully'];
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Database error in addProductImage: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add product image'];
    }
}

/**
 * Delete product image
 * @param int $imageId Image ID
 * @return array Result with success status
 */
function deleteProductImage($imageId) {
    if (!$imageId || !is_numeric($imageId) || $imageId <= 0) {
        return ['success' => false, 'message' => 'Invalid image ID'];
    }
    
    try {
        $conn = getConnection();
        
        $stmt = $conn->prepare("DELETE FROM product_images WHERE id = ?");
        $stmt->execute([$imageId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Product image deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Image not found'];
        }
        
    } catch (Exception $e) {
        error_log("Database error in deleteProductImage: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete product image'];
    }
}

// ============================================================================
// ADVANCED ANALYTICS AND REPORTING (NEW)
// ============================================================================

/**
 * Get comprehensive system analytics
 * @return array Complete system analytics
 */
function getComprehensiveAnalytics() {
    try {
        $conn = getConnection();
        
        // Combine both referral and e-commerce stats
        $referralStats = getSystemStats();
        $ecommerceStats = getEcommerceStats();
        
        // Additional analytics
        $stmt = $conn->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as daily_signups
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $dailySignups = $stmt->fetchAll();
        
        $stmt = $conn->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as daily_orders,
                SUM(final_amount) as daily_revenue
            FROM orders 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND payment_status = 'paid'
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $dailyOrders = $stmt->fetchAll();
        
        return [
            'referral_stats' => $referralStats,
            'ecommerce_stats' => $ecommerceStats,
            'daily_signups' => $dailySignups,
            'daily_orders' => $dailyOrders,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        error_log("Database error in getComprehensiveAnalytics: " . $e->getMessage());
        return [
            'referral_stats' => getSystemStats(),
            'ecommerce_stats' => getEcommerceStats(),
            'daily_signups' => [],
            'daily_orders' => [],
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Get user engagement metrics
 * @return array User engagement data
 */
function getUserEngagementMetrics() {
    try {
        $conn = getConnection();
        
        $stmt = $conn->query("
            SELECT 
                COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as daily_active,
                COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as weekly_active,
                COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as monthly_active,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d,
                COUNT(*) as total_users
            FROM users
        ");
        
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Database error in getUserEngagementMetrics: " . $e->getMessage());
        return [
            'daily_active' => 0,
            'weekly_active' => 0,
            'monthly_active' => 0,
            'new_users_30d' => 0,
            'total_users' => 0
        ];
    }
}

// ============================================================================
// MAINTENANCE AND CLEANUP FUNCTIONS (FROM ORIGINAL - ENHANCED)
// ============================================================================

/**
 * Perform system maintenance tasks
 * @return array Maintenance results
 */
function performSystemMaintenance() {
    $results = [
        'success' => true,
        'tasks_completed' => 0,
        'tasks_failed' => 0,
        'details' => []
    ];
    
    try {
        // Clean old visitor tracking (FROM ORIGINAL)
        $cleanedVisits = cleanOldVisitorTracking(30);
        $results['details']['visitor_cleanup'] = "Cleaned {$cleanedVisits} old visitor records";
        $results['tasks_completed']++;
        
        // Clean old email notifications
        $conn = getConnection();
        $stmt = $conn->prepare("DELETE FROM email_notifications WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $stmt->execute();
        $cleanedEmails = $stmt->rowCount();
        $results['details']['email_cleanup'] = "Cleaned {$cleanedEmails} old email records";
        $results['tasks_completed']++;
        
        // Clean old activity logs
        $stmt = $conn->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
        $stmt->execute();
        $cleanedActivity = $stmt->rowCount();
        $results['details']['activity_cleanup'] = "Cleaned {$cleanedActivity} old activity records";
        $results['tasks_completed']++;
        
        // Optimize tables
        $tables = ['users', 'wallet', 'referrals', 'referral_purchases', 'products', 'orders', 'order_items'];
        foreach ($tables as $table) {
            try {
                $stmt = $conn->query("OPTIMIZE TABLE {$table}");
                $results['details']["optimize_{$table}"] = "Table {$table} optimized";
                $results['tasks_completed']++;
            } catch (Exception $e) {
                $results['details']["optimize_{$table}"] = "Failed to optimize {$table}: " . $e->getMessage();
                $results['tasks_failed']++;
            }
        }
        
    } catch (Exception $e) {
        $results['success'] = false;
        $results['details']['error'] = $e->getMessage();
        $results['tasks_failed']++;
    }
    
    return $results;
}

/**
 * Backup critical data
 * @return array Backup results
 */
function backupCriticalData() {
    try {
        $conn = getConnection();
        $backupData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'users_count' => 0,
            'wallet_total' => 0,
            'referrals_count' => 0,
            'orders_count' => 0
        ];
        
        // Count users
        $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
        $backupData['users_count'] = $stmt->fetch()['count'];
        
        // Total wallet points
        $stmt = $conn->query("SELECT SUM(points + pending_points) as total FROM wallet");
        $backupData['wallet_total'] = $stmt->fetch()['total'] ?? 0;
        
        // Count referrals
        $stmt = $conn->query("SELECT COUNT(*) as count FROM referrals");
        $backupData['referrals_count'] = $stmt->fetch()['count'];
        
        // Count orders
        $stmt = $conn->query("SELECT COUNT(*) as count FROM orders");
        $backupData['orders_count'] = $stmt->fetch()['count'];
        
        // Store backup record
        $stmt = $conn->prepare("
            INSERT INTO system_backups 
            (backup_date, users_count, wallet_total, referrals_count, orders_count, created_at) 
            VALUES (NOW(), ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $backupData['users_count'],
            $backupData['wallet_total'],
            $backupData['referrals_count'],
            $backupData['orders_count']
        ]);
        
        return ['success' => true, 'data' => $backupData];
        
    } catch (Exception $e) {
        error_log("Backup error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// includes/functions.php - Final Utilities and Constants

// ============================================================================
// FINAL UTILITY FUNCTIONS AND CONSTANTS
// ============================================================================

/**
 * Get system health status
 * @return array System health information
 */
function getSystemHealth() {
    try {
        $conn = getConnection();
        
        $health = [
            'status' => 'healthy',
            'database' => 'connected',
            'email_system' => 'unknown',
            'disk_space' => 'unknown',
            'memory_usage' => 'unknown',
            'checks' => []
        ];
        
        // Check database connection
        try {
            $stmt = $conn->query("SELECT 1");
            $health['database'] = 'connected';
            $health['checks'][] = '✅ Database connection working';
        } catch (Exception $e) {
            $health['database'] = 'error';
            $health['status'] = 'warning';
            $health['checks'][] = '❌ Database connection failed';
        }
        
        // Check email system
        if (file_exists(__DIR__ . '/email-config.php')) {
            $emailConfig = include __DIR__ . '/email-config.php';
            if ($emailConfig['settings']['enabled']) {
                $health['email_system'] = 'enabled';
                $health['checks'][] = '✅ Email system enabled';
            } else {
                $health['email_system'] = 'disabled';
                $health['checks'][] = '⚠️ Email system disabled';
            }
        } else {
            $health['email_system'] = 'not_configured';
            $health['checks'][] = '❌ Email system not configured';
        }
        
        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $health['memory_usage'] = round($memoryUsage / 1024 / 1024, 2) . 'MB';
        $health['checks'][] = "💾 Memory usage: {$health['memory_usage']}";
        
        // Check disk space if possible
        if (function_exists('disk_free_space')) {
            $freeSpace = disk_free_space(__DIR__);
            if ($freeSpace !== false) {
                $health['disk_space'] = round($freeSpace / 1024 / 1024 / 1024, 2) . 'GB free';
                $health['checks'][] = "💽 Disk space: {$health['disk_space']}";
            }
        }
        
        return $health;
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'database' => 'error',
            'email_system' => 'unknown',
            'disk_space' => 'unknown',
            'memory_usage' => 'unknown',
            'checks' => ['❌ System health check failed: ' . $e->getMessage()]
        ];
    }
}

/**
 * Generate secure token
 * @param int $length Token length
 * @return string Secure token
 */
function generateSecureToken($length = 32) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    } else {
        // Fallback for older PHP versions
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    }
}

/**
 * Validate CSRF token
 * @param string $token Token to validate
 * @param string $sessionToken Session token
 * @return bool Is valid token
 */
function validateCSRFToken($token, $sessionToken) {
    return hash_equals($sessionToken, $token);
}

/**
 * Format file size
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Validate phone number
 * @param string $phone Phone number to validate
 * @return bool Is valid phone number
 */
function isValidPhone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Indian mobile number (10 digits starting with 6-9)
    return preg_match('/^[6-9]\d{9}$/', $phone);
}

/**
 * Validate Indian PIN code
 * @param string $pin PIN code to validate
 * @return bool Is valid PIN code
 */
function isValidPinCode($pin) {
    // Indian PIN codes are 6 digits and don't start with 0
    return preg_match('/^[1-9][0-9]{5}$/', $pin);
}

/**
 * Calculate distance between two coordinates
 * @param float $lat1 Latitude 1
 * @param float $lon1 Longitude 1
 * @param float $lat2 Latitude 2
 * @param float $lon2 Longitude 2
 * @return float Distance in kilometers
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}

/**
 * Generate QR code data URL (for referral links)
 * @param string $text Text to encode
 * @param int $size QR code size
 * @return string QR code data URL
 */
function generateQRCode($text, $size = 200) {
    // Using Google Charts API for QR code generation
    $encodedText = urlencode($text);
    return "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encodedText}&choe=UTF-8";
}

/**
 * Slugify text
 * @param string $text Text to slugify
 * @param string $separator Separator character
 * @return string Slugified text
 */
function slugify($text, $separator = '-') {
    // Replace non-alphanumeric characters with separator
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
    
    // Replace spaces and multiple separators with single separator
    $text = preg_replace('/[\s' . preg_quote($separator) . ']+/', $separator, $text);
    
    // Convert to lowercase and trim separators
    return trim(strtolower($text), $separator);
}

/**
 * Time ago function
 * @param string $datetime DateTime string
 * @return string Time ago text
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . ' minute' . (floor($time / 60) == 1 ? '' : 's') . ' ago';
    if ($time < 86400) return floor($time / 3600) . ' hour' . (floor($time / 3600) == 1 ? '' : 's') . ' ago';
    if ($time < 2592000) return floor($time / 86400) . ' day' . (floor($time / 86400) == 1 ? '' : 's') . ' ago';
    if ($time < 31536000) return floor($time / 2592000) . ' month' . (floor($time / 2592000) == 1 ? '' : 's') . ' ago';
    
    return floor($time / 31536000) . ' year' . (floor($time / 31536000) == 1 ? '' : 's') . ' ago';
}

/**
 * Generate random color
 * @return string Hex color code
 */
function generateRandomColor() {
    return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}

/**
 * Convert RGB to HSL
 * @param int $r Red value (0-255)
 * @param int $g Green value (0-255)
 * @param int $b Blue value (0-255)
 * @return array HSL values
 */
function rgbToHsl($r, $g, $b) {
    $r /= 255;
    $g /= 255;
    $b /= 255;
    
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $diff = $max - $min;
    
    $h = 0;
    $s = 0;
    $l = ($max + $min) / 2;
    
    if ($diff != 0) {
        $s = $l < 0.5 ? $diff / ($max + $min) : $diff / (2 - $max - $min);
        
        switch ($max) {
            case $r:
                $h = ($g - $b) / $diff + ($g < $b ? 6 : 0);
                break;
            case $g:
                $h = ($b - $r) / $diff + 2;
                break;
            case $b:
                $h = ($r - $g) / $diff + 4;
                break;
        }
        $h /= 6;
    }
    
    return [
        'h' => round($h * 360),
        's' => round($s * 100),
        'l' => round($l * 100)
    ];
}

/**
 * Truncate text with ellipsis
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to add
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

/**
 * Generate UUID v4
 * @return string UUID
 */
function generateUUID() {
    if (function_exists('random_bytes')) {
        $data = random_bytes(16);
    } else {
        $data = openssl_random_pseudo_bytes(16);
    }
    
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10
    
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Check if running in CLI mode
 * @return bool Is CLI mode
 */
function isCLI() {
    return php_sapi_name() === 'cli' || defined('STDIN');
}

/**
 * Get user's browser information
 * @return array Browser information
 */
function getBrowserInfo() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $browsers = [
        'Chrome' => '/Chrome/i',
        'Firefox' => '/Firefox/i',
        'Safari' => '/Safari/i',
        'Edge' => '/Edge/i',
        'Opera' => '/Opera/i',
        'Internet Explorer' => '/MSIE/i'
    ];
    
    $browser = 'Unknown';
    foreach ($browsers as $name => $pattern) {
        if (preg_match($pattern, $userAgent)) {
            $browser = $name;
            break;
        }
    }
    
    $platforms = [
        'Windows' => '/Windows/i',
        'Mac' => '/Mac/i',
        'Linux' => '/Linux/i',
        'Android' => '/Android/i',
        'iOS' => '/iPhone|iPad/i'
    ];
    
    $platform = 'Unknown';
    foreach ($platforms as $name => $pattern) {
        if (preg_match($pattern, $userAgent)) {
            $platform = $name;
            break;
        }
    }
    
    return [
        'browser' => $browser,
        'platform' => $platform,
        'user_agent' => $userAgent
    ];
}

// ============================================================================
// SYSTEM CONSTANTS AND CONFIGURATION
// ============================================================================

// Claim system constants (FROM ORIGINAL - PRESERVED)
if (!defined('MIN_POINTS_TO_CLAIM')) {
    define('MIN_POINTS_TO_CLAIM', 100);
}

// E-commerce constants
if (!defined('DEFAULT_CURRENCY')) {
    define('DEFAULT_CURRENCY', '₹');
}

if (!defined('DEFAULT_CURRENCY_CODE')) {
    define('DEFAULT_CURRENCY_CODE', 'INR');
}

if (!defined('MIN_ORDER_AMOUNT')) {
    define('MIN_ORDER_AMOUNT', 1);
}

if (!defined('MAX_CART_ITEMS')) {
    define('MAX_CART_ITEMS', 50);
}

if (!defined('DEFAULT_ITEMS_PER_PAGE')) {
    define('DEFAULT_ITEMS_PER_PAGE', 20);
}

// File upload constants
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
}

if (!defined('ALLOWED_IMAGE_TYPES')) {
    define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

// Email constants
if (!defined('MAX_EMAIL_ATTEMPTS')) {
    define('MAX_EMAIL_ATTEMPTS', 3);
}

// Session constants
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 3600); // 1 hour
}

// Rate limiting constants
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);
}

if (!defined('LOGIN_ATTEMPT_WINDOW')) {
    define('LOGIN_ATTEMPT_WINDOW', 900); // 15 minutes
}

// Referral system constants (FROM ORIGINAL - PRESERVED)
if (!defined('FIRST_MONTH_RATE')) {
    define('FIRST_MONTH_RATE', 10.0); // 10%
}

if (!defined('OTHER_MONTHS_RATE')) {
    define('OTHER_MONTHS_RATE', 5.0); // 5%
}

if (!defined('REFERRAL_CODE_LENGTH')) {
    define('REFERRAL_CODE_LENGTH', 6);
}

// System versioning
if (!defined('SYSTEM_VERSION')) {
    define('SYSTEM_VERSION', '2.0.0');
}

if (!defined('SYSTEM_NAME')) {
    define('SYSTEM_NAME', 'Bluefifth E-commerce & Referral System');
}

// Debug and logging
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false);
}

if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
}

// Cache constants
if (!defined('CACHE_DURATION')) {
    define('CACHE_DURATION', 3600); // 1 hour
}

// Security constants
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', '_token');
}

if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}

// API constants
if (!defined('API_VERSION')) {
    define('API_VERSION', 'v1');
}

if (!defined('API_RATE_LIMIT')) {
    define('API_RATE_LIMIT', 100); // requests per hour
}

// ============================================================================
// SYSTEM INITIALIZATION AND FINAL CHECKS
// ============================================================================

/**
 * Initialize system
 * @return bool Initialization success
 */
function initializeSystem() {
    try {
        // Check database connection
        $conn = getConnection();
        
        // Verify critical tables exist
        $requiredTables = [
            'users', 'wallet', 'referrals', 'referral_purchases',
            'products', 'categories', 'orders', 'order_items',
            'cart', 'claims', 'email_notifications'
        ];
        
        $stmt = $conn->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingTables = array_diff($requiredTables, $existingTables);
        
        if (!empty($missingTables)) {
            error_log("Missing required tables: " . implode(', ', $missingTables));
            return false;
        }
        
        // System is properly initialized
        return true;
        
    } catch (Exception $e) {
        error_log("System initialization failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log system startup
 */
function logSystemStartup() {
    if (DEBUG_MODE) {
        error_log("=== VELONA SYSTEM STARTUP ===");
        error_log("System Name: " . SYSTEM_NAME);
        error_log("Version: " . SYSTEM_VERSION);
        error_log("PHP Version: " . PHP_VERSION);
        error_log("Memory Limit: " . ini_get('memory_limit'));
        error_log("Max Execution Time: " . ini_get('max_execution_time'));
        error_log("Time Zone: " . date_default_timezone_get());
        error_log("Current Time: " . date('Y-m-d H:i:s'));
        error_log("==============================");
    }
}

// ============================================================================
// AUTO-INITIALIZATION (if not in CLI mode)
// ============================================================================

if (!isCLI()) {
    // Log system startup in debug mode
    logSystemStartup();
    
    // Initialize system and check health
    if (!initializeSystem()) {
        if (DEBUG_MODE) {
            error_log("CRITICAL: System initialization failed!");
        }
    }
}

// ============================================================================
// CATEGORY PAGE SPECIFIC CONSTANTS
// ============================================================================

if (!defined('PRODUCTS_PER_PAGE_OPTIONS')) {
    define('PRODUCTS_PER_PAGE_OPTIONS', [12, 24, 48]);
}

if (!defined('DEFAULT_PRODUCTS_PER_PAGE')) {
    define('DEFAULT_PRODUCTS_PER_PAGE', 12);
}

if (!defined('CATEGORY_SORT_OPTIONS')) {
    define('CATEGORY_SORT_OPTIONS', [
        'featured' => 'Featured',
        'name_asc' => 'Name (A-Z)',
        'name_desc' => 'Name (Z-A)', 
        'price_low' => 'Price (Low to High)',
        'price_high' => 'Price (High to Low)',
        'newest' => 'Newest First'
    ]);
}

// ================================
// ADDITIONAL ORDER MANAGEMENT FUNCTIONS - FIXED SQL
// ================================

/**
 * Get all orders for admin with pagination - FIXED SQL
 * @param int $page Current page
 * @param int $perPage Items per page
 * @param string $status Filter by status
 * @return array Orders data with pagination
 */
function getAllOrdersForAdmin($page = 1, $perPage = 25, $status = '') {
    try {
        $conn = getConnection();
        $page = max(1, intval($page));
        $perPage = max(1, min(100, intval($perPage)));
        $offset = ($page - 1) * $perPage;
        
        // Build query
        $whereClause = '';
        $params = [];
        
        if (!empty($status)) {
            $whereClause = 'WHERE o.status = ?';
            $params[] = $status;
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM orders o {$whereClause}";
        $stmt = $conn->prepare($countSql);
        $stmt->execute($params);
        $totalItems = $stmt->fetch()['total'];
        
        // Get orders with fixed LIMIT/OFFSET
        $sql = "
            SELECT 
                o.*,
                u.name as customer_name,
                u.email as customer_email,
                COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            {$whereClause}
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // Process orders
        foreach ($orders as &$order) {
            $order['shipping_address'] = json_decode($order['shipping_address'], true);
            $order['billing_address'] = json_decode($order['billing_address'], true);
        }
        
        $totalPages = ceil($totalItems / $perPage);
        
        return [
            'success' => true,
            'orders' => $orders,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => intval($totalItems),
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Database error in getAllOrdersForAdmin: " . $e->getMessage());
        return [
            'success' => false,
            'orders' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total_items' => 0,
                'total_pages' => 0,
                'has_next' => false,
                'has_prev' => false
            ]
        ];
    }
}

/**
 * Get order statistics for admin
 * @return array Order statistics
 */
function getOrderStatistics() {
    try {
        $conn = getConnection();
        
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
                COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped_orders,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_orders,
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payment_orders,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN final_amount ELSE 0 END), 0) as total_revenue,
                COALESCE(AVG(CASE WHEN payment_status = 'paid' THEN final_amount END), 0) as average_order_value
            FROM orders
        ");
        
        $stats = $stmt->fetch();
        
        // Ensure all values are numeric
        foreach ($stats as $key => $value) {
            if (strpos($key, '_orders') !== false || $key === 'total_orders') {
                $stats[$key] = intval($value);
            } else {
                $stats[$key] = floatval($value);
            }
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Database error in getOrderStatistics: " . $e->getMessage());
        return [
            'total_orders' => 0,
            'pending_orders' => 0,
            'processing_orders' => 0,
            'shipped_orders' => 0,
            'delivered_orders' => 0,
            'cancelled_orders' => 0,
            'paid_orders' => 0,
            'pending_payment_orders' => 0,
            'total_revenue' => 0,
            'average_order_value' => 0
        ];
    }
}

// ================================
// ADDITIONAL HELPER FUNCTIONS FOR ADMIN PAGES
// ================================

/**
 * Format order status for display
 * @param string $status Order status
 * @return string Formatted status
 */
function formatOrderStatus($status) {
    $statusMap = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled'
    ];
    
    return $statusMap[$status] ?? ucfirst($status);
}

/**
 * Format payment status for display
 * @param string $status Payment status
 * @return string Formatted status
 */
function formatPaymentStatus($status) {
    $statusMap = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded'
    ];
    
    return $statusMap[$status] ?? ucfirst($status);
}

/**
 * Get order status badge class
 * @param string $status Order status
 * @return string CSS class
 */
function getOrderStatusBadgeClass($status) {
    $classMap = [
        'pending' => 'badge-warning',
        'processing' => 'badge-info',
        'shipped' => 'badge-primary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    
    return $classMap[$status] ?? 'badge-secondary';
}

/**
 * Get products for admin with enhanced filtering - FIXED SQL
 * @param array $filters Filter parameters
 * @param int $page Current page
 * @param int $perPage Items per page
 * @return array Products data with pagination
 */
function getProductsForAdmin($filters = [], $page = 1, $perPage = 25) {
    try {
        $conn = getConnection();
        $page = max(1, intval($page));
        $perPage = max(1, min(100, intval($perPage)));
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['category_id'])) {
            $whereConditions[] = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['stock_status'])) {
            switch ($filters['stock_status']) {
                case 'low_stock':
                    $whereConditions[] = "p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0";
                    break;
                case 'out_of_stock':
                    $whereConditions[] = "p.stock_quantity = 0";
                    break;
                case 'in_stock':
                    $whereConditions[] = "p.stock_quantity > p.low_stock_threshold";
                    break;
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
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
        
        // Get products with fixed LIMIT/OFFSET
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
        
        $totalPages = ceil($totalItems / $perPage);
        
        return [
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
        ];
        
    } catch (Exception $e) {
        error_log("Database error in getProductsForAdmin: " . $e->getMessage());
        return [
            'success' => false,
            'products' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total_items' => 0,
                'total_pages' => 0,
                'has_next' => false,
                'has_prev' => false
            ]
        ];
    }
}

// ============================================================================
// SHIPROCKET INTEGRATION FUNCTIONS
// ============================================================================

function autoCreateShiprocketOrder($orderDbId, $orderNumber, $shippingAddress, $cartItems) {
    // DEBUG: Log function call
    error_log("DEBUG: autoCreateShiprocketOrder called with Order: {$orderNumber}, Items: " . count($cartItems));
    
    try {
        // Get Shiprocket settings - WITH DETAILED DEBUGGING
        $shiprocketEnabled = getSetting('shiprocket_enabled', '0');
        $shiprocketApiToken = getSetting('shiprocket_api_token', '');
        
        // DETAILED DEBUG OUTPUT
        error_log("DEBUG: Raw enabled value: '" . var_export($shiprocketEnabled, true) . "'");
        error_log("DEBUG: Raw enabled type: " . gettype($shiprocketEnabled));
        error_log("DEBUG: Token length: " . strlen($shiprocketApiToken));
        error_log("DEBUG: Token first 20 chars: " . substr($shiprocketApiToken, 0, 20));
        
        // Test all possible enabled values
        $isTrue = ($shiprocketEnabled === 'true');
        $isOne = ($shiprocketEnabled === '1');
        $isNumOne = ($shiprocketEnabled === 1);
        $isBoolTrue = ($shiprocketEnabled === true);
        
        error_log("DEBUG: === 'true': " . ($isTrue ? 'YES' : 'NO'));
        error_log("DEBUG: === '1': " . ($isOne ? 'YES' : 'NO'));
        error_log("DEBUG: === 1: " . ($isNumOne ? 'YES' : 'NO'));
        error_log("DEBUG: === true: " . ($isBoolTrue ? 'YES' : 'NO'));
        
        // Check if enabled (accepts multiple formats)
        $isEnabled = ($shiprocketEnabled === 'true' || $shiprocketEnabled === '1' || $shiprocketEnabled === 1 || $shiprocketEnabled === true);
        
        error_log("DEBUG: Final enabled result: " . ($isEnabled ? 'YES' : 'NO'));
        error_log("DEBUG: Token empty check: " . (empty($shiprocketApiToken) ? 'EMPTY' : 'NOT EMPTY'));
        
        if (!$isEnabled) {
            error_log("ERROR: Shiprocket disabled - Raw value: '{$shiprocketEnabled}'");
            return ['success' => false, 'message' => 'Shiprocket disabled'];
        }
        
        if (empty($shiprocketApiToken)) {
            error_log("ERROR: Shiprocket API token is empty");
            return ['success' => false, 'message' => 'Shiprocket API token missing'];
        }
        
        error_log("SUCCESS: Shiprocket config checks passed - proceeding with order creation");
        
        // Prepare order items for Shiprocket
        $orderItems = [];
        $totalWeight = 0;
        $totalValue = 0;
        
        error_log("DEBUG: Processing " . count($cartItems) . " cart items");
        
        foreach ($cartItems as $item) {
            error_log("DEBUG: Item - " . $item['product_name'] . " x " . $item['quantity']);
            
            $orderItems[] = [
                'name' => $item['product_name'],
                'sku' => 'SKU' . $item['product_id'],
                'units' => (int)$item['quantity'],
                'selling_price' => (float)$item['product_price'],
                'discount' => 0,
                'tax' => 0,
                'hsn' => '123456'
            ];
            $totalWeight += $item['quantity'] * 0.5;
            $totalValue += $item['total_price'];
        }
        
        error_log("DEBUG: Total items: " . count($orderItems) . ", Total value: " . $totalValue);
        
        // Create Shiprocket order data
        $orderData = [
            'order_id' => $orderNumber,
            'order_date' => date('Y-m-d H:i'),
            'pickup_location' => getSetting('shiprocket_pickup_location', 'velu utr'),
            'channel_id' => '',
            'comment' => 'Order from ' . getSetting('site_name', 'Bluefifth'),
            'billing_customer_name' => $shippingAddress['first_name'] . ' ' . $shippingAddress['last_name'],
            'billing_last_name' => $shippingAddress['last_name'],
            'billing_address' => $shippingAddress['address'],
            'billing_address_2' => $shippingAddress['apartment'] ?? '',
            'billing_city' => $shippingAddress['city'],
            'billing_pincode' => $shippingAddress['pincode'],
            'billing_state' => $shippingAddress['state'],
            'billing_country' => $shippingAddress['country'],
            'billing_email' => $shippingAddress['email'],
            'billing_phone' => $shippingAddress['phone'],
            'shipping_is_billing' => true,
            'shipping_customer_name' => $shippingAddress['first_name'] . ' ' . $shippingAddress['last_name'],
            'shipping_last_name' => $shippingAddress['last_name'],
            'shipping_address' => $shippingAddress['address'],
            'shipping_address_2' => $shippingAddress['apartment'] ?? '',
            'shipping_city' => $shippingAddress['city'],
            'shipping_pincode' => $shippingAddress['pincode'],
            'shipping_country' => $shippingAddress['country'],
            'shipping_state' => $shippingAddress['state'],
            'shipping_email' => $shippingAddress['email'],
            'shipping_phone' => $shippingAddress['phone'],
            'order_items' => $orderItems,
            'payment_method' => 'Prepaid',
            'shipping_charges' => 0,
            'giftwrap_charges' => 0,
            'transaction_charges' => 0,
            'total_discount' => 0,
            'sub_total' => $totalValue,
            'length' => 10,
            'breadth' => 10,
            'height' => 10,
            'weight' => max(0.5, $totalWeight)
        ];
        
        error_log("DEBUG: Prepared order data for Shiprocket API call");
        error_log("DEBUG: API URL: https://apiv2.shiprocket.in/v1/external/orders/create/adhoc");
        
        // Make API call to Shiprocket
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $shiprocketApiToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        error_log("DEBUG: Making Shiprocket API call...");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("DEBUG: API Response - HTTP Code: {$httpCode}");
        error_log("DEBUG: cURL Error: " . ($curlError ?: 'None'));
        error_log("DEBUG: Response body: " . substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            
            if (isset($responseData['order_id']) && isset($responseData['shipment_id'])) {
                error_log("SUCCESS: Shiprocket order created - Order ID: {$responseData['order_id']}, Shipment ID: {$responseData['shipment_id']}");
                
                return [
                    'success' => true,
                    'order_id' => $responseData['order_id'],
                    'shipment_id' => $responseData['shipment_id'],
                    'tracking_number' => $responseData['awb_code'] ?? null
                ];
            } else {
                error_log("ERROR: Shiprocket API success but missing required fields in response");
                error_log("ERROR: Response keys: " . implode(', ', array_keys($responseData ?: [])));
                return ['success' => false, 'message' => 'Invalid API response format'];
            }
        } else {
            error_log("ERROR: Shiprocket API failed - HTTP {$httpCode}: " . $response);
            return ['success' => false, 'message' => "API error: HTTP {$httpCode}"];
        }
        
    } catch (Exception $e) {
        error_log("EXCEPTION: Shiprocket auto-order error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get Shiprocket tracking data via API
 * @param string $token Shiprocket API token
 * @param string $shipmentId Shipment ID
 * @return array|null Tracking data or null on failure
 */
function getShiprocketTrackingData($token, $shipmentId) {
    try {
        $url = "https://apiv2.shiprocket.in/v1/external/courier/track/shipment/{$shipmentId}";
        
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
            return json_decode($response, true);
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error getting Shiprocket tracking data: " . $e->getMessage());
        return null;
    }
}

/**
 * Test Shiprocket API connection
 * @param string $email Shiprocket email
 * @param string $password Shiprocket password
 * @return array Connection test result
 */
function testShiprocketAPI($email, $password) {
    try {
        $url = "https://apiv2.shiprocket.in/v1/external/auth/login";
        
        $data = json_encode([
            'email' => $email,
            'password' => $password
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Velona-ECommerce/1.0'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return ['success' => false, 'message' => 'Connection error: ' . $curlError];
        }
        
        if ($httpCode !== 200) {
            return ['success' => false, 'message' => 'HTTP Error: ' . $httpCode];
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid response format'];
        }
        
        if (isset($result['token'])) {
            return [
                'success' => true,
                'token' => $result['token'],
                'expiry' => date('Y-m-d H:i:s', time() + (10 * 24 * 60 * 60))
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['message'] ?? 'Authentication failed'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'API Error: ' . $e->getMessage()];
    }
}

function createShiprocketReturn($orderId, $returnReason = 'Quality issue') {
    try {
        // ADD THIS DEBUGGING BLOCK AT THE START
        error_log("=== RETURN CREATION DEBUG ===");
        error_log("Order ID: $orderId");
        error_log("Return Reason: $returnReason");
        error_log("FILES array: " . print_r($_FILES, true));
        error_log("POST array: " . print_r($_POST, true));
        
        if (isset($_FILES['return_photo'])) {
            error_log("File error code: " . $_FILES['return_photo']['error']);
            error_log("File size: " . $_FILES['return_photo']['size']);
            error_log("File type: " . $_FILES['return_photo']['type']);
            error_log("Temp file exists: " . (file_exists($_FILES['return_photo']['tmp_name']) ? 'YES' : 'NO'));
        }
        
        $conn = getConnection();
        
        // CRITICAL FIX: Handle file upload properly
        $photoPath = null;
        if (isset($_FILES['return_photo']) && $_FILES['return_photo']['error'] === UPLOAD_ERR_OK) {
            error_log("Processing return photo upload for order: $orderId");
            $photoPath = handleReturnPhotoUpload($_FILES['return_photo']);
            if (!$photoPath) {
                error_log("Photo upload failed for order: $orderId");
                return ['success' => false, 'message' => 'Failed to upload photo. Please try again.'];
            }
            error_log("Photo uploaded successfully: $photoPath");
        } else {
            // Check if photo upload had an error
            if (isset($_FILES['return_photo']) && $_FILES['return_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadError = $_FILES['return_photo']['error'];
                error_log("File upload error code: $uploadError");
                return ['success' => false, 'message' => 'Photo upload failed. Error code: ' . $uploadError];
            }
            
            // No photo uploaded - this is now required
            error_log("No photo uploaded for return request: $orderId");
            return ['success' => false, 'message' => 'Product photo is required for return processing.'];
        }

        $shiprocketApiToken = getSetting('shiprocket_api_token', '');
        if (empty($shiprocketApiToken)) {
            return ['success' => false, 'message' => 'Shiprocket API not configured'];
        }

        // Fetch order
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        // Check duplicate return
        $returnCheck = $conn->prepare("SELECT id FROM order_returns WHERE order_id = ?");
        $returnCheck->execute([$orderId]);
        if ($returnCheck->rowCount() > 0) {
            return ['success' => false, 'message' => 'Return already requested for this order'];
        }

        // Fetch items
        $itemStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$items) {
            return ['success' => false, 'message' => 'No items found'];
        }

        // Rest of your existing function logic remains the same...
        // [Continue with the existing Shiprocket API call and database operations]
        
        // Decode shipping address
        $shippingAddress = json_decode($order['shipping_address'], true);
        $customerName = trim(($shippingAddress['first_name'] ?? '') . ' ' . ($shippingAddress['last_name'] ?? ''));

        // Prepare order items for Shiprocket
        $orderItems = [];
        foreach ($items as $item) {
            $orderItems[] = [
                "name"          => $item['product_name'] ?? "Product",
                "sku"           => !empty($item['sku']) ? $item['sku'] : (string)$item['product_id'],
                "units"         => (int)($item['quantity'] ?? 1),
                "selling_price" => (float)($item['product_price'] ?? 100)
            ];
        }

        // Build Shiprocket return payload
        $returnData = [
            "order_id"              => (string)$order['shiprocket_order_id'],
            "shipment_id"           => (string)$order['shiprocket_shipment_id'],
            "pickup_location"       => getSetting('shiprocket_pickup_location', 'VELU UTR'),
            "order_date"            => date("Y-m-d", strtotime($order['created_at'] ?? 'now')),
            "payment_method"        => $order['payment_method'] ?? 'Prepaid',
            "sub_total"             => (float)($order['total_amount'] ?? 100),
            "length"                => 10,
            "breadth"               => 10,
            "height"                => 10,
            "weight"                => 1,
            "pickup_customer_name"  => $customerName ?: "Customer",
            "pickup_address"        => $shippingAddress['address'] ?? "Default Address",
            "pickup_city"           => $shippingAddress['city'] ?? "Erode",
            "pickup_state"          => $shippingAddress['state'] ?? "Tamil Nadu",
            "pickup_country"        => $shippingAddress['country'] ?? "IN",
            "pickup_pincode"        => $shippingAddress['pincode'] ?? "638703",
            "pickup_email"          => $shippingAddress['email'] ?? "customer@example.com",
            "pickup_phone"          => $shippingAddress['phone'] ?? "9999999999",
            "shipping_customer_name"=> $customerName ?: "Customer",
            "shipping_address"      => $shippingAddress['address'] ?? "Default Address",
            "shipping_city"         => $shippingAddress['city'] ?? "Erode",
            "shipping_state"        => $shippingAddress['state'] ?? "Tamil Nadu",
            "shipping_country"      => $shippingAddress['country'] ?? "IN",
            "shipping_pincode"      => $shippingAddress['pincode'] ?? "638703",
            "shipping_email"        => $shippingAddress['email'] ?? "customer@example.com",
            "shipping_phone"        => $shippingAddress['phone'] ?? "9999999999",
            "order_items"           => $orderItems,
            "return_reason"         => $returnReason
        ];

        $conn->beginTransaction();

        try {
            // Make Shiprocket API call
            $ch = curl_init("https://apiv2.shiprocket.in/v1/external/orders/create/return");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($returnData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer " . $shiprocketApiToken
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);
            
            error_log("Shiprocket Return API Response - HTTP: $httpCode, Response: " . $response);

            if ($httpCode === 200 && (isset($result['return_id']) || isset($result['order_id']))) {
                // SUCCESS: Shiprocket return created
                $shiprocketReturnId = $result['return_id'] ?? $result['order_id'];
                $status = strtolower(str_replace(' ', '_', $result['status'] ?? 'requested'));
                $awb = $result['awb_code'] ?? null;
                
                // Save to database
                $insertStmt = $conn->prepare("
                    INSERT INTO order_returns (
                        order_id, return_reason, shiprocket_return_id, 
                        return_status, return_awb, photo_path, 
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $orderId, $returnReason, $shiprocketReturnId, 
                    $status, $awb, $photoPath
                ]);
                
                // Update order status
                $updateStmt = $conn->prepare("UPDATE orders SET status = 'return_requested' WHERE id = ?");
                $updateStmt->execute([$orderId]);
                
                $conn->commit();
                
                return [
                    "success"   => true,
                    "return_id" => $shiprocketReturnId,
                    "status"    => $status,
                    "awb"       => $awb,
                    "message"   => "Return request created successfully and submitted to Shiprocket"
                ];
                
            } else {
                // FALLBACK: Save locally if Shiprocket fails
                error_log("Shiprocket API failed, saving return locally");
                
                $insertStmt = $conn->prepare("
                    INSERT INTO order_returns (
                        order_id, return_reason, return_status, 
                        photo_path, created_at, updated_at
                    ) VALUES (?, ?, 'requested', ?, NOW(), NOW())
                ");
                $insertStmt->execute([$orderId, $returnReason, $photoPath]);
                
                $updateStmt = $conn->prepare("UPDATE orders SET status = 'return_requested' WHERE id = ?");
                $updateStmt->execute([$orderId]);
                
                $conn->commit();
                
                return [
                    "success" => true, 
                    "message" => "Return request submitted. Admin will process it manually.",
                    "status" => "requested"
                ];
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Return creation exception: " . $e->getMessage());
        return ["success" => false, "message" => "System error occurred while processing return"];
    }
}

// Add this function after your existing createShiprocketReturn function
function cancelShiprocketReturn($shiprocketReturnId) {
    if (empty($shiprocketReturnId)) {
        error_log("No Shiprocket return ID provided for cancellation");
        return ['success' => false, 'message' => 'No Shiprocket return ID available'];
    }
    
    $shiprocketApiToken = getSetting('shiprocket_api_token', '');
    if (empty($shiprocketApiToken)) {
        error_log("Shiprocket API token not configured");
        return ['success' => false, 'message' => 'Shiprocket API not configured'];
    }
    
    try {
        // Shiprocket return cancellation endpoint
        $url = "https://apiv2.shiprocket.in/v1/external/orders/cancel/return/{$shiprocketReturnId}";
        
        error_log("Attempting to cancel Shiprocket return: $shiprocketReturnId");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([])); // Empty body for cancellation
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $shiprocketApiToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("Shiprocket cancellation - HTTP: $httpCode, Response: $response");
        
        if ($curlError) {
            error_log("Shiprocket cancellation cURL error: $curlError");
            return ['success' => false, 'message' => 'Network error during cancellation'];
        }
        
        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'message' => 'Return cancelled in Shiprocket successfully',
                'response' => $result
            ];
        } else {
            error_log("Shiprocket cancellation failed - HTTP: $httpCode, Response: $response");
            return [
                'success' => false,
                'message' => "Shiprocket cancellation failed: HTTP $httpCode",
                'response' => $response
            ];
        }
        
    } catch (Exception $e) {
        error_log("Shiprocket return cancellation exception: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'API call failed: ' . $e->getMessage()
        ];
    }
}

// Add this function for sending rejection notifications
function sendReturnRejectionNotification($orderId, $customerEmail, $customerName, $orderNumber) {
    try {
        error_log("Preparing Brevo return rejection email for Order: $orderNumber, Customer: $customerEmail");
        
        // Load email configuration with proper path detection
        $possiblePaths = [
            __DIR__ . '/../email-config.php',           // From functions.php
            __DIR__ . '/../../includes/email-config.php', // From admin/api/
            __DIR__ . '/../includes/email-config.php',    // Alternative path
            $_SERVER['DOCUMENT_ROOT'] . '/includes/email-config.php' // Absolute path
        ];
        
        $emailConfig = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $emailConfig = include $path;
                break;
            }
        }
        
        if (!$emailConfig) {
            error_log("Could not find email-config.php in any expected location");
            return ['success' => false, 'message' => 'Email configuration file not found'];
        }
        
        if (!isset($emailConfig['settings']['enabled']) || !$emailConfig['settings']['enabled']) {
            error_log("Email system is disabled or not configured");
            return ['success' => false, 'message' => 'Email system is disabled or not configured'];
        }
        
        if (empty($emailConfig['sendinblue']['api_key'])) {
            error_log("Brevo API key is missing");
            return ['success' => false, 'message' => 'Email API key is missing'];
        }
        
        // Get site settings
        $siteName = getSetting('site_name', 'Bluefifth');
        $supportEmail = getSetting('support_email', 'info@Bluefifth.in');
        
        // Prepare email data for Brevo
        $subject = "Return Request Rejected - Order #$orderNumber";
        
        // HTML email template
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Return Request Rejected</title>
            <style>
                body { 
                    font-family: 'Arial', sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f4f4f4; 
                }
                .email-container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background-color: #ffffff; 
                    border-radius: 10px; 
                    overflow: hidden; 
                    box-shadow: 0 0 20px rgba(0,0,0,0.1); 
                }
                .header { 
                    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); 
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 24px; 
                    font-weight: 600; 
                }
                .content { 
                    padding: 40px 30px; 
                }
                .order-info { 
                    background: #f8f9fa; 
                    padding: 20px; 
                    border-radius: 8px; 
                    margin: 20px 0; 
                    border-left: 4px solid #dc3545; 
                }
                .rejection-box { 
                    background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%); 
                    border: 2px solid #fc8181; 
                    padding: 20px; 
                    border-radius: 10px; 
                    margin: 25px 0; 
                }
                .rejection-box h3 { 
                    color: #c53030; 
                    margin-top: 0; 
                    display: flex; 
                    align-items: center; 
                }
                .rejection-box ul { 
                    margin: 15px 0; 
                    padding-left: 20px; 
                }
                .rejection-box li { 
                    margin: 8px 0; 
                    color: #744545; 
                }
                .next-steps { 
                    background: #e6fffa; 
                    border: 1px solid #81e6d9; 
                    padding: 20px; 
                    border-radius: 8px; 
                    margin: 25px 0; 
                }
                .cta-button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; 
                    padding: 12px 25px; 
                    text-decoration: none; 
                    border-radius: 6px; 
                    font-weight: 600; 
                    margin: 15px 0; 
                    text-align: center; 
                }
                .footer { 
                    background: #f8f9fa; 
                    padding: 25px; 
                    text-align: center; 
                    font-size: 14px; 
                    color: #666; 
                    border-top: 1px solid #e9ecef; 
                }
                .footer a { 
                    color: #667eea; 
                    text-decoration: none; 
                }
                @media (max-width: 600px) {
                    .email-container { margin: 10px; }
                    .header, .content { padding: 20px; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🚫 Return Request Update</h1>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>$customerName</strong>,</p>
                    
                    <div class='order-info'>
                        <strong>Order Number:</strong> #$orderNumber<br>
                        <strong>Status:</strong> Return Request Rejected<br>
                        <strong>Date:</strong> " . date('F j, Y') . "
                    </div>
                    
                    <p>We regret to inform you that your return request has been <strong>rejected</strong> after careful review by our team.</p>
                    
                    <div class='rejection-box'>
                        <h3>🔍 Reason for Rejection</h3>
                        <p>Your return request was reviewed based on our return policy guidelines. Unfortunately, this item does not qualify for return due to one or more of the following reasons:</p>
                        <ul>
                            <li><strong>Item condition:</strong> Product condition does not meet return standards</li>
                            <li><strong>Time limit:</strong> Request submitted outside the 7-day return window</li>
                            <li><strong>Category restriction:</strong> Item category not eligible for returns</li>
                            <li><strong>Usage signs:</strong> Product shows signs of use beyond normal inspection</li>
                            <li><strong>Policy violation:</strong> Request doesn't comply with our return policy terms</li>
                        </ul>
                    </div>
                    
                    <div class='next-steps'>
                        <h4>📋 What happens next?</h4>
                        <ul>
                            <li>Your original order status has been restored to <strong>'Delivered'</strong></li>
                            <li>No further action is required from your side</li>
                            <li>Your order remains complete and no refund will be processed</li>
                        </ul>
                    </div>
                    
                    <p><strong>Have questions about this decision?</strong></p>
                    <p>If you believe this decision was made in error or have questions about our return policy, please don't hesitate to contact our customer service team. We're here to help!</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='mailto:$supportEmail' class='cta-button'>📧 Contact Customer Service</a>
                    </div>
                    
                    <p>Thank you for your understanding and for choosing <strong>$siteName</strong>.</p>
                    
                    <p>Best regards,<br>
                    <strong>Customer Service Team</strong><br>
                    $siteName</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message regarding your return request.</p>
                    <p>For support, contact us at <a href='mailto:$supportEmail'>$supportEmail</a></p>
                    <p>&copy; " . date('Y') . " $siteName. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Plain text version for fallback
        $textContent = "
        Dear $customerName,
        
        RETURN REQUEST REJECTED - Order #$orderNumber
        
        We regret to inform you that your return request has been rejected after review by our team.
        
        REASON FOR REJECTION:
        Your request was reviewed based on our return policy guidelines, and unfortunately, this item does not qualify for return based on criteria such as:
        - Item condition not meeting return standards
        - Request outside the 7-day return window
        - Item category not eligible for returns
        - Product showing signs of use beyond normal inspection
        
        WHAT HAPPENS NEXT:
        - Your original order status has been restored to 'Delivered'
        - No further action is required from you
        - No refund will be processed for this order
        
        If you believe this decision was made in error or have questions about our return policy, please contact our customer service team at $supportEmail.
        
        Thank you for your understanding.
        
        Best regards,
        Customer Service Team
        $siteName
        ";
        
        // Brevo API data
        $data = [
            'sender' => [
                'name' => $emailConfig['sendinblue']['from_name'],
                'email' => $emailConfig['sendinblue']['from_email']
            ],
            'to' => [
                [
                    'email' => $customerEmail,
                    'name' => $customerName
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'textContent' => $textContent,
            'tags' => ['return-rejection', 'order-management'],
            'headers' => [
                'X-Order-Number' => $orderNumber,
                'X-Notification-Type' => 'return-rejection'
            ]
        ];
        
        // Send email via Brevo API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.sendinblue.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $emailConfig['sendinblue']['api_key']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("Brevo API Response - HTTP: $httpCode, Response: " . $response);
        
        if ($curlError) {
            error_log("Brevo API cURL error: $curlError");
            return ['success' => false, 'message' => 'Network error: ' . $curlError];
        }
        
        if ($httpCode === 201) {
            error_log("Return rejection email sent successfully via Brevo to: $customerEmail");
            return ['success' => true, 'message' => 'Return rejection notification sent successfully'];
        } else {
            $errorResponse = json_decode($response, true);
            $errorMessage = $errorResponse['message'] ?? 'Unknown Brevo API error';
            error_log("Brevo API failed - HTTP: $httpCode, Error: $errorMessage");
            
            // Fallback to PHP mail if enabled
            if ($emailConfig['settings']['fallback_to_php_mail']) {
                error_log("Attempting fallback to PHP mail");
                return sendFallbackReturnRejectionEmail($customerEmail, $customerName, $orderNumber, $subject, $htmlContent);
            }
            
            return ['success' => false, 'message' => 'Brevo API error: ' . $errorMessage];
        }
        
    } catch (Exception $e) {
        error_log("Return rejection email exception: " . $e->getMessage());
        return ['success' => false, 'message' => 'Email system error: ' . $e->getMessage()];
    }
}

// Fallback email function using PHP mail
function sendFallbackReturnRejectionEmail($customerEmail, $customerName, $orderNumber, $subject, $htmlContent) {
    try {
        $siteName = getSetting('site_name', 'Bluefifth');
        $supportEmail = getSetting('support_email', 'info@Bluefifth.in.');
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $siteName . ' <' . $supportEmail . '>',
            'Reply-To: ' . $supportEmail,
            'X-Mailer: PHP/' . phpversion(),
            'X-Order-Number: ' . $orderNumber,
            'X-Notification-Type: return-rejection'
        ];
        
        $mailSent = mail($customerEmail, $subject, $htmlContent, implode("\r\n", $headers));
        
        if ($mailSent) {
            error_log("Fallback return rejection email sent successfully to: $customerEmail");
            return ['success' => true, 'message' => 'Return rejection notification sent via fallback'];
        } else {
            error_log("Fallback email also failed for: $customerEmail");
            return ['success' => false, 'message' => 'Both Brevo and fallback email failed'];
        }
        
    } catch (Exception $e) {
        error_log("Fallback email exception: " . $e->getMessage());
        return ['success' => false, 'message' => 'Fallback email error: ' . $e->getMessage()];
    }
}

// Add this new function after createShiprocketReturn
function handleReturnPhotoUpload($file) {
    try {
        // CRITICAL FIX: Use absolute path from document root
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/returns/';
        
        error_log("=== PHOTO UPLOAD DEBUG ===");
        error_log("Upload directory: $uploadDir");
        error_log("Directory exists: " . (file_exists($uploadDir) ? 'YES' : 'NO'));
        error_log("Directory writable: " . (is_writable(dirname($uploadDir)) ? 'YES' : 'NO'));
        error_log("Document root: " . $_SERVER['DOCUMENT_ROOT']);
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("Failed to create upload directory: $uploadDir");
                return false;
            }
        }
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $fileType = mime_content_type($file['tmp_name']); // More reliable than $_FILES type
        
        if (!in_array($fileType, $allowedTypes)) {
            error_log("Invalid file type detected: $fileType (reported: {$file['type']})");
            return false;
        }
        
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            error_log("File too large: {$file['size']} bytes (max: $maxSize)");
            return false;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'return_' . time() . '_' . uniqid() . '.' . strtolower($extension);
        $fullPath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            error_log("Photo uploaded successfully: $fullPath");
            return 'uploads/returns/' . $filename; // Return relative path for database
        } else {
            error_log("Failed to move uploaded file from {$file['tmp_name']} to $fullPath");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Photo upload exception: " . $e->getMessage());
        return false;
    }
}

function canOrderBeReturned($order) {
    // Check if order is delivered
    if ($order['status'] !== 'delivered') {
        return false;
    }
    
    // Check if within 7 days of delivery
    $deliveredDate = new DateTime($order['updated_at']);
    $currentDate = new DateTime();
    $daysDiff = $currentDate->diff($deliveredDate)->days;
    
    return $daysDiff <= 7;
}

function getOrderReturnStatus($orderId) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT return_status, return_awb, created_at FROM order_returns WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        // Fix empty return_status
        if (empty($result['return_status'])) {
            $result['return_status'] = 'requested';
        }
        
        $result['return_awb_code'] = $result['return_awb'];
        
        return $result;
    } catch (Exception $e) {
        error_log("Error getting return status: " . $e->getMessage());
        return null;
    }
}

/**
 * Get session cart items for guests with product details
 * @return array Cart items with product details
 */
function getSessionCartItems() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['guest_cart']) || empty($_SESSION['guest_cart'])) {
        return [];
    }
    
    $cartItems = [];
    
    try {
        foreach ($_SESSION['guest_cart'] as $cartKey => $item) {
            $product = getProductById($item['product_id'], false);
            
            if ($product && $product['status'] === 'active') {
                // Get primary image — same raw URL the logged-in cart query returns
                $primaryImage = null;

                if (!empty($product['primary_image'])) {
                    $primaryImage = $product['primary_image'];
                } else {
                    try {
                        $conn = getConnection();
                        $stmt = $conn->prepare("
                            SELECT image_url
                            FROM product_images
                            WHERE product_id = ? AND is_primary = 1
                            LIMIT 1
                        ");
                        $stmt->execute([$item['product_id']]);
                        $imageResult = $stmt->fetch();
                        if ($imageResult) {
                            $primaryImage = $imageResult['image_url'];
                        }
                    } catch (Exception $e) {
                        error_log("Error loading product image: " . $e->getMessage());
                    }
                }

                if (empty($primaryImage)) {
                    $primaryImage = '/ecommerce-project/assets/images/default-product.jpg';
                }
                
                $cartItems[] = [
                    'cart_key' => $cartKey,
                    'id' => $cartKey,
                    'product_id' => $item['product_id'],
                    'product_name' => $product['name'],
                    'product_price' => $product['price'],
                    'product_image' => $primaryImage,  // FIXED IMAGE
                    'quantity' => $item['quantity'],
                    'size' => $item['size'] ?? null,
                    'stock_quantity' => $product['stock_quantity'],
                    'total_price' => $item['quantity'] * $product['price']
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting session cart items: " . $e->getMessage());
    }
    
    return $cartItems;
}

/**
 * Get session cart summary for guests
 * @return array Cart summary
 */
function getSessionCartSummary() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['guest_cart']) || empty($_SESSION['guest_cart'])) {
        return ['item_count' => 0, 'total_quantity' => 0, 'total_amount' => 0];
    }
    
    $itemCount = 0;
    $totalQuantity = 0;
    $totalAmount = 0;
    
    try {
        foreach ($_SESSION['guest_cart'] as $item) {
            $product = getProductById($item['product_id'], false);
            
            if ($product && $product['status'] === 'active') {
                $itemCount++;
                $totalQuantity += $item['quantity'];
                $totalAmount += $item['quantity'] * $product['price'];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error calculating session cart summary: " . $e->getMessage());
    }
    
    return [
        'item_count' => $itemCount,
        'total_quantity' => $totalQuantity,
        'total_amount' => $totalAmount
    ];
}

/**
 * Add item to session cart for guests
 * @param int $productId Product ID
 * @param int $quantity Quantity
 * @param string $size Size
 * @return array Result
 */
function addToSessionCart($productId, $quantity, $size = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = [];
    }
    
    // Create unique cart key
    $cartKey = $productId . '_' . ($size ?: 'no_size');
    
    // Check if same product+size already exists
    $existingKey = null;
    foreach ($_SESSION['guest_cart'] as $key => $item) {
        if ($item['product_id'] == $productId && ($item['size'] ?? null) === $size) {
            $existingKey = $key;
            break;
        }
    }
    
    if ($existingKey) {
        // Update existing item
        $_SESSION['guest_cart'][$existingKey]['quantity'] += $quantity;
        return ['success' => true, 'message' => 'Cart updated successfully'];
    } else {
        // Add new item
        $_SESSION['guest_cart'][$cartKey] = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'size' => $size,
            'added_at' => time()
        ];
        return ['success' => true, 'message' => 'Item added to cart'];
    }
}

/**
 * Merge guest cart with user cart after login
 * @param int $userId User ID
 * @return bool Success status
 */
function mergeGuestCartWithUserCart($userId) {
    if (!isset($_SESSION['guest_cart']) || empty($_SESSION['guest_cart'])) {
        return true; // Nothing to merge
    }

    try {
        foreach ($_SESSION['guest_cart'] as $item) {
            addToCart($userId, $item['product_id'], $item['quantity'], $item['size'] ?? null);
        }
        
        // Clear guest cart after successful merge
        unset($_SESSION['guest_cart']);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error merging guest cart: " . $e->getMessage());
        return false;
    }
}

function associateGuestOrdersWithUser($userId, $userEmail) {
    try {
        $conn = getConnection();
        
        // Find all guest users with the same email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND user_type = 'guest'");
        $stmt->execute([$userEmail]);
        $guestUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ordersTransferred = 0;
        
        foreach ($guestUsers as $guestUser) {
            $guestUserId = $guestUser['id'];
            
            // Transfer orders from guest user to logged-in user
            $updateStmt = $conn->prepare("UPDATE orders SET user_id = ? WHERE user_id = ?");
            $updateResult = $updateStmt->execute([$userId, $guestUserId]);
            
            if ($updateResult) {
                $ordersTransferred += $updateStmt->rowCount();
            }
            
            // Optionally: Remove the guest user record after transferring orders
            $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND user_type = 'guest'");
            $deleteStmt->execute([$guestUserId]);
        }
        
        error_log("Associated {$ordersTransferred} guest orders with user {$userId}");
        return $ordersTransferred;
        
    } catch (Exception $e) {
        error_log("Error associating guest orders: " . $e->getMessage());
        return 0;
    }
}



// ============================================================================
// END OF FILE
// ============================================================================

/**
 * VELONA E-COMMERCE & REFERRAL SYSTEM - COMPLETE FUNCTIONS LIBRARY
 * 
 * This file contains the complete merged functionality from both:
 * 1. Original Referral System (Enhanced with bulletproof error handling)
 * 2. New E-commerce System (Full product, order, and cart management)
 * 
 * Key Features Included:
 * ✅ Enhanced wallet system with exact 1:1 money ratio
 * ✅ Complete referral system with month-based earning rates (10% first month, 5% others)
 * ✅ Full e-commerce functionality (products, categories, orders, cart)
 * ✅ Professional email system with SendinblueMailer integration
 * ✅ Admin dashboard functions and analytics
 * ✅ Claim management system with monthly restrictions
 * ✅ System maintenance and health monitoring
 * ✅ Comprehensive error handling and logging
 * ✅ Security utilities and validation functions
 * ✅ COMPLETE ADMIN SESSION MANAGEMENT
 * ✅ FIXED SQL SYNTAX FOR MARIADB COMPATIBILITY
 * 
 * Version: 2.0.1 (Updated with Admin Functions)
 * Compatible with: PHP 7.4+ and MySQL 5.7+ / MariaDB 10.3+
 * Author: Velona Development Team
 * Last Updated: 2025
 */

?>