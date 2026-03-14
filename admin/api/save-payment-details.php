<?php
session_start();
include 'db_connect.php'; // your DB connection file

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$mobile = $data['mobile_number'];
$upi = $data['upi_id'];

$stmt = $conn->prepare("UPDATE users SET mobile_number=?, upi_id=? WHERE id=?");
$result = $stmt->execute([$mobile, $upi, $user_id]);

echo json_encode(['success' => $result]);
