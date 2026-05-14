<?php
session_start();
require_once 'includes/database.php';
require_once 'includes/functions.php';

$siteName    = getSetting('site_name', 'bluefifth');
$currencySymbol = getSetting('currency_symbol', '₹');

$order       = null;
$tracking    = null;
$error       = '';

// Pre-fill from GET params (e.g. linked from email or profile)
$preOrderNum = htmlspecialchars($_GET['order'] ?? '');

// Handle lookup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNum = trim($_POST['order_number'] ?? '');
    $phone    = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
    // Strip leading 91 country code
    if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
        $phone = substr($phone, 2);
    }

    if (empty($orderNum) || empty($phone)) {
        $error = 'Please enter both your order number and phone number.';
    } else {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("
                SELECT o.*, u.phone AS user_phone
                FROM   orders o
                JOIN   users  u ON u.id = o.user_id
                WHERE  o.order_number = ?
                LIMIT  1
            ");
            $stmt->execute([$orderNum]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $error = 'Order not found. Please check your order number.';
            } elseif ($row['user_phone'] !== $phone) {
                $error = 'Phone number does not match this order.';
            } else {
                $order = $row;

                // Load order items
                $itemStmt = $conn->prepare("
                    SELECT oi.*, p.name AS product_name, p.image AS product_image
                    FROM   order_items oi
                    LEFT   JOIN products p ON p.id = oi.product_id
                    WHERE  oi.order_id = ?
                ");
                $itemStmt->execute([$order['id']]);
                $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

                // Load tracking events from order_tracking table
                try {
                    $trkStmt = $conn->prepare("
                        SELECT status, description, location, courier_update_time, created_at
                        FROM   order_tracking
                        WHERE  order_id = ?
                        ORDER  BY id DESC
                    ");
                    $trkStmt->execute([$order['id']]);
                    $trackingEvents = $trkStmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($trackingEvents) {
                        $tracking = ['events' => array_map(function($e) {
                            return [
                                'date'     => date('d M Y H:i', strtotime($e['courier_update_time'] ?: $e['created_at'])),
                                'activity' => ucwords(str_replace('_', ' ', $e['status'])) . ($e['description'] ? ': ' . $e['description'] : ''),
                                'location' => $e['location'] ?? '',
                            ];
                        }, $trackingEvents)];
                    }
                } catch (Exception $e) {
                    // non-fatal
                }
            }
        } catch (Exception $e) {
            error_log('track.php error: ' . $e->getMessage());
            $error = 'Could not fetch order details. Please try again.';
        }
    }
}

// Map order status to a display step (1-6)
function statusToStep(string $status): int {
    return match (strtolower($status)) {
        'pending', 'processing'      => 1,
        'confirmed'                  => 2,
        'packed'                     => 3,
        'shipped', 'in_transit'      => 4,
        'out_for_delivery'           => 5,
        'delivered'                  => 6,
        default                      => 1,
    };
}

$steps = [
    1 => ['label' => 'Order Confirmed',     'icon' => '✅'],
    2 => ['label' => 'Preparing',           'icon' => '📦'],
    3 => ['label' => 'Packed',              'icon' => '🎁'],
    4 => ['label' => 'Shipped',             'icon' => '🚚'],
    5 => ['label' => 'Out for Delivery',    'icon' => '🛵'],
    6 => ['label' => 'Delivered',           'icon' => '🏠'],
];

$currentStep = $order ? statusToStep($order['status']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track Your Order — <?= htmlspecialchars($siteName) ?></title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    body { background: #f8f8f8; font-family: 'Poppins', sans-serif; }
    .track-wrap { max-width: 680px; margin: 40px auto; padding: 0 16px 60px; }
    .brand-link { display: block; text-align: center; margin-bottom: 32px; }
    .brand-link img { max-height: 48px; }
    .card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 20px; }
    .card-header { background: linear-gradient(to right, #6C803F, #879D60); color: #fff; border-radius: 12px 12px 0 0 !important; padding: 18px 24px; }
    .card-header h4 { margin: 0; font-weight: 700; font-size: 18px; }
    .form-control:focus { border-color: #6C803F; box-shadow: 0 0 0 .2rem rgba(108,128,63,.2); }
    .btn-track { background: #6C803F; color: #fff; border: none; padding: 12px 32px; font-weight: 600; border-radius: 8px; width: 100%; font-size: 16px; }
    .btn-track:hover { background: #5a6e33; color: #fff; }

    /* Progress bar */
    .track-steps { display: flex; align-items: flex-start; justify-content: space-between; margin: 28px 0; position: relative; }
    .track-steps::before { content: ''; position: absolute; top: 22px; left: 0; right: 0; height: 3px; background: #e0e0e0; z-index: 0; }
    .track-step { flex: 1; text-align: center; position: relative; z-index: 1; }
    .track-step-circle { width: 44px; height: 44px; border-radius: 50%; background: #e0e0e0; color: #888; display: flex; align-items: center; justify-content: center; font-size: 18px; margin: 0 auto 6px; border: 3px solid #fff; box-shadow: 0 0 0 2px #e0e0e0; transition: all .3s; }
    .track-step.done   .track-step-circle { background: #879D60; color: #fff; box-shadow: 0 0 0 2px #879D60; }
    .track-step.active .track-step-circle { background: #6C803F; color: #fff; box-shadow: 0 0 0 3px #6C803F, 0 0 0 5px rgba(108,128,63,.2); }
    .track-step-label { font-size: 10px; color: #999; line-height: 1.3; }
    .track-step.done   .track-step-label { color: #6C803F; }
    .track-step.active .track-step-label { color: #6C803F; font-weight: 600; }
    .track-progress-line { position: absolute; top: 22px; left: 0; height: 3px; background: #879D60; z-index: 0; transition: width .5s ease; }

    /* Order info */
    .order-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
    .order-meta-item { background: #f0f4e8; border-radius: 8px; padding: 10px 16px; font-size: 13px; }
    .order-meta-item strong { display: block; font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
    .order-meta-item span { font-weight: 600; color: #333; }
    .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .status-pending     { background: #fff3cd; color: #856404; }
    .status-processing  { background: #cce5ff; color: #004085; }
    .status-confirmed   { background: #d4edda; color: #155724; }
    .status-shipped     { background: #d1ecf1; color: #0c5460; }
    .status-delivered   { background: #d4edda; color: #155724; }
    .status-cancelled   { background: #f8d7da; color: #721c24; }

    /* Item list */
    .order-item-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .order-item-row:last-child { border-bottom: none; }
    .order-item-img { width: 52px; height: 52px; object-fit: cover; border-radius: 8px; background: #f0f0f0; flex-shrink: 0; }
    .order-item-name { flex: 1; font-size: 14px; font-weight: 500; }
    .order-item-qty  { color: #888; font-size: 13px; }
    .order-item-price { font-weight: 600; white-space: nowrap; }

    @media (max-width: 480px) {
      .track-step-label { font-size: 9px; }
      .track-step-circle { width: 36px; height: 36px; font-size: 15px; }
      .track-steps::before { top: 18px; }
      .track-progress-line { top: 18px; }
    }
  </style>
</head>
<body>
<div class="track-wrap">

  <a href="index.php" class="brand-link">
    <img src="assets/images/logo2.jpg" alt="<?= htmlspecialchars($siteName) ?>">
  </a>

  <!-- Lookup form -->
  <div class="card">
    <div class="card-header"><h4>Track Your Order</h4></div>
    <div class="card-body p-4">
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label for="order_number"><strong>Order Number</strong></label>
          <input type="text" name="order_number" id="order_number" class="form-control"
                 placeholder="e.g. ORD-20240001"
                 value="<?= htmlspecialchars($_POST['order_number'] ?? $preOrderNum) ?>" required>
        </div>
        <div class="form-group">
          <label for="phone"><strong>Phone Number</strong></label>
          <input type="tel" name="phone" id="phone" class="form-control"
                 placeholder="10-digit mobile number"
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                 inputmode="numeric" maxlength="12" required>
        </div>
        <button type="submit" class="btn-track">Track Order</button>
      </form>
    </div>
  </div>

  <?php if ($order): ?>

  <!-- Progress tracker -->
  <div class="card">
    <div class="card-header">
      <h4>Order <?= htmlspecialchars($order['order_number']) ?></h4>
    </div>
    <div class="card-body p-4">

      <!-- Meta row -->
      <div class="order-meta">
        <div class="order-meta-item">
          <strong>Date</strong>
          <span><?= date('d M Y', strtotime($order['created_at'])) ?></span>
        </div>
        <div class="order-meta-item">
          <strong>Amount</strong>
          <span><?= $currencySymbol ?><?= number_format($order['final_amount'], 2) ?></span>
        </div>
        <div class="order-meta-item">
          <strong>Payment</strong>
          <span><?= ucfirst($order['payment_method'] ?? 'N/A') ?></span>
        </div>
        <div class="order-meta-item">
          <strong>Status</strong>
          <span class="status-badge status-<?= strtolower($order['status']) ?>">
            <?= ucwords(str_replace('_', ' ', $order['status'])) ?>
          </span>
        </div>
        <?php if (!empty($order['tracking_number'])): ?>
        <div class="order-meta-item">
          <strong>Tracking No.</strong>
          <span><?= htmlspecialchars($order['tracking_number']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($order['courier_partner'])): ?>
        <div class="order-meta-item">
          <strong>Courier</strong>
          <span><?= htmlspecialchars($order['courier_partner']) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Step progress bar -->
      <div class="track-steps" id="track-steps">
        <?php
        $totalSteps = count($steps);
        foreach ($steps as $stepNum => $step):
            $cls = '';
            if ($stepNum < $currentStep)  $cls = 'done';
            if ($stepNum === $currentStep) $cls = 'active';
        ?>
        <div class="track-step <?= $cls ?>">
          <div class="track-step-circle"><?= $step['icon'] ?></div>
          <div class="track-step-label"><?= htmlspecialchars($step['label']) ?></div>
        </div>
        <?php endforeach; ?>
        <!-- Progress fill line -->
        <div class="track-progress-line" id="progress-line"></div>
      </div>

      <?php if ($order['status'] === 'cancelled'): ?>
      <div class="alert alert-danger mt-2">This order has been cancelled.</div>
      <?php elseif ($order['status'] === 'delivered'): ?>
      <div class="alert alert-success mt-2">Your order has been delivered. Thank you for shopping with <?= htmlspecialchars($siteName) ?>!</div>
      <?php elseif (!empty($order['estimated_delivery'])): ?>
      <p class="text-muted mt-2" style="font-size:13px;">Estimated delivery: <strong><?= htmlspecialchars($order['estimated_delivery']) ?></strong></p>
      <?php endif; ?>

      <!-- Shiprocket live tracking events -->
      <?php if (!empty($tracking['events'])): ?>
      <h6 class="mt-4 mb-3" style="font-weight:700;">Tracking Updates</h6>
      <div style="max-height:240px; overflow-y:auto;">
        <?php foreach ($tracking['events'] as $event): ?>
        <div style="display:flex; gap:12px; padding:8px 0; border-bottom:1px solid #f0f0f0; font-size:13px;">
          <div style="white-space:nowrap; color:#888;"><?= htmlspecialchars($event['date'] ?? '') ?></div>
          <div><?= htmlspecialchars($event['activity'] ?? $event['status'] ?? '') ?><br>
            <?php if (!empty($event['location'])): ?>
            <small class="text-muted"><?= htmlspecialchars($event['location']) ?></small>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Order items -->
  <div class="card">
    <div class="card-header"><h4 style="font-size:15px;">Items in this Order</h4></div>
    <div class="card-body p-4">
      <?php foreach ($order['items'] as $item): ?>
      <div class="order-item-row">
        <img src="<?= htmlspecialchars($item['product_image'] ?? 'https://via.placeholder.com/52?text=P') ?>"
             alt="<?= htmlspecialchars($item['product_name'] ?? 'Product') ?>"
             class="order-item-img">
        <div class="order-item-name">
          <?= htmlspecialchars($item['product_name'] ?? 'Product') ?>
          <?php if (!empty($item['size'])): ?>
          <small class="text-muted d-block">Size: <?= htmlspecialchars($item['size']) ?></small>
          <?php endif; ?>
        </div>
        <span class="order-item-qty">×<?= (int)$item['quantity'] ?></span>
        <span class="order-item-price"><?= $currencySymbol ?><?= number_format(($item['total_price'] ?? ($item['product_price'] * $item['quantity'])), 2) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php endif; ?>

  <p class="text-center text-muted mt-4" style="font-size:13px;">
    Need help? Email <a href="mailto:<?= getSetting('admin_email', 'info@bluefifth.in') ?>"><?= getSetting('admin_email', 'info@bluefifth.in') ?></a>
  </p>

</div>

<script>
// Animate progress line to match current step
(function() {
    var steps = document.querySelectorAll('.track-step');
    var line  = document.getElementById('progress-line');
    var wrap  = document.getElementById('track-steps');
    if (!steps.length || !line || !wrap) return;

    var activeIdx = -1;
    steps.forEach(function(s, i) { if (s.classList.contains('active') || s.classList.contains('done')) activeIdx = i; });

    if (activeIdx < 0) { line.style.width = '0'; return; }

    var wrapRect   = wrap.getBoundingClientRect();
    var activeRect = steps[activeIdx].getBoundingClientRect();
    var centerX    = activeRect.left + activeRect.width / 2 - wrapRect.left;
    line.style.width = Math.max(0, centerX) + 'px';
}());
</script>
</body>
</html>
