<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: text/plain');

echo "=== SHIPROCKET DEBUG ===\n\n";

// 1. Check if functions exist
echo "1. CHECKING FUNCTIONS:\n";
echo "testShiprocketAPI exists: " . (function_exists('testShiprocketAPI') ? 'YES' : 'NO') . "\n";
echo "getShiprocketTrackingData exists: " . (function_exists('getShiprocketTrackingData') ? 'YES' : 'NO') . "\n";
echo "getSetting exists: " . (function_exists('getSetting') ? 'YES' : 'NO') . "\n\n";

// 2. Check database settings
echo "2. DATABASE SETTINGS:\n";
$settings = [
    'shiprocket_enabled',
    'shiprocket_email',
    'shiprocket_password',
    'shiprocket_api_token',
    'shiprocket_token_expiry'
];

foreach ($settings as $key) {
    $value = getSetting($key);
    if ($key == 'shiprocket_password' || $key == 'shiprocket_api_token') {
        echo $key . ": " . (!empty($value) ? 'SET (hidden)' : 'NOT SET') . "\n";
    } else {
        echo $key . ": " . ($value ?: 'NOT SET') . "\n";
    }
}

echo "\n3. TESTING CONNECTION:\n";
$email = getSetting('shiprocket_email');
$password = getSetting('shiprocket_password');

if (empty($email) || empty($password)) {
    echo "ERROR: Missing email or password!\n";
    echo "Email: " . ($email ?: 'MISSING') . "\n";
    echo "Password: " . (!empty($password) ? 'SET' : 'MISSING') . "\n";
} else {
    echo "Attempting connection with:\n";
    echo "Email: " . $email . "\n";
    echo "Password: SET\n\n";
    
    // Test the API
    if (function_exists('testShiprocketAPI')) {
        $result = testShiprocketAPI($email, $password);
        echo "Result:\n";
        print_r($result);
    } else {
        echo "ERROR: testShiprocketAPI function not found!\n";
    }
}

echo "\n4. CHECKING ORDERS TABLE:\n";
try {
    $conn = getConnection();
    $stmt = $conn->query("DESCRIBE orders");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $shiprocketColumns = ['shiprocket_shipment_id', 'shiprocket_order_id', 'tracking_number', 'courier_partner'];
    foreach ($shiprocketColumns as $col) {
        echo $col . ": " . (in_array($col, $columns) ? 'EXISTS' : 'MISSING') . "\n";
    }
} catch (Exception $e) {
    echo "ERROR checking table: " . $e->getMessage() . "\n";
}

echo "\n5. RAW API TEST:\n";
if ($email && $password) {
    $url = "https://apiv2.shiprocket.in/v1/external/auth/login";
    $data = json_encode(['email' => $email, 'password' => $password]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: " . $httpCode . "\n";
    if ($error) {
        echo "CURL Error: " . $error . "\n";
    }
    
    if ($response) {
        $decoded = json_decode($response, true);
        if (isset($decoded['token'])) {
            echo "SUCCESS! Token received: " . substr($decoded['token'], 0, 20) . "...\n";
        } else {
            echo "Response: " . $response . "\n";
        }
    }
}

echo "\n=== END DEBUG ===\n";
?>