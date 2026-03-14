<?php
// return-webhook.php

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/functions.php";

// -----------------------------
// CONFIG
// -----------------------------
$expectedToken = "BFReturn2025\$SecretKey"; // Shiprocket secret token

// -----------------------------
// FORCE CREATE LOGS FOLDER
// -----------------------------
$logDir = __DIR__ . "/logs";
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . "/return_webhook.log";
if (!file_exists($logFile)) {
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] Log folder and file created." . PHP_EOL);
}

// -----------------------------
// VERIFY SHIPROCKET TOKEN
// -----------------------------
$receivedToken = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($receivedToken !== $expectedToken) {
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] SECURITY: Invalid token" . PHP_EOL, FILE_APPEND);
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// -----------------------------
// GET RAW POST DATA
// -----------------------------
$raw = file_get_contents("php://input");
file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] RAW: " . $raw . PHP_EOL, FILE_APPEND);

$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON"]);
    exit;
}

// -----------------------------
// STATUS MAPPING
// -----------------------------
$statusMapping = [
    'requested' => 'requested',
    'pickup_scheduled' => 'pickup_scheduled',
    'collected' => 'collected',
    'received' => 'received',
    'processed' => 'processed',
    'picked_up' => 'collected',
    'delivered' => 'processed'
];

// -----------------------------
// PROCESS RETURN / TRACKING
// -----------------------------
try {
    $conn = getConnection(); // PDO connection

    // IDs from Shiprocket
    $shiprocketReturnId = $data['return_id'] ?? null;
    $orderId            = $data['order_id'] ?? $data['sr_order_id'] ?? null;

    // AWB
    $awbCode = $data['awb'] ?? $data['awb_code'] ?? null;

    // Raw status from Shiprocket
    $status = $data['status'] ?? $data['shipment_status'] ?? $data['current_status'] ?? 'requested';
    $statusKey = strtolower($status);
    $finalStatus = $statusMapping[$statusKey] ?? 'requested';

    // Determine ID to use in table
    $idToUse = $shiprocketReturnId ?? $orderId;

    if ($idToUse && $orderId) {
        // Check if return/order already exists
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM order_returns WHERE shiprocket_return_id = ?");
        $stmtCheck->execute([$idToUse]);
        $exists = $stmtCheck->fetchColumn();

        if ($exists) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE order_returns
                SET return_status = ?, 
                    return_awb = ?, 
                    updated_at = NOW()
                WHERE shiprocket_return_id = ?
            ");
            $stmt->execute([$finalStatus, $awbCode, $idToUse]);

            file_put_contents(
                $logFile, 
                "[" . date("Y-m-d H:i:s") . "] Updated Return → ID: {$idToUse}, Status: {$finalStatus}, AWB: {$awbCode}" . PHP_EOL, 
                FILE_APPEND
            );
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO order_returns (order_id, shiprocket_return_id, return_status, return_awb, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$orderId, $idToUse, $finalStatus, $awbCode]);

            file_put_contents(
                $logFile, 
                "[" . date("Y-m-d H:i:s") . "] Inserted New Return → ID: {$idToUse}, Status: {$finalStatus}, AWB: {$awbCode}" . PHP_EOL, 
                FILE_APPEND
            );
        }
    } else {
        file_put_contents(
            $logFile, 
            "[" . date("Y-m-d H:i:s") . "] ERROR: Missing order_id or return_id in payload" . PHP_EOL, 
            FILE_APPEND
        );
    }

    http_response_code(200);
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] ERROR: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error"]);
}
?>
