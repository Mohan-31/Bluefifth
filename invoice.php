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
    <title>Invoice – <?= htmlspecialchars($order['order_number']) ?></title>

    <!-- PDF libs -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        /* ── reset ── */
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        /* ── page setup ── */
        @page { size: A4 portrait; margin: 12mm; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.55;
            color: #1a1a1a;
            background: #F0EBE1;
            min-height: 100vh;
        }

        /* ── action bar (no-print) ── */
        .action-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: rgba(240,235,225,.95);
            backdrop-filter: blur(8px);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 10px 20px;
            z-index: 100;
            border-bottom: 1px solid #d5cfc4;
        }
        .action-bar button {
            padding: 9px 20px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            cursor: pointer;
            border: 2px solid #1a1a1a;
            transition: all .18s;
        }
        .btn-print  { background: transparent; color: #1a1a1a; }
        .btn-print:hover  { background: #1a1a1a; color: #F0EBE1; }
        .btn-dl { background: #C41930; color: #fff; border-color: #C41930; }
        .btn-dl:hover { background: #a01226; border-color: #a01226; }

        /* ── invoice wrapper ── */
        #invoice-wrapper {
            max-width: 780px;
            margin: 64px auto 40px;
            background: #F0EBE1;
            padding: 40px 44px 36px;
        }

        /* ── top: INVOICE + company ── */
        .inv-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }
        .inv-heading {
            font-family: 'Arial Black', 'Arial', sans-serif;
            font-size: 72px;
            font-weight: 900;
            color: #C41930;
            letter-spacing: -.02em;
            line-height: 1;
            text-transform: uppercase;
        }
        .inv-company {
            text-align: right;
            padding-top: 6px;
        }
        .inv-company-name {
            font-family: 'Arial Black', 'Arial', sans-serif;
            font-size: 18px;
            font-weight: 900;
            color: #C41930;
            text-transform: uppercase;
            letter-spacing: .04em;
            line-height: 1.2;
        }
        .inv-company-sub {
            font-size: 10px;
            color: #555;
            margin-top: 4px;
        }

        /* ── divider ── */
        .inv-rule { border: none; border-top: 1.5px solid #1a1a1a; margin: 12px 0; }
        .inv-rule-thin { border: none; border-top: .5px solid #b0a898; margin: 10px 0; }

        /* ── meta row: invoice# / address ── */
        .inv-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }
        .inv-meta-left { font-size: 10px; }
        .inv-meta-left .meta-label {
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #666;
            font-size: 9px;
        }
        .inv-meta-left .meta-value { font-weight: 700; font-size: 11px; }
        .inv-meta-left .meta-row { margin-bottom: 6px; }
        .inv-addr-right {
            text-align: right;
            font-size: 10px;
            color: #444;
            line-height: 1.6;
            max-width: 220px;
        }

        /* ── bill-to / payment row ── */
        .inv-bill-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin: 18px 0 14px;
        }
        .inv-bill-col { width: 48%; }
        .inv-col-label {
            text-transform: uppercase;
            letter-spacing: .1em;
            font-size: 9px;
            color: #666;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .inv-bill-name { font-size: 13px; font-weight: 700; text-transform: uppercase; color: #1a1a1a; }
        .inv-bill-detail { font-size: 10px; color: #444; line-height: 1.7; margin-top: 2px; }

        /* ── items table ── */
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .inv-table th {
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .1em;
            font-size: 9px;
            color: #555;
            padding: 7px 8px;
            border-top: 1.5px solid #b0a898;
            border-bottom: 1.5px solid #b0a898;
            font-weight: 700;
        }
        .inv-table th:not(:first-child) { text-align: right; }
        .inv-table td {
            padding: 8px 8px;
            border-bottom: .5px solid #c8c0b4;
            font-size: 10.5px;
            vertical-align: top;
        }
        .inv-table td:not(:first-child) { text-align: right; }
        .inv-table .td-name { font-weight: 600; }
        .inv-table .td-sub { font-size: 9px; color: #777; margin-top: 2px; }
        .inv-table .tr-empty td { padding: 6px 8px; border-bottom: .5px solid #c8c0b4; }

        /* ── summary rows ── */
        .inv-table .tr-summary td {
            border-bottom: none;
            padding: 4px 8px;
            font-size: 10.5px;
        }
        .inv-table .tr-summary td:first-child { color: #555; }
        .inv-table .tr-summary td:last-child  { font-weight: 700; }
        .inv-table .tr-discount td:last-child  { color: #2e7d32; }
        .inv-table .tr-grand td {
            padding: 8px 8px 2px;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .06em;
            border-top: 1.5px solid #1a1a1a;
            border-bottom: none;
        }

        /* ── footer ── */
        .inv-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 28px;
            padding-top: 16px;
            border-top: .5px solid #b0a898;
        }
        .inv-footer-left { width: 55%; font-size: 9px; color: #555; line-height: 1.6; }
        .inv-footer-left strong { font-size: 10px; color: #1a1a1a; display: block; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .08em; }
        .inv-footer-right { width: 40%; text-align: right; font-size: 9px; color: #555; line-height: 1.7; }
        .inv-footer-right strong { font-size: 10px; color: #1a1a1a; display: block; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .08em; }

        /* ── print ── */
        @media print {
            body { background: #F0EBE1; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .action-bar { display: none !important; }
            #invoice-wrapper { margin: 0; padding: 20px; }
        }
        @media (max-width: 640px) {
            #invoice-wrapper { padding: 20px 16px; margin-top: 58px; }
            .inv-heading { font-size: 48px; }
            .inv-bill-row { flex-direction: column; gap: 16px; }
            .inv-bill-col { width: 100%; }
        }
    </style>
</head>
<body>

    <!-- Action bar -->
    <div class="action-bar no-print">
        <button class="btn-print" onclick="window.print()">🖨 Print</button>
        <button class="btn-dl" id="download-btn" onclick="downloadInvoicePDF()">⬇ Download PDF</button>
    </div>

    <!-- Invoice body -->
    <div id="invoice-wrapper">

        <!-- Top: big INVOICE + company name -->
        <div class="inv-top">
            <div class="inv-heading">Invoice</div>
            <div class="inv-company">
                <div class="inv-company-name"><?= htmlspecialchars(strtoupper($siteName)) ?></div>
                <div class="inv-company-sub">
                    www.bluefifth.in<br>
                    <?= htmlspecialchars($contactEmail) ?><br>
                    <?= htmlspecialchars($contactPhone) ?>
                </div>
            </div>
        </div>

        <hr class="inv-rule">

        <!-- Meta: invoice number / date  ||  company address -->
        <div class="inv-meta-row">
            <div class="inv-meta-left">
                <div class="meta-row">
                    <div class="meta-label">Invoice Number</div>
                    <div class="meta-value"><?= htmlspecialchars($order['order_number']) ?></div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">Date</div>
                    <div class="meta-value"><?= date('F j, Y', strtotime($order['created_at'])) ?></div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">Payment</div>
                    <div class="meta-value" style="text-transform:uppercase;"><?= htmlspecialchars($order['payment_status']) ?></div>
                </div>
            </div>
            <div class="inv-addr-right">
                CIN: U13999TZ2021PTC037885<br>
                GSTIN: 33AAJCT0905H2ZK<br>
                <?= htmlspecialchars($siteDescription) ?>
            </div>
        </div>

        <hr class="inv-rule-thin">

        <!-- Bill To  ||  Payment Method -->
        <div class="inv-bill-row">
            <div class="inv-bill-col">
                <div class="inv-col-label">Bill To</div>
                <div class="inv-bill-name"><?= htmlspecialchars($order['customer_name'] ?? 'Guest Customer') ?></div>
                <div class="inv-bill-detail">
                    <?= htmlspecialchars($addressString) ?><br>
                    <?= htmlspecialchars($order['customer_email'] ?? '') ?><br>
                    <?= htmlspecialchars($customerPhone) ?>
                </div>
            </div>
            <div class="inv-bill-col" style="text-align:right;">
                <div class="inv-col-label" style="text-align:right;">Payment Method</div>
                <div class="inv-bill-name" style="font-size:12px;">
                    <?php
                        $payMethod = 'Online Payment';
                        if (!empty($order['payment_method'])) {
                            $payMethod = ucwords(str_replace('_', ' ', $order['payment_method']));
                        } elseif (!empty($order['razorpay_payment_id'])) {
                            $payMethod = 'Razorpay';
                        } elseif (strtolower($order['payment_status'] ?? '') === 'cod') {
                            $payMethod = 'Cash on Delivery';
                        }
                        echo htmlspecialchars($payMethod);
                    ?>
                </div>
                <div class="inv-bill-detail" style="text-align:right;">
                    <?php if (!empty($order['razorpay_payment_id'])): ?>
                        Txn: <?= htmlspecialchars($order['razorpay_payment_id']) ?><br>
                    <?php endif; ?>
                    <?= date('F j, Y', strtotime($order['created_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Items table -->
        <?php
        $taxRate2       = (float) getSetting('tax_rate', 18.0);
        $calculatedTax  = (float) ($order['tax_amount'] ?? 0);
        ?>
        <table class="inv-table">
            <thead>
                <tr>
                    <th style="width:52%;">Description</th>
                    <th style="width:10%;">Qty</th>
                    <th style="width:19%;">Price</th>
                    <th style="width:19%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $item): ?>
                <tr>
                    <td>
                        <div class="td-name"><?= htmlspecialchars($item['product_name']) ?></div>
                        <div class="td-sub">
                            <?= !empty($item['size'])     ? 'Size: ' . htmlspecialchars($item['size'])     : '' ?>
                            <?= !empty($item['hsn_code']) ? ' | HSN: ' . htmlspecialchars($item['hsn_code']) : '' ?>
                        </div>
                    </td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td><?= $currencySymbol ?><?= number_format($item['product_price'], 2) ?></td>
                    <td><?= $currencySymbol ?><?= number_format($item['total_price'],   2) ?></td>
                </tr>
                <?php endforeach; ?>

                <!-- Visual spacing rows -->
                <tr class="tr-empty"><td colspan="4" style="height:14px;"></td></tr>

                <!-- Summary rows -->
                <tr class="tr-summary">
                    <td colspan="3">Subtotal</td>
                    <td><?= $currencySymbol ?><?= number_format($order['total_amount'], 2) ?></td>
                </tr>

                <?php if (isset($order['coupon_code']) && !empty($order['coupon_code'])): ?>
                <tr class="tr-summary tr-discount">
                    <td colspan="3">Coupon (<?= htmlspecialchars($order['coupon_code']) ?> <?= $order['coupon_discount_percentage'] ?>%)</td>
                    <td>-<?= $currencySymbol ?><?= number_format($order['coupon_discount_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($calculatedTax > 0): ?>
                <tr class="tr-summary">
                    <td colspan="3">Tax (<?= $taxRate2 ?>% GST)</td>
                    <td><?= $currencySymbol ?><?= number_format($calculatedTax, 2) ?></td>
                </tr>
                <?php endif; ?>

                <?php if (isset($order['shipping_amount']) && $order['shipping_amount'] > 0): ?>
                <tr class="tr-summary">
                    <td colspan="3">Shipping</td>
                    <td><?= $currencySymbol ?><?= number_format($order['shipping_amount'], 2) ?></td>
                </tr>
                <?php else: ?>
                <tr class="tr-summary">
                    <td colspan="3">Shipping</td>
                    <td style="color:#2e7d32;">Free</td>
                </tr>
                <?php endif; ?>

                <?php if (!empty($order['wallet_points_used']) && $order['wallet_points_used'] > 0): ?>
                <tr class="tr-summary tr-discount">
                    <td colspan="3">Wallet Discount</td>
                    <td>-<?= $currencySymbol ?><?= number_format($order['wallet_points_used'], 2) ?></td>
                </tr>
                <?php endif; ?>

                <tr class="tr-grand">
                    <td colspan="3">Grand Total</td>
                    <td><?= $currencySymbol ?><?= number_format($order['final_amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="inv-footer">
            <div class="inv-footer-left">
                <strong>Terms &amp; Conditions</strong>
                All sales are final. Refunds are subject to our return policy. This is a
                computer-generated invoice and does not require a physical signature.
                For disputes, please contact us within 7 days of delivery.
                <?php if (!empty($order['referral_code'])): ?>
                  <br>Referral code used: <strong><?= htmlspecialchars($order['referral_code']) ?></strong>
                <?php endif; ?>
            </div>
            <div class="inv-footer-right">
                <strong>For any questions</strong>
                <?= htmlspecialchars($contactEmail) ?><br>
                <?= htmlspecialchars($contactPhone) ?><br>
                www.bluefifth.in<br><br>
                <?= htmlspecialchars($siteName) ?><br>
                © <?= date('Y') ?> All rights reserved.
            </div>
        </div>

    </div><!-- /#invoice-wrapper -->

    <script>
        async function downloadInvoicePDF() {
            const btn = document.getElementById('download-btn');
            btn.textContent = '⏳ Generating…';
            btn.disabled = true;

            // Hide action bar so it doesn't appear in the PDF
            const bar = document.querySelector('.action-bar');
            bar.style.visibility = 'hidden';

            try {
                const el = document.getElementById('invoice-wrapper');
                const canvas = await html2canvas(el, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#F0EBE1',
                    logging: false
                });

                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
                const pdfW  = pdf.internal.pageSize.getWidth();
                const pdfH  = pdf.internal.pageSize.getHeight();
                const ratio = canvas.height / canvas.width;
                const imgH  = pdfW * ratio;

                const imgData = canvas.toDataURL('image/jpeg', 0.93);

                if (imgH <= pdfH) {
                    pdf.addImage(imgData, 'JPEG', 0, 0, pdfW, imgH);
                } else {
                    // Multi-page support
                    let posY = 0;
                    while (posY < imgH) {
                        if (posY > 0) pdf.addPage();
                        pdf.addImage(imgData, 'JPEG', 0, -posY, pdfW, imgH);
                        posY += pdfH;
                    }
                }

                pdf.save('Invoice-<?= htmlspecialchars($order['order_number']) ?>.pdf');
            } catch (err) {
                console.error(err);
                alert('Could not generate PDF. Please use the Print button and choose "Save as PDF".');
            } finally {
                bar.style.visibility = '';
                btn.textContent = '⬇ Download PDF';
                btn.disabled = false;
            }
        }

        // Auto-print when ?auto_print=1
        if (new URLSearchParams(location.search).get('auto_print') === '1') {
            setTimeout(() => window.print(), 800);
        }
    </script>
</body>
</html>