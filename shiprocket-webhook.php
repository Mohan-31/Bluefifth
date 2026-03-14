<?php
// shiprocket-webhook.php - Place this in ROOT directory like return-webhook.php
require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/functions.php";

// -----------------------------
// CONFIG - Match your return webhook exactly
// -----------------------------
$expectedToken = "BFOrder2025\$SecretKey"; // Order webhook token

// -----------------------------
// FORCE CREATE LOGS FOLDER
// -----------------------------
$logDir = __DIR__ . "/logs";
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . "/order_webhook.log";
if (!file_exists($logFile)) {
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] Order webhook log created." . PHP_EOL);
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
    'order confirmed' => 'processing',
    'ready to ship' => 'processing',
    'picked up' => 'shipped',
    'in transit' => 'shipped',
    'out for delivery' => 'shipped',
    'delivered' => 'delivered',
    'rto' => 'cancelled',
    'cancelled' => 'cancelled'
];

// -----------------------------
// PROCESS ORDER UPDATE
// -----------------------------
try {
    $conn = getConnection();

    $shipmentId = $data['shipment_id'] ?? null;
    $status = $data['status'] ?? $data['current_status'] ?? '';
    $trackingNumber = $data['awb_code'] ?? $data['awb'] ?? '';

    if ($shipmentId) {
        // Find order by Shiprocket shipment ID
        $stmt = $conn->prepare("SELECT id, order_number FROM orders WHERE shiprocket_shipment_id = ?");
        $stmt->execute([$shipmentId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $statusKey = strtolower(trim($status));
            $orderStatus = $statusMapping[$statusKey] ?? 'processing';

            // Update order
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = ?, tracking_number = ?, updated_at = NOW() 
                WHERE shiprocket_shipment_id = ?
            ");
            $stmt->execute([$orderStatus, $trackingNumber, $shipmentId]);

            file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] Updated order {$order['order_number']} to {$orderStatus}" . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] Order not found for shipment: $shipmentId" . PHP_EOL, FILE_APPEND);
        }
    }

    http_response_code(200);
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] ERROR: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error"]);
}
?>