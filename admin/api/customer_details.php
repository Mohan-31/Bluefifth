<?php
// api/customer_details.php - Handles single customer detail requests

include('../includes/database.php');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $sql = "SELECT id, name, email, created_at, last_login, status,
                   wallet_balance, total_earned, total_claimed, total_orders, total_spent,
                   referral_code, referral_count, referral_earnings,
                   aadhar_front, aadhar_back, pan_front, pan_back
            FROM users
            WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $customer = [
                'success' => true,
                'customer' => [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'created_at' => $row['created_at'],
                    'last_login' => $row['last_login'],
                    'status' => $row['status'],
                    'wallet_balance' => $row['wallet_balance'],
                    'total_earned' => $row['total_earned'],
                    'total_claimed' => $row['total_claimed'],
                    'total_orders' => $row['total_orders'],
                    'total_spent' => $row['total_spent'],
                    'referral_code' => $row['referral_code'],
                    'referral_count' => $row['referral_count'],
                    'referral_earnings' => $row['referral_earnings'],
                    'aadhar_front' => $row['aadhar_front'],
                    'aadhar_back' => $row['aadhar_back'],
                    'pan_front' => $row['pan_front'],
                    'pan_back' => $row['pan_back']
                ]
            ];

            header('Content-Type: application/json');
            echo json_encode($customer);
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database query failed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>