<?php
$host     = getenv('DB_HOST')     ?: 'localhost';
$dbname   = getenv('DB_NAME')     ?: 'ecommerce_referral_db';
$username = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Do not expose PDO error messages to the browser in production.
    error_log("Database connection error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Database unavailable']));
}

function getConnection() {
    global $conn;
    return $conn;
}