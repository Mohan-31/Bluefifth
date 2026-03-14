<?php
session_start();
require_once "../includes/database.php"; 

header('Content-Type: application/json');


// ✅ Step 2: Validate POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

$user_id  = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$status   = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($user_id <= 0 || !in_array($status, ['verified', 'not_verified'])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid input."
    ]);
    exit;
}

// ✅ Step 3: Update KYC status
try {
    $stmt = $conn->prepare("UPDATE users SET kyc_status = ? WHERE id = ?");
    $stmt->execute([$status, $user_id]);

    echo json_encode([
        "success" => true,
        "message" => "KYC status updated successfully.",
        "new_status" => ucfirst(str_replace('_', ' ', $status))
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
    exit;
}
