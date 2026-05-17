<?php
session_start();
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'auth/session.php';

// â”€â”€ Parse JSON body into $_POST so AJAX calls work transparently â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (!empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
    $jsonRaw = file_get_contents('php://input');
    if ($jsonRaw) { $jd = json_decode($jsonRaw, true); if (is_array($jd)) foreach ($jd as $k=>$v) $_POST[$k]=$v; }
}

// â”€â”€ GET-based success page (redirect target after AJAX payment) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_GET['order_success'])) {
    $oid  = (int)($_GET['oid']   ?? 0);
    $onum = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['onum'] ?? ''));
    $oprice = (float)($_GET['oprice'] ?? 0);
    $ometh  = $_GET['omethod'] ?? 'razorpay';
    if ($oid) {
        try { $conn=getConnection(); $s=$conn->prepare("SELECT order_number,final_amount,payment_method FROM orders WHERE id=? LIMIT 1"); $s->execute([$oid]); $od=$s->fetch(PDO::FETCH_ASSOC); if($od){$onum=$od['order_number'];$oprice=(float)$od['final_amount'];$ometh=$od['payment_method'];} } catch(Exception $e){}
    }
    $sn  = getSetting('site_name','Bluefifth');
    $cs  = 'â‚¹';
    $fst = (float)getSetting('free_shipping_threshold',500);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
    <title>Order Confirmed â€“ <?=htmlspecialchars($sn)?></title>
    <style>
      body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#f0f4ff 0%,#fff5fb 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
      .ty{background:#fff;border-radius:24px;padding:2.8rem 2.2rem;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.12);max-width:500px;width:100%;animation:pop .5s cubic-bezier(.34,1.56,.64,1) forwards;transform:scale(.9);opacity:0}
      @keyframes pop{to{transform:scale(1);opacity:1}}
      .csv{width:88px;height:88px;margin:0 auto 1.4rem;display:block}
      .cbg{fill:#e8f5e9}.cring{fill:none;stroke:#28a745;stroke-width:5;stroke-linecap:round;stroke-dasharray:276;stroke-dashoffset:276;transform-origin:44px 44px;transform:rotate(-90deg);animation:rng .65s .15s forwards}
      .ctick{fill:none;stroke:#28a745;stroke-width:5.5;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:48;stroke-dashoffset:48;animation:tck .35s .75s forwards}
      @keyframes rng{to{stroke-dashoffset:0}}@keyframes tck{to{stroke-dashoffset:0}}
      h1{font-size:1.9rem;font-weight:700;color:#1a1a2e;margin-bottom:.3rem}
      .obox{background:#f0fff4;border:1.5px solid #c8e6c9;border-radius:14px;padding:1.3rem;margin:1.4rem 0}
      .onum{color:#004AAD;font-weight:600;font-size:.9rem}.oamt{font-size:1.9rem;font-weight:700;color:#1e7e34}
      .btns{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:1.6rem}
      .btn{padding:11px 18px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.87rem;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
      .bh{background:#004AAD;color:#fff}.bs{background:#f0f4ff;color:#004AAD;border:2px solid #004AAD}.bt{background:#28a745;color:#fff}.bi{background:#f8f9fa;color:#333;border:2px solid #ddd}
      ul.nl{list-style:none;padding:0;text-align:left;margin:.8rem 0 0}
      ul.nl li{padding:5px 0;color:#555;font-size:.87rem}ul.nl li::before{content:'âœ“ ';color:#28a745;font-weight:700}
    </style></head><body>
    <div class="ty">
      <svg class="csv" viewBox="0 0 88 88" xmlns="http://www.w3.org/2000/svg"><circle class="cbg" cx="44" cy="44" r="41"/><circle class="cring" cx="44" cy="44" r="41"/><polyline class="ctick" points="25,45 37,57 63,31"/></svg>
      <h1>Thank You!</h1><p style="color:#6c757d;margin-bottom:0">Your order is confirmed &amp; being prepared.</p>
      <div class="obox">
        <div class="onum">Order #<?=htmlspecialchars($onum)?></div>
        <div class="oamt"><?=$cs?><?=number_format($oprice,2)?></div>
        <?php if($ometh==='cod'):?><div style="color:#6c757d;font-size:.82rem;margin-top:.3rem">Pay on delivery</div><?php endif;?>
      </div>
      <div class="btns">
        <a href="index.php" class="btn bh"><i class="fas fa-home"></i> Home</a>
        <a href="shop/category.php" class="btn bs">Shop More</a>
        <a href="account/orders.php" class="btn bt"><i class="fas fa-truck"></i> Track</a>
        <a href="invoice.php?order_id=<?=(int)$oid?>" target="_blank" class="btn bi">Invoice</a>
      </div>
      <ul class="nl"><li>Confirmation email sent</li><li>Order prepared within 24h</li><li>Delivery in 3â€“7 business days</li></ul>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <script>
      var C=['#004AAD','#28a745','#FFD700','#ff6b6b','#4ecdc4','#fd79a8'];
      setTimeout(function(){confetti({particleCount:80,angle:60,spread:55,origin:{x:0,y:.75},colors:C,zIndex:9999});confetti({particleCount:80,angle:120,spread:55,origin:{x:1,y:.75},colors:C,zIndex:9999});confetti({particleCount:60,spread:80,origin:{x:.5,y:.65},startVelocity:40,colors:C,zIndex:9999});},600);
    </script>
    </body></html><?php exit;
}

// Clean up stale coupon sessions if needed
if (isset($_SESSION['applied_coupon']) && (!isset($_POST['apply_coupon']) && !isset($_POST['remove_coupon']))) {
    // Verify coupon is still valid
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
        $stmt->execute([$_SESSION['applied_coupon']['code']]);
        if ($stmt->rowCount() == 0) {
            // Coupon no longer valid, clear it
            unset($_SESSION['applied_coupon']);
        }
    } catch (Exception $e) {
        error_log("COUPON VALIDATION ERROR: " . $e->getMessage());
    }
}

// ========================================
// DYNAMIC SETTINGS LOADING
// ========================================
$razorpayKeyId = getSetting('razorpay_key_id', 'YOUR_RAZORPAY_KEY_ID_HERE');
$razorpayKeySecret = getSetting('razorpay_key_secret', 'YOUR_RAZORPAY_KEY_SECRET_HERE');
$showRazorpayCheckout = false;
$razorpayOrderData    = null;
$razorpayCustomerId   = $_SESSION['razorpay_customer_id'] ?? null;
$taxRate = (float) getSetting('tax_rate', 18.0);
$shippingCharge = (float) getSetting('shipping_charge', 50.0);
$freeShippingThreshold = (float) getSetting('free_shipping_threshold', 500.0);
$minOrderAmount = (float) getSetting('min_order_amount', 100.0);
$siteName = getSetting('site_name', 'VELONA');
$currency = getSetting('currency', 'INR');
$currencySymbol = getSetting('currency_symbol', 'â‚¹');
$firstMonthRate = (float) getSetting('first_month_rate', 10.0);
$otherMonthsRate = (float) getSetting('other_months_rate', 5.0);
$minPointsToClaim = (int) getSetting('min_points_to_claim', 100);
$enableReferrals = (bool) getSetting('enable_referrals', true);
$emailNotifications = (bool) getSetting('email_notifications', true);
$smtpHost = getSetting('smtp_host', 'smtp.gmail.com');
$smtpPort = (int) getSetting('smtp_port', 587);
$fromEmail = getSetting('from_email', 'info@bluefifth.in');
$fromName = getSetting('from_name', 'bluefifth Team');

// User authentication
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;
$currentUser = $isLoggedIn ? getUserById($userId) : null;

// ========================================
// CART DATA PROCESSING - FIXED ORDER
// ========================================
if (!$isLoggedIn) {
    $cartItems = getSessionCartItems();
    $regularCartSummary = getSessionCartSummary();
    $balance = ['points' => 0, 'pending_points' => 0];
    $totalWalletPoints = 0;
    $userInfo = null;
} else {
    $cartItems = getCartItems($userId);
    $regularCartSummary = getCartSummary($userId);
    $balance = getWalletBalance($userId);
    $totalWalletPoints = ($balance['points'] ?? 0) + ($balance['pending_points'] ?? 0);
    $userInfo = getUserById($userId);
}

// Load saved default delivery address for auto-fill
$savedDefaultAddress = null;
if ($isLoggedIn && $userId) {
    try {
        $addrConn = getConnection();
        $addrStmt = $addrConn->prepare("SELECT * FROM customer_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1");
        $addrStmt->execute([$userId]);
        $savedDefaultAddress = $addrStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) { /* non-fatal */ }
}

// Form auto-fill: prefer saved address > users table
$fillName  = ($savedDefaultAddress['full_name']    ?? '') ?: ($userInfo['name']    ?? '');
$fillEmail = ($savedDefaultAddress['email']        ?? '') ?: ($userInfo['email']   ?? '');
$fillAddr1 = ($savedDefaultAddress['address_line'] ?? '') ?: ($userInfo['address'] ?? '');
$fillApt   = $savedDefaultAddress['apartment'] ?? '';
$fillCity  = ($savedDefaultAddress['city']    ?? '') ?: ($userInfo['city']    ?? '');
$fillState = ($savedDefaultAddress['state']   ?? '') ?: ($userInfo['state']   ?? 'TN');
$fillPin   = ($savedDefaultAddress['pincode'] ?? '') ?: ($userInfo['pincode'] ?? '');
$fillPhone = $userInfo['phone'] ?? '';
$fillFirst = $fillName ? explode(' ', trim($fillName))[0] : '';
$fillLast  = $fillName ? implode(' ', array_slice(explode(' ', trim($fillName)), 1)) : '';

$totalAmount = $regularCartSummary['total_amount'];
$cartSummary  = $regularCartSummary;

// ========================================
// COUPON HANDLING - AFTER TOTAL AMOUNT IS DEFINED
// ========================================
$appliedCoupon = null;
$couponDiscount = 0;
$couponCode = '';

// Handle coupon application via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    header('Content-Type: application/json');
    
    $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $response = ['success' => false, 'message' => ''];
    
    if (!empty($couponCode)) {
        try {
            $conn = getConnection();
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ?");
            $stmt->execute([$couponCode]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coupon) {
                $response['message'] = 'Invalid coupon code';
            } elseif (!$coupon['is_active']) {
                $response['message'] = 'This coupon is no longer active';
            } elseif ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
                $response['message'] = 'This coupon has expired';
            } elseif ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
                $response['message'] = 'This coupon has reached its usage limit';
            } else {
                $_SESSION['applied_coupon'] = $coupon;
                $couponDiscount = ($totalAmount * $coupon['discount_percentage']) / 100;
                $response['success'] = true;
                $response['coupon'] = $coupon;
                $response['discount_amount'] = $couponDiscount;
                $response['message'] = 'Coupon applied successfully!';
            }
        } catch (Exception $e) {
            error_log("Coupon error: " . $e->getMessage());
            $response['message'] = 'Error validating coupon';
        }
    } else {
        $response['message'] = 'Please enter a coupon code';
    }
    
    echo json_encode($response);
    exit;
}

// Handle coupon removal via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_coupon'])) {
    header('Content-Type: application/json');
    unset($_SESSION['applied_coupon']);
    echo json_encode(['success' => true]);
    exit;
}

/// Restore coupon from session with debugging
if (isset($_SESSION['applied_coupon'])) {
    $appliedCoupon = $_SESSION['applied_coupon'];
    $couponCode = $appliedCoupon['code'];
    $couponDiscount = ($totalAmount * $appliedCoupon['discount_percentage']) / 100;
} else {
    $appliedCoupon = null;
    $couponDiscount = 0;
    $couponCode = '';
}

// ========================================
// FINAL CALCULATIONS - SAFE DIVISION
// ========================================
$totalAmountAfterCoupon = $totalAmount;
if ($couponDiscount > 0) {
    $totalAmountAfterCoupon = $totalAmount - $couponDiscount;
}

// Store coupon data in session for invoice and order processing
if ($appliedCoupon) {
    $_SESSION['checkout_coupon_data'] = [
        'code' => $appliedCoupon['code'],
        'discount_percentage' => $appliedCoupon['discount_percentage'],
        'discount_amount' => $couponDiscount,
        'original_total' => $totalAmount,
        'discounted_total' => $totalAmountAfterCoupon
    ];
} else {
    // Clear coupon data if no coupon applied
    unset($_SESSION['checkout_coupon_data']);
}

$shippingCost = 0;
if ($totalAmountAfterCoupon < $freeShippingThreshold) {
    $shippingCost = $shippingCharge;
}

// Safe tax calculation - prevent division by zero
$grossTotal = $totalAmountAfterCoupon + $shippingCost;
if ($taxRate > -100) {
    $taxAmount = ($grossTotal * $taxRate) / (100 + $taxRate);
} else {
    $taxAmount = 0;
    error_log("Invalid tax rate: $taxRate");
}

$netSubtotal = $totalAmountAfterCoupon - $taxAmount;
$subtotal = $netSubtotal;
$finalTotalBeforePoints = $totalAmountAfterCoupon;

// ========================================
// VALIDATION CHECKS
// ========================================
if (empty($cartItems)) {
    header('Location: shop/cart.php');
    exit;
}

if ($totalAmount < $minOrderAmount) {
    header('Location: shop/cart.php?error=minimum_order&required=' . $minOrderAmount);
    exit;
}

// Validate critical variables
if (!isset($totalAmount) || $totalAmount <= 0) {
    error_log("Invalid total amount: $totalAmount");
    $error = 'Cart calculation error. Please refresh and try again.';
}

// ========================================
// AJAX CHECKOUT API â€” all actions exit before HTML render
// ========================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    // Shared helper: send order confirmation email
    function _doSendOrderEmail() {
        global $orderId,$orderNumber,$cartItems,$shippingCost,$finalPrice;
        global $taxAmount,$totalAmount,$pointsToUse,$appliedCoupon,$couponDiscount;
        global $currencySymbol,$taxRate,$freeShippingThreshold;
        try {
            $sa = $_SESSION['checkout_data'] ?? [];
            if (empty($sa) && $orderId) {
                try { $c=getConnection();$s=$c->prepare("SELECT shipping_address FROM orders WHERE id=? LIMIT 1");$s->execute([$orderId]);$r=$s->fetchColumn();if($r)$sa=json_decode($r,true)??[]; } catch(Exception $e){}
            }
            require_once 'includes/sendinblue-mailer.php';
            $key=getSetting('sendinblue_api_key');
            if(!$key||$key==='YOUR_API_KEY_HERE') return;
            $mailer=new SendinblueMailer($key,getSetting('sendinblue_from_email','info@bluefifth.in'),getSetting('sendinblue_from_name','Bluefifth Team'));
            $cName=trim(($sa['first_name']??'Customer').' '.($sa['last_name']??''));
            $cEmail=$sa['email']??'';
            if(!$cEmail) return;
            $cs=$currencySymbol??'â‚¹';
            $rows='';
            foreach((array)$cartItems as $item){
                $rows.="<tr><td style='padding:10px 14px;border-bottom:1px solid #eee'><strong>".htmlspecialchars($item['product_name']??$item['name']??'')."</strong>".(!empty($item['size'])?"<br><small style='color:#888'>Size:{$item['size']}</small>":"")."</td><td style='padding:10px 14px;text-align:center;border-bottom:1px solid #eee'>".(int)$item['quantity']."</td><td style='padding:10px 14px;text-align:right;border-bottom:1px solid #eee;font-weight:600'>{$cs}".number_format((float)($item['total_price']??0),2)."</td></tr>";
            }
            $sline=$shippingCost>0?"<tr><td colspan='2' style='padding:6px 14px;color:#666'>Shipping</td><td style='padding:6px 14px;text-align:right'>{$cs}".number_format($shippingCost,2)."</td></tr>":"<tr><td colspan='2' style='padding:6px 14px;color:#666'>Shipping</td><td style='padding:6px 14px;text-align:right;color:#28a745'>FREE</td></tr>";
            $cline=($appliedCoupon&&$couponDiscount>0)?"<tr><td colspan='2' style='padding:6px 14px;color:#dc3545'>Coupon ({$appliedCoupon['code']})</td><td style='padding:6px 14px;text-align:right;color:#dc3545'>-{$cs}".number_format($couponDiscount,2)."</td></tr>":'';
            $pline=$pointsToUse>0?"<tr><td colspan='2' style='padding:6px 14px;color:#666'>Wallet Points</td><td style='padding:6px 14px;text-align:right;color:#28a745'>-{$cs}".number_format($pointsToUse,2)."</td></tr>":'';
            $addr=implode(', ',array_filter([$sa['address']??'',$sa['apartment']??'',$sa['city']??'',$sa['state']??'',$sa['pincode']??'']));
            $sn=getSetting('site_name','Bluefifth');
            $html="<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0'><div style='max-width:580px;margin:0 auto;background:#fff'><div style='background:#004AAD;padding:28px 20px;text-align:center'><h1 style='color:#fff;margin:0;font-size:22px'>Order Confirmed!</h1><p style='color:rgba(255,255,255,.85);margin:6px 0 0;font-size:14px'>Hi {$cName}, your order is on its way.</p></div><div style='padding:22px'><div style='text-align:center;background:#f0fff4;border:1.5px solid #c8e6c9;border-radius:10px;padding:18px;margin-bottom:18px'><div style='color:#666;font-size:12px'>Order Number</div><div style='color:#004AAD;font-size:20px;font-weight:700'>{$orderNumber}</div><div style='color:#1e7e34;font-size:18px;font-weight:700'>{$cs}".number_format($finalPrice,2)."</div></div><table style='width:100%;border-collapse:collapse;margin-bottom:18px'><thead><tr style='background:#f8f9fa'><th style='padding:10px 14px;text-align:left'>Product</th><th style='padding:10px 14px;text-align:center'>Qty</th><th style='padding:10px 14px;text-align:right'>Total</th></tr></thead><tbody>{$rows}</tbody></table><table style='width:100%;border-collapse:collapse;margin-bottom:18px'><tbody>{$sline}{$cline}{$pline}<tr style='font-weight:700;border-top:2px solid #eee'><td colspan='2' style='padding:10px 14px;color:#333'>Total Paid</td><td style='padding:10px 14px;text-align:right;color:#1e7e34'>{$cs}".number_format($finalPrice,2)."</td></tr></tbody></table><div style='background:#f8f9fa;border-radius:8px;padding:14px;margin-bottom:16px'><strong style='font-size:14px'>Delivery Address</strong><div style='color:#555;margin-top:6px;font-size:13px;line-height:1.7'>".htmlspecialchars($addr)."<br>ðŸ“ž ".htmlspecialchars($sa['phone']??'')."</div></div><div style='background:#004AAD;color:#fff;border-radius:8px;padding:14px;font-size:13px;line-height:1.8'>Order prepared within 24 hours Â· Delivery in 3â€“7 business days</div></div><div style='text-align:center;padding:16px;background:#f8f9fa;font-size:12px;color:#888'>Â© ".date('Y')." {$sn}. Thank you!</div></div></body></html>";
            $mailer->sendEmail($cEmail,$cName,"Order Confirmed - {$orderNumber} | {$sn}",$html);
        } catch(Exception $e){ error_log("Email error: ".$e->getMessage()); }
    }

    $ajaxAction = $_POST['ajax_action'] ?? '';

    // â”€â”€ Create Razorpay order â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($ajaxAction === 'create_razorpay_order') {
        $phone = trim($_POST['phone'] ?? '');
        $name  = trim($_POST['name']  ?? $fillName ?? '');
        $email = trim($_POST['email'] ?? $fillEmail ?? '');
        $nameParts = explode(' ', $name, 2);
        createRazorpayOrder([
            'first_name'    => $nameParts[0] ?? '',
            'last_name'     => $nameParts[1] ?? '',
            'email'         => $email,
            'phone'         => $phone,
            'points_to_use' => (int)($_POST['points_to_use'] ?? 0),
            'referral_code' => trim($_POST['referral_code'] ?? ''),
        ]);
        if ($showRazorpayCheckout && $razorpayOrderData) {
            echo json_encode([
                'success'     => true,
                'order_id'    => $razorpayOrderData['id'],
                'amount'      => $razorpayOrderData['amount'],
                'currency'    => 'INR',
                'key_id'      => $razorpayKeyId,
                'customer_id' => $_SESSION['razorpay_customer_id'] ?? null,
                'prefill'     => ['contact' => $phone ?: $fillPhone, 'name' => $name ?: $fillName, 'email' => $email ?: $fillEmail],
                'site_name'   => $siteName,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $error ?? 'Could not create payment order. Please try again.']);
        }
        exit;
    }

    // â”€â”€ Verify Razorpay payment + create order â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($ajaxAction === 'process_payment') {
        $pid = trim($_POST['razorpay_payment_id'] ?? '');
        $oid = trim($_POST['razorpay_order_id']   ?? '');
        $sig = trim($_POST['razorpay_signature']  ?? '');
        if (!$pid || !$oid || !$sig) { echo json_encode(['success'=>false,'message'=>'Incomplete payment data.']); exit; }
        if (!hash_equals(hash_hmac('sha256', $oid.'|'.$pid, $razorpayKeySecret), $sig)) {
            echo json_encode(['success'=>false,'message'=>'Payment signature verification failed.']); exit;
        }
        $result = processVerifiedOrder(['razorpay_payment_id'=>$pid,'razorpay_order_id'=>$oid,'razorpay_signature'=>$sig]);
        if ($result && $orderProcessed) {
            _doSendOrderEmail();
            echo json_encode(['success'=>true,'order_number'=>$orderNumber,'order_id'=>$orderId,'final_price'=>$finalPrice]);
        } else {
            echo json_encode(['success'=>false,'message'=>$error??'Order could not be processed.']);
        }
        exit;
    }

    // â”€â”€ COD order â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($ajaxAction === 'process_cod') {
        $fd = [
            'first_name'    => trim($_POST['first_name']   ?? ''),
            'last_name'     => trim($_POST['last_name']    ?? ''),
            'email'         => trim($_POST['email']        ?? ''),
            'phone'         => trim($_POST['phone']        ?? ''),
            'address'       => trim($_POST['address']      ?? ''),
            'apartment'     => trim($_POST['apartment']    ?? ''),
            'city'          => trim($_POST['city']         ?? ''),
            'state'         => trim($_POST['state']        ?? ''),
            'pincode'       => trim($_POST['pincode']      ?? ''),
            'payment_method'=> 'cod',
            'points_to_use' => (int)($_POST['points_to_use'] ?? 0),
            'referral_code' => trim($_POST['referral_code'] ?? ''),
        ];
        $result = processCODOrder($fd);
        if ($result && $orderProcessed) {
            _doSendOrderEmail();
            echo json_encode(['success'=>true,'order_number'=>$orderNumber,'order_id'=>$orderId,'final_price'=>$finalPrice]);
        } else {
            echo json_encode(['success'=>false,'message'=>$error??'Order could not be processed.']);
        }
        exit;
    }

    // â”€â”€ Apply coupon â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($ajaxAction === 'apply_coupon') {
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        if (!$code) { echo json_encode(['success'=>false,'message'=>'Enter a coupon code.']); exit; }
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT * FROM coupons WHERE code=? AND is_active=1 AND (expiry_date IS NULL OR expiry_date>=CURDATE())");
            $stmt->execute([$code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$coupon) { echo json_encode(['success'=>false,'message'=>'Invalid or expired coupon.']); exit; }
            if ((int)$coupon['usage_limit'] > 0) {
                $us = $conn->prepare("SELECT COUNT(*) FROM orders WHERE coupon_code=?");
                $us->execute([$code]);
                if ((int)$us->fetchColumn() >= (int)$coupon['usage_limit']) { echo json_encode(['success'=>false,'message'=>'Coupon usage limit reached.']); exit; }
            }
            $disc = round($totalAmount * (float)$coupon['discount_percentage'] / 100, 2);
            $_SESSION['applied_coupon'] = ['code'=>$coupon['code'],'discount_percentage'=>$coupon['discount_percentage'],'id'=>$coupon['id']];
            echo json_encode(['success'=>true,'message'=>"Coupon applied! {$coupon['discount_percentage']}% off.",'discount_amount'=>$disc,'discount_percent'=>$coupon['discount_percentage'],'coupon_code'=>$coupon['code']]);
        } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>'Could not apply coupon.']); }
        exit;
    }

    // â”€â”€ Remove coupon â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($ajaxAction === 'remove_coupon') {
        unset($_SESSION['applied_coupon']);
        echo json_encode(['success'=>true]);
        exit;
    }

    // â”€â”€ Get saved addresses â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($ajaxAction === 'get_addresses') {
        if (!$userId) { echo json_encode(['success'=>true,'addresses',[]]); exit; }
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT * FROM customer_addresses WHERE user_id=? ORDER BY is_default DESC,created_at DESC LIMIT 5");
            $stmt->execute([$userId]);
            echo json_encode(['success'=>true,'addresses'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch(Exception $e){ echo json_encode(['success'=>true,'addresses'=>[]]); }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']);
    exit;
}

// ========================================
// ORDER PROCESSING - MISSING SECTION ADDED
// ========================================
$orderProcessed = false;
$error = null;
$pointsUsed = 0;
$finalPrice = $finalTotalBeforePoints;
$orderId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle coupon operations first
    if (isset($_POST['apply_coupon']) && !isset($_POST['checkout_submit'])) {
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    if (isset($_POST['remove_coupon']) && !isset($_POST['checkout_submit'])) {
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Process payment if it's checkout submission
    if (isset($_POST['checkout_submit']) || isset($_POST['razorpay_payment_id'])) {
        $paymentMethod = $_POST['payment_method'] ?? 'razorpay';
        
        if ($paymentMethod === 'cod') {
            $orderProcessed = processCODOrder($_POST);
        } else {
            if (isset($_POST['razorpay_payment_id'])) {
                $razorpayPaymentId = $_POST['razorpay_payment_id'];
                $razorpayOrderId = $_POST['razorpay_order_id'];
                $razorpaySignature = $_POST['razorpay_signature'];
                
                // Verify payment signature
                $generated_signature = hash_hmac('sha256', $razorpayOrderId . "|" . $razorpayPaymentId, $razorpayKeySecret);
                
                if (hash_equals($generated_signature, $razorpaySignature)) {
                    $orderProcessed = processVerifiedOrder($_POST);
                } else {
                    $error = 'Payment verification failed. Please try again.';
                }
            } else {
                createRazorpayOrder($_POST);
            }
        }
    }
}

// ========================================
// FUNCTION DEFINITIONS - WITH PROPER GLOBAL DECLARATIONS
// ========================================
function processCODOrder($formData) {
    global $userId, $cartItems, $subtotal, $taxAmount, $shippingCost, $finalPrice, $netSubtotal;
    global $orderProcessed, $orderId, $orderNumber, $totalAmount;
    global $isLoggedIn, $finalTotalBeforePoints, $totalWalletPoints;
    global $appliedCoupon, $couponDiscount, $totalAmountAfterCoupon, $error;

    $pointsToUse = intval($formData['points_to_use'] ?? 0);
    $referralCodeFromForm = trim($formData['referral_code'] ?? '');
    
    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'state', 'pincode'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty(trim($formData[$field] ?? ''))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        $error = 'Please fill in all required fields: ' . implode(', ', $missingFields);
        return false;
    }
    
    // Validate points
    if ($pointsToUse > $totalWalletPoints) {
        $error = 'Not enough points available';
        return false;
    }
    
    // Calculate final price with proper fallbacks
    $baseAmount = isset($totalAmountAfterCoupon) && $totalAmountAfterCoupon > 0 ? $totalAmountAfterCoupon : $totalAmount;
    $discountAmount = min($pointsToUse, $baseAmount);
    $finalPrice = max(0, $baseAmount - $discountAmount);
    
    // Store data for processing
    $_SESSION['checkout_data'] = $formData;
    $_SESSION['points_to_use'] = $pointsToUse;
    $_SESSION['referral_code'] = $referralCodeFromForm;
    
    return processVerifiedOrder([
        'payment_method' => 'cod',
        'cod_order' => true
    ]);
}

/**
 * Create or fetch a Razorpay Customer for Magic Checkout recognition.
 * Returns the Razorpay customer_id string, or null on failure.
 */
function getRazorpayCustomerId(string $name, string $email, string $phone,
                                string $keyId, string $keySecret): ?string {
    if (empty($phone)) return null;

    // Razorpay expects 10-digit number; strip non-digits
    $contact = preg_replace('/\D/', '', $phone);
    if (strlen($contact) === 10) {
        $contact = '91' . $contact; // add country code
    }

    $payload = json_encode([
        'name'    => $name    ?: 'Customer',
        'email'   => $email   ?: '',
        'contact' => $contact,
        'fail_existing' => '0', // return existing customer if phone already registered
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/customers');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 || $code === 201) {
        $data = json_decode($resp, true);
        return $data['id'] ?? null;
    }
    return null;
}

function createRazorpayOrder($formData) {
    global $razorpayKeyId, $razorpayKeySecret, $finalPrice, $userId, $currencySymbol;
    global $totalWalletPoints, $totalAmountAfterCoupon, $totalAmount, $error;
    global $showRazorpayCheckout, $razorpayOrderData, $razorpayCustomerId;

    // Validate and calculate final price
    $pointsToUse = intval($formData['points_to_use'] ?? 0);
    $baseAmount = isset($totalAmountAfterCoupon) && $totalAmountAfterCoupon > 0 ? $totalAmountAfterCoupon : $totalAmount;
    $discountAmount = min($pointsToUse, $baseAmount);
    $finalPrice = max(0, $baseAmount - $discountAmount);

    if ($pointsToUse > $totalWalletPoints) {
        $error = 'Not enough points available';
        return;
    }

    // Store form data
    $_SESSION['checkout_data'] = $formData;
    $_SESSION['points_to_use'] = $pointsToUse;
    $_SESSION['referral_code'] = trim($formData['referral_code'] ?? '');

    // Guard: Razorpay keys must be configured in admin settings
    if (empty($razorpayKeyId) || $razorpayKeyId === 'YOUR_RAZORPAY_KEY_ID_HERE' ||
        empty($razorpayKeySecret) || $razorpayKeySecret === 'YOUR_RAZORPAY_KEY_SECRET_HERE') {
        $error = 'Payment gateway is not configured. Please use Cash on Delivery or contact support.';
        return;
    }

    // --- Razorpay Magic Checkout: create/fetch customer for saved-payment recognition ---
    $custName  = trim(($formData['first_name'] ?? '') . ' ' . ($formData['last_name'] ?? ''));
    $custEmail = trim($formData['email']  ?? '');
    $custPhone = trim($formData['phone']  ?? '');
    $rzpCustId = getRazorpayCustomerId($custName, $custEmail, $custPhone, $razorpayKeyId, $razorpayKeySecret);
    if ($rzpCustId) {
        $_SESSION['razorpay_customer_id'] = $rzpCustId;
    }
    $razorpayCustomerId = $rzpCustId;
    // ---------------------------------------------------------------------------------

    // Create Razorpay order â€” attach customer_id so Magic Checkout can surface saved cards/UPI
    $orderData = [
        'amount'          => round($finalPrice * 100),
        'currency'        => 'INR',
        'receipt'         => 'order_' . time(),
        'payment_capture' => 1,
    ];
    if ($rzpCustId) {
        $orderData['customer_id'] = $rzpCustId;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $razorpayKeyId . ':' . $razorpayKeySecret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200) {
        $razorpayOrder = json_decode($response, true);
        $_SESSION['razorpay_order_id'] = $razorpayOrder['id'];
        $showRazorpayCheckout = true;
        $razorpayOrderData    = $razorpayOrder;
    } else {
        $apiError = json_decode($response, true);
        $errorMsg = $apiError['error']['description'] ?? ($curlError ?: 'Unknown error');
        error_log("Razorpay order creation failed (HTTP $httpCode): $errorMsg");
        $error = 'Payment gateway error. Please use Cash on Delivery or try again later.';
    }
}

// [processVerifiedOrder function remains the same but with proper global declarations]
function processVerifiedOrder($paymentData) {
    global $userId, $conn, $cartItems, $subtotal, $taxAmount, $shippingCost, $finalPrice, $netSubtotal;
    global $orderProcessed, $orderId, $orderNumber, $totalAmount;
    global $appliedCoupon, $couponDiscount, $isLoggedIn, $finalTotalBeforePoints, $totalWalletPoints;
    global $totalAmountAfterCoupon, $error;

    // Get stored checkout data
    $formData = $_SESSION['checkout_data'] ?? [];
    $pointsToUse = $_SESSION['points_to_use'] ?? 0;
    $referralCodeFromForm = $_SESSION['referral_code'] ?? '';

    // Handle COD orders
    $isCODOrder = isset($paymentData['cod_order']) && $paymentData['cod_order'] === true;
    $paymentMethod = $isCODOrder ? 'cod' : 'razorpay';
    $paymentStatus = $isCODOrder ? 'pending' : 'paid';

    $finalCartTotal = $totalAmount;

    // Phone-first guest identity: find/create user by phone before the transaction.
    // This ensures every order is tied to a persistent customer profile keyed on phone.
    if (!$isLoggedIn || $userId === null) {
        $prePhone = trim($formData['phone'] ?? '');
        if (!empty($prePhone)) {
            $preUser = findOrCreateUserByPhone($prePhone);
            if ($preUser) {
                // Merge any session-cart items into the user's DB cart
                mergeGuestCartWithUserCart((int)$preUser['id']);
                $userId     = (int)$preUser['id'];
                $isLoggedIn = true;
                loginUser($userId);
            }
        }
    }

    // Get cart items
    global $cartItems, $taxRate, $shippingCost;
    $cartItems = $isLoggedIn ? getCartItems($userId) : getSessionCartItems();

    // Calculate amounts using tax-inclusive method
    $cartTotal  = $finalCartTotal;
    $grossTotal = $cartTotal + $shippingCost;
    $taxAmount  = ($grossTotal * $taxRate) / (100 + $taxRate);
    $netSubtotal = $cartTotal;

    // Use coupon-adjusted amount as base for points calculation
    $baseAmount = isset($totalAmountAfterCoupon) ? $totalAmountAfterCoupon : $totalAmount;
    $discountAmount = min($pointsToUse, $baseAmount);
    $finalPrice = max(0, $baseAmount - $discountAmount);
        
    $shippingAddress = [
        'first_name' => trim($formData['first_name'] ?? ''),
        'last_name' => trim($formData['last_name'] ?? ''),
        'email' => trim($formData['email'] ?? ''),
        'phone' => trim($formData['phone'] ?? ''),
        'address' => trim($formData['address'] ?? ''),
        'apartment' => trim($formData['apartment'] ?? ''),
        'city' => trim($formData['city'] ?? ''),
        'state' => trim($formData['state'] ?? ''),
        'country' => trim($formData['country'] ?? 'IN'),
        'pincode' => trim($formData['pincode'] ?? '')
    ];
    
    try {
        $conn = getConnection();
        
        // Test connection
        if (!$conn) {
            throw new Exception('Failed to get database connection');
        }
        
        // Set PDO attributes for better error handling
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);
        
        // Start transaction
        $conn->beginTransaction();
        
        // Update the user profile with the name/email from the checkout form
        // (phone-first identity was already resolved above, before the transaction)
        if ($userId) {
            try {
                $profileName  = trim(($formData['first_name'] ?? '') . ' ' . ($formData['last_name'] ?? ''));
                $profileEmail = trim($formData['email'] ?? '');
                $conn->prepare("
                    UPDATE users
                    SET name    = COALESCE(NULLIF(?, ''), name),
                        email   = COALESCE(NULLIF(?, ''), email),
                        address = COALESCE(NULLIF(?, ''), address),
                        city    = COALESCE(NULLIF(?, ''), city),
                        state   = COALESCE(NULLIF(?, ''), state),
                        pincode = COALESCE(NULLIF(?, ''), pincode)
                    WHERE id = ?
                ")->execute([
                    $profileName,
                    $profileEmail,
                    trim($formData['address'] ?? ''),
                    trim($formData['city']    ?? ''),
                    trim($formData['state']   ?? ''),
                    trim($formData['pincode'] ?? ''),
                    $userId,
                ]);
            } catch (Exception $e) {
                error_log('Profile update error (non-fatal): ' . $e->getMessage());
            }
        }
        
        $orderNumber = 'VLN' . time() . rand(100, 999);
        
        $orderSql = "
            INSERT INTO orders
            (order_number, user_id, total_amount, tax_amount, shipping_amount, wallet_points_used, final_amount,
             shipping_address, billing_address, referral_code, coupon_code, coupon_discount_percentage, coupon_discount_amount,
             payment_method, status, payment_status, razorpay_payment_id, razorpay_order_id,
             created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $orderParams = [
            $orderNumber,
            $userId,
            $finalCartTotal,
            $taxAmount,
            $shippingCost,
            $pointsToUse,
            $finalPrice,
            json_encode($shippingAddress),
            json_encode($shippingAddress),
            $referralCodeFromForm ?: null,
            $appliedCoupon['code'] ?? null,
            $appliedCoupon['discount_percentage'] ?? null,
            $couponDiscount ?? null,
            $paymentMethod,
            'pending',
            $paymentStatus,
            $isCODOrder ? null : $paymentData['razorpay_payment_id'],
            $isCODOrder ? null : $paymentData['razorpay_order_id'],
        ];

        $orderStmt = $conn->prepare($orderSql);
        $orderInsertResult = $orderStmt->execute($orderParams);

        if (!$orderInsertResult) {
            $errorInfo = $orderStmt->errorInfo();
            throw new Exception('Failed to insert order: ' . implode(', ', $errorInfo));
        }

        // Get the inserted order ID
        $orderDbId = $conn->lastInsertId();

        // Fallback: look up by order_number if lastInsertId returns 0
        if (!$orderDbId || $orderDbId <= 0) {
            $verifyStmt = $conn->prepare("SELECT id FROM orders WHERE order_number = ? ORDER BY created_at DESC LIMIT 1");
            $verifyStmt->execute([$orderNumber]);
            $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);

            if ($verifyResult && isset($verifyResult['id'])) {
                $orderDbId = (int)$verifyResult['id'];
            } else {
                error_log("Verification query failed - no order found with number: " . $orderNumber);
                
                // Try one more check - get the latest order for this user
                $latestStmt = $conn->prepare("SELECT id, order_number FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                $latestStmt->execute([$userId]);
                $latestResult = $latestStmt->fetch(PDO::FETCH_ASSOC);
                error_log("Latest order for user: " . json_encode($latestResult));
                
                throw new Exception('Failed to retrieve order ID after insertion. Order number: ' . $orderNumber);
            }
        }
        
        // Validate we have a valid order ID
        if (!is_numeric($orderDbId) || $orderDbId <= 0) {
            throw new Exception('Invalid order ID retrieved: ' . $orderDbId);
        }
        
        error_log("âœ… ORDER CREATION SUCCESS: Order ID = " . $orderDbId . ", Order Number = " . $orderNumber);
        
        unset($_SESSION['preserved_checkout_data']);

        
        // Verify order exists in database
        $doubleCheckStmt = $conn->prepare("SELECT id, order_number, user_id, final_amount FROM orders WHERE id = ?");
        $doubleCheckStmt->execute([$orderDbId]);
        $doubleCheckResult = $doubleCheckStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doubleCheckResult) {
            throw new Exception('Order verification failed - order not found after creation');
        }
        
        error_log("âœ… ORDER VERIFICATION: " . json_encode($doubleCheckResult));
        
        // Increment coupon usage after successful order
        if ($appliedCoupon && !empty($appliedCoupon['code'])) {
            try {
                $stmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?");
                $stmt->execute([$appliedCoupon['code']]);
                error_log("Coupon usage incremented for: " . $appliedCoupon['code']);
                
                // Store coupon data for invoice BEFORE clearing session
                $_SESSION['order_coupon_data'] = [
                    'code' => $appliedCoupon['code'],
                    'discount_percentage' => $appliedCoupon['discount_percentage'],
                    'discount_amount' => $couponDiscount ?? 0,
                    'original_total' => $totalAmount,
                    'discounted_total' => $totalAmountAfterCoupon ?? $totalAmount
                ];
                
                // Clear coupon from checkout session after successful order
                unset($_SESSION['applied_coupon']);
                unset($_SESSION['checkout_coupon_data']);
                
            } catch (Exception $e) {
                error_log("Failed to increment coupon usage: " . $e->getMessage());
            }
        }
        
        // Create order items
        foreach ($cartItems as $index => $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                error_log("âŒ CART ITEM ERROR: Invalid item at index $index: " . json_encode($item));
                continue;
            }
            
            $itemSql = "
                INSERT INTO order_items 
                (order_id, product_id, product_name, product_price, quantity, size, total_price, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $itemStmt = $conn->prepare($itemSql);
            $itemParams = [
                $orderDbId,
                $item['product_id'],
                $item['product_name'] ?? 'Unknown Product',
                $item['product_price'] ?? 0,
                $item['quantity'],
                $item['size'] ?? null,
                $item['total_price'] ?? ($item['product_price'] * $item['quantity'])
            ];
            
            $itemResult = $itemStmt->execute($itemParams);
            
            if (!$itemResult) {
                error_log("âŒ ORDER ITEM ERROR: Failed to insert item: " . json_encode($item));
                error_log("Item Error Info: " . json_encode($itemStmt->errorInfo()));
                throw new Exception('Failed to insert order item');
            }
            
            error_log("âœ… ORDER ITEM: Inserted item for product_id: " . $item['product_id']);
        }
        
        // Process wallet points (if any)
        if ($pointsToUse > 0) {
            error_log("Processing wallet points: " . $pointsToUse);
            
            $balance = getWalletBalance($userId);
            $walletId = ensureUserWallet($userId);
            
            if (!$walletId) {
                throw new Exception('Failed to get or create wallet for user: ' . $userId);
            }
            
            $pointsFromRegular = min($pointsToUse, $balance['points']);
            $pointsFromPending = $pointsToUse - $pointsFromRegular;
            
            if ($pointsFromRegular > 0) {
                $updateWalletStmt = $conn->prepare("UPDATE wallet SET points = points - ? WHERE user_id = ?");
                $walletResult = $updateWalletStmt->execute([$pointsFromRegular, $userId]);
                
                if (!$walletResult) {
                    throw new Exception('Failed to update wallet points');
                }
                error_log("âœ… WALLET: Updated regular points: " . $pointsFromRegular);
            }
            
            if ($pointsFromPending > 0) {
                $updatePendingStmt = $conn->prepare("UPDATE wallet SET pending_points = pending_points - ? WHERE user_id = ?");
                $pendingResult = $updatePendingStmt->execute([$pointsFromPending, $userId]);
                
                if (!$pendingResult) {
                    throw new Exception('Failed to update pending wallet points');
                }
                error_log("âœ… WALLET: Updated pending points: " . $pointsFromPending);
            }
            
            // Record transaction
            $transactionStmt = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, points, transaction_type, description, created_at) VALUES (?, ?, 'used', ?, NOW())");
            $transactionResult = $transactionStmt->execute([$walletId, -$pointsToUse, "Used for order {$orderNumber}"]);
            
            if ($transactionResult) {
                error_log("âœ… WALLET TRANSACTION: Recorded successfully");
            } else {
                error_log("âŒ WALLET TRANSACTION: Failed to record");
            }
        }
        
        // Shiprocket integration
        try {
            error_log("Attempting Shiprocket integration...");
            $shiprocketResult = autoCreateShiprocketOrder($orderDbId, $orderNumber, $shippingAddress, $cartItems);

            if ($shiprocketResult['success']) {
                $updateShiprocketStmt = $conn->prepare("
                    UPDATE orders 
                    SET shiprocket_order_id = ?, shiprocket_shipment_id = ?, tracking_number = ?, status = 'processing'
                    WHERE id = ?
                ");
                $updateShiprocketStmt->execute([
                    $shiprocketResult['order_id'],
                    $shiprocketResult['shipment_id'],
                    $shiprocketResult['tracking_number'],
                    $orderDbId
                ]);
                
                error_log("âœ… SHIPROCKET: Order created successfully");
            } else {
                error_log("âŒ SHIPROCKET: Failed - " . ($shiprocketResult['message'] ?? 'Unknown error'));
            }
        } catch (Exception $shipError) {
            error_log("âŒ SHIPROCKET ERROR: " . $shipError->getMessage());
        }

        // Clear cart
        clearCart($userId);
        error_log("âœ… CART: Cleared successfully");

        // Persist delivery address for one-click repeat checkout
        if ($userId && !empty($shippingAddress['address'])) {
            try {
                saveCustomerAddress($userId, [
                    'full_name'    => trim(($shippingAddress['first_name'] ?? '') . ' ' . ($shippingAddress['last_name'] ?? '')),
                    'phone'        => $shippingAddress['phone']     ?? '',
                    'email'        => $shippingAddress['email']     ?? '',
                    'address_line' => $shippingAddress['address']   ?? '',
                    'apartment'    => $shippingAddress['apartment'] ?? '',
                    'city'         => $shippingAddress['city']      ?? '',
                    'state'        => $shippingAddress['state']     ?? '',
                    'pincode'      => $shippingAddress['pincode']   ?? '',
                ]);
            } catch (Exception $addrEx) {
                error_log('saveCustomerAddress error: ' . $addrEx->getMessage());
            }
        }
        
        // Clean up session data
        unset($_SESSION['checkout_data'], $_SESSION['points_to_use'], $_SESSION['referral_code'], $_SESSION['razorpay_order_id']);
        
        // Final verification
        $finalVerifyStmt = $conn->prepare("SELECT id, order_number, user_id, final_amount FROM orders WHERE id = ?");
        $finalVerifyStmt->execute([$orderDbId]);
        $finalOrder = $finalVerifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$finalOrder) {
            throw new Exception('Final order verification failed');
        }
        
        error_log("âœ… FINAL VERIFICATION: " . json_encode($finalOrder));
        
        // Process referral purchase - EXISTING LOGIC WITH HOLD SYSTEM ADDED
        if (isset($_SESSION['referral_code']) || isset($_COOKIE['referral_code'])) {
            $referralCode = $_SESSION['referral_code'] ?? $_COOKIE['referral_code'];
            
            if ($referralCode) {
                try {
                    error_log("Processing referral purchase for code: " . $referralCode);
                    
                    $stmt = $conn->prepare("SELECT id, user_id, created_at FROM referrals WHERE code = ?");
                    $stmt->execute([$referralCode]);
                    
                    if ($stmt->rowCount() > 0) {
                        $referral = $stmt->fetch();
                        $referralId = $referral['id'];
                        $referrerId = $referral['user_id'];
                        
                        // Don't process if customer is the referrer themselves
                        if ($referrerId != $userId) {
                            // Calculate purchase month (months since referral creation)
                            $referralCreated = new DateTime($referral['created_at']);
                            $now = new DateTime();
                            $diff = $referralCreated->diff($now);
                            $purchaseMonth = ($diff->y * 12) + $diff->m + 1;
                            
                            // Get month-wise referral rates from dynamic settings
                            $firstMonthRate = (float) getSetting('first_month_rate', 10.0);
                            $otherMonthsRate = (float) getSetting('other_months_rate', 5.0);
                            
                            // Apply month-wise rate logic
                            $earningRate = ($purchaseMonth == 1) ? $firstMonthRate : $otherMonthsRate;
                            $points = floor(($finalPrice * $earningRate) / 100);
                            
                            if ($points > 0) {
                                // HOLD SYSTEM: Calculate hold until date (7 days from now)
                                $holdUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
                                
                                // Record the referral purchase with HOLD STATUS
                                $stmt = $conn->prepare("
                                    INSERT INTO referral_purchases 
                                    (referral_id, order_id, amount, points_earned, purchase_month, earning_rate, status, hold_until, hold_status) 
                                    VALUES (?, ?, ?, ?, ?, ?, 'credited', ?, 'hold')
                                ");
                                $stmt->execute([
                                    $referralId, 
                                    $orderNumber,
                                    $finalPrice, 
                                    $points, 
                                    $purchaseMonth, 
                                    $earningRate,
                                    $holdUntil,
                                ]);
                                $purchaseId = $conn->lastInsertId();
                                
                                // HOLD SYSTEM: DO NOT ADD POINTS TO WALLET YET - They are on hold
                                // Just ensure wallet exists and log the transaction for tracking purposes
                                $walletCheck = $conn->prepare("SELECT id FROM wallet WHERE user_id = ?");
                                $walletCheck->execute([$referrerId]);
                                
                                if ($walletCheck->rowCount() > 0) {
                                    $walletRow = $walletCheck->fetch();
                                    $walletId = $walletRow['id'];
                                } else {
                                    // Create new wallet entry
                                    $walletCreate = $conn->prepare("
                                        INSERT INTO wallet (user_id, points, pending_points, total_earned) 
                                        VALUES (?, 0, 0, 0)
                                    ");
                                    $walletCreate->execute([$referrerId]);
                                    $walletId = $conn->lastInsertId();
                                }
                                
                                // Log the held transaction for tracking purposes
                                try {
                                    $stmt = $conn->prepare("
                                        INSERT INTO wallet_transactions 
                                        (wallet_id, points, transaction_type, reference_id, description, created_at) 
                                        VALUES (?, ?, 'held', ?, ?, NOW())
                                    ");
                                    $stmt->execute([
                                        $walletId, 
                                        $points, 
                                        $purchaseId, 
                                        "Referral points on hold from order {$orderNumber} (Month {$purchaseMonth} - {$earningRate}%) - Release on {$holdUntil}"
                                    ]);
                                } catch (Exception $e) {
                                    // Continue if wallet_transactions fails (table might have different structure)
                                    error_log("Wallet transaction logging failed: " . $e->getMessage());
                                }
                                
                                error_log("HOLD SYSTEM: Referral processed with HOLD - Order {$orderNumber}, Referrer ID: {$referrerId}, Points: {$points}, Month: {$purchaseMonth}, Rate: {$earningRate}%, Release on {$holdUntil}");
                                error_log("WALLET: Points are on hold - will be credited after 7 days if no return is initiated");
                                
                            } else {
                                error_log("REFERRAL: Order amount too small for points. Final price: {$finalPrice}");
                            }
                        } else {
                            error_log("REFERRAL: Skipped - customer is the referrer themselves");
                        }
                        
                    } else {
                        error_log("REFERRAL: Invalid referral code: {$referralCode}");
                    }
                } catch (Exception $e) {
                    error_log("REFERRAL ERROR: " . $e->getMessage());
                }
                
                // Clear referral codes after processing
                unset($_SESSION['referral_code']);
                if (isset($_COOKIE['referral_code'])) {
                    setcookie('referral_code', '', time() - 3600, '/');
                }
            } else {
                error_log("REFERRAL: No referral code found in session/cookie");
            }
        } else {
            error_log("REFERRAL: No referral session or cookie detected");
        }
        
        // Commit transaction
        $conn->commit();
        error_log("âœ… TRANSACTION: Committed successfully");

        // Set success variables
        $orderProcessed = true;
        $orderId = $orderDbId;
        
        return true;

    } catch (Exception $e) {
        if ($conn && $conn->inTransaction()) {
            $conn->rollBack();
        }

        global $error;
        $error = 'Order processing failed: ' . $e->getMessage();
        error_log("Order creation error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        return false;
    }
}

// If order processed successfully, show thank you page
if ($orderProcessed) {
    // Get the shipping address from session for email
    $shippingAddress = $_SESSION['checkout_data'] ?? [];
    
    // If session was cleared, reconstruct customer data from what we stored during order processing
    if (empty($shippingAddress) && $orderProcessed) {
        // Try to get customer data from the order that was just created
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT shipping_address FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $orderShippingData = $stmt->fetchColumn();
            if ($orderShippingData) {
                $shippingAddress = json_decode($orderShippingData, true) ?: [];
            }
        } catch (Exception $e) {
            error_log("Could not retrieve shipping address for email: " . $e->getMessage());
        }
    }
    
    $pointsToUse = $_SESSION['points_to_use'] ?? 0;
    
    // Send order confirmation email using your existing SendinblueMailer
    try {
        require_once 'includes/sendinblue-mailer.php';
        
        // Get API key from settings
        $sendinblueApiKey = getSetting('sendinblue_api_key');
        $sendinblueFromEmail = getSetting('sendinblue_from_email', 'info@bluefifth.in');
        $sendinblueFromName = getSetting('sendinblue_from_name', 'Bluefifth Team');
        
        if ($sendinblueApiKey && $sendinblueApiKey !== 'YOUR_API_KEY_HERE') {
            // Initialize mailer
            $mailer = new SendinblueMailer($sendinblueApiKey, $sendinblueFromEmail, $sendinblueFromName);
            
            // Prepare customer data - FIXED FOR BOTH LOGGED-IN AND GUEST USERS
            $customerName = ($shippingAddress['first_name'] ?? 'Customer') . ' ' . ($shippingAddress['last_name'] ?? '');
            $customerEmail = $shippingAddress['email'] ?? ($userInfo['email'] ?? 'customer@example.com');
            
            // Ensure we have valid email for guests - get from form data if shipping address is empty
            if (empty($customerEmail) || $customerEmail === 'customer@example.com') {
                $formData = $_SESSION['checkout_data'] ?? [];
                $customerEmail = $formData['email'] ?? 'customer@example.com';
                
                // Also update customer name if needed
                if ($customerName === 'Customer ' || empty(trim($customerName))) {
                    $customerName = ($formData['first_name'] ?? 'Customer') . ' ' . ($formData['last_name'] ?? '');
                }
            }
            
            // Create order confirmation email content
            $subject = "Order Confirmed - {$orderNumber} | " . getSetting('site_name', 'Velona');
            
            // Create items HTML for email
            $itemsHtml = '';
            $totalItems = 0;
            foreach ($cartItems as $item) {
                $totalItems += $item['quantity'];
                $itemsHtml .= "
                    <tr style='border-bottom: 1px solid #eee;'>
                        <td style='padding: 15px; vertical-align: top;'>
                            <div style='font-weight: 600; color: #333; margin-bottom: 5px;'>{$item['product_name']}</div>
                            " . ($item['size'] ? "<div style='font-size: 12px; color: #666;'>Size: {$item['size']}</div>" : "") . "
                        </td>
                        <td style='padding: 15px; text-align: center; color: #666;'>{$item['quantity']}</td>
                        <td style='padding: 15px; text-align: right; color: #333; font-weight: 500;'>{$currencySymbol}" . number_format($item['product_price'], 2) . "</td>
                        <td style='padding: 15px; text-align: right; color: #333; font-weight: 600;'>{$currencySymbol}" . number_format($item['total_price'], 2) . "</td>
                    </tr>
                ";
            }
            
            // Create order confirmation HTML email
            $emailHtml = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Order Confirmation</title>
            </head>
            <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f4f4f4;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff;'>
                    <!-- Header -->
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;'>
                        <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;'>Thank You for Your Order!</h1>
                        <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;'>Your order has been confirmed and is being processed</p>
                    </div>
                    
                    <!-- Order Summary -->
                    <div style='padding: 30px;'>
                        <div style='text-align: center; margin-bottom: 30px;'>
                            <div style='display: inline-block; background: #f8f9fa; padding: 20px 30px; border-radius: 8px; border: 2px solid #e9ecef;'>
                                <div style='color: #666; font-size: 14px; margin-bottom: 5px;'>Order Number</div>
                                <div style='color: #667eea; font-size: 24px; font-weight: 700;'>{$orderNumber}</div>
                            </div>
                        </div>
                        
                        <div style='background: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 30px;'>
                            <h2 style='color: #333; margin: 0 0 20px 0; font-size: 18px;'>ðŸ’° Order Summary</h2>
                            
                            <!-- Hidden Subtotal (before tax) -->
                            <div class='d-none' style='display: none;'>
                                <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                    <span style='color: #666;'>Subtotal (before tax):</span>
                                    <span style='color: #333; font-weight: 500;'>{$currencySymbol}" . number_format($totalAmount - $taxAmount, 2) . "</span>
                                </div>
                            </div>
                            
                            " . (isset($appliedCoupon) && $appliedCoupon && $couponDiscount > 0 ? "
                            <!-- Coupon Discount in Email -->
                            <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                <span style='color: #dc3545; font-weight: 600;'>ðŸ’° Coupon Discount (" . htmlspecialchars($appliedCoupon['code']) . " - " . $appliedCoupon['discount_percentage'] . "%):</span>
                                <span style='color: #dc3545; font-weight: 600;'>-{$currencySymbol}" . number_format($couponDiscount, 2) . "</span>
                            </div>
                            " : "") . "
                            
                            <!-- Hidden Tax -->
                            <div class='d-none' style='display: none;'>
                                " . ($taxAmount > 0 ? "
                                <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                    <span style='color: #666;'>Tax ({$taxRate}%):</span>
                                    <span style='color: #333; font-weight: 500;'>{$currencySymbol}" . number_format($taxAmount, 2) . "</span>
                                </div>
                                " : "") . "
                            </div>
                            
                            " . ($shippingCost > 0 ? "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                <span style='color: #666;'>Shipping:</span>
                                <span style='color: #333; font-weight: 500;'>{$currencySymbol}" . number_format($shippingCost, 2) . "</span>
                            </div>
                            " : "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                <span style='color: #666;'>Shipping:</span>
                                <span style='color: #28a745; font-weight: 500;'>FREE</span>
                            </div>
                            ") . "
                            
                            " . ($pointsToUse > 0 ? "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                                <span style='color: #666;'>ðŸ’° Wallet Points Used:</span>
                                <span style='color: #28a745; font-weight: 500;'>-{$currencySymbol}" . number_format($pointsToUse, 2) . "</span>
                            </div>
                            " : "") . "
                            
                            <hr style='border: none; border-top: 1px solid #dee2e6; margin: 15px 0;'>
                            <div style='display: flex; justify-content: space-between; font-size: 18px;'>
                                <span style='color: #333; font-weight: 600;'>Total Paid:</span>
                                <span style='color: #28a745; font-weight: 700;'>{$currencySymbol}" . number_format($finalPrice, 2) . "</span>
                            </div>
                        </div>
                    </div>
                        
                        <!-- Order Items -->
                        <div style='margin-bottom: 30px;'>
                            <h2 style='color: #333; margin: 0 0 20px 0; font-size: 18px;'>ðŸ“¦ Order Items</h2>
                            <table style='width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #eee; border-radius: 8px; overflow: hidden;'>
                                <thead>
                                    <tr style='background: #f8f9fa;'>
                                        <th style='padding: 15px; text-align: left; color: #333; font-weight: 600; border-bottom: 1px solid #eee;'>Product</th>
                                        <th style='padding: 15px; text-align: center; color: #333; font-weight: 600; border-bottom: 1px solid #eee;'>Qty</th>
                                        <th style='padding: 15px; text-align: right; color: #333; font-weight: 600; border-bottom: 1px solid #eee;'>Price</th>
                                        <th style='padding: 15px; text-align: right; color: #333; font-weight: 600; border-bottom: 1px solid #eee;'>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$itemsHtml}
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Shipping Address -->
                        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                            <h3 style='color: #333; margin-bottom: 15px; font-size: 16px;'>ðŸ“¦ Shipping Address</h3>
                            <div style='color: #555; line-height: 1.6;'>
                                <strong>" . ($shippingAddress['first_name'] ?? 'Customer') . " " . ($shippingAddress['last_name'] ?? '') . "</strong><br>
                                " . ($shippingAddress['address'] ?? 'Address not provided') . "<br>
                                " . (!empty($shippingAddress['apartment']) ? "{$shippingAddress['apartment']}<br>" : "") . "
                                " . ($shippingAddress['city'] ?? 'City') . ", " . ($shippingAddress['state'] ?? 'State') . " " . ($shippingAddress['pincode'] ?? 'PIN') . "<br>
                                " . ($shippingAddress['country'] ?? 'IN') . "<br>
                                ðŸ“ž " . ($shippingAddress['phone'] ?? 'Phone not provided') . "
                            </div>
                        </div>
                        
                        <!-- What's Next -->
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; margin: 30px 0;'>
                            <h3 style='margin: 0 0 15px 0; font-size: 18px;'>ðŸš€ What's Next?</h3>
                            <ul style='margin: 0; padding-left: 20px; line-height: 1.8;'>
                                <li>We'll prepare your order within 24 hours</li>
                                <li>You'll receive a tracking number once shipped</li>
                                <li>Estimated delivery: 3-7 business days</li>
                                <li>Free shipping on orders above {$currencySymbol}500</li>
                            </ul>
                        </div>
                        
                        <!-- Action Button -->
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='" . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}/invoice.php?order_id={$orderId}' 
                               style='display: inline-block; background: #28a745; color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: 600; margin: 10px;'>
                                ðŸ“„ Download Invoice
                            </a>
                            <a href='" . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}/account/orders.php' 
                               style='display: inline-block; background: #667eea; color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: 600; margin: 10px;'>
                                ðŸš› Track Order
                            </a>
                        </div>
                        
                        <!-- Contact Info -->
                        <div style='text-align: center; padding: 20px 0; border-top: 1px solid #eee; margin-top: 30px;'>
                            <p style='color: #666; margin: 0 0 10px 0; font-size: 14px;'>Need help with your order?</p>
                            <p style='color: #333; margin: 0; font-weight: 500;'>
                                ðŸ“§ <a href='mailto:" . getSetting('contact_email', 'contact@velona.com') . "' style='color: #667eea; text-decoration: none;'>" . getSetting('contact_email', 'contact@velona.com') . "</a> | 
                                ðŸ“ž " . getSetting('contact_phone', '+91 9876543210') . "
                            </p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #eee;'>
                        <p style='color: #666; margin: 0; font-size: 14px;'>
                            Â© " . date('Y') . " " . getSetting('site_name', 'Velona') . ". All rights reserved.<br>
                            Thank you for shopping with us!
                        </p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // Send the email
            $emailSent = $mailer->sendEmail($customerEmail, $customerName, $subject, $emailHtml);
            
            if ($emailSent) {
                error_log("Order confirmation email sent successfully to {$customerEmail}");
            } else {
                error_log("Failed to send order confirmation email to {$customerEmail}");
            }
        }
    } catch (Exception $e) {
        error_log("Order confirmation email error: " . $e->getMessage());
    }
    
// Ensure all email variables are properly set
$emailSubtotal = $totalAmount;
$emailTaxExclusiveSubtotal = $emailSubtotal - $taxAmount;
    ?>

    <!doctype html>
    <html lang="en">
    <head>
      <link rel="stylesheet" href="assets/css/style.css">
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
      <title>Order Confirmed - <?= htmlspecialchars($siteName) ?></title>
      
      <style>
        body {
          font-family: 'Poppins', sans-serif;
          background: linear-gradient(135deg, #f0f4ff 0%, #fff5fb 100%);
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 20px 0;
        }

        .ty-card {
          background: white;
          border-radius: 28px;
          padding: 3rem 2.5rem;
          text-align: center;
          box-shadow: 0 24px 64px rgba(0,0,0,0.12);
          position: relative;
          overflow: visible;
          max-width: 520px;
          width: 100%;
          margin: 0 auto;
        }

        /* â”€â”€ Animated SVG checkmark â”€â”€ */
        .success-icon-wrap {
          width: 110px;
          height: 110px;
          margin: 0 auto 1.6rem;
          position: relative;
        }

        .check-svg {
          width: 110px;
          height: 110px;
          filter: drop-shadow(0 6px 18px rgba(40,167,69,0.35));
        }

        .check-circle-bg {
          fill: #e8f5e9;
          stroke: none;
        }

        .check-ring {
          fill: none;
          stroke: #28a745;
          stroke-width: 5;
          stroke-linecap: round;
          stroke-dasharray: 314;
          stroke-dashoffset: 314;
          transform-origin: 55px 55px;
          transform: rotate(-90deg);
          animation: draw-ring 0.65s cubic-bezier(0.4,0,0.2,1) 0.15s forwards;
        }

        .check-tick {
          fill: none;
          stroke: #28a745;
          stroke-width: 5.5;
          stroke-linecap: round;
          stroke-linejoin: round;
          stroke-dasharray: 55;
          stroke-dashoffset: 55;
          animation: draw-tick 0.35s ease 0.75s forwards;
        }

        @keyframes draw-ring {
          to { stroke-dashoffset: 0; }
        }
        @keyframes draw-tick {
          to { stroke-dashoffset: 0; }
        }

        /* â”€â”€ Pop-scale on arrival â”€â”€ */
        .ty-card {
          animation: card-pop 0.5s cubic-bezier(0.34,1.56,0.64,1) forwards;
          transform: scale(0.9);
          opacity: 0;
        }
        @keyframes card-pop {
          to { transform: scale(1); opacity: 1; }
        }

        /* â”€â”€ Typography â”€â”€ */
        .ty-title {
          font-size: 2.2rem;
          font-weight: 700;
          color: #1a1a2e;
          margin-bottom: 0.4rem;
          letter-spacing: -0.5px;
        }

        .ty-sub {
          font-size: 1rem;
          color: #6c757d;
          margin-bottom: 1.6rem;
        }

        /* â”€â”€ Order summary card â”€â”€ */
        .order-card {
          background: linear-gradient(135deg, #e8f5e9 0%, #f0fff4 100%);
          border: 1.5px solid #c8e6c9;
          border-radius: 18px;
          padding: 1.5rem 1.2rem;
          margin: 0 0 1.6rem;
        }

        .order-num {
          font-size: 1rem;
          font-weight: 600;
          color: #004AAD;
          margin-bottom: 0.4rem;
          letter-spacing: 0.3px;
        }

        .order-amt {
          font-size: 2.2rem;
          font-weight: 700;
          color: #1e7e34;
          line-height: 1.1;
        }

        .order-meta {
          font-size: 0.82rem;
          color: #888;
          margin-top: 0.6rem;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 0.4rem;
        }

        .order-meta i { color: #28a745; }

        /* â”€â”€ Action buttons â”€â”€ */
        .ty-actions {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 0.7rem;
          margin-bottom: 1.6rem;
        }

        .ty-btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          gap: 0.45rem;
          padding: 0.75rem 1rem;
          border-radius: 50px;
          font-size: 0.88rem;
          font-weight: 600;
          text-decoration: none;
          transition: transform 0.2s ease, box-shadow 0.2s ease;
          border: none;
          cursor: pointer;
          white-space: nowrap;
        }

        .ty-btn:hover { transform: translateY(-3px); text-decoration: none; }

        .ty-btn-home {
          grid-column: 1 / -1;
          background: linear-gradient(135deg, #004AAD, #0066cc);
          color: white;
        }
        .ty-btn-home:hover  { box-shadow: 0 10px 24px rgba(0,74,173,0.35); color: white; }

        .ty-btn-shop {
          background: #f5f5f5;
          color: #333;
          border: 1.5px solid #e0e0e0;
        }
        .ty-btn-shop:hover  { background: #ebebeb; color: #333; }

        .ty-btn-track {
          background: #f5f5f5;
          color: #333;
          border: 1.5px solid #e0e0e0;
        }
        .ty-btn-track:hover { background: #ebebeb; color: #333; }

        .ty-btn-invoice {
          grid-column: 1 / -1;
          background: linear-gradient(135deg, #28a745, #20c997);
          color: white;
        }
        .ty-btn-invoice:hover { box-shadow: 0 10px 24px rgba(40,167,69,0.35); color: white; }

        /* â”€â”€ What's next â”€â”€ */
        .whats-next {
          border-top: 1px solid #f0f0f0;
          padding-top: 1.4rem;
        }

        .whats-next-title {
          font-size: 0.85rem;
          font-weight: 700;
          color: #333;
          text-transform: uppercase;
          letter-spacing: 0.8px;
          margin-bottom: 0.8rem;
        }

        .next-list {
          list-style: none;
          padding: 0;
          margin: 0;
          display: inline-block;
          text-align: left;
        }

        .next-list li {
          font-size: 0.83rem;
          color: #666;
          padding: 0.28rem 0;
          display: flex;
          align-items: center;
          gap: 0.55rem;
        }

        .next-list li i { color: #28a745; width: 14px; flex-shrink: 0; }

        @media (max-width: 500px) {
          .ty-card    { padding: 2rem 1.1rem; border-radius: 20px; }
          .ty-title   { font-size: 1.7rem; }
          .ty-actions { grid-template-columns: 1fr; }
          .ty-btn-home, .ty-btn-invoice { grid-column: 1; }
          .order-amt  { font-size: 1.8rem; }
        }
      </style>
    </head>
    <body>
      <!-- confetti renders on its own canvas â€” no extra DOM needed -->

      <div class="container">
        <div class="row justify-content-center">
          <div class="col-12 px-3">
            <div class="ty-card">

              <!-- Animated SVG checkmark -->
              <div class="success-icon-wrap">
                <svg class="check-svg" viewBox="0 0 110 110" xmlns="http://www.w3.org/2000/svg">
                  <circle class="check-circle-bg" cx="55" cy="55" r="50"/>
                  <circle class="check-ring" cx="55" cy="55" r="50"/>
                  <polyline class="check-tick" points="32,57 47,72 78,38"/>
                </svg>
              </div>

              <h1 class="ty-title">Thank You!</h1>
              <p class="ty-sub">Your order has been successfully placed and confirmed.</p>

              <!-- Order card -->
              <div class="order-card">
                <div class="order-num">Order #<?= htmlspecialchars($orderNumber) ?></div>
                <div class="order-amt">â‚¹<?= number_format($finalPrice, 2) ?></div>
                <?php if ($pointsToUse > 0): ?>
                <div class="order-meta">
                  <i class="fas fa-wallet"></i>
                  Wallet points used: â‚¹<?= number_format($pointsToUse, 2) ?>
                </div>
                <?php endif; ?>
                <div class="order-meta">
                  <i class="fas fa-clock"></i>
                  Confirmation email sent with tracking details
                </div>
              </div>

              <!-- Action buttons -->
              <div class="ty-actions">
                <a href="index.php" class="ty-btn ty-btn-home">
                  <i class="fas fa-home"></i> Back to Home
                </a>
                <a href="shop/category.php" class="ty-btn ty-btn-shop">
                  <i class="fas fa-shopping-bag"></i> Shop More
                </a>
                <a href="account/orders.php" class="ty-btn ty-btn-track">
                  <i class="fas fa-truck"></i> Track Order
                </a>
                <a href="invoice.php?order_id=<?= $orderId ?>" target="_blank" class="ty-btn ty-btn-invoice">
                  <i class="fas fa-file-invoice"></i> Download Invoice
                </a>
              </div>

              <!-- What's next -->
              <div class="whats-next">
                <p class="whats-next-title">What happens next?</p>
                <ul class="next-list">
                  <li><i class="fas fa-envelope"></i> Order confirmation email sent</li>
                  <li><i class="fas fa-box"></i> We'll prepare your order within 24 hours</li>
                  <li><i class="fas fa-shipping-fast"></i> Free shipping on orders above <?= htmlspecialchars($currencySymbol) ?><?= number_format($freeShippingThreshold, 0) ?></li>
                  <li><i class="fas fa-headset"></i> Need help? Contact info@bluefifth.in</li>
                </ul>
              </div>

            </div><!-- /.ty-card -->
          </div>
        </div>
      </div>

      <!-- canvas-confetti CDN -->
      <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          var COLORS = ['#004AAD','#28a745','#FFD700','#ff6b6b','#4ecdc4','#a29bfe','#fd79a8','#f9ca24'];

          function fire(opts) {
            confetti(Object.assign({ zIndex: 9999, colors: COLORS }, opts));
          }

          // Initial big burst â€” fires from both lower corners (Flipkart/Amazon style)
          setTimeout(function () {
            fire({ particleCount: 60, angle: 60,  spread: 55, origin: { x: 0,   y: 0.75 } });
            fire({ particleCount: 60, angle: 120, spread: 55, origin: { x: 1,   y: 0.75 } });
            fire({ particleCount: 80, spread: 80, origin: { x: 0.5, y: 0.65 }, startVelocity: 40 });
          }, 550);

          // Gold coin burst
          setTimeout(function () {
            confetti({
              particleCount: 70,
              spread: 90,
              origin: { x: 0.5, y: 0.55 },
              colors: ['#FFD700','#FFA500','#FF8C00','#FFEC8B'],
              shapes: ['circle'],
              scalar: 1.4,
              gravity: 0.7,
              zIndex: 9999
            });
          }, 900);

          // Second side-burst for extra drama
          setTimeout(function () {
            fire({ particleCount: 40, angle: 60,  spread: 45, origin: { x: 0,   y: 0.7 } });
            fire({ particleCount: 40, angle: 120, spread: 45, origin: { x: 1,   y: 0.7 } });
          }, 1500);
        });
      </script>
    </body>
    </html>
    <?php
    exit;
}
?>


<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Checkout â€“ <?= htmlspecialchars($siteName) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/4358befd66.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">

  <style>
    /* â”€â”€â”€ Base â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    *{box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:#f7f8fc;color:#1a1a2e;margin:0}
    .co-wrap{display:grid;grid-template-columns:1fr 420px;gap:32px;max-width:1060px;margin:0 auto;padding:32px 16px 64px}
    @media(max-width:900px){.co-wrap{grid-template-columns:1fr;padding:16px 12px 80px}}

    /* â”€â”€â”€ Header â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .co-header{background:#fff;border-bottom:1px solid #eee;padding:14px 20px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:200}
    .co-header a{color:#555;text-decoration:none;font-size:14px;display:flex;align-items:center;gap:5px}
    .co-header a:hover{color:#004AAD}
    .co-title{font-size:17px;font-weight:600;color:#1a1a2e;margin:0 auto}
    .co-header-count{font-size:13px;color:#888;white-space:nowrap}

    /* â”€â”€â”€ Cards â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .co-card{background:#fff;border-radius:14px;padding:22px 20px;margin-bottom:16px;border:1px solid #eee}
    .co-card-title{font-size:14px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.6px;margin-bottom:16px}

    /* â”€â”€â”€ Phone â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .phone-row{display:flex;gap:10px;align-items:stretch}
    .phone-prefix{display:flex;align-items:center;padding:0 12px;background:#f0f4ff;border:1px solid #dde2f0;border-radius:8px;font-size:14px;font-weight:600;color:#004AAD;white-space:nowrap}
    .phone-input{flex:1;border:1px solid #dde2f0;border-radius:8px;padding:12px 14px;font-size:15px;font-family:'Poppins',sans-serif;outline:none;transition:border-color .2s}
    .phone-input:focus{border-color:#004AAD;box-shadow:0 0 0 3px rgba(0,74,173,.1)}
    .phone-lookup-btn{padding:0 16px;background:#f0f4ff;border:1px solid #dde2f0;border-radius:8px;font-size:13px;font-weight:600;color:#004AAD;cursor:pointer;white-space:nowrap;transition:all .2s}
    .phone-lookup-btn:hover{background:#004AAD;color:#fff;border-color:#004AAD}
    .welcome-msg{margin-top:10px;padding:10px 14px;background:#f0fff4;border:1px solid #c8e6c9;border-radius:8px;font-size:13px;color:#2d6a4f;font-weight:500}

    /* â”€â”€â”€ Payment options â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .pay-option{display:flex;align-items:center;gap:14px;padding:14px 16px;border:2px solid #eee;border-radius:12px;cursor:pointer;transition:all .2s;margin-bottom:10px;position:relative}
    .pay-option:last-child{margin-bottom:0}
    .pay-option.selected{border-color:#004AAD;background:#f0f4ff}
    .pay-option input[type=radio]{width:18px;height:18px;accent-color:#004AAD;flex-shrink:0}
    .pay-option-label{flex:1}
    .pay-option-title{font-size:15px;font-weight:600;color:#1a1a2e}
    .pay-option-sub{font-size:12px;color:#888;margin-top:2px}
    .pay-option-badge{background:#28a745;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:auto}

    /* â”€â”€â”€ Coupon â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .coupon-row{display:flex;gap:8px}
    .coupon-input{flex:1;border:1px solid #dde2f0;border-radius:8px;padding:11px 14px;font-size:14px;font-family:'Poppins',sans-serif;outline:none;text-transform:uppercase;transition:border-color .2s}
    .coupon-input:focus{border-color:#004AAD}
    .coupon-btn{padding:0 18px;background:#1a1a2e;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s}
    .coupon-btn:hover{background:#004AAD}
    .coupon-applied{display:flex;align-items:center;gap:8px;padding:10px 14px;background:#f0fff4;border:1px solid #c8e6c9;border-radius:8px;font-size:13px;color:#2d6a4f;margin-top:8px}
    .coupon-remove{margin-left:auto;background:none;border:none;color:#dc3545;cursor:pointer;font-size:16px;padding:0;line-height:1}

    /* â”€â”€â”€ Order summary sidebar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .summary-sticky{position:sticky;top:70px}
    .item-row{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #f0f0f0}
    .item-row:last-child{border-bottom:none}
    .item-img{width:54px;height:54px;object-fit:cover;border-radius:8px;flex-shrink:0;border:1px solid #eee}
    .item-info{flex:1;min-width:0}
    .item-name{font-size:13px;font-weight:600;color:#1a1a2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .item-meta{font-size:11px;color:#888;margin-top:2px}
    .item-price{font-size:13px;font-weight:600;color:#1a1a2e;white-space:nowrap;align-self:center}
    .tot-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:14px;color:#555}
    .tot-row.green{color:#28a745;font-weight:600}
    .tot-row.red{color:#dc3545;font-weight:600}
    .tot-divider{border:none;border-top:1px solid #eee;margin:10px 0}
    .tot-final{display:flex;justify-content:space-between;align-items:center;padding:10px 0;font-size:17px;font-weight:700;color:#1a1a2e}

    /* â”€â”€â”€ Pay CTA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .pay-cta{width:100%;padding:15px;background:#004AAD;color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;font-family:'Poppins',sans-serif}
    .pay-cta:hover:not(:disabled){background:#0C2D71;transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,74,173,.35)}
    .pay-cta:disabled{opacity:.65;cursor:not-allowed;transform:none}
    .pay-cta .spinner{width:18px;height:18px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none}
    @keyframes spin{to{transform:rotate(360deg)}}
    .pay-cta.loading .spinner{display:block}
    .pay-cta.loading .btn-text{display:none}
    .trust-row{display:flex;align-items:center;justify-content:center;gap:6px;font-size:11px;color:#aaa;margin-top:10px}

    /* â”€â”€â”€ COD Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:flex-end;justify-content:center}
    @media(min-width:600px){.modal-overlay{align-items:center}}
    .modal-overlay.open{display:flex}
    .modal-box{background:#fff;border-radius:20px 20px 0 0;padding:24px 20px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;animation:slideUp .3s cubic-bezier(.34,1.56,.64,1)}
    @media(min-width:600px){.modal-box{border-radius:20px}}
    @keyframes slideUp{from{transform:translateY(40px);opacity:0}to{transform:translateY(0);opacity:1}}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
    .modal-title{font-size:17px;font-weight:700;color:#1a1a2e}
    .modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:#888;line-height:1;padding:0}
    .mf-row{display:flex;gap:10px}
    .mf-group{flex:1;margin-bottom:14px}
    .mf-label{font-size:12px;font-weight:600;color:#555;margin-bottom:5px;display:block}
    .mf-input{width:100%;border:1px solid #dde2f0;border-radius:8px;padding:11px 13px;font-size:14px;font-family:'Poppins',sans-serif;outline:none;transition:border-color .2s}
    .mf-input:focus{border-color:#004AAD;box-shadow:0 0 0 3px rgba(0,74,173,.08)}
    .mf-select{width:100%;border:1px solid #dde2f0;border-radius:8px;padding:11px 13px;font-size:14px;font-family:'Poppins',sans-serif;outline:none;appearance:none;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23999' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 12px center}
    .cod-btn{width:100%;padding:14px;background:#1a1a2e;color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;margin-top:8px;font-family:'Poppins',sans-serif;transition:background .2s}
    .cod-btn:hover{background:#004AAD}
    .cod-btn:disabled{opacity:.65;cursor:not-allowed}

    /* â”€â”€â”€ Error toast â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .err-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#dc3545;color:#fff;padding:12px 22px;border-radius:10px;font-size:14px;font-weight:500;z-index:2000;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;max-width:90vw}
    .err-toast.show{opacity:1}

    /* â”€â”€â”€ Mobile sticky bar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .mob-pay-bar{display:none;position:fixed;bottom:0;left:0;right:0;padding:12px 16px;background:#fff;border-top:1px solid #eee;z-index:100}
    @media(max-width:900px){.mob-pay-bar{display:block}}
    .mob-pay-cta{width:100%;padding:14px;background:#004AAD;color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:'Poppins',sans-serif;transition:all .2s}
    .mob-pay-cta:hover:not(:disabled){background:#0C2D71}
    .mob-pay-cta:disabled{opacity:.65;cursor:not-allowed}

    /* â”€â”€â”€ Wallet slider â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .wallet-slider{-webkit-appearance:none;width:100%;height:5px;border-radius:3px;background:#e0e0e0;outline:none;margin-top:8px}
    .wallet-slider::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:#004AAD;cursor:pointer}
    .wallet-slider::-moz-range-thumb{width:18px;height:18px;border-radius:50%;background:#004AAD;cursor:pointer;border:none}
  </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- â”€â”€â”€ Page header â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="co-header nav-align">
  <a href="shop/cart.php"><i class="fas fa-arrow-left"></i> Cart</a>
  <span class="co-title">Checkout</span>
  <span class="co-header-count"><?= count($cartItems) ?> item<?= count($cartItems)!==1?'s':'' ?></span>
</div>

<div class="co-wrap">

  <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       LEFT COLUMN  â€”  Contact Â· Coupon Â· Wallet Â· Payment
  â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
  <div>

    <!-- â”€â”€ Contact / Phone â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div class="co-card">
      <div class="co-card-title">Contact</div>
      <div class="phone-row">
        <span class="phone-prefix">+91</span>
        <input type="tel" id="co-phone" class="phone-input" placeholder="Mobile number"
               maxlength="10" inputmode="numeric"
               value="<?= htmlspecialchars($fillPhone) ?>">
        <button class="phone-lookup-btn" onclick="lookupCustomer()" id="lookup-btn">Use saved details</button>
      </div>
      <div id="phone-welcome" style="display:none"></div>
    </div>

    <!-- â”€â”€ Coupon â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div class="co-card">
      <div class="co-card-title">Coupon</div>
      <div id="coupon-applied-banner" style="display:<?= $appliedCoupon ? 'flex' : 'none' ?>" class="coupon-applied">
        <i class="fas fa-tag"></i>
        <span id="coupon-applied-text"><?= $appliedCoupon ? "Coupon <strong>{$appliedCoupon['code']}</strong> applied â€“ {$appliedCoupon['discount_percentage']}% off" : '' ?></span>
        <button class="coupon-remove" onclick="removeCoupon()" title="Remove coupon">Ã—</button>
      </div>
      <div id="coupon-form-row" style="display:<?= $appliedCoupon ? 'none' : 'flex' ?>" class="coupon-row">
        <input type="text" id="coupon-input" class="coupon-input" placeholder="Enter coupon code"
               value="<?= htmlspecialchars($appliedCoupon ? $appliedCoupon['code'] : '') ?>">
        <button class="coupon-btn" onclick="applyCoupon()">Apply</button>
      </div>
    </div>

    <?php if ($isLoggedIn && $totalWalletPoints > 0): ?>
    <!-- â”€â”€ Wallet Points â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div class="co-card">
      <div class="co-card-title">Wallet Points</div>
      <div style="display:flex;justify-content:space-between;font-size:13px;color:#555;margin-bottom:6px">
        <span>Available: â‚¹<?= number_format($totalWalletPoints, 2) ?></span>
        <span>Using: <strong id="wallet-using">â‚¹0</strong></span>
      </div>
      <input type="range" id="wallet-slider" class="wallet-slider"
             min="0" max="<?= $totalWalletPoints ?>" value="0" step="1"
             oninput="updateWalletDisplay(this.value)">
    </div>
    <?php endif; ?>

    <!-- â”€â”€ Payment Method â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div class="co-card">
      <div class="co-card-title">Payment Method</div>

      <label class="pay-option selected" id="opt-online" onclick="selectPayment('online')">
        <input type="radio" name="pay_method" value="online" checked>
        <div class="pay-option-label">
          <div class="pay-option-title">Pay Online</div>
          <div class="pay-option-sub">Cards Â· UPI Â· Netbanking Â· Wallets</div>
        </div>
        <img src="https://razorpay.com/favicon.ico" width="20" height="20" alt="Razorpay" style="border-radius:4px;opacity:.8">
      </label>

      <label class="pay-option" id="opt-cod" onclick="selectPayment('cod')">
        <input type="radio" name="pay_method" value="cod">
        <div class="pay-option-label">
          <div class="pay-option-title">Cash on Delivery</div>
          <div class="pay-option-sub">Pay when your order arrives</div>
        </div>
        <span class="pay-option-badge">Available</span>
      </label>
    </div>

    <!-- Pay button (desktop only â€” mobile uses sticky bar) -->
    <button class="pay-cta d-none d-lg-flex" id="pay-btn-desktop" onclick="handleCheckout()">
      <div class="spinner"></div>
      <span class="btn-text">
        <i class="fas fa-lock" style="font-size:13px"></i>
        Pay <?= htmlspecialchars($currencySymbol) ?><span id="pay-amount-desktop"><?= number_format($finalTotalBeforePoints, 2) ?></span>
      </span>
    </button>
    <div class="trust-row d-none d-lg-flex">
      <i class="fas fa-shield-alt"></i> SSL Secured &nbsp;Â·&nbsp;
      <i class="fas fa-lock"></i> 100% Safe Payments
    </div>
  </div>

  <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       RIGHT COLUMN  â€”  Order Summary
  â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
  <div class="summary-sticky">
    <div class="co-card">
      <div class="co-card-title">Order Summary</div>

      <!-- Items -->
      <?php foreach ($cartItems as $item):
        $img = !empty($item['primary_image'])
          ? 'uploads/products/' . htmlspecialchars($item['primary_image'])
          : 'assets/images/placeholder.png';
      ?>
      <div class="item-row">
        <img src="<?= $img ?>" class="item-img" alt="<?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? '') ?>">
        <div class="item-info">
          <div class="item-name"><?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? '') ?></div>
          <div class="item-meta">
            <?= !empty($item['size']) ? 'Size: ' . htmlspecialchars($item['size']) . ' Â· ' : '' ?>Qty: <?= (int)$item['quantity'] ?>
          </div>
        </div>
        <div class="item-price"><?= htmlspecialchars($currencySymbol) ?><?= number_format((float)($item['total_price'] ?? $item['product_price'] ?? 0), 2) ?></div>
      </div>
      <?php endforeach; ?>

      <hr class="tot-divider">

      <!-- Totals -->
      <div class="tot-row">
        <span>Subtotal</span>
        <span><?= htmlspecialchars($currencySymbol) ?><?= number_format($totalAmount, 2) ?></span>
      </div>
      <div class="tot-row">
        <span>Shipping</span>
        <span id="shipping-display" class="<?= $shippingCost <= 0 ? 'green' : '' ?>">
          <?= $shippingCost > 0 ? htmlspecialchars($currencySymbol) . number_format($shippingCost, 2) : 'FREE' ?>
        </span>
      </div>
      <div class="tot-row red" id="coupon-row" style="display:<?= $couponDiscount > 0 ? 'flex' : 'none' ?>">
        <span id="coupon-label">Coupon</span>
        <span>â€“<?= htmlspecialchars($currencySymbol) ?><span id="coupon-discount-val"><?= number_format($couponDiscount, 2) ?></span></span>
      </div>
      <div class="tot-row green" id="wallet-row" style="display:none">
        <span>Wallet Points</span>
        <span>â€“<?= htmlspecialchars($currencySymbol) ?><span id="wallet-discount-val">0.00</span></span>
      </div>

      <hr class="tot-divider">

      <div class="tot-final">
        <span>Total</span>
        <span id="grand-total-display"><?= htmlspecialchars($currencySymbol) ?><?= number_format($finalTotalBeforePoints, 2) ?></span>
      </div>

      <!-- Pay button (sidebar) -->
      <button class="pay-cta d-lg-none" id="pay-btn-sidebar" onclick="handleCheckout()">
        <div class="spinner"></div>
        <span class="btn-text">
          <i class="fas fa-lock" style="font-size:13px"></i>
          Pay <?= htmlspecialchars($currencySymbol) ?><span id="pay-amount-sidebar"><?= number_format($finalTotalBeforePoints, 2) ?></span>
        </span>
      </button>
      <div class="trust-row">
        <i class="fas fa-shield-alt"></i> Secured by Razorpay &nbsp;Â·&nbsp; SSL
      </div>
    </div>
  </div>

</div><!-- /.co-wrap -->

<!-- â”€â”€â”€ Mobile sticky pay bar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="mob-pay-bar">
  <button class="mob-pay-cta" id="pay-btn-mob" onclick="handleCheckout()">
    <i class="fas fa-lock" style="font-size:13px"></i>
    Pay <?= htmlspecialchars($currencySymbol) ?><span id="pay-amount-mob"><?= number_format($finalTotalBeforePoints, 2) ?></span>
  </button>
</div>

<!-- â”€â”€â”€ COD Address Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal-overlay" id="cod-modal">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Delivery Address</span>
      <button class="modal-close" onclick="closeCODModal()">Ã—</button>
    </div>
    <div class="mf-row">
      <div class="mf-group">
        <label class="mf-label">First Name *</label>
        <input type="text" class="mf-input" id="cod-fname" value="<?= htmlspecialchars($fillFirst) ?>" placeholder="First name">
      </div>
      <div class="mf-group">
        <label class="mf-label">Last Name *</label>
        <input type="text" class="mf-input" id="cod-lname" value="<?= htmlspecialchars($fillLast) ?>" placeholder="Last name">
      </div>
    </div>
    <div class="mf-group">
      <label class="mf-label">Email *</label>
      <input type="email" class="mf-input" id="cod-email" value="<?= htmlspecialchars($fillEmail) ?>" placeholder="your@email.com">
    </div>
    <div class="mf-group">
      <label class="mf-label">Phone *</label>
      <input type="tel" class="mf-input" id="cod-phone" value="<?= htmlspecialchars($fillPhone) ?>" placeholder="10-digit mobile number" maxlength="10" inputmode="numeric">
    </div>
    <div class="mf-group">
      <label class="mf-label">Address *</label>
      <input type="text" class="mf-input" id="cod-address" value="<?= htmlspecialchars($fillAddr1) ?>" placeholder="Street address, house number">
    </div>
    <div class="mf-group">
      <label class="mf-label">Apartment / Flat (optional)</label>
      <input type="text" class="mf-input" id="cod-apt" value="<?= htmlspecialchars($fillApt) ?>" placeholder="Flat / floor / building">
    </div>
    <div class="mf-row">
      <div class="mf-group">
        <label class="mf-label">City *</label>
        <input type="text" class="mf-input" id="cod-city" value="<?= htmlspecialchars($fillCity) ?>" placeholder="City">
      </div>
      <div class="mf-group">
        <label class="mf-label">Pincode *</label>
        <input type="text" class="mf-input" id="cod-pin" value="<?= htmlspecialchars($fillPin) ?>" placeholder="6-digit PIN" maxlength="6" inputmode="numeric">
      </div>
    </div>
    <div class="mf-group">
      <label class="mf-label">State *</label>
      <select class="mf-input mf-select" id="cod-state">
        <?php
        $states=['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Andaman and Nicobar Islands','Chandigarh','Dadra and Nagar Haveli and Daman and Diu','Delhi','Jammu and Kashmir','Ladakh','Lakshadweep','Puducherry'];
        foreach($states as $st): $sel = ($fillState===$st)?'selected':''; ?>
        <option value="<?= htmlspecialchars($st) ?>" <?= $sel ?>><?= htmlspecialchars($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mf-group">
      <label class="mf-label">Referral Code (optional)</label>
      <input type="text" class="mf-input" id="cod-referral" placeholder="Enter referral code" style="text-transform:uppercase">
    </div>
    <button class="cod-btn" id="cod-submit-btn" onclick="placeCODOrder()">
      Place Order Â· <?= htmlspecialchars($currencySymbol) ?><span id="cod-total-display"><?= number_format($finalTotalBeforePoints, 2) ?></span>
    </button>
  </div>
</div>

<!-- â”€â”€â”€ Error toast â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="err-toast" id="err-toast"></div>

<!-- â”€â”€â”€ Razorpay Magic Checkout SDK â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<!-- magic-checkout.js provides phone-first OTP, saved addresses, network autofill -->
<!-- Requires activation: contact Razorpay support/SPOC to enable for your account -->
<!-- Falls back to standard checkout behaviour if not yet activated -->
<script src="https://checkout.razorpay.com/v1/magic-checkout.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   CHECKOUT STATE
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
var STATE = {
  subtotal:      <?= json_encode($totalAmount) ?>,
  shipping:      <?= json_encode($shippingCost) ?>,
  couponDisc:    <?= json_encode($couponDiscount) ?>,
  couponCode:    <?= json_encode($appliedCoupon ? $appliedCoupon['code'] : '') ?>,
  walletPoints:  0,
  maxWallet:     <?= json_encode($totalWalletPoints ?? 0) ?>,
  payMethod:     'online',
  razorpayKeyId: <?= json_encode($razorpayKeyId) ?>,
  siteName:      <?= json_encode($siteName) ?>,
  currency:      'INR',
  symbol:        <?= json_encode($currencySymbol) ?>,
  prefillPhone:  <?= json_encode($fillPhone) ?>,
  prefillName:   <?= json_encode($fillName)  ?>,
  prefillEmail:  <?= json_encode($fillEmail) ?>,
};

/* â”€â”€ Computed total â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function getTotal() {
  var t = STATE.subtotal + STATE.shipping - STATE.couponDisc - STATE.walletPoints;
  return Math.max(0, parseFloat(t.toFixed(2)));
}

function refreshTotalUI() {
  var t = getTotal();
  var fmt = t.toFixed(2);
  document.querySelectorAll('#pay-amount-desktop,#pay-amount-sidebar,#pay-amount-mob,#cod-total-display').forEach(function(el){ if(el) el.textContent = fmt; });
  var gtEl = document.getElementById('grand-total-display');
  if (gtEl) gtEl.textContent = STATE.symbol + fmt;
}

/* â”€â”€ Wallet slider â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function updateWalletDisplay(val) {
  val = parseFloat(val) || 0;
  STATE.walletPoints = val;
  var wuEl = document.getElementById('wallet-using');
  if (wuEl) wuEl.textContent = STATE.symbol + val.toFixed(2);
  var wvEl = document.getElementById('wallet-discount-val');
  if (wvEl) wvEl.textContent = val.toFixed(2);
  var wr = document.getElementById('wallet-row');
  if (wr) wr.style.display = val > 0 ? 'flex' : 'none';
  refreshTotalUI();
}

/* â”€â”€ Phone customer lookup â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function lookupCustomer() {
  var phone = document.getElementById('co-phone').value.replace(/\D/g,'');
  if (!/^[6-9]\d{9}$/.test(phone)) { showError('Enter a valid 10-digit mobile number first.'); return; }
  var btn = document.getElementById('lookup-btn');
  btn.textContent = '...'; btn.disabled = true;
  fetch('shop/api/customer-lookup.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({phone: phone})
  })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    btn.textContent = 'Use saved details'; btn.disabled = false;
    if (data.success && data.found && data.user) {
      var u = data.user;
      STATE.prefillPhone = phone;
      STATE.prefillName  = (u.full_name || u.name || '').trim();
      STATE.prefillEmail = u.email || '';
      // Pre-fill COD form too
      setVal('cod-fname', STATE.prefillName.split(' ')[0]);
      setVal('cod-lname', STATE.prefillName.split(' ').slice(1).join(' '));
      setVal('cod-email', STATE.prefillEmail);
      setVal('cod-phone', phone);
      if (u.address)  setVal('cod-address', u.address);
      if (u.city)     setVal('cod-city',    u.city);
      if (u.pincode)  setVal('cod-pin',     u.pincode);
      if (u.state) { var ss = document.getElementById('cod-state'); if(ss) ss.value = u.state; }
      var firstName = STATE.prefillName.split(' ')[0];
      var wel = document.getElementById('phone-welcome');
      if (wel) {
        wel.innerHTML = 'ðŸ‘‹ Welcome back, <strong>' + firstName + '</strong>! Your saved details are ready.';
        wel.style.display = 'block';
      }
      // Silent cart merge if guest has items
      if ((parseInt(data.guest_cart_count)||0) > 0) {
        fetch('shop/api/cart.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'merge_guest'})}).catch(function(){});
      }
    } else if (data.success && !data.found) {
      var wel = document.getElementById('phone-welcome');
      if (wel) { wel.innerHTML = 'New customer â€“ please fill your details in the form below.'; wel.style.display = 'block'; }
    }
  })
  .catch(function(){ btn.textContent = 'Use saved details'; btn.disabled = false; });
}

/* â”€â”€ Coupon â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function applyCoupon() {
  var code = (document.getElementById('coupon-input').value || '').trim().toUpperCase();
  if (!code) { showError('Enter a coupon code.'); return; }
  fetch('checkout.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ajax_action:'apply_coupon', coupon_code: code})
  })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    if (data.success) {
      STATE.couponDisc = parseFloat(data.discount_amount) || 0;
      STATE.couponCode = data.coupon_code;
      document.getElementById('coupon-form-row').style.display = 'none';
      var banner = document.getElementById('coupon-applied-banner');
      document.getElementById('coupon-applied-text').innerHTML =
        'Coupon <strong>' + data.coupon_code + '</strong> applied â€“ ' + data.discount_percent + '% off';
      banner.style.display = 'flex';
      document.getElementById('coupon-row').style.display = 'flex';
      document.getElementById('coupon-discount-val').textContent = STATE.couponDisc.toFixed(2);
      refreshTotalUI();
    } else { showError(data.message || 'Could not apply coupon.'); }
  })
  .catch(function(){ showError('Network error. Try again.'); });
}

function removeCoupon() {
  fetch('checkout.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ajax_action:'remove_coupon'})}).catch(function(){});
  STATE.couponDisc = 0; STATE.couponCode = '';
  document.getElementById('coupon-applied-banner').style.display = 'none';
  document.getElementById('coupon-form-row').style.display = 'flex';
  document.getElementById('coupon-input').value = '';
  document.getElementById('coupon-row').style.display = 'none';
  refreshTotalUI();
}

/* â”€â”€ Payment method toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function selectPayment(method) {
  STATE.payMethod = method;
  document.getElementById('opt-online').classList.toggle('selected', method==='online');
  document.getElementById('opt-cod').classList.toggle('selected', method==='cod');
  document.querySelector('#opt-online input').checked = method==='online';
  document.querySelector('#opt-cod input').checked = method==='cod';
}

/* â”€â”€ Main checkout handler â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function handleCheckout() {
  if (STATE.payMethod === 'cod') {
    openCODModal();
  } else {
    initiateRazorpayCheckout();
  }
}

/* â”€â”€ Razorpay flow â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
async function initiateRazorpayCheckout() {
  setAllBtnsLoading(true);
  var phone = document.getElementById('co-phone').value.replace(/\D/g,'');

  try {
    var res = await fetch('checkout.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        ajax_action:    'create_razorpay_order',
        phone:          phone || STATE.prefillPhone,
        name:           STATE.prefillName,
        email:          STATE.prefillEmail,
        points_to_use:  STATE.walletPoints,
        referral_code:  '',
      })
    });
    var data = await res.json();
    setAllBtnsLoading(false);

    if (!data.success) { showError(data.message || 'Could not create order.'); return; }

    var options = {
      key:             data.key_id,
      amount:          data.amount,
      currency:        data.currency,
      name:            data.site_name || STATE.siteName,
      description:     'Order Payment',
      order_id:        data.order_id,
      remember_customer: true,
      prefill: {
        contact: data.prefill.contact || STATE.prefillPhone,
        name:    data.prefill.name    || STATE.prefillName,
        email:   data.prefill.email   || STATE.prefillEmail,
      },
      theme:  { color: '#004AAD' },
      modal:  { confirm_close: true, escape: false },
      handler: async function(response) {
        setAllBtnsLoading(true);
        try {
          var vres = await fetch('checkout.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
              ajax_action:          'process_payment',
              razorpay_payment_id:  response.razorpay_payment_id,
              razorpay_order_id:    response.razorpay_order_id,
              razorpay_signature:   response.razorpay_signature,
              phone:                phone || STATE.prefillPhone,
            })
          });
          var vdata = await vres.json();
          if (vdata.success) {
            window.location.href = 'checkout.php?order_success=1&oid=' + vdata.order_id +
              '&onum=' + encodeURIComponent(vdata.order_number) +
              '&oprice=' + vdata.final_price + '&omethod=razorpay';
          } else {
            setAllBtnsLoading(false);
            showError(vdata.message || 'Payment could not be confirmed. Contact support.');
          }
        } catch(e) { setAllBtnsLoading(false); showError('Network error. Contact support.'); }
      }
    };
    // Pass customer_id if we have it (enables Magic Checkout network autofill)
    if (data.customer_id) { options.customer_id = data.customer_id; }

    var rzp = new Razorpay(options);
    rzp.on('payment.failed', function(r){
      setAllBtnsLoading(false);
      showError('Payment failed: ' + (r.error.description || 'Please try again.'));
    });
    rzp.open();

  } catch(e) { setAllBtnsLoading(false); showError('Network error. Please try again.'); }
}

/* â”€â”€ COD modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function openCODModal() {
  // Pre-fill phone from checkout form
  var phone = document.getElementById('co-phone').value.replace(/\D/g,'');
  if (phone) setVal('cod-phone', phone);
  document.getElementById('cod-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeCODModal() {
  document.getElementById('cod-modal').classList.remove('open');
  document.body.style.overflow = '';
}

async function placeCODOrder() {
  var fd = {
    first_name: document.getElementById('cod-fname').value.trim(),
    last_name:  document.getElementById('cod-lname').value.trim(),
    email:      document.getElementById('cod-email').value.trim(),
    phone:      document.getElementById('cod-phone').value.replace(/\D/g,''),
    address:    document.getElementById('cod-address').value.trim(),
    apartment:  document.getElementById('cod-apt').value.trim(),
    city:       document.getElementById('cod-city').value.trim(),
    state:      document.getElementById('cod-state').value,
    pincode:    document.getElementById('cod-pin').value.replace(/\D/g,''),
    points_to_use: STATE.walletPoints,
    referral_code: document.getElementById('cod-referral').value.trim().toUpperCase(),
  };
  var errs = [];
  if (!fd.first_name)                     errs.push('First name');
  if (!fd.email || !/\S+@\S+\.\S+/.test(fd.email)) errs.push('Valid email');
  if (!/^[6-9]\d{9}$/.test(fd.phone))    errs.push('Valid 10-digit phone');
  if (!fd.address)                         errs.push('Address');
  if (!fd.city)                            errs.push('City');
  if (!/^\d{6}$/.test(fd.pincode))        errs.push('6-digit pincode');
  if (errs.length) { showError('Please fill: ' + errs.join(', ')); return; }

  var btn = document.getElementById('cod-submit-btn');
  btn.disabled = true; btn.textContent = 'Placing orderâ€¦';

  try {
    var res  = await fetch('checkout.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({ajax_action:'process_cod'},fd))});
    var data = await res.json();
    if (data.success) {
      window.location.href = 'checkout.php?order_success=1&oid=' + data.order_id +
        '&onum=' + encodeURIComponent(data.order_number) +
        '&oprice=' + data.final_price + '&omethod=cod';
    } else {
      btn.disabled = false; btn.textContent = 'Place Order Â· ' + STATE.symbol + getTotal().toFixed(2);
      showError(data.message || 'Order could not be placed. Please try again.');
    }
  } catch(e) { btn.disabled=false; btn.textContent='Place Order'; showError('Network error.'); }
}

/* â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function setAllBtnsLoading(on) {
  ['pay-btn-desktop','pay-btn-sidebar','pay-btn-mob'].forEach(function(id){
    var el = document.getElementById(id);
    if (!el) return;
    el.disabled = on;
    el.classList.toggle('loading', on);
  });
}
function setVal(id, val) { var el=document.getElementById(id); if(el && val!=null) el.value=val; }
function showError(msg) {
  var t = document.getElementById('err-toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(function(){ t.classList.remove('show'); }, 4000);
}

/* â”€â”€ Close COD modal on overlay click â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
document.getElementById('cod-modal').addEventListener('click', function(e){
  if (e.target === this) closeCODModal();
});

/* â”€â”€ Phone input: digits only â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
document.getElementById('co-phone').addEventListener('input', function(){
  this.value = this.value.replace(/\D/g,'').slice(0,10);
});

/* â”€â”€ Allow Enter on phone to trigger lookup â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
document.getElementById('co-phone').addEventListener('keydown', function(e){
  if (e.key === 'Enter') { e.preventDefault(); lookupCustomer(); }
});

/* â”€â”€ Init: if phone already pre-filled (logged-in user), run lookup â”€â”€â”€â”€â”€â”€â”€â”€ */
document.addEventListener('DOMContentLoaded', function(){
  refreshTotalUI();
  var phone = document.getElementById('co-phone').value.trim();
  if (/^[6-9]\d{9}$/.test(phone)) {
    // Silently pre-fill COD form from server-side data
    setVal('cod-phone', phone);
    setVal('cod-fname', <?= json_encode($fillFirst) ?>);
    setVal('cod-lname', <?= json_encode($fillLast)  ?>);
    setVal('cod-email', <?= json_encode($fillEmail) ?>);
    setVal('cod-address', <?= json_encode($fillAddr1) ?>);
    setVal('cod-city',  <?= json_encode($fillCity)  ?>);
    setVal('cod-pin',   <?= json_encode($fillPin)   ?>);
    var ss = document.getElementById('cod-state');
    if (ss && <?= json_encode($fillState) ?>) ss.value = <?= json_encode($fillState) ?>;
    STATE.prefillPhone = phone;
    STATE.prefillName  = <?= json_encode($fillName)  ?>;
    STATE.prefillEmail = <?= json_encode($fillEmail) ?>;
  }
});
</script>

</body>
</html>

