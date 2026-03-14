<?php
session_start();
require_once '../includes/database.php';  
require_once '../includes/functions.php'; 
require_once 'admin-session.php';

// ONLY authentication check - Gateway control
requireAdminAuth();

// Get dashboard statistics
$stats = getEcommerceStats();
$recentOrders = getRecentOrders(5);
$topReferrers = getTopReferrers(5);

// Get monthly sales data for chart
$conn = getConnection();
$stmt = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as order_count,
        SUM(final_amount) as total_sales
    FROM orders 
    WHERE payment_status = 'paid' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
");
$monthlySales = $stmt->fetchAll();

// Get top selling products
$stmt = $conn->query("
    SELECT 
        p.name,
        p.price,
        SUM(oi.quantity) as total_sold,
        SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.payment_status = 'paid'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$topProducts = $stmt->fetchAll();

// Get order status breakdown
$stmt = $conn->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM orders
    GROUP BY status
");
$orderStatusBreakdown = $stmt->fetchAll();

// Calculate orders this month
$stmt = $conn->query("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$ordersThisMonth = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bluefifth Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="admin-styles.css" rel="stylesheet"></head>
<body>
    <!-- Sidebar -->
    <?php include 'admin-navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <h1 class="mb-4">Dashboard Overview</h1>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div>
                            <h6 class="text-light mb-1">Total Sales</h6>
                            <h3 class="mb-0">₹<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-success bg-opacity-10 text-success me-3">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <h6 class="text-light mb-1">Orders This Month</h6>
                            <h3 class="mb-0"><?php echo $ordersThisMonth; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-info bg-opacity-10 text-info me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h6 class="text-light mb-1">Total Customers</h6>
                            <h3 class="mb-0"><?php echo $stats['total_customers']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="fas fa-box"></i>
                        </div>
                        <div>
                            <h6 class=" text-light mb-1">Total Orders</h6>
                            <h3 class="mb-0"><?php echo $stats['total_orders']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Monthly Sales Chart -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Sales</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Status Breakdown -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-box me-2"></i>Order Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Top Selling Products -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Selling Products</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($topProducts as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo $product['total_sold']; ?></td>
                                        <td>₹<?php echo number_format($product['revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Orders</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recentOrders as $order): ?>
                                    <tr>
                                        <td><?php echo $order['order_number']; ?></td>
                                        <td><?php echo $order['customer_name'] ?? 'Guest'; ?></td>
                                        <td>₹<?php echo number_format($order['final_amount'], 2); ?></td>
                                        <td><span class="badge bg-<?php 
                                            echo $order['status'] == 'delivered' ? 'success' : 
                                                ($order['status'] == 'pending' ? 'warning' : 'info'); 
                                        ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Referrers (Optional) -->
        <?php if (!empty($topReferrers)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-share-alt me-2"></i>Top Referrers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Referral Code</th>
                                        <th>Total Sales</th>
                                        <th>Commission Earned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($topReferrers as $referrer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($referrer['user_name']); ?></td>
                                        <td><code><?php echo $referrer['code']; ?></code></td>
                                        <td><?php echo $referrer['purchase_count']; ?> orders</td>
                                        <td>₹<?php echo number_format($referrer['total_earnings'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?php echo json_encode(array_reverse($monthlySales)); ?>;
        
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(d => d.month),
                datasets: [{
                    label: 'Monthly Sales',
                    data: salesData.map(d => d.total_sales),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Order Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php echo json_encode($orderStatusBreakdown); ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
                datasets: [{
                    data: statusData.map(d => d.count),
                    backgroundColor: [
                        '#ffc107', // pending
                        '#17a2b8', // processing
                        '#6f42c1', // shipped
                        '#28a745', // delivered
                        '#dc3545', // cancelled
                        '#fd7e14'  // refunded
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Auto-refresh dashboard every 60 seconds
        setInterval(() => {
            location.reload();
        }, 60000);
    </script>

    <script>
// Fix active page indicators - UNIVERSAL SCRIPT FOR ALL ADMIN PAGES
document.addEventListener('DOMContentLoaded', function() {
    // Get current page filename
    const currentPage = window.location.pathname.split('/').pop();
    
    // Remove active class from all nav links
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.classList.remove('active');
    });
    
    // Add active class to current page link
    const activeLink = document.querySelector(`.sidebar .nav-link[href="${currentPage}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
    
    // Fallback: if no exact match, try to match by page name
    if (!activeLink) {
        let pageMatch = '';
        if (currentPage.includes('admin.php')) pageMatch = 'admin.php';
        else if (currentPage.includes('orders.php')) pageMatch = 'orders.php';
        else if (currentPage.includes('customers.php')) pageMatch = 'customers.php';
        else if (currentPage.includes('categories.php')) pageMatch = 'categories.php';
        else if (currentPage.includes('products.php')) pageMatch = 'products.php';
        else if (currentPage.includes('referral-dashboard.php')) pageMatch = 'referral-dashboard.php';
        else if (currentPage.includes('settings.php')) pageMatch = 'settings.php';
        
        if (pageMatch) {
            const fallbackLink = document.querySelector(`.sidebar .nav-link[href="${pageMatch}"]`);
            if (fallbackLink) {
                fallbackLink.classList.add('active');
            }
        }
    }
    
    // Also run this when sidebar is toggled (for responsive behavior)
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    // Re-apply active state when sidebar classes change
                    setTimeout(() => {
                        const currentActiveLink = document.querySelector(`.sidebar .nav-link[href="${currentPage}"]`) || 
                                                document.querySelector(`.sidebar .nav-link[href="${pageMatch}"]`);
                        if (currentActiveLink && !currentActiveLink.classList.contains('active')) {
                            // Remove all active states first
                            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                                link.classList.remove('active');
                            });
                            // Add active to current page
                            currentActiveLink.classList.add('active');
                        }
                    }, 100);
                }
            });
        });
        
        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class']
        });
    }
});

// Also fix active state when sidebar toggles on mobile
document.addEventListener('click', function(e) {
    if (e.target.closest('.mobile-toggle-btn') || e.target.closest('.toggle-sidebar-btn')) {
        setTimeout(() => {
            const currentPage = window.location.pathname.split('/').pop();
            let pageMatch = '';
            if (currentPage.includes('admin.php')) pageMatch = 'admin.php';
            else if (currentPage.includes('orders.php')) pageMatch = 'orders.php';
            else if (currentPage.includes('customers.php')) pageMatch = 'customers.php';
            else if (currentPage.includes('categories.php')) pageMatch = 'categories.php';
            else if (currentPage.includes('products.php')) pageMatch = 'products.php';
            else if (currentPage.includes('referral-dashboard.php')) pageMatch = 'referral-dashboard.php';
            else if (currentPage.includes('settings.php')) pageMatch = 'settings.php';
            
            const activeLink = document.querySelector(`.sidebar .nav-link[href="${currentPage}"]`) || 
                             document.querySelector(`.sidebar .nav-link[href="${pageMatch}"]`);
            
            if (activeLink) {
                // Remove all active states
                document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                    link.classList.remove('active');
                });
                // Add active to current page
                activeLink.classList.add('active');
            }
        }, 150);
    }
});
</script>

</body>
</html>