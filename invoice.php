<?php
session_start();
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Check authentication - ALLOW GUEST ACCESS FOR RECENT ORDERS
$isAdmin = isset($_SESSION['admin_id']);
$isCustomer = isset($_SESSION['user_id']);
$orderId = intval($_GET['order_id'] ?? 0);

if (!$orderId) {
    echo '<div style="text-align: center; padding: 50px; color: red;">Order ID is required</div>';
    exit;
}

// Allow access for:
// 1. Admin users (can view any invoice)
// 2. Logged-in customers (can view their own invoices)
// 3. Guest users (can view invoices within 24 hours of order creation)
if (!$isAdmin && !$isCustomer) {
    // For guests, check if order was created recently (within 24 hours)
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT created_at FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $orderDate = $stmt->fetchColumn();
    
    if (!$orderDate || (time() - strtotime($orderDate)) > 86400) { // 86400 = 24 hours
        echo '<div style="text-align: center; padding: 50px; color: red;">Please log in to view invoices.</div>';
        echo '<div style="text-align: center;"><a href="auth/login.php">Login</a></div>';
        exit;
    }
}

// If customer (not admin), verify they own this order
if ($isCustomer && !$isAdmin) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $orderOwner = $stmt->fetchColumn();
    
    if ($orderOwner != $_SESSION['user_id']) {
        echo '<div style="text-align: center; padding: 50px; color: red;">Access denied. You can only view your own invoices.</div>';
        exit;
    }
}

try {
    $conn = getConnection();
    
    // Get order details with customer info
    $stmt = $conn->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo '<div style="text-align: center; padding: 50px; color: red;">Order not found</div>';
        exit;
    }
    
    // ✅ Get order items with HSN code
    $stmt = $conn->prepare("
        SELECT 
            oi.*, 
            COALESCE(p.name, oi.product_name) AS product_name, 
            p.description AS product_description,
            c.hsn_code
        FROM order_items oi 
        LEFT JOIN products p ON oi.product_id = p.id 
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get dynamic settings
    $siteName = getSetting('site_name', 'Velona');
    $siteDescription = getSetting('site_description', 'Premium clothing with sustainable fashion');
    $contactEmail = getSetting('contact_email', 'contact@velona.com');
    $contactPhone = getSetting('contact_phone', '+91 9876543210');
    $currencySymbol = getSetting('currency_symbol', '₹');
    $currency = getSetting('currency', 'INR');
    
    // Parse shipping address
    $shippingAddress = json_decode($order['shipping_address'], true);
    $addressString = 'N/A';
    $customerPhone = 'N/A';
    if ($shippingAddress) {
        $addressString = $shippingAddress['address'];
        if (!empty($shippingAddress['apartment'])) {
            $addressString .= ', ' . $shippingAddress['apartment'];
        }
        $addressString .= ', ' . $shippingAddress['city'] . ', ' . $shippingAddress['state'] . ' ' . $shippingAddress['pincode'] . ', ' . $shippingAddress['country'];
        $customerPhone = $shippingAddress['phone'] ?? 'N/A';
    }
    
} catch (Exception $e) {
    echo '<div style="text-align: center; padding: 50px; color: red;">Error generating invoice: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($order['order_number']) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            background: white;
        }
        
        .invoice-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 15px;
        }
        
        /* Header */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }
        
        .company-info h1 {
            font-size: 24px;
            color: #667eea;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .company-info p {
            color: #666;
            margin-bottom: 2px;
            font-size: 10px;
        }
        
        .invoice-title {
            text-align: right;
        }
        
        .invoice-title h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .invoice-number {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            font-size: 12px;
        }
        
        /* Invoice Details */
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .invoice-meta {
            width: 45%;
        }
        
        .customer-info {
            width: 50%;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
            margin-left: 20px;
        }
        
        .invoice-meta h3, .customer-info h3 {
            color: #333;
            font-size: 12px;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #eee;
        }
        
        .invoice-meta p, .customer-info p {
            margin-bottom: 4px;
            color: #666;
            font-size: 10px;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .items-table thead th {
            background: #667eea;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        
        .items-table thead th:last-child,
        .items-table thead th:nth-child(3),
        .items-table thead th:nth-child(4) {
            text-align: right;
        }
        
        .items-table tbody td {
            padding: 8px 6px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
            font-size: 10px;
        }
        
        .items-table tbody td:last-child,
        .items-table tbody td:nth-child(3),
        .items-table tbody td:nth-child(4) {
            text-align: right;
        }
        
        .items-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .product-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 2px;
        }
        
        .product-details {
            font-size: 9px;
            color: #666;
        }
        
        /* Summary */
        .invoice-summary {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        
        .summary-table {
            width: 250px;
            border-collapse: collapse;
        }
        
        .summary-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
            font-size: 10px;
        }
        
        .summary-table td:first-child {
            text-align: left;
            color: #666;
        }
        
        .summary-table td:last-child {
            text-align: right;
            font-weight: bold;
            color: #333;
        }
        
        .summary-table .total-row {
            background: #f8f9fa;
            border-top: 2px solid #667eea;
            border-bottom: 2px solid #667eea;
        }
        
        .summary-table .total-row td {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            padding: 10px;
        }
        
        .discount-row td:last-child {
            color: #28a745 !important;
        }
        
        /* Payment Info */
        .payment-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .payment-info h3 {
            color: #28a745;
            margin-bottom: 8px;
            font-size: 12px;
        }
        
        .payment-info p {
            margin-bottom: 4px;
            font-size: 10px;
        }
        
        .payment-status {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Footer */
        .invoice-footer {
            border-top: 1px solid #eee;
            padding-top: 15px;
            text-align: center;
        }
        
        .footer-note {
            font-size: 9px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .footer-contact {
            font-size: 10px;
            color: #333;
        }
        
        /* Print Styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .invoice-container {
                padding: 0;
                margin: 0;
                box-shadow: none;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        /* Print Button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
            font-size: 12px;
        }
        
        .print-button:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        
        .download-button {
            position: fixed;
            top: 70px;
            right: 20px;
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
            font-size: 12px;
        }
        
        .download-button:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Print Invoice
    </button>
    
    <button class="download-button no-print" onclick="downloadPDF()">
        📄 Download PDF
    </button>
    
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-info">
                <h1><?= htmlspecialchars($siteName) ?></h1>
                <p><?= htmlspecialchars($siteDescription) ?></p>
                <p>📧 <?= htmlspecialchars($contactEmail) ?></p>
                <p>📞 <?= htmlspecialchars($contactPhone) ?></p>
                <p>🌐 www.bluefifth.in</p>
            </div>
            <div class="invoice-title">
                <h2>INVOICE</h2>
                <div class="invoice-number"><?= htmlspecialchars($order['order_number']) ?></div>
            </div>
        </div>
        
        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="invoice-meta">
                <h3>📄 Invoice Details</h3>
                <p><strong>Invoice Date:</strong> <?= date('M d, Y', strtotime($order['created_at'])) ?></p>
                <p><strong>Order ID:</strong> <?= htmlspecialchars($order['id']) ?></p>
                <p><strong>Payment Method:</strong> online payment</p>
                <p><strong>Currency:</strong> <?= htmlspecialchars($currency) ?></p>
            </div>
            
            <div class="customer-info">
                <h3>👤 Bill To</h3>
                <p><strong><?= htmlspecialchars($order['customer_name'] ?? 'Guest Customer') ?></strong></p>
                <p>📧 <?= htmlspecialchars($order['customer_email'] ?? 'N/A') ?></p>
                <p>📞 <?= htmlspecialchars($customerPhone) ?></p>
                <br>
                <p><strong>📦 Shipping Address:</strong></p>
                <p><?= htmlspecialchars($addressString) ?></p>
            </div>
        </div>
        
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product Description</th>
                    <th style="width: 60px;">Qty</th>
                    <th style="width: 80px;">Unit Price</th>
                    <th style="width: 80px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $item): ?>
                <tr>
                    <td>
                        <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                        <div class="product-details">
                            <?php if (!empty($item['size'])): ?>
                                Size: <?= htmlspecialchars($item['size']) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($item['hsn_code'])): ?>
                                HSN Code: <?= htmlspecialchars($item['hsn_code']) ?>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td><?= $item['quantity'] ?></td>
                    <td><?= $currencySymbol ?><?= number_format($item['product_price'], 2) ?></td>
                    <td><?= $currencySymbol ?><?= number_format($item['total_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Summary -->
        <div class="invoice-summary">
            <table class="summary-table">
                <?php 
                // Calculate tax-exclusive subtotal dynamically
                $taxRate = (float) getSetting('tax_rate', 18.0);
                $taxExclusiveSubtotal = $order['total_amount'];
                $calculatedTax = $order['tax_amount'] ?? 0;
                
                // If tax exists, calculate the tax-exclusive amount
                if ($calculatedTax > 0) {
                    $taxExclusiveSubtotal = $order['total_amount'] - $calculatedTax;
                }
                ?>
                
                <?php if ($order['is_combo_applied']): ?>
                <!-- Combo Pricing Display -->
                <tr>
                    <td>Regular Subtotal:</td>
                    <td style="text-decoration: line-through; color: #999;"><?= $currencySymbol ?><?= number_format($order['total_amount'] + ($order['combo_savings'] ?? 0), 2) ?></td>
                </tr>
                <tr class="discount-row">
                    <td>🎉 Combo Discount 
                        <?php if ($order['combo_type'] == '3_for_1199'): ?>
                            (3 for ₹1199):
                        <?php else: ?>
                            (5 for ₹1699):
                        <?php endif; ?>
                    </td>
                    <td>-<?= $currencySymbol ?><?= number_format($order['combo_savings'], 2) ?></td>
                </tr>
                <tr>
                    <td>After Combo Subtotal:</td>
                    <td><?= $currencySymbol ?><?= number_format($order['total_amount'], 2) ?></td>
                </tr>
                <?php else: ?>
                <!-- Regular Pricing -->
                <tr>
                    <td>After Combo Subtotal:</td>
                    <td><?= $currencySymbol ?><?= number_format($order['total_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if (isset($order['coupon_code']) && !empty($order['coupon_code'])): ?>
                <!-- Coupon Discount Display -->
                <tr class="discount-row">
                    <td>💰 Coupon Discount (<?= htmlspecialchars($order['coupon_code']) ?> - <?= $order['coupon_discount_percentage'] ?>%):</td>
                    <td>-<?= $currencySymbol ?><?= number_format($order['coupon_discount_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if (isset($order['tax_amount']) && $order['tax_amount'] > 0): ?>
                <tr>
                    <td>Tax (<?= $taxRate ?>%):</td>
                    <td><?= $currencySymbol ?><?= number_format($calculatedTax, 2) ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if (isset($order['shipping_amount']) && $order['shipping_amount'] > 0): ?>
                <tr>
                    <td>Shipping:</td>
                    <td><?= $currencySymbol ?><?= number_format($order['shipping_amount'], 2) ?></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td>Shipping:</td>
                    <td style="color: #28a745;">FREE</td>
                </tr>
                <?php endif; ?>
                
                <?php if (isset($order['wallet_points_used']) && $order['wallet_points_used'] > 0): ?>
                <tr class="discount-row">
                    <td>💰 Wallet Discount:</td>
                    <td>-<?= $currencySymbol ?><?= number_format($order['wallet_points_used'], 2) ?></td>
                </tr>
                <?php endif; ?>
                
                <tr class="total-row">
                    <td>TOTAL PAID:</td>
                    <td><?= $currencySymbol ?><?= number_format($order['final_amount'], 2) ?></td>
                </tr>
            </table>
        </div>   
                
        <!-- Payment Info -->
        <div class="payment-info">
            <h3>💳 Payment Information</h3>
            <p><strong>Payment Status:</strong> 
                <span class="payment-status status-<?= strtolower($order['payment_status']) ?>">
                    <?= strtoupper($order['payment_status']) ?>
                </span>
            </p>
            <p><strong>Transaction ID:</strong> <?= htmlspecialchars($order['razorpay_payment_id'] ?? 'N/A') ?></p>
            <p><strong>Payment Date:</strong> <?= date('M d, Y', strtotime($order['created_at'])) ?></p>
            
            <?php if ($order['is_combo_applied']): ?>
                <p><strong>🎉 Special Offer Applied:</strong> 
                    <?php if ($order['combo_type'] == '3_for_1199'): ?>
                        3 Products Combo (₹1199) - Saved ₹<?= number_format($order['combo_savings'], 2) ?>
                    <?php else: ?>
                        5 Products Combo (₹1699) - Saved ₹<?= number_format($order['combo_savings'], 2) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            
            <?php if (isset($order['coupon_code']) && !empty($order['coupon_code'])): ?>
                <p><strong>💰 Coupon Applied:</strong> 
                    <?= htmlspecialchars($order['coupon_code']) ?> (<?= $order['coupon_discount_percentage'] ?>% off) - Saved ₹<?= number_format($order['coupon_discount_amount'], 2) ?>
                </p>
            <?php endif; ?>
            
            <?php if (!empty($order['referral_code'])): ?>
                <p><strong>Referral Code Used:</strong> <?= htmlspecialchars($order['referral_code']) ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="invoice-footer">
            <p class="footer-note">
                <strong>Thank you for your business!</strong><br>
                CIN: U13999TZ2021PTC037885<br>
                GSTIN: 33AAJCT0905H2ZK<br>
                This is a computer-generated invoice. If you have any questions, please contact us.
            </p>
            <p class="footer-contact">
                <?= htmlspecialchars($siteName) ?> | <?= htmlspecialchars($contactEmail) ?> | <?= htmlspecialchars($contactPhone) ?><br>
                © <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. All rights reserved.
            </p>
        </div>
    </div>
    
    <script>
        // Download PDF functionality (using browser's print to PDF)
        function downloadPDF() {
            // Hide print buttons
            const printButtons = document.querySelectorAll('.no-print');
            printButtons.forEach(btn => btn.style.display = 'none');
            
            // Trigger print dialog
            window.print();
            
            // Show buttons again after print dialog
            setTimeout(() => {
                printButtons.forEach(btn => btn.style.display = 'block');
            }, 1000);
        }
        
        // Auto-print functionality (optional)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_print') === '1') {
            setTimeout(() => {
                window.print();
            }, 1000);
        }
    </script>
</body>
</html>