<?php
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';
$conn = getConnection();

if ($action === 'save_guest_profile') {
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    
    if (empty($email) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Email and name are required']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, address = ?, city = ?, state = ?, pincode = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $address, $city, $state, $pincode, $user['id']]);
            $userId = $user['id'];
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, phone, address, city, state, pincode, user_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'guest')");
            $stmt->execute([$name, $email, $phone, $address, $city, $state, $pincode]);
            $userId = $conn->lastInsertId();
        }
        
        $_SESSION['guest_user_id'] = $userId;
        echo json_encode(['success' => true, 'user_id' => $userId]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

elseif ($action === 'get_guest_profile') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email required']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("SELECT name, email, phone, address, city, state, pincode FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>