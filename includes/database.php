<?php
$host = "localhost";
$dbname = "ecommerce_referral_db";
$username = "root";
$password = "";

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