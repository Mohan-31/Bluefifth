<?php
// admin/orders.php - Order Management Interface
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once 'admin-session.php';
// ONLY authentication check - Gateway control
requireAdminAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Velona Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="admin-styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'admin-navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="header-title mb-1">Order Management</h1>
                <p class="text-muted">Track and manage all customer orders</p>
            </div>
            <button class="btn btn-primary" onclick="exportOrders()">
                <i class="fas fa-download me-2"></i>Export Orders
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                        <h3 class="mb-1" id="totalOrders">0</h3>
                        <p class="mb-0">Total Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <h3 class="mb-1" id="pendingOrders">0</h3>
                        <p class="mb-0">Pending Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h3 class="mb-1" id="deliveredOrders">0</h3>
                        <p class="mb-0">Delivered</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-rupee-sign fa-2x mb-2"></i>
                        <h3 class="mb-1" id="totalRevenue">₹0</h3>
                        <p class="mb-0">Total Revenue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Search Orders</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Order number, customer...">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter" onchange="filterOrders()">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Payment Status</label>
                        <select class="form-select" id="paymentFilter" onchange="filterOrders()">
                            <option value="">All Payments</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Date Range</label>
                        <select class="form-select" id="dateFilter" onchange="filterOrders()">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Show</label>
                        <select class="form-select" id="itemsPerPage" onchange="changeItemsPerPage()">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="col-md-1 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary w-100" onclick="searchOrders()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="card-body">
                <div class="loading" id="tableLoading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading orders...</p>
                </div>
                
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table table-hover" id="ordersTable" style="display: none;">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <!-- Orders will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Orders pagination" class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
                        <!-- Pagination will be loaded here -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsBody">
                    <!-- Order details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printOrder()">
                        <i class="fas fa-print me-2"></i>Print Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="updateStatusForm">
                    <input type="hidden" id="updateOrderId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Order Number</label>
                            <input type="text" class="form-control" id="updateOrderNumber" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <input type="text" class="form-control" id="currentStatus" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select class="form-select" id="newStatus" required>
                                <option value="">Select Status</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="statusNotes" rows="3" placeholder="Add notes about this status change..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentPage = 1;
        let itemsPerPage = 25;
        let totalPages = 1;
        let currentFilters = {};

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadOrders();
            loadStats();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            // Form submissions
            document.getElementById('updateStatusForm').addEventListener('submit', handleStatusUpdate);
            
            // Search on Enter key
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchOrders();
                }
            });
        }


        // Load orders with pagination and filters
        async function loadOrders() {
            showLoading(true);
            
            try {
                const params = new URLSearchParams({
                    action: 'get_orders',
                    page: currentPage,
                    per_page: itemsPerPage,
                    ...currentFilters
                });
                
                const response = await fetch(`api/manage-orders.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    displayOrders(data.orders);
                    updatePagination(data.pagination);
                    document.getElementById('ordersTable').style.display = 'table';
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading orders:', error);
                showAlert('Error loading orders', 'danger');
            } finally {
                showLoading(false);
            }
        }

        // Display orders in table
        function displayOrders(orders) {
            const tbody = document.getElementById('ordersTableBody');
            tbody.innerHTML = '';
            
            orders.forEach(order => {
                const row = document.createElement('tr');
                
                // Customer info
                const customerHtml = order.customer_name ? `
                    <div class="customer-info">
                        ${order.customer_profile_image 
                            ? `<img src="${order.customer_profile_image}" alt="Avatar" class="customer-avatar">`
                            : `<div class="customer-avatar placeholder">${order.customer_name.charAt(0).toUpperCase()}</div>`
                        }
                        <div>
                            <div class="fw-bold">${order.customer_name}</div>
                            <small class="text-muted">${order.customer_email}</small>
                        </div>
                    </div>
                ` : `
                    <div class="text-muted">
                        <i class="fas fa-user-slash me-1"></i>Guest Order
                    </div>
                `;
                
                // Items summary
                const itemsHtml = `
                    <div class="order-items">
                        <small class="text-muted">${order.item_count} item(s)</small>
                        <div class="mt-1">
                            <small>₹${parseFloat(order.total_amount).toFixed(2)}</small>
                            ${order.wallet_points_used > 0 ? `<br><small class="text-info">-₹${parseFloat(order.wallet_points_used).toFixed(2)} wallet</small>` : ''}
                        </div>
                    </div>
                `;
                
                row.innerHTML = `
                    <td>
                        <div class="fw-bold text-primary">${order.order_number}</div>
                        <small class="text-muted">#${order.id}</small>
                    </td>
                    <td>${customerHtml}</td>
                    <td>${itemsHtml}</td>
                    <td>
                        <div class="fw-bold">₹${parseFloat(order.final_amount).toFixed(2)}</div>
                        ${order.referral_code ? `<small class="text-success">Ref: ${order.referral_code}</small>` : ''}
                    </td>
                    <td>
                        <span class="status-badge payment-${order.payment_status}">
                            ${order.payment_status.toUpperCase()}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-${order.status}">
                            ${order.status.toUpperCase()}
                        </span>
                    </td>
                    <td>
                        <div>${new Date(order.created_at).toLocaleDateString()}</div>
                        <small class="text-muted">${new Date(order.created_at).toLocaleTimeString()}</small>
                    </td>
                    <td>
                        <button class="action-btn btn-view" onclick="viewOrder(${order.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn btn-edit" onclick="updateStatus(${order.id}, '${order.order_number}', '${order.status}')" title="Update Status">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Update pagination (same as products.php)
        function updatePagination(pagination) {
            currentPage = pagination.current_page;
            totalPages = pagination.total_pages;
            
            const paginationElement = document.getElementById('pagination');
            paginationElement.innerHTML = '';
            
            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>`;
            paginationElement.appendChild(prevLi);
            
            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const pageLi = document.createElement('li');
                pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
                pageLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${i})">${i}</a>`;
                paginationElement.appendChild(pageLi);
            }
            
            // Next button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>`;
            paginationElement.appendChild(nextLi);
        }

        // Change page
        function changePage(page) {
            if (page >= 1 && page <= totalPages && page !== currentPage) {
                currentPage = page;
                loadOrders();
            }
        }

        // Change items per page
        function changeItemsPerPage() {
            itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
            currentPage = 1;
            loadOrders();
        }

        // Search orders
        function searchOrders() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            if (searchTerm) {
                currentFilters.search = searchTerm;
            } else {
                delete currentFilters.search;
            }
            currentPage = 1;
            loadOrders();
        }

        // Filter orders
        function filterOrders() {
            const status = document.getElementById('statusFilter').value;
            const payment = document.getElementById('paymentFilter').value;
            const dateRange = document.getElementById('dateFilter').value;
            
            currentFilters = {};
            if (status) currentFilters.status = status;
            if (payment) currentFilters.payment_status = payment;
            if (dateRange && dateRange !== 'custom') currentFilters.date_range = dateRange;
            
            currentPage = 1;
            loadOrders();
        }

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('api/manage-orders.php?action=get_stats');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalOrders').textContent = data.stats.total_orders;
                    document.getElementById('pendingOrders').textContent = data.stats.pending_orders;
                    document.getElementById('deliveredOrders').textContent = data.stats.delivered_orders;
                    document.getElementById('totalRevenue').textContent = '₹' + parseFloat(data.stats.total_revenue).toLocaleString();
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // View order details
        async function viewOrder(orderId) {
            try {
                const response = await fetch(`api/manage-orders.php?action=get_order&id=${orderId}`);
                const data = await response.json();
                
                if (data.success) {
                    displayOrderDetails(data.order);
                    new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading order details:', error);
                showAlert('Error loading order details', 'danger');
            }
        }

        // displayOrderDetails function
        function displayOrderDetails(order) {
            const detailsHtml = `
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 style="color: #333; margin-bottom: 15px; font-weight: 600;">📋 Order Information</h6>
                            <table class="table table-sm" style="background: white; border: none;">
                                <tr><td style="border: none; padding: 8px 0;"><strong>Order Number:</strong></td><td style="border: none; padding: 8px 0;">${order.order_number}</td></tr>
                                <tr><td style="border: none; padding: 8px 0;"><strong>Status:</strong></td><td style="border: none; padding: 8px 0;"><span class="status-badge status-${order.status}">${order.status.toUpperCase()}</span></td></tr>
                                <tr><td style="border: none; padding: 8px 0;"><strong>Payment Status:</strong></td><td style="border: none; padding: 8px 0;"><span class="status-badge payment-${order.payment_status}">${order.payment_status.toUpperCase()}</span></td></tr>
                                <tr><td style="border: none; padding: 8px 0;"><strong>Payment Method:</strong></td><td style="border: none; padding: 8px 0;">${order.payment_method || 'N/A'}</td></tr>
                                <tr><td style="border: none; padding: 8px 0;"><strong>Order Date:</strong></td><td style="border: none; padding: 8px 0;">${new Date(order.created_at).toLocaleString()}</td></tr>
                                ${order.referral_code ? `<tr><td style="border: none; padding: 8px 0;"><strong>Referral Code:</strong></td><td style="border: none; padding: 8px 0;">${order.referral_code}</td></tr>` : ''}
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 style="color: #333; margin-bottom: 15px; font-weight: 600;">👤 Customer Information</h6>
                            ${order.customer_name ? `
                                <div style="display: flex; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
                                    ${order.customer_profile_image 
                                        ? `<img src="${order.customer_profile_image}" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 15px; object-fit: cover;">`
                                        : `<div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-right: 15px;">${order.customer_name.charAt(0).toUpperCase()}</div>`
                                    }
                                    <div>
                                        <div style="font-weight: 600; color: #333;">${order.customer_name}</div>
                                        <div style="color: #666; font-size: 14px;">${order.customer_email}</div>
                                    </div>
                                </div>
                            ` : '<p style="color: #666; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; margin: 0;">Guest Order</p>'}
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6 style="color: #333; margin-bottom: 15px; font-weight: 600;">📦 Shipping Address</h6>
                            ${order.shipping_address ? `
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
                                    <address style="margin: 0; line-height: 1.6; color: #555;">
                                        <strong>${order.shipping_address.first_name} ${order.shipping_address.last_name}</strong><br>
                                        ${order.shipping_address.address}<br>
                                        ${order.shipping_address.apartment ? `${order.shipping_address.apartment}<br>` : ''}
                                        ${order.shipping_address.city}, ${order.shipping_address.state} ${order.shipping_address.pincode}<br>
                                        ${order.shipping_address.country}<br>
                                        ${order.shipping_address.phone ? `📞 ${order.shipping_address.phone}` : ''}
                                    </address>
                                </div>
                            ` : '<p style="color: #666; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; margin: 0;">No shipping address</p>'}
                        </div>
                        <div class="col-md-6">
                            <h6 style="color: #333; margin-bottom: 15px; font-weight: 600;">💰 Order Summary</h6>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
                                <table style="width: 100%; border: none; margin: 0;">
                                    <tr><td style="border: none; padding: 5px 0; color: #666;">Subtotal:</td><td style="border: none; padding: 5px 0; text-align: right; color: #333; font-weight: 500;">₹${parseFloat(order.total_amount).toFixed(2)}</td></tr>
                                    ${order.tax_amount > 0 ? `<tr><td style="border: none; padding: 5px 0; color: #666;">Tax:</td><td style="border: none; padding: 5px 0; text-align: right; color: #333; font-weight: 500;">₹${parseFloat(order.tax_amount).toFixed(2)}</td></tr>` : ''}
                                    ${order.shipping_amount > 0 ? `<tr><td style="border: none; padding: 5px 0; color: #666;">Shipping:</td><td style="border: none; padding: 5px 0; text-align: right; color: #333; font-weight: 500;">₹${parseFloat(order.shipping_amount).toFixed(2)}</td></tr>` : '<tr><td style="border: none; padding: 5px 0; color: #666;">Shipping:</td><td style="border: none; padding: 5px 0; text-align: right; color: #28a745; font-weight: 600;">FREE</td></tr>'}
                                    ${order.wallet_points_used > 0 ? `<tr><td style="border: none; padding: 5px 0; color: #666;">💰 Wallet Points Used:</td><td style="border: none; padding: 5px 0; text-align: right; color: #28a745; font-weight: 600;">-₹${parseFloat(order.wallet_points_used).toFixed(2)}</td></tr>` : ''}
                                    <tr style="border-top: 2px solid #667eea;"><td style="border: none; padding: 10px 0 5px 0; color: #333; font-weight: 700; font-size: 16px;">Final Amount:</td><td style="border: none; padding: 10px 0 5px 0; text-align: right; color: #28a745; font-weight: 700; font-size: 18px;">₹${parseFloat(order.final_amount).toFixed(2)}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6 style="color: #333; margin-bottom: 15px; font-weight: 600;">📦 Order Items</h6>
                        <div style="background: white; border-radius: 8px; overflow: hidden; border: 1px solid #eee; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <table style="width: 100%; border-collapse: collapse; margin: 0;">
                                <thead>
                                    <tr style="background: #667eea; color: white;">
                                        <th style="border: none; padding: 12px; text-align: left; font-weight: 600;">Product</th>
                                        <th style="border: none; padding: 12px; text-align: center; font-weight: 600;">Qty</th>
                                        <th style="border: none; padding: 12px; text-align: right; font-weight: 600;">Price</th>
                                        <th style="border: none; padding: 12px; text-align: right; font-weight: 600;">Size</th>
                                        <th style="border: none; padding: 12px; text-align: right; font-weight: 600;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${order.items && order.items.length > 0 ? order.items.map((item, index) => `
                                        <tr style="border-bottom: 1px solid #f0f0f0; ${index % 2 === 0 ? 'background: #fafafa;' : 'background: white;'}">
                                            <td style="border: none; padding: 12px;">
                                                <div style="font-weight: 600; color: #333; margin-bottom: 3px;">${item.product_name}</div>
                                                <div style="font-size: 12px; color: #666;">ID: ${item.product_id}</div>
                                            </td>
                                            <td style="border: none; padding: 12px; text-align: center; color: #666; font-weight: 500;">${item.quantity}</td>
                                            <td style="border: none; padding: 12px; text-align: right; color: #333; font-weight: 500;">₹${parseFloat(item.product_price).toFixed(2)}</td>
                                            <td style="border: none; padding: 12px; text-align: right; color: #666;">${item.size || 'N/A'}</td>
                                            <td style="border: none; padding: 12px; text-align: right; font-weight: 600; color: #333;">₹${parseFloat(item.total_price).toFixed(2)}</td>
                                        </tr>
                                    `).join('') : '<tr><td colspan="5" style="text-align: center; padding: 30px; color: #666; background: white;">No items found</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="margin-top: 30px; text-align: center; padding-top: 20px; border-top: 1px solid #eee;">
                        <a href="javascript:void(0)" onclick="window.open('/invoice.php?order_id=${order.id}', '_blank')" 
                        style="display: inline-block; background: #28a745; color: white; text-decoration: none; padding: 10px 20px; border-radius: 6px; margin: 0 10px; font-weight: 500;">
                            📄 Download Invoice
                        </a>
                        <button type="button" onclick="printOrder()" 
                                style="background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 6px; margin: 0 10px; font-weight: 500; cursor: pointer;">
                            🖨️ Print Order
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('orderDetailsBody').innerHTML = detailsHtml;
        }

        // Update order status
        function updateStatus(orderId, orderNumber, currentStatus) {
            document.getElementById('updateOrderId').value = orderId;
            document.getElementById('updateOrderNumber').value = orderNumber;
            document.getElementById('currentStatus').value = currentStatus.toUpperCase();
            document.getElementById('newStatus').value = '';
            document.getElementById('statusNotes').value = '';
            
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        // Handle status update
        async function handleStatusUpdate(e) {
            e.preventDefault();
            
            const orderId = document.getElementById('updateOrderId').value;
            const newStatus = document.getElementById('newStatus').value;
            const notes = document.getElementById('statusNotes').value;
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('order_id', orderId);
                formData.append('status', newStatus);
                formData.append('notes', notes);
                
                const response = await fetch('api/manage-orders.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Order status updated successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('updateStatusModal')).hide();
                    loadOrders();
                    loadStats();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showAlert('Error updating order status', 'danger');
            }
        }

        // Print order
        function printOrder() {
            window.print();
        }

        // Export orders
        async function exportOrders() {
            try {
                const params = new URLSearchParams({
                    action: 'export_orders',
                    ...currentFilters
                });
                
                window.open(`api/manage-orders.php?${params}`, '_blank');
            } catch (error) {
                console.error('Error exporting orders:', error);
                showAlert('Error exporting orders', 'danger');
            }
        }

        // Show/hide loading
        function showLoading(show) {
            const loading = document.getElementById('tableLoading');
            const table = document.getElementById('ordersTable');
            
            if (show) {
                loading.style.display = 'block';
                table.style.display = 'none';
            } else {
                loading.style.display = 'none';
                table.style.display = 'table';
            }
        }

        // Show alert
        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
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